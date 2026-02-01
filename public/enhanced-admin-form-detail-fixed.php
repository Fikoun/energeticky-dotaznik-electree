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

// Pomocné funkce pro rendering
function getAllFieldsForStep($step_number) {
    $stepFields = [
        1 => [
            'companyName', 'ico', 'dic', 'contactPerson', 'email', 'phone', 'address', 
            'companyAddress', 'sameAsCompanyAddress', 'customerType', 'additionalContacts', 
            'companyDetails'
        ],
        2 => [
            'hasFveVte', 'fveVtePower', 'accumulationPercentage', 'interestedInFveVte', 
            'interestedInInstallationProcessing', 'hasTransformer', 'transformerPower', 
            'transformerVoltage', 'coolingType', 'transformerYear', 'transformerType', 
            'transformerCurrent', 'circuitBreakerType', 'customCircuitBreaker', 
            'sharesElectricity', 'electricityShared', 'receivesSharedElectricity', 
            'electricityReceived', 'mainCircuitBreaker', 'reservedPower'
        ],
        3 => [
            'monthlyConsumption', 'monthlyMaxConsumption', 'significantConsumption', 
            'distributionTerritory', 'cezTerritory', 'edsTerritory', 'preTerritory', 
            'ldsName', 'ldsOwner', 'ldsNotes', 'measurementType', 'measurementTypeOther', 
            'weekdayStart', 'weekdayEnd', 'weekdayConsumption', 'weekendStart', 
            'weekendEnd', 'weekendConsumption', 'weekdayPattern', 'weekendPattern'
        ],
        4 => [
            'yearlyConsumption', 'dailyAverageConsumption', 'maxConsumption', 
            'minConsumption', 'hasCriticalConsumption', 'criticalConsumptionDescription', 
            'hasDistributionCurves', 'distributionCurvesFile', 'energyAccumulation', 
            'energyAccumulationAmount', 'batteryCycles', 'requiresBackup', 
            'backupDuration', 'backupDescription', 'backupDurationHours'
        ],
        5 => [
            'goals', 'goalDetails', 'priority1', 'priority2', 'priority3', 
            'otherPurposeDescription', 'priceOptimization', 'hasElectricityProblems', 
            'electricityProblemsDetails', 'hasEnergyAudit', 'energyAuditDetails', 
            'hasOwnEnergySource', 'ownEnergySourceDetails', 'interestedInElectromobility', 
            'reservedOutput'
        ],
        6 => [
            'siteDescription', 'hasOutdoorSpace', 'outdoorSpaceSize', 'hasIndoorSpace', 
            'indoorSpaceType', 'indoorSpaceSize', 'accessibility', 'accessibilityLimitations', 
            'hasProjectDocumentation', 'documentationTypes', 'projectDocumentationFiles', 
            'infrastructureNotes', 'sitePhotos', 'visualizations'
        ],
        7 => [
            'gridConnectionPlanned', 'powerIncreaseRequested', 'requestedPowerIncrease', 
            'requestedOutputIncrease', 'connectionContractFile', 'connectionApplicationFile', 
            'connectionApplicationBy', 'willingToSignPowerOfAttorney', 'hasEnergeticSpecialist', 
            'specialistName', 'specialistPosition', 'specialistEmail', 'specialistPhone', 
            'legislativeNotes', 'proposedSteps'
        ],
        8 => [
            'energyPricing', 'currentEnergyPrice', 'billingMethod', 'billingDocuments', 
            'electricitySharing', 'monthlySharedElectricity', 'monthlyReceivedElectricity', 
            'hasGasConsumption', 'gasConsumption', 'hasGas', 'hotWaterConsumption', 
            'steamConsumption', 'otherConsumption', 'gasUsage', 'gasBill', 
            'hasCogeneration', 'cogenerationDetails', 'cogenerationPhotos', 
            'energyNotes', 'spotSurcharge', 'sharingDetails', 'priceImportance', 
            'agreements', 'additionalNotes'
        ]
    ];
    
    return $stepFields[$step_number] ?? [];
}

function getStatusClass($status) {
    switch($status) {
        case 'draft': return 'bg-yellow-100 text-yellow-800';
        case 'submitted': return 'bg-green-100 text-green-800';
        case 'processing': return 'bg-blue-100 text-blue-800';
        case 'completed': return 'bg-emerald-100 text-emerald-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStatusIcon($status) {
    switch($status) {
        case 'draft': return 'edit';
        case 'submitted': return 'check-circle';
        case 'processing': return 'clock';
        case 'completed': return 'check-double';
        case 'cancelled': return 'times-circle';
        default: return 'question-circle';
    }
}

function getStatusLabel($status) {
    switch($status) {
        case 'draft': return 'Koncept';
        case 'submitted': return 'Odesláno';
        case 'processing': return 'Zpracovává se';
        case 'completed': return 'Dokončeno';
        case 'cancelled': return 'Zrušeno';
        default: return ucfirst($status);
    }
}

function getStepGradient($step) {
    $gradients = [
        1 => 'from-blue-500 to-blue-600',
        2 => 'from-purple-500 to-purple-600', 
        3 => 'from-green-500 to-green-600',
        4 => 'from-yellow-500 to-yellow-600',
        5 => 'from-red-500 to-red-600',
        6 => 'from-pink-500 to-pink-600',
        7 => 'from-indigo-500 to-indigo-600',
        8 => 'from-teal-500 to-teal-600'
    ];
    return $gradients[$step] ?? 'from-gray-500 to-gray-600';
}

function getStepIcon($step) {
    $icons = [
        1 => 'fas fa-building',
        2 => 'fas fa-plug',
        3 => 'fas fa-chart-line',
        4 => 'fas fa-battery-half',
        5 => 'fas fa-target',
        6 => 'fas fa-map-marker-alt',
        7 => 'fas fa-gavel',
        8 => 'fas fa-file-invoice-dollar'
    ];
    return $icons[$step] ?? 'fas fa-circle';
}

function getFieldIcon($field) {
    $icons = [
        'companyName' => 'fas fa-building',
        'ico' => 'fas fa-hashtag',
        'dic' => 'fas fa-hashtag',
        'email' => 'fas fa-envelope',
        'phone' => 'fas fa-phone',
        'address' => 'fas fa-map-marker-alt',
        'hasFveVte' => 'fas fa-solar-panel',
        'hasTransformer' => 'fas fa-bolt',
        'monthlyConsumption' => 'fas fa-chart-bar',
        'goals' => 'fas fa-bullseye'
    ];
    return $icons[$field] ?? 'fas fa-info-circle';
}

function getFieldLabel($field) {
    $labels = [
        // Krok 1
        'companyName' => 'Název společnosti',
        'ico' => 'IČO',
        'dic' => 'DIČ',
        'contactPerson' => 'Kontaktní osoba',
        'email' => 'E-mail',
        'phone' => 'Telefon',
        'address' => 'Adresa',
        'companyAddress' => 'Adresa společnosti',
        'sameAsCompanyAddress' => 'Stejná jako adresa společnosti',
        'customerType' => 'Typ zákazníka',
        'additionalContacts' => 'Další kontakty',
        'companyDetails' => 'Detaily společnosti',
        
        // Krok 2
        'hasFveVte' => 'Má FVE/VTE',
        'fveVtePower' => 'Výkon FVE/VTE (kW)',
        'accumulationPercentage' => 'Procento akumulace (%)',
        'hasTransformer' => 'Má transformátor',
        'transformerPower' => 'Výkon transformátoru (kVA)',
        'transformerVoltage' => 'Napětí transformátoru (kV)',
        'coolingType' => 'Typ chlazení',
        'circuitBreakerType' => 'Typ hlavního jističe',
        'sharesElectricity' => 'Sdílí elektřinu',
        'electricityShared' => 'Sdílená elektřina (kWh)',
        'mainCircuitBreaker' => 'Hlavní jistič (A)',
        'reservedPower' => 'Rezervovaný výkon (kW)',
        
        // Krok 3
        'monthlyConsumption' => 'Měsíční spotřeba (kWh)',
        'monthlyMaxConsumption' => 'Maximální měsíční spotřeba (kWh)',
        'distributionTerritory' => 'Distribuční území',
        'measurementType' => 'Typ měření',
        'weekdayStart' => 'Začátek pracovního dne',
        'weekdayEnd' => 'Konec pracovního dne',
        'weekendStart' => 'Začátek víkendu',
        'weekendEnd' => 'Konec víkendu',
        'weekdayPattern' => 'Vzorec pracovních dnů',
        'weekendPattern' => 'Vzorec víkendu',
        
        // Krok 4
        'yearlyConsumption' => 'Roční spotřeba (kWh)',
        'dailyAverageConsumption' => 'Průměrná denní spotřeba (kWh)',
        'maxConsumption' => 'Maximální spotřeba (kW)',
        'hasCriticalConsumption' => 'Má kritickou spotřebu',
        'criticalConsumptionDescription' => 'Popis kritické spotřeby',
        'energyAccumulation' => 'Akumulace energie',
        'batteryCycles' => 'Cykly baterie',
        'requiresBackup' => 'Vyžaduje záložní napájení',
        'backupDescription' => 'Popis záložního napájení',
        
        // Krok 5
        'goals' => 'Cíle projektu',
        'goalDetails' => 'Detaily cílů',
        'priority1' => 'Priorita 1',
        'priority2' => 'Priorita 2',
        'priority3' => 'Priorita 3',
        'priceOptimization' => 'Optimalizace ceny',
        'hasElectricityProblems' => 'Problémy s elektřinou',
        'hasEnergyAudit' => 'Má energetický audit',
        'hasOwnEnergySource' => 'Má vlastní zdroj energie',
        'interestedInElectromobility' => 'Zájem o elektromobilitu',
        
        // Krok 6
        'siteDescription' => 'Popis místa',
        'hasOutdoorSpace' => 'Má venkovní prostor',
        'hasIndoorSpace' => 'Má vnitřní prostor',
        'accessibility' => 'Přístupnost',
        'hasProjectDocumentation' => 'Má projektovou dokumentaci',
        'infrastructureNotes' => 'Poznámky k infrastruktuře',
        
        // Krok 7
        'gridConnectionPlanned' => 'Plánované připojení k síti',
        'powerIncreaseRequested' => 'Požadavek na zvýšení výkonu',
        'hasEnergeticSpecialist' => 'Má energetického specialistu',
        'specialistName' => 'Jméno specialisty',
        'legislativeNotes' => 'Legislativní poznámky',
        
        // Krok 8
        'energyPricing' => 'Ceníková struktura',
        'currentEnergyPrice' => 'Současná cena energie (Kč/kWh)',
        'billingMethod' => 'Způsob fakturace',
        'hasGasConsumption' => 'Má spotřebu plynu',
        'gasConsumption' => 'Spotřeba plynu (m³)',
        'hasCogeneration' => 'Má kogeneraci',
        'cogenerationDetails' => 'Detaily kogenerace',
        'energyNotes' => 'Energetické poznámky',
        'priceImportance' => 'Důležitost ceny (1-10)',
        'agreements' => 'Souhlasy',
        'additionalNotes' => 'Další poznámky'
    ];
    
    return $labels[$field] ?? ucfirst(str_replace(['_'], [' '], $field));
}

function formatFieldValue($field, $value) {
    if (empty($value) && $value !== '0' && $value !== 0) {
        return '<span class="text-gray-500 italic">Neuvedeno</span>';
    }
    
    if (is_array($value)) {
        if (count($value) == 0) {
            return '<span class="text-gray-500 italic">Neuvedeno</span>';
        }
        
        // Specifické formátování pro různé typy polí
        if ($field === 'customerType' || $field === 'goals' || $field === 'documentationTypes' || $field === 'gasUsage' || $field === 'agreements') {
            $result = [];
            foreach ($value as $key => $val) {
                if ($val === true || $val === 'true' || $val === 1) {
                    $result[] = ucfirst(str_replace(['_'], [' '], $key));
                }
            }
            return !empty($result) ? implode(', ', $result) : '<span class="text-gray-500 italic">Neuvedeno</span>';
        }
        
        if ($field === 'additionalContacts') {
            $output = '';
            foreach ($value as $index => $contact) {
                if (is_array($contact)) {
                    $output .= '<div class="mb-2 p-2 bg-gray-50 rounded border-l-4 border-blue-200">';
                    foreach ($contact as $key => $val) {
                        if (!empty($val)) {
                            $label = getFieldLabel($key);
                            $output .= "<div><strong>$label:</strong> " . htmlspecialchars($val) . "</div>";
                        }
                    }
                    $output .= '</div>';
                }
            }
            return !empty($output) ? $output : '<span class="text-gray-500 italic">Neuvedeno</span>';
        }
        
        if ($field === 'weekdayPattern' || $field === 'weekendPattern') {
            $output = '<div class="text-sm space-y-1">';
            foreach ($value as $key => $val) {
                if (!empty($val)) {
                    $label = getFieldLabel($key);
                    $output .= "<div><strong>$label:</strong> " . htmlspecialchars($val) . "</div>";
                }
            }
            $output .= '</div>';
            return $output;
        }
        
        // Pro ostatní pole zobraz jako JSON
        return '<pre class="text-xs bg-gray-50 p-2 rounded">' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
    }
    
    if (is_bool($value)) {
        return $value ? '<span class="text-green-600 font-medium">Ano</span>' : '<span class="text-red-600 font-medium">Ne</span>';
    }
    
    if (is_numeric($value)) {
        return '<span class="font-mono">' . number_format($value, 0, ',', ' ') . '</span>';
    }
    
    return htmlspecialchars($value);
}

// Načtení dat formuláře
$form_data = null;

try {
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
    echo "Chyba při načítání dat: " . $e->getMessage();
    exit();
}

if (!$form_data) {
    echo "Formulář nenalezen";
    exit();
}

// Dekódování dat formuláře
$decoded_data = json_decode($form_data['form_data'], true);
$step_notes = $decoded_data['stepNotes'] ?? [];

// Názvy kroků
$step_names = [
    1 => 'Identifikační údaje zákazníka',
    2 => 'Parametry odběrného místa', 
    3 => 'Spotřeba a vzorce',
    4 => 'Analýza spotřeby',
    5 => 'Cíle a optimalizace',
    6 => 'Místo a infrastruktura',
    7 => 'Připojení a legislativa',
    8 => 'Fakturace a energie'
];
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
        .data-card {
            transition: all 0.3s ease;
        }
        .data-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .scrollable-section {
            max-height: 70vh;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <ol class="flex items-center space-x-2 text-sm">
                        <li><a href="/admin" class="text-blue-600 hover:text-blue-800">Admin</a></li>
                        <li class="text-gray-400">/</li>
                        <li><a href="/admin/forms" class="text-blue-600 hover:text-blue-800">Formuláře</a></li>
                        <li class="text-gray-400">/</li>
                        <li class="text-gray-900">#<?= htmlspecialchars($form_id) ?></li>
                    </ol>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-print mr-2"></i>Tisk
                    </button>
                    <button onclick="window.location.href='/admin/forms'" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Zpět
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-xl mb-8 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-8 py-6">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center mb-3">
                            <i class="fas fa-file-alt text-3xl mr-4 opacity-90 text-white"></i>
                            <h1 class="text-3xl font-bold text-white">Formulář #<?= htmlspecialchars($form_id) ?></h1>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-white/90">
                            <div class="flex items-center">
                                <i class="far fa-calendar-plus mr-2"></i>
                                <span>Vytvořen: <?= date('d.m.Y H:i', strtotime($form_data['created_at'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-user mr-2"></i>
                                <span><?= htmlspecialchars($form_data['user_name'] ?? 'Neznámý') ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-building mr-2"></i>
                                <span><?= htmlspecialchars($form_data['company_name'] ?? 'Neznámá společnost') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="px-4 py-2 text-sm font-semibold rounded-full <?= getStatusClass($form_data['status']) ?>">
                            <i class="fas fa-<?= getStatusIcon($form_data['status']) ?> mr-2"></i>
                            <?= getStatusLabel($form_data['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulářová data -->
        <div class="bg-white rounded-2xl shadow-xl mb-8">
            <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-clipboard-list mr-3 text-2xl"></i>
                    Vyplněné údaje formuláře
                </h2>
            </div>
            
            <div class="scrollable-section">
                <div class="px-6 py-6">
                    <?php if ($decoded_data && is_array($decoded_data)): ?>
                        <div class="space-y-6">
                            <?php for ($step_number = 1; $step_number <= 8; $step_number++): 
                                $stepFields = getAllFieldsForStep($step_number);
                                if (empty($stepFields)) continue;
                            ?>
                                <div class="border border-gray-200 rounded-xl overflow-hidden data-card">
                                    <!-- Step Header -->
                                    <div class="bg-gradient-to-r <?= getStepGradient($step_number) ?> px-4 py-3">
                                        <h3 class="text-lg font-semibold text-white flex items-center">
                                            <div class="bg-white/20 backdrop-blur-sm rounded-lg px-2 py-1 mr-3">
                                                <span class="text-sm font-black"><?= $step_number ?></span>
                                            </div>
                                            <i class="<?= getStepIcon($step_number) ?> mr-2 text-lg"></i>
                                            <?= htmlspecialchars($step_names[$step_number] ?? "Krok $step_number") ?>
                                        </h3>
                                    </div>
                                    
                                    <!-- Step Content -->
                                    <div class="px-4 py-4">
                                        <div class="grid grid-cols-1 gap-3">
                                            <?php foreach ($stepFields as $field_key): ?>
                                                <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                                    <div class="flex items-start space-x-2">
                                                        <div class="bg-blue-100 p-1.5 rounded-lg flex-shrink-0">
                                                            <i class="<?= getFieldIcon($field_key) ?> text-blue-600 text-sm"></i>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                                                <?= getFieldLabel($field_key) ?>
                                                            </label>
                                                            <div class="text-gray-900 text-sm">
                                                                <?= formatFieldValue($field_key, $decoded_data[$field_key] ?? null) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <!-- Poznámka ke kroku -->
                                        <?php if (isset($step_notes[$step_number]) && !empty($step_notes[$step_number])): ?>
                                            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                                <h4 class="text-sm font-medium text-blue-900 mb-2 flex items-center">
                                                    <i class="fas fa-sticky-note text-blue-600 mr-2"></i>
                                                    Poznámka ke kroku
                                                </h4>
                                                <p class="text-sm text-blue-800"><?= nl2br(htmlspecialchars($step_notes[$step_number])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-exclamation-triangle text-4xl mb-4 opacity-50"></i>
                            <h3 class="text-lg font-semibold mb-2">Nelze načíst data formuláře</h3>
                            <p class="text-sm">Data formuláře nejsou dostupná nebo jsou poškozená.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Funkcionalita pro smooth scrolling a interakce
        document.addEventListener('DOMContentLoaded', function() {
            // Hover efekty pro karty
            const cards = document.querySelectorAll('.data-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>
