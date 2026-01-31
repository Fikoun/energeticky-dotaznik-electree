<?php
/**
 * File Deletion Handler
 * 
 * Deletes uploaded files from both filesystem and database.
 * Supports soft-delete (marking as deleted) or hard-delete.
 */

require_once __DIR__ . '/../config/database.php';

// CORS and headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda není povolena']);
    exit;
}

try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Also check POST data for form submissions
    if (empty($input)) {
        $input = $_POST;
    }
    
    $fileId = $input['fileId'] ?? null;
    $formId = $input['formId'] ?? null;
    $hardDelete = isset($input['hardDelete']) && $input['hardDelete'] === true;
    
    if (!$fileId) {
        throw new Exception('ID souboru je povinné');
    }
    
    $pdo = getDbConnection();
    
    // Get file info
    $stmt = $pdo->prepare("
        SELECT id, form_id, file_path, thumbnail_path 
        FROM form_files 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        throw new Exception('Soubor nebyl nalezen');
    }
    
    // Optional: Verify form ownership
    if ($formId && $file['form_id'] !== $formId) {
        throw new Exception('Soubor nepatří k zadanému formuláři');
    }
    
    $uploadDir = __DIR__ . '/';
    
    if ($hardDelete) {
        // Hard delete - remove from filesystem and database
        
        // Delete main file
        $filePath = $uploadDir . $file['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete thumbnail if exists
        if ($file['thumbnail_path']) {
            $thumbPath = $uploadDir . $file['thumbnail_path'];
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM form_files WHERE id = ?");
        $stmt->execute([$fileId]);
        
    } else {
        // Soft delete - just mark as deleted
        $stmt = $pdo->prepare("UPDATE form_files SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$fileId]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Soubor byl úspěšně odstraněn',
        'fileId' => $fileId
    ]);
    
} catch (Exception $e) {
    error_log("File deletion error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
