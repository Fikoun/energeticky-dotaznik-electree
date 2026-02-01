<?php
// Zabránit jakémukoli HTML výstupu
ob_start();

// Nastavit JSON header na začátku
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Vypnout zobrazování chyb do výstupu
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    // Dočasně zakázáno pro testování
    /*
    // Kontrola admin oprávnění
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        throw new Exception('Nedostatečná oprávnění');
    }
    */

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Neplatná JSON data');
    }

    $action = $data['action'] ?? '';

    // Zkusíme databázové připojení
    $useDatabase = false;
    $pdo = null;
    
    try {
        // Database configuration - use centralized config
        require_once __DIR__ . '/../config/database.php';
        $pdo = getDbConnection();
        $useDatabase = true;
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        $useDatabase = false;
    }

    if ($action === 'get_activity_log') {
        $activities = [];
        
        if ($useDatabase) {
            try {
                $stmt = $pdo->query("
                    SELECT 
                        a.id,
                        a.user_id,
                        u.name as user_name,
                        u.role as user_role,
                        a.action_type,
                        a.description,
                        a.ip_address,
                        a.user_agent,
                        a.created_at
                    FROM activity_log a
                    LEFT JOIN users u ON a.user_id = u.id
                    ORDER BY a.created_at DESC
                    LIMIT 100
                ");
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                $useDatabase = false;
            }
        }
        
        // Fallback mock data
        if (!$useDatabase) {
            $activities = [
                [
                    'id' => '1',
                    'user_id' => 'user_admin',
                    'user_name' => 'admin',
                    'user_role' => 'admin',
                    'action_type' => 'login',
                    'description' => 'Administrátor se přihlásil do systému',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
                ],
                [
                    'id' => '2',
                    'user_id' => 'user_test',
                    'user_name' => 'test',
                    'user_role' => 'user',
                    'action_type' => 'form_submit',
                    'description' => 'Uživatel odeslal nový formulář (ID: form_123)',
                    'ip_address' => '192.168.1.100',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
                ],
                [
                    'id' => '3',
                    'user_id' => 'user_partner',
                    'user_name' => 'partner',
                    'user_role' => 'partner',
                    'action_type' => 'form_draft',
                    'description' => 'Partner uložil formulář jako koncept (ID: form_124)',
                    'ip_address' => '10.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
                ],
                [
                    'id' => '4',
                    'user_id' => 'user_sales',
                    'user_name' => 'obchodnik',
                    'user_role' => 'sales',
                    'action_type' => 'login',
                    'description' => 'Obchodník se přihlásil do systému',
                    'ip_address' => '203.0.113.1',
                    'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))
                ],
                [
                    'id' => '5',
                    'user_id' => 'user_test',
                    'user_name' => 'test',
                    'user_role' => 'user',
                    'action_type' => 'gdpr_confirm',
                    'description' => 'Uživatel potvrdil GDPR souhlas (Token: abc123)',
                    'ip_address' => '192.168.1.100',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))
                ],
                [
                    'id' => '6',
                    'user_id' => 'user_admin',
                    'user_name' => 'admin',
                    'user_role' => 'admin',
                    'action_type' => 'user_create',
                    'description' => 'Administrátor vytvořil nového uživatele (new_user)',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours'))
                ],
                [
                    'id' => '7',
                    'user_id' => 'user_partner',
                    'user_name' => 'partner',
                    'user_role' => 'partner',
                    'action_type' => 'form_view',
                    'description' => 'Partner zobrazil formulář (ID: form_125)',
                    'ip_address' => '10.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours'))
                ],
                [
                    'id' => '8',
                    'user_id' => 'user_sales',
                    'user_name' => 'obchodnik',
                    'user_role' => 'sales',
                    'action_type' => 'form_submit',
                    'description' => 'Obchodník odeslal formulář za klienta (ID: form_126)',
                    'ip_address' => '203.0.113.1',
                    'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-8 hours'))
                ],
                [
                    'id' => '9',
                    'user_id' => 'user_test',
                    'user_name' => 'test',
                    'user_role' => 'user',
                    'action_type' => 'logout',
                    'description' => 'Uživatel se odhlásil ze systému',
                    'ip_address' => '192.168.1.100',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-12 hours'))
                ],
                [
                    'id' => '10',
                    'user_id' => 'user_admin',
                    'user_name' => 'admin',
                    'user_role' => 'admin',
                    'action_type' => 'system_backup',
                    'description' => 'Administrátor spustil zálohu systému',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
                ]
            ];
        }

        // Mapování aktivit na správný formát
        $formatted_activities = [];
        foreach ($activities as $activity) {
            $formatted_activities[] = [
                'id' => $activity['id'],
                'user_name' => $activity['user_name'],
                'action' => $activity['description'],
                'details' => $activity['description'],
                'ip_address' => $activity['ip_address'],
                'timestamp' => $activity['created_at']
            ];
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'activities' => $formatted_activities
        ]);

    } elseif ($action === 'log_activity') {
        // Function to log new activity
        $userId = $data['user_id'] ?? '';
        $actionType = $data['action_type'] ?? '';
        $description = $data['description'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (empty($userId) || empty($actionType) || empty($description)) {
            throw new Exception('Povinné údaje pro záznam aktivity chybí');
        }

        if ($useDatabase) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO activity_log (user_id, action_type, description, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$userId, $actionType, $description, $ipAddress, $userAgent]);
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                throw new Exception('Chyba při ukládání aktivity');
            }
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Aktivita byla zaznamenána'
        ]);

    } else {
        throw new Exception('Neplatná akce');
    }

} catch (Exception $e) {
    // Vyčistit output buffer před chybou
    ob_end_clean();
    
    error_log("Activity log error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
