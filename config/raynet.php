<?php
/**
 * Raynet CRM API Configuration
 * 
 * Credentials for connecting to Raynet CRM API.
 * Replace with your actual credentials from Raynet.
 */

return [
    'username' => 'marketing@electree.cz',  // Raynet login email
    'api_key' => 'crm-YQw0MQkOCBkL1MzpMFMIsD1TT7g',            // Raynet API key (from Settings > API)
    'instance_name' => 'electree',      // Raynet instance name
    
    'timeout' => 30,                         // HTTP timeout in seconds
    'retry_attempts' => 3,                   // Number of retries on rate limit
    'retry_delay' => 2,                      // Delay between retries in seconds
];
