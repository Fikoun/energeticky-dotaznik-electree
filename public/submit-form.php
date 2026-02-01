<?php
// Form submission API - handles both draft saves and final submissions
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Disable HTML error output for clean JSON responses
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

    // Database configuration
    $host = 's2.onhost.cz';
    $dbname = 'OH_13_edele';
    $username = 'OH_13_edele';
    $password = 'stjTmLjaYBBKa9u9_U';

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
        // Ensure user exists in database (for foreign key constraint)
        $userEmail = $data['user']['email'] ?? 'unknown@electree.cz';
        $userName = $data['user']['name'] ?? 'Unknown User';
        
        $stmtCheckUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmtCheckUser->execute([$userId]);
        $existingUser = $stmtCheckUser->fetch();
        
        if (!$existingUser) {
            // Try to find by email
            $stmtFindUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtFindUser->execute([$userEmail]);
            $userByEmail = $stmtFindUser->fetch();
            
            if ($userByEmail) {
                $userId = $userByEmail['id'];
            } else {
                // Create user if not exists
                try {
                    $stmtCreateUser = $pdo->prepare("
                        INSERT INTO users (id, name, email, role, is_active, created_at) 
                        VALUES (?, ?, ?, 'user', 1, NOW())
                    ");
                    $stmtCreateUser->execute([$userId, $userName, $userEmail]);
                    error_log("Submit form - Created new user: $userId");
                } catch (PDOException $e) {
                    // If it's a duplicate key error, ignore it
                    if ($e->getCode() != 23000) {
                        throw $e;
                    }
                }
            }
        }
        
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
