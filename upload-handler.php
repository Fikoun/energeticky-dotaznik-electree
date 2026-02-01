<?php
// File upload handler for battery form
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda není povolena']);
    exit;
}

try {
    // Create uploads directory in public folder if it doesn't exist
    $uploadDir = 'public/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique form submission folder
    $formId = $_POST['formId'] ?? uniqid('form_');
    $formDir = $uploadDir . $formId . '/';
    
    if (!is_dir($formDir)) {
        mkdir($formDir, 0755, true);
    }
    
    $uploadedFiles = [];
    
    // Process each uploaded file
    foreach ($_FILES as $fieldName => $fileInfo) {
        if (is_array($fileInfo['name'])) {
            // Multiple files for this field
            for ($i = 0; $i < count($fileInfo['name']); $i++) {
                if ($fileInfo['error'][$i] === UPLOAD_ERR_OK) {
                    $fileName = sanitizeFileName($fileInfo['name'][$i]);
                    $targetPath = $formDir . $fieldName . '_' . ($i + 1) . '_' . $fileName;
                    
                    if (move_uploaded_file($fileInfo['tmp_name'][$i], $targetPath)) {
                        $uploadedFiles[$fieldName][] = [
                            'originalName' => $fileInfo['name'][$i],
                            'fileName' => basename($targetPath),
                            'path' => $targetPath,
                            'size' => $fileInfo['size'][$i],
                            'type' => $fileInfo['type'][$i]
                        ];
                    }
                }
            }
        } else {
            // Single file for this field
            if ($fileInfo['error'] === UPLOAD_ERR_OK) {
                $fileName = sanitizeFileName($fileInfo['name']);
                $targetPath = $formDir . $fieldName . '_' . $fileName;
                
                if (move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
                    $uploadedFiles[$fieldName] = [
                        'originalName' => $fileInfo['name'],
                        'fileName' => basename($targetPath),
                        'path' => $targetPath,
                        'size' => $fileInfo['size'],
                        'type' => $fileInfo['type']
                    ];
                }
            }
        }
    }
    
    // Database configuration - use centralized config
    require_once __DIR__ . '/config/database.php';
    $pdo = getDbConnection();
    
    // Store file information in database
    foreach ($uploadedFiles as $fieldName => $files) {
        $filesArray = is_array($files) ? $files : [$files];
        
        foreach ($filesArray as $file) {
            $stmt = $pdo->prepare("
                INSERT INTO form_files (form_id, field_name, original_name, file_name, file_path, file_size, file_type, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $formId,
                $fieldName,
                $file['originalName'],
                $file['fileName'],
                $file['path'],
                $file['size'],
                $file['type']
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'formId' => $formId,
        'uploadedFiles' => $uploadedFiles,
        'message' => 'Soubory byly úspěšně nahrány'
    ]);
    
} catch (Exception $e) {
    error_log("File upload error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Chyba při nahrávání souborů: ' . $e->getMessage()
    ]);
}

function sanitizeFileName($fileName) {
    // Remove dangerous characters and normalize filename
    $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $fileName = preg_replace('/_+/', '_', $fileName);
    return $fileName;
}
?>
