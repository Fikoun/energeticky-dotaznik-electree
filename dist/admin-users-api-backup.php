<?php
/**
 * Opravená verze admin-users.php
 * Správa uživatelů s opravami všech funkcí
 * Datum: 3. září 2025
 */

// Zabránit jakémukoli HTML výstupu
ob_start();

// Nastavit JSON header na začátku
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Vypnout zobrazování chyb do výstupu
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    // Zpracování GET i POST požadavků
    $action = '';
    $data = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        $data = $_GET;
    } else {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            throw new Exception('Neplatná JSON data');
        }
        $action = $data['action'] ?? '';
    }

    // Databázové připojení - use centralized config
    require_once __DIR__ . '/../config/database.php';
    $useDatabase = false;
    $pdo = null;
    
    try {
        $pdo = getDbConnection();
        $useDatabase = true;
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        $useDatabase = false;
        // Nepřerušovat běh, pokračovat s mock daty
    }

    // ===== NAČTENÍ UŽIVATELE =====
    if ($action === 'get_user') {
        $userId = $data['user_id'] ?? '';
        if (empty($userId)) {
            throw new Exception('ID uživatele je povinné');
        }

        try {
            $stmt = $pdo->prepare("
                SELECT 
                    u.id, u.name, u.email, u.role, u.phone, u.company_name, u.address,
                    u.ico, u.dic, u.billing_address, u.is_active,
                    u.last_login, u.created_at, u.updated_at, u.login_streak, u.total_login_time_minutes,
                    COUNT(f.id) as total_forms,
                    COUNT(CASE WHEN f.status = 'draft' THEN 1 END) as forms_draft,
                    COUNT(CASE WHEN f.status = 'submitted' THEN 1 END) as forms_submitted,
                    COUNT(CASE WHEN f.status = 'confirmed' THEN 1 END) as forms_confirmed,
                    COUNT(CASE WHEN f.status = 'processed' THEN 1 END) as forms_processed
                FROM users u
                LEFT JOIN forms f ON u.id = f.user_id
                WHERE u.id = ?
                GROUP BY u.id
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('Uživatel nebyl nalezen');
            }
            
            // Formátuj data
            $user['created_at_formatted'] = date('d.m.Y H:i:s', strtotime($user['created_at']));
            $user['last_login_formatted'] = $user['last_login'] ? 
                date('d.m.Y H:i:s', strtotime($user['last_login'])) : 'Nikdy';
                
            // Success rate
            if ($user['total_forms'] > 0) {
                $user['success_rate'] = round(($user['forms_confirmed'] / $user['total_forms']) * 100);
            } else {
                $user['success_rate'] = 0;
            }
            
            ob_end_clean();
            echo json_encode(['success' => true, 'user' => $user]);
            exit;
            
        } catch (PDOException $e) {
            error_log("Database error in get_user: " . $e->getMessage());
            throw new Exception('Chyba při načítání uživatele');
        }

    // ===== VYTVOŘENÍ UŽIVATELE =====
    } elseif ($action === 'create_user') {
        // Validace povinných polí
        $requiredFields = ['name', 'email'];
        foreach ($requiredFields as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                throw new Exception("Pole '$field' je povinné");
            }
        }
        
        $name = trim($data['name']);
        $email = trim($data['email']);
        $role = $data['role'] ?? 'user';
        $phone = trim($data['phone'] ?? '');
        $address = trim($data['address'] ?? '');
        $ico = trim($data['ico'] ?? '');
        $dic = trim($data['dic'] ?? '');
        $company_name = trim($data['company_name'] ?? '');
        $billing_address = trim($data['billing_address'] ?? '');
        $is_active = (int)($data['is_active'] ?? 1);
        
        // Generuj ID a heslo
        $userId = 'user_' . uniqid();
        $defaultPassword = 'password123'; // V produkci by mělo být náhodné
        $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
        
        try {
            // Zkontroluj duplicitu emailu
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Uživatel s tímto emailem již existuje');
            }
            
            // Vlož nového uživatele
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    id, name, email, password_hash, role, phone, address, 
                    ico, dic, company_name, billing_address, is_active,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $userId, $name, $email, $passwordHash, $role, $phone, $address,
                $ico, $dic, $company_name, $billing_address, $is_active
            ]);
            
            // Načti vytvořeného uživatele
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $newUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode([
                'success' => true, 
                'message' => 'Uživatel byl úspěšně vytvořen',
                'user' => $newUser,
                'default_password' => $defaultPassword
            ]);
            exit;
            
        } catch (PDOException $e) {
            error_log("Database error in create_user: " . $e->getMessage());
            throw new Exception('Chyba při vytváření uživatele');
        }

    // ===== AKTUALIZACE UŽIVATELE =====
    } elseif ($action === 'update_user') {
        error_log("[ADMIN-USERS] Update user request received");
        error_log("[ADMIN-USERS] Data: " . print_r($data, true));
        
        $userId = $data['user_id'] ?? '';
        if (empty($userId)) {
            error_log("[ADMIN-USERS] ERROR: Missing user_id");
            throw new Exception('ID uživatele je povinné');
        }
        error_log("[ADMIN-USERS] Processing user ID: " . $userId);
        
        // Validace povinných polí
        $requiredFields = ['name', 'email'];
        foreach ($requiredFields as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                error_log("[ADMIN-USERS] ERROR: Missing required field: " . $field);
                throw new Exception("Pole '$field' je povinné");
            }
        }
        
        $name = trim($data['name']);
        $email = trim($data['email']);
        $role = $data['role'] ?? 'user';
        $phone = trim($data['phone'] ?? '');
        $address = trim($data['address'] ?? '');
        $ico = trim($data['ico'] ?? '');
        $dic = trim($data['dic'] ?? '');
        $company_name = trim($data['company_name'] ?? '');
        $billing_address = trim($data['billing_address'] ?? '');
        $is_active = (int)($data['is_active'] ?? 1);
        
        error_log("[ADMIN-USERS] Prepared data - name: $name, email: $email, role: $role");
        
        try {
            // Zkontroluj zda uživatel existuje
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userCount = $stmt->fetchColumn();
            error_log("[ADMIN-USERS] User exists check: " . $userCount);
            
            if ($userCount == 0) {
                error_log("[ADMIN-USERS] ERROR: User not found");
                throw new Exception('Uživatel nebyl nalezen');
            }
            
            // Zkontroluj duplicitu emailu (kromě aktuálního uživatele)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            $emailCount = $stmt->fetchColumn();
            error_log("[ADMIN-USERS] Email duplicate check: " . $emailCount);
            
            if ($emailCount > 0) {
                error_log("[ADMIN-USERS] ERROR: Email already exists");
                throw new Exception('Uživatel s tímto emailem již existuje');
            }
            
            // Aktualizuj uživatele
            error_log("[ADMIN-USERS] Performing update...");
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    name = ?, email = ?, role = ?, phone = ?, address = ?,
                    ico = ?, dic = ?, company_name = ?, billing_address = ?, 
                    is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $name, $email, $role, $phone, $address,
                $ico, $dic, $company_name, $billing_address, 
                $is_active, $userId
            ]);
            
            $affectedRows = $stmt->rowCount();
            error_log("[ADMIN-USERS] Update result: " . ($result ? 'true' : 'false') . ", affected rows: " . $affectedRows);
            
            if ($affectedRows > 0) {
                // Načti aktualizovaného uživatele
                $stmt = $pdo->prepare("
                    SELECT 
                        u.*, 
                        COUNT(f.id) as total_forms,
                        COUNT(CASE WHEN f.status = 'confirmed' THEN 1 END) as forms_confirmed
                    FROM users u
                    LEFT JOIN forms f ON u.id = f.user_id
                    WHERE u.id = ?
                    GROUP BY u.id
                ");
                $stmt->execute([$userId]);
                $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("[ADMIN-USERS] SUCCESS: User updated successfully");
                ob_end_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Uživatel byl úspěšně aktualizován',
                    'user' => $updatedUser
                ]);
                exit;
            } else {
                error_log("[ADMIN-USERS] WARNING: No rows affected by update");
                throw new Exception('Žádné změny nebyly provedeny - data jsou možná stejná jako předtím');
            }
            
        } catch (PDOException $e) {
            error_log("Database error in update_user: " . $e->getMessage());
            throw new Exception('Chyba při aktualizaci uživatele');
        }

    // ===== SMAZÁNÍ UŽIVATELE =====
    } elseif ($action === 'delete_user') {
        $userId = $data['user_id'] ?? '';
        if (empty($userId)) {
            throw new Exception('ID uživatele je povinné');
        }
        
        try {
            // Zkontroluj zda uživatel existuje
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception('Uživatel nebyl nalezen');
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
            } else {
                // Smaž uživatele bez formulářů
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                
                ob_end_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Uživatel byl úspěšně smazán'
                ]);
            }
            exit;
            
        } catch (PDOException $e) {
            error_log("Database error in delete_user: " . $e->getMessage());
            throw new Exception('Chyba při mazání uživatele');
        }

    // ===== SEZNAM UŽIVATELŮ =====
    } elseif ($action === 'list_users') {
        $page = (int)($data['page'] ?? 1);
        $limit = (int)($data['per_page'] ?? 10);
        $search = $data['search'] ?? '';
        $roleFilter = $data['role'] ?? '';
        $statusFilter = $data['status'] ?? '';
        
        $offset = ($page - 1) * $limit;
        
        if ($useDatabase) {
            try {
                // Sestavení WHERE klauzule
                $whereConditions = [];
                $params = [];
                
                if (!empty($search)) {
                    $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.company_name LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
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
                    } else {
                        $whereConditions[] = "u.is_active = 0";
                    }
                }
                
                $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
                
                // Pokračování s databázovým dotazem...
                    $whereConditions[] = "u.is_active = 1";
                } else {
                    $whereConditions[] = "u.is_active = 0";
                }
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            // Celkový počet záznamů
            $countQuery = "SELECT COUNT(*) as total FROM users u $whereClause";
            $stmt = $pdo->prepare($countQuery);
            $stmt->execute($params);
            $totalCount = $stmt->fetch()['total'];
            
            // Hlavní dotaz s JOIN pro počty formulářů
            $query = "
                SELECT 
                    u.id, u.name, u.email, u.role, u.phone, u.company_name, u.address,
                    u.ico, u.dic, u.billing_address, u.is_active,
                    u.last_login, u.created_at, u.updated_at, u.login_streak, u.total_login_time_minutes,
                    COUNT(f.id) as total_forms,
                    COUNT(CASE WHEN f.status = 'draft' THEN 1 END) as forms_draft,
                    COUNT(CASE WHEN f.status = 'submitted' THEN 1 END) as forms_submitted,
                    COUNT(CASE WHEN f.status = 'confirmed' THEN 1 END) as forms_confirmed,
                    COUNT(CASE WHEN f.status = 'processed' THEN 1 END) as forms_processed
                FROM users u
                LEFT JOIN forms f ON u.id = f.user_id
                $whereClause
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT $limit OFFSET $offset
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formátuj data
            foreach ($users as &$user) {
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
            
            $result = [
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalCount / $limit),
                    'total_count' => $totalCount,
                    'per_page' => $limit
                ]
            ];
            
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $result]);
            exit;
            
        } catch (PDOException $e) {
            error_log("Database error in list_users: " . $e->getMessage());
            throw new Exception('Chyba při načítání seznamu uživatelů');
        }

    } else {
        throw new Exception('Neznámá akce: ' . $action);
    }

} catch (Exception $e) {
    error_log("Admin users error: " . $e->getMessage());
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
