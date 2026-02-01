<?php
session_start();

// Kontrola oprávnění
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /');
    exit();
}

$form_id = $_GET['id'] ?? '';

if (empty($form_id)) {
    echo "Neplatné ID formuláře";
    exit();
}

// Log form view activity
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/UserActivityTracker.php';
        $tracker = new UserActivityTracker();
        $tracker->logActivity($_SESSION['user_id'], 'form_view', "Zobrazení detailu formuláře #$form_id");
    } catch (Exception $e) {
        error_log("Activity logging error: " . $e->getMessage());
    }
}

// Načtení dat formuláře
$form_data = null;

try {
    // Database configuration - use centralized config
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as user_name, u.email as user_email 
        FROM forms f 
        LEFT JOIN users u ON f.user_id = u.id 
        WHERE f.id = ?
    ");
    $stmt->execute([$form_id]);
    $form_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Database error in form detail: " . $e->getMessage());
    $form_data = null;
}

if (!$form_data) {
    echo "Formulář nenalezen";
    exit();
}

// Dekódování dat formuláře
$decoded_data = json_decode($form_data['form_data'], true);

function getStepLabel($step) {
    $labels = [
        'step1' => '🔍 Základní informace',
        'step2' => '📞 Kontaktní údaje',
        'step3' => '🏠 Adresa instalace',
        'step4' => '⚡ Technické údaje objektu',
        'step5' => '🔋 Preference baterie',
        'step6' => '📅 Časové preference',
        'step7' => '💬 Dodatečné informace',
        'step8' => '✅ Souhlas s podmínkami'
    ];
    return $labels[$step] ?? ucfirst($step);
}

function getFieldLabel($field) {
    $labels = [
        // Step 1 - Základní informace
        'name' => 'Jméno a příjmení',
        'email' => 'E-mailová adresa',
        'phone' => 'Telefonní číslo',
        
        // Step 2 - Kontaktní údaje
        'contactPreference' => 'Preferovaný způsob kontaktu',
        'bestTimeToCall' => 'Nejlepší čas pro volání',
        'alternativePhone' => 'Alternativní telefon',
        
        // Step 3 - Adresa
        'street' => 'Ulice a číslo popisné',
        'city' => 'Město',
        'postalCode' => 'PSČ',
        'region' => 'Kraj',
        'houseType' => 'Typ nemovitosti',
        'propertyOwnership' => 'Vlastnictví nemovitosti',
        
        // Step 4 - Technické údaje
        'roofType' => 'Typ střechy',
        'roofOrientation' => 'Orientace střechy',
        'roofArea' => 'Plocha střechy',
        'roofAngle' => 'Sklon střechy',
        'shadingIssues' => 'Stínění střechy',
        'currentConsumption' => 'Roční spotřeba energie',
        'electricityBill' => 'Měsíční účet za elektřinu',
        'heatingType' => 'Typ vytápění',
        'electricVehicle' => 'Vlastnictví elektromobilu',
        
        // Step 5 - Preference baterie
        'batteryInterest' => 'Zájem o akumulaci energie',
        'batteryCapacity' => 'Preferovaná kapacita baterie',
        'batteryType' => 'Typ baterie',
        'budgetRange' => 'Rozpočtové rozpětí',
        'financingInterest' => 'Zájem o financování',
        
        // Step 6 - Časové preference
        'installationTimeframe' => 'Preferovaný termín instalace',
        'urgency' => 'Naléhavost projektu',
        'availabilityWeekdays' => 'Dostupnost v pracovní dny',
        'availabilityWeekends' => 'Dostupnost o víkendech',
        
        // Step 7 - Dodatečné informace
        'additionalInfo' => 'Dodatečné informace',
        'questions' => 'Dotazy nebo požadavky',
        'foundUsHow' => 'Jak jste se o nás dozvěděli',
        'referralSource' => 'Zdroj doporučení',
        
        // Step 8 - Souhlas
        'gdprConsent' => 'Souhlas se zpracováním osobních údajů',
        'newsletterConsent' => 'Souhlas s odebíráním newsletteru',
        'marketingConsent' => 'Souhlas s marketingovými sděleními',
        'dataRetention' => 'Doba uchovávání dat',
        'thirdPartySharing' => 'Sdílení dat s třetími stranami'
    ];
    return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
}

function getStatusClass($status) {
    switch ($status) {
        case 'draft': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'submitted': return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'confirmed': return 'bg-green-100 text-green-800 border-green-200';
        case 'completed': return 'bg-green-100 text-green-800 border-green-200';
        case 'processing': return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'cancelled': return 'bg-red-100 text-red-800 border-red-200';
        default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

function getStatusLabel($status) {
    switch ($status) {
        case 'draft': return 'Rozpracováno';
        case 'submitted': return 'Odesláno';
        case 'confirmed': return 'Potvrzeno';
        case 'completed': return 'Dokončeno';
        case 'processing': return 'Zpracovává se';
        case 'cancelled': return 'Zrušeno';
        default: return 'Neznámý stav';
    }
}

function formatValue($key, $value) {
    if (is_array($value)) {
        return implode(', ', array_map('htmlspecialchars', $value));
    }
    
    if (empty($value)) {
        return '<span class="text-gray-400 italic">Neuvedeno</span>';
    }
    
    // Speciální formátování pro konkrétní pole
    switch ($key) {
        case 'budgetRange':
            return '<span class="font-semibold text-green-600">' . number_format($value) . ' Kč</span>';
        case 'roofArea':
            return '<span class="font-semibold">' . $value . ' m²</span>';
        case 'currentConsumption':
            return '<span class="font-semibold text-blue-600">' . number_format($value) . ' kWh/rok</span>';
        case 'electricityBill':
            return '<span class="font-semibold text-red-600">' . number_format($value) . ' Kč/měsíc</span>';
        case 'batteryCapacity':
            return '<span class="font-semibold text-purple-600">' . $value . ' kWh</span>';
        case 'gdprConsent':
        case 'newsletterConsent':
        case 'marketingConsent':
            return $value ? '<span class="text-green-600 font-semibold">✓ Ano</span>' : '<span class="text-red-600 font-semibold">✗ Ne</span>';
        case 'phone':
        case 'alternativePhone':
            return '<a href="tel:' . htmlspecialchars($value) . '" class="text-blue-600 hover:underline font-medium">' . 
                   preg_replace('/(\d{3})(\d{3})(\d{3})/', '$1 $2 $3', htmlspecialchars($value)) . '</a>';
        case 'email':
            return '<a href="mailto:' . htmlspecialchars($value) . '" class="text-blue-600 hover:underline font-medium">' . 
                   htmlspecialchars($value) . '</a>';
        case 'urgency':
            $urgencyColors = [
                'low' => 'text-green-600',
                'medium' => 'text-yellow-600', 
                'high' => 'text-red-600',
                'urgent' => 'text-red-800 font-bold'
            ];
            $color = $urgencyColors[$value] ?? 'text-gray-600';
            return '<span class="' . $color . '">' . htmlspecialchars($value) . '</span>';
        case 'companyDetails':
            return formatCompanyDetails($value);
        default:
            return htmlspecialchars($value);
    }
}

function formatCompanyDetails($details) {
    if (empty($details) || !is_array($details)) {
        return '<span class="text-gray-400 italic">Dodatečné informace nedostupné</span>';
    }
    
    $html = '<div class="bg-gray-50 p-4 rounded-lg mt-2">';
    $html .= '<h4 class="font-semibold text-gray-800 mb-3 flex items-center">';
    $html .= '<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">';
    $html .= '<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-6a1 1 0 00-1-1H9a1 1 0 00-1 1v6a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"></path></svg>';
    $html .= 'Rozšířené informace o společnosti</h4>';
    
    $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
    
    // Levý sloupec
    $html .= '<div class="space-y-2">';
    
    if (!empty($details['legal_form'])) {
        $html .= '<div><span class="text-gray-600 text-sm">Právní forma:</span> <strong>' . htmlspecialchars($details['legal_form']) . '</strong></div>';
    }
    
    if (!empty($details['estab_date'])) {
        $date = date('d.m.Y', strtotime($details['estab_date']));
        $html .= '<div><span class="text-gray-600 text-sm">Založena:</span> <strong>' . $date . '</strong></div>';
    }
    
    if (!empty($details['years_in_business'])) {
        $html .= '<div><span class="text-gray-600 text-sm">Doba podnikání:</span> <strong>' . $details['years_in_business'] . ' let</strong></div>';
    }
    
    if (isset($details['is_vatpayer'])) {
        $vatStatus = $details['is_vatpayer'] ? 
            '<span class="text-green-600 font-semibold">✓ Ano</span>' : 
            '<span class="text-gray-600">Ne</span>';
        $html .= '<div><span class="text-gray-600 text-sm">Plátce DPH:</span> ' . $vatStatus . '</div>';
    }
    
    if (!empty($details['databox_id'])) {
        $html .= '<div><span class="text-gray-600 text-sm">Datová schránka:</span> <code class="bg-gray-200 px-1 rounded text-sm">' . htmlspecialchars($details['databox_id']) . '</code></div>';
    }
    
    $html .= '</div>';
    
    // Pravý sloupec  
    $html .= '<div class="space-y-2">';
    
    if (!empty($details['status'])) {
        $statusColor = strpos($details['status'], 'bez omezení') !== false ? 'text-green-600' : 'text-yellow-600';
        $html .= '<div><span class="text-gray-600 text-sm">Stav:</span> <span class="' . $statusColor . ' font-medium">' . htmlspecialchars($details['status']) . '</span></div>';
    }
    
    if (!empty($details['industry'])) {
        $html .= '<div><span class="text-gray-600 text-sm">Obor:</span> <strong>' . htmlspecialchars($details['industry']) . '</strong></div>';
    }
    
    if (!empty($details['magnitude'])) {
        $html .= '<div><span class="text-gray-600 text-sm">Velikost:</span> <strong>' . htmlspecialchars($details['magnitude']) . '</strong></div>';
    }
    
    if (!empty($details['turnover'])) {
        $html .= '<div><span class="text-gray-600 text-sm">Obrat:</span> <strong>' . htmlspecialchars($details['turnover']) . '</strong></div>';
    }
    
    if (!empty($details['court'])) {
        $html .= '<div><span class="text-gray-600 text-sm">Soud:</span> <strong>' . htmlspecialchars($details['court']) . '</strong>';
        if (!empty($details['court_file'])) {
            $html .= '<br><span class="text-xs text-gray-500">Sp. zn.: ' . htmlspecialchars($details['court_file']) . '</span>';
        }
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

function organizeDataBySteps($decoded_data) {
    // Mapování polí ke krokům
    $stepMapping = [
        'step1' => [
            'title' => '🔍 Základní informace o společnosti',
            'fields' => ['companyName', 'contactPerson', 'customerType', 'ico', 'dic', 'email', 'phone', 'address', 'companyAddress', 'sameAsCompanyAddress', 'companyDetails']
        ],
        'step2' => [
            'title' => '⚡ Technické údaje objektu',
            'fields' => ['hasFveVte', 'interestedInFveVte', 'hasTransformer', 'circuitBreakerType', 'sharesElectricity', 'receivesSharedElectricity', 'currentPowerOutput', 'yearlyConsumption', 'monthlyElectricityBill']
        ],
        'step3' => [
            'title' => '🏠 Informace o objektu',
            'fields' => ['roofType', 'roofCondition', 'roofAge', 'roofArea', 'roofOrientation', 'roofAngle', 'shadingIssues', 'accessToRoof', 'installationSurface']
        ],
        'step4' => [
            'title' => '📊 Energetické potřeby',
            'fields' => ['heatingType', 'coolingType', 'hotWaterHeating', 'hasElectricVehicle', 'planningElectricVehicle', 'hasSwimmingPool', 'hasHeatPump']
        ],
        'step5' => [
            'title' => '🔋 Preference akumulace',
            'fields' => ['batteryInterest', 'batteryCapacity', 'batteryType', 'backupPower', 'gridIndependence']
        ],
        'step6' => [
            'title' => '💰 Rozpočet a financování',
            'fields' => ['budgetRange', 'financingInterest', 'subsidyInterest', 'paybackPeriod', 'roi']
        ],
        'step7' => [
            'title' => '� Časové preference',
            'fields' => ['installationTimeframe', 'urgency', 'availabilityWeekdays', 'availabilityWeekends', 'preferredContactTime']
        ],
        'step8' => [
            'title' => '✅ Dodatečné informace a souhlas',
            'fields' => ['additionalInfo', 'questions', 'foundUsHow', 'referralSource', 'gdprConsent', 'newsletterConsent', 'marketingConsent']
        ]
    ];

    $organizedData = [];
    
    // Inicializuj všechny kroky
    foreach ($stepMapping as $stepKey => $stepInfo) {
        $organizedData[$stepKey] = [
            'title' => $stepInfo['title'],
            'data' => []
        ];
    }
    
    // Prochází všechna data a přiřaď je ke správným krokům
    if (is_array($decoded_data)) {
        foreach ($decoded_data as $field => $value) {
            $assigned = false;
            
            // Najdi krok pro toto pole
            foreach ($stepMapping as $stepKey => $stepInfo) {
                if (in_array($field, $stepInfo['fields'])) {
                    $organizedData[$stepKey]['data'][$field] = $value;
                    $assigned = true;
                    break;
                }
            }
            
            // Pokud pole není mapované, přidej ho do step1
            if (!$assigned && !empty($value)) {
                $organizedData['step1']['data'][$field] = $value;
            }
        }
    }
    
    return $organizedData;
}

function getStepIcon($step) {
    $icons = [
        'step1' => '🏢',
        'step2' => '⚡',
        'step3' => '🏠',
        'step4' => '📊',
        'step5' => '🔋',
        'step6' => '💰',
        'step7' => '📅',
        'step8' => '✅'
    ];
    return $icons[$step] ?? '📄';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail formuláře #<?= htmlspecialchars($form_id) ?> - Electree Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .step-card {
            transition: all 0.3s ease;
        }
        .step-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .print-hidden { display: none; }
        @media print {
            .print-hidden { display: block !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen">
    <div class="min-h-screen py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Header -->
            <div class="bg-white shadow-lg rounded-xl mb-8 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-6 text-white">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-bold">📋 Detail formuláře</h1>
                            <p class="text-blue-100 mt-2 text-lg">ID: #<?= htmlspecialchars($form_id) ?></p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="px-4 py-2 text-sm font-semibold rounded-full border-2 <?= getStatusClass($form_data['status']) ?>">
                                <?= getStatusLabel($form_data['status']) ?>
                            </span>
                            <div class="flex space-x-2 no-print">
                                <button onclick="window.print()" class="px-4 py-2 bg-white text-blue-600 rounded-lg hover:bg-blue-50 transition-colors">
                                    <i class="fas fa-print mr-2"></i>Tisk
                                </button>
                                <button onclick="window.close()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-400 transition-colors">
                                    <i class="fas fa-times mr-2"></i>Zavřít
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="px-6 py-4 bg-gray-50 border-b">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-plus text-blue-500 mr-2"></i>
                            <span class="text-gray-600">Vytvořen:</span>
                            <span class="ml-2 font-semibold"><?= date('d.m.Y H:i', strtotime($form_data['created_at'])) ?></span>
                        </div>
                        <?php if ($form_data['updated_at']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-calendar-check text-green-500 mr-2"></i>
                            <span class="text-gray-600">Aktualizován:</span>
                            <span class="ml-2 font-semibold"><?= date('d.m.Y H:i', strtotime($form_data['updated_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex items-center">
                            <i class="fas fa-clock text-orange-500 mr-2"></i>
                            <span class="text-gray-600">Doba vyplnění:</span>
                            <span class="ml-2 font-semibold">
                                <?php 
                                $diff = strtotime($form_data['updated_at'] ?? $form_data['created_at']) - strtotime($form_data['created_at']);
                                echo gmdate("H:i:s", $diff);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Info -->
            <div class="bg-white shadow-lg rounded-xl mb-8 overflow-hidden">
                <div class="bg-gradient-to-r from-green-500 to-teal-500 px-6 py-4 text-white">
                    <h2 class="text-xl font-bold"><i class="fas fa-user mr-2"></i>Informace o žadateli</h2>
                </div>
                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex items-center space-x-4">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                                <?= strtoupper(substr($form_data['user_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($form_data['user_name'] ?? 'Neznámý uživatel') ?></h3>
                                <p class="text-gray-600"><?= htmlspecialchars($form_data['user_email'] ?? 'Email neuvedený') ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">👤 Jméno</label>
                                <p class="text-gray-900 font-semibold"><?= htmlspecialchars($form_data['user_name'] ?? 'Neznámý') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">📧 Email</label>
                                <p class="text-gray-900"><?= formatValue('email', $form_data['user_email'] ?? 'Neznámý') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Data -->
            <?php if ($decoded_data && is_array($decoded_data)): ?>
                <?php $organizedData = organizeDataBySteps($decoded_data); ?>
                <div class="space-y-6">
                    <?php 
                    $stepCounter = 0;
                    foreach ($organizedData as $stepKey => $stepInfo): 
                        $stepCounter++;
                        // Přeskač kroky bez dat
                        if (empty($stepInfo['data'])) continue;
                    ?>
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden step-card">
                            <div class="bg-gradient-to-r from-purple-500 to-pink-500 px-6 py-4 text-white">
                                <h3 class="text-xl font-bold flex items-center">
                                    <span class="text-2xl mr-3"><?= getStepIcon($stepKey) ?></span>
                                    <?= $stepInfo['title'] ?>
                                    <span class="ml-auto text-sm bg-white bg-opacity-20 px-3 py-1 rounded-full">
                                        Krok <?= $stepCounter ?>/8
                                    </span>
                                </h3>
                            </div>
                            <div class="px-6 py-6">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <?php foreach ($stepInfo['data'] as $key => $value): ?>
                                        <?php if (!empty($value)): ?>
                                        <div class="bg-gray-50 rounded-lg p-4 border-l-4 border-blue-400">
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                                <?= getFieldLabel($key) ?>
                                            </label>
                                            <div class="text-gray-900">
                                                <?= formatValue($key, $value) ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white shadow-lg rounded-xl p-8 text-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Žádná data formuláře</h3>
                    <p class="text-gray-600">Formulář neobsahuje platná data nebo došlo k chybě při jejich načítání.</p>
                </div>
            <?php endif; ?>

            <!-- Summary -->
            <div class="bg-white shadow-lg rounded-xl mt-8 overflow-hidden no-print">
                <div class="bg-gradient-to-r from-indigo-500 to-blue-500 px-6 py-4 text-white">
                    <h3 class="text-xl font-bold"><i class="fas fa-chart-bar mr-2"></i>Shrnutí formuláře</h3>
                </div>
                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">
                                <?php 
                                $completedSteps = 0;
                                if (isset($organizedData)) {
                                    foreach ($organizedData as $stepInfo) {
                                        if (!empty($stepInfo['data'])) {
                                            $completedSteps++;
                                        }
                                    }
                                }
                                echo $completedSteps;
                                ?>
                            </div>
                            <div class="text-sm text-gray-600">Vyplněných kroků</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">
                                <?= round(($completedSteps / 8) * 100) ?>%
                            </div>
                            <div class="text-sm text-gray-600">Dokončenost</div>
                        </div>
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">
                                <?php 
                                $totalFields = 0;
                                if (isset($organizedData)) {
                                    foreach ($organizedData as $stepInfo) {
                                        foreach ($stepInfo['data'] as $value) {
                                            if (!empty($value)) {
                                                $totalFields++;
                                            }
                                        }
                                    }
                                }
                                echo $totalFields;
                                ?>
                            </div>
                            <div class="text-sm text-gray-600">Vyplněných polí</div>
                        </div>
                        <div class="text-center p-4 bg-orange-50 rounded-lg">
                            <div class="text-2xl font-bold text-orange-600">
                                <?= getStatusLabel($form_data['status']) ?>
                            </div>
                            <div class="text-sm text-gray-600">Současný stav</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Auto-refresh page every 30 seconds if form is being processed
        <?php if ($form_data['status'] === 'processing'): ?>
        setTimeout(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>
        
        // Print optimization
        window.addEventListener('beforeprint', function() {
            document.querySelectorAll('.step-card').forEach(card => {
                card.style.breakInside = 'avoid';
            });
        });
    </script>
</body>
</html>
