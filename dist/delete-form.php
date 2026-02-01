<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration - use centralized config
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $formId = $input['formId'] ?? null;
    $userId = $input['userId'] ?? null;
    
    if (!$formId || !$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing formId or userId']);
        exit;
    }

    try {
        // Verify that the form belongs to the user
        $stmt = $pdo->prepare("SELECT id FROM forms WHERE id = ? AND user_id = ?");
        $stmt->execute([$formId, $userId]);
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Form not found or access denied']);
            exit;
        }
        
        // Delete the form
        $stmt = $pdo->prepare("DELETE FROM forms WHERE id = ? AND user_id = ?");
        $stmt->execute([$formId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Form deleted successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Form not found']);
        }
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database operation failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
