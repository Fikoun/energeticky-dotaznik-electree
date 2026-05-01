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

session_set_cookie_params(["path" => "/", "httponly" => true, "samesite" => "Lax", "secure" => (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")]);
session_start();

// Any authenticated user can manage their own Raynet credentials
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nepřihlášen']);
    exit;
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
            $result = testCredentials($data);
            break;
            
        case 'clear_credentials':
            validateCsrf($data);
            $result = clearCredentials($pdo, $userId);
            break;
            
        case 'get_csrf_token':
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            $result = ['success' => true, 'csrf_token' => $_SESSION['csrf_token']];
            break;
            
        default:
            throw new Exception("Neznámá akce: {$action}");
    }
    
    ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("User Raynet credentials API error: " . $e->getMessage());
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
        throw new Exception('Neplatný CSRF token');
    }
}

function getCredentialStatus(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare("SELECT raynet_username, raynet_api_key, raynet_instance_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        return ['success' => true, 'configured' => false];
    }
    
    $configured = !empty($row['raynet_api_key']) && !empty($row['raynet_username']) && !empty($row['raynet_instance_name']);
    
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

function testCredentials(array $data): array
{
    $username = trim($data['username'] ?? '');
    $apiKey = trim($data['api_key'] ?? '');
    $instanceName = trim($data['instance_name'] ?? '');
    
    if (empty($username) || empty($apiKey) || empty($instanceName)) {
        return [
            'success' => false,
            'error' => 'Vyplňte všechna pole (uživatelské jméno, API klíč, název instance)'
        ];
    }
    
    try {
        $client = new RaynetApiClient($username, $apiKey, $instanceName);
        
        // Use the same test as the existing test_connection: fetch 1 company
        $results = $client->get('/company/', ['limit' => 1]);
        
        return [
            'success' => true,
            'message' => 'Připojení k Raynet je funkční',
            'rate_limit' => $client->getRateLimitRemaining()
        ];
        
    } catch (RaynetException $e) {
        return [
            'success' => false,
            'error' => 'Test připojení selhal: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
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
        return [
            'success' => false,
            'error' => 'Vyplňte všechna pole (uživatelské jméno, API klíč, název instance)'
        ];
    }
    
    // Test credentials before saving
    $testResult = testCredentials($data);
    if (!$testResult['success']) {
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
        return [
            'success' => false,
            'error' => 'Uživatel nebyl nalezen'
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Raynet přihlašovací údaje byly uloženy a ověřeny'
    ];
}

function clearCredentials(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare("
        UPDATE users 
        SET raynet_username = NULL, raynet_api_key = NULL, raynet_instance_name = NULL, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    
    return [
        'success' => true,
        'message' => 'Raynet přihlašovací údaje byly odstraněny'
    ];
}
