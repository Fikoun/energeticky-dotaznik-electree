<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();
    
    // Get form ID from POST
    $input = json_decode(file_get_contents('php://input'), true);
    $formId = $input['formId'] ?? $_POST['formId'] ?? null;
    
    if (!$formId) {
        throw new Exception('Form ID je povinný');
    }
    
    // Fetch form data
    $stmt = $pdo->prepare("SELECT id, form_data, gdpr_confirmed_at FROM forms WHERE id = ?");
    $stmt->execute([$formId]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        throw new Exception('Formulář nebyl nalezen');
    }
    
    // Check if already confirmed
    if ($form['gdpr_confirmed_at']) {
        throw new Exception('GDPR souhlas již byl potvrzen dne ' . date('d.m.Y H:i', strtotime($form['gdpr_confirmed_at'])));
    }
    
    $formData = json_decode($form['form_data'], true);
    
    // Extract contact information
    $email = $formData['email'] ?? null;
    $contactPerson = $formData['contactPerson'] ?? 'Vážený zákazníku';
    $phone = $formData['phone'] ?? null;
    $companyName = $formData['companyName'] ?? null;
    
    if (!$email) {
        throw new Exception('Email nebyl nalezen ve formuláři');
    }
    
    // Generate new GDPR token
    $gdprToken = bin2hex(random_bytes(32));
    
    // Update token in database
    $stmt = $pdo->prepare("UPDATE forms SET gdpr_token = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$gdprToken, $formId]);
    
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
        
        <p><em>Poznámka: Tento email byl znovu odeslán na žádost administrátora.</em></p>
        
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
        error_log("Failed to resend GDPR confirmation email to: " . $email . " for form: " . $formId);
        throw new Exception('Email se nepodařilo odeslat');
    }
    
    error_log("GDPR confirmation email resent successfully to: " . $email . " for form: " . $formId);
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'GDPR email byl úspěšně odeslán na adresu: ' . $email,
        'email' => $email,
        'formId' => $formId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    error_log("Resend GDPR error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
