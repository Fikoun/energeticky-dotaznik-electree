<?php
/**
 * Debug script to check field mapping status
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Raynet/RaynetCustomFields.php';

use Raynet\RaynetCustomFields;

try {
    $pdo = getDbConnection();
    
    // Check if settings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    $tableExists = $stmt->rowCount() > 0;
    
    // Check if mapping exists
    $mapping = [];
    if ($tableExists) {
        $stmt = $pdo->prepare("SELECT `key`, `value`, `updated_at` FROM settings WHERE `key` = 'raynet_field_mapping'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $mapping = json_decode($row['value'], true) ?? [];
        }
    }
    
    // Get auto-mapping for comparison
    $autoMapping = RaynetCustomFields::getAutoMapping();
    
    // Count mapping targets
    $customMappings = [];
    foreach ($mapping as $formField => $raynetField) {
        $customMappings[$formField] = [
            'raynetField' => $raynetField,
            'inAutoMapping' => isset($autoMapping[$formField]),
            'suggestedName' => $autoMapping[$formField]['raynetField'] ?? $autoMapping[$formField]['suggestedName'] ?? null,
            'target' => $autoMapping[$formField]['target'] ?? 'unknown'
        ];
    }
    
    // Group by whether actual Raynet field name looks auto-generated
    $looksLikeRaynetName = [];
    $looksLikeSuggestedName = [];
    
    foreach ($mapping as $formField => $raynetField) {
        // Raynet auto-generated names have underscore + random chars at end
        if (preg_match('/_[a-f0-9]{5,}$/', $raynetField)) {
            $looksLikeRaynetName[$formField] = $raynetField;
        } else {
            $looksLikeSuggestedName[$formField] = $raynetField;
        }
    }
    
    echo json_encode([
        'success' => true,
        'tableExists' => $tableExists,
        'totalMappings' => count($mapping),
        'rawMapping' => $mapping,
        'analysis' => [
            'looksLikeActualRaynetNames' => count($looksLikeRaynetName),
            'looksLikeSuggestedNames' => count($looksLikeSuggestedName),
            'actualRaynetFields' => $looksLikeRaynetName,
            'suggestedFields' => $looksLikeSuggestedName
        ],
        'updatedAt' => $row['updated_at'] ?? null
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
