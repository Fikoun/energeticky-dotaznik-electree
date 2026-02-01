<?php
/**
 * Setup Permissions Script
 * 
 * Access URLs to try:
 * - https://ed.electree.cz/setup-permissions.php
 * - https://ed.electree.cz/public/setup-permissions.php
 * - https://ed.electree.cz/dist/public/setup-permissions.php
 * 
 * This will diagnose and attempt to fix upload folder permissions
 * DELETE THIS FILE after successful setup for security!
 */

// Test if PHP is running
if (!isset($_SERVER['PHP_SELF'])) {
    die('PHP is not running');
}

// Disable output buffering for immediate display
ob_end_clean();
ini_set('output_buffering', 'off');
ini_set('implicit_flush', true);
ob_implicit_flush(true);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup Upload Permissions</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .success { color: green; background: #e8f5e9; padding: 10px; margin: 10px 0; border-left: 4px solid green; border-radius: 4px; }
        .error { color: red; background: #ffebee; padding: 10px; margin: 10px 0; border-left: 4px solid red; border-radius: 4px; }
        .warning { color: orange; background: #fff3e0; padding: 10px; margin: 10px 0; border-left: 4px solid orange; border-radius: 4px; }
        .info { color: blue; background: #e3f2fd; padding: 10px; margin: 10px 0; border-left: 4px solid blue; border-radius: 4px; }
        pre { background: #f5f5f5; padding: 15px; overflow: auto; border: 1px solid #ddd; border-radius: 4px; }
        h1 { color: #333; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .box { border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 5px; background: white; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔧 Upload Folder Permissions Setup</h1>
    
    <div class="info">
        <strong>✅ PHP is working!</strong><br>
        This page is being served correctly.
    </div>

<?php

$uploadDir = __DIR__ . '/uploads/';
$testFile = $uploadDir . 'test_write.txt';

echo '<div class="box">';
echo '<h2>Step 1: Directory Information</h2>';
echo '<pre>';
echo "Target directory: $uploadDir\n";
echo "Current script user: " . get_current_user() . "\n";
echo "PHP process user: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'Unknown') . "\n";
echo '</pre>';
echo '</div>';

// Step 1: Check if directory exists
echo '<div class="box">';
echo '<h2>Step 2: Check Directory Exists</h2>';
if (is_dir($uploadDir)) {
    echo '<div class="success">✅ Directory exists: ' . $uploadDir . '</div>';
    
    // Get current permissions
    $perms = fileperms($uploadDir);
    $permsOctal = substr(sprintf('%o', $perms), -4);
    echo '<div class="info">Current permissions: ' . $permsOctal . '</div>';
    
    // Check ownership
    if (function_exists('posix_getpwuid')) {
        $owner = posix_getpwuid(fileowner($uploadDir));
        $group = posix_getgrgid(filegroup($uploadDir));
        echo '<div class="info">Owner: ' . $owner['name'] . ':' . $group['name'] . '</div>';
    }
} else {
    echo '<div class="warning">⚠️ Directory does not exist. Attempting to create...</div>';
    
    // Try to create directory
    if (@mkdir($uploadDir, 0775, true)) {
        echo '<div class="success">✅ Directory created successfully!</div>';
    } else {
        echo '<div class="error">❌ Failed to create directory. Please create it manually via FTP.</div>';
        echo '</body></html>';
        exit;
    }
}
echo '</div>';

// Step 2: Test write permissions
echo '<div class="box">';
echo '<h2>Step 3: Test Write Permission</h2>';

if (is_writable($uploadDir)) {
    echo '<div class="success">✅ Directory is writable!</div>';
    
    // Try to actually write a file
    if (@file_put_contents($testFile, 'Test write at ' . date('Y-m-d H:i:s'))) {
        echo '<div class="success">✅ Successfully wrote test file!</div>';
        
        // Clean up test file
        @unlink($testFile);
        echo '<div class="success">✅ Test file cleaned up.</div>';
        
        echo '<div class="success" style="margin-top: 20px; font-weight: bold;">';
        echo '🎉 SUCCESS! Everything is working correctly.<br>';
        echo '⚠️ IMPORTANT: Delete this file (setup-permissions.php) for security!';
        echo '</div>';
    } else {
        echo '<div class="error">❌ Directory reports as writable but cannot write file.</div>';
        attemptFix($uploadDir);
    }
} else {
    echo '<div class="error">❌ Directory is NOT writable by PHP process.</div>';
    attemptFix($uploadDir);
}
echo '</div>';

function attemptFix($uploadDir) {
    echo '<div class="box">';
    echo '<h2>Step 4: Attempting to Fix Permissions</h2>';
    
    $attempts = [
        '0777' => 'Full permissions (least secure but most compatible)',
        '0775' => 'Group writable',
        '0755' => 'Owner writable only'
    ];
    
    foreach ($attempts as $perm => $desc) {
        echo "<p>Trying $perm ($desc)...</p>";
        if (@chmod($uploadDir, octdec($perm))) {
            echo '<div class="success">✅ Successfully set permissions to ' . $perm . '</div>';
            
            // Test if it's now writable
            if (is_writable($uploadDir)) {
                echo '<div class="success">✅ Directory is now writable! Try uploading files.</div>';
                echo '<div class="warning">⚠️ IMPORTANT: Delete this file (setup-permissions.php) for security!</div>';
                return;
            } else {
                echo '<div class="warning">⚠️ Permissions set but still not writable by PHP.</div>';
            }
        } else {
            echo '<div class="error">❌ Could not set permissions to ' . $perm . '</div>';
        }
    }
    
    echo '<div class="error">';
    echo '<h3>❌ Automatic fix failed. Manual steps required:</h3>';
    echo '<ol>';
    echo '<li>Connect via FTP/SSH to your server</li>';
    echo '<li>Navigate to: <code>' . dirname($uploadDir) . '</code></li>';
    echo '<li>Right-click on "uploads" folder → Properties/Permissions</li>';
    echo '<li>Set permissions to <strong>775</strong> or <strong>777</strong></li>';
    echo '<li>Make sure to check "Apply to subdirectories"</li>';
    echo '<li>If using ISPConfig/cPanel, ensure the folder owner matches the website user</li>';
    echo '</ol>';
    
    echo '<h4>Alternative: Via SSH/Terminal</h4>';
    echo '<pre>';
    echo "cd " . dirname($uploadDir) . "\n";
    echo "chmod -R 775 uploads\n";
    echo "# If you have sudo access:\n";
    echo "sudo chown -R www-data:www-data uploads\n";
    echo '</pre>';
    echo '</div>';
    echo '</div>';
}

echo '<hr>';
echo '<p style="color: #666; font-size: 12px;">After successful setup, delete this file: ' . __FILE__ . '</p>';
echo '</body></html>';
?>
