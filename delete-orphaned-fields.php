<?php
/**
 * Bulk delete orphaned Raynet custom fields
 * 
 * Run: php84 delete-orphaned-fields.php
 * 
 * This script deletes all EnergyForms custom fields (ef_*) from Raynet
 * that no longer have a matching AUTO_MAPPING entry, so you can
 * re-create them cleanly with the corrected mapping.
 * 
 * Options:
 *   --dry-run     Show what would be deleted without actually deleting (default)
 *   --execute     Actually delete the fields
 *   --all         Delete ALL ef_* fields (for full reset + re-create)
 */

require __DIR__ . '/includes/Raynet/autoload.php';

use Raynet\RaynetApiClient;
use Raynet\RaynetCustomFields;

// Parse args
$dryRun = !in_array('--execute', $argv ?? []);
$deleteAll = in_array('--all', $argv ?? []);

echo "=== Raynet Custom Field Cleanup ===\n";
echo $dryRun ? "MODE: DRY RUN (add --execute to actually delete)\n\n" : "MODE: EXECUTING DELETIONS\n\n";

// Get Raynet config
$raynetConfig = require __DIR__ . '/config/raynet.php';
if (!$raynetConfig || empty($raynetConfig['instance_name'])) {
    echo "ERROR: Raynet not configured. Check config/raynet.php\n";
    exit(1);
}

$client = new RaynetApiClient(
    $raynetConfig['username'],
    $raynetConfig['api_key'],
    $raynetConfig['instance_name']
);
$customFields = new RaynetCustomFields($client);

// Get current fields from Raynet
echo "Fetching current Raynet Company custom fields...\n";
$existingFields = $customFields->getCompanyFields();

if (empty($existingFields)) {
    echo "No custom fields found in Raynet.\n";
    exit(0);
}

// Get current AUTO_MAPPING suggested names
$mapping = RaynetCustomFields::getAutoMapping();
$activeSuggestedNames = [];
foreach ($mapping as $formField => $config) {
    if ($config['target'] === 'custom' && !empty($config['suggestedName'])) {
        $activeSuggestedNames[] = $config['suggestedName'];
    }
}

// Also get the mapping from the database (actual field names used in sync)
$dbMapping = [];
try {
    require __DIR__ . '/config/database.php';
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'raynet_field_mapping'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $dbMapping = json_decode($row['setting_value'], true) ?: [];
    }
} catch (Exception $e) {
    echo "Note: Could not read DB mapping: " . $e->getMessage() . "\n";
}

// Active Raynet field names (from DB mapping values)
$activeRaynetFields = array_values($dbMapping);

echo "\nFound " . count($existingFields) . " custom fields in Raynet.\n";
echo "Active mapping has " . count($activeSuggestedNames) . " suggested names.\n";
echo "DB mapping has " . count($activeRaynetFields) . " active field mappings.\n\n";

// Categorize fields
$toDelete = [];
$toKeep = [];

foreach ($existingFields as $field) {
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? '';
    $group = $field['groupName'] ?? '';
    
    // Only touch EnergyForms fields (by group name)
    // NEVER touch manually-created fields in "Specifické údaje" group
    $isEfField = (
        stripos($group, 'EnergyForms') !== false
    );
    
    if (!$isEfField) {
        continue; // Skip non-EnergyForms fields (keeps "Specifické údaje" like EAN/EIC, Typ střídače)
    }
    
    if ($deleteAll) {
        $toDelete[] = $field;
        continue;
    }
    
    // Check if this field is still actively used
    $isActive = in_array($name, $activeRaynetFields);
    
    if (!$isActive) {
        $toDelete[] = $field;
    } else {
        $toKeep[] = $field;
    }
}

echo "--- FIELDS TO KEEP (" . count($toKeep) . ") ---\n";
foreach ($toKeep as $f) {
    echo "  ✓ " . str_pad($f['name'] ?? '', 45) . " | " . ($f['label'] ?? '') . "\n";
}

echo "\n--- FIELDS TO DELETE (" . count($toDelete) . ") ---\n";
if (empty($toDelete)) {
    echo "  Nothing to delete!\n";
    exit(0);
}

foreach ($toDelete as $f) {
    echo "  ✗ " . str_pad($f['name'] ?? '', 45) . " | " . ($f['label'] ?? '') . " | Group: " . ($f['groupName'] ?? '') . "\n";
}

if ($dryRun) {
    echo "\n⚠️  DRY RUN - no changes made. Run with --execute to delete these fields.\n";
    echo "   Or use --all --execute to delete ALL EnergyForms fields for a full reset.\n";
    exit(0);
}

// Execute deletions
echo "\nDeleting " . count($toDelete) . " fields...\n";
$deleted = 0;
$failed = 0;

foreach ($toDelete as $field) {
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? '';
    
    try {
        $customFields->deleteField(RaynetCustomFields::ENTITY_COMPANY, $name);
        echo "  ✓ Deleted: {$name} ({$label})\n";
        $deleted++;
        usleep(200000); // 200ms rate limit
    } catch (Exception $e) {
        echo "  ✗ Failed: {$name} - " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n=== Done! Deleted: {$deleted}, Failed: {$failed} ===\n";

if ($deleteAll) {
    echo "\nAll EnergyForms fields deleted. Now re-create them via:\n";
    echo "  Admin Panel → Custom Fields → Create All Fields\n";
    echo "  This will create fields with the corrected mapping.\n";
}
