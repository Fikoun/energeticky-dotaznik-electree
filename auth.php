<?php
// Authentication API - handles login and session management
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET request - check session status
if ($method === 'GET') {
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
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action'
    ]);
    exit;
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => 'Method not allowed'
]);
exit;

// Set headers early
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

echo "AUTH_DEBUG_AFTER_HEADERS\n";

// Handle CORS preflight
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['success' => true, 'message' => 'CORS preflight OK']);
    exit(0);
}

echo "AUTH_DEBUG_AFTER_CORS\n";

try {
    // Start session safely
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check request method - povolit GET pro debug
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // GET request debug mode
    if ($requestMethod === 'GET' && isset($_GET['debug'])) {
        echo json_encode([
            'success' => true,
            'message' => 'GET Debug mode',
            'debug_info' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'server' => $_SERVER['HTTP_HOST'] ?? 'unknown',
                'php_version' => PHP_VERSION,
                'method' => 'GET',
                'auth_php_accessible' => true,
                'session_id' => session_id(),
                'get_params' => $_GET
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($requestMethod !== 'POST') {
        // Pokud je GET bez debug parametru, ukáži help
        if ($requestMethod === 'GET') {
            echo json_encode([
                'success' => false,
                'error' => 'GET metoda vyžaduje ?debug=1 parametr',
                'help' => 'Použijte POST request s JSON daty nebo GET s ?debug=1',
                'accessible' => true
            ]);
        } else {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metoda není povolena']);
        }
        exit;
    }

    // Get and parse input
    $input = file_get_contents('php://input');
    if ($input === false) {
        throw new Exception('Nelze načíst vstupní data');
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Neplatná JSON data: ' . json_last_error_msg());
    }
    
    if (!$data) {
        throw new Exception('Prázdná JSON data');
    }

    $action = $data['action'] ?? '';
    if (empty($action)) {
        throw new Exception('Chybí akce');
    }

    // DEBUG MODE - pokud je požadována akce 'debug'
    if ($action === 'debug') {
        $debugInfo = [];
        $debugInfo['timestamp'] = date('Y-m-d H:i:s');
        $debugInfo['server'] = $_SERVER['HTTP_HOST'] ?? 'unknown';
        $debugInfo['php_version'] = PHP_VERSION;
        
        // Test databáze
        try {
            $host = 's2.onhost.cz';
            $dbname = 'OH_13_edele';
            $username = 'OH_13_edele';
            $password = 'stjTmLjaYBBKa9u9_U';

            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ];
            
            $startTime = microtime(true);
            $pdo = new PDO($dsn, $username, $password, $options);
            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $debugInfo['database'] = [
                'connection' => 'OK',
                'connection_time_ms' => $connectionTime,
                'host' => $host,
                'database' => $dbname
            ];
            
            // Zkontrolovat tabulku users
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            $tableExists = $stmt->rowCount() > 0;
            $debugInfo['users_table_exists'] = $tableExists;
            
            if ($tableExists) {
                // Počet uživatelů
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
                $userCount = $stmt->fetch()['count'];
                $debugInfo['users_count'] = $userCount;
                
                // Struktura tabulky
                $stmt = $pdo->query("DESCRIBE users");
                $columns = $stmt->fetchAll();
                $debugInfo['table_structure'] = array_column($columns, 'Field');
                
                // Najít admin uživatele
                $stmt = $pdo->prepare("SELECT id, name, email, role, is_active FROM users WHERE name = ? OR email = ?");
                $stmt->execute(['admin', 'admin']);
                $adminUser = $stmt->fetch();
                
                if ($adminUser) {
                    $debugInfo['admin_user'] = [
                        'found' => true,
                        'id' => $adminUser['id'],
                        'name' => $adminUser['name'],
                        'email' => $adminUser['email'],
                        'role' => $adminUser['role'],
                        'is_active' => $adminUser['is_active']
                    ];
                } else {
                    $debugInfo['admin_user'] = ['found' => false];
                }
                
                // Zobrazit několik uživatelů
                $stmt = $pdo->query("SELECT id, name, email, role, is_active FROM users LIMIT 5");
                $sampleUsers = $stmt->fetchAll();
                $debugInfo['sample_users'] = $sampleUsers;
            }
            
        } catch (PDOException $e) {
            $debugInfo['database'] = [
                'connection' => 'ERROR',
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Debug informace',
            'debug' => $debugInfo
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // Database connection with timeout
    $host = 's2.onhost.cz';
    $dbname = 'OH_13_edele';
    $username = 'OH_13_edele';
    $password = 'stjTmLjaYBBKa9u9_U';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5, // 5 sekund timeout
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);

    switch ($action) {
        case 'login':
            // KOMPLETNÍ DEBUG LOGGING
            $debugLog = [];
            $debugLog[] = "=== LOGIN DEBUG START ===";
            $debugLog[] = "Timestamp: " . date('Y-m-d H:i:s');
            $debugLog[] = "Server: " . ($_SERVER['HTTP_HOST'] ?? 'unknown');
            $debugLog[] = "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
            $debugLog[] = "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            
            $nickname = trim($data['nickname'] ?? '');
            $inputPassword = $data['password'] ?? '';
            
            $debugLog[] = "Input nickname: '$nickname'";
            $debugLog[] = "Input password length: " . strlen($inputPassword);
            $debugLog[] = "Input password (first 3 chars): " . substr($inputPassword, 0, 3) . "***";
            
            if (empty($nickname)) {
                $debugLog[] = "ERROR: Empty nickname";
                throw new Exception('Nickname je povinný');
            }

            $debugLog[] = "Starting database query...";
            
            // Find user by nickname or email
            $stmt = $pdo->prepare("
                SELECT id, name, email, password_hash, role, is_active, last_login 
                FROM users 
                WHERE (name = ? OR email = ?) AND is_active = 1
                LIMIT 1
            ");
            
            $queryStart = microtime(true);
            $stmt->execute([$nickname, $nickname]);
            $queryTime = round((microtime(true) - $queryStart) * 1000, 2);
            $debugLog[] = "Database query executed in ${queryTime}ms";
            
            $user = $stmt->fetch();
            $debugLog[] = "User found in database: " . ($user ? 'YES' : 'NO');

            if (!$user) {
                $debugLog[] = "User not found - creating new user";
                
                // Create new user for backwards compatibility
                $userId = uniqid('user_');
                $hashedPassword = password_hash($inputPassword ?: 'default', PASSWORD_BCRYPT);
                
                $debugLog[] = "Generated user ID: $userId";
                $debugLog[] = "Password hashed successfully";
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (id, name, email, password_hash, role, is_active, created_at) 
                    VALUES (?, ?, ?, ?, 'user', 1, NOW())
                ");
                
                $insertStart = microtime(true);
                $result = $stmt->execute([
                    $userId,
                    $nickname,
                    $nickname . '@temp.local',
                    $hashedPassword
                ]);
                $insertTime = round((microtime(true) - $insertStart) * 1000, 2);
                
                $debugLog[] = "User insert query executed in ${insertTime}ms";
                $debugLog[] = "User insert result: " . ($result ? 'SUCCESS' : 'FAILED');
                
                if (!$result) {
                    $debugLog[] = "ERROR: Failed to create user";
                    throw new Exception('Nepodařilo se vytvořit uživatele');
                }
                
                $user = [
                    'id' => $userId,
                    'name' => $nickname,
                    'email' => $nickname . '@temp.local',
                    'role' => 'user',
                    'is_active' => 1
                ];
                
                $debugLog[] = "New user created successfully";
                error_log("Created new user: $nickname with ID: $userId");
                
            } else {
                $debugLog[] = "Existing user found:";
                $debugLog[] = "  - ID: " . $user['id'];
                $debugLog[] = "  - Name: " . $user['name'];
                $debugLog[] = "  - Email: " . $user['email'];
                $debugLog[] = "  - Role: " . $user['role'];
                $debugLog[] = "  - Has password hash: " . (!empty($user['password_hash']) ? 'YES' : 'NO');
                
                // Verify password if provided
                if (!empty($inputPassword) && !empty($user['password_hash'])) {
                    $debugLog[] = "Verifying password...";
                    
                    $verifyStart = microtime(true);
                    $passwordValid = password_verify($inputPassword, $user['password_hash']);
                    $verifyTime = round((microtime(true) - $verifyStart) * 1000, 2);
                    
                    $debugLog[] = "Password verification took ${verifyTime}ms";
                    $debugLog[] = "Password verification result: " . ($passwordValid ? 'VALID' : 'INVALID');
                    
                    if (!$passwordValid) {
                        $debugLog[] = "ERROR: Invalid password";
                        
                        // Přidat debug info k chybě
                        throw new Exception('Neplatné přihlašovací údaje - Debug: ' . implode(' | ', $debugLog));
                    }
                } else {
                    $debugLog[] = "Password verification skipped (empty password or hash)";
                }
                
                // Update last login
                $debugLog[] = "Updating last login...";
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                
                $updateStart = microtime(true);
                $stmt->execute([$user['id']]);
                $updateTime = round((microtime(true) - $updateStart) * 1000, 2);
                
                $debugLog[] = "Last login updated in ${updateTime}ms";
                error_log("User logged in: " . $user['name'] . " (ID: " . $user['id'] . ")");
            }

            // Set session
            $debugLog[] = "Setting session variables...";
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $debugLog[] = "Session variables set successfully";

            // Prepare response with debug info
            $response = [
                'success' => true,
                'message' => 'Přihlášení úspěšné',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ],
                'debug' => $debugLog,
                'debug_summary' => [
                    'total_steps' => count($debugLog),
                    'user_created' => !isset($user['password_hash']),
                    'password_verified' => isset($passwordValid) ? $passwordValid : null,
                    'session_id' => session_id()
                ]
            ];
            
            $debugLog[] = "Preparing JSON response...";
            $jsonStart = microtime(true);
            $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE);
            $jsonTime = round((microtime(true) - $jsonStart) * 1000, 2);
            
            if ($jsonOutput === false) {
                $debugLog[] = "ERROR: JSON encoding failed - " . json_last_error_msg();
                throw new Exception('Chyba při generování JSON odpovědi: ' . json_last_error_msg());
            }
            
            $debugLog[] = "JSON encoded successfully in ${jsonTime}ms";
            $debugLog[] = "JSON length: " . strlen($jsonOutput) . " bytes";
            $debugLog[] = "=== LOGIN DEBUG END ===";
            
            // Log úspěšného přihlášení
            error_log("LOGIN SUCCESS: " . $user['name'] . " - Debug steps: " . count($debugLog));
            
            echo $jsonOutput;
            exit;

        case 'register':
            $nickname = $data['nickname'] ?? '';
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            if (empty($nickname) || empty($email)) {
                throw new Exception('Nickname a email jsou povinné');
            }

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE name = ? OR email = ?");
            $stmt->execute([$nickname, $email]);
            if ($stmt->fetch()) {
                throw new Exception('Uživatel s tímto nickname nebo emailem již existuje');
            }

            $userId = uniqid('user_');
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (id, name, email, password_hash, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, 'user', 1, NOW())
            ");
            $stmt->execute([$userId, $nickname, $email, $hashedPassword]);

            echo json_encode([
                'success' => true,
                'message' => 'Registrace úspěšná',
                'user' => [
                    'id' => $userId,
                    'name' => $nickname,
                    'email' => $email,
                    'role' => 'user'
                ]
            ]);
            exit;

        case 'logout':
            session_destroy();
            echo json_encode([
                'success' => true,
                'message' => 'Odhlášení úspěšné'
            ]);
            exit;

        case 'check_session':
            if (isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    echo json_encode([
                        'success' => true,
                        'user' => $user
                    ]);
                } else {
                    session_destroy();
                    echo json_encode(['success' => false, 'error' => 'Neplatná session']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Nejste přihlášeni']);
            }
            exit;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Neplatná akce'
            ], JSON_UNESCAPED_UNICODE);
            exit;
    }

} catch (PDOException $e) {
    $errorDebug = isset($debugLog) ? $debugLog : ['No debug log available'];
    $errorDebug[] = "PDO ERROR: " . $e->getMessage();
    $errorDebug[] = "Error Code: " . $e->getCode();
    $errorDebug[] = "Error File: " . $e->getFile() . ":" . $e->getLine();
    
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Chyba databáze: ' . $e->getMessage(),
        'debug' => $errorDebug,
        'error_type' => 'PDOException'
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Exception $e) {
    $errorDebug = isset($debugLog) ? $debugLog : ['No debug log available'];
    $errorDebug[] = "EXCEPTION: " . $e->getMessage();
    $errorDebug[] = "Error File: " . $e->getFile() . ":" . $e->getLine();
    
    error_log("Auth error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $errorDebug,
        'error_type' => 'Exception'
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Throwable $e) {
    $errorDebug = isset($debugLog) ? $debugLog : ['No debug log available'];
    $errorDebug[] = "FATAL ERROR: " . $e->getMessage();
    $errorDebug[] = "Error File: " . $e->getFile() . ":" . $e->getLine();
    
    error_log("Fatal auth error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Vnitřní chyba serveru',
        'debug' => $errorDebug,
        'error_type' => 'Throwable',
        'error_details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
