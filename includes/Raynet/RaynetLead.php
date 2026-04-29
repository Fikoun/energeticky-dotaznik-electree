<?php
/**
 * Raynet Lead Entity
 *
 * Represents a lead (Leady) in Raynet CRM.
 * A lead is always created as a new record, even when a duplicate is detected.
 * Duplicates trigger an admin notification but do not block creation.
 */

namespace Raynet;

class RaynetLead extends RaynetEntity
{
    /**
     * Lead priorities
     */
    public const PRIORITY_MINOR    = 'MINOR';
    public const PRIORITY_DEFAULT  = 'DEFAULT';
    public const PRIORITY_CRITICAL = 'CRITICAL';

    /**
     * Lead statuses (read-only, set by Raynet)
     */
    public const STATUS_ACTIVE    = 'B_ACTIVE';
    public const STATUS_CANCELLED = 'G_STORNO';
    public const STATUS_DONE      = 'D_DONE';

    protected function getEndpoint(): string
    {
        return '/lead/';
    }

    protected function getRequiredFields(): array
    {
        return ['topic', 'priority'];
    }

    protected function getExtIdPrefix(): string
    {
        // Results in extId = "energyforms:lead:<formId>"
        // Raynet interprets this as code="energyforms", value="lead:<formId>"
        return 'energyforms:lead';
    }

    /**
     * Transform EnergyForms data to Raynet Lead format.
     *
     * @param array       $formData        Parsed form data (may contain nested form_data JSON).
     * @param string|int  $formId          EnergyForms form ID.
     * @param int|null    $raynetCompanyId Raynet company ID already synced for this form (for notice linkage).
     */
    public function fromFormData(array $formData, string|int $formId, ?int $raynetCompanyId = null): self
    {
        $data = $this->parseFormData($formData);

        $companyName = $data['companyName'] ?? 'Neznámý zákazník';

        $leadData = [
            'topic'      => 'Bateriové úložiště – ' . $companyName,
            'priority'   => self::PRIORITY_DEFAULT,
            'companyName' => $companyName,
            'regNumber'  => $data['ico'] ?? null,
            'leadDate'   => date('Y-m-d'),
            'notice'     => $this->buildNotice($data, $formId, $raynetCompanyId),
        ];

        // Contact person
        $names = $this->parseContactName($data['contactPerson'] ?? '');
        if (!empty($names['firstName'])) {
            $leadData['firstName'] = $names['firstName'];
        }
        if (!empty($names['lastName'])) {
            $leadData['lastName'] = $names['lastName'];
        }

        // Contact info
        $contactInfo = [];
        if (!empty($data['email'])) {
            $contactInfo['email'] = $data['email'];
        }
        if (!empty($data['phone'])) {
            $contactInfo['tel1']     = $data['phone'];
            $contactInfo['tel1Type'] = 'mobil';
        }
        if (!empty($contactInfo)) {
            $leadData['contactInfo'] = $contactInfo;
        }

        // Address
        $address = $this->buildAddress($data);
        if ($address) {
            $leadData['address'] = $address;
        }

        // Remove null values
        $leadData = array_filter($leadData, fn($v) => $v !== null);

        $this->data  = $leadData;
        $this->extId = $this->generateExtId($formId);

        return $this;
    }

    /**
     * Find an existing lead by IČO (registration number).
     */
    public function findByIco(string $ico): ?self
    {
        $results = $this->search([
            'regNumber' => ['EQ' => $ico],
            'status'    => ['EQ' => self::STATUS_ACTIVE],
        ], 1);

        return $results[0] ?? null;
    }

    /**
     * Find an existing lead by e-mail.
     */
    public function findByEmail(string $email): ?self
    {
        $results = $this->search([
            'contactInfo.email' => ['EQ' => $email],
            'status'            => ['EQ' => self::STATUS_ACTIVE],
        ], 1);

        return $results[0] ?? null;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function parseFormData(array $formData): array
    {
        if (isset($formData['form_data']) && is_string($formData['form_data'])) {
            $decoded = json_decode($formData['form_data'], true);
            if ($decoded) {
                return array_merge($formData, $decoded);
            }
        }

        if (isset($formData['form_data']) && is_array($formData['form_data'])) {
            return array_merge($formData, $formData['form_data']);
        }

        return $formData;
    }

    private function parseContactName(string $fullName): array
    {
        $fullName = trim($fullName);

        if (empty($fullName)) {
            return ['firstName' => '', 'lastName' => ''];
        }

        $parts = explode(' ', $fullName, 2);

        return [
            'firstName' => $parts[0],
            'lastName'  => $parts[1] ?? '',
        ];
    }

    private function buildAddress(array $data): ?array
    {
        $hasAddress = !empty($data['address']) || !empty($data['city']) || !empty($data['zipCode']);

        if (!$hasAddress) {
            return null;
        }

        return [
            'street'  => $data['address'] ?? $data['street'] ?? '',
            'city'    => $data['city'] ?? '',
            'zipCode' => $data['zipCode'] ?? $data['psc'] ?? '',
            'country' => 'CZ',
        ];
    }

    private function buildNotice(array $data, string|int $formId, ?int $raynetCompanyId): string
    {
        $lines = [
            'Importováno z EnergyForms',
            "Form ID: {$formId}",
            "Datum importu: " . date('d.m.Y H:i'),
        ];

        if ($raynetCompanyId !== null) {
            $lines[] = "Propojená firma v Raynet (ID): {$raynetCompanyId}";
        }

        if (!empty($data['projectType'])) {
            $lines[] = "Typ projektu: {$data['projectType']}";
        }

        if (!empty($data['installedPower'])) {
            $lines[] = "Instalovaný výkon: {$data['installedPower']} kW";
        }

        if (!empty($data['batteryCapacity'])) {
            $lines[] = "Kapacita baterie: {$data['batteryCapacity']} kWh";
        }

        return implode("\n", $lines);
    }
}
