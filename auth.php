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
        session_set_cookie_params([
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
        session_start();
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
            
            // Authenticate against database
            $authenticatedUser = null;

            if (function_exists('getDbConnection')) {
                try {
                    $pdo = getDbConnection();
                    $stmt = $pdo->prepare("SELECT id, name, email, role, password_hash FROM users WHERE email = ? OR name = ? LIMIT 1");
                    $stmt->execute([$username, $username]);
                    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($dbUser) {
                        // Check password against password_hash column
                        $passwordValid = false;
                        if (!empty($dbUser['password_hash'])) {
                            $passwordValid = password_verify($password, $dbUser['password_hash']);
                        }

                        if ($passwordValid) {
                            $authenticatedUser = [
                                'id'    => $dbUser['id'],
                                'name'  => $dbUser['name'],
                                'email' => $dbUser['email'],
                                'role'  => $dbUser['role']
                            ];
                        }
                    }
                } catch (Exception $e) {
                    error_log("Auth DB lookup failed: " . $e->getMessage());
                }
            }
            
            if ($authenticatedUser) {
                // Regenerate session ID on login to prevent session fixation
                session_regenerate_id(true);

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
            $_SESSION = [];
            session_destroy();
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
    // Log full details server-side only; never expose to client
    error_log('Auth.php fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal server error',
    ]);
    exit(0);
}

// Fallback - should never reach here
echo json_encode([
    'success' => false,
    'error' => 'Unexpected execution path'
]);
exit(0);
