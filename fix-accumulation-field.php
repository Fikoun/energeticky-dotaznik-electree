<?php
/**
 * Fix Script: Delete and recreate energyAccumulation field with correct type
 * 
 * This script:
 * 1. Deletes the incorrectly typed ef_has_accumulation field (BOOLEAN)
 * 2. Creates ef_accumulation_type field with correct STRING type
 * 3. Updates the field mapping in database
 * 
 * Run from command line: php fix-accumulation-field.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Raynet/RaynetApiClient.php';
require_once __DIR__ . '/includes/Raynet/RaynetCustomFields.php';
require_once __DIR__ . '/includes/Raynet/RaynetException.php';

use Raynet\RaynetApiClient;
use Raynet\RaynetCustomFields;
use Raynet\RaynetException;

echo "=== Fix energyAccumulation Field Script ===\n\n";

try {
    // Initialize
    $pdo = getDbConnection();
    $client = RaynetApiClient::fromConfig();
    $customFields = new RaynetCustomFields($client);
    
    $entityType = RaynetCustomFields::ENTITY_COMPANY;
    $oldFieldName = 'ef_has_accumulation';  // Old incorrect field (BOOLEAN)
    $formField = 'energyAccumulation';
    
    // Step 1: Get current mapping to find actual Raynet field name
    echo "Step 1: Checking current field mapping...\n";
    
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'raynet_field_mapping'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $mapping = $row ? json_decode($row['value'], true) : [];
    
    $actualFieldName = $mapping[$formField] ?? null;
    echo "  Current mapping for '{$formField}': " . ($actualFieldName ?: 'NOT MAPPED') . "\n";
    
    // Step 2: Try to delete the old field
    echo "\nStep 2: Attempting to delete old field...\n";
    
    $fieldsToTry = array_filter([$actualFieldName, $oldFieldName]);
    $deleted = false;
    
    foreach ($fieldsToTry as $fieldToDelete) {
        if (empty($fieldToDelete)) continue;
        
        echo "  Trying to delete: {$fieldToDelete}...\n";
        try {
            $customFields->deleteField($entityType, $fieldToDelete);
            echo "  ✓ Successfully deleted: {$fieldToDelete}\n";
            $deleted = true;
            break;
        } catch (RaynetException $e) {
            echo "  ✗ Could not delete {$fieldToDelete}: " . $e->getMessage() . "\n";
        }
    }
    
    if (!$deleted) {
        echo "  (No existing field found to delete - will create fresh)\n";
    }
    
    // Step 3: Create the field with correct type
    echo "\nStep 3: Creating new field with correct STRING type...\n";
    
    $fieldDef = RaynetCustomFields::FORM_FIELDS[$formField] ?? null;
    
    if (!$fieldDef) {
        throw new Exception("Field '{$formField}' not found in FORM_FIELDS definition");
    }
    
    echo "  Label: {$fieldDef['label']}\n";
    echo "  Type: {$fieldDef['type']} (STRING)\n";
    echo "  Group: {$fieldDef['group']}\n";
    
    $result = $customFields->createField($entityType, [
        'label' => $fieldDef['label'],
        'groupName' => $fieldDef['group'],
        'dataType' => $fieldDef['type'],  // TYPE_STRING
        'description' => "EnergyForms: {$formField} - Typ akumulace energie (neví/konkrétní hodnota)",
        'showInListView' => true,
        'showInFilterView' => true,
    ]);
    
    $newFieldName = $result['fieldName'] ?? null;
    
    if (!$newFieldName) {
        throw new Exception("Failed to create field - no field name returned");
    }
    
    echo "  ✓ Created new field: {$newFieldName}\n";
    
    // Step 4: Update mapping in database
    echo "\nStep 4: Updating field mapping in database...\n";
    
    $mapping[$formField] = $newFieldName;
    
    $stmt = $pdo->prepare("
        INSERT INTO settings (`key`, `value`)
        VALUES ('raynet_field_mapping', ?)
        ON DUPLICATE KEY UPDATE `value` = ?
    ");
    $jsonMapping = json_encode($mapping);
    $stmt->execute([$jsonMapping, $jsonMapping]);
    
    echo "  ✓ Mapping updated: {$formField} => {$newFieldName}\n";
    
    // Done
    echo "\n=== SUCCESS ===\n";
    echo "The energyAccumulation field has been recreated with STRING type.\n";
    echo "You can now re-sync your form and it should work correctly.\n";
    
} catch (Exception $e) {
    echo "\n=== ERROR ===\n";
    echo "Failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
