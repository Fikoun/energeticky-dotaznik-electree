<?php
/**
 * Database Migration: Add raynet_sync_status column to forms table
 * 
 * Tracks the sync lifecycle:
 *   - 'pending'          → waiting for GDPR confirmation before sync
 *   - 'synced'           → successfully synced to Raynet
 *   - 'pending_approval' → duplicate/ambiguity found, needs manual review
 *   - 'error'            → sync failed with an error
 *   - NULL               → legacy forms (not attempted)
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();

    echo "Checking for raynet_sync_status column...\n";

    $stmt = $pdo->query("DESCRIBE forms");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('raynet_sync_status', $columns)) {
        $sql = "ALTER TABLE forms ADD COLUMN raynet_sync_status VARCHAR(20) NULL DEFAULT NULL";
        echo "Running: $sql\n";
        $pdo->exec($sql);

        // Back-fill existing rows based on current sync columns
        $pdo->exec("UPDATE forms SET raynet_sync_status = 'synced'  WHERE raynet_synced_at IS NOT NULL");
        $pdo->exec("UPDATE forms SET raynet_sync_status = 'error'   WHERE raynet_sync_error IS NOT NULL AND raynet_synced_at IS NULL");
        $pdo->exec("UPDATE forms SET raynet_sync_status = 'pending' WHERE raynet_sync_status IS NULL AND status = 'submitted'");

        echo "Migration completed successfully!\n";
    } else {
        echo "Column raynet_sync_status already exists. No changes needed.\n";
    }

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
