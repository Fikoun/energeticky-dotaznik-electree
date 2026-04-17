<?php
/**
 * Migration: Add per-user Raynet CRM credentials columns to users table.
 * 
 * Run: php migrations/add-user-raynet-credentials.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();
    
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'raynet_api_key'");
    if ($stmt->rowCount() > 0) {
        echo "Migration already applied: raynet columns exist in users table.\n";
        exit(0);
    }
    
    $pdo->exec("
        ALTER TABLE users
            ADD COLUMN raynet_username VARCHAR(255) DEFAULT NULL AFTER dic,
            ADD COLUMN raynet_api_key VARCHAR(255) DEFAULT NULL AFTER raynet_username,
            ADD COLUMN raynet_instance_name VARCHAR(255) DEFAULT NULL AFTER raynet_api_key
    ");
    
    echo "Migration successful: Added raynet_username, raynet_api_key, raynet_instance_name to users table.\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
