<?php
/**
 * File Serving Script
 * Serves uploaded files from private storage (outside web root)
 */

require_once __DIR__ . '/../config/database.php';

// Get file ID from request
$fileId = $_GET['id'] ?? null;
$thumbnail = isset($_GET['thumb']) && $_GET['thumb'] == '1';

if (!$fileId) {
    http_response_code(400);
    die('File ID is required');
}

try {
    $pdo = getDbConnection();
    
    // Get file info from database
    $stmt = $pdo->prepare("
        SELECT file_path, thumbnail_path, original_name, mime_type 
        FROM form_files 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        die('File not found');
    }
    
    // Determine which file to serve
    $filePath = $thumbnail && $file['thumbnail_path'] ? $file['thumbnail_path'] : $file['file_path'];
    
    // Check if file exists
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('File does not exist on disk');
    }
    
    // Check if file is readable
    if (!is_readable($filePath)) {
        http_response_code(403);
        die('File is not readable');
    }
    
    // Set headers
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
    header('Cache-Control: public, max-age=31536000');
    
    // Serve file
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    error_log("File serving error: " . $e->getMessage());
    http_response_code(500);
    die('Error serving file');
}
?>
