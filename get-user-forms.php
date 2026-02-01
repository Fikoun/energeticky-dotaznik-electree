<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration - use centralized config
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $_GET['userId'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing userId parameter']);
        exit;
    }

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
        
        echo json_encode([
            'success' => true,
            'forms' => $forms
        ]);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
