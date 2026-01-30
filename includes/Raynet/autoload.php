<?php
/**
 * Raynet CRM Classes Autoloader
 * 
 * Include this file to autoload all Raynet classes.
 */

spl_autoload_register(function ($class) {
    // Only handle Raynet namespace
    if (strpos($class, 'Raynet\\') !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $className = str_replace('Raynet\\', '', $class);
    $file = __DIR__ . '/' . $className . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Also load exception classes immediately (they're commonly needed)
require_once __DIR__ . '/RaynetException.php';
