<?php
/**
 * Raynet Person Entity
 * 
 * Represents a contact person in Raynet CRM.
 */

namespace Raynet;

class RaynetPerson extends RaynetEntity
{
    protected function getEndpoint(): string
    {
        return '/person/';
    }
    
    protected function getRequiredFields(): array
    {
        return ['lastName'];
    }
    
    protected function getExtIdPrefix(): string
    {
        return 'energyforms:person';
    }
    
    /**
     * Transform EnergyForms data to Raynet Person format
     */
    public function fromFormData(array $formData, string|int $formId): self
    {
        // Parse form data
        $data = $this->parseFormData($formData);
        
        // Parse contact person name
        $names = $this->parseContactName($data['contactPerson'] ?? '');
        
        // Build person data
        $personData = [
            'firstName' => $names['firstName'],
            'lastName' => $names['lastName'],
            'extId' => $this->generateExtId($formId),
            'securityLevel' => 1  // Required field: 1 = standard access level
        ];
        
        // Add contact info
        $contactInfo = [];
        
        if (!empty($data['email'])) {
            $contactInfo['email'] = $data['email'];
        }
        
        if (!empty($data['phone'])) {
            $contactInfo['tel1'] = $data['phone'];
            $contactInfo['tel1Type'] = 'mobil';
        }
        
        if (!empty($contactInfo)) {
            $personData['contactInfo'] = $contactInfo;
        }
        
        // Add notice
        $personData['notice'] = "Kontaktní osoba z EnergyForms\nForm ID: {$formId}";
        
        $this->data = $personData;
        $this->extId = $personData['extId'];
        
        return $this;
    }
    
    /**
     * Link this person to a company
     */
    public function linkToCompany(int $companyId, string $position = 'kontaktní osoba'): bool
    {
        if (!$this->id) {
            throw new RaynetException("Cannot link person without ID - save person first");
        }
        
        $endpoint = "/person/{$this->id}/relationship/";
        
        $relationshipData = [
            'company' => $companyId,
            'type' => $position
        ];
        
        $this->client->put($endpoint, $relationshipData);
        
        error_log("Raynet: Linked person {$this->id} to company {$companyId}");
        
        return true;
    }
    
    /**
     * Get all relationships for this person
     */
    public function getRelationships(): array
    {
        if (!$this->id) {
            return [];
        }
        
        $result = $this->client->get("/person/{$this->id}/relationship/");
        
        return $result['data'] ?? [];
    }
    
    /**
     * Check if person is linked to a specific company
     */
    public function isLinkedToCompany(int $companyId): bool
    {
        $relationships = $this->getRelationships();
        
        foreach ($relationships as $rel) {
            if (isset($rel['company']['id']) && $rel['company']['id'] === $companyId) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Smart sync with company linking
     */
    public function smartSync(array $formData, string|int $formId, ?int $companyId = null, string $position = 'kontaktní osoba'): self
    {
        $this->fromFormData($formData, $formId);
        
        $extId = $this->generateExtId($formId);
        
        // Try to find existing
        $existing = $this->findByExtId($extId);
        
        if ($existing) {
            $this->id = $existing->getId();
            $this->update();
        } else {
            $this->create();
        }
        
        // Link to company if provided
        if ($companyId && !$this->isLinkedToCompany($companyId)) {
            $this->linkToCompany($companyId, $position);
        }
        
        return $this;
    }
    
    /**
     * Parse form data from various formats
     */
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
    
    /**
     * Parse contact person name into first/last name
     */
    private function parseContactName(string $fullName): array
    {
        $fullName = trim($fullName);
        
        if (empty($fullName)) {
            return [
                'firstName' => '',
                'lastName' => 'Neznámý kontakt'
            ];
        }
        
        $parts = preg_split('/\s+/', $fullName, 2);
        
        if (count($parts) === 1) {
            // Only one name - treat as last name
            return [
                'firstName' => '',
                'lastName' => $parts[0]
            ];
        }
        
        return [
            'firstName' => $parts[0],
            'lastName' => $parts[1]
        ];
    }
}
