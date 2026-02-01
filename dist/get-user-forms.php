<?php
// Zabránit jakémukoli HTML výstupu
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Vypnout zobrazování chyb do výstupu
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Database configuration - use centralized config
require_once __DIR__ . '/../config/database.php';

$useDatabase = false;
$pdo = null;

// Zkusíme databázové připojení
try {
    $pdo = getDbConnection();
    $useDatabase = true;
} catch(Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $useDatabase = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $_GET['userId'] ?? null;
    
    if (!$userId) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Missing userId parameter']);
        exit;
    }

    if ($useDatabase) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, user_id, company_name, contact_person, phone, email, 
                       status, form_data, gdpr_token, gdpr_confirmed_at, 
                       created_at, updated_at
                FROM forms 
                WHERE user_id = ? 
                ORDER BY updated_at DESC, created_at DESC
            ");
            
            $stmt->execute([$userId]);
            $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format dates for better readability
            foreach ($forms as &$form) {
                if ($form['created_at']) {
                    $form['created_at'] = date('c', strtotime($form['created_at']));
                }
                if ($form['updated_at']) {
                    $form['updated_at'] = date('c', strtotime($form['updated_at']));
                }
                if ($form['gdpr_confirmed_at']) {
                    $form['gdpr_confirmed_at'] = date('c', strtotime($form['gdpr_confirmed_at']));
                }
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'forms' => $forms
            ]);
            
        } catch(PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            $useDatabase = false;
        }
    }
    
    // Fallback na mock data pokud databáze nefunguje
    if (!$useDatabase) {
        // Mock data pro testování
        $mockForms = [
            [
                'id' => 'form_1',
                'user_id' => $userId,
                'company_name' => 'Testovací firma s.r.o.',
                'contact_person' => 'Jan Novák',
                'phone' => '+420123456789',
                'email' => 'jan.novak@test.cz',
                'status' => 'completed',
                'form_data' => '{"step1":{"companyName":"Testovací firma s.r.o.","contactPerson":"Jan Novák"},"step2":{"electricityBill":"5000"},"step8":{"finalSubmit":true}}',
                'gdpr_token' => null,
                'gdpr_confirmed_at' => date('c'),
                'created_at' => date('c', strtotime('-2 days')),
                'updated_at' => date('c', strtotime('-1 day'))
            ],
            [
                'id' => 'form_2',
                'user_id' => $userId,
                'company_name' => 'Rozpracovaná firma',
                'contact_person' => 'Marie Svobodová',
                'phone' => '+420987654321',
                'email' => 'marie@rozpracovana.cz',
                'status' => 'draft',
                'form_data' => '{"step1":{"companyName":"Rozpracovaná firma","contactPerson":"Marie Svobodová"},"step2":{"electricityBill":"3000"}}',
                'gdpr_token' => null,
                'gdpr_confirmed_at' => null,
                'created_at' => date('c', strtotime('-5 days')),
                'updated_at' => date('c', strtotime('-3 days'))
            ]
        ];
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'forms' => $mockForms,
            'note' => 'Using mock data - database not available'
        ]);
    }
    
} else {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
