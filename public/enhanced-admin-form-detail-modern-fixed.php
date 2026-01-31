<?php
require_once 'db_connection.php';
require_once 'auth.php';

// Získání ID formuláře z URL
$form_id = $_GET['id'] ?? null;

if (!$form_id) {
    echo "ID formuláře nebylo poskytnuto";
    exit();
}

// Načtení dat z databáze
$stmt = $conn->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->bind_param("i", $form_id);
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
        
        // Krok 5 - Cíle a optimalizace
        'goals' => 'Hlavní cíle bateriového úložiště',
        'priority1' => 'Priorita č. 1',
        'priority2' => 'Priorita č. 2', 
        'priority3' => 'Priorita č. 3',
        
        // Krok 6 - Místo realizace a infrastruktura
        'hasOutdoorSpace' => 'Venkovní prostory',
        'outdoorSpaceDetails' => 'Detaily venkovních prostor',
        'hasIndoorSpace' => 'Vnitřní prostory',
        'indoorSpaceDetails' => 'Detaily vnitřních prostor',
        'accessibility' => 'Přístupnost lokality',
        'hasProjectDocumentation' => 'Projektová dokumentace',
        'documentationTypes' => 'Typy dostupné dokumentace',
        'projectDocuments' => 'Projektová dokumentace (soubory)',
        'sitePlan' => 'Situační plán areálu',
        'electricalPlan' => 'Elektrická dokumentace',
        'buildingPlan' => 'Půdorysy budov',
        'otherDocumentation' => 'Jiná dokumentace',
        
        // Krok 7 - Připojení k síti a legislativa  
        'gridConnectionPlanned' => 'Připojení k DS/ČEPS',
        'powerIncreaseRequested' => 'Navýšení rezervovaného příkonu',
        'requestedPowerIncrease' => 'Požadované navýšení příkonu (kW)',
        'requestedOutputIncrease' => 'Požadované navýšení výkonu (kW)',
        'connectionApplicationBy' => 'Žádost o připojení podá',
        'willingToSignPowerOfAttorney' => 'Ochoten podepsat plnou moc',
        'hasEnergeticSpecialist' => 'Energetický specialista',
        'specialistPosition' => 'Pozice specialisty',
        'energeticSpecialist' => 'Jméno energetického specialisty',
        'energeticSpecialistContact' => 'Kontakt na specialistu',
        'proposedSteps' => 'Navrhované kroky',
        
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
        'otherConsumption' => 'Další spotřeby',
        
        // Poznámky a soubory
        'notes' => 'Poznámky',
        'stepNotes' => 'Poznámky ke kroku',
        'fileUploads' => 'Nahraté soubory',
        'budgetMin' => 'Minimální rozpočet',
        'budgetMax' => 'Maximální rozpočet',
        'timeframeStart' => 'Začátek realizace',
        'timeframeEnd' => 'Konec realizace'
    ];
    
    return $labels[$key] ?? ucfirst(str_replace(['_', 'Type', 'Has', 'Is'], [' ', ' typ', 'Má ', 'Je '], $key));
}

// Funkce pro formatování hodnot s českými překlady
function formatFieldValue($key, $value) {
    // Prázdné hodnoty
    if (is_null($value) || $value === '' || $value === false || (is_array($value) && empty($value))) {
        return '<span class="text-gray-400 italic flex items-center"><i class="fas fa-minus-circle mr-1"></i>Nevyplněno</span>';
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
        
        // Přístupnost
        'unlimited' => 'Bez omezení',
        'limited' => 'Omezený',
        
        // Způsob vyúčtování elektřiny
        'spot' => 'Spotová cena',
        'fix' => 'Fixní cena',
        'combined' => 'Kombinace fix/spot',
        'gradual' => 'Postupná fixace',
        
        // Žádost o připojení - kdo podá
        'customer' => 'Zákazník sám',
        'electree' => 'Firma Electree na základě plné moci',
        'undecided' => 'Ještě nerozhodnuto',
        
        // Pozice energetického specialisty
        'specialist' => 'Specialista',
        'manager' => 'Správce',
        
        // Důležitost ceny
        'very-important' => 'Velmi důležitá',
        'important' => 'Důležitá',
        'neutral' => 'Neutrální',
        'less-important' => 'Méně důležitá',
        'not-important' => 'Nedůležitá'
    ];
    
    // Použití překladu pokud existuje
    if (isset($translations[strtolower($value)])) {
        $translatedValue = $translations[strtolower($value)];
        if (in_array($translatedValue, ['Ano', 'Ne'])) {
            return $translatedValue === 'Ano' ? 
                '<span class="text-emerald-600 font-medium flex items-center"><i class="fas fa-check-circle mr-1"></i>Ano</span>' : 
                '<span class="text-red-600 font-medium flex items-center"><i class="fas fa-times-circle mr-1"></i>Ne</span>';
        }
        return '<span class="font-medium text-blue-600">' . htmlspecialchars($translatedValue) . '</span>';
    }
    
    // Pole s daty
    if (is_array($value)) {
        // Pro pole typu zákazníka
        if (strpos($key, 'customerType') !== false) {
            $types = [];
            foreach ($value as $type => $selected) {
                if ($selected) {
                    $type_labels = [
                        'industrial' => '🏭 Průmysl',
                        'commercial' => '🏢 Komerční objekt', 
                        'services' => '🚚 Služby / Logistika',
                        'agriculture' => '🌾 Zemědělství',
                        'public' => '🏛️ Veřejný sektor',
                        'other' => '❓ Jiný'
                    ];
                    $types[] = $type_labels[$type] ?? ucfirst($type);
                }
            }
            return !empty($types) ? '<div class="flex flex-wrap gap-1">' . implode('</div><div class="bg-blue-100 px-2 py-1 rounded text-sm">', $types) . '</div>' : '<span class="text-gray-400 italic">Nevyplněno</span>';
        }
        
        // Pro výběr cílů
        if (strpos($key, 'goals') !== false || strpos($key, 'Goals') !== false) {
            $goals = [];
            foreach ($value as $goalKey => $selected) {
                if ($selected) {
                    $goal_labels = [
                        'energyIndependence' => 'Energetická nezávislost',
                        'costSaving' => 'Úspora nákladů',
                        'backupPower' => 'Záložní napájení',
                        'peakShaving' => 'Peak shaving',
                        'gridStabilization' => 'Stabilizace sítě',
                        'environmentalBenefit' => 'Ekologický přínos',
                        'other' => 'Jiné'
                    ];
                    $goals[] = $goal_labels[$goalKey] ?? ucfirst(str_replace('_', ' ', $goalKey));
                }
            }
            return !empty($goals) ? '<div class="space-y-1">' . implode('</div><div class="text-sm bg-green-50 px-2 py-1 rounded">', $goals) . '</div>' : '<span class="text-gray-400 italic">Nevyplněno</span>';
        }
        
        // Pro priority
        if (strpos($key, 'priority') !== false && is_string($value)) {
            $priority_labels = [
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
                'other' => 'Jiný účel'
            ];
            $priority_text = $priority_labels[$value] ?? $value;
            return '<div class="bg-orange-100 px-3 py-2 rounded-lg text-orange-800 font-medium">' . htmlspecialchars($priority_text) . '</div>';
        }
        
        // Pro použití plynu
        if (strpos($key, 'gasUsage') !== false) {
            $usages = [];
            foreach ($value as $usage => $selected) {
                if ($selected) {
                    $usage_labels = [
                        'heating' => 'Vytápění',
                        'hotWater' => 'Ohřev vody',
                        'technology' => 'Technologie/výroba',
                        'cooking' => 'Vaření'
                    ];
                    $usages[] = $usage_labels[$usage] ?? ucfirst($usage);
                }
            }
            return !empty($usages) ? '<div class="space-y-1">' . implode('</div><div class="text-sm bg-yellow-50 px-2 py-1 rounded">', $usages) . '</div>' : '<span class="text-gray-400 italic">Nevyplněno</span>';
        }
        
        // Pro obecná pole
        $formatted = [];
        foreach ($value as $k => $v) {
            if ($v && $v !== false && $v !== '') {
                $formatted[] = is_string($k) ? "$k: $v" : $v;
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
        return '<div class="flex items-start max-w-sm">
                    <i class="fas fa-map-marker-alt text-red-500 mr-2 mt-1 flex-shrink-0"></i>
                    <span class="text-gray-900">' . htmlspecialchars($value) . '</span>
                </div>';
    }
    
    // Číselné hodnoty s jednotkami
    if (strpos($key, 'Power') !== false || strpos($key, 'power') !== false) {
        return '<span class="font-medium text-blue-600">' . number_format((float)$value, 0, ',', ' ') . '</span> <span class="text-gray-500 text-sm">kW</span>';
    }
    
    if (strpos($key, 'Consumption') !== false || strpos($key, 'consumption') !== false) {
        return '<span class="font-medium text-green-600">' . number_format((float)$value, 0, ',', ' ') . '</span> <span class="text-gray-500 text-sm">kWh</span>';
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
        }
        .step-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .field-item {
            transition: all 0.2s ease;
        }
        .field-item:hover {
            background-color: rgb(249 250 251);
            transform: translateX(2px);
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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php for($step = 1; $step <= 8; $step++): ?>
                <?php 
                    $step_data = $decoded_data[$step] ?? [];
                    if (empty($step_data)) continue;
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
                    <div class="p-6">
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
                                    $fileUrl = '/public/' . $file['file_path'];
                                    $thumbUrl = $file['thumbnail_path'] ? '/public/' . $file['thumbnail_path'] : null;
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
