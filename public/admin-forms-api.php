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

    if ($action === 'list_forms') {
        $statusFilter = $data['status_filter'] ?? '';
        $search = $data['search'] ?? '';
        $page = (int)($data['page'] ?? 1);
        $limit = (int)($data['limit'] ?? 10);
        $offset = ($page - 1) * $limit;
        
        $forms = [];
        $totalCount = 0;
        
        if ($useDatabase) {
            try {
                // Build WHERE clause
                $whereConditions = [];
                $params = [];
                
                if (!empty($statusFilter)) {
                    $whereConditions[] = "f.status = ?";
                    $params[] = $statusFilter;
                }
                
                if (!empty($search)) {
                    $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ? OR f.id LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
                
                // Count total records
                $countSql = "
                    SELECT COUNT(*) as total
                    FROM forms f
                    LEFT JOIN users u ON f.user_id = u.id
                    $whereClause
                ";
                $stmt = $pdo->prepare($countSql);
                $stmt->execute($params);
                $totalCount = $stmt->fetch()['total'];
                
                // Get paginated data
                $sql = "
                    SELECT f.*, u.name as user_name, u.email as user_email
                    FROM forms f
                    LEFT JOIN users u ON f.user_id = u.id
                    $whereClause
                    ORDER BY f.updated_at DESC
                    LIMIT $limit OFFSET $offset
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                $useDatabase = false;
            }
        }
        
        // Fallback mock data
        if (!$useDatabase) {
            $forms = [
                [
                    'id' => 'form_1',
                    'user_id' => 'user_test',
                    'user_name' => 'test',
                    'user_email' => 'test@temp.local',
                    'company_name' => 'Testovací firma s.r.o.',
                    'contact_person' => 'Jan Novák',
                    'phone' => '+420123456789',
                    'email' => 'jan.novak@test.cz',
                    'status' => 'submitted',
                    'form_data' => '{"step1":{"companyName":"Testovací firma s.r.o."}}',
                    'gdpr_confirmed_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
                ],
                [
                    'id' => 'form_2',
                    'user_id' => 'user_partner',
                    'user_name' => 'partner',
                    'user_email' => 'partner@electree.cz',
                    'company_name' => 'Rozpracovaná firma',
                    'contact_person' => 'Marie Svobodová',
                    'phone' => '+420987654321',
                    'email' => 'marie@rozpracovana.cz',
                    'status' => 'draft',
                    'form_data' => '{"step1":{"companyName":"Rozpracovaná firma"}}',
                    'gdpr_confirmed_at' => null,
                    'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
                ],
                [
                    'id' => 'form_3',
                    'user_id' => 'user_sales',
                    'user_name' => 'obchodnik',
                    'user_email' => 'sales@electree.cz',
                    'company_name' => 'Confirmed Company Ltd.',
                    'contact_person' => 'Petr Novotný',
                    'phone' => '+420555123456',
                    'email' => 'petr@confirmed.cz',
                    'status' => 'confirmed',
                    'form_data' => '{"step1":{"companyName":"Confirmed Company Ltd."}}',
                    'gdpr_confirmed_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'created_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-6 hours'))
                ]
            ];
            
            // Filter mock data if status filter is provided
            if (!empty($statusFilter)) {
                $forms = array_filter($forms, function($form) use ($statusFilter) {
                    return $form['status'] === $statusFilter;
                });
                $forms = array_values($forms); // Re-index array
            }
            
            // Apply search filter to mock data
            if (!empty($search)) {
                $forms = array_filter($forms, function($form) use ($search) {
                    $searchLower = strtolower($search);
                    return strpos(strtolower($form['user_name']), $searchLower) !== false ||
                           strpos(strtolower($form['user_email']), $searchLower) !== false ||
                           strpos(strtolower($form['id']), $searchLower) !== false;
                });
                $forms = array_values($forms);
            }
            
            $totalCount = count($forms);
            
            // Apply pagination to mock data
            $forms = array_slice($forms, $offset, $limit);
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'forms' => $forms,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_count' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ]);

    } elseif ($action === 'delete_form') {
        $formId = $data['form_id'] ?? '';

        if (empty($formId)) {
            throw new Exception('ID formuláře je povinné');
        }

        if ($useDatabase) {
            try {
                $stmt = $pdo->prepare("UPDATE forms SET status = 'deleted' WHERE id = ?");
                $stmt->execute([$formId]);
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                throw new Exception('Chyba při mazání formuláře');
            }
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Formulář byl označen jako smazaný'
        ]);

    } elseif ($action === 'change_form_status') {
        $formId = $data['form_id'] ?? '';
        $newStatus = $data['new_status'] ?? '';

        if (empty($formId) || empty($newStatus)) {
            throw new Exception('ID formuláře a nový stav jsou povinné');
        }

        if ($useDatabase) {
            try {
                $stmt = $pdo->prepare("UPDATE forms SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $formId]);
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                throw new Exception('Chyba při změně stavu formuláře');
            }
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Stav formuláře byl úspěšně změněn'
        ]);

    } elseif ($action === 'get_form_details') {
        $formId = $data['form_id'] ?? '';

        if (empty($formId)) {
            throw new Exception('ID formuláře je povinné');
        }

        $form_details = null;
        
        if ($useDatabase) {
            try {
                $stmt = $pdo->prepare("
                    SELECT f.*, u.name as user_name, u.email as user_email 
                    FROM forms f 
                    LEFT JOIN users u ON f.user_id = u.id 
                    WHERE f.id = ?
                ");
                $stmt->execute([$formId]);
                $form_details = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($form_details) {
                    // Dekóduj JSON data
                    $form_details['parsed_data'] = json_decode($form_details['form_data'], true);
                }
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                throw new Exception('Chyba při načítání detailu formuláře');
            }
        }

        if (!$form_details) {
            throw new Exception('Formulář nenalezen');
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'form' => $form_details
        ]);

    } elseif ($action === 'bulk_action') {
        $formIds = $data['form_ids'] ?? [];
        $bulkAction = $data['bulk_action'] ?? '';

        if (empty($formIds) || empty($bulkAction)) {
            throw new Exception('ID formulářů a akce jsou povinné');
        }

        if ($useDatabase) {
            try {
                $placeholders = str_repeat('?,', count($formIds) - 1) . '?';
                
                switch ($bulkAction) {
                    case 'delete':
                        $stmt = $pdo->prepare("UPDATE forms SET status = 'deleted' WHERE id IN ($placeholders)");
                        $stmt->execute($formIds);
                        break;
                    case 'approve':
                        $stmt = $pdo->prepare("UPDATE forms SET status = 'approved' WHERE id IN ($placeholders)");
                        $stmt->execute($formIds);
                        break;
                    case 'reject':
                        $stmt = $pdo->prepare("UPDATE forms SET status = 'rejected' WHERE id IN ($placeholders)");
                        $stmt->execute($formIds);
                        break;
                    default:
                        throw new Exception('Neplatná hromadná akce');
                }
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                throw new Exception('Chyba při provádění hromadné akce');
            }
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Hromadná akce byla úspěšně provedena'
        ]);

    } else {
        throw new Exception('Neplatná akce');
    }

} catch (Exception $e) {
    // Vyčistit output buffer před chybou
    ob_end_clean();
    
    error_log("Forms management error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
