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
    // Start session safely with consistent cookie settings
    if (session_status() === PHP_SESSION_NONE) {
        // Ensure session cookie is available across all paths
        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
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
            
            // Load database to look up real user
            $dbConfigPath = __DIR__ . '/config/database.php';
            if (file_exists($dbConfigPath)) {
                require_once $dbConfigPath;
            }
            
            // Authenticate against database first, fall back to hardcoded admin
            $authenticatedUser = null;
            
            if (function_exists('getDbConnection')) {
                try {
                    $pdo = getDbConnection();
                    $stmt = $pdo->prepare("SELECT id, name, email, role, password FROM users WHERE email = ? OR name = ? LIMIT 1");
                    $stmt->execute([$username, $username]);
                    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($dbUser) {
                        // Check password - support both hashed and legacy plain check
                        $passwordValid = false;
                        if (!empty($dbUser['password'])) {
                            $passwordValid = password_verify($password, $dbUser['password']);
                        }
                        // Hardcoded admin fallback check
                        if (!$passwordValid && $dbUser['role'] === 'admin' && $username === 'admin' && $password === 'admin123') {
                            $passwordValid = true;
                        }
                        
                        if ($passwordValid) {
                            $authenticatedUser = [
                                'id' => $dbUser['id'],
                                'name' => $dbUser['name'],
                                'email' => $dbUser['email'],
                                'role' => $dbUser['role']
                            ];
                        }
                    }
                } catch (Exception $e) {
                    error_log("Auth DB lookup failed: " . $e->getMessage());
                    // Fall through to hardcoded check
                }
            }
            
            // Fallback: hardcoded admin credentials (lookup real ID from DB)
            if (!$authenticatedUser && $username === 'admin' && $password === 'admin123') {
                $adminId = 'admin_' . substr(md5('admin@electree.cz'), 0, 13);
                
                // Try to find or create the admin user in DB
                if (function_exists('getDbConnection')) {
                    try {
                        $pdo = getDbConnection();
                        // Find existing admin user
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY created_at ASC LIMIT 1");
                        $stmt->execute();
                        $existingAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existingAdmin) {
                            $adminId = $existingAdmin['id'];
                        } else {
                            // Create admin user if not exists
                            $stmt = $pdo->prepare("INSERT IGNORE INTO users (id, name, email, role, is_active, created_at) VALUES (?, 'admin', 'admin@electree.cz', 'admin', 1, NOW())");
                            $stmt->execute([$adminId]);
                        }
                    } catch (Exception $e) {
                        error_log("Auth admin user lookup failed: " . $e->getMessage());
                    }
                }
                
                $authenticatedUser = [
                    'id' => $adminId,
                    'name' => 'Administrator',
                    'email' => 'admin@electree.cz',
                    'role' => 'admin'
                ];
            }
            
            if ($authenticatedUser) {
                $_SESSION['user_id'] = $authenticatedUser['id'];
                $_SESSION['username'] = $authenticatedUser['name'];
                $_SESSION['user_email'] = $authenticatedUser['email'];
                $_SESSION['user_role'] = $authenticatedUser['role'];
                $_SESSION['is_logged_in'] = true;
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => $authenticatedUser
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
