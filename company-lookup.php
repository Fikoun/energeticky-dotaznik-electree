<?php
/**
 * Company lookup redirect
 * This file exists at root level for dev mode compatibility
 * In production (dist/), the standalone version in public/ is used
 */

// Check if we're in production (dist folder) or development
if (file_exists(__DIR__ . '/public/company-lookup.php')) {
    // Development mode - include from public folder
    require_once __DIR__ . '/public/company-lookup.php';
} else {
    // Production mode - this file shouldn't be used, but handle gracefully
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode([
        'success' => false,
        'error' => 'API endpoint misconfiguration. Please use /public/company-lookup.php'
    ]);
    exit(0);
}
