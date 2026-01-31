<?php
// Authentication API - handles login and session management
// Enable error logging but suppress display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Start output buffering to prevent any stray output
    if (ob_get_level() === 0) {
        ob_start();
    }
    
    // Set headers
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Handle CORS preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        ob_end_clean();
        http_response_code(200);
        exit(0);
    }

    // Start session safely
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // GET request - check session status
    if ($method === 'GET') {
        ob_end_clean();
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
        ], JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    // POST request - handle login/logout
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON input: ' . json_last_error_msg()
            ], JSON_UNESCAPED_UNICODE);
            exit(0);
        }
        
        $action = $data['action'] ?? 'login';
        
        if ($action === 'login') {
            $username = $data['username'] ?? $data['nickname'] ?? '';
            $password = $data['password'] ?? '';
            
            if (empty($username)) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Username is required'
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
            
            // Simple hardcoded authentication (replace with database in production)
            if ($username === 'admin' && $password === 'admin123') {
                $_SESSION['user_id'] = 1;
                $_SESSION['username'] = 'admin';
                $_SESSION['user_email'] = 'admin@electree.cz';
                $_SESSION['user_role'] = 'admin';
                $_SESSION['is_logged_in'] = true;
                
                ob_end_clean();
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
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            } else {
                ob_end_clean();
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Nesprávné přihlašovací údaje'
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
        }
        
        if ($action === 'logout') {
            @session_destroy();
            ob_end_clean();
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Logged out successfully'
            ], JSON_UNESCAPED_UNICODE);
            exit(0);
        }
        
        ob_end_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ], JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    // Default response for unsupported methods
    ob_end_clean();
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit(0);

} catch (Throwable $e) {
    // Catch any errors and return JSON
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    error_log('Auth.php error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
}
