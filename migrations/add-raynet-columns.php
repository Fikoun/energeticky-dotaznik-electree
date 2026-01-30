<?php
/**
 * Database Migration: Add Raynet sync columns to forms table
 * 
 * Run this script once to add the required columns for Raynet sync tracking.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();
    
    echo "Checking and adding Raynet sync columns to forms table...\n";
    
    // Check if columns exist
    $stmt = $pdo->query("DESCRIBE forms");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $migrations = [];
    
    // raynet_company_id
    if (!in_array('raynet_company_id', $columns)) {
        $migrations[] = "ALTER TABLE forms ADD COLUMN raynet_company_id INT NULL";
    }
    
    // raynet_person_id
    if (!in_array('raynet_person_id', $columns)) {
        $migrations[] = "ALTER TABLE forms ADD COLUMN raynet_person_id INT NULL";
    }
    
    // raynet_synced_at
    if (!in_array('raynet_synced_at', $columns)) {
        $migrations[] = "ALTER TABLE forms ADD COLUMN raynet_synced_at DATETIME NULL";
    }
    
    // raynet_sync_error
    if (!in_array('raynet_sync_error', $columns)) {
        $migrations[] = "ALTER TABLE forms ADD COLUMN raynet_sync_error TEXT NULL";
    }
    
    if (empty($migrations)) {
        echo "All Raynet columns already exist. No changes needed.\n";
    } else {
        foreach ($migrations as $sql) {
            echo "Running: $sql\n";
            $pdo->exec($sql);
        }
        echo "Migration completed successfully!\n";
    }
    
    // Add index for faster queries
    $indexCheck = $pdo->query("SHOW INDEX FROM forms WHERE Key_name = 'idx_raynet_sync'");
    if ($indexCheck->rowCount() === 0) {
        echo "Adding index for raynet_synced_at...\n";
        $pdo->exec("CREATE INDEX idx_raynet_sync ON forms (raynet_synced_at, raynet_sync_error(100))");
        echo "Index created.\n";
    }
    
    echo "\nDone! Raynet sync columns are ready.\n";
    
} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
