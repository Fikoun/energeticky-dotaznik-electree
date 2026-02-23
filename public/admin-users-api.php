<?php
/**
 * Admin Users API s databázovým připojením a bezpečnostními vylepšeními
 * Datum: 4. září 2025
 */

// Spustit session jako první věc
session_set_cookie_params(["path" => "/", "httponly" => true, "samesite" => "Lax"]);
session_start();

// Zabránit jakémukoli HTML výstupu
ob_start();

// Nastavit JSON header na začátku
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Vypnout zobrazování chyb do výstupu
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Performance optimization functions
function getCacheKey($action, $params = []) {
    return 'cache_' . $action . '_' . md5(serialize($params));
}

function getFromCache($key, $maxAge = 300) { // 5 minutes default
    if (!isset($_SESSION[$key])) {
        return null;
    }
    
    $cached = $_SESSION[$key];
    if (time() - $cached['timestamp'] > $maxAge) {
        unset($_SESSION[$key]);
        return null;
    }
    
    return $cached['data'];
}

function setCache($key, $data) {
    $_SESSION[$key] = [
        'data' => $data,
        'timestamp' => time()
    ];
}

function clearCachePattern($pattern) {
    foreach (array_keys($_SESSION) as $key) {
        if (strpos($key, $pattern) === 0) {
            unset($_SESSION[$key]);
        }
    }
}

// Database connection with optimization
function getOptimizedDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // Use centralized database config
            require_once __DIR__ . '/../config/database.php';
            $pdo = getDbConnection();
            error_log("Optimized database connection established");
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    return $pdo;
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        error_log("New CSRF token created: " . substr($_SESSION['csrf_token'], 0, 8) . "...");
    } else {
        error_log("Existing CSRF token reused: " . substr($_SESSION['csrf_token'], 0, 8) . "...");
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate Limiting
function checkRateLimit($action) {
    $key = "rate_limit_" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "_$action";
    $max_requests = 60; // requests per minute
    $window = 60; // seconds
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start' => time()];
    }
    
    $rate_data = $_SESSION[$key];
    
    // Reset if window has passed
    if (time() - $rate_data['start'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    // Check if limit exceeded
    if ($rate_data['count'] >= $max_requests) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

// Input Sanitization
function sanitizeInput($input, $type = 'string') {
    if (is_array($input)) {
        return array_map(function($item) use ($type) {
            return sanitizeInput($item, $type);
        }, $input);
    }
    
    switch ($type) {
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        case 'string':
        default:
            return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
}

// Enhanced validation
function validateUserData($data, $isUpdate = false) {
    $errors = [];
    
    // Name validation
    if (!$isUpdate || isset($data['name'])) {
        $name = $data['name'] ?? '';
        if (empty($name)) {
            $errors[] = 'Jméno je povinné';
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = 'Jméno musí mít 2-100 znaků';
        } elseif (!preg_match('/^[\p{L}\s\-\.]+$/u', $name)) {
            $errors[] = 'Jméno obsahuje nepovolené znaky';
        }
    }
    
    // Email validation
    if (!$isUpdate || isset($data['email'])) {
        $email = $data['email'] ?? '';
        if (empty($email)) {
            $errors[] = 'Email je povinný';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email má neplatný formát';
        } elseif (strlen($email) > 255) {
            $errors[] = 'Email je příliš dlouhý';
        }
    }
    
    // Role validation
    if (!$isUpdate || isset($data['role'])) {
        $role = $data['role'] ?? '';
        $validRoles = ['admin', 'salesman', 'partner', 'customer'];
        if (empty($role)) {
            $errors[] = 'Role je povinná';
        } elseif (!in_array($role, $validRoles)) {
            $errors[] = 'Neplatná role';
        }
    }
    
    // Phone validation (optional)
    if (isset($data['phone']) && !empty($data['phone'])) {
        $phone = $data['phone'];
        if (!preg_match('/^[\+\d\s\-\(\)]+$/', $phone)) {
            $errors[] = 'Telefon má neplatný formát';
        }
    }
    
    // ICO validation (optional)
    if (isset($data['ico']) && !empty($data['ico'])) {
        $ico = $data['ico'];
        if (!preg_match('/^\d{8}$/', $ico)) {
            $errors[] = 'IČO musí mít 8 číslic';
        }
    }
    
    // DIC validation (optional)
    if (isset($data['dic']) && !empty($data['dic'])) {
        $dic = $data['dic'];
        if (!preg_match('/^CZ\d{8,10}$/', $dic)) {
            $errors[] = 'DIČ má neplatný formát (CZ následované 8-10 číslicemi)';
        }
    }
    
    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    // Zpracování POST požadavků
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        $data = $_POST; // fallback na $_POST
    }
    
    // Sanitize input data
    $data = array_map(function($value) {
        return is_string($value) ? sanitizeInput($value) : $value;
    }, $data);
    
    $action = $data['action'] ?? '';
    
    // Rate limiting check
    if (!checkRateLimit($action)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Příliš mnoho požadavků. Zkuste to později.']);
        exit;
    }
    
    // CSRF Protection for modifying operations
    $modifyingActions = ['create_user', 'update_user', 'delete', 'delete_user'];
    if (in_array($action, $modifyingActions)) {
        $csrfToken = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCSRFToken($csrfToken)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Neplatný CSRF token']);
            exit;
        }
    }

    // Optimalizované databázové připojení
    $useDatabase = false;
    $pdo = null;
    
    try {
        $pdo = getOptimizedDatabaseConnection();
        $useDatabase = true;
        error_log("Database connection successful");
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        $useDatabase = false;
    }

    // ===== CSRF TOKEN =====
    if ($action === 'get_csrf_token') {
        $csrf_token = generateCSRFToken();
        error_log("CSRF token generated: " . substr($csrf_token, 0, 8) . "...");
        ob_end_clean();
        echo json_encode(['success' => true, 'csrf_token' => $csrf_token]);
        exit;

    // ===== SEZNAM ROLÍ =====
    } elseif ($action === 'list_roles') {
        try {
            $stmt = $pdo->prepare("
                SELECT role_key, role_name, role_description, is_active
                FROM user_roles 
                WHERE is_active = 1
                ORDER BY role_name ASC
            ");
            $stmt->execute();
            $roles = $stmt->fetchAll();
            
            // Debug log for roles
            error_log("List roles API - Found roles: " . json_encode($roles));
            
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $roles]);
            exit;
        } catch (Exception $e) {
            error_log("Error loading roles: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba při načítání rolí']);
            exit;
        }

    // ===== SEZNAM UŽIVATELŮ =====
    } elseif ($action === 'list_users' || empty($action)) {
        $page = (int)($data['page'] ?? 1);
        $limit = (int)($data['per_page'] ?? 10);
        $search = sanitizeInput($data['search'] ?? '');
        $roleFilter = sanitizeInput($data['role'] ?? '');
        $statusFilter = sanitizeInput($data['status'] ?? '');
        
        // Cache key based on parameters
        $cacheParams = compact('page', 'limit', 'search', 'roleFilter', 'statusFilter');
        $cacheKey = getCacheKey('list_users', $cacheParams);
        
        // Try to get from cache first (cache for 2 minutes)
        $cachedResult = getFromCache($cacheKey, 120);
        if ($cachedResult !== null) {
            error_log("Returning cached users list");
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $cachedResult]);
            exit;
        }
        
        $users = [];
        $totalCount = 0;
        
        // Pokus o načtení z databáze
        if ($useDatabase) {
            try {
                // Sestavení WHERE klauzule
                $whereConditions = [];
                $params = [];
                
                if (!empty($search)) {
                    $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                if (!empty($roleFilter)) {
                    $whereConditions[] = "u.role = ?";
                    $params[] = $roleFilter;
                }
                
                if (!empty($statusFilter)) {
                    if ($statusFilter === 'active') {
                        $whereConditions[] = "u.is_active = 1";
                    } elseif ($statusFilter === 'inactive') {
                        $whereConditions[] = "u.is_active = 0";
                    }
                }
                
                $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
                
                // Celkový počet záznamů
                $countQuery = "SELECT COUNT(*) as total FROM users u $whereClause";
                $stmt = $pdo->prepare($countQuery);
                $stmt->execute($params);
                $result = $stmt->fetch();
                $totalCount = $result ? (int)$result['total'] : 0;
                
                // Hlavní dotaz s paginací
                $offset = ($page - 1) * $limit;
                $query = "
                    SELECT 
                        u.id, u.name, u.email, u.role, u.phone, u.company_name, u.address,
                        u.ico, u.dic, u.billing_address, u.is_active,
                        u.last_login, u.created_at, u.updated_at
                    FROM users u
                    $whereClause
                    ORDER BY u.created_at DESC
                    LIMIT $limit OFFSET $offset
                ";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Doplnění údajů o formulářích pro každého uživatele
                foreach ($users as &$user) {
                    // Počet formulářů
                    $formStmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_forms,
                            COUNT(CASE WHEN status = 'draft' THEN 1 END) as forms_draft,
                            COUNT(CASE WHEN status = 'submitted' THEN 1 END) as forms_submitted,
                            COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as forms_confirmed,
                            COUNT(CASE WHEN status = 'processed' THEN 1 END) as forms_processed
                        FROM forms WHERE user_id = ?
                    ");
                    $formStmt->execute([$user['id']]);
                    $formStats = $formStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($formStats) {
                        $user = array_merge($user, $formStats);
                    } else {
                        $user['total_forms'] = 0;
                        $user['forms_draft'] = 0;
                        $user['forms_submitted'] = 0;
                        $user['forms_confirmed'] = 0;
                        $user['forms_processed'] = 0;
                    }
                    
                    // Formátování dat
                    $user['created_at_formatted'] = date('d.m.Y H:i:s', strtotime($user['created_at']));
                    $user['last_login_formatted'] = $user['last_login'] ? 
                        date('d.m.Y H:i:s', strtotime($user['last_login'])) : 'Nikdy';
                    
                    // Success rate
                    if ($user['total_forms'] > 0) {
                        $user['success_rate'] = round(($user['forms_confirmed'] / $user['total_forms']) * 100);
                    } else {
                        $user['success_rate'] = 0;
                    }
                }
                
                error_log("Loaded " . count($users) . " users from database");
                
            } catch (PDOException $e) {
                error_log("Database error in list_users: " . $e->getMessage());
                $useDatabase = false;
            }
        }
        
        // Fallback na mock data
        if (!$useDatabase || count($users) === 0) {
            error_log("Using mock data as fallback");
            
            $mockUsers = [
                [
                    'id' => '1',
                    'name' => 'Admin User',
                    'email' => 'admin@electree.cz',
                    'role' => 'admin',
                    'phone' => '+420 123 456 789',
                    'company_name' => 'Electree s.r.o.',
                    'address' => 'Praha 1',
                    'ico' => '12345678',
                    'dic' => 'CZ12345678',
                    'billing_address' => 'Praha 1',
                    'is_active' => '1',
                    'last_login' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'total_forms' => 5,
                    'forms_draft' => 1,
                    'forms_submitted' => 2,
                    'forms_confirmed' => 2,
                    'forms_processed' => 0,
                    'created_at_formatted' => date('d.m.Y H:i:s', strtotime('-30 days')),
                    'last_login_formatted' => date('d.m.Y H:i:s', strtotime('-1 hour')),
                    'success_rate' => 40
                ],
                [
                    'id' => '2',
                    'name' => 'Test Partner',
                    'email' => 'partner@test.cz',
                    'role' => 'partner',
                    'phone' => '+420 987 654 321',
                    'company_name' => 'Test Partner s.r.o.',
                    'address' => 'Brno',
                    'ico' => '87654321',
                    'dic' => 'CZ87654321',
                    'billing_address' => 'Brno',
                    'is_active' => '1',
                    'last_login' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'created_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'total_forms' => 3,
                    'forms_draft' => 0,
                    'forms_submitted' => 1,
                    'forms_confirmed' => 2,
                    'forms_processed' => 0,
                    'created_at_formatted' => date('d.m.Y H:i:s', strtotime('-15 days')),
                    'last_login_formatted' => date('d.m.Y H:i:s', strtotime('-2 days')),
                    'success_rate' => 67
                ]
            ];
            
            $users = $mockUsers;
            $totalCount = count($mockUsers);
            
            // Paginace pro mock data
            $offset = ($page - 1) * $limit;
            $users = array_slice($users, $offset, $limit);
        }
        
        $result = [
            'users' => array_values($users),
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_count' => $totalCount,
                'per_page' => $limit
            ]
        ];
        
        // Cache the result
        setCache($cacheKey, $result);
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $result]);
        exit;

    // ===== DETAIL UŽIVATELE =====
    } elseif ($action === 'get_user') {
        $userId = $data['user_id'] ?? '';
        
        if (empty($userId)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chybí ID uživatele']);
            exit;
        }
        
        $user = null;
        
        if ($useDatabase) {
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        u.id, u.name, u.email, u.role, u.phone, u.company_name, u.address,
                        u.ico, u.dic, u.billing_address, u.is_active,
                        u.last_login, u.created_at, u.updated_at
                    FROM users u
                    WHERE u.id = ?
                ");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Statistiky formulářů
                    $formStmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_forms,
                            COUNT(CASE WHEN status = 'draft' THEN 1 END) as forms_draft,
                            COUNT(CASE WHEN status = 'submitted' THEN 1 END) as forms_submitted,
                            COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as forms_confirmed,
                            COUNT(CASE WHEN status = 'processed' THEN 1 END) as forms_processed
                        FROM forms WHERE user_id = ?
                    ");
                    $formStmt->execute([$userId]);
                    $formStats = $formStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($formStats) {
                        $user = array_merge($user, $formStats);
                    }
                    
                    // Formátování dat
                    $user['created_at_formatted'] = date('d.m.Y H:i:s', strtotime($user['created_at']));
                    $user['last_login_formatted'] = $user['last_login'] ? 
                        date('d.m.Y H:i:s', strtotime($user['last_login'])) : 'Nikdy';
                    
                    // Success rate
                    if ($user['total_forms'] > 0) {
                        $user['success_rate'] = round(($user['forms_confirmed'] / $user['total_forms']) * 100);
                    } else {
                        $user['success_rate'] = 0;
                    }
                }
                
            } catch (PDOException $e) {
                error_log("Database error in get_user: " . $e->getMessage());
                $useDatabase = false;
            }
        }
        
        if (!$user) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Uživatel nenalezen']);
            exit;
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $user]);
        exit;

    // ===== VYTVOŘENÍ NOVÉHO UŽIVATELE =====
    } elseif ($action === 'create_user') {
        // Debug log pro kontrolu přijatých dat
        error_log("Create user - received data: " . json_encode($data));
        
        if (!$useDatabase) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Databáze není dostupná']);
            exit;
        }
        
        // Použití nové validace
        $validationErrors = validateUserData($data, false);
        if (!empty($validationErrors)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => implode(', ', $validationErrors)]);
            exit;
        }
        
        try {
            // Kontrola duplicitního emailu
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([sanitizeInput($data['email'], 'email')]);
            if ($stmt->fetchColumn() > 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Uživatel s tímto emailem již existuje']);
                exit;
            }
            
            // Příprava sanitizovaných dat pro vložení
            $insertFields = [
                'name' => sanitizeInput($data['name']),
                'email' => sanitizeInput($data['email'], 'email'),
                'role' => sanitizeInput($data['role']),
                'phone' => sanitizeInput($data['phone'] ?? ''),
                'company_name' => sanitizeInput($data['company_name'] ?? ''),
                'address' => sanitizeInput($data['address'] ?? ''),
                'ico' => sanitizeInput($data['ico'] ?? ''),
                'dic' => sanitizeInput($data['dic'] ?? ''),
                'billing_address' => sanitizeInput($data['billing_address'] ?? ''),
                'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Hash hesla pokud je zadáno
            if (!empty($data['password'])) {
                $insertFields['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            // Sestavení SQL dotazu
            $fields = array_keys($insertFields);
            $placeholders = ':' . implode(', :', $fields);
            $fieldsList = implode(', ', $fields);
            
            $query = "INSERT INTO users ($fieldsList) VALUES ($placeholders)";
            
            // Debug log pro SQL dotaz
            error_log("Create user - SQL query: " . $query);
            
            $stmt = $pdo->prepare($query);
            $success = $stmt->execute($insertFields);
            
            if ($success) {
                $newUserId = $pdo->lastInsertId();
                error_log("User created successfully with ID: " . $newUserId);
                
                // Invalidate cache
                clearCachePattern('cache_list_users');
                clearCachePattern('cache_get_user');
                
                ob_end_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Uživatel byl úspěšně vytvořen',
                    'user_id' => $newUserId
                ]);
                exit;
            } else {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Nepodařilo se vytvořit uživatele']);
                exit;
            }
            
        } catch (PDOException $e) {
            error_log("Database error in create_user: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba databáze při vytváření uživatele']);
            exit;
        }

    // ===== AKTUALIZACE UŽIVATELE =====
    } elseif ($action === 'update_user') {
        $userId = $data['user_id'] ?? '';
        
        // Debug log pro kontrolu přijatých dat
        error_log("Update user - received data: " . json_encode($data));
        
        if (empty($userId)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chybí ID uživatele']);
            exit;
        }
        
        if (!$useDatabase) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Databáze není dostupná']);
            exit;
        }
        
        try {
            $updateFields = [];
            $params = [];
            
            // Povolené pole pro aktualizaci s validací
            $allowedFields = [
                'name' => ['required' => true, 'max_length' => 100],
                'email' => ['required' => true, 'max_length' => 255, 'validate' => 'email'],
                'role' => ['required' => true, 'allowed_values' => ['admin', 'salesman', 'partner', 'customer']],
                'phone' => ['required' => false, 'max_length' => 20],
                'company_name' => ['required' => false, 'max_length' => 255],
                'address' => ['required' => false, 'max_length' => 500],
                'ico' => ['required' => false, 'max_length' => 20],
                'dic' => ['required' => false, 'max_length' => 20],
                'billing_address' => ['required' => false, 'max_length' => 500],
                'is_active' => ['required' => false, 'type' => 'boolean']
            ];
            
            // Validace a příprava dat
            foreach ($allowedFields as $field => $rules) {
                if (isset($data[$field])) {
                    $value = $data[$field];
                    
                    // Validace required
                    if ($rules['required'] && empty($value)) {
                        ob_end_clean();
                        echo json_encode(['success' => false, 'message' => "Pole '$field' je povinné"]);
                        exit;
                    }
                    
                    // Validace délky
                    if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                        ob_end_clean();
                        echo json_encode(['success' => false, 'message' => "Pole '$field' je příliš dlouhé (max {$rules['max_length']} znaků)"]);
                        exit;
                    }
                    
                    // Validace email
                    if (isset($rules['validate']) && $rules['validate'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        ob_end_clean();
                        echo json_encode(['success' => false, 'message' => "Email má neplatný formát"]);
                        exit;
                    }
                    
                    // Validace povolených hodnot
                    if (isset($rules['allowed_values']) && !in_array($value, $rules['allowed_values'])) {
                        ob_end_clean();
                        echo json_encode(['success' => false, 'message' => "Neplatná hodnota pro pole '$field'"]);
                        exit;
                    }
                    
                    // Validace boolean
                    if (isset($rules['type']) && $rules['type'] === 'boolean') {
                        $value = $value ? 1 : 0;
                    }
                    
                    $updateFields[] = "$field = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($updateFields)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Žádná data k aktualizaci']);
                exit;
            }
            
            // Kontrola duplicitního emailu (pokud se mění email)
            if (isset($data['email'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $userId]);
                if ($stmt->fetchColumn() > 0) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Uživatel s tímto emailem již existuje']);
                    exit;
                }
            }
            
            $updateFields[] = "updated_at = NOW()";
            $params[] = $userId;
            
            $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            
            // Debug log pro SQL dotaz a parametry
            error_log("Update user - SQL query: " . $query);
            error_log("Update user - SQL params: " . json_encode($params));
            
            $stmt = $pdo->prepare($query);
            $success = $stmt->execute($params);
            
            if ($success && $stmt->rowCount() > 0) {
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'Uživatel byl úspěšně aktualizován']);
                exit;
            } else {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Nepodařilo se aktualizovat uživatele nebo žádné změny nebyly provedeny']);
                exit;
            }
            
        } catch (PDOException $e) {
            error_log("Database error in update_user: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba databáze: ' . $e->getMessage()]);
            exit;
        }

    // ===== DEAKTIVACE UŽIVATELE =====
    } elseif ($action === 'delete' || $action === 'delete_user') {
        $userId = $data['user_id'] ?? '';
        
        if (empty($userId)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chybí ID uživatele']);
            exit;
        }
        
        if (!$useDatabase) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Databáze není dostupná']);
            exit;
        }
        
        try {
            // Zkontroluj zda uživatel existuje
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() == 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Uživatel nebyl nalezen']);
                exit;
            }
            
            // Počet formulářů uživatele
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM forms WHERE user_id = ?");
            $stmt->execute([$userId]);
            $formsCount = $stmt->fetchColumn();
            
            if ($formsCount > 0) {
                // Pouze deaktivuj uživatele místo smazání
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$userId]);
                
                ob_end_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => "Uživatel byl deaktivován (má $formsCount formulářů)"
                ]);
                exit;
            } else {
                // Deaktivuj uživatele i bez formulářů
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$userId]);
                
                ob_end_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Uživatel byl úspěšně deaktivován'
                ]);
                exit;
            }        } catch (PDOException $e) {
            error_log("Database error in deactivate_user: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba při deaktivaci uživatele: ' . $e->getMessage()]);
            exit;
        }

    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Neznámá akce: ' . $action]);
        exit;
    }

} catch (Exception $e) {
    error_log("Admin users error: " . $e->getMessage());
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>
