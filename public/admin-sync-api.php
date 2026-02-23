<?php
/**
 * Admin Sync API
 * 
 * API endpoints for Raynet synchronization management.
 */

ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error occurred',
            'details' => [
                'message' => $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line']
            ]
        ], JSON_UNESCAPED_UNICODE);
        
        // Try to log it
        try {
            require_once __DIR__ . '/../config/database.php';
            require_once __DIR__ . '/../includes/Logger.php';
            $pdo = getDbConnection();
            $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
            $logger->critical(Logger::TYPE_RAYNET, 'Fatal error in admin-sync-api: ' . $error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]);
        } catch (Exception $e) {
            error_log("Failed to log fatal error: " . $e->getMessage());
        }
    }
});

session_set_cookie_params(["path" => "/", "httponly" => true, "samesite" => "Lax"]);
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

use Raynet\RaynetConnector;
use Raynet\RaynetException;

$action = 'unknown';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? [];
    
    $action = $data['action'] ?? $_GET['action'] ?? 'stats';
    
    $pdo = getDbConnection();
    
    // Initialize logger early for error tracking
    $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
    
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
            
        case 'stats':
            $result = getSyncStats($pdo);
            break;
            
        case 'list_local_forms':
            $page = $data['page'] ?? 1;
            $perPage = $data['per_page'] ?? 20;
            $filter = $data['filter'] ?? 'all'; // all, synced, pending, error
            $result = getLocalForms($pdo, $page, $perPage, $filter);
            break;
            
        case 'list_raynet_companies':
            $page = $data['page'] ?? 1;
            $perPage = $data['per_page'] ?? 20;
            $search = $data['search'] ?? '';
            $result = getRaynetCompanies($page, $perPage, $search);
            break;
            
        case 'sync_form':
            validateCsrf($data);
            $formId = $data['form_id'] ?? null;
            if (!$formId) {
                throw new Exception('form_id is required');
            }
            // Optional: target_company_id - if set, link to this existing Raynet company
            // If null, create a new company
            $targetCompanyId = $data['target_company_id'] ?? null;
            $result = syncSingleForm($pdo, $formId, $targetCompanyId);
            break;
            
        case 'preview_sync':
            validateCsrf($data);
            $formId = $data['form_id'] ?? null;
            if (!$formId) {
                throw new Exception('form_id is required');
            }
            $result = previewSync($pdo, $formId);
            break;
            
        case 'sync_all_pending':
            validateCsrf($data);
            $result = syncAllPending($pdo);
            break;
            
        case 'retry_errors':
            validateCsrf($data);
            $result = retryErrors($pdo);
            break;
            
        case 'clear_error':
            validateCsrf($data);
            $formId = $data['form_id'] ?? null;
            if (!$formId) {
                throw new Exception('form_id is required');
            }
            $result = clearSyncError($pdo, $formId);
            break;
            
        case 'get_sync_log':
            $formId = $data['form_id'] ?? null;
            $result = getSyncLog($pdo, $formId);
            break;
            
        case 'test_connection':
            $result = testRaynetConnection();
            break;
            
        case 'get_company_json':
            validateCsrf($data);
            $companyId = $data['company_id'] ?? null;
            if (!$companyId) {
                throw new Exception('company_id is required');
            }
            $result = getCompanyJson($companyId);
            break;
            
        case 'get_logs':
            $level = $data['level'] ?? $_GET['level'] ?? null;
            $limit = (int) ($data['limit'] ?? $_GET['limit'] ?? 50);
            $offset = (int) ($data['offset'] ?? $_GET['offset'] ?? 0);
            $result = getRaynetLogs($pdo, $level, $limit, $offset);
            break;
            
        case 'clear_logs':
            validateCsrf($data);
            $daysOld = (int) ($data['days_old'] ?? 30);
            $result = clearOldRaynetLogs($pdo, $daysOld);
            break;
            
        case 'log_frontend_error':
            // Log frontend errors to database
            $level = $data['level'] ?? 'error';
            $message = $data['message'] ?? 'Frontend error';
            $context = $data['context'] ?? [];
            
            // Add browser info to context
            $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $context['referer'] = $_SERVER['HTTP_REFERER'] ?? '';
            
            $logger->log(Logger::TYPE_RAYNET, $level, $message, $context);
            
            $result = [
                'success' => true,
                'message' => 'Error logged'
            ];
            break;
            
            
        default:
            throw new Exception("Unknown action: {$action}");
    }
    
    ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (RaynetException $e) {
    ob_end_clean();
    
    // Log the error
    try {
        $pdo = getDbConnection();
        $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
        $logger->error(Logger::TYPE_RAYNET, 'Raynet API error: ' . $e->getMessage(), [
            'action' => $action ?? 'unknown',
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString()
        ]);
    } catch (Exception $logError) {
        error_log("Failed to log Raynet error: " . $logError->getMessage());
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => 'raynet_error'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    
    // Log the error - try to initialize logger if not already done
    try {
        if (!isset($pdo)) {
            $pdo = getDbConnection();
        }
        if (!isset($logger)) {
            $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
        }
        $logger->error(Logger::TYPE_RAYNET, 'Sync API error: ' . $e->getMessage(), [
            'action' => $action,
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    } catch (Exception $logError) {
        error_log("Failed to log error: " . $logError->getMessage());
    }
    
    $actionName = $action ?? 'unknown';
    error_log("Admin sync API error [$actionName]: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'action' => $actionName
        ]
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

function getSyncStats(PDO $pdo): array
{
    // Local stats
    $localStats = $pdo->query("
        SELECT 
            COUNT(*) as total_forms,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_forms,
            SUM(CASE WHEN raynet_synced_at IS NOT NULL AND raynet_sync_error IS NULL THEN 1 ELSE 0 END) as synced_forms,
            SUM(CASE WHEN status = 'submitted' AND raynet_synced_at IS NULL AND raynet_sync_error IS NULL THEN 1 ELSE 0 END) as pending_forms,
            SUM(CASE WHEN raynet_sync_error IS NOT NULL THEN 1 ELSE 0 END) as error_forms
        FROM forms
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Recent errors
    $recentErrors = $pdo->query("
        SELECT id, company_name, raynet_sync_error, updated_at
        FROM forms 
        WHERE raynet_sync_error IS NOT NULL 
        ORDER BY updated_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Last sync times
    $lastSync = $pdo->query("
        SELECT MAX(raynet_synced_at) as last_sync_time
        FROM forms
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Check Raynet connection
    $raynetStatus = checkRaynetStatus();
    
    return [
        'success' => true,
        'data' => [
            'local' => [
                'total_forms' => (int) $localStats['total_forms'],
                'submitted_forms' => (int) $localStats['submitted_forms'],
                'synced_forms' => (int) $localStats['synced_forms'],
                'pending_forms' => (int) $localStats['pending_forms'],
                'error_forms' => (int) $localStats['error_forms']
            ],
            'raynet' => $raynetStatus,
            'recent_errors' => $recentErrors,
            'last_sync_time' => $lastSync['last_sync_time']
        ]
    ];
}

function checkRaynetStatus(): array
{
    try {
        $connector = RaynetConnector::create();
        
        if (!$connector->isConfigured()) {
            return [
                'connected' => false,
                'configured' => false,
                'message' => 'Raynet není nakonfigurován',
                'companies_count' => 0
            ];
        }
        
        // Try to fetch companies count
        $company = $connector->company();
        $results = $company->search([], 1, 0);
        
        // Get rate limit info
        $rateLimit = $connector->getClient()->getRateLimitRemaining();
        
        return [
            'connected' => true,
            'configured' => true,
            'message' => 'Připojeno k Raynet',
            'rate_limit_remaining' => $rateLimit,
            'companies_count' => null // Would need separate API call to get total
        ];
        
    } catch (RaynetException $e) {
        return [
            'connected' => false,
            'configured' => true,
            'message' => 'Chyba připojení: ' . $e->getMessage(),
            'companies_count' => 0
        ];
    } catch (Exception $e) {
        return [
            'connected' => false,
            'configured' => false,
            'message' => 'Chyba: ' . $e->getMessage(),
            'companies_count' => 0
        ];
    }
}

function getLocalForms(PDO $pdo, int $page, int $perPage, string $filter): array
{
    $offset = ($page - 1) * $perPage;
    
    $whereClause = "WHERE status = 'submitted'";
    
    switch ($filter) {
        case 'synced':
            $whereClause .= " AND raynet_synced_at IS NOT NULL AND raynet_sync_error IS NULL";
            break;
        case 'pending':
            $whereClause .= " AND raynet_synced_at IS NULL AND raynet_sync_error IS NULL";
            break;
        case 'error':
            $whereClause .= " AND raynet_sync_error IS NOT NULL";
            break;
    }
    
    // Get total count
    $countStmt = $pdo->query("SELECT COUNT(*) FROM forms {$whereClause}");
    $totalCount = (int) $countStmt->fetchColumn();
    
    // Get forms
    $stmt = $pdo->prepare("
        SELECT 
            f.id,
            f.company_name,
            f.contact_person,
            f.email,
            f.phone,
            f.status,
            f.raynet_company_id,
            f.raynet_person_id,
            f.raynet_synced_at,
            f.raynet_sync_error,
            f.created_at,
            f.updated_at,
            u.name as user_name
        FROM forms f
        LEFT JOIN users u ON f.user_id = u.id
        {$whereClause}
        ORDER BY f.updated_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates and add sync status
    foreach ($forms as &$form) {
        $form['sync_status'] = getSyncStatusLabel($form);
        $form['created_at_formatted'] = $form['created_at'] ? date('d.m.Y H:i', strtotime($form['created_at'])) : '';
        $form['synced_at_formatted'] = $form['raynet_synced_at'] ? date('d.m.Y H:i', strtotime($form['raynet_synced_at'])) : '';
    }
    
    return [
        'success' => true,
        'data' => [
            'forms' => $forms,
            'pagination' => [
                'total_count' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalCount / $perPage)
            ]
        ]
    ];
}

function getSyncStatusLabel(array $form): array
{
    if ($form['raynet_sync_error']) {
        return ['status' => 'error', 'label' => 'Chyba', 'class' => 'bg-red-100 text-red-800'];
    }
    if ($form['raynet_synced_at']) {
        return ['status' => 'synced', 'label' => 'Synchronizováno', 'class' => 'bg-green-100 text-green-800'];
    }
    return ['status' => 'pending', 'label' => 'Čeká', 'class' => 'bg-yellow-100 text-yellow-800'];
}

function getRaynetCompanies(int $page, int $perPage, string $search): array
{
    try {
        $connector = RaynetConnector::create();
        
        if (!$connector->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Raynet není nakonfigurován'
            ];
        }
        
        $company = $connector->company();
        $offset = ($page - 1) * $perPage;
        
        $filters = [];
        if ($search) {
            $filters['name'] = ['LIKE' => "%{$search}%"];
        }
        
        $companies = $company->search($filters, $perPage, $offset);
        
        // Convert to array format
        $companiesData = [];
        foreach ($companies as $comp) {
            $data = $comp->getData();
            $companiesData[] = [
                'id' => $data['id'] ?? null,
                'name' => $data['name'] ?? '',
                'regNumber' => $data['regNumber'] ?? '',
                'taxNumber' => $data['taxNumber'] ?? '',
                'state' => $data['state'] ?? '',
                'role' => $data['role'] ?? '',
                'extId' => $data['extId'] ?? '',
                'rating' => $data['rating'] ?? ''
            ];
        }
        
        return [
            'success' => true,
            'data' => [
                'companies' => $companiesData,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage
                ]
            ]
        ];
        
    } catch (RaynetException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function previewSync(PDO $pdo, $formId): array
{
    try {
        // Get form data
        $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
        $stmt->execute([$formId]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$form) {
            throw new Exception("Form not found: {$formId}");
        }
        
        $connector = RaynetConnector::create($pdo);
        
        // Check if company exists in Raynet
        $raynetExists = false;
        $raynetData = null;
        $matchReason = null;
        $candidates = []; // Multiple potential matches for admin to choose from
        
        // 1. Already synced - get existing data
        if ($form['raynet_company_id']) {
            try {
                $company = $connector->company()->findById($form['raynet_company_id']);
                if ($company) {
                    $data = $company->getData();
                    $raynetExists = true;
                    $raynetData = [
                        'id' => $data['id'],
                        'name' => $data['name'] ?? null
                    ];
                    $matchReason = 'Již synchronizováno (Raynet ID: ' . $data['id'] . ')';
                }
            } catch (Exception $e) {
                // Company not found, treat as new
            }
        }
        
        // 2. Search by IČO
        if (!$raynetExists && !empty($form['ico'])) {
            try {
                $existing = $connector->company()->findByIco($form['ico']);
                if ($existing) {
                    $data = $existing->getData();
                    $raynetExists = true;
                    $raynetData = [
                        'id' => $data['id'],
                        'name' => $data['name'] ?? null
                    ];
                    $matchReason = 'Nalezeno podle IČO: ' . $form['ico'];
                }
            } catch (Exception $e) {
                // Not found
            }
        }
        
        // 3. Search by company name (if not already matched)
        if (!$raynetExists && !empty($form['company_name'])) {
            try {
                $client = $connector->getClient();
                $response = $client->get('/company/', [
                    'name[LIKE]' => '%' . $form['company_name'] . '%',
                    'limit' => 10
                ]);
                
                if (!empty($response['data'])) {
                    foreach ($response['data'] as $company) {
                        // Extract email from primaryAddress.contactInfo
                        $companyEmail = $company['primaryAddress']['contactInfo']['email'] ?? null;
                        
                        $candidates[] = [
                            'id' => $company['id'],
                            'name' => $company['name'] ?? null,
                            'regNumber' => $company['regNumber'] ?? null,
                            'email' => $companyEmail,
                            'match_type' => 'name',
                            'match_reason' => 'Shoda názvu: ' . ($company['name'] ?? '')
                        ];
                    }
                }
            } catch (Exception $e) {
                // Search failed, continue
            }
        }
        
        // 4. Search by email using fulltext (if nothing found by name and email exists)
        if (!$raynetExists && empty($candidates) && !empty($form['email'])) {
            try {
                $client = $connector->getClient();
                
                // Raynet doesn't support contactInfo.email filter on /company/
                // Use fulltext search instead which searches across all fields including email
                $response = $client->get('/company/', [
                    'fulltext' => $form['email'],
                    'limit' => 10
                ]);
                
                if (!empty($response['data'])) {
                    foreach ($response['data'] as $company) {
                        // Extract email from primaryAddress if available
                        $companyEmail = $company['primaryAddress']['contactInfo']['email'] ?? null;
                        
                        $candidates[] = [
                            'id' => $company['id'],
                            'name' => $company['name'] ?? null,
                            'regNumber' => $company['regNumber'] ?? null,
                            'email' => $companyEmail,
                            'match_type' => 'email',
                            'match_reason' => 'Shoda emailu (fulltext): ' . $form['email']
                        ];
                    }
                }
                
                // Also search in Person entity as backup
                if (empty($candidates)) {
                    $personResponse = $client->get('/person/', [
                        'contactInfo.email' => $form['email'],
                        'limit' => 10
                    ]);
                    
                    if (!empty($personResponse['data'])) {
                        foreach ($personResponse['data'] as $person) {
                            // Get the company ID from the person
                            $companyId = $person['company']['id'] ?? null;
                            if ($companyId) {
                                // Fetch company details
                                try {
                                    $companyResponse = $client->get('/company/' . $companyId);
                                    if (!empty($companyResponse['data'])) {
                                        $company = $companyResponse['data'];
                                        $candidates[] = [
                                            'id' => $company['id'],
                                            'name' => $company['name'] ?? null,
                                            'regNumber' => $company['regNumber'] ?? null,
                                            'email' => $person['contactInfo']['email'] ?? null,
                                            'match_type' => 'person_email',
                                            'match_reason' => 'Osoba s emailem: ' . $form['email']
                                        ];
                                    }
                                } catch (Exception $e) {
                                    // Skip if can't fetch company
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Search failed, continue
            }
        }
        
        // If exactly one candidate, use it
        if (!$raynetExists && count($candidates) === 1) {
            $raynetExists = true;
            $raynetData = $candidates[0];
            $matchReason = $candidates[0]['match_reason'];
            $candidates = []; // Clear candidates since we have a match
        }
        
        return [
            'success' => true,
            'data' => [
                'local' => [
                    'company_name' => $form['company_name'],
                    'ico' => $form['ico'],
                    'email' => $form['email'],
                    'contact_person' => $form['contact_person'],
                    'phone' => $form['phone']
                ],
                'raynet_exists' => $raynetExists,
                'raynet' => $raynetData,
                'match_reason' => $matchReason,
                'candidates' => $candidates, // Multiple options for admin to pick
                'has_multiple_matches' => count($candidates) > 1
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function syncSingleForm(PDO $pdo, $formId, ?int $targetCompanyId = null): array
{
    $logger = new Logger($pdo, $_SESSION['user_id'] ?? null, $formId);
    
    try {
        $logger->debug(Logger::TYPE_RAYNET, "syncSingleForm called for form #{$formId}" . ($targetCompanyId ? " with target company #{$targetCompanyId}" : " (create new)"));
        
        // Get form data first
        $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
        $stmt->execute([$formId]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$form) {
            $logger->error(Logger::TYPE_RAYNET, "Form not found: {$formId}");
            throw new Exception("Form not found: {$formId}");
        }
        
        $logger->debug(Logger::TYPE_RAYNET, "Creating RaynetConnector");
        $connector = RaynetConnector::create($pdo);
        
        if (!$connector) {
            $logger->error(Logger::TYPE_RAYNET, "Failed to create RaynetConnector");
            throw new Exception("Failed to create RaynetConnector");
        }
        
        $logger->info(Logger::TYPE_RAYNET, "Starting sync for form #{$formId}", [
            'company_name' => $form['company_name'],
            'contact_person' => $form['contact_person'],
            'target_company_id' => $targetCompanyId
        ]);
        
        $logger->debug(Logger::TYPE_RAYNET, "Calling connector->syncForm()");
        $result = $connector->syncForm($form, $formId, $targetCompanyId);
        $logger->debug(Logger::TYPE_RAYNET, "syncForm returned", ['result' => $result]);
        
        if ($result['success']) {
            $logger->info(Logger::TYPE_RAYNET, "Successfully synced form #{$formId}", [
                'company_id' => $result['company_id'] ?? null,
                'person_id' => $result['person_id'] ?? null
            ]);
        } else {
            $logger->error(Logger::TYPE_RAYNET, "Failed to sync form #{$formId}: " . ($result['error'] ?? 'Unknown error'), [
                'result' => $result
            ]);
        }
        
        return [
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['success'] 
                ? 'Formulář byl úspěšně synchronizován' 
                : 'Synchronizace selhala: ' . ($result['error'] ?? 'Unknown error')
        ];
        
    } catch (Exception $e) {
        $logger->error(Logger::TYPE_RAYNET, "Exception during sync of form #{$formId}: " . $e->getMessage(), [
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

function syncAllPending(PDO $pdo): array
{
    $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
    
    try {
        $logger->info(Logger::TYPE_RAYNET, 'Starting bulk sync of pending forms');
        
        $connector = RaynetConnector::create($pdo);
        
        if (!$connector->isConfigured()) {
            $logger->warning(Logger::TYPE_RAYNET, 'Raynet not configured, cannot sync');
            throw new Exception('Raynet není nakonfigurován. Aktualizujte config/raynet.php');
        }
        
        $result = $connector->syncPendingForms();
        
        $logger->info(Logger::TYPE_RAYNET, "Bulk sync completed: {$result['success']} success, {$result['failed']} failed", [
            'total' => $result['total'],
            'success' => $result['success'],
            'failed' => $result['failed']
        ]);
        
        return [
            'success' => true,
            'data' => [
                'total' => $result['total'],
                'success' => $result['success'],
                'failed' => $result['failed']
            ],
            'message' => "Synchronizováno {$result['success']} z {$result['total']} formulářů"
        ];
        
    } catch (Exception $e) {
        $logger->error(Logger::TYPE_RAYNET, 'Bulk sync failed: ' . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        throw $e;
    }
}

function retryErrors(PDO $pdo): array
{
    // Get all forms with errors
    $stmt = $pdo->query("
        SELECT id FROM forms 
        WHERE raynet_sync_error IS NOT NULL 
        LIMIT 50
    ");
    $forms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $connector = RaynetConnector::create($pdo);
    $success = 0;
    $failed = 0;
    
    foreach ($forms as $formId) {
        try {
            $result = $connector->resyncForm($formId);
            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
        }
        
        usleep(100000); // 100ms delay
    }
    
    return [
        'success' => true,
        'data' => [
            'total' => count($forms),
            'success' => $success,
            'failed' => $failed
        ],
        'message' => "Opakovaně synchronizováno {$success} z " . count($forms) . " formulářů"
    ];
}

function clearSyncError(PDO $pdo, $formId): array
{
    $stmt = $pdo->prepare("
        UPDATE forms SET 
            raynet_sync_error = NULL,
            raynet_synced_at = NULL
        WHERE id = ?
    ");
    $stmt->execute([$formId]);
    
    return [
        'success' => true,
        'message' => 'Chyba byla vymazána, formulář je připraven k synchronizaci'
    ];
}

function getSyncLog(PDO $pdo, $formId = null): array
{
    if ($formId) {
        $stmt = $pdo->prepare("
            SELECT id, company_name, raynet_synced_at, raynet_sync_error, updated_at
            FROM forms 
            WHERE id = ?
        ");
        $stmt->execute([$formId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $log = $pdo->query("
            SELECT id, company_name, raynet_synced_at, raynet_sync_error, updated_at
            FROM forms 
            WHERE raynet_synced_at IS NOT NULL OR raynet_sync_error IS NOT NULL
            ORDER BY COALESCE(raynet_synced_at, updated_at) DESC
            LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return [
        'success' => true,
        'data' => $log
    ];
}

function testRaynetConnection(): array
{
    try {
        $connector = RaynetConnector::create();
        
        if (!$connector->isConfigured()) {
            return [
                'success' => false,
                'connected' => false,
                'message' => 'Raynet není nakonfigurován. Aktualizujte config/raynet.php'
            ];
        }
        
        // Try a simple search
        $company = $connector->company();
        $results = $company->search([], 1);
        
        // Log successful connection
        try {
            $pdo = getDbConnection();
            $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
            $logger->info(Logger::TYPE_RAYNET, 'Raynet connection test successful', [
                'rate_limit' => $connector->getClient()->getRateLimitRemaining()
            ]);
        } catch (Exception $e) {
            // Continue even if logging fails
        }
        
        return [
            'success' => true,
            'connected' => true,
            'message' => 'Připojení k Raynet je funkční',
            'rate_limit' => $connector->getClient()->getRateLimitRemaining()
        ];
        
    } catch (RaynetException $e) {
        // Log failed connection
        try {
            $pdo = getDbConnection();
            $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
            $logger->error(Logger::TYPE_RAYNET, 'Raynet connection test failed: ' . $e->getMessage());
        } catch (Exception $logError) {
            // Continue even if logging fails
        }
        
        return [
            'success' => false,
            'connected' => false,
            'message' => 'Chyba připojení: ' . $e->getMessage()
        ];
    }
}

function getRaynetLogs(PDO $pdo, ?string $level, int $limit, int $offset): array
{
    $sql = "
        SELECT 
            id,
            level,
            message,
            context,
            user_id,
            form_id,
            ip_address,
            created_at
        FROM logs
        WHERE type = 'raynet'
    ";
    
    $params = [];
    
    if ($level) {
        $sql .= " AND level = ?";
        $params[] = $level;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM logs WHERE type = 'raynet'";
    if ($level) {
        $countSql .= " AND level = ?";
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($level ? [$level] : []);
    $totalCount = (int) $countStmt->fetchColumn();
    
    // Get logs
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode context JSON
    foreach ($logs as &$log) {
        if ($log['context']) {
            $log['context'] = json_decode($log['context'], true);
        }
        $log['created_at_formatted'] = date('d.m.Y H:i:s', strtotime($log['created_at']));
    }
    
    // Get error statistics
    $stats = $pdo->query("
        SELECT 
            level,
            COUNT(*) as count
        FROM logs
        WHERE type = 'raynet'
        GROUP BY level
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    return [
        'success' => true,
        'data' => [
            'logs' => $logs,
            'stats' => [
                'total' => $totalCount,
                'by_level' => $stats
            ],
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $totalCount,
                'pages' => ceil($totalCount / $limit)
            ]
        ]
    ];
}

function clearOldRaynetLogs(PDO $pdo, int $daysOld): array
{
    $stmt = $pdo->prepare("
        DELETE FROM logs 
        WHERE type = 'raynet' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$daysOld]);
    $deleted = $stmt->rowCount();
    
    // Log the cleanup
    try {
        $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
        $logger->info(Logger::TYPE_SYSTEM, "Cleared {$deleted} old Raynet logs", [
            'days_old' => $daysOld,
            'deleted_count' => $deleted
        ]);
    } catch (Exception $e) {
        // Continue even if logging fails
    }
    
    return [
        'success' => true,
        'data' => [
            'deleted' => $deleted
        ],
        'message' => "Vymazáno {$deleted} starých logů"
    ];
}

function getCompanyJson($companyId): array
{
    try {
        $connector = RaynetConnector::create();
        
        if (!$connector->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Raynet není nakonfigurován'
            ];
        }
        
        // Fetch company by ID
        $company = $connector->company()->findById($companyId);
        
        if (!$company) {
            return [
                'success' => false,
                'error' => 'Company not found'
            ];
        }
        
        // Get raw data
        $companyData = $company->getData();
        
        // Log successful fetch
        try {
            $pdo = getDbConnection();
            $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
            $logger->debug(Logger::TYPE_RAYNET, "Fetched company JSON for ID: {$companyId}");
        } catch (Exception $e) {
            // Continue even if logging fails
        }
        
        return [
            'success' => true,
            'data' => $companyData
        ];
        
    } catch (RaynetException $e) {
        // Log the error
        try {
            $pdo = getDbConnection();
            $logger = new Logger($pdo, $_SESSION['user_id'] ?? null);
            $logger->error(Logger::TYPE_RAYNET, "Failed to fetch company JSON: " . $e->getMessage(), [
                'company_id' => $companyId
            ]);
        } catch (Exception $logError) {
            // Continue even if logging fails
        }
        
        return [
            'success' => false,
            'error' => 'Raynet error: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
