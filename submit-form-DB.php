<?php
// DATABASE AUTH API via submit-form.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Database connection - use centralized config
    require_once __DIR__ . '/config/database.php';
    $pdo = getDbConnection();
    
    // GET request for testing
    if ($method === 'GET') {
        // Test database connection
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $userCount = $stmt->fetch()['count'];
        
        echo json_encode([
            'success' => true,
            'message' => 'DATABASE AUTH API WORKING!',
            'timestamp' => date('Y-m-d H:i:s'),
            'server' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'method' => 'GET',
            'database' => 'connected',
            'users_count' => $userCount,
            'session_id' => session_id()
        ]);
        exit;
    }
    
    // POST request handling
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'login':
            $nickname = trim($data['nickname'] ?? '');
            $inputPassword = $data['password'] ?? '';
            
            if (empty($nickname)) {
                throw new Exception('Username is required');
            }
            
            // Find user in database by name or email
            $stmt = $pdo->prepare("
                SELECT id, name, email, password_hash, role, is_active, last_login 
                FROM users 
                WHERE (name = ? OR email = ?) AND is_active = 1
                LIMIT 1
            ");
            
            $stmt->execute([$nickname, $nickname]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('User not found or inactive');
            }
            
            // Verify password if hash exists
            if (!empty($user['password_hash'])) {
                if (!password_verify($inputPassword, $user['password_hash'])) {
                    throw new Exception('Invalid credentials');
                }
            } else {
                // Fallback: if no hash, check for admin with admin123
                if ($nickname !== 'admin' || $inputPassword !== 'admin123') {
                    throw new Exception('Invalid credentials - no password hash');
                }
                
                // Update user with hashed password
                $hashedPassword = password_hash($inputPassword, PASSWORD_BCRYPT);
                $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $user['id']]);
            }
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'last_login' => $user['last_login']
                ]
            ]);
            break;
            
        case 'logout':
            session_destroy();
            echo json_encode([
                'success' => true,
                'message' => 'Logout successful'
            ]);
            break;
            
        case 'check_session':
            if (isset($_SESSION['user_id'])) {
                // Verify user still exists and is active
                $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ? AND is_active = 1");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if ($user) {
                    echo json_encode([
                        'success' => true,
                        'user' => $user
                    ]);
                } else {
                    session_destroy();
                    echo json_encode(['success' => false, 'error' => 'Session invalid']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Not logged in']);
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;
?>