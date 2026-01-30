<?php
/**
 * Admin Custom Fields API
 * 
 * API endpoints for managing Raynet custom fields and field mappings.
 */

ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Check authentication
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Load dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Raynet/autoload.php';
require_once __DIR__ . '/../includes/Logger.php';

use Raynet\RaynetApiClient;
use Raynet\RaynetCustomFields;
use Raynet\RaynetException;

$action = 'unknown';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? [];
    
    $action = $data['action'] ?? $_GET['action'] ?? 'get_config';
    
    $pdo = getDbConnection();
    $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
    
    // Initialize Raynet client and custom fields handler
    $client = RaynetApiClient::fromConfig();
    $customFields = new RaynetCustomFields($client);
    
    $result = [];
    
    switch ($action) {
        case 'get_csrf_token':
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            $result = [
                'success' => true,
                'csrf_token' => $_SESSION['csrf_token']
            ];
            break;
            
        case 'get_config':
            // Get all custom field configurations from Raynet
            $forceRefresh = $data['force_refresh'] ?? false;
            $config = $customFields->getConfig($forceRefresh);
            
            $result = [
                'success' => true,
                'data' => $config
            ];
            break;
            
        case 'get_company_fields':
            // Get custom fields for Company entity
            $forceRefresh = $data['force_refresh'] ?? false;
            if ($forceRefresh) {
                $customFields->getConfig(true); // Force refresh cache
            }
            $fields = $customFields->getCompanyFields();
            
            $result = [
                'success' => true,
                'data' => $fields
            ];
            break;
            
        case 'get_person_fields':
            // Get custom fields for Person entity
            $forceRefresh = $data['force_refresh'] ?? false;
            if ($forceRefresh) {
                $customFields->getConfig(true);
            }
            $fields = $customFields->getPersonFields();
            
            $result = [
                'success' => true,
                'data' => $fields
            ];
            break;
            
        case 'get_form_fields':
            // Get available EnergyForms fields for mapping
            $fields = $customFields->getFormFields();
            $byStep = $customFields->getFormFieldsByStep();
            $byGroup = RaynetCustomFields::getFieldsByGroup();
            $groups = RaynetCustomFields::getGroups();
            
            $result = [
                'success' => true,
                'data' => [
                    'fields' => $fields,
                    'by_step' => $byStep,
                    'by_group' => $byGroup,
                    'groups' => $groups
                ]
            ];
            break;
            
        case 'get_auto_mapping':
            // Get the intelligent auto-mapping configuration
            $autoMapping = RaynetCustomFields::getAutoMapping();
            $byTarget = RaynetCustomFields::getAutoMappingByTarget();
            $customByGroup = RaynetCustomFields::getCustomFieldsByGroup();
            $defaultMapping = RaynetCustomFields::generateDefaultMapping();
            $groups = RaynetCustomFields::getGroups();
            $formFields = RaynetCustomFields::FORM_FIELDS;
            
            $result = [
                'success' => true,
                'data' => [
                    'mapping' => $autoMapping,
                    'by_target' => $byTarget,
                    'custom_by_group' => $customByGroup,
                    'default_mapping' => $defaultMapping,
                    'groups' => $groups,
                    'form_fields' => $formFields
                ]
            ];
            break;
            
        case 'apply_auto_mapping':
            // Apply the auto-mapping to the database
            validateCsrf($data);
            
            $defaultMapping = RaynetCustomFields::generateDefaultMapping();
            
            // Save using the standard saveFieldMapping function
            saveFieldMapping($pdo, $defaultMapping);
            
            $logger->info(Logger::TYPE_RAYNET, "Applied auto-mapping configuration", [
                'field_count' => count($defaultMapping)
            ]);
            
            $result = [
                'success' => true,
                'message' => 'Auto-mapování bylo úspěšně aplikováno',
                'data' => [
                    'mapping' => $defaultMapping,
                    'field_count' => count($defaultMapping)
                ]
            ];
            break;
            
        case 'detect_mapping':
            // Auto-detect mapping from existing Raynet fields by matching labels
            // This is useful when fields were created manually in Raynet
            $detection = $customFields->detectMappingFromExistingFields();
            
            $result = [
                'success' => true,
                'data' => $detection
            ];
            break;
            
        case 'apply_detected_mapping':
            // Apply the detected mapping from existing Raynet fields
            validateCsrf($data);
            
            $detection = $customFields->detectMappingFromExistingFields();
            
            if (empty($detection['mapping'])) {
                throw new Exception('Žádná pole nebyla detekována. Ujistěte se, že existující pole v Raynet mají skupinu začínající na "EnergyForms".');
            }
            
            // Merge with any existing mapping
            $existingMapping = getFieldMapping($pdo);
            $newMapping = array_merge($existingMapping, $detection['mapping']);
            
            saveFieldMapping($pdo, $newMapping);
            
            $logger->info(Logger::TYPE_RAYNET, "Applied detected mapping from Raynet fields", [
                'matched_count' => count($detection['matched']),
                'total_mapping' => count($newMapping)
            ]);
            
            $result = [
                'success' => true,
                'message' => 'Detekované mapování bylo aplikováno',
                'data' => [
                    'matched' => $detection['matched'],
                    'unmatched' => $detection['unmatched'],
                    'total_mapping' => count($newMapping)
                ]
            ];
            break;
            
        case 'get_enum_values':
            // Get enum values for a specific field
            $entityType = $data['entity_type'] ?? RaynetCustomFields::ENTITY_COMPANY;
            $fieldName = $data['field_name'] ?? null;
            
            if (!$fieldName) {
                throw new Exception('field_name is required');
            }
            
            $values = $customFields->getEnumValues($entityType, $fieldName);
            
            $result = [
                'success' => true,
                'data' => $values
            ];
            break;
            
        case 'create_field':
            // Create a new custom field on Raynet
            validateCsrf($data);
            
            $entityType = $data['entity_type'] ?? RaynetCustomFields::ENTITY_COMPANY;
            $fieldConfig = [
                'label' => $data['label'] ?? '',
                'groupName' => $data['group_name'] ?? 'EnergyForms',
                'dataType' => $data['data_type'] ?? RaynetCustomFields::TYPE_STRING,
                'description' => $data['description'] ?? '',
                'showInListView' => $data['show_in_list'] ?? true,
                'showInFilterView' => $data['show_in_filter'] ?? true,
            ];
            
            // For enumeration type, add values
            if ($fieldConfig['dataType'] === RaynetCustomFields::TYPE_ENUMERATION) {
                $fieldConfig['enumeration'] = $data['enumeration_values'] ?? [];
            }
            
            $createResult = $customFields->createField($entityType, $fieldConfig);
            
            $logger->info(Logger::TYPE_RAYNET, "Created custom field: {$fieldConfig['label']}", [
                'entity_type' => $entityType,
                'field_name' => $createResult['fieldName'] ?? 'unknown'
            ]);
            
            $result = [
                'success' => true,
                'message' => 'Pole úspěšně vytvořeno',
                'data' => $createResult
            ];
            break;
            
        case 'delete_field':
            // Delete a custom field from Raynet
            validateCsrf($data);
            
            $entityType = $data['entity_type'] ?? RaynetCustomFields::ENTITY_COMPANY;
            $fieldName = $data['field_name'] ?? '';
            
            if (empty($fieldName)) {
                throw new Exception('field_name is required');
            }
            
            $customFields->deleteField($entityType, $fieldName);
            
            $logger->info(Logger::TYPE_RAYNET, "Deleted custom field: {$fieldName}", [
                'entity_type' => $entityType,
                'field_name' => $fieldName
            ]);
            
            $result = [
                'success' => true,
                'message' => 'Pole úspěšně smazáno z Raynet'
            ];
            break;
            
        case 'create_fields_batch':
            // Create multiple fields from form field mapping
            validateCsrf($data);
            
            $entityType = $data['entity_type'] ?? RaynetCustomFields::ENTITY_COMPANY;
            $formFields = $data['form_fields'] ?? [];
            // Optional: override all groups with a single group name
            $groupName = !empty($data['group_name']) ? $data['group_name'] : null;
            
            if (empty($formFields)) {
                throw new Exception('form_fields array is required');
            }
            
            // Pass null for groupName to use each field's defined group
            $createResult = $customFields->createFieldsFromFormMapping($entityType, $formFields, $groupName);
            
            $logger->info(Logger::TYPE_RAYNET, "Batch created custom fields", [
                'entity_type' => $entityType,
                'created_count' => count($createResult['created']),
                'error_count' => count($createResult['errors']),
                'errors' => $createResult['errors'] // Log full error details
            ]);
            
            // AUTO-UPDATE MAPPING: Save the actual Raynet field names to the mapping
            if (!empty($createResult['created'])) {
                // Get current mapping
                $currentMapping = getFieldMapping($pdo);
                
                // Update with actual Raynet field names
                foreach ($createResult['created'] as $createdField) {
                    $formField = $createdField['formField'];
                    $raynetField = $createdField['raynetField'];
                    if ($formField && $raynetField) {
                        $currentMapping[$formField] = $raynetField;
                    }
                }
                
                // Save updated mapping
                saveFieldMapping($pdo, $currentMapping);
                
                $logger->info(Logger::TYPE_RAYNET, "Auto-updated field mapping after batch creation", [
                    'updated_fields' => count($createResult['created'])
                ]);
            }
            
            $result = [
                'success' => true,
                'message' => sprintf(
                    'Vytvořeno %d polí, %d chyb',
                    count($createResult['created']),
                    count($createResult['errors'])
                ),
                'data' => $createResult
            ];
            break;
            
        case 'get_mapping':
            // Get current field mapping from database
            $mapping = getFieldMapping($pdo);
            
            $result = [
                'success' => true,
                'data' => $mapping
            ];
            break;
            
        case 'save_mapping':
            // Save field mapping to database
            validateCsrf($data);
            
            $mapping = $data['mapping'] ?? [];
            
            saveFieldMapping($pdo, $mapping);
            
            $logger->info(Logger::TYPE_RAYNET, "Updated custom field mapping", [
                'field_count' => count($mapping)
            ]);
            
            $result = [
                'success' => true,
                'message' => 'Mapování uloženo'
            ];
            break;
            
        case 'get_data_types':
            // Get available data types for UI
            $result = [
                'success' => true,
                'data' => $customFields->getDataTypeOptions()
            ];
            break;
            
        case 'test_field_sync':
            // Test custom fields sync with a sample form
            validateCsrf($data);
            
            $formId = $data['form_id'] ?? null;
            
            if (!$formId) {
                throw new Exception('form_id is required');
            }
            
            // Get form data
            $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
            $stmt->execute([$formId]);
            $form = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$form) {
                throw new Exception('Form not found');
            }
            
            // Parse form data
            $formData = json_decode($form['form_data'] ?? '{}', true);
            if (!is_array($formData)) {
                $formData = [];
            }
            $formData = array_merge($form, $formData);
            
            // Get mapping
            $mapping = getFieldMapping($pdo);
            
            // Build custom fields payload
            $payload = $customFields->buildCustomFieldsPayload($formData, $mapping);
            
            $result = [
                'success' => true,
                'message' => 'Test úspěšný',
                'data' => [
                    'form_id' => $formId,
                    'mapped_fields' => count($payload),
                    'payload' => $payload
                ]
            ];
            break;
            
        default:
            throw new Exception("Unknown action: {$action}");
    }
    
    ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (RaynetException $e) {
    ob_end_clean();
    
    error_log("Custom Fields API Raynet error [$action]: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => 'raynet_error'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    
    error_log("Custom Fields API error [$action]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// === Helper Functions ===

function validateCsrf(array $data): void
{
    $token = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid CSRF token');
    }
}

/**
 * Get field mapping from database
 */
function getFieldMapping(PDO $pdo): array
{
    // Check if settings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    if ($stmt->rowCount() === 0) {
        // Create settings table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                `key` VARCHAR(100) PRIMARY KEY,
                `value` TEXT,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        return [];
    }
    
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'raynet_field_mapping'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['value']) {
        $decoded = json_decode($row['value'], true);
        return is_array($decoded) ? $decoded : [];
    }
    
    return [];
}

/**
 * Save field mapping to database
 */
function saveFieldMapping(PDO $pdo, array $mapping): void
{
    // Ensure settings table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(100) PRIMARY KEY,
            `value` TEXT,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    $json = json_encode($mapping, JSON_UNESCAPED_UNICODE);
    
    $stmt = $pdo->prepare("
        INSERT INTO settings (`key`, `value`) 
        VALUES ('raynet_field_mapping', ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
    ");
    $stmt->execute([$json]);
}
