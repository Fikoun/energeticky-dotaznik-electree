<?php
/**
 * Raynet CRM Connector
 * 
 * Main orchestrator for syncing EnergyForms data to Raynet CRM.
 * Handles complete sync flow: company + contact person + relationship.
 */

namespace Raynet;

require_once __DIR__ . '/RaynetException.php';
require_once __DIR__ . '/RaynetApiClient.php';
require_once __DIR__ . '/RaynetEntity.php';
require_once __DIR__ . '/RaynetCompany.php';
require_once __DIR__ . '/RaynetPerson.php';
require_once __DIR__ . '/RaynetLead.php';
require_once __DIR__ . '/RaynetCustomFields.php';
require_once __DIR__ . '/../raynet-sync-helpers.php';

class RaynetConnector
{
    private RaynetApiClient $client;
    private ?\PDO $pdo;
    private ?RaynetCustomFields $customFieldsHandler = null;
    
    public function __construct(RaynetApiClient $client, ?\PDO $pdo = null)
    {
        $this->client = $client;
        $this->pdo = $pdo;
    }
    
    /**
     * Create connector from global config with optional database (legacy)
     * @deprecated Use createForUser() instead
     */
    public static function create(?\PDO $pdo = null): self
    {
        $client = RaynetApiClient::fromConfig();
        return new self($client, $pdo);
    }
    
    /**
     * Create connector using a specific user's Raynet credentials.
     * 
     * @param string $userId User ID whose Raynet credentials to use
     * @param \PDO|null $pdo Database connection (will be created if null)
     * @return self
     */
    public static function createForUser(string $userId, ?\PDO $pdo = null): self
    {
        if (!$pdo) {
            require_once dirname(__DIR__, 2) . '/config/database.php';
            $pdo = getDbConnection();
        }
        $client = RaynetApiClient::fromUserCredentials($userId, $pdo);
        return new self($client, $pdo);
    }
    
    /**
     * Check if a user has Raynet credentials configured.
     */
    public static function isUserConfigured(string $userId, \PDO $pdo): bool
    {
        $stmt = $pdo->prepare("SELECT raynet_api_key FROM users WHERE id = ? AND raynet_api_key IS NOT NULL AND raynet_api_key != ''");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() !== false;
    }
    
    /**
     * Get API client
     */
    public function getClient(): RaynetApiClient
    {
        return $this->client;
    }
    
    /**
     * Get Custom Fields handler
     */
    public function customFields(): RaynetCustomFields
    {
        if ($this->customFieldsHandler === null) {
            $this->customFieldsHandler = new RaynetCustomFields($this->client);
        }
        return $this->customFieldsHandler;
    }
    
    /**
     * Get Company entity instance
     */
    public function company(): RaynetCompany
    {
        return new RaynetCompany($this->client);
    }
    
    /**
     * Get Person entity instance
     */
    public function person(): RaynetPerson
    {
        return new RaynetPerson($this->client);
    }

    /**
     * Get Lead entity instance
     */
    public function lead(): RaynetLead
    {
        return new RaynetLead($this->client);
    }
    
    /**
     * Check if connector is configured
     */
    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }
    
    /**
     * Get custom field mapping from database
     */
    private function getFieldMapping(): array
    {
        if (!$this->pdo) {
            error_log("Raynet getFieldMapping: No PDO connection");
            return [];
        }
        
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'settings'");
            if ($stmt->rowCount() === 0) {
                error_log("Raynet getFieldMapping: settings table doesn't exist");
                return [];
            }
            
            // Use the standard column names (key, value)
            $stmt = $this->pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'raynet_field_mapping'");
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($row && $row['value']) {
                $decoded = json_decode($row['value'], true);
                error_log("Raynet getFieldMapping: Loaded " . count($decoded ?? []) . " mappings");
                return is_array($decoded) ? $decoded : [];
            }
            
            error_log("Raynet getFieldMapping: No mapping found in database");
        } catch (\PDOException $e) {
            error_log("Failed to get field mapping: " . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Build custom fields payload for a form
     */
    private function buildCustomFieldsPayload(array $formData): array
    {
        $mapping = $this->getFieldMapping();
        
        if (empty($mapping)) {
            return [];
        }
        
        return $this->customFields()->buildCustomFieldsPayload($formData, $mapping);
    }
    
    /**
     * Sync a complete form to Raynet (company + contact person)
     * 
     * @param array $formData Form data (from database or form submission)
     * @param string|int $formId Form ID in EnergyForms (can be UUID string or int)
     * @param int|null $targetCompanyId If set, link to this existing Raynet company instead of creating new
     * @return array Sync result with company/person IDs and status
     */
    public function syncForm(array $formData, string|int $formId, ?int $targetCompanyId = null): array
    {
        $result = [
            'success' => false,
            'form_id' => $formId,
            'company_id' => null,
            'person_id' => null,
            'lead_id' => null,
            'lead_sync_warning' => null,
            'synced_at' => null,
            'error' => null,
            'custom_fields_synced' => 0
        ];
        
        try {
            // Validate configuration
            if (!$this->isConfigured()) {
                throw new RaynetException("Raynet connector is not configured");
            }
            
            // Parse form data to get all fields
            $parsedFormData = $this->parseFormData($formData);
            
            // Add metadata fields that aren't directly in form data
            $parsedFormData['formId'] = (string) $formId;
            $parsedFormData['formSubmittedAt'] = $formData['created_at'] ?? $formData['submitted_at'] ?? date('Y-m-d H:i:s');
            
            // Generate form URL (admin panel link)
            $baseUrl = rtrim($_SERVER['HTTP_HOST'] ?? 'electree.cz', '/');
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $parsedFormData['formUrl'] = "{$protocol}://{$baseUrl}/public/form-detail.php?id={$formId}";
            
            // Build custom fields payload from mapping
            $customFields = $this->buildCustomFieldsPayload($parsedFormData);
            $result['custom_fields_synced'] = count($customFields);
            
            if (!empty($customFields)) {
                error_log("Raynet sync: Including " . count($customFields) . " custom fields");
            }
            
            // 1. Sync company
            $company = $this->company();
            
            if ($targetCompanyId !== null) {
                // Admin selected an existing company to link to
                error_log("Raynet sync: Linking to existing company #{$targetCompanyId}");
                $company->linkToExisting($targetCompanyId, $formData, $formId);
                // Also set custom fields for update
                if (!empty($customFields)) {
                    $company->setCustomFields($customFields);
                    $company->update();
                }
            } else {
                // Create new or find matching company with custom fields
                $company->smartSyncWithCustomFields($parsedFormData, $formId, $customFields);
            }
            $result['company_id'] = $company->getId();
            
            error_log("Raynet sync: Company synced with ID {$result['company_id']}");

            // 2. Sync lead – always create a new lead; duplicate = notify admin only
            try {
                $leadId = $this->syncLead($parsedFormData, $formId, $result['company_id'], $customFields);
                $result['lead_id'] = $leadId;
                error_log("Raynet sync: Lead created with ID {$leadId}");
            } catch (\Exception $e) {
                // Lead sync failure is non-fatal – company is still marked synced,
                // but the warning is persisted to DB and returned to the caller.
                $warning = 'LEAD_WARNING: ' . $e->getMessage();
                $result['lead_sync_warning'] = $warning;
                error_log("Raynet sync: Failed to create lead for form {$formId}: " . $e->getMessage());
            }

            // TODO: Person sync is disabled to prevent duplicate person objects being created in Raynet.
            // Each sync was re-adding the contact person to the company's relation list, causing duplicates.
            // Re-enable and fix deduplication logic before re-implementing person sync.
            //
            // // 2. Sync primary contact person if we have contact data
            // $hasContactPerson = !empty($parsedFormData['contactPerson'])
            //     || !empty($parsedFormData['contact_person']);
            //
            // if ($hasContactPerson) {
            //     try {
            //         $person = $this->person();
            //         $person->smartSync($parsedFormData, $formId, $result['company_id']);
            //         $result['person_id'] = $person->getId();
            //         error_log("Raynet sync: Primary person synced with ID {$result['person_id']}");
            //     } catch (\Exception $e) {
            //         error_log("Raynet sync: Failed to sync primary person: " . $e->getMessage());
            //         // Continue - person sync failure shouldn't break the whole sync
            //     }
            // }
            //
            // // 3. Sync additional contacts
            // $additionalContacts = $parsedFormData['additionalContacts'] ?? [];
            // if (!empty($additionalContacts) && is_array($additionalContacts)) {
            //     $result['additional_contacts'] = [];
            //
            //     foreach ($additionalContacts as $index => $contactData) {
            //         // Skip empty contacts
            //         if (empty($contactData['name']) && empty($contactData['email'])) {
            //             continue;
            //         }
            //
            //         try {
            //             $additionalPerson = $this->person();
            //             $additionalPerson->syncAdditionalContact(
            //                 $contactData,
            //                 $formId,
            //                 $index,
            //                 $result['company_id']
            //             );
            //
            //             $result['additional_contacts'][] = [
            //                 'index' => $index,
            //                 'person_id' => $additionalPerson->getId(),
            //                 'name' => $contactData['name'] ?? '',
            //                 'isPrimary' => $contactData['isPrimary'] ?? false
            //             ];
            //
            //             error_log("Raynet sync: Additional contact {$index} synced with ID {$additionalPerson->getId()}");
            //         } catch (\Exception $e) {
            //             error_log("Raynet sync: Failed to sync additional contact {$index}: " . $e->getMessage());
            //             // Continue with other contacts
            //         }
            //     }
            //
            //     error_log("Raynet sync: Synced " . count($result['additional_contacts']) . " additional contacts");
            // }
            
            // 4. Update sync status in database
            $result['synced_at'] = date('Y-m-d H:i:s');
            $result['success'] = true;
            $result['additional_contacts_count'] = count($result['additional_contacts'] ?? []);
            
            if ($this->pdo) {
                $this->updateSyncStatus($formId, $result);
            }
            
            error_log("Raynet sync: Form {$formId} synced successfully");
            
        } catch (RaynetException $e) {
            $result['error'] = $e->getMessage();
            error_log("Raynet sync error for form {$formId}: " . $e->getMessage());
            
            if ($this->pdo) {
                $this->updateSyncError($formId, $e->getMessage());
            }
        } catch (\Exception $e) {
            $result['error'] = "Unexpected error: " . $e->getMessage();
            error_log("Raynet sync unexpected error for form {$formId}: " . $e->getMessage());
            
            if ($this->pdo) {
                $this->updateSyncError($formId, $e->getMessage());
            }
        }
        
        return $result;
    }
    
    /**
     * Sync multiple forms
     * 
     * @param array $forms Array of form records (with id and form_data)
     * @return array Summary of sync results
     */
    public function syncForms(array $forms): array
    {
        $summary = [
            'total' => count($forms),
            'success' => 0,
            'failed' => 0,
            'results' => []
        ];
        
        foreach ($forms as $form) {
            $formId = $form['id'] ?? null;
            
            if (!$formId) {
                continue;
            }
            
            $result = $this->syncForm($form, $formId);
            $summary['results'][] = $result;
            
            if ($result['success']) {
                $summary['success']++;
            } else {
                $summary['failed']++;
            }
            
            // Small delay to respect rate limits
            usleep(100000); // 100ms
        }
        
        return $summary;
    }
    
    /**
     * Sync all unsynced completed forms
     */
    public function syncPendingForms(): array
    {
        if (!$this->pdo) {
            throw new RaynetException("Database connection required for syncPendingForms");
        }
        
        // Get forms that are completed but not synced
        $stmt = $this->pdo->prepare("
            SELECT * FROM forms 
            WHERE status = 'submitted' 
            AND (raynet_synced_at IS NULL OR raynet_sync_error IS NOT NULL)
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
        $forms = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->syncForms($forms);
    }
    
    /**
     * Update sync status in database
     */
    private function updateSyncStatus(string|int $formId, array $result): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE forms SET
                    raynet_company_id = ?,
                    raynet_person_id = ?,
                    raynet_lead_id = ?,
                    raynet_synced_at = ?,
                    raynet_sync_error = ?,
                    raynet_sync_status = 'synced'
                WHERE id = ?
            ");

            $stmt->execute([
                $result['company_id'],
                $result['person_id'],
                $result['lead_id'],
                $result['synced_at'],
                $this->leadWarningForDb($result), // null = no warning; clears error on clean sync
                $formId,
            ]);
        } catch (\PDOException $e) {
            error_log("Failed to update sync status for form {$formId}: " . $e->getMessage());
        }
    }

    /**
     * Determine the value to store in raynet_sync_error after a successful company sync.
     * Returns a LEAD_WARNING string when lead creation failed, null otherwise.
     */
    private function leadWarningForDb(array $result): ?string
    {
        return $result['lead_sync_warning'] ?? null;
    }

    /**
     * Create a new lead for the given form in Raynet.
     *
     * Always creates a fresh lead record. If a matching lead already exists
     * (by IČO or e-mail), an admin notification is sent but creation proceeds.
     *
     * @param array      $parsedFormData  Form data (already parsed/merged)
     * @param string|int $formId          EnergyForms form ID
     * @param int|null   $raynetCompanyId Raynet company ID from the company sync step
     * @return int  The newly created Raynet lead ID
     */
    private function syncLead(
        array $parsedFormData,
        string|int $formId,
        ?int $raynetCompanyId,
        array $customFields = []
    ): int {
        $checker = new RaynetDuplicateChecker($this->client);

        // Check for existing leads (informational – does NOT block creation)
        $existing = $checker->findExistingLead([
            'ico'   => $parsedFormData['ico']   ?? null,
            'email' => $parsedFormData['email'] ?? null,
        ]);

        if ($existing) {
            error_log(
                "Raynet syncLead: duplicate lead found for form {$formId}"
                . " – matched by '{$existing['matched_by']}', existing Raynet lead ID {$existing['id']}."
                . " Creating new lead anyway and notifying admin."
            );
            $this->sendDuplicateLeadNotification($formId, $parsedFormData, $existing);
        }

        // Always create a new lead, including the same custom fields as the company
        $lead = $this->lead();
        $lead->fromFormData($parsedFormData, $formId, $raynetCompanyId);
        if (!empty($customFields)) {
            $lead->setCustomFields($customFields);
            error_log("Raynet syncLead: attaching " . count($customFields) . " custom fields to lead");
        }
        $lead->create();

        return $lead->getId();
    }

    /**
     * Send admin notification when a duplicate lead is detected.
     * Reads the notify e-mail from the settings table (falls back to a default).
     */
    private function sendDuplicateLeadNotification(
        string|int $formId,
        array $formData,
        array $existing
    ): void {
        $notifyEmail = 'info@electree.cz';

        if ($this->pdo) {
            try {
                $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'settings'");
                if ($tableCheck && $tableCheck->rowCount() > 0) {
                    $stmt = $this->pdo->prepare(
                        "SELECT `value` FROM settings WHERE `key` = 'raynet_duplicate_notify_email'"
                    );
                    $stmt->execute();
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && !empty($row['value'])) {
                        $notifyEmail = $row['value'];
                    }
                }
            } catch (\Exception $e) {
                error_log("sendDuplicateLeadNotification: could not read settings – " . $e->getMessage());
            }
        }

        \sendDuplicateLeadNotificationEmail($notifyEmail, $formId, $formData, $existing);
    }
    
    /**
     * Update sync error in database
     */
    private function updateSyncError(string|int $formId, string $error): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE forms SET 
                    raynet_sync_error = ?,
                    raynet_sync_status = 'error'
                WHERE id = ?
            ");
            
            $stmt->execute([$error, $formId]);
        } catch (\PDOException $e) {
            error_log("Failed to update sync error for form {$formId}: " . $e->getMessage());
        }
    }
    
    /**
     * Get sync status for a form
     */
    public function getSyncStatus(string|int $formId): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT 
                raynet_company_id,
                raynet_person_id,
                raynet_synced_at,
                raynet_sync_error
            FROM forms 
            WHERE id = ?
        ");
        $stmt->execute([$formId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Force resync a form (clear error and sync again)
     */
    public function resyncForm(string|int $formId): array
    {
        if (!$this->pdo) {
            throw new RaynetException("Database connection required for resyncForm");
        }
        
        // Get form data
        $stmt = $this->pdo->prepare("SELECT * FROM forms WHERE id = ?");
        $stmt->execute([$formId]);
        $form = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$form) {
            return [
                'success' => false,
                'error' => "Form not found: {$formId}"
            ];
        }
        
        // Clear previous sync status
        $stmt = $this->pdo->prepare("
            UPDATE forms SET 
                raynet_synced_at = NULL,
                raynet_sync_error = NULL
            WHERE id = ?
        ");
        $stmt->execute([$formId]);
        
        // Sync
        return $this->syncForm($form, $formId);
    }
    
    /**
     * Parse form data from various formats
     * Handles form_data as JSON string or nested array
     * Also loads file attachments from form_files table
     */
    private function parseFormData(array $formData): array
    {
        // If form_data is a JSON string, decode it
        if (isset($formData['form_data']) && is_string($formData['form_data'])) {
            $decoded = json_decode($formData['form_data'], true);
            if ($decoded) {
                $result = array_merge($formData, $decoded);
            } else {
                $result = $formData;
            }
        }
        // If form_data is already an array
        elseif (isset($formData['form_data']) && is_array($formData['form_data'])) {
            $result = array_merge($formData, $formData['form_data']);
        } else {
            $result = $formData;
        }
        
        // Load file attachments from form_files table
        $formId = $formData['id'] ?? $formData['form_id'] ?? null;
        if ($formId && $this->pdo) {
            $files = $this->loadFormFiles($formId);
            if (!empty($files)) {
                $result = array_merge($result, $files);
                error_log("parseFormData: Loaded files for {$formId}: " . json_encode(array_keys($files)));
            }
        }
        
        return $result;
    }
    
    /**
     * Load file attachments from form_files table
     * Groups files by field_name and builds URLs
     * 
     * @param string|int $formId Form ID to load files for
     * @return array Files grouped by field name with URLs
     */
    private function loadFormFiles($formId): array
    {
        $files = [];
        $baseUrl = 'https://ed.electree.cz/public/serve-file.php?id=';
        
        try {
            // Check if form_files table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'form_files'");
            if ($stmt->rowCount() === 0) {
                return [];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT id, field_name, original_name, file_size, mime_type
                FROM form_files 
                WHERE form_id = ? AND deleted_at IS NULL
                ORDER BY field_name, uploaded_at
            ");
            $stmt->execute([$formId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($rows)) {
                return [];
            }
            
            // Group files by field_name
            foreach ($rows as $row) {
                $fieldName = $row['field_name'];
                
                if (!isset($files[$fieldName])) {
                    $files[$fieldName] = [];
                }
                
                $files[$fieldName][] = [
                    'name' => $row['original_name'],
                    'url' => $baseUrl . $row['id'],
                    'size' => $row['file_size'],
                    'type' => $row['mime_type'],
                ];
            }
            
            error_log("loadFormFiles: Loaded " . count($rows) . " files for form {$formId}");
            
        } catch (\PDOException $e) {
            error_log("loadFormFiles error: " . $e->getMessage());
        }
        
        return $files;
    }
}
