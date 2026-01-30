<?php
/**
 * Abstract Base Entity for Raynet CRM
 * 
 * All Raynet entities (Company, Person, Lead, etc.) extend this class.
 * Provides common CRUD operations and external ID management.
 */

namespace Raynet;

abstract class RaynetEntity
{
    protected RaynetApiClient $client;
    protected ?int $id = null;
    protected ?string $extId = null;
    protected array $data = [];
    
    /**
     * Get the API endpoint for this entity (e.g., '/company/', '/person/')
     */
    abstract protected function getEndpoint(): string;
    
    /**
     * Get required fields for creating this entity
     */
    abstract protected function getRequiredFields(): array;
    
    /**
     * Get the external ID prefix for this entity type
     */
    abstract protected function getExtIdPrefix(): string;
    
    /**
     * Transform form data to Raynet API format
     */
    abstract public function fromFormData(array $formData, string|int $formId): self;
    
    public function __construct(RaynetApiClient $client)
    {
        $this->client = $client;
    }
    
    /**
     * Set entity data
     */
    public function setData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }
    
    /**
     * Get entity data
     */
    public function getData(): array
    {
        return $this->data;
    }
    
    /**
     * Set Raynet internal ID
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    /**
     * Get Raynet internal ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }
    
    /**
     * Set external ID (for linking with EnergyForms)
     */
    public function setExtId(string $extId): self
    {
        $this->extId = $extId;
        $this->data['extId'] = $extId;
        return $this;
    }
    
    /**
     * Get external ID
     */
    public function getExtId(): ?string
    {
        return $this->extId;
    }
    
    /**
     * Generate external ID from form ID
     */
    public function generateExtId(string|int $formId): string
    {
        return $this->getExtIdPrefix() . ':' . $formId;
    }
    
    /**
     * Find entity by external ID
     */
    public function findByExtId(string $extId): ?self
    {
        $endpoint = $this->getEndpoint() . "ext/{$extId}/";
        $result = $this->client->get($endpoint);
        
        if ($result && isset($result['data'])) {
            return $this->hydrateFromApiResponse($result['data']);
        }
        
        return null;
    }
    
    /**
     * Find entity by Raynet ID
     */
    public function findById(int $id): ?self
    {
        $endpoint = $this->getEndpoint() . "{$id}/";
        $result = $this->client->get($endpoint);
        
        if ($result && isset($result['data'])) {
            return $this->hydrateFromApiResponse($result['data']);
        }
        
        return null;
    }
    
    /**
     * Search entities with filters
     * 
     * @param array $filters Associative array of field => [operator => value]
     *                       e.g., ['regNumber' => ['EQ' => '12345678']]
     */
    public function search(array $filters, int $limit = 20, int $offset = 0): array
    {
        $params = [
            'limit' => $limit,
            'offset' => $offset
        ];
        
        // Convert filters to Raynet query format
        foreach ($filters as $field => $conditions) {
            foreach ($conditions as $operator => $value) {
                $params["{$field}[{$operator}]"] = $value;
            }
        }
        
        $result = $this->client->get($this->getEndpoint(), $params);
        
        if (!$result || !isset($result['data'])) {
            return [];
        }
        
        $entities = [];
        foreach ($result['data'] as $item) {
            $entity = $this->createNew();
            $entity->hydrateFromApiResponse($item);
            $entities[] = $entity;
        }
        
        return $entities;
    }
    
    /**
     * Create entity in Raynet
     */
    public function create(): self
    {
        $this->validateForCreate();
        
        $result = $this->client->put($this->getEndpoint(), $this->data);
        
        if (!$result || !isset($result['data']['id'])) {
            throw new RaynetException("Failed to create entity: no ID returned");
        }
        
        $this->id = $result['data']['id'];
        
        error_log("Raynet: Created {$this->getEndpoint()} with ID: {$this->id}");
        
        return $this;
    }
    
    /**
     * Update entity in Raynet
     */
    public function update(): self
    {
        if (!$this->id) {
            throw new RaynetException("Cannot update entity without ID");
        }
        
        $endpoint = $this->getEndpoint() . "{$this->id}/";
        $this->client->post($endpoint, $this->data);
        
        error_log("Raynet: Updated {$this->getEndpoint()}{$this->id}");
        
        return $this;
    }
    
    /**
     * Save entity (create or update)
     */
    public function save(): self
    {
        if ($this->id) {
            return $this->update();
        }
        
        return $this->create();
    }
    
    /**
     * Delete entity from Raynet
     */
    public function delete(): bool
    {
        if (!$this->id) {
            throw new RaynetException("Cannot delete entity without ID");
        }
        
        $endpoint = $this->getEndpoint() . "{$this->id}/";
        $this->client->delete($endpoint);
        
        error_log("Raynet: Deleted {$this->getEndpoint()}{$this->id}");
        
        return true;
    }
    
    /**
     * Find or create entity based on external ID
     */
    public function findOrCreate(string|int $formId): self
    {
        $extId = $this->generateExtId($formId);
        
        // Try to find by external ID
        $existing = $this->findByExtId($extId);
        if ($existing) {
            return $existing;
        }
        
        // Create new
        $this->setExtId($extId);
        return $this->create();
    }
    
    /**
     * Sync entity - find and update or create
     */
    public function sync(string|int $formId): self
    {
        $extId = $this->generateExtId($formId);
        $this->setExtId($extId);
        
        // Try to find by external ID
        $existing = $this->findByExtId($extId);
        
        if ($existing) {
            // Update existing
            $this->id = $existing->getId();
            return $this->update();
        }
        
        // Create new
        return $this->create();
    }
    
    /**
     * Validate data before create
     */
    protected function validateForCreate(): void
    {
        $missing = [];
        
        foreach ($this->getRequiredFields() as $field) {
            if (!isset($this->data[$field]) || $this->data[$field] === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            throw new RaynetValidationException(
                "Missing required fields: " . implode(', ', $missing),
                $missing
            );
        }
    }
    
    /**
     * Create new instance of this entity type
     */
    protected function createNew(): self
    {
        return new static($this->client);
    }
    
    /**
     * Populate entity from API response
     */
    protected function hydrateFromApiResponse(array $data): self
    {
        $this->id = $data['id'] ?? null;
        $this->extId = $data['extId'] ?? null;
        $this->data = $data;
        
        return $this;
    }
}
