<?php
// Centralizované připojení k databázi
require_once __DIR__ . '/../config/database.php';

try {
    $conn = getMysqliConnection();
} catch (Exception $e) {
    die("Chyba připojení k databázi: " . $e->getMessage());
}

$form_id = $_GET['id'] ?? null;

if (!$form_id) {
    echo "ID formuláře nebylo poskytnuto";
    exit();
}

// Načtení dat z databáze
$stmt = $conn->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->bind_param("s", $form_id);
$stmt->execute();
$result = $stmt->get_result();
$form_data = $result->fetch_assoc();

if (!$form_data) {
    echo "Formulář nenalezen";
    exit();
}

// Dekódování dat formuláře
$decoded_data = json_decode($form_data['form_data'], true);
$step_notes = $decoded_data['stepNotes'] ?? [];

// Organizace dat podle kroků - kompletní seznam všech polí
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
    
    $organized_data = [];
    foreach ($steps as $step_num => $fields) {
        foreach ($fields as $field) {
            if (isset($decoded_data[$field])) {
                $organized_data[$step_num][$field] = $decoded_data[$field];
            }
        }
    }
    
    return $organized_data;
}

$organized_data = organizeDataBySteps($decoded_data);

// Názvy kroků odpovídající skutečnému formuláři
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

// Ikony pro kroky
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

// Gradienty pro kroky
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

// Ikony pro pole
function getFieldIcon($field) {
    $icons = [
        // Základní údaje
        'companyName' => 'fas fa-building',
        'ico' => 'fas fa-hashtag',
        'dic' => 'fas fa-file-text',
        'contactPerson' => 'fas fa-user',
        'email' => 'fas fa-envelope',
        'phone' => 'fas fa-phone',
        'address' => 'fas fa-map-marker-alt',
        'companyAddress' => 'fas fa-building',
        'customerType' => 'fas fa-tags',
        
        // Technické parametry
        'hasFveVte' => 'fas fa-solar-panel',
        'fveVtePower' => 'fas fa-bolt',
        'hasTransformer' => 'fas fa-plug',
        'transformerPower' => 'fas fa-plug',
        'circuitBreakerType' => 'fas fa-toggle-on',
        'mainCircuitBreaker' => 'fas fa-toggle-on',
        'reservedPower' => 'fas fa-battery-full',
        'monthlyConsumption' => 'fas fa-chart-bar',
        'yearlyConsumption' => 'fas fa-chart-pie',
        
        // Cíle a plány
        'goals' => 'fas fa-target',
        'batteryCapacity' => 'fas fa-battery-half',
        'installationLocation' => 'fas fa-map-pin',
        'budgetRange' => 'fas fa-coins',
        'timeframe' => 'fas fa-clock',
        
        // Ostatní
        'notes' => 'fas fa-sticky-note',
        'documents' => 'fas fa-file-alt',
        'agreements' => 'fas fa-handshake'
    ];
    
    return $icons[$field] ?? 'fas fa-info-circle';
}

// Kompletní seznam názvů polí podle skutečného formuláře
function getFieldLabel($key) {
    $labels = [
        // Krok 1 - Identifikační údaje zákazníka
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
        'companyDetails' => 'Detaily společnosti z MERK',
        
        // Typy zákazníků
        'industrial' => '🏭 Průmysl',
        'commercial' => '🏢 Komerční objekt',
        'services' => '🚚 Služby / Logistika',  
        'agriculture' => '🌾 Zemědělství',
        'public' => '🏛️ Veřejný sektor',
        'other' => '❓ Jiný typ',
        
        // Krok 2 - Parametry odběrného místa
        'hasFveVte' => 'Má instalovanou FVE/VTE',
        'fveVtePower' => 'Výkon FVE/VTE (kW)',
        'accumulationPercentage' => 'Procento akumulace přetoků (%)',
        'interestedInFveVte' => 'Zájem o instalaci FVE',
        'interestedInInstallationProcessing' => 'Zájem o zpracování instalace',
        'interestedInElectromobility' => 'Zájem o elektromobilitu',
        
        // Transformátor
        'hasTransformer' => 'Má vlastní trafostanici',
        'transformerPower' => 'Výkon trafostanice (kVA)',
        'transformerVoltage' => 'VN strana napětí',
        'coolingType' => 'Typ chlazení transformátoru',
        'transformerYear' => 'Rok výroby transformátoru',
        'transformerType' => 'Typ transformátoru',
        'transformerCurrent' => 'Proud transformátoru (A)',
        'circuitBreakerType' => 'Typ hlavního jističe',
        'customCircuitBreaker' => 'Vlastní specifikace jističe',
        
        // Sdílení elektřiny
        'sharesElectricity' => 'Sdílí elektřinu s jinými',
        'electricityShared' => 'Množství sdílené elektřiny (kWh/měsíc)',
        'receivesSharedElectricity' => 'Přijímá sdílenou elektřinu',
        'electricityReceived' => 'Množství přijaté elektřiny (kWh/měsíc)',
        'mainCircuitBreaker' => 'Hlavní jistič (A)',
        'reservedPower' => 'Rezervovaný příkon (kW)',
        
        // Krok 3 - Spotřeba a rozložení
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
        
        // Provozní doba
        'weekdayStart' => 'Začátek pracovního dne',
        'weekdayEnd' => 'Konec pracovního dne',
        'weekdayConsumption' => 'Spotřeba během pracovního dne',
        'weekendStart' => 'Začátek víkendu',
        'weekendEnd' => 'Konec víkendu', 
        'weekendConsumption' => 'Víkendová spotřeba',
        'weekdayPattern' => 'Vzorec spotřeby během týdne',
        'weekendPattern' => 'Vzorec víkendové spotřeby',
        
        // Krok 4 - Analýza spotřeby a akumulace
        'energyAccumulation' => 'Množství energie k akumulaci',
        'energyAccumulationAmount' => 'Konkrétní hodnota (kWh)',
        'energyAccumulationValue' => 'Konkrétní hodnota akumulace (kWh)',
        'batteryCycles' => 'Kolikrát denně využít baterii',
        'requiresBackup' => 'Potřeba záložního napájení',
        'backupDescription' => 'Co je potřeba zálohovat',
        'backupDuration' => 'Požadovaná doba zálohy',
        'priceOptimization' => 'Řízení podle ceny elektřiny',
        'hasElectricityProblems' => 'Problémy s elektřinou',
        'electricityProblemsDetails' => 'Detaily problémů s elektřinou',
        'hasEnergyAudit' => 'Energetický audit',
        'energyAuditDate' => 'Datum energetického auditu',
        'auditDocuments' => 'Dokumenty energetického auditu',
        'hasOwnEnergySource' => 'Vlastní zdroj energie',
        'ownEnergySourceDetails' => 'Detaily vlastního zdroje',
        'canProvideLoadSchema' => 'Může poskytnout schéma zatížení',
        'loadSchemaDetails' => 'Detaily schématu zatížení',
        'energyNotes' => 'Poznámky k energii',
        
        // Krok 5 - Cíle a optimalizace
        'goals' => 'Hlavní cíle bateriového úložiště',
        'priority1' => 'Priorita č. 1',
        'priority2' => 'Priorita č. 2', 
        'priority3' => 'Priorita č. 3',
        
        // Krok 6 - Místo realizace a infrastruktura
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
        
        // Krok 7 - Připojení k síti a legislativa  
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
        
        // Krok 8 - Energetická fakturace a bilancování
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
        'priceImportance' => 'Důležitost ceny elektřiny',
        'electricitySharing' => 'Sdílení elektřiny',
        'sharingDetails' => 'Detaily sdílení',
        'hasGas' => 'Využití plynu',
        'gasPrice' => 'Cena plynu (Kč/kWh)',
        'gasConsumption' => 'Spotřeba plynu (kWh/rok)',
        'gasUsage' => 'Použití plynu',
        'heating' => 'Vytápění',
        'hotWater' => 'Ohřev vody',
        'technology' => 'Technologie/výroba',
        'cooking' => 'Vaření',
        'hasCogeneration' => 'Kogenerační jednotka',
        'cogenerationDetails' => 'Detaily kogenerační jednotky',
        'cogenerationPhotos' => 'Fotografie kogenerační jednotky',
        'hotWaterConsumption' => 'Spotřeba teplé vody (l/den)',
        'heatingConsumption' => 'Spotřeba tepla (kWh/rok)',
        'coolingConsumption' => 'Spotřeba chladu (kWh/rok)',
        'steamConsumption' => 'Spotřeba páry (kWh/rok)',
        'otherConsumption' => 'Další spotřeby',
        'agreements' => 'Dohody a smlouvy',
        'timeline' => 'Časový harmonogram',
        'urgency' => 'Naléhavost realizace',
        'additionalNotes' => 'Dodatečné poznámky',
        
        // Poznámky a soubory
        'notes' => 'Poznámky',
        'stepNotes' => 'Poznámky ke kroku',
        'fileUploads' => 'Nahraté soubory',
        'budgetMin' => 'Minimální rozpočet',
        'budgetMax' => 'Maximální rozpočet',
        'timeframeStart' => 'Začátek realizace',
        'timeframeEnd' => 'Konec realizace',
        
        // Chybejici preklady - presne nazvy klicu z formulare
        'backupDurationHours' => 'Doba zálohy (hodiny)',
        'energyAuditDetails' => 'Detaily energetického auditu',
        'goalDetails' => 'Detaily cílů',
        'otherPurposeDescription' => 'Popis jiného účelu',
        
        // CamelCase varianty (s velkými písmeny na začátku)
        'BackupDurationHours' => 'Doba zálohy (hodiny)',
        'EnergyAuditDetails' => 'Detaily energetického auditu',
        'GoalDetails' => 'Detaily cílů',
        'ReservedOutput' => 'Rezervovaný výkon',
        'LegislativeNotes' => 'Legislativní poznámky',
        'Agreements' => 'Dohody a smlouvy',
        'AdditionalNotes' => 'Dodatečné poznámky',
        'ConnectionContractFile' => 'Smlouva o připojení (soubor)',
        'ConnectionApplicationFile' => 'Žádost o připojení (soubor)',
        'SpecialistName' => 'Jméno specialisty',
        'SpecialistEmail' => 'E-mail specialisty',
        'SpecialistPhone' => 'Telefon specialisty',
        'AccessibilityLimitations' => 'Omezení přístupnosti',
        'OutdoorSpaceSize' => 'Velikost venkovního prostoru',
        'IndoorSpaceType' => 'Typ vnitřního prostoru',
        'IndoorSpaceSize' => 'Velikost vnitřního prostoru',
        'SiteDescription' => 'Popis lokality',
        'InfrastructureNotes' => 'Poznámky k infrastruktuře',
        'CriticalConsumptionDescription' => 'Popis kritické spotřeby',
        'EnergyNotes' => 'Poznámky k energii',
    ];
    
    return $labels[$key] ?? ucfirst(str_replace(['_', 'Type', 'Has', 'Is'], [' ', ' typ', 'Má ', 'Je '], $key));
}

// Funkce pro formátování souborů a obrázků
function formatFileUploads($key, $value) {
    if (empty($value)) {
        return '<span class="text-gray-400 italic flex items-center"><i class="fas fa-minus-circle mr-1"></i>Žádné soubory</span>';
    }
    
    // Pokud je hodnota string (URL nebo cesta k souboru)
    if (is_string($value)) {
        return formatSingleFile($value);
    }
    
    // Pokud je hodnota pole souborů
    if (!is_array($value)) {
        return '<span class="text-gray-400 italic">Neplatný formát</span>';
    }
    
    $files_html = '<div class="space-y-2">';
    $file_count = 0;
    
    foreach ($value as $idx => $file) {
        $file_count++;
        
        // Soubor může být string (URL) nebo objekt s vlastnostmi
        if (is_string($file)) {
            $files_html .= formatSingleFile($file);
        } elseif (is_array($file)) {
            // Objekt s vlastnostmi jako name, url, path, type
            $file_url = $file['url'] ?? $file['path'] ?? $file['filePath'] ?? '';
            $file_name = $file['name'] ?? $file['fileName'] ?? $file['originalName'] ?? basename($file_url);
            $file_type = $file['type'] ?? $file['mimeType'] ?? '';
            $file_size = $file['size'] ?? null;
            
            if (empty($file_url) && empty($file_name)) {
                continue;
            }
            
            $files_html .= formatSingleFile($file_url, $file_name, $file_type, $file_size);
        }
    }
    
    $files_html .= '</div>';
    
    if ($file_count === 0) {
        return '<span class="text-gray-400 italic flex items-center"><i class="fas fa-minus-circle mr-1"></i>Žádné soubory</span>';
    }
    
    return '<div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                <div class="text-sm text-gray-600 mb-2"><i class="fas fa-folder-open mr-1"></i> ' . $file_count . ' soubor(ů)</div>
                ' . $files_html . '
            </div>';
}

// Pomocná funkce pro formátování jednoho souboru
function formatSingleFile($url, $name = null, $type = null, $size = null) {
    if (empty($url)) {
        if (!empty($name)) {
            return '<div class="flex items-center p-2 bg-white rounded border border-gray-200 text-sm">
                        <i class="fas fa-file text-gray-400 mr-2"></i>
                        <span class="text-gray-700">' . htmlspecialchars($name) . '</span>
                    </div>';
        }
        return '';
    }
    
    $display_name = $name ?: basename($url);
    $file_type = $type ?: '';
    
    // Detekce typu souboru podle přípony
    $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp']);
    $is_pdf = $extension === 'pdf';
    $is_doc = in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
    
    // Také kontrolovat MIME type
    if (strpos($file_type, 'image/') === 0) {
        $is_image = true;
    } elseif (strpos($file_type, 'application/pdf') !== false) {
        $is_pdf = true;
    }
    
    // Sestavení URL - zajistit že je absolutní
    $full_url = $url;
    if (strpos($url, 'http') !== 0 && strpos($url, '/') !== 0) {
        // Relativní cesta - předpokládáme že je v uploads
        $full_url = '/uploads/' . $url;
    }
    
    if ($is_image) {
        // Zobrazit obrázek jako náhled
        return '<div class="inline-block m-1">
                    <a href="' . htmlspecialchars($full_url) . '" target="_blank" class="block group">
                        <div class="relative overflow-hidden rounded-lg border border-gray-200 hover:border-blue-400 transition-colors">
                            <img src="' . htmlspecialchars($full_url) . '" 
                                 alt="' . htmlspecialchars($display_name) . '" 
                                 class="w-32 h-32 object-cover group-hover:scale-105 transition-transform"
                                 onerror="this.onerror=null; this.parentNode.innerHTML=\'<div class=\\\'w-32 h-32 flex items-center justify-center bg-gray-100 text-gray-400\\\'><i class=\\\'fas fa-image fa-2x\\\'></i></div>\';">
                            <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs p-1 truncate opacity-0 group-hover:opacity-100 transition-opacity">
                                ' . htmlspecialchars($display_name) . '
                            </div>
                        </div>
                    </a>
                </div>';
    } elseif ($is_pdf) {
        return '<div class="flex items-center p-2 bg-red-50 rounded border border-red-200 text-sm mb-1">
                    <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                    <a href="' . htmlspecialchars($full_url) . '" target="_blank" class="text-blue-600 hover:underline flex-1 truncate">
                        ' . htmlspecialchars($display_name) . '
                    </a>
                    <a href="' . htmlspecialchars($full_url) . '" download class="ml-2 text-gray-500 hover:text-gray-700" title="Stáhnout">
                        <i class="fas fa-download"></i>
                    </a>
                </div>';
    } elseif ($is_doc) {
        return '<div class="flex items-center p-2 bg-blue-50 rounded border border-blue-200 text-sm mb-1">
                    <i class="fas fa-file-word text-blue-500 mr-2"></i>
                    <a href="' . htmlspecialchars($full_url) . '" target="_blank" class="text-blue-600 hover:underline flex-1 truncate">
                        ' . htmlspecialchars($display_name) . '
                    </a>
                    <a href="' . htmlspecialchars($full_url) . '" download class="ml-2 text-gray-500 hover:text-gray-700" title="Stáhnout">
                        <i class="fas fa-download"></i>
                    </a>
                </div>';
    } else {
        // Obecný soubor
        return '<div class="flex items-center p-2 bg-white rounded border border-gray-200 text-sm mb-1">
                    <i class="fas fa-file text-gray-400 mr-2"></i>
                    <a href="' . htmlspecialchars($full_url) . '" target="_blank" class="text-blue-600 hover:underline flex-1 truncate">
                        ' . htmlspecialchars($display_name) . '
                    </a>
                    <a href="' . htmlspecialchars($full_url) . '" download class="ml-2 text-gray-500 hover:text-gray-700" title="Stáhnout">
                        <i class="fas fa-download"></i>
                    </a>
                </div>';
    }
}

// Funkce pro formatování hodnot s českými překlady
function formatFieldValue($key, $value) {
    // Prázdné hodnoty
    if (is_null($value) || $value === '' || $value === false || (is_array($value) && empty($value))) {
        return '<span class="text-gray-400 italic flex items-center"><i class="fas fa-minus-circle mr-1"></i>Nevyplněno</span>';
    }
    
    // PRIORITY - musí být první před obecným překladem!
    if ((strpos($key, 'priority') !== false || strpos($key, 'Priority') !== false) && is_string($value) && !empty($value)) {
        $priority_labels = [
            'fve-overflow' => '🔋 Úspora z přetoků z FVE',
            'peak-shaving' => '📊 Posun spotřeby (peak shaving)',
            'backup-power' => '⚡ Záložní napájení',
            'grid-services' => '🏗️ Služby pro síť',
            'cost-optimization' => '💰 Optimalizace nákladů na elektřinu',
            'environmental' => '🌱 Ekologický přínos',
            'machine-support' => '🔧 Podpora výkonu strojů',
            'power-reduction' => '📉 Snížení rezervovaného příkonu',
            'energy-trading' => '📈 Možnost obchodování s energií',
            'subsidy' => '💸 Získání dotace',
            'other' => '❓ Jiný účel'
        ];
        $priority_text = $priority_labels[$value] ?? $value;
        return '<div class="bg-orange-100 px-3 py-2 rounded-lg text-orange-800 font-medium flex items-center">' . htmlspecialchars($priority_text) . '</div>';
    }
    
    // SPECIÁLNÍ FORMÁTOVÁNÍ PRO KLÍČOVÁ POLE
    
    // Vzorec spotřeby během týdne
    if ($key === 'weekdayPattern' || $key === 'weekConsumptionPattern') {
        if (is_array($value) && !empty($value)) {
            $pattern_html = '<div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4">
                <div class="grid grid-cols-4 gap-2 text-sm">';
            
            $hours = ['06:00', '08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'];
            foreach ($hours as $hour) {
                $consumption = $value[$hour] ?? '0';
                $pattern_html .= '<div class="bg-white rounded px-2 py-1 text-center border border-blue-100">
                    <div class="font-medium text-blue-700">' . $hour . '</div>
                    <div class="text-lg font-bold text-blue-900">' . htmlspecialchars($consumption) . '</div>
                    <div class="text-xs text-gray-500">kWh</div>
                </div>';
            }
            
            $pattern_html .= '</div></div>';
            return $pattern_html;
        }
        return '<div class="bg-blue-50 p-3 rounded-lg text-blue-700 italic flex items-center"><i class="fas fa-chart-line mr-2"></i>Vzorec týdenní spotřeby nebyl vyplněn</div>';
    }
    
    // Vzorec víkendové spotřeby
    if ($key === 'weekendPattern' || $key === 'weekendConsumptionPattern') {
        if (is_array($value) && !empty($value)) {
            $pattern_html = '<div class="bg-green-50 border-2 border-green-200 rounded-lg p-4">
                <div class="grid grid-cols-4 gap-2 text-sm">';
            
            $hours = ['06:00', '08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'];
            foreach ($hours as $hour) {
                $consumption = $value[$hour] ?? '0';
                $pattern_html .= '<div class="bg-white rounded px-2 py-1 text-center border border-green-100">
                    <div class="font-medium text-green-700">' . $hour . '</div>
                    <div class="text-lg font-bold text-green-900">' . htmlspecialchars($consumption) . '</div>
                    <div class="text-xs text-gray-500">kWh</div>
                </div>';
            }
            
            $pattern_html .= '</div></div>';
            return $pattern_html;
        }
        return '<div class="bg-green-50 p-3 rounded-lg text-green-700 italic flex items-center"><i class="fas fa-calendar-weekend mr-2"></i>Vzorec víkendové spotřeby nebyl vyplněn</div>';
    }
    
    // Navrhované kroky
    if ($key === 'proposedSteps') {
        if (is_array($value) && !empty($value)) {
            // Mapování pro navrhované kroky
            $steps_mapping = [
                'permitsAndApprovals' => 'Získání povolení a schválení',
                'gridConnectionApplication' => 'Žádost o připojení k síti',
                'technicalAssessment' => 'Technické posouzení',
                'batteryInstallation' => 'Instalace bateriového úložiště',
                'systemIntegration' => 'Integrace systému',
                'commissioningAndTesting' => 'Uvedení do provozu a testování',
                'optimizationSetup' => 'Nastavení optimalizace',
                'monitoring' => 'Monitoring a údržba',
                'powerIncrease' => 'Zvýšení rezervovaného příkonu',
                'peakShavingSetup' => 'Nastavení peak shaving',
                'backupConfiguration' => 'Konfigurace záložního napájení',
                'gridServices' => 'Služby pro distribuční soustavu',
                'costOptimization' => 'Optimalizace nákladů',
                'energyTrading' => 'Obchodování s energií',
                'subsidyApplication' => 'Žádost o dotaci',
                'legalDocumentation' => 'Právní dokumentace',
                'installation' => 'Instalace',
                'configuration' => 'Konfigurace',
                'testing' => 'Testování',
                'maintenance' => 'Údržba'
            ];
            
            $steps_html = '<div class="bg-yellow-50 border-2 border-yellow-200 rounded-lg p-4">
                <div class="space-y-2">';
            
            $step_counter = 1;
            foreach ($value as $stepKey => $stepValue) {
                // Pokud je to key-value pár s hodnotou 1 nebo true
                if (!empty($stepValue) && ($stepValue === '1' || $stepValue === 1 || $stepValue === true || $stepValue === 'true')) {
                    $stepText = $steps_mapping[$stepKey] ?? $stepKey;
                    $steps_html .= '<div class="bg-white rounded-lg p-3 border-l-4 border-yellow-400 flex items-start">
                        <div class="bg-yellow-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 flex-shrink-0">' . $step_counter . '</div>
                        <div class="text-gray-800">' . htmlspecialchars($stepText) . '</div>
                    </div>';
                    $step_counter++;
                }
                // Pokud je to přímo text (string)
                elseif (is_string($stepValue) && !empty($stepValue) && $stepValue !== '0' && $stepValue !== 'false') {
                    $steps_html .= '<div class="bg-white rounded-lg p-3 border-l-4 border-yellow-400 flex items-start">
                        <div class="bg-yellow-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 flex-shrink-0">' . $step_counter . '</div>
                        <div class="text-gray-800">' . htmlspecialchars($stepValue) . '</div>
                    </div>';
                    $step_counter++;
                }
            }
            
            $steps_html .= '</div></div>';
            return $steps_html;
        } elseif (is_string($value) && !empty($value)) {
            return '<div class="bg-yellow-50 border-2 border-yellow-200 rounded-lg p-4">
                <div class="bg-white rounded-lg p-3 border-l-4 border-yellow-400 text-gray-800">' . nl2br(htmlspecialchars($value)) . '</div>
            </div>';
        }
        return '<div class="bg-yellow-50 p-3 rounded-lg text-yellow-700 italic flex items-center"><i class="fas fa-list-ol mr-2"></i>Žádné navrhované kroky</div>';
    }
    
    // Dohody a smlouvy
    if ($key === 'agreements' || $key === 'agreementsAndContracts' || $key === 'Agreements') {
        if (is_array($value) && !empty($value)) {
            // Mapování pro dohody a smlouvy
            $agreements_mapping = [
                'gridConnectionContract' => 'Smlouva o připojení k distribuční soustavě',
                'technicalConditionsAgreement' => 'Dohoda o technických podmínkách připojení',
                'powerOfAttorney' => 'Plná moc pro jednání s distributorem',
                'installationContract' => 'Smlouva o instalaci bateriového systému',
                'maintenanceContract' => 'Smlouva o údržbě a servisu',
                'monitoringAgreement' => 'Dohoda o monitoringu systému',
                'optimizationContract' => 'Smlouva o optimalizaci provozu',
                'energyTradingAgreement' => 'Dohoda o obchodování s energií',
                'gridServicesContract' => 'Smlouva o poskytování podpůrných služeb',
                'insurancePolicy' => 'Pojistná smlouva pro bateriový systém',
                'warrantyAgreement' => 'Záruční smlouva',
                'operatingAgreement' => 'Provozní smlouva',
                'subsidyContract' => 'Smlouva o poskytnutí dotace',
                'legalDocumentation' => 'Právní dokumentace',
                'permitDocuments' => 'Povolení a licence',
                'technicalDocumentation' => 'Technická dokumentace',
                'safetyAgreement' => 'Bezpečnostní dohoda',
                'environmentalPermit' => 'Environmentální povolení'
            ];
            
            $agreements_html = '<div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-4">
                <div class="space-y-2">';
            
            foreach ($value as $agreementKey => $agreementValue) {
                // Pokud je to key-value pár s hodnotou 1 nebo true
                if (!empty($agreementValue) && ($agreementValue === '1' || $agreementValue === 1 || $agreementValue === true || $agreementValue === 'true')) {
                    $agreementText = $agreements_mapping[$agreementKey] ?? $agreementKey;
                    $agreements_html .= '<div class="bg-white rounded-lg p-3 border-l-4 border-purple-400 flex items-center">
                        <i class="fas fa-file-contract text-purple-500 mr-3"></i>
                        <div class="text-gray-800">' . htmlspecialchars($agreementText) . '</div>
                    </div>';
                }
                // Pokud je to přímo text (string)
                elseif (is_string($agreementValue) && !empty($agreementValue) && $agreementValue !== '0' && $agreementValue !== 'false') {
                    $agreements_html .= '<div class="bg-white rounded-lg p-3 border-l-4 border-purple-400 flex items-center">
                        <i class="fas fa-file-contract text-purple-500 mr-3"></i>
                        <div class="text-gray-800">' . htmlspecialchars($agreementValue) . '</div>
                    </div>';
                }
            }
            
            $agreements_html .= '</div></div>';
            return $agreements_html;
        } elseif (is_string($value) && !empty($value)) {
            return '<div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-4">
                <div class="bg-white rounded-lg p-3 border-l-4 border-purple-400 flex items-center">
                    <i class="fas fa-file-contract text-purple-500 mr-3"></i>
                    <div class="text-gray-800">' . nl2br(htmlspecialchars($value)) . '</div>
                </div>
            </div>';
        }
        return '<div class="bg-purple-50 p-3 rounded-lg text-purple-700 italic flex items-center"><i class="fas fa-handshake mr-2"></i>Žádné dohody a smlouvy</div>';
    }
    
    // České překlady podle FormSummary.jsx
    $translations = [
        // Základní yes/no
        'yes' => 'Ano',
        'no' => 'Ne',
        'true' => 'Ano',
        'false' => 'Ne',
        '1' => 'Ano',
        '0' => 'Ne',
        
        // Distribuční území
        'cez' => 'ČEZ',
        'pre' => 'PRE', 
        'egd' => 'E.GD',
        'lds' => 'LDS',
        
        // Typy jističe
        'oil' => 'Olejový spínač',
        'vacuum' => 'Vakuový spínač',
        'SF6' => 'SF6 spínač',
        'other' => 'Jiný typ',
        'custom' => 'Vlastní specifikace',
        
        // Napětí transformátoru
        '22kV' => '22kV',
        '35kV' => '35kV', 
        '110kV' => '110kV',
        
        // Chlazení transformátoru
        'ONAN' => 'ONAN',
        'ONAF' => 'ONAF',
        
        // Typ měření
        'quarter-hour' => 'Čtvrthodinové měření (A-měření)',
        
        // Akumulace energie
        'unknown' => 'Neví',
        'specific' => 'Konkrétní hodnota',
        
        // Cykly baterie
        'once' => '1x denně',
        'multiple' => 'Vícekrát denně',
        'recommend' => 'Neznámo - doporučit',
        
        // Doba zálohy
        'minutes' => 'Desítky minut',
        'hours-1-3' => '1-3 hodiny',
        'hours-3-plus' => 'Více než 3 hodiny',
        'exact-time' => 'Přesně stanovená doba',
        
        // Přístupnost
        'easy' => 'Snadná přístupnost',
        'moderate' => 'Středně obtížná',
        'difficult' => 'Obtížná přístupnost',
        
        // Způsob vyúčtování
        'fix' => 'Fixní cena',
        'spot' => 'Spotová cena',
        'gradual' => 'Postupná fixace',
        
        // Důležitost ceny
        'very-important' => 'Velmi důležité',
        'important' => 'Důležité',
        'not-important' => 'Není důležité',
        'unlimited' => 'Bez omezení',
        'limited' => 'Omezený',
        
        // Žádost o připojení - kdo podá
        'customer' => 'Zákazník sám',
        'customerbyelectree' => 'Zákazník prostřednictvím Electree',
        'electree' => 'Firma Electree na základě plné moci',
        'undecided' => 'Ještě nerozhodnuto',
        
        // Pozice energetického specialisty
        'specialist' => 'Specialista',
        'manager' => 'Správce',
        
        // Typy zákazníků 
        'industrial' => 'Průmysl',
        'commercial' => 'Komerční objekt',
        'services' => 'Služby / Logistika',
        'agriculture' => 'Zemědělství',
        'public' => 'Veřejný sektor',
        
        // Cíle (goals)
        'energyindependence' => 'Energetická nezávislost',
        'costsaving' => 'Úspora nákladů',
        'backuppower' => 'Záložní napájení',
        'peakshaving' => 'Peak shaving',
        'gridstabilization' => 'Stabilizace sítě',
        'environmentalbenefit' => 'Ekologický přínos',
        
        // Priority
        'fve-overflow' => 'Úspora z přetoků z FVE',
        'peak-shaving' => 'Posun spotřeby (peak shaving)',
        'backup-power' => 'Záložní napájení',
        'grid-services' => 'Služby pro síť',
        'cost-optimization' => 'Optimalizace nákladů na elektřinu',
        'environmental' => 'Ekologický přínos',
        'machine-support' => 'Podpora výkonu strojů',
        'power-reduction' => 'Snížení rezervovaného příkonu',
        'energy-trading' => 'Možnost obchodování s energií',
        'subsidy' => 'Získání dotace',
        
        // Použití plynu
        'heating' => 'Vytápění',
        'hot-water' => 'Ohřev teplé vody',
        'cooking' => 'Vaření',
        'production' => 'Výrobní procesy',
        'backup-heating' => 'Záložní vytápění',
        'technology' => 'Technologické procesy',
        
        // Časová pásma
        'nt' => 'NT (nízký tarif)',
        'vt' => 'VT (vysoký tarif)',
        'morning' => 'Ranní hodiny',
        'afternoon' => 'Odpolední hodiny',
        'evening' => 'Večerní hodiny',
        'night' => 'Noční hodiny',
        
        // Velikosti
        'small' => 'Malá',
        'medium' => 'Střední',
        'large' => 'Velká',
        'extra-large' => 'Extra velká',
        
        // Stavy
        'active' => 'Aktivní',
        'inactive' => 'Neaktivní',
        'pending' => 'Čekající',
        'approved' => 'Schváleno',
        'rejected' => 'Zamítnuto',
        'draft' => 'Návrh',
        'submitted' => 'Odesláno',
        'processing' => 'Zpracovává se',
        'completed' => 'Dokončeno'
    ];
    
    // Použití překladu pokud existuje (pro řetězce i čísla)
    $valueToCheck = is_string($value) ? strtolower($value) : (string)$value;
    if (isset($translations[$valueToCheck])) {
        $translatedValue = $translations[$valueToCheck];
        if (in_array($translatedValue, ['Ano', 'Ne'])) {
            return $translatedValue === 'Ano' ? 
                '<span class="text-emerald-600 font-medium flex items-center"><i class="fas fa-check-circle mr-1"></i>Ano</span>' : 
                '<span class="text-red-600 font-medium flex items-center"><i class="fas fa-times-circle mr-1"></i>Ne</span>';
        }
        return '<span class="font-medium text-blue-600">' . htmlspecialchars($translatedValue) . '</span>';
    }
    
    // Pole s daty
    if (is_array($value)) {
        // Pro pole typu zákazníka - zobraz vybrané + tooltip s všemi možnostmi
        if (strpos($key, 'customerType') !== false) {
            $type_labels = [
                'industrial' => '🏭 Průmysl',
                'commercial' => '🏢 Komerční objekt', 
                'services' => '🚚 Služby / Logistika',
                'agriculture' => '🌾 Zemědělství',
                'public' => '🏛️ Veřejný sektor',
                'other' => '❓ Jiný'
            ];
            
            $selected_types = [];
            $all_options = [];
            
            foreach ($type_labels as $type => $label) {
                $is_selected = !empty($value[$type]);
                if ($is_selected) {
                    $selected_types[] = $label;
                }
                $all_options[] = ($is_selected ? '✅ ' : '⚪ ') . $label;
            }
            
            // Přidej specifikaci pro "jiný" typ
            if (!empty($value['otherSpecification'])) {
                $selected_types[] = '📝 Specifikace: ' . htmlspecialchars($value['otherSpecification']);
            }
            
            $tooltip_content = implode('\n', $all_options);
            
            return '<div class="relative group">
                        <div class="flex flex-wrap gap-2">' . 
                        (!empty($selected_types) ? 
                            implode('', array_map(function($type) { return '<div class="bg-blue-100 px-3 py-1 rounded-full text-sm font-medium text-blue-800 flex items-center">' . $type . '</div>'; }, $selected_types)) : 
                            '<span class="text-gray-400 italic">Nevyplněno</span>') . 
                        '</div>
                        <div class="absolute bottom-full left-0 mb-2 px-3 py-2 bg-gray-800 text-white text-sm rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 whitespace-pre-line min-w-64">
                            Všechny možnosti:\n' . $tooltip_content . '
                        </div>
                    </div>';
        }
        
        // Pro výběr cílů - zobraz vybrané + tooltip s všemi možnostmi
        if (strpos($key, 'goals') !== false || strpos($key, 'Goals') !== false) {
            $goal_labels = [
                'energyIndependence' => '🔋 Energetická nezávislost',
                'costSaving' => '💰 Úspora nákladů',
                'backupPower' => '⚡ Záložní napájení',
                'peakShaving' => '📊 Peak shaving',
                'gridStabilization' => '🏗️ Stabilizace sítě',
                'environmentalBenefit' => '🌱 Ekologický přínos',
                'other' => '❓ Jiné'
            ];
            
            $selected_goals = [];
            $all_options = [];
            
            foreach ($goal_labels as $goalKey => $label) {
                $is_selected = !empty($value[$goalKey]);
                if ($is_selected) {
                    $selected_goals[] = $label;
                }
                $all_options[] = ($is_selected ? '✅ ' : '⚪ ') . $label;
            }
            
            $tooltip_content = implode('\n', $all_options);
            
            return '<div class="relative group">
                        <div class="space-y-2">' . 
                        (!empty($selected_goals) ? 
                            implode('', array_map(function($goal) { return '<div class="text-sm bg-green-50 px-3 py-2 rounded-lg border-l-4 border-green-400">' . $goal . '</div>'; }, $selected_goals)) : 
                            '<span class="text-gray-400 italic">Nevyplněno</span>') . 
                        '</div>
                        <div class="absolute bottom-full left-0 mb-2 px-3 py-2 bg-gray-800 text-white text-sm rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 whitespace-pre-line min-w-64">
                            Všechny možnosti:\n' . $tooltip_content . '
                        </div>
                    </div>';
        }
        
        // Pro použití plynu - zobraz vybrané + tooltip s všemi možnostmi
        if (strpos($key, 'gasUsage') !== false) {
            $usage_labels = [
                'heating' => '🔥 Vytápění',
                'hotWater' => '🚿 Ohřev vody',
                'technology' => '🏭 Technologie/výroba',
                'cooking' => '👨‍🍳 Vaření'
            ];
            
            $selected_usages = [];
            $all_options = [];
            
            foreach ($usage_labels as $usage => $label) {
                $is_selected = !empty($value[$usage]);
                if ($is_selected) {
                    $selected_usages[] = $label;
                }
                $all_options[] = ($is_selected ? '✅ ' : '⚪ ') . $label;
            }
            
            $tooltip_content = implode('\n', $all_options);
            
            return '<div class="relative group">
                        <div class="space-y-1">' . 
                        (!empty($selected_usages) ? 
                            implode('', array_map(function($usage) { return '<div class="text-sm bg-yellow-50 px-3 py-2 rounded-lg border-l-4 border-yellow-400">' . $usage . '</div>'; }, $selected_usages)) : 
                            '<span class="text-gray-400 italic">Nevyplněno</span>') . 
                        '</div>
                        <div class="absolute bottom-full left-0 mb-2 px-3 py-2 bg-gray-800 text-white text-sm rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 whitespace-pre-line min-w-48">
                            Všechny možnosti:\n' . $tooltip_content . '
                        </div>
                    </div>';
        }
        
        // Pro typy dokumentace - zobraz vybrané + tooltip s všemi možnostmi  
        if (strpos($key, 'documentationTypes') !== false) {
            $doc_labels = [
                'sitePlan' => '🗺️ Situacni plan arealu',
                'electricalPlan' => '⚡ Elektricka dokumentace',
                'buildingPlan' => '🏗️ Pudorysy budov',
                'other' => '📄 Jina dokumentace'
            ];
            
            $selected_docs = [];
            $all_options = [];
            
            foreach ($doc_labels as $docType => $label) {
                $is_selected = !empty($value[$docType]);
                if ($is_selected) {
                    $selected_docs[] = $label;
                }
                $all_options[] = ($is_selected ? '✅ ' : '⚪ ') . $label;
            }
            
            $tooltip_content = implode('\n', $all_options);
            
            return '<div class="relative group">
                        <div class="space-y-1">' . 
                        (!empty($selected_docs) ? 
                            implode('', array_map(function($doc) { return '<div class="text-sm bg-purple-50 px-3 py-2 rounded-lg border-l-4 border-purple-400">' . $doc . '</div>'; }, $selected_docs)) : 
                            '<span class="text-gray-400 italic">Nevyplněno</span>') . 
                        '</div>
                        <div class="absolute bottom-full left-0 mb-2 px-3 py-2 bg-gray-800 text-white text-sm rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 whitespace-pre-line min-w-56">
                            Všechny možnosti:\n' . $tooltip_content . '
                        </div>
                    </div>';
        }
        
        // Pro dodatečné kontakty
        if (strpos($key, 'additionalContacts') !== false) {
            $contacts_html = '<div class="space-y-3">';
            foreach ($value as $contact) {
                if (!is_array($contact)) continue;
                $is_primary = !empty($contact['isPrimary']) ? ' 👑 Primární' : '';
                $contacts_html .= '<div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-user-tie text-blue-600 mr-2"></i>
                        <span class="font-medium">' . htmlspecialchars($contact['name'] ?? '') . '</span>
                        <span class="text-blue-600 text-sm ml-2">' . htmlspecialchars($contact['position'] ?? '') . $is_primary . '</span>
                    </div>
                    <div class="text-sm text-gray-600 space-y-1">
                        <div><i class="fas fa-phone mr-2"></i>' . htmlspecialchars($contact['phone'] ?? '') . '</div>
                        <div><i class="fas fa-envelope mr-2"></i>' . htmlspecialchars($contact['email'] ?? '') . '</div>
                    </div>
                </div>';
            }
            $contacts_html .= '</div>';
            
            return $contacts_html;
        }
        
        // Pro soubory a obrázky (file uploads)
        $file_fields = ['sitePhotos', 'photos', 'visualizations', 'projectDocumentationFiles', 
                        'distributionCurvesFile', 'billingDocuments', 'cogenerationPhotos',
                        'connectionContractFile', 'connectionApplicationFile', 'auditDocuments',
                        'projectDocuments'];
        if (in_array($key, $file_fields) || strpos($key, 'File') !== false || strpos($key, 'Photos') !== false || strpos($key, 'Documents') !== false) {
            return formatFileUploads($key, $value);
        }
        
        // Pro detaily společnosti z MERK
        if (strpos($key, 'companyDetails') !== false) {
            $details_html = '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-2">';
            
            $detail_labels = [
                'legal_form' => '🏢 Právní forma',
                'estab_date' => '📅 Datum vzniku', 
                'is_vatpayer' => '💰 Plátce DPH',
                'status' => '📊 Status',
                'court' => '⚖️ Soud',
                'court_file' => '📁 Spisová značka',
                'industry' => '🏭 Odvětví',
                'magnitude' => '👥 Velikost',
                'turnover' => '💵 Obrat',
                'years_in_business' => '📈 Roky v podnikání',
                'databox_id' => '📮 ID datové schránky'
            ];
            
            foreach ($value as $field => $val) {
                if (!empty($val) && $val !== false) {
                    $label = $detail_labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
                    
                    // Speciální formátování pro datum
                    if ($field === 'estab_date' && strpos($val, 'T') !== false) {
                        $val = date('d.m.Y', strtotime($val));
                    }
                    
                    $details_html .= '<div class="flex items-start">
                        <span class="font-medium text-gray-700 mr-2">' . $label . ':</span>
                        <span class="text-gray-900">' . htmlspecialchars(is_scalar($val) ? (string)$val : json_encode($val, JSON_UNESCAPED_UNICODE)) . '</span>
                    </div>';
                }
            }
            
            $details_html .= '</div>';
            return $details_html;
        }
        
        // Pro obecná pole - bezpečné formátování bez array-to-string conversion
        $formatted = [];
        foreach ($value as $k => $v) {
            if ($v !== null && $v !== false && $v !== '') {
                // Bezpečně převést hodnotu na string
                if (is_array($v)) {
                    // Rekurzivně zpracovat vnořené pole
                    $nested_items = [];
                    foreach ($v as $nk => $nv) {
                        if ($nv !== null && $nv !== false && $nv !== '') {
                            if (is_scalar($nv)) {
                                $nested_items[] = is_string($nk) ? "$nk: $nv" : (string)$nv;
                            }
                        }
                    }
                    if (!empty($nested_items)) {
                        $formatted[] = is_string($k) ? "$k: " . implode(', ', $nested_items) : implode(', ', $nested_items);
                    }
                } elseif (is_scalar($v)) {
                    $formatted[] = is_string($k) ? "$k: $v" : (string)$v;
                }
            }
        }
        return !empty($formatted) ? 
            '<div class="bg-gray-100 rounded p-2 text-sm max-w-lg">' . implode('<br>', array_map('htmlspecialchars', $formatted)) . '</div>' :
            '<span class="text-gray-400 italic">Nevyplněno</span>';
    }
    
    // Telefonní čísla
    if (strpos($key, 'phone') !== false || strpos($key, 'Phone') !== false) {
        return '<a href="tel:' . htmlspecialchars($value) . '" class="text-blue-600 hover:underline flex items-center">
                    <i class="fas fa-phone mr-1"></i>' . htmlspecialchars($value) . '</a>';
    }
    
    // Emailové adresy
    if (strpos($key, 'email') !== false || strpos($key, 'Email') !== false) {
        return '<a href="mailto:' . htmlspecialchars($value) . '" class="text-blue-600 hover:underline flex items-center">
                    <i class="fas fa-envelope mr-1"></i>' . htmlspecialchars($value) . '</a>';
    }
    
    // Adresy
    if (strpos($key, 'address') !== false || strpos($key, 'Address') !== false) {
        $addr_value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
        return '<div class="flex items-start max-w-sm">
                    <i class="fas fa-map-marker-alt text-red-500 mr-2 mt-1 flex-shrink-0"></i>
                    <span class="text-gray-900">' . htmlspecialchars($addr_value) . '</span>
                </div>';
    }
    
    // Číselné hodnoty s jednotkami
    if (strpos($key, 'Power') !== false || strpos($key, 'power') !== false) {
        $numeric_val = is_numeric($value) ? (float)$value : 0;
        return '<span class="font-medium text-blue-600">' . number_format($numeric_val, 0, ',', ' ') . '</span> <span class="text-gray-500 text-sm">kW</span>';
    }
    
    if (strpos($key, 'Consumption') !== false || strpos($key, 'consumption') !== false) {
        $numeric_val = is_numeric($value) ? (float)$value : 0;
        return '<span class="font-medium text-green-600">' . number_format($numeric_val, 0, ',', ' ') . '</span> <span class="text-gray-500 text-sm">kWh</span>';
    }
    
    // Zajistit že hodnota je string pro další zpracování
    if (!is_string($value)) {
        if (is_array($value)) {
            // Pole které nebylo zachyceno výše - zobrazit jako JSON
            return '<div class="bg-gray-100 rounded p-2 text-sm max-w-lg font-mono text-xs overflow-auto">' . htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</div>';
        }
        $value = (string)$value;
    }
    
    // Dlouhé texty
    if (strlen($value) > 100 || strpos($key, 'note') !== false || strpos($key, 'description') !== false || strpos($key, 'detail') !== false) {
        return '<div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 rounded max-w-2xl">
                    <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">' . htmlspecialchars($value) . '</div>
                </div>';
    }
    
    // Číselné hodnoty
    if (is_numeric($value)) {
        return '<span class="font-medium text-blue-600">' . number_format((float)$value, 0, ',', ' ') . '</span>';
    }
    
    // Kontrola délky textu pro zabránění rozbití layoutu
    $maxLength = 100;
    if (strlen($value) > $maxLength) {
        $truncated = substr($value, 0, $maxLength);
        return '<div class="text-gray-900 relative group">
                    <div class="truncate">' . htmlspecialchars($truncated) . '...</div>
                    <div class="absolute bottom-full left-0 mb-2 p-3 bg-gray-800 text-white text-sm rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 max-w-xs whitespace-normal">
                        ' . htmlspecialchars($value) . '
                    </div>
                </div>';
    }
    
    // Výchozí formátování
    return '<span class="text-gray-900">' . htmlspecialchars($value) . '</span>';
}

// Status funkce
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

function getStatusLabel($status) {
    switch($status) {
        case 'draft': return 'Rozpracovaný';
        case 'submitted': return 'Odeslaný';
        case 'processing': return 'Zpracovává se';
        case 'completed': return 'Dokončený';
        case 'cancelled': return 'Zrušený';
        default: return ucfirst($status);
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail formuláře #<?= htmlspecialchars($form_id) ?> - Electree Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e'
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .step-card {
            transition: all 0.3s ease;
            align-self: stretch;
            min-height: 500px;
            display: flex;
            flex-direction: column;
        }
        .step-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .step-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .field-item {
            transition: all 0.2s ease;
        }
        .field-item:hover {
            background-color: rgb(249 250 251);
            transform: translateX(2px);
        }
        
        /* Truncate dlouhých textů */
        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* KRITICKÉ CSS pro 3-column grid layout */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            align-items: stretch;
        }
        
        /* Responzivní breakpoints */
        @media (max-width: 1024px) {
            .steps-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .steps-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    
    <!-- Header -->
    <div class="bg-white shadow-sm border-b sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="/admin-forms.php" class="text-gray-500 hover:text-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Zpět na seznam
                    </a>
                    <div class="h-6 border-l border-gray-300"></div>
                    <h1 class="text-xl font-semibold text-gray-900">
                        Formulář #<?= htmlspecialchars($form_id) ?>
                    </h1>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="px-3 py-1 text-sm font-medium rounded-full <?= getStatusClass($form_data['status']) ?>">
                        <?= getStatusLabel($form_data['status']) ?>
                    </span>
                    <button onclick="window.print()" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors text-sm">
                        <i class="fas fa-print mr-2"></i>Tisk
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        
        <!-- Záhlaví formuláře -->
        <div class="bg-white rounded-2xl shadow-lg mb-8 overflow-hidden">
            <div class="bg-gradient-to-r from-primary-600 to-primary-800 px-8 py-6">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center mb-4">
                            <i class="fas fa-file-alt text-4xl mr-4 opacity-90 text-white"></i>
                            <div>
                                <h1 class="text-3xl font-bold text-white">Bateriové úložiště</h1>
                                <p class="text-primary-100 text-lg">Formulář pro návrh řešení</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-white/90">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span class="text-sm">Vytvořeno: <?= date('d.m.Y H:i', strtotime($form_data['created_at'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-clock mr-2"></i>
                                <span class="text-sm">Upraveno: <?= date('d.m.Y H:i', strtotime($form_data['updated_at'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-user mr-2"></i>
                                <span class="text-sm">Uživatel ID: <?= htmlspecialchars($form_data['user_id'] ?? 'Neznámý') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kroky formuláře -->
        <div class="steps-grid">
            <?php for($step = 1; $step <= 8; $step++): ?>
                <?php 
                    $step_data = $organized_data[$step] ?? [];
                    // OPRAVA: Zobraz všechny kroky - i prázdné!
                    // Krok 8 se zobrazí vždy
                ?>
                
                <div class="step-card bg-white rounded-xl shadow-lg overflow-hidden">
                    <!-- Header kroku -->
                    <div class="bg-gradient-to-r <?= getStepGradient($step) ?> px-6 py-4">
                        <div class="flex items-center">
                            <div class="bg-white/20 rounded-full p-3 mr-4">
                                <i class="<?= getStepIcon($step) ?> text-2xl text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-white">Krok <?= $step ?></h2>
                                <p class="text-white/90"><?= $step_names[$step] ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Obsah kroku -->
                    <div class="step-content p-6">
                        <?php if (!empty($step_notes[$step])): ?>
                            <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                <div class="flex items-start">
                                    <i class="fas fa-sticky-note text-amber-500 mr-2 mt-0.5"></i>
                                    <div>
                                        <h4 class="font-semibold text-amber-800 text-sm">Poznámka ke kroku</h4>
                                        <p class="text-amber-700 text-sm mt-1"><?= htmlspecialchars($step_notes[$step]) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="space-y-4">
                            <?php foreach ($step_data as $field_key => $field_value): ?>
                                <?php if ($field_key === 'stepNotes' || empty($field_value)) continue; ?>
                                <div class="field-item p-3 rounded-lg border border-gray-100">
                                    <div class="flex items-start">
                                        <i class="<?= getFieldIcon($field_key) ?> text-gray-400 mr-3 mt-1 flex-shrink-0"></i>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-gray-700 text-sm mb-1">
                                                <?= getFieldLabel($field_key) ?>
                                            </div>
                                            <div class="text-gray-900">
                                                <?= formatFieldValue($field_key, $field_value) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Nahrané soubory -->
        <?php
        // Načtení souborů z databáze
        $files_stmt = $conn->prepare("
            SELECT id, field_name, original_name, file_name, file_path, file_size, mime_type, thumbnail_path, uploaded_at 
            FROM form_files 
            WHERE form_id = ? AND deleted_at IS NULL 
            ORDER BY field_name, uploaded_at ASC
        ");
        $files_stmt->bind_param("s", $form_id);
        $files_stmt->execute();
        $files_result = $files_stmt->get_result();
        $uploaded_files = [];
        while ($file = $files_result->fetch_assoc()) {
            $field = $file['field_name'];
            if (!isset($uploaded_files[$field])) {
                $uploaded_files[$field] = [];
            }
            $uploaded_files[$field][] = $file;
        }
        $files_stmt->close();

        // Názvy polí souborů
        $file_field_labels = [
            'sitePhotos' => 'Fotografie místa instalace',
            'visualizations' => 'Vizualizace a nákresy',
            'projectDocumentationFiles' => 'Projektová dokumentace',
            'distributionCurvesFile' => 'Odběrové křivky',
            'billingDocuments' => 'Doklady o vyúčtování',
            'cogenerationPhotos' => 'Fotografie kogenerační jednotky'
        ];

        // Formátování velikosti souboru
        function formatFileSizeAdmin($bytes) {
            if ($bytes == 0) return '0 B';
            $units = ['B', 'KB', 'MB', 'GB'];
            $factor = floor(log($bytes) / log(1024));
            return round($bytes / pow(1024, $factor), 2) . ' ' . $units[$factor];
        }

        // Zjištění, zda je soubor obrázek
        function isImageMimeType($mimeType) {
            return strpos($mimeType, 'image/') === 0;
        }
        ?>

        <?php if (!empty($uploaded_files)): ?>
        <div class="mt-8 bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-teal-500 to-teal-600 px-8 py-6">
                <div class="flex items-center">
                    <div class="bg-white/20 rounded-full p-3 mr-4">
                        <i class="fas fa-paperclip text-2xl text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-white">Nahrané soubory</h2>
                        <p class="text-teal-100">Přílohy a dokumentace k formuláři</p>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <?php foreach ($uploaded_files as $field_name => $files): ?>
                    <div class="mb-6 last:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-folder-open text-teal-500 mr-2"></i>
                            <?= htmlspecialchars($file_field_labels[$field_name] ?? ucfirst($field_name)) ?>
                            <span class="ml-2 text-sm font-normal text-gray-500">(<?= count($files) ?>)</span>
                        </h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            <?php foreach ($files as $file): ?>
                                <?php 
                                    $isImage = isImageMimeType($file['mime_type'] ?? '');
                                    $fileUrl = '/public/serve-file.php?id=' . urlencode($file['id']);
                                    $thumbUrl = $file['thumbnail_path'] ? '/public/serve-file.php?id=' . urlencode($file['id']) . '&thumb=1' : null;
                                ?>
                                <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow bg-white">
                                    <?php if ($isImage): ?>
                                        <!-- Náhled obrázku -->
                                        <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="block aspect-video bg-gray-100 relative group">
                                            <img 
                                                src="<?= htmlspecialchars($thumbUrl ?? $fileUrl) ?>" 
                                                alt="<?= htmlspecialchars($file['original_name']) ?>"
                                                class="w-full h-full object-cover"
                                                loading="lazy"
                                            />
                                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                                                <i class="fas fa-search-plus text-white text-2xl opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <!-- Ikona pro ne-obrázky -->
                                        <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="block aspect-video bg-gray-50 flex items-center justify-center group">
                                            <?php
                                                $extension = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                                                $iconClass = 'fa-file';
                                                if ($extension === 'pdf') $iconClass = 'fa-file-pdf text-red-500';
                                                elseif (in_array($extension, ['doc', 'docx'])) $iconClass = 'fa-file-word text-blue-500';
                                                elseif (in_array($extension, ['xls', 'xlsx', 'csv'])) $iconClass = 'fa-file-excel text-green-500';
                                                elseif (in_array($extension, ['dwg', 'dxf'])) $iconClass = 'fa-drafting-compass text-orange-500';
                                            ?>
                                            <i class="fas <?= $iconClass ?> text-5xl group-hover:scale-110 transition-transform"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Info o souboru -->
                                    <div class="p-3">
                                        <p class="text-sm font-medium text-gray-900 truncate" title="<?= htmlspecialchars($file['original_name']) ?>">
                                            <?= htmlspecialchars($file['original_name']) ?>
                                        </p>
                                        <div class="flex items-center justify-between mt-1">
                                            <span class="text-xs text-gray-500">
                                                <?= formatFileSizeAdmin($file['file_size']) ?>
                                            </span>
                                            <span class="text-xs text-gray-400">
                                                <?= date('d.m.Y', strtotime($file['uploaded_at'])) ?>
                                            </span>
                                        </div>
                                        <a 
                                            href="<?= htmlspecialchars($fileUrl) ?>" 
                                            download="<?= htmlspecialchars($file['original_name']) ?>"
                                            class="mt-2 w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-teal-700 bg-teal-50 rounded hover:bg-teal-100 transition-colors"
                                        >
                                            <i class="fas fa-download mr-1"></i>
                                            Stáhnout
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>