<?php
// Authentication API - handles login and session management
// NO output buffering - ensure errors are visible
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers immediately
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit(0);
}

// Wrap everything in try-catch to guarantee JSON output
try {
    // Start session safely
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // GET request - check session status
    if ($method === 'GET') {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Auth API ready',
            'authenticated' => isset($_SESSION['user_id']),
            'user' => isset($_SESSION['user_id']) ? [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['username'] ?? 'Unknown',
                'email' => $_SESSION['user_email'] ?? '',
                'role' => $_SESSION['user_role'] ?? 'user'
            ] : null
        ]);
        exit(0);
    }

    // POST request - handle login/logout
    if ($method === 'POST') {
        $input = @file_get_contents('php://input');
        
        if ($input === false || empty($input)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No input data received'
            ]);
            exit(0);
        }
        
        $data = json_decode($input, true);
        
        if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON: ' . json_last_error_msg()
            ]);
            exit(0);
        }
        
        $action = $data['action'] ?? '';
        
        if (empty($action)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Action is required'
            ]);
            exit(0);
        }
        
        // Handle login
        if ($action === 'login') {
            $username = trim($data['username'] ?? $data['nickname'] ?? '');
            $password = $data['password'] ?? '';
            
            if (empty($username)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Username is required'
                ]);
                exit(0);
            }
            
            // Simple hardcoded authentication
            if ($username === 'admin' && $password === 'admin123') {
                $_SESSION['user_id'] = 1;
                $_SESSION['username'] = 'admin';
                $_SESSION['user_email'] = 'admin@electree.cz';
                $_SESSION['user_role'] = 'admin';
                $_SESSION['is_logged_in'] = true;
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => 1,
                        'name' => 'Administrator',
                        'email' => 'admin@electree.cz',
                        'role' => 'admin'
                    ]
                ]);
                exit(0);
            } else {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Nesprávné přihlašovací údaje'
                ]);
                exit(0);
            }
        }
        
        // Handle logout
        if ($action === 'logout') {
            @session_destroy();
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
            exit(0);
        }
        
        // Unknown action
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Unknown action: ' . $action
        ]);
        exit(0);
    }

    // Unsupported method
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed: ' . $method
    ]);
    exit(0);

} catch (Throwable $e) {
    // Catch ALL errors and return JSON
    error_log('Auth.php fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit(0);
}

// Fallback - should never reach here
echo json_encode([
    'success' => false,
    'error' => 'Unexpected execution path'
]);
exit(0);
