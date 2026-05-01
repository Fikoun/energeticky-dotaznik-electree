<?php
// Zabránit jakémukoli HTML výstupu
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Vypnout zobrazování chyb do výstupu
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Session auth – must be called before any output
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášený uživatel']);
    exit;
}

// Database configuration - use centralized config
require_once __DIR__ . '/../config/database.php';

$useDatabase = false;
$pdo = null;

// Zkusíme databázové připojení
try {
    $pdo = getDbConnection();
    $useDatabase = true;
} catch(Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $useDatabase = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $_GET['userId'] ?? null;

    // Users can only retrieve their own forms; admins can retrieve any user's forms
    if ($userId && $userId !== $_SESSION['user_id'] && ($_SESSION['user_role'] ?? '') !== 'admin') {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['error' => 'Nedostatečná oprávnění']);
        exit;
    }

    // Default to the current user's ID if none provided
    if (!$userId) {
        $userId = $_SESSION['user_id'];
    }

    if ($useDatabase) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, user_id, company_name, contact_person, phone, email, 
                       status, form_data, gdpr_token, gdpr_confirmed_at, 
                       created_at, updated_at
                FROM forms 
                WHERE user_id = ? 
                ORDER BY updated_at DESC, created_at DESC
            ");
            
            $stmt->execute([$userId]);
            $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format dates for better readability
            foreach ($forms as &$form) {
                if ($form['created_at']) {
                    $form['created_at'] = date('c', strtotime($form['created_at']));
                }
                if ($form['updated_at']) {
                    $form['updated_at'] = date('c', strtotime($form['updated_at']));
                }
                if ($form['gdpr_confirmed_at']) {
                    $form['gdpr_confirmed_at'] = date('c', strtotime($form['gdpr_confirmed_at']));
                }
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'forms' => $forms
            ]);
            
        } catch(PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            $useDatabase = false;
        }
    }

    if (!$useDatabase) {
        ob_end_clean();
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Databáze není dostupná']);
    }

} else {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
