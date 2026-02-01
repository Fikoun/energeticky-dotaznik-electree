<?php
// Disable HTML output for clean JSON responses


















































































































echo "4. Sync a form: curl -X POST 'http://localhost:8080/api/raynet-sync.php?action=sync&form_id=YOUR_FORM_ID'\n";echo "3. Test sync with: curl -X POST 'http://localhost:8080/api/raynet-sync.php?action=test'\n";echo "2. If columns missing, run: php migrations/add-raynet-columns.php\n";echo "1. If not configured, update config/raynet.php with your credentials\n";echo "\nNext steps:\n";echo "\n=== Test Complete ===\n";}    echo "   ⚠️  Database error: " . $e->getMessage() . "\n";} catch (Exception $e) {    }        echo "   Run: php migrations/add-raynet-columns.php\n";        echo "   ⚠️  Missing columns: " . implode(', ', $missingColumns) . "\n";    } else {        echo "   ✅ Database has all Raynet columns\n";    if (empty($missingColumns)) {        $missingColumns = array_diff($raynetColumns, $columns);    $raynetColumns = ['raynet_company_id', 'raynet_person_id', 'raynet_synced_at', 'raynet_sync_error'];        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);    $stmt = $pdo->query("DESCRIBE forms");    // Check if raynet columns exist        $pdo = getDbConnection();try {echo "\n4. Testing database connection...\n";// Test 4: Database connectionecho "\n   ✅ Entity transformation works correctly\n";echo "   - ExtId: " . $person->getExtId() . "\n";echo "   - Name: " . ($person->getData()['firstName'] ?? '') . ' ' . $person->getData()['lastName'] . "\n";echo "   Person data prepared:\n";$person->fromFormData($mockFormData, 999999);$person = $connector->person();echo "   - ExtId: " . $company->getExtId() . "\n";echo "   - IČO: " . ($company->getData()['regNumber'] ?? 'N/A') . "\n";echo "   - Name: " . $company->getData()['name'] . "\n";echo "   Company data prepared:\n";$company->fromFormData($mockFormData, 999999);$company = $connector->company();];    'batteryCapacity' => '200'    'installedPower' => '100',    'projectType' => 'battery_storage',    'zipCode' => '110 00',    'city' => 'Praha',    'address' => 'Testovací 123',    'phone' => '+420 123 456 789',    'email' => 'jan.novak@example.com',    'contactPerson' => 'Jan Novák',    'dic' => 'CZ12345678',    'ico' => '12345678',    'companyName' => 'Test Company s.r.o.',$mockFormData = [echo "\n3. Testing entity transformation (dry run)...\n";// Test 3: Test entity creation (dry run)}    exit(1);    echo "   ❌ API Error: " . $e->getMessage() . "\n";} catch (RaynetException $e) {    }        echo "   Found " . count($results) . " company(ies) in Raynet\n";    if (count($results) > 0) {        echo "   Rate limit remaining: " . ($connector->getClient()->getRateLimitRemaining() ?? 'unknown') . "\n";    echo "   ✅ API connection successful\n";        $results = $company->search([], 1);    $company = $connector->company();try {echo "\n2. Testing API connection...\n";// Test 2: Test API connection}    exit(1);    echo "   ❌ Error: " . $e->getMessage() . "\n";} catch (Exception $e) {    }        exit(1);        echo "   Please update config/raynet.php with your credentials\n";        echo "   ⚠️  Raynet connector is NOT configured\n";    } else {        echo "   ✅ Raynet connector is configured\n";    if ($connector->isConfigured()) {        $connector = RaynetConnector::create();try {echo "1. Checking configuration...\n";// Test 1: Check configurationecho "=== Raynet Connector Test ===\n\n";use Raynet\RaynetException;use Raynet\RaynetPerson;use Raynet\RaynetCompany;use Raynet\RaynetConnector;require_once __DIR__ . '/config/database.php';require_once __DIR__ . '/includes/Raynet/autoload.php'; */ * Run from command line: php test-raynet-connector.php * Tests the Raynet CRM connector functionality. *  * Raynet Connector Test Scriptob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Vypnout zobrazování chyb do výstupu
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load Raynet sync helper (optional - fails silently if not configured)
$raynetSyncEnabled = false;
$raynetHelperPath = __DIR__ . '/../includes/raynet-sync-helpers.php';
if (file_exists($raynetHelperPath)) {
    require_once $raynetHelperPath;
    $raynetSyncEnabled = function_exists('isRaynetConfigured') && isRaynetConfigured();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda není povolena']);
    exit;
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    error_log("Submit form - Raw input: " . $input);
    
    if (!$data) {
        throw new Exception('Neplatná JSON data');
    }

    // Database configuration - use centralized config
    require_once __DIR__ . '/../config/database.php';

    $useDatabase = false;
    $pdo = null;
    
    try {
        $pdo = getDbConnection();
        $useDatabase = true;
        error_log("Submit form - Database connected successfully");
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        $useDatabase = false;
    }

    // Determine if this is a draft save or final submission
    $isDraft = isset($data['isDraft']) && $data['isDraft'] === true;
    $isUpdate = isset($data['formId']) && !empty($data['formId']);
    $userId = $data['user']['id'] ?? null;

    error_log("Submit form - Processing: isDraft=$isDraft, isUpdate=$isUpdate, userId=$userId");

    if (!$userId) {
        throw new Exception('Chybí identifikace uživatele');
    }

    // Prepare basic form data
    $formData = json_encode($data, JSON_UNESCAPED_UNICODE);
    $currentTime = date('Y-m-d H:i:s');
    
    // Extract key fields for easier querying
    $companyName = $data['companyName'] ?? '';
    $contactPerson = $data['contactPerson'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';

    error_log("Submit form - Extracted data: company=$companyName, contact=$contactPerson, email=$email");

    $formId = null;
    
    if ($useDatabase) {
        if ($isUpdate) {
            // Update existing form
            error_log("Submit form - Updating form ID: " . $data['formId']);
            
            $stmt = $pdo->prepare("
                UPDATE forms SET 
                    company_name = ?,
                    contact_person = ?,
                    email = ?,
                    phone = ?,
                    form_data = ?,
                    status = ?,
                    updated_at = ?
                WHERE id = ? AND user_id = ?
            ");
            
            $status = $isDraft ? 'draft' : 'submitted';
            $result = $stmt->execute([
                $companyName,
                $contactPerson, 
                $email,
                $phone,
                $formData,
                $status,
                $currentTime,
                $data['formId'],
                $userId
            ]);
            
            if (!$result) {
                throw new Exception('Chyba při aktualizaci formuláře');
            }
            
            $formId = $data['formId'];
            error_log("Submit form - Updated form successfully: $formId");
            
        } else {
            // Create new form
            $formId = uniqid('form_' . $userId . '_');
            $status = $isDraft ? 'draft' : 'submitted';
            
            error_log("Submit form - Creating new form ID: $formId");
            
            $stmt = $pdo->prepare("
                INSERT INTO forms (
                    id, user_id, company_name, contact_person, email, phone,
                    form_data, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $formId,
                $userId,
                $companyName,
                $contactPerson,
                $email, 
                $phone,
                $formData,
                $status,
                $currentTime,
                $currentTime
            ]);
            
            if (!$result) {
                throw new Exception('Chyba při vytváření formuláře');
            }
            
            error_log("Submit form - Created form successfully: $formId");
        }
    } else {
        // Fallback bez databáze
        $formId = $data['formId'] ?? uniqid('mock_form_' . $userId . '_');
        error_log("Submit form - Using mock storage (no database): $formId");
    }

    // If this is just a draft save, return success without sending emails
    if ($isDraft) {
        error_log("Submit form - Draft saved successfully: $formId");
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Formulář byl uložen jako rozpracovaný',
            'formId' => $formId,
            'isDraft' => true
        ]);
        exit;
    }

    // Validate required fields for final submission
    if (empty($email) || empty($contactPerson)) {
        throw new Exception('Pro odeslání formuláře jsou povinné údaje: email a kontaktní osoba');
    }

    error_log("Submit form - Preparing GDPR email for: $email");

    // Send GDPR confirmation email for final submissions
    $gdprToken = bin2hex(random_bytes(32));
    
    // Store GDPR token if database available
    if ($useDatabase) {
        $stmt = $pdo->prepare("UPDATE forms SET gdpr_token = ? WHERE id = ?");
        $stmt->execute([$gdprToken, $formId]);
    }

    // Prepare email content
    $confirmationUrl = "https://ed.electree.cz/gdpr-confirm.php?token=" . $gdprToken;
    
    $emailSubject = "Potvrzení souhlasu GDPR - Dotazník bateriových systémů";
    $emailBody = "
        <h2>Potvrzení souhlasu se zpracováním osobních údajů</h2>
        <p>Dobrý den " . htmlspecialchars($contactPerson) . ",</p>
        <p>děkujeme za vyplnění dotazníku pro bateriové systémy.</p>
        
        <h3>Základní informace z vašeho dotazníku:</h3>
        <ul>
            <li><strong>Jméno:</strong> " . htmlspecialchars($contactPerson) . "</li>
            <li><strong>Email:</strong> " . htmlspecialchars($email) . "</li>
            <li><strong>Telefon:</strong> " . htmlspecialchars($phone ?: 'Neuvedeno') . "</li>
            " . ($companyName ? "<li><strong>Společnost:</strong> " . htmlspecialchars($companyName) . "</li>" : "") . "
            <li><strong>Datum odeslání:</strong> " . date('d.m.Y H:i') . "</li>
        </ul>
        
        <p><strong>Pro dokončení zpracování vašeho dotazníku je nutné potvrdit souhlas se zpracováním osobních údajů podle GDPR.</strong></p>
        
        <p>Kliknutím na následující odkaz potvrdíte správnost uvedených údajů a souhlas s jejich zpracováním:</p>
        
        <p style='margin: 20px 0;'>
            <a href='" . $confirmationUrl . "' style='background-color: #0066cc; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                POTVRDIT ÚDAJE A SOUHLAS GDPR
            </a>
        </p>
        
        <p><small>Pokud odkaz nefunguje, zkopírujte tuto adresu do prohlížeče:<br>
        " . $confirmationUrl . "</small></p>
        
        <p><small>Tento souhlas je nutný pro zpracování vaší poptávky. Bez potvrzení nebudeme moci vaši poptávku zpracovat.</small></p>
        
        <p>S pozdravem,<br>
        tým Electree</p>
    ";

    // Send email
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: noreply@electree.cz',
        'Reply-To: info@electree.cz'
    ];

    $emailSent = mail($email, $emailSubject, $emailBody, implode("\r\n", $headers));
    
    if (!$emailSent) {
        error_log("Failed to send GDPR confirmation email to: " . $email);
        // Continue, don't fail the submission
    } else {
        error_log("GDPR confirmation email sent successfully to: " . $email);
    }

    // Sync to Raynet CRM (async-friendly - won't block on failure)
    $raynetResult = null;
    if ($raynetSyncEnabled && $useDatabase) {
        try {
            $raynetResult = syncFormToRaynet($data, $formId, $pdo);
            if ($raynetResult && $raynetResult['success']) {
                error_log("Raynet sync successful for form: $formId");
            }
        } catch (Exception $e) {
            error_log("Raynet sync error (non-blocking): " . $e->getMessage());
        }
    }

    // Return success response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Formulář byl úspěšně odeslán. Na váš email jsme zaslali odkaz pro potvrzení souhlasu GDPR.',
        'formId' => $formId,
        'requiresGdprConfirmation' => true,
        'emailSent' => $emailSent,
        'raynetSynced' => $raynetResult ? $raynetResult['success'] : false
    ]);

    error_log("Submit form - Process completed successfully for: $formId");

} catch (Exception $e) {
    // Clean any HTML output to ensure pure JSON
    ob_end_clean();
    
    error_log("Form submission error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} catch (Throwable $e) {
    // Zachytit i fatal errors
    ob_end_clean();
    
    error_log("Submit form fatal error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Vnitřní chyba serveru'
    ]);
}
?>
