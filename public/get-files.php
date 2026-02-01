<?php
/**
 * Get Files Handler
 * 
 * Retrieves uploaded files for a specific form and/or field.
 * Used to sync frontend state with backend after page reload.
 */

require_once __DIR__ . '/../config/database.php';

// CORS and headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Get parameters from GET or POST
    $formId = $_GET['formId'] ?? $_POST['formId'] ?? null;
    $fieldName = $_GET['fieldName'] ?? $_POST['fieldName'] ?? null;
    
    if (!$formId) {
        throw new Exception('ID formuláře je povinné');
    }
    
    $pdo = getDbConnection();
    
    // Build query
    $sql = "
        SELECT 
            id, 
            form_id AS formId,
            field_name AS fieldName, 
            original_name AS originalName, 
            file_name AS fileName, 
            file_path AS path,
            file_size AS size, 
            mime_type AS mimeType,
            thumbnail_path AS thumbnailPath,
            uploaded_at AS uploadedAt
        FROM form_files 
        WHERE form_id = ? AND deleted_at IS NULL
    ";
    $params = [$formId];
    
    // Filter by field name if provided
    if ($fieldName) {
        $sql .= " AND field_name = ?";
        $params[] = $fieldName;
    }
    
    $sql .= " ORDER BY uploaded_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format files for frontend
    $formattedFiles = array_map(function($file) {
        $fileId = $file['id'];
        
        // Use serve-file.php to serve files (works with private uploads)
        $fileUrl = '/public/serve-file.php?id=' . urlencode($fileId);
        $thumbnailUrl = $file['thumbnailPath'] 
            ? '/public/serve-file.php?id=' . urlencode($fileId) . '&thumb=1' 
            : null;
        
        return [
            'id' => $fileId,
            'fieldName' => $file['fieldName'],
            'originalName' => $file['originalName'],
            'fileName' => $file['fileName'],
            'path' => $file['path'],
            'url' => $fileUrl,
            'size' => (int)$file['size'],
            'formattedSize' => formatFileSize($file['size']),
            'mimeType' => $file['mimeType'],
            'thumbnailUrl' => $thumbnailUrl,
            'uploadedAt' => $file['uploadedAt']
        ];
    }, $files);
    
    // Group by field if no specific field requested
    if (!$fieldName) {
        $grouped = [];
        foreach ($formattedFiles as $file) {
            $field = $file['fieldName'];
            if (!isset($grouped[$field])) {
                $grouped[$field] = [];
            }
            $grouped[$field][] = $file;
        }
        
        echo json_encode([
            'success' => true,
            'files' => $formattedFiles,
            'byField' => $grouped,
            'total' => count($files)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'files' => $formattedFiles,
            'fieldName' => $fieldName,
            'total' => count($files)
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get files error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor(log($bytes) / log(1024));
    
    return round($bytes / pow(1024, $factor), 2) . ' ' . $units[$factor];
}
?>
