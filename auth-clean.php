<?php
// Authentication API - handles login and session management
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET request - check session status
if ($method === 'GET') {
    ob_end_clean();
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
    exit;
}

// POST request - handle login/logout
if ($method === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON input'
        ]);
        exit;
    }
    
    $action = $data['action'] ?? 'login';
    
    if ($action === 'login') {
        $username = $data['username'] ?? $data['nickname'] ?? '';
        $password = $data['password'] ?? '';
        
        // Simple hardcoded authentication (replace with database in production)
        if ($username === 'admin' && $password === 'admin123') {
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = 'admin';
            $_SESSION['user_email'] = 'admin@electree.cz';
            $_SESSION['user_role'] = 'admin';
            $_SESSION['is_logged_in'] = true;
            
            ob_end_clean();
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
        } else {
            ob_end_clean();
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Nesprávné přihlašovací údaje'
            ]);
        }
        exit;
    }
    
    if ($action === 'logout') {
        session_destroy();
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
        exit;
    }
    
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action'
    ]);
    exit;
}

ob_end_clean();
http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => 'Method not allowed'
]);
exit;
