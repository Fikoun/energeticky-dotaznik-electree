<?php
/**
 * Raynet CRM Configuration
 * 
 * DEPRECATED: Global credentials are no longer used.
 * Each user now manages their own Raynet API key via the admin panel
 * (Raynet API page). Credentials are stored per-user in the users table.
 * 
 * This file only provides default timeout/retry settings used by RaynetApiClient.
 */

return [
    // Per-user credentials are stored in the database (users table).
    // These fields are kept empty for backward compatibility.
    'username' => '',
    'api_key' => '',
    'instance_name' => '',
    
    'timeout' => 30,                         // HTTP timeout in seconds
    'retry_attempts' => 3,                   // Number of retries on rate limit
    'retry_delay' => 2,                      // Delay between retries in seconds
];
