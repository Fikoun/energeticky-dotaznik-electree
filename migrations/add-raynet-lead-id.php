<?php
/**
 * Database Migration: Add raynet_lead_id column to forms table
 *
 * Run this script once to add the column used for tracking the Raynet Lead ID
 * created during form synchronisation.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();

    echo "Checking and adding raynet_lead_id column to forms table...\n";

    $stmt    = $pdo->query("DESCRIBE forms");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('raynet_lead_id', $columns)) {
        $pdo->exec("ALTER TABLE forms ADD COLUMN raynet_lead_id INT NULL");
        echo "Added column: raynet_lead_id INT NULL\n";
    } else {
        echo "Column raynet_lead_id already exists. No changes needed.\n";
    }

    echo "\nDone!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
