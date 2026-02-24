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

// Load dependencies (must be at file scope so `use` aliases are valid)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Raynet/autoload.php';

use Raynet\RaynetApiClient;
use Raynet\RaynetCompany;
use Raynet\RaynetPerson;
use Raynet\RaynetDuplicateChecker;

try {
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
            $personData = $person->getData();
            $results['raynet_matches'][] = [
                'match_type' => 'email',
                'match_score' => 100,
                'person' => $personData,
                'company' => findPersonCompany($client, $person->getId())
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
            
            // Collect IDs already found by email to avoid duplicates
            $foundIds = array_column(
                array_column($results['raynet_matches'], 'person'),
                'id'
            );

            foreach ($nameMatches as $person) {
                $personData = $person->getData();
                if (!in_array($personData['id'] ?? null, $foundIds, true)) {
                    $results['raynet_matches'][] = [
                        'match_type' => 'name',
                        'match_score' => calculateNameMatchScore($contactPerson, $personData),
                        'person' => $personData,
                        'company' => findPersonCompany($client, $person->getId())
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
            // ------------------------------------------------------------------
            // Before creating, run duplicate detection to prevent duplicates.
            // If a duplicate is found, return a 409 so the admin can choose to
            // link to the existing record instead.
            // Pass force_create = true in the request body to bypass this check.
            // ------------------------------------------------------------------
            $forceCreate = !empty($input['force_create']);

            if (!$forceCreate) {
                $checker = new RaynetDuplicateChecker($client);

                // Extract normalised fields for duplicate lookup
                $names = parseContactName($formData['contactPerson'] ?? '');
                $companyExtId = $companyEntity->generateExtId($formId);
                $personExtId  = $personEntity->generateExtId($formId);

                $dupPerson  = $checker->findExistingPerson($personExtId, [
                    'email'     => $formData['email'] ?? '',
                    'phone'     => $formData['phone'] ?? '',
                    'firstName' => $names['firstName'],
                    'lastName'  => $names['lastName'],
                ]);

                $dupCompany = $checker->findExistingCompany($companyExtId, [
                    'ico'       => $formData['ico'] ?? '',
                    'taxNumber' => $formData['dic'] ?? '',
                    'name'      => $formData['companyName'] ?? '',
                ]);

                if ($dupPerson || $dupCompany) {
                    ob_end_clean();
                    http_response_code(409);
                    echo json_encode([
                        'success'    => false,
                        'duplicate'  => true,
                        'error'      => 'Potenciální duplikát nalezen. Použijte režim \"Propojit\" pro existující záznamy, nebo pošlete force_create=true pro vynucení vytvoření.',
                        'found' => [
                            'person'  => $dupPerson  ? [
                                'id'         => $dupPerson['id'],
                                'matched_by' => $dupPerson['matched_by'],
                                'name'       => trim(($dupPerson['data']['firstName'] ?? '') . ' ' . ($dupPerson['data']['lastName'] ?? '')),
                                'email'      => $dupPerson['data']['contactInfo']['email'] ?? null,
                            ] : null,
                            'company' => $dupCompany ? [
                                'id'         => $dupCompany['id'],
                                'matched_by' => $dupCompany['matched_by'],
                                'name'       => $dupCompany['data']['name'] ?? null,
                                'ico'        => $dupCompany['data']['regNumber'] ?? null,
                            ] : null,
                        ]
                    ]);
                    exit;
                }
            }

            // No duplicate (or force_create=true) — create new records
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
