<?php
/**
 * Database Migration: Create logs table
 * 
 * General-purpose logging table for the entire application.
 * Can be used for Raynet sync logs, API calls, errors, etc.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();
    
    echo "Creating logs table...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context JSON NULL,
            user_id INT NULL,
            form_id INT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_type (type),
            INDEX idx_level (level),
            INDEX idx_user_id (user_id),
            INDEX idx_form_id (form_id),
            INDEX idx_created_at (created_at),
            INDEX idx_type_level (type, level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ Logs table created successfully\n";
    
    // Create a view for quick access to Raynet logs
    $pdo->exec("
        CREATE OR REPLACE VIEW raynet_logs AS
        SELECT 
            l.*,
            u.name as user_name,
            f.company_name,
            f.contact_person
        FROM logs l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN forms f ON l.form_id = f.id
        WHERE l.type = 'raynet'
        ORDER BY l.created_at DESC
    ");
    
    echo "✓ Raynet logs view created\n";
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
