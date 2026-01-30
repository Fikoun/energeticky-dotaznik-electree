<?php
/**
 * Raynet Sync API Endpoint
 * 
 * Provides API endpoints for syncing forms to Raynet CRM.
 * 
 * Endpoints:
 * - POST /api/raynet-sync.php?action=sync&form_id=123 - Sync single form
 * - POST /api/raynet-sync.php?action=sync-pending - Sync all pending forms
 * - GET  /api/raynet-sync.php?action=status&form_id=123 - Get sync status
 */

ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Load dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Raynet/RaynetConnector.php';

use Raynet\RaynetConnector;
use Raynet\RaynetException;

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'status';
    $formId = $_GET['form_id'] ?? $_POST['form_id'] ?? null;
    
    $pdo = getDbConnection();
    $connector = RaynetConnector::create($pdo);
    
    // Check if configured
    if (!$connector->isConfigured()) {
        throw new RaynetException("Raynet connector is not configured. Please update config/raynet.php");
    }
    
    $result = [];
    
    switch ($action) {
        case 'sync':
            // Sync single form
            if (!$formId) {
                throw new RaynetException("form_id is required");
            }
            
            // Get form data
            $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
            $stmt->execute([$formId]);
            $form = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$form) {
                throw new RaynetException("Form not found: {$formId}");
            }
            
            $result = $connector->syncForm($form, (int)$formId);
            break;
            
        case 'resync':
            // Force resync (clear error and sync again)
            if (!$formId) {
                throw new RaynetException("form_id is required");
            }
            
            $result = $connector->resyncForm((int)$formId);
            break;
            
        case 'sync-pending':
            // Sync all pending forms
            $result = $connector->syncPendingForms();
            break;
            
        case 'status':
            // Get sync status for a form
            if (!$formId) {
                throw new RaynetException("form_id is required");
            }
            
            $status = $connector->getSyncStatus((int)$formId);
            
            $result = [
                'success' => true,
                'form_id' => (int)$formId,
                'synced' => !empty($status['raynet_synced_at']),
                'company_id' => $status['raynet_company_id'] ?? null,
                'person_id' => $status['raynet_person_id'] ?? null,
                'synced_at' => $status['raynet_synced_at'] ?? null,
                'error' => $status['raynet_sync_error'] ?? null
            ];
            break;
            
        case 'test':
            // Test connection to Raynet API
            $company = $connector->company();
            $results = $company->search([], 1);
            
            $result = [
                'success' => true,
                'message' => 'Raynet connection successful',
                'rate_limit_remaining' => $connector->getClient()->getRateLimitRemaining()
            ];
            break;
            
        default:
            throw new RaynetException("Unknown action: {$action}");
    }
    
    ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (RaynetException $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Raynet sync API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ], JSON_UNESCAPED_UNICODE);
}
