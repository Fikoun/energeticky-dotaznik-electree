<?php
/**
 * Admin Settings API pro správu rolí a systémových nastavení
 * Datum: 5. září 2025
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

// Kontrola oprávnění
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění']);
    exit;
}

// Databázové připojení - use centralized config
require_once __DIR__ . '/../config/database.php';

function getDatabaseConnection() {
    try {
        return getDbConnection();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Chyba databáze: ' . $e->getMessage()]);
        exit;
    }
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

// Input Sanitization
function sanitizeInput($input, $type = 'string') {
    if (is_array($input)) {
        return array_map(function($item) use ($type) {
            return sanitizeInput($item, $type);
        }, $input);
    }
    
    $input = trim($input);
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

// Zpracování požadavku
try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Neplatný JSON formát');
    }
    
    $action = sanitizeInput($data['action'] ?? '');
    
    // Rate limiting - základní implementace
    $rateKey = "settings_rate_" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!isset($_SESSION[$rateKey])) {
        $_SESSION[$rateKey] = ['count' => 0, 'start' => time()];
    }
    
    $rateData = $_SESSION[$rateKey];
    if (time() - $rateData['start'] > 60) {
        $_SESSION[$rateKey] = ['count' => 1, 'start' => time()];
    } else {
        $_SESSION[$rateKey]['count']++;
        if ($rateData['count'] > 100) { // 100 requests per minute
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Příliš mnoho požadavků']);
            exit;
        }
    }
    
    // Získání databázového připojení
    $pdo = getDatabaseConnection();
    
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
                SELECT id, role_key, role_name, role_description, permissions, is_active, 
                       created_at, updated_at
                FROM user_roles 
                ORDER BY role_name ASC
            ");
            $stmt->execute();
            $roles = $stmt->fetchAll();
            
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $roles]);
            exit;
        } catch (Exception $e) {
            error_log("Error loading roles: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba při načítání rolí']);
            exit;
        }

    // ===== VYTVOŘENÍ ROLE =====
    } elseif ($action === 'create_role') {
        // CSRF Protection
        if (!isset($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Neplatný CSRF token']);
            exit;
        }
        
        $roleKey = sanitizeInput($data['role_key'] ?? '');
        $roleName = sanitizeInput($data['role_name'] ?? '');
        $roleDescription = sanitizeInput($data['role_description'] ?? '');
        $permissions = $data['permissions'] ?? [];
        $isActive = isset($data['is_active']) ? 1 : 0;
        
        // Validace
        if (empty($roleKey) || empty($roleName)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Klíč role a název jsou povinné']);
            exit;
        }
        
        if (!preg_match('/^[a-z_]+$/', $roleKey)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Klíč role může obsahovat pouze malá písmena a podtržítka']);
            exit;
        }
        
        try {
            // Kontrola duplikátů
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_key = ?");
            $stmt->execute([$roleKey]);
            if ($stmt->fetchColumn() > 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Role s tímto klíčem již existuje']);
                exit;
            }
            
            // Vytvoření role
            $stmt = $pdo->prepare("
                INSERT INTO user_roles (role_key, role_name, role_description, permissions, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $roleKey,
                $roleName,
                $roleDescription,
                implode(',', $permissions),
                $isActive
            ]);
            
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Role byla úspěšně vytvořena']);
            exit;
        } catch (Exception $e) {
            error_log("Error creating role: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba při vytváření role']);
            exit;
        }

    // ===== DETAIL ROLE =====
    } elseif ($action === 'get_role') {
        $roleId = sanitizeInput($data['role_id'] ?? '', 'int');
        
        if (empty($roleId)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'ID role je povinné']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("
                SELECT id, role_key, role_name, role_description, permissions, is_active, 
                       created_at, updated_at
                FROM user_roles 
                WHERE id = ?
            ");
            $stmt->execute([$roleId]);
            $role = $stmt->fetch();
            
            if (!$role) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Role nebyla nalezena']);
                exit;
            }
            
            // Převést permissions na array
            $role['permissions'] = $role['permissions'] ? explode(',', $role['permissions']) : [];
            
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $role]);
            exit;
        } catch (Exception $e) {
            error_log("Error loading role: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba při načítání role']);
            exit;
        }

    // ===== AKTUALIZACE ROLE =====
    } elseif ($action === 'update_role') {
        // CSRF Protection
        if (!isset($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Neplatný CSRF token']);
            exit;
        }
        
        $roleId = sanitizeInput($data['role_id'] ?? '', 'int');
        $roleKey = sanitizeInput($data['role_key'] ?? '');
        $roleName = sanitizeInput($data['role_name'] ?? '');
        $roleDescription = sanitizeInput($data['role_description'] ?? '');
        $permissions = $data['permissions'] ?? [];
        $isActive = isset($data['is_active']) ? 1 : 0;
        
        // Validace
        if (empty($roleId) || empty($roleKey) || empty($roleName)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'ID, klíč role a název jsou povinné']);
            exit;
        }
        
        try {
            // Kontrola existence a duplikátů
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_key = ? AND id != ?");
            $stmt->execute([$roleKey, $roleId]);
            if ($stmt->fetchColumn() > 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Role s tímto klíčem již existuje']);
                exit;
            }
            
            // Aktualizace role
            $stmt = $pdo->prepare("
                UPDATE user_roles 
                SET role_key = ?, role_name = ?, role_description = ?, permissions = ?, 
                    is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $roleKey,
                $roleName,
                $roleDescription,
                implode(',', $permissions),
                $isActive,
                $roleId
            ]);
            
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Role byla úspěšně aktualizována']);
            exit;
        } catch (Exception $e) {
            error_log("Error updating role: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba při aktualizaci role']);
            exit;
        }

    // ===== SMAZÁNÍ ROLE =====
    } elseif ($action === 'delete_role') {
        // CSRF Protection
        if (!isset($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Neplatný CSRF token']);
            exit;
        }
        
        $roleId = sanitizeInput($data['role_id'] ?? '', 'int');
        
        if (empty($roleId)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'ID role je povinné']);
            exit;
        }
        
        try {
            // Kontrola, zda role není používána
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = (SELECT role_key FROM user_roles WHERE id = ?)");
            $stmt->execute([$roleId]);
            if ($stmt->fetchColumn() > 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Nelze smazat roli, která je přiřazena uživatelům']);
                exit;
            }
            
            // Smazání role
            $stmt = $pdo->prepare("DELETE FROM user_roles WHERE id = ?");
            $stmt->execute([$roleId]);
            
            if ($stmt->rowCount() === 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Role nebyla nalezena']);
                exit;
            }
            
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Role byla úspěšně smazána']);
            exit;
        } catch (Exception $e) {
            error_log("Error deleting role: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba při mazání role']);
            exit;
        }

    // ===== NAČTENÍ SYSTÉMOVÝCH NASTAVENÍ =====
    } elseif ($action === 'get_system_settings') {
        try {
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE category = 'system'");
            $stmt->execute();
            $settings = $stmt->fetchAll();
            
            $settingsArray = [];
            foreach ($settings as $setting) {
                $settingsArray[$setting['setting_key']] = $setting['setting_value'];
            }
            
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $settingsArray]);
            exit;
        } catch (Exception $e) {
            error_log("Error loading system settings: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba při načítání nastavení']);
            exit;
        }

    // ===== ULOŽENÍ SYSTÉMOVÝCH NASTAVENÍ =====
    } elseif ($action === 'save_system_settings') {
        // CSRF Protection
        if (!isset($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Neplatný CSRF token']);
            exit;
        }
        
        $settings = $data['settings'] ?? [];
        
        try {
            $pdo->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                    VALUES (?, ?, 'system', NOW())
                    ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value), 
                    updated_at = VALUES(updated_at)
                ");
                $stmt->execute([sanitizeInput($key), sanitizeInput($value)]);
            }
            
            $pdo->commit();
            
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Nastavení byla úspěšně uložena']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error saving system settings: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Chyba při ukládání nastavení']);
            exit;
        }

    // ===== NEPLATNÁ AKCE =====
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Neplatná akce: ' . $action]);
        exit;
    }

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Systémová chyba: ' . $e->getMessage()]);
    exit;
}
?>
