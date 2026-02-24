<?php
/**
 * Raynet Duplicate Checker
 *
 * Provides configurable, strategy-based duplicate detection for Person and Company
 * entities before creating new records in Raynet CRM.
 *
 * Each entity type has its own set of independent strategies that can be
 * individually enabled or disabled. Strategies are evaluated in priority order
 * and the first match wins.
 *
 * PERSON strategies (in priority order):
 *   'extId' – match by external ID (energyforms:<formId>)
 *   'email' – exact match on contactInfo.email
 *   'phone' – exact match on contactInfo.tel1
 *   'name'  – exact first+last name match
 *
 * COMPANY strategies (in priority order):
 *   'extId'     – match by external ID
 *   'ico'       – exact match on regNumber (IČO)
 *   'taxNumber' – exact match on taxNumber (DIČ)
 *   'name'      – LIKE match on company name
 */

namespace Raynet;

class RaynetDuplicateChecker
{
    private RaynetApiClient $client;

    /**
     * Person duplicate strategies.
     * Key → enabled flag. Order defines evaluation priority.
     */
    private array $personStrategies = [
        'extId' => true,
        'email' => true,
        'phone' => false,
        'name'  => false,
    ];

    /**
     * Company duplicate strategies.
     * Key → enabled flag. Order defines evaluation priority.
     */
    private array $companyStrategies = [
        'extId'     => true,
        'ico'       => true,
        'taxNumber' => false,
        'name'      => false,
    ];

    public function __construct(RaynetApiClient $client)
    {
        $this->client = $client;
    }

    // -------------------------------------------------------------------------
    // Strategy configuration
    // -------------------------------------------------------------------------

    /**
     * Enable or disable a single person duplicate strategy.
     *
     * @param string $strategy  One of: extId, email, phone, name
     * @param bool   $enabled
     */
    public function setPersonStrategy(string $strategy, bool $enabled): self
    {
        if (!array_key_exists($strategy, $this->personStrategies)) {
            throw new \InvalidArgumentException(
                "Unknown person strategy '{$strategy}'. Valid: " . implode(', ', array_keys($this->personStrategies))
            );
        }
        $this->personStrategies[$strategy] = $enabled;
        return $this;
    }

    /**
     * Enable or disable a single company duplicate strategy.
     *
     * @param string $strategy  One of: extId, ico, taxNumber, name
     * @param bool   $enabled
     */
    public function setCompanyStrategy(string $strategy, bool $enabled): self
    {
        if (!array_key_exists($strategy, $this->companyStrategies)) {
            throw new \InvalidArgumentException(
                "Unknown company strategy '{$strategy}'. Valid: " . implode(', ', array_keys($this->companyStrategies))
            );
        }
        $this->companyStrategies[$strategy] = $enabled;
        return $this;
    }

    /**
     * Bulk configure person strategies.
     * Example: ['email' => true, 'phone' => false]
     */
    public function configurePersonStrategies(array $map): self
    {
        foreach ($map as $strategy => $enabled) {
            $this->setPersonStrategy($strategy, (bool) $enabled);
        }
        return $this;
    }

    /**
     * Bulk configure company strategies.
     * Example: ['ico' => true, 'name' => true, 'taxNumber' => false]
     */
    public function configureCompanyStrategies(array $map): self
    {
        foreach ($map as $strategy => $enabled) {
            $this->setCompanyStrategy($strategy, (bool) $enabled);
        }
        return $this;
    }

    /**
     * Return current person strategy configuration.
     */
    public function getPersonStrategies(): array
    {
        return $this->personStrategies;
    }

    /**
     * Return current company strategy configuration.
     */
    public function getCompanyStrategies(): array
    {
        return $this->companyStrategies;
    }

    // -------------------------------------------------------------------------
    // Person duplicate lookup
    // -------------------------------------------------------------------------

    /**
     * Look for an existing Person in Raynet using the enabled strategies.
     *
     * Returns a result array on first match, null if nothing found.
     *
     * Result shape:
     * [
     *   'id'          => int,
     *   'matched_by'  => string,   // strategy name that produced the match
     *   'data'        => array,    // raw Raynet entity data
     * ]
     *
     * @param string $extId    External ID to check (energyforms:person:<formId>)
     * @param array  $data     Normalised person data:
     *                         email, phone, firstName, lastName
     */
    public function findExistingPerson(string $extId, array $data): ?array
    {
        $personEntity = new RaynetPerson($this->client);

        // Strategy: extId
        if ($this->personStrategies['extId']) {
            $found = $this->safeCallFind(fn() => $personEntity->findByExtId($extId));
            if ($found) {
                return $this->buildResult($found, 'extId');
            }
        }

        // Strategy: email
        if ($this->personStrategies['email'] && !empty($data['email'])) {
            $matches = $this->safeSearch(fn() => $personEntity->search(
                ['contactInfo.email' => ['EQ' => $data['email']]],
                1
            ));
            if (!empty($matches)) {
                return $this->buildResult($matches[0], 'email');
            }
        }

        // Strategy: phone
        if ($this->personStrategies['phone'] && !empty($data['phone'])) {
            $normalized = $this->normalizePhone($data['phone']);
            if ($normalized) {
                $matches = $this->safeSearch(fn() => $personEntity->search(
                    ['contactInfo.tel1' => ['EQ' => $normalized]],
                    1
                ));
                if (!empty($matches)) {
                    return $this->buildResult($matches[0], 'phone');
                }
            }
        }

        // Strategy: name (exact first+last)
        if ($this->personStrategies['name'] && !empty($data['firstName']) && !empty($data['lastName'])) {
            $matches = $this->safeSearch(fn() => $personEntity->search(
                [
                    'firstName' => ['EQ' => $data['firstName']],
                    'lastName'  => ['EQ' => $data['lastName']],
                ],
                5
            ));
            if (!empty($matches)) {
                // Prefer exact full-name match to reduce false positives
                foreach ($matches as $candidate) {
                    $candidateData = $candidate->getData();
                    if (
                        strtolower($candidateData['firstName'] ?? '') === strtolower($data['firstName']) &&
                        strtolower($candidateData['lastName'] ?? '')  === strtolower($data['lastName'])
                    ) {
                        return $this->buildResult($candidate, 'name');
                    }
                }
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Company duplicate lookup
    // -------------------------------------------------------------------------

    /**
     * Look for an existing Company in Raynet using the enabled strategies.
     *
     * Result shape:
     * [
     *   'id'          => int,
     *   'matched_by'  => string,
     *   'data'        => array,
     * ]
     *
     * @param string      $extId     External ID (energyforms:<formId>)
     * @param array       $data      Normalised company data: ico, taxNumber, name
     */
    public function findExistingCompany(string $extId, array $data): ?array
    {
        $companyEntity = new RaynetCompany($this->client);

        // Strategy: extId
        if ($this->companyStrategies['extId']) {
            $found = $this->safeCallFind(fn() => $companyEntity->findByExtId($extId));
            if ($found) {
                return $this->buildResult($found, 'extId');
            }
        }

        // Strategy: ico
        if ($this->companyStrategies['ico'] && !empty($data['ico'])) {
            $found = $this->safeCallFind(fn() => $companyEntity->findByIco($data['ico']));
            if ($found) {
                return $this->buildResult($found, 'ico');
            }
        }

        // Strategy: taxNumber
        if ($this->companyStrategies['taxNumber'] && !empty($data['taxNumber'])) {
            $matches = $this->safeSearch(fn() => $companyEntity->search(
                ['taxNumber' => ['EQ' => $data['taxNumber']]],
                1
            ));
            if (!empty($matches)) {
                return $this->buildResult($matches[0], 'taxNumber');
            }
        }

        // Strategy: name (LIKE match, case-insensitive)
        if ($this->companyStrategies['name'] && !empty($data['name'])) {
            $matches = $this->safeSearch(fn() => $companyEntity->search(
                ['name' => ['LIKE' => '%' . $data['name'] . '%']],
                5
            ));
            if (!empty($matches)) {
                // Prefer exact name match to avoid false positives on LIKE
                foreach ($matches as $candidate) {
                    $candidateData = $candidate->getData();
                    if (strtolower($candidateData['name'] ?? '') === strtolower($data['name'])) {
                        return $this->buildResult($candidate, 'name_exact');
                    }
                }
                // Fall back to best LIKE match
                return $this->buildResult($matches[0], 'name_like');
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the standard match-result array from an entity.
     */
    private function buildResult(RaynetEntity $entity, string $matchedBy): array
    {
        return [
            'id'         => $entity->getId(),
            'matched_by' => $matchedBy,
            'data'       => $entity->getData(),
        ];
    }

    /**
     * Safely call a find function that may throw (e.g., on API errors).
     * Returns null instead of propagating exceptions.
     */
    private function safeCallFind(callable $fn): ?RaynetEntity
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log("RaynetDuplicateChecker safeCallFind error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Safely call a search function that may throw.
     * Returns an empty array instead of propagating exceptions.
     */
    private function safeSearch(callable $fn): array
    {
        try {
            return $fn() ?? [];
        } catch (\Throwable $e) {
            error_log("RaynetDuplicateChecker safeSearch error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Normalize a phone number to a consistent format for comparison.
     * Strips spaces, dashes, and leading zeros; adds country code if missing.
     */
    private function normalizePhone(string $phone): ?string
    {
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $phone);

        if (empty($cleaned)) {
            return null;
        }

        // Normalize Czech numbers: 069… → +420 69…
        if (preg_match('/^0(\d{9})$/', $cleaned, $m)) {
            return '+420' . $m[1];
        }

        return $cleaned;
    }
}
