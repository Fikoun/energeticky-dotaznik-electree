<?php
header('Content-Type: text/html; charset=utf-8');

// Database configuration - use centralized config
require_once __DIR__ . '/config/database.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('<h1>Chyba</h1><p>Neplatný odkaz pro potvrzení GDPR.</p>');
}

try {
    $pdo = getDbConnection();

    // Find form by GDPR token
    $stmt = $pdo->prepare("SELECT * FROM forms WHERE gdpr_token = ? AND gdpr_confirmed_at IS NULL");
    $stmt->execute([$token]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$form) {
        die('<h1>Chyba</h1><p>Neplatný nebo již použitý odkaz pro potvrzení GDPR.</p>');
    }

    $formData = json_decode($form['form_data'], true);

    // Handle form submission (confirmation)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_gdpr'])) {
        // Update GDPR confirmation
        $stmt = $pdo->prepare("UPDATE forms SET gdpr_confirmed_at = NOW(), status = 'confirmed' WHERE id = ?");
        $stmt->execute([$form['id']]);

        // Send data to Raynet
        $raynetSuccess = sendToRaynet($formData, $form['id']);

        // Send notification email to admin
        sendAdminNotification($form, $formData);

        // Show success page
        showSuccessPage($form['id'], $raynetSuccess);
        exit;
    }

    // Show confirmation form with all data
    showConfirmationForm($form, $formData);

} catch (Exception $e) {
    error_log("GDPR confirmation error: " . $e->getMessage());
    die('<h1>Chyba</h1><p>Došlo k technické chybě. Kontaktujte nás prosím na info@electree.cz</p>');
}

function showConfirmationForm($form, $formData) {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Potvrzení údajů a GDPR - Electree</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; line-height: 1.6; }
            .header { background: #0066cc; color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; text-align: center; }
            .section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #0066cc; }
            .section h3 { margin-top: 0; color: #0066cc; }
            .info { background: #cce6ff; color: #004085; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .confirm-box { background: #e8f5e8; border: 2px solid #28a745; padding: 20px; border-radius: 8px; margin: 30px 0; }
            .btn { background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
            .btn:hover { background: #218838; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            td { padding: 8px; border-bottom: 1px solid #ddd; }
            td:first-child { font-weight: bold; width: 200px; }
            .gdpr-text { font-size: 14px; line-height: 1.5; margin: 20px 0; }
            .required { color: red; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>🔒 Potvrzení údajů a souhlas GDPR</h1>
            <p>Electree - Bateriové systémy</p>
        </div>

        <div class="info">
            <p><strong>Věc:</strong> Potvrzení správnosti údajů z dotazníku a souhlas se zpracováním osobních údajů</p>
            <p><strong>Datum odeslání:</strong> <?php echo date('d.m.Y H:i', strtotime($form['created_at'])); ?></p>
        </div>

        <form method="POST">
            <!-- Základní údaje -->
            <div class="section">
                <h3>1. Identifikační údaje</h3>
                <table>
                    <tr><td>Název společnosti:</td><td><?php echo htmlspecialchars($formData['companyName'] ?? 'Neuvedeno'); ?></td></tr>
                    <tr><td>IČO:</td><td><?php echo htmlspecialchars($formData['ico'] ?? 'Neuvedeno'); ?></td></tr>
                    <tr><td>DIČ:</td><td><?php echo htmlspecialchars($formData['dic'] ?? 'Neuvedeno'); ?></td></tr>
                    <tr><td>Kontaktní osoba:</td><td><?php echo htmlspecialchars($formData['contactPerson'] ?? 'Neuvedeno'); ?></td></tr>
                    <tr><td>Telefon:</td><td><?php echo htmlspecialchars($formData['phone'] ?? 'Neuvedeno'); ?></td></tr>
                    <tr><td>Email:</td><td><?php echo htmlspecialchars($formData['email'] ?? 'Neuvedeno'); ?></td></tr>
                    <tr><td>Adresa:</td><td><?php echo htmlspecialchars($formData['address'] ?? 'Neuvedeno'); ?></td></tr>
                </table>
            </div>

            <!-- Technické parametry -->
            <div class="section">
                <h3>2. Parametry odběrného místa</h3>
                <table>
                    <tr><td>Instalace FVE/VTE:</td><td><?php echo $formData['hasFveVte'] === 'yes' ? 'Ano' : ($formData['hasFveVte'] === 'no' ? 'Ne' : 'Neuvedeno'); ?></td></tr>
                    <?php if ($formData['hasFveVte'] === 'yes'): ?>
                    <tr><td>Výkon FVE:</td><td><?php echo htmlspecialchars($formData['fveVtePower'] ?? ''); ?> kWp</td></tr>
                    <tr><td>% přetoků k akumulaci:</td><td><?php echo htmlspecialchars($formData['accumulationPercentage'] ?? ''); ?>%</td></tr>
                    <?php endif; ?>
                    <tr><td>Trafostanice:</td><td><?php echo $formData['hasTransformer'] === 'yes' ? 'Ano' : ($formData['hasTransformer'] === 'no' ? 'Ne' : 'Neuvedeno'); ?></td></tr>
                    <tr><td>Hlavní jistič:</td><td><?php echo htmlspecialchars($formData['mainCircuitBreaker'] ?? ''); ?> A</td></tr>
                    <tr><td>Rezervovaný příkon:</td><td><?php echo htmlspecialchars($formData['reservedPower'] ?? ''); ?> kW</td></tr>
                    <tr><td>Měsíční spotřeba:</td><td><?php echo htmlspecialchars($formData['monthlyConsumption'] ?? ''); ?> MWh</td></tr>
                </table>
            </div>

            <!-- Energetické potřeby -->
            <div class="section">
                <h3>3. Energetické potřeby</h3>
                <table>
                    <tr><td>Distribuční území:</td><td><?php echo htmlspecialchars($formData['distributionTerritory'] ?? 'Neuvedeno'); ?></td></tr>
                    <?php if ($formData['distributionTerritory'] === 'lds'): ?>
                    <tr><td>Název LDS:</td><td><?php echo htmlspecialchars($formData['ldsName'] ?? ''); ?></td></tr>
                    <tr><td>Majitel LDS:</td><td><?php echo htmlspecialchars($formData['ldsOwner'] ?? ''); ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Typ měření:</td><td><?php echo htmlspecialchars($formData['measurementType'] ?? 'Neuvedeno'); ?></td></tr>
                    <tr><td>Pracovní dny - čas:</td><td><?php echo ($formData['weekdayStart'] ?? 8) . ':00 - ' . ($formData['weekdayEnd'] ?? 17) . ':00'; ?></td></tr>
                    <tr><td>Pracovní dny - spotřeba:</td><td><?php echo htmlspecialchars($formData['weekdayConsumption'] ?? '0'); ?> kW</td></tr>
                    <tr><td>Víkendy - čas:</td><td><?php echo ($formData['weekendStart'] ?? 10) . ':00 - ' . ($formData['weekendEnd'] ?? 15) . ':00'; ?></td></tr>
                    <tr><td>Víkendy - spotřeba:</td><td><?php echo htmlspecialchars($formData['weekendConsumption'] ?? '0'); ?> kW</td></tr>
                </table>
            </div>

            <!-- Energetický dotazník -->
            <?php if (!empty($formData['energyPricing'])): ?>
            <div class="section">
                <h3>8. Energetický dotazník</h3>
                <table>
                    <tr><td>Cenování energie:</td><td><?php echo htmlspecialchars($formData['energyPricing'] ?? 'Neuvedeno'); ?></td></tr>
                    <tr><td>Aktuální cena energie:</td><td><?php echo htmlspecialchars($formData['currentEnergyPrice'] ?? ''); ?> Kč/MWh</td></tr>
                    <tr><td>Způsob fakturace:</td><td><?php echo htmlspecialchars($formData['billingMethod'] ?? 'Neuvedeno'); ?></td></tr>
                    <tr><td>Sdílení elektřiny:</td><td><?php echo htmlspecialchars($formData['electricitySharing'] ?? 'Neuvedeno'); ?></td></tr>
                    <?php if ($formData['hasGasConsumption']): ?>
                    <tr><td>Spotřeba plynu:</td><td><?php echo htmlspecialchars($formData['gasConsumption'] ?? ''); ?> MWh/rok</td></tr>
                    <?php endif; ?>
                    <?php if ($formData['hasCogeneration']): ?>
                    <tr><td>Kogenerace:</td><td><?php echo htmlspecialchars($formData['cogenerationDetails'] ?? ''); ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>

            <!-- Poznámky -->
            <?php if (!empty($formData['additionalNotes'])): ?>
            <div class="section">
                <h3>7. Dodatečné poznámky</h3>
                <p><?php echo nl2br(htmlspecialchars($formData['additionalNotes'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- GDPR souhlas -->
            <div class="confirm-box">
                <h3>🔒 Potvrzení a souhlas GDPR</h3>
                
                <div class="gdpr-text">
                    <p><strong>Tímto potvrzuji:</strong></p>
                    <ul>
                        <li>✅ Správnost všech výše uvedených údajů</li>
                        <li>✅ Souhlas se zpracováním osobních údajů podle GDPR</li>
                        <li>✅ Souhlas s kontaktováním ohledně nabídky bateriových systémů</li>
                        <li>✅ Předání dat do CRM systému Raynet pro zpracování poptávky</li>
                    </ul>
                    
                    <p><strong>Zpracovatel údajů:</strong> Electree s.r.o.<br>
                    <strong>Účel zpracování:</strong> Zpracování poptávky na bateriové systémy<br>
                    <strong>Doba uchování:</strong> 3 roky od posledního kontaktu</p>
                    
                    <p><small>Souhlas můžete kdykoli odvolat na emailu info@electree.cz</small></p>
                </div>

                <label style="display: flex; align-items: center; margin: 20px 0;">
                    <input type="checkbox" required style="margin-right: 10px; transform: scale(1.2);">
                    <span><strong class="required">*</strong> Potvrzuję správnost údajů a souhlasím se zpracováním osobních údajů podle GDPR</span>
                </label>

                <button type="submit" name="confirm_gdpr" class="btn">
                    🔒 POTVRDIT ÚDAJE A SOUHLAS
                </button>
            </div>
        </form>

        <div class="info">
            <p><strong>Kontakt:</strong> info@electree.cz | +420 123 456 789 | www.electree.cz</p>
            <p><small>ID formuláře: <?php echo htmlspecialchars($form['id']); ?></small></p>
        </div>
    </body>
    </html>
    <?php
}

function showSuccessPage($formId, $raynetSuccess) {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>GDPR Souhlas Potvrzen - Electree</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; line-height: 1.6; }
            .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
            .info { background: #cce6ff; color: #004085; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .checkmark { font-size: 48px; color: #28a745; }
        </style>
    </head>
    <body>
        <div class="success">
            <div class="checkmark">✅</div>
            <h1>GDPR Souhlas Úspěšně Potvrzen</h1>
            <p><strong>Děkujeme!</strong> Váš souhlas se zpracováním osobních údajů byl úspěšně potvrzen.</p>
        </div>

        <div class="info">
            <h3>Co se děje dále?</h3>
            <ul>
                <li>✅ Vaše data byla předána našemu týmu specialistů</li>
                <li>✅ Dotazník byl <?php echo $raynetSuccess ? 'úspěšně odeslán' : 'zařazen k manuálnímu zpracování'; ?> do systému Raynet</li>
                <li>📞 Do 2 pracovních dnů vás kontaktuje náš specialista</li>
                <li>📋 Připravíme pro vás individuální nabídku bateriového systému</li>
            </ul>
        </div>

        <?php if (!$raynetSuccess): ?>
        <div class="warning">
            <strong>Upozornění:</strong> Došlo k drobné technické chybě při automatickém předání dat do našeho CRM systému. 
            Vaše data jsou ale bezpečně uložena a budou zpracována manuálně.
        </div>
        <?php endif; ?>

        <div class="info">
            <h3>Kontaktní údaje:</h3>
            <p>
                <strong>Email:</strong> info@electree.cz<br>
                <strong>Telefon:</strong> +420 123 456 789<br>
                <strong>Web:</strong> <a href="https://electree.cz">www.electree.cz</a>
            </p>
        </div>

        <p><small>ID formuláře: <?php echo htmlspecialchars($formId); ?></small></p>
    </body>
    </html>
    <?php
}

function sendToRaynet($formData, $formId) {
    try {
        // Raynet API configuration
        $raynetApiUrl = 'https://app.raynet.cz/api/v2/company/';
        $raynetUsername = 'your_raynet_username';
        $raynetApiKey = 'your_raynet_api_key';

        // Prepare Raynet data structure
        $raynetData = [
            'name' => $formData['companyName'] ?? ($formData['contactPerson'] ?? 'Neznámá společnost'),
            'person' => [
                'firstName' => $formData['contactPerson'] ?? '',
                'contactInfo' => [
                    'email' => $formData['email'] ?? '',
                    'tel' => $formData['phone'] ?? ''
                ]
            ],
            'addresses' => [
                [
                    'address' => [
                        'name' => $formData['address'] ?? ''
                    ],
                    'contactInfo' => [
                        'email' => $formData['email'] ?? '',
                        'tel' => $formData['phone'] ?? ''
                    ]
                ]
            ],
            'customFields' => [
                'batteryFormId' => $formId,
                'technicalParams' => json_encode($formData),
                'submissionDate' => date('Y-m-d H:i:s')
            ],
            'note' => "Automaticky vytvořeno z dotazníku bateriových systémů. ID: $formId\n\nKlíčové údaje:\n" . 
                     "- Rezervovaný příkon: " . ($formData['reservedPower'] ?? 'N/A') . " kW\n" .
                     "- Měsíční spotřeba: " . ($formData['monthlyConsumption'] ?? 'N/A') . " MWh\n" .
                     "- FVE instalace: " . ($formData['hasFveVte'] === 'yes' ? 'Ano' : 'Ne'),
            'category' => 'lead',
            'tags' => ['battery-form', 'website-lead', 'gdpr-confirmed']
        ];

        // Send to Raynet
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $raynetApiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($raynetData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Instance-Name: your_instance_name',
            'Authorization: Basic ' . base64_encode($raynetUsername . ':' . $raynetApiKey)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Successfully sent form $formId to Raynet");
            return true;
        } else {
            error_log("Failed to send form $formId to Raynet. HTTP Code: $httpCode, Response: $response");
            return false;
        }

    } catch (Exception $e) {
        error_log("Raynet API error for form $formId: " . $e->getMessage());
        return false;
    }
}

function sendAdminNotification($form, $formData) {
    $subject = "Nový potvrzený dotazník bateriových systémů";
    $body = "
        <h2>Nový potvrzený dotazník bateriových systémů</h2>
        <p><strong>ID formuláře:</strong> {$form['id']}</p>
        <p><strong>Datum odeslání:</strong> {$form['created_at']}</p>
        <p><strong>Potvrzeno GDPR:</strong> " . date('Y-m-d H:i:s') . "</p>
        
        <h3>Kontaktní údaje:</h3>
        <ul>
            <li><strong>Společnost:</strong> " . htmlspecialchars($formData['companyName'] ?? 'Neuvedeno') . "</li>
            <li><strong>Osoba:</strong> " . htmlspecialchars($formData['contactPerson'] ?? 'Neuvedeno') . "</li>
            <li><strong>Email:</strong> " . htmlspecialchars($formData['email'] ?? 'Neuvedeno') . "</li>
            <li><strong>Telefon:</strong> " . htmlspecialchars($formData['phone'] ?? 'Neuvedeno') . "</li>
        </ul>
        
        <h3>Klíčové parametry:</h3>
        <ul>
            <li><strong>Rezervovaný příkon:</strong> " . htmlspecialchars($formData['reservedPower'] ?? 'N/A') . " kW</li>
            <li><strong>Měsíční spotřeba:</strong> " . htmlspecialchars($formData['monthlyConsumption'] ?? 'N/A') . " MWh</li>
            <li><strong>FVE instalace:</strong> " . ($formData['hasFveVte'] === 'yes' ? 'Ano' : 'Ne') . "</li>
        </ul>
    ";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: noreply@electree.cz'
    ];

    mail('info@electree.cz', $subject, $body, implode("\r\n", $headers));
}
?>
