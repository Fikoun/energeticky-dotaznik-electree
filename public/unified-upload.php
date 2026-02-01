<?php
/**
 * Unified File Upload Handler
 * 
 * Features:
 * - Server-side file type verification using finfo_file()
 * - Consistent filename sanitization
 * - Database storage for all uploads
 * - Thumbnail generation for images
 * - Size limits and validation
 */

require_once __DIR__ . '/../config/database.php';

// CORS and headers
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

// Configuration
const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB per file
const MAX_TOTAL_SIZE = 200 * 1024 * 1024; // 200MB total per request
const THUMBNAIL_SIZE = 200; // 200px max dimension

// Allowed MIME types per field
const ALLOWED_MIME_TYPES = [
    'sitePhotos' => ['image/jpeg', 'image/png', 'image/heic', 'image/heif'],
    'visualizations' => ['image/jpeg', 'image/png', 'application/pdf', 'image/vnd.dwg', 'application/acad', 'application/x-acad', 'application/x-autocad', 'image/x-dwg'],
    'projectDocumentationFiles' => ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'distributionCurvesFile' => ['application/pdf', 'image/jpeg', 'image/png', 'text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'billingDocuments' => ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'cogenerationPhotos' => ['image/jpeg', 'image/png', 'image/heic', 'image/heif'],
    // Default for unknown fields
    'default' => ['image/jpeg', 'image/png', 'application/pdf']
];

// Image MIME types (for thumbnail generation)
const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

try {
    $pdo = getDbConnection();
    
    // Ensure form_files table exists
    ensureTableExists($pdo);
    
    // Get form ID and field name from POST data
    $formId = $_POST['formId'] ?? null;
    $fieldName = $_POST['fieldName'] ?? 'unknown';
    
    if (!$formId) {
        throw new Exception('ID formuláře je povinné');
    }
    
    // Create upload directories
    $uploadDir = __DIR__ . '/uploads/';
    $formDir = $uploadDir . $formId . '/';
    $thumbDir = $formDir . 'thumbnails/';
    
    // Check and create directories with error handling
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            throw new Exception('Nelze vytvořit složku pro nahrávání. Kontaktujte administrátora pro nastavení oprávnění složky: ' . $uploadDir);
        }
    }
    
    if (!is_writable($uploadDir)) {
        throw new Exception('Složka pro nahrávání není zapisovatelná. Kontaktujte administrátora pro nastavení oprávnění složky: ' . $uploadDir);
    }
    
    if (!is_dir($formDir)) {
        if (!@mkdir($formDir, 0755, true)) {
            throw new Exception('Nelze vytvořit složku pro formulář: ' . $formDir);
        }
    }
    if (!is_dir($thumbDir)) {
        if (!@mkdir($thumbDir, 0755, true)) {
            // Thumbnail directory is optional, just log the error
            error_log("Could not create thumbnail directory: " . $thumbDir);
        }
    }
    
    $uploadedFiles = [];
    $errors = [];
    $totalSize = 0;
    
    // Process uploaded files
    $files = $_FILES['files'] ?? null;
    
    if (!$files) {
        throw new Exception('Žádné soubory nebyly odeslány');
    }
    
    // Normalize files array (handle both single and multiple)
    $normalizedFiles = normalizeFilesArray($files);
    
    // Calculate total size first
    foreach ($normalizedFiles as $file) {
        $totalSize += $file['size'];
    }
    
    if ($totalSize > MAX_TOTAL_SIZE) {
        throw new Exception('Celková velikost souborů překračuje limit ' . formatFileSize(MAX_TOTAL_SIZE));
    }
    
    // Get allowed MIME types for this field
    $allowedMimeTypes = ALLOWED_MIME_TYPES[$fieldName] ?? ALLOWED_MIME_TYPES['default'];
    
    // Process each file
    foreach ($normalizedFiles as $index => $file) {
        try {
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Soubor '{$file['name']}': " . getUploadErrorMessage($file['error']);
                continue;
            }
            
            // Check file size
            if ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = "Soubor '{$file['name']}' překračuje maximální velikost " . formatFileSize(MAX_FILE_SIZE);
                continue;
            }
            
            // Verify MIME type using finfo_file()
            $detectedMimeType = getActualMimeType($file['tmp_name']);
            
            if (!in_array($detectedMimeType, $allowedMimeTypes)) {
                $errors[] = "Soubor '{$file['name']}' má nepodporovaný typ ($detectedMimeType)";
                continue;
            }
            
            // Sanitize filename consistently
            $originalName = $file['name'];
            $sanitizedName = sanitizeFileName($originalName);
            $uniqueFileName = $fieldName . '_' . uniqid() . '_' . $sanitizedName;
            $targetPath = $formDir . $uniqueFileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                $errors[] = "Nepodařilo se uložit soubor '{$file['name']}'";
                continue;
            }
            
            // Generate thumbnail for images
            $thumbnailPath = null;
            $thumbnailUrl = null;
            
            if (in_array($detectedMimeType, IMAGE_MIME_TYPES)) {
                $thumbFileName = 'thumb_' . $uniqueFileName;
                $thumbnailPath = $thumbDir . $thumbFileName;
                
                if (generateThumbnail($targetPath, $thumbnailPath, THUMBNAIL_SIZE)) {
                    $thumbnailUrl = 'uploads/' . $formId . '/thumbnails/' . $thumbFileName;
                }
            }
            
            // Generate unique file ID
            $fileId = uniqid('file_', true);
            
            // Store in database
            $stmt = $pdo->prepare("
                INSERT INTO form_files (
                    id, form_id, field_name, original_name, file_name, 
                    file_path, file_size, mime_type, thumbnail_path, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $relativeFilePath = 'uploads/' . $formId . '/' . $uniqueFileName;
            
            $stmt->execute([
                $fileId,
                $formId,
                $fieldName,
                $originalName,
                $uniqueFileName,
                $relativeFilePath,
                $file['size'],
                $detectedMimeType,
                $thumbnailUrl
            ]);
            
            $uploadedFiles[] = [
                'id' => $fileId,
                'originalName' => $originalName,
                'fileName' => $uniqueFileName,
                'path' => $relativeFilePath,
                'url' => '/public/' . $relativeFilePath,
                'size' => $file['size'],
                'formattedSize' => formatFileSize($file['size']),
                'mimeType' => $detectedMimeType,
                'thumbnailUrl' => $thumbnailUrl ? '/public/' . $thumbnailUrl : null,
                'uploadedAt' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $errors[] = "Chyba při zpracování souboru '{$file['name']}': " . $e->getMessage();
        }
    }
    
    // Return response
    if (empty($uploadedFiles) && !empty($errors)) {
        echo json_encode([
            'success' => false,
            'error' => implode('; ', $errors)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'files' => $uploadedFiles,
            'errors' => $errors,
            'message' => 'Úspěšně nahráno ' . count($uploadedFiles) . ' soubor(ů)'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Unified upload error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Ensure form_files table exists with proper schema
 */
function ensureTableExists($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS form_files (
            id VARCHAR(50) PRIMARY KEY,
            form_id VARCHAR(100) NOT NULL,
            field_name VARCHAR(100) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL,
            mime_type VARCHAR(100),
            thumbnail_path VARCHAR(500),
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            INDEX idx_form_id (form_id),
            INDEX idx_field_name (field_name),
            INDEX idx_form_field (form_id, field_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Normalize files array to handle both single and multiple uploads
 */
function normalizeFilesArray($files) {
    $normalized = [];
    
    if (is_array($files['name'])) {
        // Multiple files
        for ($i = 0; $i < count($files['name']); $i++) {
            $normalized[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
        }
    } else {
        // Single file
        $normalized[] = $files;
    }
    
    return $normalized;
}

/**
 * Get actual MIME type using finfo_file()
 */
function getActualMimeType($filePath) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    // Handle special cases for HEIC files
    if ($mimeType === 'application/octet-stream') {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($extension, ['heic', 'heif'])) {
            return 'image/heic';
        }
    }
    
    return $mimeType;
}

/**
 * Consistent filename sanitization
 * Preserves Czech characters, removes dangerous ones
 */
function sanitizeFileName($fileName) {
    // Get file extension
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
    
    // Remove dangerous characters but keep letters, numbers, dashes, underscores
    // Also preserve Czech diacritics
    $baseName = preg_replace('/[<>:\"\/\\\\|?*\x00-\x1f]/', '', $baseName);
    
    // Replace multiple spaces/underscores with single underscore
    $baseName = preg_replace('/[\s_]+/', '_', $baseName);
    
    // Trim underscores from ends
    $baseName = trim($baseName, '_');
    
    // Ensure we have a valid basename
    if (empty($baseName)) {
        $baseName = 'file';
    }
    
    // Limit length
    if (mb_strlen($baseName) > 100) {
        $baseName = mb_substr($baseName, 0, 100);
    }
    
    // Sanitize extension
    $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
    
    return $baseName . ($extension ? '.' . $extension : '');
}

/**
 * Generate thumbnail for image files
 */
function generateThumbnail($sourcePath, $targetPath, $maxSize) {
    try {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        $mimeType = $imageInfo['mime'];
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Calculate new dimensions
        if ($width > $height) {
            $newWidth = min($width, $maxSize);
            $newHeight = intval($height * ($newWidth / $width));
        } else {
            $newHeight = min($height, $maxSize);
            $newWidth = intval($width * ($newHeight / $height));
        }
        
        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Create thumbnail
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save thumbnail as JPEG for consistency
        $result = imagejpeg($thumb, $targetPath, 85);
        
        // Free memory
        imagedestroy($source);
        imagedestroy($thumb);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Thumbnail generation error: " . $e->getMessage());
        return false;
    }
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

/**
 * Get human-readable upload error message
 */
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'Soubor překračuje maximální velikost povolenou serverem';
        case UPLOAD_ERR_FORM_SIZE:
            return 'Soubor překračuje maximální velikost povolenou formulářem';
        case UPLOAD_ERR_PARTIAL:
            return 'Soubor byl nahrán pouze částečně';
        case UPLOAD_ERR_NO_FILE:
            return 'Žádný soubor nebyl nahrán';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Chybí dočasná složka na serveru';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Nepodařilo se zapsat soubor na disk';
        case UPLOAD_ERR_EXTENSION:
            return 'Nahrávání bylo zastaveno rozšířením PHP';
        default:
            return 'Neznámá chyba při nahrávání';
    }
}
?>
