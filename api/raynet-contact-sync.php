<?php
/**
 * Raynet Contact Sync API
 * 
 * Manual contact matching and synchronization between local forms and Raynet CRM.
 * Provides search, comparison, and manual confirmation workflow.
 */

session_start();

ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Nedostatečná oprávnění']);
    exit;
}

try {
    // Load dependencies
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/Raynet/autoload.php';
    
    use Raynet\RaynetApiClient;
    use Raynet\RaynetCompany;
    use Raynet\RaynetPerson;
    
    $pdo = getDbConnection();
    
    // Parse request
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'search-contact':
            searchContactInRaynet($pdo);
            break;
            
        case 'confirm-sync':
            confirmContactSync($pdo);
            break;
            
        default:
            throw new Exception('Neplatná akce');
    }
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

/**
 * Search for contact in Raynet by email and name
 */
function searchContactInRaynet($pdo)
{
    $formId = $_GET['form_id'] ?? null;
    
    if (!$formId) {
        throw new Exception('ID formuláře není zadáno');
    }
    
    // Get local form data
    $stmt = $pdo->prepare("
        SELECT id, form_data, raynet_company_id, raynet_person_id, raynet_synced_at
        FROM forms
        WHERE id = ?
    ");
    $stmt->execute([$formId]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        throw new Exception('Formulář nenalezen');
    }
    
    $formData = json_decode($form['form_data'], true);
    
    // Extract search criteria
    $email = $formData['email'] ?? '';
    $contactPerson = $formData['contactPerson'] ?? '';
    $companyName = $formData['companyName'] ?? '';
    $ico = $formData['ico'] ?? '';
    
    // Initialize Raynet client
    $client = RaynetApiClient::fromConfig();
    
    if (!$client->isConfigured()) {
        throw new Exception('Raynet není nakonfigurováno');
    }
    
    $results = [
        'local_data' => [
            'form_id' => $form['id'],
            'email' => $email,
            'contact_person' => $contactPerson,
            'company_name' => $companyName,
            'ico' => $ico,
            'phone' => $formData['phone'] ?? '',
            'already_synced' => [
                'company_id' => $form['raynet_company_id'],
                'person_id' => $form['raynet_person_id'],
                'synced_at' => $form['raynet_synced_at']
            ]
        ],
        'raynet_matches' => []
    ];
    
    // 1. Search by email (primary)
    if (!empty($email)) {
        $personEntity = new RaynetPerson($client);
        $emailMatches = $personEntity->search([
            'contactInfo.email' => ['EQ' => $email]
        ], 10);
        
        foreach ($emailMatches as $person) {
            $results['raynet_matches'][] = [
                'match_type' => 'email',
                'match_score' => 100,
                'person' => $person,
                'company' => findPersonCompany($client, $person['id'])
            ];
        }
    }
    
    // 2. Search by name (secondary)
    if (!empty($contactPerson) && count($results['raynet_matches']) < 5) {
        $names = parseContactName($contactPerson);
        $personEntity = new RaynetPerson($client);
        
        // Try exact name match
        $nameFilters = [];
        if (!empty($names['firstName'])) {
            $nameFilters['firstName'] = ['LIKE' => $names['firstName'] . '%'];
        }
        if (!empty($names['lastName'])) {
            $nameFilters['lastName'] = ['LIKE' => $names['lastName'] . '%'];
        }
        
        if (!empty($nameFilters)) {
            $nameMatches = $personEntity->search($nameFilters, 10);
            
            foreach ($nameMatches as $person) {
                // Skip if already found by email
                $alreadyFound = false;
                foreach ($results['raynet_matches'] as $existing) {
                    if ($existing['person']['id'] === $person['id']) {
                        $alreadyFound = true;
                        break;
                    }
                }
                
                if (!$alreadyFound) {
                    $results['raynet_matches'][] = [
                        'match_type' => 'name',
                        'match_score' => calculateNameMatchScore($contactPerson, $person),
                        'person' => $person,
                        'company' => findPersonCompany($client, $person['id'])
                    ];
                }
            }
        }
    }
    
    // 3. Search companies by IČO or name (for context)
    if (!empty($ico) || !empty($companyName)) {
        $companyEntity = new RaynetCompany($client);
        $companyMatches = [];
        
        if (!empty($ico)) {
            try {
                $icoCompany = $companyEntity->findByIco($ico);
                if ($icoCompany) {
                    $companyMatches[] = $icoCompany->getData();
                }
            } catch (Exception $e) {
                error_log("IČO search failed: " . $e->getMessage());
            }
        }
        
        if (empty($companyMatches) && !empty($companyName)) {
            $companyMatches = $companyEntity->search([
                'name' => ['LIKE' => '%' . $companyName . '%']
            ], 5);
        }
        
        $results['company_matches'] = $companyMatches;
    }
    
    // Sort matches by score
    usort($results['raynet_matches'], function($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

/**
 * Confirm and execute contact sync
 */
function confirmContactSync($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $formId = $input['form_id'] ?? null;
    $personId = $input['person_id'] ?? null;
    $companyId = $input['company_id'] ?? null;
    $updateMode = $input['update_mode'] ?? 'link'; // 'link', 'update', or 'create'
    
    if (!$formId) {
        throw new Exception('ID formuláře není zadáno');
    }
    
    // Get form data
    $stmt = $pdo->prepare("SELECT form_data FROM forms WHERE id = ?");
    $stmt->execute([$formId]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        throw new Exception('Formulář nenalezen');
    }
    
    $formData = json_decode($form['form_data'], true);
    
    // Initialize Raynet client
    $client = RaynetApiClient::fromConfig();
    $companyEntity = new RaynetCompany($client);
    $personEntity = new RaynetPerson($client);
    
    $syncedCompanyId = $companyId;
    $syncedPersonId = $personId;
    
    // Handle based on update mode
    switch ($updateMode) {
        case 'link':
            // Just link existing IDs without updating data
            if (!$personId && !$companyId) {
                throw new Exception('Není vybrána žádná entita pro propojení');
            }
            break;
            
        case 'update':
            // Update existing Raynet records with form data
            if ($companyId) {
                $companyEntity->setId($companyId);
                $companyEntity->fromFormData($formData, $formId);
                $companyEntity->update();
            }
            
            if ($personId) {
                $personEntity->setId($personId);
                $personEntity->fromFormData($formData, $formId);
                $personEntity->update();
                
                // Ensure person is linked to company
                if ($companyId && !$personEntity->isLinkedToCompany($companyId)) {
                    $personEntity->linkToCompany($companyId);
                }
            }
            break;
            
        case 'create':
            // Create new records in Raynet
            $companyEntity->fromFormData($formData, $formId);
            $companyEntity->save();
            $syncedCompanyId = $companyEntity->getId();
            
            $personEntity->fromFormData($formData, $formId);
            $personEntity->save();
            $syncedPersonId = $personEntity->getId();
            
            // Link person to company
            if ($syncedCompanyId && $syncedPersonId) {
                $personEntity->linkToCompany($syncedCompanyId);
            }
            break;
            
        default:
            throw new Exception('Neplatný režim synchronizace');
    }
    
    // Update local database
    $stmt = $pdo->prepare("
        UPDATE forms 
        SET raynet_company_id = ?,
            raynet_person_id = ?,
            raynet_synced_at = NOW(),
            raynet_sync_error = NULL
        WHERE id = ?
    ");
    $stmt->execute([$syncedCompanyId, $syncedPersonId, $formId]);
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'company_id' => $syncedCompanyId,
            'person_id' => $syncedPersonId,
            'mode' => $updateMode
        ],
        'message' => 'Kontakt byl úspěšně synchronizován'
    ]);
}

/**
 * Find company associated with a person
 */
function findPersonCompany($client, $personId)
{
    try {
        $result = $client->get("/person/{$personId}/relationship/");
        $relationships = $result['data'] ?? [];
        
        foreach ($relationships as $rel) {
            if (isset($rel['company']['id'])) {
                // Get full company details
                $companyResult = $client->get("/company/{$rel['company']['id']}/");
                return $companyResult['data'] ?? null;
            }
        }
    } catch (Exception $e) {
        error_log("Failed to find person company: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Parse contact name into first and last name
 */
function parseContactName($fullName)
{
    $parts = explode(' ', trim($fullName), 2);
    
    return [
        'firstName' => $parts[0] ?? '',
        'lastName' => $parts[1] ?? ($parts[0] ?? '')
    ];
}

/**
 * Calculate name match score (0-100)
 */
function calculateNameMatchScore($localName, $raynetPerson)
{
    $localName = strtolower(trim($localName));
    $raynetFullName = strtolower(trim(
        ($raynetPerson['firstName'] ?? '') . ' ' . ($raynetPerson['lastName'] ?? '')
    ));
    
    // Exact match
    if ($localName === $raynetFullName) {
        return 95;
    }
    
    // Contains match
    if (strpos($raynetFullName, $localName) !== false || strpos($localName, $raynetFullName) !== false) {
        return 80;
    }
    
    // Check individual name parts
    $localParts = explode(' ', $localName);
    $raynetParts = explode(' ', $raynetFullName);
    $matchedParts = 0;
    
    foreach ($localParts as $localPart) {
        foreach ($raynetParts as $raynetPart) {
            if ($localPart === $raynetPart) {
                $matchedParts++;
                break;
            }
        }
    }
    
    $totalParts = max(count($localParts), count($raynetParts));
    return (int) (($matchedParts / $totalParts) * 70);
}
