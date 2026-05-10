<?php
/**
 * User Raynet Credentials API
 * 
 * Allows any authenticated user to manage their own Raynet CRM credentials.
 * Actions: get_status, save_credentials, test_credentials, clear_credentials
 */

ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

session_set_cookie_params(["path" => "/", "httponly" => true, "samesite" => "Lax"]);
session_start();

// Any authenticated user can manage their own Raynet credentials
if (!isset($_SESSION['user_id'])) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    error_log("[UserRaynetAPI] Unauthenticated request from {$ip}, method={$_SERVER['REQUEST_METHOD']}");
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nepřihlášen']);
    exit;
}

// Shared log prefix function — provides consistent context for every log line
function raynetApiLog(string $level, string $userId, string $message, array $context = []): void
{
    $ctx = '';
    if (!empty($context)) {
        $parts = [];
        foreach ($context as $k => $v) {
            $parts[] = "{$k}=" . (is_bool($v) ? ($v ? 'true' : 'false') : $v);
        }
        $ctx = ' [' . implode(', ', $parts) . ']';
    }
    error_log("[UserRaynetAPI] [{$level}] user={$userId} {$message}{$ctx}");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Raynet/autoload.php';

use Raynet\RaynetApiClient;
use Raynet\RaynetException;

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? [];
    
    $action = $data['action'] ?? $_GET['action'] ?? '';
    $userId = $_SESSION['user_id'];
    
    raynetApiLog('INFO', $userId, "Action dispatched", ['action' => $action, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

    $pdo = getDbConnection();
    
    switch ($action) {
        case 'get_status':
            $result = getCredentialStatus($pdo, $userId);
            break;
            
        case 'save_credentials':
            validateCsrf($data);
            $result = saveCredentials($pdo, $userId, $data);
            break;
            
        case 'test_credentials':
            $result = testCredentials($data, $userId);
            break;
            
        case 'clear_credentials':
            validateCsrf($data);
            $result = clearCredentials($pdo, $userId);
            break;
            
        case 'get_csrf_token':
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            raynetApiLog('INFO', $userId, 'CSRF token issued');
            $result = ['success' => true, 'csrf_token' => $_SESSION['csrf_token']];
            break;
            
        default:
            raynetApiLog('WARN', $userId, "Unknown action requested", ['action' => $action]);
            throw new Exception("Neznámá akce: {$action}");
    }
    
    ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $uid = $_SESSION['user_id'] ?? 'unauthenticated';
    raynetApiLog('ERROR', $uid, 'Unhandled exception', [
        'action' => $action ?? 'unknown',
        'error'  => $e->getMessage(),
        'file'   => basename($e->getFile()),
        'line'   => $e->getLine(),
    ]);
    ob_end_clean();
    http_response_code(400);
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
        $userId = $_SESSION['user_id'] ?? 'unknown';
        raynetApiLog('WARN', $userId, 'CSRF token mismatch', [
            'provided_prefix' => substr($token, 0, 8) . '...',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        throw new Exception('Neplatný CSRF token');
    }
}

function getCredentialStatus(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare("SELECT raynet_username, raynet_api_key, raynet_instance_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        raynetApiLog('WARN', $userId, 'get_status: user not found in DB');
        return ['success' => true, 'configured' => false];
    }
    
    $configured = !empty($row['raynet_api_key']) && !empty($row['raynet_username']) && !empty($row['raynet_instance_name']);
    raynetApiLog('INFO', $userId, 'get_status', [
        'configured'    => $configured,
        'username'      => $row['raynet_username'] ?? '(empty)',
        'instance_name' => $row['raynet_instance_name'] ?? '(empty)',
        'api_key_set'   => !empty($row['raynet_api_key']),
    ]);
    
    return [
        'success' => true,
        'configured' => $configured,
        'credentials' => [
            'username' => $row['raynet_username'] ?? '',
            'instance_name' => $row['raynet_instance_name'] ?? '',
            // Never return the actual API key, just indicate if it's set
            'api_key_set' => !empty($row['raynet_api_key'])
        ]
    ];
}

function testCredentials(array $data, string $userId = 'unknown'): array
{
    $username = trim($data['username'] ?? '');
    $apiKey = trim($data['api_key'] ?? '');
    $instanceName = trim($data['instance_name'] ?? '');
    
    if (empty($username) || empty($apiKey) || empty($instanceName)) {
        $missing = array_filter([
            empty($username)      ? 'username' : null,
            empty($apiKey)        ? 'api_key'  : null,
            empty($instanceName)  ? 'instance_name' : null,
        ]);
        raynetApiLog('WARN', $userId, 'test_credentials: missing fields', [
            'missing' => implode(',', $missing),
        ]);
        return [
            'success' => false,
            'error' => 'Vyplňte všechna pole (uživatelské jméno, API klíč, název instance)'
        ];
    }
    
    raynetApiLog('INFO', $userId, 'test_credentials: attempting connection', [
        'username'      => $username,
        'instance_name' => $instanceName,
        'api_key_prefix' => substr($apiKey, 0, 8) . '...',
    ]);

    try {
        $client = new RaynetApiClient($username, $apiKey, $instanceName);
        
        // Use the same test as the existing test_connection: fetch 1 company
        $results = $client->get('/company/', ['limit' => 1]);
        $rateLimit = $client->getRateLimitRemaining();

        raynetApiLog('INFO', $userId, 'test_credentials: OK', [
            'username'      => $username,
            'instance_name' => $instanceName,
            'rate_limit'    => $rateLimit,
        ]);
        
        return [
            'success' => true,
            'message' => 'Připojení k Raynet je funkční',
            'rate_limit' => $rateLimit
        ];
        
    } catch (RaynetException $e) {
        raynetApiLog('ERROR', $userId, 'test_credentials: RaynetException', [
            'username'      => $username,
            'instance_name' => $instanceName,
            'error'         => $e->getMessage(),
        ]);
        return [
            'success' => false,
            'error' => 'Test připojení selhal: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        raynetApiLog('ERROR', $userId, 'test_credentials: unexpected exception', [
            'error' => $e->getMessage(),
            'file'  => basename($e->getFile()),
            'line'  => $e->getLine(),
        ]);
        return [
            'success' => false,
            'error' => 'Neočekávaná chyba: ' . $e->getMessage()
        ];
    }
}

function saveCredentials(PDO $pdo, string $userId, array $data): array
{
    $username = trim($data['username'] ?? '');
    $apiKey = trim($data['api_key'] ?? '');
    $instanceName = trim($data['instance_name'] ?? '');
    
    if (empty($username) || empty($apiKey) || empty($instanceName)) {
        $missing = array_filter([
            empty($username)     ? 'username' : null,
            empty($apiKey)       ? 'api_key'  : null,
            empty($instanceName) ? 'instance_name' : null,
        ]);
        raynetApiLog('WARN', $userId, 'save_credentials: missing fields', [
            'missing' => implode(',', $missing),
        ]);
        return [
            'success' => false,
            'error' => 'Vyplňte všechna pole (uživatelské jméno, API klíč, název instance)'
        ];
    }
    
    raynetApiLog('INFO', $userId, 'save_credentials: testing before save', [
        'username'       => $username,
        'instance_name'  => $instanceName,
        'api_key_prefix' => substr($apiKey, 0, 8) . '...',
    ]);

    // Test credentials before saving
    $testResult = testCredentials($data, $userId);
    if (!$testResult['success']) {
        raynetApiLog('WARN', $userId, 'save_credentials: test failed, not saving', [
            'username'      => $username,
            'instance_name' => $instanceName,
            'error'         => $testResult['error'] ?? 'neznámá chyba',
        ]);
        return [
            'success' => false,
            'error' => 'Nelze uložit – API klíč nefunguje: ' . ($testResult['error'] ?? 'neznámá chyba')
        ];
    }
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET raynet_username = ?, raynet_api_key = ?, raynet_instance_name = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$username, $apiKey, $instanceName, $userId]);
    
    if ($stmt->rowCount() === 0) {
        raynetApiLog('WARN', $userId, 'save_credentials: UPDATE affected 0 rows – user not found');
        return [
            'success' => false,
            'error' => 'Uživatel nebyl nalezen'
        ];
    }
    
    raynetApiLog('INFO', $userId, 'save_credentials: credentials saved', [
        'username'      => $username,
        'instance_name' => $instanceName,
    ]);
    return [
        'success' => true,
        'message' => 'Raynet přihlašovací údaje byly uloženy a ověřeny'
    ];
}

function clearCredentials(PDO $pdo, string $userId): array
{
    // Read current credentials so the log shows what was removed
    $stmt = $pdo->prepare("SELECT raynet_username, raynet_instance_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $before = $stmt->fetch(PDO::FETCH_ASSOC);

    raynetApiLog('INFO', $userId, 'clear_credentials: clearing', [
        'username'      => $before['raynet_username'] ?? '(empty)',
        'instance_name' => $before['raynet_instance_name'] ?? '(empty)',
    ]);

    $stmt = $pdo->prepare("
        UPDATE users 
        SET raynet_username = NULL, raynet_api_key = NULL, raynet_instance_name = NULL, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    
    if ($stmt->rowCount() === 0) {
        raynetApiLog('WARN', $userId, 'clear_credentials: UPDATE affected 0 rows – user not found');
    } else {
        raynetApiLog('INFO', $userId, 'clear_credentials: done');
    }

    return [
        'success' => true,
        'message' => 'Raynet přihlašovací údaje byly odstraněny'
    ];
}
