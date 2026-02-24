<?php
header('Content-Type: text/html; charset=utf-8');

// Database configuration - use centralized config
require_once __DIR__ . '/../config/database.php';

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

        // Send notification email to admin
        sendAdminNotification($form, $formData);

        // Raynet is not triggered here; default to manual processing flag
        $raynetSuccess = false;

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

// ============================================================================
// HELPER FUNCTIONS (copied from form-detail.php for consistency)
// ============================================================================

// Organizace dat podle kroků
function organizeDataBySteps($decoded_data) {
    $steps = [
        1 => ['companyName', 'ico', 'dic', 'contactPerson', 'phone', 'email', 'address', 'companyAddress', 'sameAsCompanyAddress', 'customerType', 'additionalContacts', 'companyDetails'],
        2 => ['hasFveVte', 'fveVtePower', 'accumulationPercentage', 'interestedInFveVte', 'interestedInInstallationProcessing', 'interestedInElectromobility', 'hasTransformer', 'transformerPower', 'transformerVoltage', 'coolingType', 'transformerYear', 'transformerType', 'transformerCurrent', 'circuitBreakerType', 'customCircuitBreaker', 'sharesElectricity', 'electricityShared', 'receivesSharedElectricity', 'electricityReceived', 'mainCircuitBreaker', 'reservedPower', 'reservedOutput', 'monthlySharedElectricity', 'monthlyReceivedElectricity'],
        3 => ['monthlyConsumption', 'monthlyMaxConsumption', 'significantConsumption', 'distributionTerritory', 'cezTerritory', 'edsTerritory', 'preTerritory', 'ldsName', 'ldsOwner', 'ldsNotes', 'measurementType', 'measurementTypeOther', 'yearlyConsumption', 'dailyAverageConsumption', 'maxConsumption', 'minConsumption', 'hasDistributionCurves', 'distributionCurvesDetails', 'distributionCurvesFile', 'hasCriticalConsumption', 'criticalConsumption', 'criticalConsumptionDescription', 'weekdayStart', 'weekdayEnd', 'weekdayConsumption', 'weekendStart', 'weekendEnd', 'weekendConsumption', 'weekdayPattern', 'weekendPattern'],
        4 => ['batteryCapacity', 'batteryType', 'energyAccumulation', 'energyAccumulationAmount', 'energyAccumulationValue', 'batteryCycles', 'requiresBackup', 'backupDescription', 'backupDuration', 'backupDurationHours', 'priceOptimization', 'energyPricing', 'hasElectricityProblems', 'electricityProblemsDetails', 'hasEnergyAudit', 'energyAuditDate', 'energyAuditDetails', 'auditDocuments', 'hasOwnEnergySource', 'ownEnergySourceDetails', 'canProvideLoadSchema', 'loadSchemaDetails', 'priceImportance', 'energyNotes'],
        5 => ['goals', 'goalDetails', 'priority1', 'priority2', 'priority3', 'otherPurposeDescription'],
        6 => ['hasOutdoorSpace', 'outdoorSpaceDetails', 'outdoorSpaceSize', 'hasIndoorSpace', 'indoorSpaceDetails', 'indoorSpaceType', 'indoorSpaceSize', 'accessibility', 'accessibilityLimitations', 'hasProjectDocumentation', 'documentationTypes', 'projectDocuments', 'projectDocumentationFiles', 'sitePlan', 'electricalPlan', 'buildingPlan', 'otherDocumentation', 'roofType', 'roofOrientation', 'siteDescription', 'sitePhotos', 'hasPhotos', 'photos', 'hasVisualization', 'visualization', 'visualizations', 'infrastructureNotes', 'solarInstallation', 'plannedInstallationDate', 'installationLocation', 'installationPreference'],
        7 => ['gridConnectionPlanned', 'powerIncreaseRequested', 'requestedPowerIncrease', 'requestedOutputIncrease', 'connectionApplicationBy', 'connectionApplication', 'hasConnectionApplication', 'connectionContractFile', 'connectionApplicationFile', 'willingToSignPowerOfAttorney', 'hasEnergeticSpecialist', 'specialistPosition', 'specialistName', 'specialistEmail', 'specialistPhone', 'energeticSpecialist', 'energeticSpecialistContact', 'proposedSteps', 'legislativeNotes', 'hasCapacityIncrease', 'capacityIncreaseDetails'],
        8 => ['electricityPriceVT', 'electricityPriceNT', 'distributionPriceVT', 'distributionPriceNT', 'systemServices', 'ote', 'billingFees', 'billingMethod', 'spotSurcharge', 'fixPrice', 'fixPercentage', 'spotPercentage', 'gradualFixPrice', 'gradualSpotSurcharge', 'billingDocuments', 'currentEnergyPrice', 'electricitySharing', 'sharingDetails', 'hasGas', 'hasGasConsumption', 'gasPrice', 'gasConsumption', 'gasUsage', 'heating', 'hotWater', 'hotWaterConsumption', 'technology', 'cooking', 'hasCogeneration', 'cogenerationDetails', 'cogenerationPhotos', 'heatingConsumption', 'coolingConsumption', 'steamConsumption', 'otherConsumption', 'agreements', 'timeline', 'urgency', 'additionalNotes']
    ];
    // Build reverse lookup for quick step assignment
    $field_to_step = [];
    foreach ($steps as $step_num => $fields) {
        foreach ($fields as $field) {
            $field_to_step[$field] = $step_num;
        }
    }

    // Initialize all steps to keep ordering stable
    $organized_data = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => [], 8 => []];

    // Heuristic buckets for any new/unmapped fields so we do not drop data
    $heuristics = [
        3 => '/consumption|distribution|measurement|curve/i',
        4 => '/battery|backup|accumulation|energy|audit|priceOptimization/i',
        5 => '/goal|priority|purpose/i',
        6 => '/site|photo|visual|doc|plan|roof|infrastructure|space|access/i',
        7 => '/grid|connection|power|specialist|proposed|legisl|capacity/i',
        8 => '/price|billing|gas|cogeneration|agreement|timeline|urgency/i'
    ];

    foreach ($decoded_data as $key => $value) {
        // Skip metadata keys that are not user inputs
        if (in_array($key, ['stepNotes', 'uploadedFiles', 'user', 'formId', 'submittedAt', 'isDraft', 'tempFormId', 'status'], true)) {
            continue;
        }

        $target_step = $field_to_step[$key] ?? null;

        if ($target_step === null) {
            foreach ($heuristics as $step_num => $pattern) {
                if (preg_match($pattern, $key)) {
                    $target_step = $step_num;
                    break;
                }
            }
        }

        if ($target_step === null) {
            $target_step = 1; // Fallback bucket so nothing gets dropped
        }

        $organized_data[$target_step][$key] = $value;
    }

    // Remove empty steps but keep keys intact
    return array_filter($organized_data, function($fields) {
        return !empty($fields);
    });
}

// Názvy kroků
$step_names = [
    1 => 'Identifikační údaje zákazníka',
    2 => 'Parametry odběrného místa',
    3 => 'Spotřeba a rozložení',
    4 => 'Analýza spotřeby a akumulace',
    5 => 'Cíle a optimalizace',
    6 => 'Místo realizace a infrastruktura',
    7 => 'Připojení k síti a legislativa',
    8 => 'Energetická fakturace a bilancování'
];

function getStepIcon($step) {
    $icons = [
        1 => 'fas fa-user-circle',
        2 => 'fas fa-bolt',
        3 => 'fas fa-chart-line',
        4 => 'fas fa-battery-half',
        5 => 'fas fa-bullseye',
        6 => 'fas fa-building',
        7 => 'fas fa-plug',
        8 => 'fas fa-file-invoice-dollar'
    ];
    return $icons[$step] ?? 'fas fa-file';
}

function getStepGradient($step) {
    $gradients = [
        1 => 'from-blue-500 to-blue-600',
        2 => 'from-green-500 to-green-600',
        3 => 'from-purple-500 to-purple-600',
        4 => 'from-orange-500 to-orange-600',
        5 => 'from-red-500 to-red-600',
        6 => 'from-indigo-500 to-indigo-600',
        7 => 'from-yellow-500 to-yellow-600',
        8 => 'from-pink-500 to-pink-600'
    ];
    return $gradients[$step] ?? 'from-gray-500 to-gray-600';
}

function getFieldIcon($field) {
    $icons = [
        'companyName' => 'fas fa-building',
        'ico' => 'fas fa-hashtag',
        'dic' => 'fas fa-file-text',
        'contactPerson' => 'fas fa-user',
        'email' => 'fas fa-envelope',
        'phone' => 'fas fa-phone',
        'address' => 'fas fa-map-marker-alt',
        'hasFveVte' => 'fas fa-solar-panel',
        'fveVtePower' => 'fas fa-bolt',
        'hasTransformer' => 'fas fa-plug',
        'mainCircuitBreaker' => 'fas fa-toggle-on',
        'reservedPower' => 'fas fa-battery-full',
        'monthlyConsumption' => 'fas fa-chart-bar',
        'goals' => 'fas fa-target',
        'batteryCapacity' => 'fas fa-battery-half',
    ];
    return $icons[$field] ?? 'fas fa-info-circle';
}

function getFieldLabel($key) {
    $labels = [
        'companyName' => 'Název společnosti / jméno',
        'ico' => 'IČO',
        'dic' => 'DIČ',
        'contactPerson' => 'Kontaktní osoba',
        'email' => 'E-mailová adresa',
        'phone' => 'Telefon',
        'address' => 'Adresa odběrného místa',
        'companyAddress' => 'Adresa sídla firmy',
        'sameAsCompanyAddress' => 'Stejná adresa jako sídlo',
        'customerType' => 'Typ zákazníka',
        'additionalContacts' => 'Dodatečné kontaktní osoby',
        'hasFveVte' => 'Má instalovanou FVE/VTE',
        'fveVtePower' => 'Výkon FVE/VTE (kWp)',
        'accumulationPercentage' => 'Procento akumulace přetoků (%)',
        'interestedInFveVte' => 'Zájem o instalaci FVE',
        'interestedInInstallationProcessing' => 'Zájem o zpracování instalace',
        'interestedInElectromobility' => 'Zájem o elektromobilitu',
        'hasTransformer' => 'Má vlastní trafostanici',
        'transformerPower' => 'Výkon trafostanice (kVA)',
        'transformerVoltage' => 'VN strana napětí',
        'coolingType' => 'Typ chlazení transformátoru',
        'transformerYear' => 'Rok výroby transformátoru',
        'transformerType' => 'Typ transformátoru',
        'transformerCurrent' => 'Proud transformátoru (A)',
        'circuitBreakerType' => 'Typ hlavního jističe',
        'customCircuitBreaker' => 'Vlastní specifikace jističe',
        'sharesElectricity' => 'Sdílí elektřinu s jinými',
        'electricityShared' => 'Množství sdílené elektřiny (kWh/měsíc)',
        'receivesSharedElectricity' => 'Přijímá sdílenou elektřinu',
        'electricityReceived' => 'Množství přijaté elektřiny (kWh/měsíc)',
        'mainCircuitBreaker' => 'Hlavní jistič (A)',
        'reservedPower' => 'Rezervovaný příkon (kW)',
        'reservedOutput' => 'Rezervovaný výkon (kW)',
        'monthlyConsumption' => 'Měsíční spotřeba (MWh)',
        'monthlyMaxConsumption' => 'Měsíční maximum odběru (kW)',
        'significantConsumption' => 'Významné odběry / technologie',
        'distributionTerritory' => 'Distribuční území',
        'cezTerritory' => 'ČEZ Distribuce',
        'edsTerritory' => 'E.ON Distribuce',
        'preTerritory' => 'PRE Distribuce',
        'ldsName' => 'Lokální distribuční soustava - název',
        'ldsOwner' => 'Vlastník LDS',
        'ldsNotes' => 'Poznámky k LDS',
        'measurementType' => 'Typ měření',
        'measurementTypeOther' => 'Jiný typ měření',
        'yearlyConsumption' => 'Roční spotřeba (MWh)',
        'dailyAverageConsumption' => 'Průměrná denní spotřeba (kWh)',
        'maxConsumption' => 'Maximální odběr (kW)',
        'minConsumption' => 'Minimální odběr (kW)',
        'hasDistributionCurves' => 'Má k dispozici odběrové diagramy',
        'distributionCurvesDetails' => 'Detaily odběrových diagramů',
        'distributionCurvesFile' => 'Soubor s odběrovými křivkami',
        'hasCriticalConsumption' => 'Má kritickou spotřebu',
        'criticalConsumption' => 'Popis kritické spotřeby',
        'criticalConsumptionDescription' => 'Popis kritické spotřeby',
        'weekdayStart' => 'Začátek pracovního dne',
        'weekdayEnd' => 'Konec pracovního dne',
        'weekdayConsumption' => 'Spotřeba během pracovního dne (kW)',
        'weekendStart' => 'Začátek víkendu',
        'weekendEnd' => 'Konec víkendu',
        'weekendConsumption' => 'Víkendová spotřeba (kW)',
        'weekdayPattern' => 'Vzorec spotřeby během týdne',
        'weekendPattern' => 'Vzorec víkendové spotřeby',
        'batteryCapacity' => 'Kapacita baterie',
        'batteryType' => 'Typ baterie',
        'energyAccumulation' => 'Množství energie k akumulaci',
        'energyAccumulationAmount' => 'Konkrétní hodnota (kWh)',
        'energyAccumulationValue' => 'Konkrétní hodnota akumulace (kWh)',
        'batteryCycles' => 'Kolikrát denně využít baterii',
        'requiresBackup' => 'Potřeba záložního napájení',
        'backupDescription' => 'Co je potřeba zálohovat',
        'backupDuration' => 'Požadovaná doba zálohy',
        'backupDurationHours' => 'Doba zálohy (hodiny)',
        'priceOptimization' => 'Řízení podle ceny elektřiny',
        'energyPricing' => 'Cenování energie',
        'hasElectricityProblems' => 'Problémy s elektřinou',
        'electricityProblemsDetails' => 'Detaily problémů s elektřinou',
        'hasEnergyAudit' => 'Energetický audit',
        'energyAuditDate' => 'Datum energetického auditu',
        'energyAuditDetails' => 'Detaily energetického auditu',
        'auditDocuments' => 'Dokumenty energetického auditu',
        'hasOwnEnergySource' => 'Vlastní zdroj energie',
        'ownEnergySourceDetails' => 'Detaily vlastního zdroje',
        'canProvideLoadSchema' => 'Může poskytnout schéma zatížení',
        'loadSchemaDetails' => 'Detaily schématu zatížení',
        'priceImportance' => 'Důležitost ceny elektřiny',
        'energyNotes' => 'Poznámky k energii',
        'goals' => 'Hlavní cíle bateriového úložiště',
        'goalDetails' => 'Detaily cílů',
        'priority1' => 'Priorita č. 1',
        'priority2' => 'Priorita č. 2',
        'priority3' => 'Priorita č. 3',
        'otherPurposeDescription' => 'Popis jiného účelu',
        'hasOutdoorSpace' => 'Venkovní prostory',
        'outdoorSpaceDetails' => 'Detaily venkovních prostor',
        'outdoorSpaceSize' => 'Velikost venkovního prostoru',
        'hasIndoorSpace' => 'Vnitřní prostory',
        'indoorSpaceDetails' => 'Detaily vnitřních prostor',
        'indoorSpaceType' => 'Typ vnitřního prostoru',
        'indoorSpaceSize' => 'Velikost vnitřního prostoru',
        'accessibility' => 'Přístupnost lokality',
        'accessibilityLimitations' => 'Omezení přístupnosti',
        'hasProjectDocumentation' => 'Projektová dokumentace',
        'documentationTypes' => 'Typy dostupné dokumentace',
        'projectDocuments' => 'Projektová dokumentace (soubory)',
        'projectDocumentationFiles' => 'Soubory projektové dokumentace',
        'sitePlan' => 'Situační plán areálu',
        'electricalPlan' => 'Elektrická dokumentace',
        'buildingPlan' => 'Půdorysy budov',
        'otherDocumentation' => 'Jiná dokumentace',
        'roofType' => 'Typ střechy',
        'roofOrientation' => 'Orientace střechy',
        'siteDescription' => 'Popis lokality',
        'sitePhotos' => 'Fotografie místa',
        'hasPhotos' => 'Má fotografie',
        'photos' => 'Fotografie',
        'hasVisualization' => 'Má vizualizace',
        'visualization' => 'Vizualizace',
        'visualizations' => 'Vizualizace',
        'infrastructureNotes' => 'Poznámky k infrastruktuře',
        'solarInstallation' => 'Solární instalace',
        'plannedInstallationDate' => 'Plánované datum instalace',
        'installationLocation' => 'Místo instalace',
        'installationPreference' => 'Preference instalace',
        'gridConnectionPlanned' => 'Připojení k DS/ČEPS',
        'powerIncreaseRequested' => 'Navýšení rezervovaného příkonu',
        'requestedPowerIncrease' => 'Požadované navýšení příkonu (kW)',
        'requestedOutputIncrease' => 'Požadované navýšení výkonu (kW)',
        'connectionApplicationBy' => 'Žádost o připojení podá',
        'connectionApplication' => 'Žádost o připojení',
        'hasConnectionApplication' => 'Má žádost o připojení',
        'connectionContractFile' => 'Smlouva o připojení (soubor)',
        'connectionApplicationFile' => 'Žádost o připojení (soubor)',
        'willingToSignPowerOfAttorney' => 'Ochoten podepsat plnou moc',
        'hasEnergeticSpecialist' => 'Energetický specialista',
        'specialistPosition' => 'Pozice specialisty',
        'specialistName' => 'Jméno specialisty',
        'specialistEmail' => 'E-mail specialisty',
        'specialistPhone' => 'Telefon specialisty',
        'energeticSpecialist' => 'Jméno energetického specialisty',
        'energeticSpecialistContact' => 'Kontakt na specialistu',
        'proposedSteps' => 'Navrhované kroky',
        'legislativeNotes' => 'Legislativní poznámky',
        'hasCapacityIncrease' => 'Navýšení kapacity',
        'capacityIncreaseDetails' => 'Detaily navýšení kapacity',
        'electricityPriceVT' => 'Cena elektřiny VT (Kč/kWh)',
        'electricityPriceNT' => 'Cena elektřiny NT (Kč/kWh)',
        'distributionPriceVT' => 'Distribuce VT (Kč/kWh)',
        'distributionPriceNT' => 'Distribuce NT (Kč/kWh)',
        'systemServices' => 'Systémové služby (Kč/kWh)',
        'ote' => 'OTE (Kč/kWh)',
        'billingFees' => 'Poplatky za vyúčtování (Kč/měsíc)',
        'billingMethod' => 'Způsob vyúčtování',
        'spotSurcharge' => 'Přirážka na spot cenu (Kč/MWh)',
        'fixPrice' => 'Fixní cena elektřiny (Kč/kWh)',
        'fixPercentage' => 'Podíl fix (%)',
        'spotPercentage' => 'Podíl spot (%)',
        'gradualFixPrice' => 'Postupná fixní cena (Kč/kWh)',
        'gradualSpotSurcharge' => 'Postupná spot přirážka (Kč/MWh)',
        'billingDocuments' => 'Doklady o vyúčtování',
        'currentEnergyPrice' => 'Současná cena elektřiny (Kč/kWh)',
        'electricitySharing' => 'Sdílení elektřiny',
        'sharingDetails' => 'Detaily sdílení',
        'hasGas' => 'Využití plynu',
        'hasGasConsumption' => 'Spotřeba plynu',
        'gasPrice' => 'Cena plynu (Kč/kWh)',
        'gasConsumption' => 'Spotřeba plynu (kWh/rok)',
        'gasUsage' => 'Použití plynu',
        'heating' => 'Vytápění',
        'hotWater' => 'Ohřev vody',
        'hotWaterConsumption' => 'Spotřeba teplé vody (l/den)',
        'technology' => 'Technologie/výroba',
        'cooking' => 'Vaření',
        'hasCogeneration' => 'Kogenerační jednotka',
        'cogenerationDetails' => 'Detaily kogenerační jednotky',
        'cogenerationPhotos' => 'Fotografie kogenerační jednotky',
        'heatingConsumption' => 'Spotřeba tepla (kWh/rok)',
        'coolingConsumption' => 'Spotřeba chladu (kWh/rok)',
        'steamConsumption' => 'Spotřeba páry (kWh/rok)',
        'otherConsumption' => 'Další spotřeby',
        'agreements' => 'Dohody a smlouvy',
        'timeline' => 'Časový harmonogram',
        'urgency' => 'Naléhavost realizace',
        'additionalNotes' => 'Dodatečné poznámky',
    ];
    
    return $labels[$key] ?? ucfirst(str_replace(['_', 'Type', 'Has', 'Is'], [' ', ' typ', 'Má ', 'Je '], $key));
}

function formatSingleFile($url, $name = null, $type = null, $size = null) {
    if (empty($url)) {
        return '';
    }
    
    $display_name = $name ?: basename($url);
    $file_type = $type ?: '';
    
    $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp']);
    $is_pdf = $extension === 'pdf';
    $is_doc = in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
    
    if (strpos($file_type, 'image/') === 0) {
        $is_image = true;
    } elseif (strpos($file_type, 'application/pdf') !== false) {
        $is_pdf = true;
    }
    
    $full_url = $url;
    if (strpos($url, 'http') !== 0 && strpos($url, '/') !== 0) {
        $full_url = '/uploads/' . $url;
    }
    
    if ($is_image) {
        return '<div style="display: inline-block; margin: 5px;">
                    <a href="' . htmlspecialchars($full_url) . '" target="_blank" style="display: block; text-decoration: none;">
                        <img src="' . htmlspecialchars($full_url) . '" 
                             alt="' . htmlspecialchars($display_name) . '" 
                             style="max-width: 200px; max-height: 200px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer;">
                        <div style="text-align: center; font-size: 12px; color: #666; margin-top: 5px;">' . htmlspecialchars($display_name) . '</div>
                    </a>
                </div>';
    } elseif ($is_pdf) {
        return '<div style="display: flex; align-items: center; padding: 10px; background: #fee; border: 1px solid #fcc; border-radius: 5px; margin: 5px 0;">
                    <i class="fas fa-file-pdf" style="color: #dc3545; font-size: 20px; margin-right: 10px;"></i>
                    <div style="flex: 1;">
                        <a href="' . htmlspecialchars($full_url) . '" target="_blank" style="color: #dc3545; text-decoration: none; font-weight: bold;">' . htmlspecialchars($display_name) . '</a>
                        <div style="font-size: 11px; color: #999;">PDF dokument</div>
                    </div>
                </div>';
    } elseif ($is_doc) {
        return '<div style="display: flex; align-items: center; padding: 10px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px; margin: 5px 0;">
                    <i class="fas fa-file-word" style="color: #0066cc; font-size: 20px; margin-right: 10px;"></i>
                    <div style="flex: 1;">
                        <a href="' . htmlspecialchars($full_url) . '" target="_blank" style="color: #0066cc; text-decoration: none; font-weight: bold;">' . htmlspecialchars($display_name) . '</a>
                        <div style="font-size: 11px; color: #999;">Dokument Office</div>
                    </div>
                </div>';
    } else {
        return '<div style="display: flex; align-items: center; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 5px; margin: 5px 0;">
                    <i class="fas fa-file" style="color: #666; font-size: 20px; margin-right: 10px;"></i>
                    <div style="flex: 1;">
                        <a href="' . htmlspecialchars($full_url) . '" target="_blank" style="color: #333; text-decoration: none; font-weight: bold;">' . htmlspecialchars($display_name) . '</a>
                    </div>
                </div>';
    }
}

function formatFileUploads($key, $value) {
    if (empty($value)) {
        return '<span style="color: #999; font-style: italic;">Žádné soubory</span>';
    }
    
    if (is_string($value)) {
        return formatSingleFile($value);
    }
    
    if (!is_array($value)) {
        return '<span style="color: #999; font-style: italic;">Neplatný formát</span>';
    }
    
    $files_html = '<div style="margin: 10px 0;">';
    $file_count = 0;
    
    foreach ($value as $idx => $file) {
        $file_count++;
        
        if (is_string($file)) {
            $files_html .= formatSingleFile($file);
        } elseif (is_array($file)) {
            $url = $file['url'] ?? $file['path'] ?? $file['name'] ?? '';
            $name = $file['name'] ?? basename($url);
            $type = $file['type'] ?? '';
            $size = $file['size'] ?? null;
            $files_html .= formatSingleFile($url, $name, $type, $size);
        }
    }
    
    $files_html .= '</div>';
    
    if ($file_count === 0) {
        return '<span style="color: #999; font-style: italic;">Žádné soubory</span>';
    }
    
    return '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 10px 0;">
                <div style="font-size: 14px; color: #666; margin-bottom: 10px;"><i class="fas fa-folder-open"></i> ' . $file_count . ' soubor(ů)</div>
                ' . $files_html . '
            </div>';
}

function isAssocArray(array $arr): bool {
    if (empty($arr)) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

// Normalizes a checkbox-group value stored as either:
//   assoc array: {key: 1/true, key2: ""/false, ...}  →  returns selected keys
//   indexed array: ["key1", "key2", "", ...]          →  returns non-empty values
function normalizeCheckboxGroup(array $value): array {
    if (isAssocArray($value)) {
        $selected = [];
        foreach ($value as $k => $v) {
            if (!empty($k) && ($v === 1 || $v === '1' || $v === true || $v === 'true')) {
                $selected[] = (string)$k;
            }
        }
        return $selected;
    }
    return array_values(array_filter($value, fn($v) => $v !== '' && $v !== null && $v !== false));
}

function formatFieldValue($key, $value) {
    if (is_null($value) || $value === '' || $value === false || (is_array($value) && empty($value))) {
        return '<span style="color: #999; font-style: italic;">Nevyplněno</span>';
    }
    
    // File fields
    $file_fields = ['sitePhotos', 'visualizations', 'projectDocumentationFiles', 'distributionCurvesFile', 
                    'billingDocuments', 'cogenerationPhotos', 'auditDocuments', 'photos', 'projectDocuments',
                    'connectionContractFile', 'connectionApplicationFile'];
    if (in_array($key, $file_fields)) {
        return formatFileUploads($key, $value);
    }
    
    // Priority fields
    if ((strpos($key, 'priority') !== false || strpos($key, 'Priority') !== false) && is_string($value) && !empty($value)) {
        $priority_labels = [
            'fve-overflow' => '⚡ Úspora z přetoků z FVE',
            'peak-shaving' => '📊 Posun spotřeby (peak shaving)',
            'backup-power' => '🔋 Záložní napájení',
            'grid-services' => '🔌 Služby pro síť',
            'cost-optimization' => '💰 Optimalizace nákladů na elektřinu',
            'environmental' => '🌿 Ekologický přínos',
            'machine-support' => '⚙️ Podpora výkonu strojů',
        ];
        $priority_text = $priority_labels[$value] ?? $value;
        return '<div style="background: #ffe6cc; padding: 10px; border-radius: 5px; color: #994d00; font-weight: 500;">' . htmlspecialchars($priority_text) . '</div>';
    }
    
    // Weekday pattern
    if ($key === 'weekdayPattern' && is_array($value) && !empty($value)) {
        $pattern_html = '<div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 5px 0;">';
        foreach ($value as $hour => $consumption) {
            $pattern_html .= '<div style="padding: 3px 0; border-bottom: 1px solid #ccc;">
                                <strong>' . htmlspecialchars($hour) . ':00</strong> - ' . htmlspecialchars($consumption) . ' kW
                              </div>';
        }
        $pattern_html .= '</div>';
        return $pattern_html;
    }
    
    // Weekend pattern
    if ($key === 'weekendPattern' && is_array($value) && !empty($value)) {
        $pattern_html = '<div style="background: #e8f5e8; padding: 10px; border-radius: 5px; margin: 5px 0;">';
        foreach ($value as $hour => $consumption) {
            $pattern_html .= '<div style="padding: 3px 0; border-bottom: 1px solid #ccc;">
                                <strong>' . htmlspecialchars($hour) . ':00</strong> - ' . htmlspecialchars($consumption) . ' kW
                              </div>';
        }
        $pattern_html .= '</div>';
        return $pattern_html;
    }
    
    // customerType checkbox group
    if ($key === 'customerType' && is_array($value)) {
        $ct_labels = [
            'industrial'  => '🏭 Průmysl',
            'commercial'  => '🏢 Komerční objekt',
            'services'    => '🚚 Služby / Logistika',
            'agriculture' => '🌾 Zemědělství',
            'public'      => '🏛️ Veřejný sektor',
        ];
        $items = normalizeCheckboxGroup($value);
        if (empty($items)) return '<span style="color: #999; font-style: italic;">Nevyplněno</span>';
        $html = '';
        foreach ($items as $item) {
            $label = $ct_labels[$item] ?? $item;
            $html .= '<div style="display: inline-block; background: #e8f5e8; padding: 5px 12px; border-radius: 5px; margin: 3px; color: #155724; font-weight: 500;">' . htmlspecialchars($label) . '</div>';
        }
        return '<div>' . $html . '</div>';
    }

    // goals checkbox group
    if ($key === 'goals' && is_array($value)) {
        $goal_labels = [
            'fve-overflow'        => '⚡ Úspora z přetoků z FVE',
            'peak-shaving'        => '📊 Posun spotřeby (peak shaving)',
            'backup-power'        => '🔋 Záložní napájení',
            'grid-services'       => '🔌 Služby pro síť',
            'cost-optimization'   => '💰 Optimalizace nákladů na elektřinu',
            'environmental'       => '🌿 Ekologický přínos',
            'machine-support'     => '⚙️ Podpora výkonu strojů',
            'energyindependence'  => 'Energetická nezávislost',
            'costsaving'          => 'Úspora nákladů',
            'backuppower'         => 'Záložní napájení',
            'peakshaving'         => 'Peak shaving',
            'gridstabilization'   => 'Stabilizace sítě',
            'environmentalbenefit'=> 'Ekologický přínos',
        ];
        $items = normalizeCheckboxGroup($value);
        if (empty($items)) return '<span style="color: #999; font-style: italic;">Nevyplněno</span>';
        $html = '';
        foreach ($items as $item) {
            $label = $goal_labels[$item] ?? $item;
            $html .= '<div style="padding: 8px; margin: 5px 0; background: #e8f5e8; border-left: 4px solid #28a745; border-radius: 3px; font-weight: 500;">' . htmlspecialchars($label) . '</div>';
        }
        return '<div style="background: #f8f9fa; border-radius: 8px; padding: 10px;">' . $html . '</div>';
    }

    // documentationTypes checkbox group
    if ($key === 'documentationTypes' && is_array($value)) {
        $doc_labels = [
            'sitePlan'           => 'Situační plán areálu',
            'electricalPlan'     => 'Elektrická dokumentace',
            'buildingPlan'       => 'Půdorysy budov',
            'otherDocumentation' => 'Jiná dokumentace',
        ];
        $items = normalizeCheckboxGroup($value);
        if (empty($items)) return '<span style="color: #999; font-style: italic;">Nevyplněno</span>';
        $html = '';
        foreach ($items as $item) {
            $label = $doc_labels[$item] ?? $item;
            $html .= '<li style="margin: 5px 0;">' . htmlspecialchars($label) . '</li>';
        }
        return '<ul style="list-style: disc; padding-left: 20px; margin: 5px 0;">' . $html . '</ul>';
    }

    // gasUsage checkbox group
    if ($key === 'gasUsage' && is_array($value)) {
        $gas_labels = [
            'heating'    => 'Vytápění',
            'hotWater'   => 'Ohřev vody',
            'technology' => 'Technologie/výroba',
            'cooking'    => 'Vaření',
        ];
        $items = normalizeCheckboxGroup($value);
        if (empty($items)) return '<span style="color: #999; font-style: italic;">Nevyplněno</span>';
        $html = '';
        foreach ($items as $item) {
            $label = $gas_labels[$item] ?? $item;
            $html .= '<li style="margin: 5px 0;">' . htmlspecialchars($label) . '</li>';
        }
        return '<ul style="list-style: disc; padding-left: 20px; margin: 5px 0;">' . $html . '</ul>';
    }

    // Proposed steps
    if ($key === 'proposedSteps' && is_array($value) && !empty($value)) {
        $step_labels = [
            'connectionApplication' => '📄 Žádost o připojení k distribuční síti',
            'powerIncrease'         => '⚡ Navýšení rezervovaného příkonu',
            'projectDocumentation'  => '📋 Zpracování projektové dokumentace',
            'permitProcess'         => '🏛️ Proces stavebního/provozního povolení',
            'gridConnection'        => '🔌 Připojení k distribuční síti',
            'subsidyApplication'    => '💰 Žádost o dotace/podporu',
        ];
        $normalized_steps = normalizeCheckboxGroup($value);
        if (empty($normalized_steps)) return '<span style="color: #999; font-style: italic;">Nevyplněno</span>';
        $steps_html = '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0;">';
        foreach ($normalized_steps as $step) {
            $label = $step_labels[$step] ?? $step;
            $steps_html .= '<div style="padding: 8px; margin: 5px 0; background: white; border-left: 4px solid #ffc107; border-radius: 3px;">' . htmlspecialchars($label) . '</div>';
        }
        $steps_html .= '</div>';
        return $steps_html;
    }
    
    // Agreements
    if ($key === 'agreements' && is_array($value) && !empty($value)) {
        $agreement_labels = [
            'connectionContract'  => '📝 Smlouva o připojení k DS',
            'powerOfAttorney'     => '📜 Plná moc pro jednání s DS',
            'landLease'           => '🏞️ Smlouva o pronájmu pozemku',
            'gridAccess'          => '🔌 Smlouva o přístupu do sítě',
            'maintenanceContract' => '🔧 Servisní smlouva',
        ];
        $normalized_agreements = normalizeCheckboxGroup($value);
        if (empty($normalized_agreements)) return '<span style="color: #999; font-style: italic;">Nevyplněno</span>';
        $agreements_html = '<div style="background: #f3e5f5; padding: 15px; border-radius: 8px; margin: 10px 0;">';
        foreach ($normalized_agreements as $agreement) {
            $label = $agreement_labels[$agreement] ?? $agreement;
            $agreements_html .= '<div style="padding: 8px; margin: 5px 0; background: white; border-left: 4px solid #9c27b0; border-radius: 3px;">' . htmlspecialchars($label) . '</div>';
        }
        $agreements_html .= '</div>';
        return $agreements_html;
    }
    
    // Translations
    $translations = [
        'yes' => 'Ano',
        'no' => 'Ne',
        'true' => 'Ano',
        'false' => 'Ne',
        'cez' => 'ČEZ',
        'pre' => 'PRE',
        'egd' => 'E.GD',
        'lds' => 'LDS',
        'oil' => 'Olejový spínač',
        'vacuum' => 'Vakuový spínač',
        'SF6' => 'SF6 spínač',
        'other' => 'Jiný typ',
        'quarter-hour' => 'Čtvrthodinové měření (A-měření)',
        'unknown' => 'Neví',
        'specific' => 'Konkrétní hodnota',
        'once' => '1x denně',
        'multiple' => 'Vícekrát denně',
        'recommend' => 'Neznámo - doporučit',
        'minutes' => 'Desítky minut',
        'hours-1-3' => '1-3 hodiny',
        'hours-3-plus' => 'Více než 3 hodiny',
        'exact-time' => 'Přesně stanovená doba',
        'easy' => 'Snadná přístupnost',
        'moderate' => 'Středně obtížná',
        'difficult' => 'Obtížná přístupnost',
        'fix' => 'Fixní cena',
        'spot' => 'Spotová cena',
        'gradual' => 'Postupná fixace',
        'very-important' => 'Velmi důležité',
        'important' => 'Důležité',
        'not-important' => 'Není důležité',
        'customer' => 'Zákazník sám',
        'customerbyelectree' => 'Zákazník prostřednictvím Electree',
        'electree' => 'Firma Electree na základě plné moci',
        'undecided' => 'Ještě nerozhodnuto',
        'industrial' => '🏭 Průmysl',
        'commercial' => '🏢 Komerční objekt',
        'services' => '🚚 Služby / Logistika',
        'agriculture' => '🌾 Zemědělství',
        'public' => '🏛️ Veřejný sektor',
        'energyindependence' => 'Energetická nezávislost',
        'costsaving' => 'Úspora nákladů',
        'backuppower' => 'Záložní napájení',
        'peakshaving' => 'Peak shaving',
        'gridstabilization' => 'Stabilizace sítě',
        'environmentalbenefit' => 'Ekologický přínos',
    ];
    
    $valueToCheck = is_string($value) ? strtolower($value) : (string)$value;
    if (isset($translations[$valueToCheck])) {
        $translated = $translations[$valueToCheck];
        $color = ($valueToCheck === 'yes' || $valueToCheck === 'true' || $valueToCheck === '1') ? '#28a745' : '#333';
        return '<div style="background: #e8f5e8; padding: 8px 12px; border-radius: 5px; color: ' . $color . '; font-weight: 500; display: inline-block;">' . htmlspecialchars($translated) . '</div>';
    }
    
    // Generic arrays — normalize (handles both assoc checkbox groups and indexed lists)
    if (is_array($value)) {
        $items = normalizeCheckboxGroup($value);
        if (empty($items)) return '<span style="color: #999; font-style: italic;">Nevyplněno</span>';
        $items_html = array_map(fn($item) => '<li style="margin: 3px 0;">' . htmlspecialchars($translations[strtolower((string)$item)] ?? (string)$item) . '</li>', $items);
        return '<ul style="list-style: disc; padding-left: 20px; margin: 5px 0;">' . implode('', $items_html) . '</ul>';
    }
    
    // Phone numbers
    if (strpos($key, 'phone') !== false || strpos($key, 'Phone') !== false) {
        return '<a href="tel:' . htmlspecialchars($value) . '" style="color: #0066cc; text-decoration: none; font-weight: 500;">
                    <i class="fas fa-phone"></i> ' . htmlspecialchars($value) . '
                </a>';
    }
    
    // Email
    if (strpos($key, 'email') !== false || strpos($key, 'Email') !== false) {
        return '<a href="mailto:' . htmlspecialchars($value) . '" style="color: #0066cc; text-decoration: none; font-weight: 500;">
                    <i class="fas fa-envelope"></i> ' . htmlspecialchars($value) . '
                </a>';
    }
    
    // Long text
    if (strlen($value) > 100 || strpos($key, 'note') !== false || strpos($key, 'description') !== false || strpos($key, 'detail') !== false) {
        return '<div style="background: #f8f9fa; padding: 12px; border-radius: 5px; border-left: 3px solid #0066cc; white-space: pre-wrap; line-height: 1.6;">' . 
               htmlspecialchars($value) . 
               '</div>';
    }
    
    return '<span style="color: #333; font-weight: 500;">' . htmlspecialchars($value) . '</span>';
}

function showConfirmationForm($form, $formData) {
    global $step_names;
    
    // Organize data by steps
    $organized_data = organizeDataBySteps($formData);
    $step_notes = $formData['stepNotes'] ?? [];
    
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Potvrzení údajů a GDPR - Electree</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                max-width: 1000px; 
                margin: 0 auto; 
                padding: 20px; 
                line-height: 1.6;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            }
            .header { 
                background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
                color: white; 
                padding: 30px; 
                border-radius: 12px; 
                margin-bottom: 30px; 
                text-align: center;
                box-shadow: 0 4px 12px rgba(0,102,204,0.3);
            }
            .header h1 {
                margin: 0 0 10px 0;
                font-size: 28px;
                font-weight: 700;
            }
            .info-box { 
                background: white;
                border-left: 4px solid #0066cc;
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .step-section {
                background: white;
                border-radius: 12px;
                padding: 25px;
                margin: 25px 0;
                box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                border-top: 4px solid #0066cc;
            }
            .step-header {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #e9ecef;
            }
            .step-icon {
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 24px;
                margin-right: 15px;
                box-shadow: 0 4px 8px rgba(0,102,204,0.2);
            }
            .step-title {
                font-size: 22px;
                font-weight: 700;
                color: #212529;
                margin: 0;
            }
            .field-row {
                display: flex;
                padding: 15px 0;
                border-bottom: 1px solid #f1f3f5;
                align-items: flex-start;
            }
            .field-row:last-child {
                border-bottom: none;
            }
            .field-label {
                flex: 0 0 35%;
                font-weight: 600;
                color: #495057;
                padding-right: 20px;
                display: flex;
                align-items: center;
            }
            .field-label i {
                margin-right: 8px;
                color: #0066cc;
                width: 20px;
                text-align: center;
            }
            .field-value {
                flex: 1;
                color: #212529;
            }
            .step-notes {
                background: #fff8e1;
                border-left: 4px solid #ffc107;
                padding: 15px;
                border-radius: 8px;
                margin-top: 20px;
            }
            .step-notes-title {
                font-weight: 700;
                color: #f57c00;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
            }
            .step-notes-title i {
                margin-right: 8px;
            }
            .confirm-box { 
                background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
                border: 2px solid #28a745; 
                padding: 30px; 
                border-radius: 12px; 
                margin: 40px 0;
                box-shadow: 0 4px 12px rgba(40,167,69,0.2);
            }
            .confirm-box h3 {
                color: #155724;
                font-size: 24px;
                margin-top: 0;
                display: flex;
                align-items: center;
            }
            .confirm-box h3 i {
                margin-right: 10px;
            }
            .btn { 
                background: linear-gradient(135deg, #28a745 0%, #20903a 100%);
                color: white; 
                padding: 16px 40px; 
                border: none; 
                border-radius: 8px; 
                font-size: 18px;
                font-weight: 700;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(40,167,69,0.3);
                transition: all 0.3s ease;
                display: inline-block;
                text-decoration: none;
            }
            .btn:hover { 
                background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
                box-shadow: 0 6px 16px rgba(40,167,69,0.4);
                transform: translateY(-2px);
            }
            .gdpr-text { 
                font-size: 15px; 
                line-height: 1.7; 
                margin: 20px 0;
                color: #155724;
            }
            .gdpr-text ul {
                margin: 15px 0;
                padding-left: 20px;
            }
            .gdpr-text li {
                margin: 8px 0;
            }
            .required { 
                color: #dc3545;
                font-weight: 700;
            }
            .checkbox-label {
                display: flex;
                align-items: center;
                padding: 15px;
                background: white;
                border-radius: 8px;
                margin: 20px 0;
                cursor: pointer;
                border: 2px solid #28a745;
            }
            .checkbox-label input[type="checkbox"] {
                margin-right: 12px;
                transform: scale(1.5);
                cursor: pointer;
            }
            @media (max-width: 768px) {
                body {
                    padding: 10px;
                }
                .field-row {
                    flex-direction: column;
                }
                .field-label {
                    flex: 1;
                    margin-bottom: 8px;
                }
                .step-title {
                    font-size: 18px;
                }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><i class="fas fa-lock"></i> Potvrzení údajů a souhlas GDPR</h1>
            <p style="margin: 0; font-size: 18px; opacity: 0.95;">Electree - Bateriové systémy</p>
        </div>

        <div class="info-box">
            <p style="margin: 8px 0;"><strong><i class="fas fa-file-alt"></i> Věc:</strong> Potvrzení správnosti údajů z dotazníku a souhlas se zpracováním osobních údajů</p>
            <p style="margin: 8px 0;"><strong><i class="fas fa-calendar"></i> Datum odeslání:</strong> <?php echo date('d.m.Y H:i', strtotime($form['created_at'])); ?></p>
            <p style="margin: 8px 0; font-size: 14px; color: #666;"><strong><i class="fas fa-hashtag"></i> ID formuláře:</strong> <?php echo htmlspecialchars($form['id']); ?></p>
        </div>

        <form method="POST">
            <?php foreach ($organized_data as $step_num => $step_data): ?>
                <?php if (!empty($step_data)): ?>
                    <div class="step-section">
                        <div class="step-header">
                            <div class="step-icon">
                                <i class="<?php echo getStepIcon($step_num); ?>"></i>
                            </div>
                            <h2 class="step-title">
                                <?php echo $step_num . '. ' . $step_names[$step_num]; ?>
                            </h2>
                        </div>
                        
                        <?php foreach ($step_data as $key => $value): ?>
                            <div class="field-row">
                                <div class="field-label">
                                    <i class="<?php echo getFieldIcon($key); ?>"></i>
                                    <?php echo getFieldLabel($key); ?>
                                </div>
                                <div class="field-value">
                                    <?php echo formatFieldValue($key, $value); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($step_notes[$step_num])): ?>
                            <div class="step-notes">
                                <div class="step-notes-title">
                                    <i class="fas fa-sticky-note"></i>
                                    Poznámky ke kroku
                                </div>
                                <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($step_notes[$step_num]); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- GDPR souhlas -->
            <div class="confirm-box">
                <h3><i class="fas fa-shield-alt"></i> Potvrzení a souhlas GDPR</h3>
                
                <div class="gdpr-text">
                    <p><strong>Tímto potvrzuji:</strong></p>
                    <ul>
                        <li>✅ <strong>Správnost všech výše uvedených údajů</strong></li>
                        <li>✅ <strong>Souhlas se zpracováním osobních údajů podle GDPR</strong></li>
                        <li>✅ <strong>Souhlas s kontaktováním ohledně nabídky bateriových systémů</strong></li>
                        <li>✅ <strong>Předání dat do CRM systému Raynet pro zpracování poptávky</strong></li>
                    </ul>
                    
                    <div style="background: white; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 5px 0;"><strong>Zpracovatel údajů:</strong> Electree s.r.o.</p>
                        <p style="margin: 5px 0;"><strong>Účel zpracování:</strong> Zpracování poptávky na bateriové systémy</p>
                        <p style="margin: 5px 0;"><strong>Doba uchování:</strong> 3 roky od posledního kontaktu</p>
                    </div>
                    
                    <p style="font-size: 13px; opacity: 0.9;">
                        <i class="fas fa-info-circle"></i> 
                        Souhlas můžete kdykoli odvolat na emailu <a href="mailto:info@electree.cz" style="color: #155724; font-weight: 600;">info@electree.cz</a>
                    </p>
                </div>

                <label class="checkbox-label">
                    <input type="checkbox" required>
                    <span>
                        <span class="required">*</span> 
                        <strong>Potvrzuji správnost údajů a souhlasím se zpracováním osobních údajů podle GDPR</strong>
                    </span>
                </label>

                <button type="submit" name="confirm_gdpr" class="btn">
                    <i class="fas fa-check-circle"></i> POTVRDIT ÚDAJE A SOUHLAS
                </button>
            </div>
        </form>

        <div class="info-box">
            <p style="margin: 8px 0;">
                <strong><i class="fas fa-headset"></i> Kontakt:</strong> 
                <a href="mailto:info@electree.cz" style="color: #0066cc; text-decoration: none;">info@electree.cz</a> | 
                <a href="tel:+420123456789" style="color: #0066cc; text-decoration: none;">+420 123 456 789</a> | 
                <a href="https://electree.cz" target="_blank" style="color: #0066cc; text-decoration: none;">www.electree.cz</a>
            </p>
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
