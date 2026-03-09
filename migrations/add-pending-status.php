<?php
/**
 * Migration: Add 'pending' status to forms table ENUM
 * 
 * The form flow is: draft → pending (submitted, awaiting GDPR) → confirmed → processed
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();
    
    echo "Updating forms.status ENUM to include 'pending'..." . PHP_EOL;
    
    $pdo->exec("ALTER TABLE forms MODIFY COLUMN status ENUM('draft', 'pending', 'submitted', 'confirmed', 'processed') NOT NULL DEFAULT 'draft'");
    
    echo "✓ Status ENUM updated successfully" . PHP_EOL;
    
    // Show new column definition
    $stmt = $pdo->query("SHOW COLUMNS FROM forms WHERE Field = 'status'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  New type: " . $col['Type'] . PHP_EOL;
    
    echo PHP_EOL . "✅ Migration completed!" . PHP_EOL;
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . PHP_EOL;
}
