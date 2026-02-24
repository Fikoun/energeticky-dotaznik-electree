<?php
/**
 * Raynet Company Entity
 * 
 * Represents a company/client in Raynet CRM.
 */

namespace Raynet;

class RaynetCompany extends RaynetEntity
{
    /**
     * Company states
     */
    public const STATE_POTENTIAL = 'A_POTENTIAL';
    public const STATE_ACTUAL = 'B_ACTUAL';
    public const STATE_DEFERRED = 'C_DEFERRED';
    public const STATE_UNATTRACTIVE = 'D_UNATTRACTIVE';
    
    /**
     * Company roles
     */
    public const ROLE_SUBSCRIBER = 'A_SUBSCRIBER';
    public const ROLE_PARTNER = 'B_PARTNER';
    public const ROLE_SUPPLIER = 'C_SUPPLIER';
    public const ROLE_RIVAL = 'D_RIVAL';
    
    /**
     * Company ratings
     */
    public const RATING_A = 'A';
    public const RATING_B = 'B';
    public const RATING_C = 'C';
    
    protected function getEndpoint(): string
    {
        return '/company/';
    }
    
    protected function getRequiredFields(): array
    {
        return ['name', 'rating', 'state', 'role'];
    }
    
    protected function getExtIdPrefix(): string
    {
        return 'energyforms';
    }
    
    /**
     * Transform EnergyForms data to Raynet Company format
     * 
     * @param array $formData Form data from EnergyForms
     * @param string|int $formId Form ID
     * @param array|null $customFields Optional custom fields payload (from RaynetCustomFields::buildCustomFieldsPayload)
     */
    public function fromFormData(array $formData, string|int $formId, ?array $customFields = null): self
    {
        // Parse form data (may be nested in 'form_data' JSON)
        $data = $this->parseFormData($formData);
        
        // Build company data
        $companyData = [
            'name' => $data['companyName'] ?? 'Neznámá firma',
            'regNumber' => $data['ico'] ?? null,
            'taxNumber' => $data['dic'] ?? null,
            'rating' => self::RATING_A,
            'state' => self::STATE_POTENTIAL,
            'role' => self::ROLE_SUBSCRIBER,
            'notice' => $this->buildNotice($data, $formId),
            'extId' => $this->generateExtId($formId)
        ];
        
        // Add address if available
        $address = $this->buildAddress($data);
        if ($address) {
            $companyData['addresses'] = [$address];
        }
        
        // Add custom fields if provided
        if (!empty($customFields)) {
            $companyData['customFields'] = $customFields;
        }
        
        // Remove null values (but keep customFields even if some are null)
        $companyData = array_filter($companyData, fn($v) => $v !== null);
        
        $this->data = $companyData;
        $this->extId = $companyData['extId'];
        
        return $this;
    }
    
    /**
     * Set custom fields for the company
     * 
     * @param array $customFields Custom fields payload
     */
    public function setCustomFields(array $customFields): self
    {
        if (!empty($customFields)) {
            $this->data['customFields'] = $customFields;
        }
        return $this;
    }
    
    /**
     * Get custom fields from company data
     */
    public function getCustomFields(): array
    {
        return $this->data['customFields'] ?? [];
    }
    
    /**
     * Find company by IČO (registration number)
     */
    public function findByIco(string $ico): ?self
    {
        $results = $this->search([
            'regNumber' => ['EQ' => $ico]
        ], 1);
        
        return $results[0] ?? null;
    }
    
    /**
     * Find company by name (partial match)
     */
    public function findByName(string $name): array
    {
        return $this->search([
            'name' => ['LIKE' => "%{$name}%"]
        ]);
    }
    
    /**
     * Find existing company by external ID or IČO.
     *
     * Each lookup is isolated; a failure in one strategy does not prevent
     * the next from running (important when extId is unknown in Raynet).
     */
    public function findExisting(string $extId, ?string $ico = null): ?self
    {
        // Strategy 1: external ID
        try {
            $byExtId = $this->findByExtId($extId);
            if ($byExtId) {
                return $byExtId;
            }
        } catch (\Throwable $e) {
            error_log("RaynetCompany::findExisting – extId lookup failed: " . $e->getMessage());
        }

        // Strategy 2: IČO
        if ($ico) {
            try {
                $byIco = $this->findByIco($ico);
                if ($byIco) {
                    return $byIco;
                }
            } catch (\Throwable $e) {
                error_log("RaynetCompany::findExisting – IČO lookup failed: " . $e->getMessage());
            }
        }

        return null;
    }
    
    /**
     * Smart sync – find by configurable strategies, then update or create.
     *
     * Pass a pre-configured $checker to override the default strategies.
     */
    public function smartSync(array $formData, string|int $formId, ?RaynetDuplicateChecker $checker = null): self
    {
        $this->fromFormData($formData, $formId);

        $extId  = $this->generateExtId($formId);
        $data   = $this->parseFormData($formData);

        $checker = $checker ?? new RaynetDuplicateChecker($this->client);

        $existing = $checker->findExistingCompany($extId, [
            'ico'       => $data['ico'] ?? ($this->data['regNumber'] ?? null),
            'taxNumber' => $data['dic'] ?? ($this->data['taxNumber'] ?? null),
            'name'      => $data['companyName'] ?? ($this->data['name'] ?? null),
        ]);

        if ($existing) {
            $this->id = $existing['id'];
            // Don't overwrite extId when matched by ICO/name (company may be linked to other forms)
            if ($existing['matched_by'] !== 'extId') {
                unset($this->data['extId']);
            }
            error_log("Raynet: Updated existing company {$this->id} (matched by '{$existing['matched_by']}').");
            return $this->update();
        }

        error_log("Raynet: Created new company.");
        return $this->create();
    }
    
    /**
     * Smart sync with custom fields.
     *
     * Pass a pre-configured $checker to override the default strategies.
     */
    public function smartSyncWithCustomFields(
        array $formData,
        string|int $formId,
        array $customFields = [],
        ?RaynetDuplicateChecker $checker = null
    ): self {
        $this->fromFormData($formData, $formId, $customFields);

        $extId = $this->generateExtId($formId);
        $data  = $this->parseFormData($formData);

        $checker = $checker ?? new RaynetDuplicateChecker($this->client);

        $existing = $checker->findExistingCompany($extId, [
            'ico'       => $data['ico'] ?? ($this->data['regNumber'] ?? null),
            'taxNumber' => $data['dic'] ?? ($this->data['taxNumber'] ?? null),
            'name'      => $data['companyName'] ?? ($this->data['name'] ?? null),
        ]);

        if ($existing) {
            $this->id = $existing['id'];
            if ($existing['matched_by'] !== 'extId') {
                unset($this->data['extId']);
            }
            error_log("Raynet: Updated existing company {$this->id} (matched by '{$existing['matched_by']}').");
            return $this->update();
        }

        error_log("Raynet: Created new company.");
        return $this->create();
    }
    
    /**
     * Link to an existing Raynet company (admin-selected)
     * Updates the existing company with new form data instead of creating new
     */
    public function linkToExisting(int $targetCompanyId, array $formData, string|int $formId): self
    {
        // First, prepare the data from the form
        $this->fromFormData($formData, $formId);
        
        // Set the ID to the admin-selected company
        $this->id = $targetCompanyId;
        
        // Don't overwrite extId - the company might be linked to other forms
        // Just add a note about this new link
        $existingNotice = '';
        
        // Try to fetch existing company data
        try {
            $response = $this->client->get($this->getEndpoint() . $targetCompanyId);
            if ($response && isset($response['data'])) {
                $existingNotice = $response['data']['notice'] ?? '';
            }
        } catch (\Exception $e) {
            // Ignore, we'll just create fresh notice
        }
        
        // Append to existing notice
        $newNotice = $this->buildNotice($this->parseFormData($formData), $formId);
        if ($existingNotice) {
            $this->data['notice'] = $existingNotice . "\n\n---\n" . $newNotice;
        }
        
        // Update the company
        return $this->update();
    }

    /**
     * Parse form data from various formats
     */
    private function parseFormData(array $formData): array
    {
        // If form_data is a JSON string, decode it
        if (isset($formData['form_data']) && is_string($formData['form_data'])) {
            $decoded = json_decode($formData['form_data'], true);
            if ($decoded) {
                return array_merge($formData, $decoded);
            }
        }
        
        // If form_data is already an array
        if (isset($formData['form_data']) && is_array($formData['form_data'])) {
            return array_merge($formData, $formData['form_data']);
        }
        
        return $formData;
    }
    
    /**
     * Build address structure for Raynet
     */
    private function buildAddress(array $data): ?array
    {
        // Check if we have any address data
        $hasAddress = !empty($data['address']) || !empty($data['city']) || !empty($data['zipCode']);
        $hasContact = !empty($data['email']) || !empty($data['phone']);
        
        if (!$hasAddress && !$hasContact) {
            return null;
        }
        
        $address = [
            'address' => [
                'name' => 'Hlavní adresa',
                'street' => $data['address'] ?? $data['street'] ?? '',
                'city' => $data['city'] ?? '',
                'zipCode' => $data['zipCode'] ?? $data['psc'] ?? '',
                'country' => 'CZ'
            ],
            'contactInfo' => []
        ];
        
        // Add contact info
        if (!empty($data['email'])) {
            $address['contactInfo']['email'] = $data['email'];
        }
        
        if (!empty($data['phone'])) {
            $address['contactInfo']['tel1'] = $data['phone'];
            $address['contactInfo']['tel1Type'] = 'mobil';
        }
        
        if (!empty($data['website'])) {
            $address['contactInfo']['www'] = $data['website'];
        }
        
        return $address;
    }
    
    /**
     * Build notice text with form details
     */
    private function buildNotice(array $data, string|int $formId): string
    {
        $lines = [
            "Importováno z EnergyForms",
            "Form ID: {$formId}",
            "Datum importu: " . date('d.m.Y H:i')
        ];
        
        // Add project type if available
        if (!empty($data['projectType'])) {
            $lines[] = "Typ projektu: {$data['projectType']}";
        }
        
        // Add installation details if available
        if (!empty($data['installedPower'])) {
            $lines[] = "Instalovaný výkon: {$data['installedPower']} kW";
        }
        
        if (!empty($data['batteryCapacity'])) {
            $lines[] = "Kapacita baterie: {$data['batteryCapacity']} kWh";
        }
        
        return implode("\n", $lines);
    }
}
