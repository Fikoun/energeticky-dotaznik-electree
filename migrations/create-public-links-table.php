<?php
/**
 * Migration: Create public_form_links table
 *
 * Stores shareable form links created by registered users (salesmen/admins)
 * for external users to fill forms without an account.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS public_form_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL UNIQUE,
            owner_user_id VARCHAR(255) NOT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255) DEFAULT NULL,
            password_hash VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            form_id VARCHAR(255) DEFAULT NULL,
            status ENUM('active', 'used', 'expired', 'revoked') NOT NULL DEFAULT 'active',
            expires_at DATETIME DEFAULT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_token (token),
            INDEX idx_owner (owner_user_id),
            INDEX idx_status (status),
            INDEX idx_recipient_email (recipient_email),
            CONSTRAINT fk_public_links_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Add public_link_id column to forms table to track which link spawned the form
    $cols = $pdo->query("SHOW COLUMNS FROM forms LIKE 'public_link_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE forms ADD COLUMN public_link_id INT DEFAULT NULL AFTER user_id");
        echo "Added public_link_id column to forms table.\n";
    }

    echo "Migration completed: public_form_links table created successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
