<?php
/**
 * Test Logging System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Logger.php';

try {
    echo "=== Testing Logging System ===\n\n";
    
    $pdo = getDbConnection();
    $logger = Logger::create(1, null); // User ID 1
    
    // Test different log levels
    echo "1. Testing log levels...\n";
    $logger->debug(Logger::TYPE_RAYNET, 'This is a debug message', ['test' => true]);
    $logger->info(Logger::TYPE_RAYNET, 'This is an info message');
    $logger->warning(Logger::TYPE_RAYNET, 'This is a warning message');
    $logger->error(Logger::TYPE_RAYNET, 'This is an error message', ['error_code' => 'TEST_ERROR']);
    $logger->critical(Logger::TYPE_RAYNET, 'This is a critical message');
    echo "✓ All log levels tested\n\n";
    
    // Test reading logs
    echo "2. Testing log retrieval...\n";
    $logs = $logger->getLogs(Logger::TYPE_RAYNET, null, 10, 0);
    echo "✓ Found " . count($logs) . " logs\n\n";
    
    // Test error count
    echo "3. Testing error count...\n";
    $errorCount = $logger->getErrorCount(Logger::TYPE_RAYNET);
    echo "✓ Total error/critical logs: {$errorCount}\n\n";
    
    // Test recent errors
    echo "4. Testing recent errors retrieval...\n";
    $recentErrors = $logger->getRecentErrors(Logger::TYPE_RAYNET, 5);
    echo "✓ Found " . count($recentErrors) . " recent errors\n\n";
    
    // Display sample logs
    echo "5. Sample logs:\n";
    echo "---\n";
    foreach (array_slice($logs, 0, 3) as $log) {
        echo "ID: {$log['id']}\n";
        echo "Level: {$log['level']}\n";
        echo "Message: {$log['message']}\n";
        echo "Created: {$log['created_at']}\n";
        echo "---\n";
    }
    
    echo "\n✅ All tests passed!\n";
    echo "\nYou can now:\n";
    echo "1. Visit http://localhost:8080/public/admin-sync.php\n";
    echo "2. Click on the '📋 Logy' tab\n";
    echo "3. Or click on the 'Chyby' card to see errors\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}
