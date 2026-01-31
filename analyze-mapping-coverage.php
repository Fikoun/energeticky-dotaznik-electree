<?php
/**
 * Script to analyze form fields vs AUTO_MAPPING coverage
 * 
 * This compares all fields from React form steps with the AUTO_MAPPING constant
 * to identify which fields are mapped and which are missing.
 */

require_once __DIR__ . '/includes/Raynet/RaynetCustomFields.php';

use Raynet\RaynetCustomFields;

// All form fields from React components (from the agent's analysis)
$allFormFields = [
    // Step 1: Customer Identification
    'step1' => [
        'companyName', 'ico', 'dic', 'contactPerson', 'email', 'phone',
        'address', 'companyAddress', 'customerType', 'customerTypeOther',
        'companyDetails', // object: legal_form, estab_date, is_vatpayer, status, court, court_file, industry, magnitude, turnover, years_in_business, databox_id
        'additionalContacts', // array of contact objects
        'raynetCompanyId', 'raynetPersonId', 'raynetSyncEnabled',
        'raynetSyncStatus', 'ean', 'eic', 'eanGas',
    ],
    
    // Step 2: Connection Point Parameters
    'step2' => [
        'hasFveVte', 'fveVtePower', 'fveVteYear', 'fveVteType',
        'accumulationPercentage', 'interestedInFveVte', 'interestedInInstallationProcessing',
        'interestedInElectromobility', 'hasTransformer', 'transformerPower', 'transformerVoltage',
        'transformerYear', 'transformerCount', 'transformerOwnership', 'transformerCooling',
        'circuitBreakerType', 'circuitBreakerValue', 'mainCircuitBreaker', 'reservedPower',
        'distributionTerritory', 'connectionVoltage', 'lvDistributionBoard', 'monthlyConsumption',
        'reservedOutput', 'coolingType', 'customCircuitBreaker',
    ],
    
    // Step 3: Energy Needs
    'step3' => [
        'measurementType', 'distributionCurvesFile', 'hasQuarterHourData',
        'consumptionDataSource', 'averageLoad', 'peakLoad', 'lowLoad',
        // TimeSlider patterns (weekday + weekend)
        'weekdayPattern', // object with morningPeak*, noonLow*, afternoonPeak*, nightLow*, q1-q4Consumption
        'weekendPattern', // object with same structure
        'yearlyConsumption', 'dailyAverageConsumption', 'maxConsumption', 'minConsumption',
        'hasCriticalConsumption', 'criticalConsumptionDescription', 'energyAccumulation',
        'energyAccumulationAmount', 'batteryCycles', 'requiresBackup', 'backupDescription',
        'backupDuration', 'backupDurationHours', 'priceOptimization', 'hasElectricityProblems',
        'electricityProblemsDetails', 'hasEnergyAudit', 'energyAuditDetails',
        'hasOwnEnergySource', 'ownEnergySourceDetails',
    ],
    
    // Step 4: Goals and Expectations
    'step4' => [
        'goals', // array: energyIndependence, costSaving, backupPower, peakShaving, gridStabilization, environmentalBenefit, other
        'priority1', 'priority2', 'priority3',
        'otherGoal', 'otherPurposeDescription', 'goalDetails',
        'expectedRoi', 'budgetRange', 'timeline',
    ],
    
    // Step 5: Infrastructure and Space
    'step5' => [
        'sitePhotos', // file upload
        'visualizations', // file upload
        'siteDescription', 'hasOutdoorSpace', 'outdoorSpaceSize',
        'hasIndoorSpace', 'indoorSpaceType', 'indoorSpaceSize',
        'accessibility', 'accessibilityLimitations', 'floorLoadCapacity',
        'projectDocumentationFiles', // file upload
        'documentationTypes', // object: sitePlan, electricalPlan, buildingPlan, other
        'infrastructureNotes',
    ],
    
    // Step 6: Operational and Legislative Framework
    'step6' => [
        'gridConnectionPlanned', 'powerIncreaseRequested', 'requestedPowerIncrease',
        'requestedOutputIncrease', 'connectionContractFile', 'connectionApplicationFile',
        'connectionApplicationBy', 'willingToSignPowerOfAttorney',
        'hasEnergeticSpecialist', 'specialistName', 'specialistPosition',
        'specialistPhone', 'specialistEmail', 'legislativeNotes',
    ],
    
    // Step 7: Proposed Procedure and Notes
    'step7' => [
        'proposedSteps', // object: preliminary, technical, detailed, consultancy, support, other, otherDescription
        'additionalNotes',
        'agreements', // object: dataProcessing, technicalVisit, marketing
        'urgency',
    ],
    
    // Step 8: Energy Questionnaire
    'step8' => [
        'billingMethod', 'spotSurcharge', 'fixPrice', 'currentEnergyPrice',
        'energySupplier', 'gradualFixPrice', 'gradualSpotSurcharge',
        'billingDocuments', // file upload
        'energySharing', 'priceImportance', 'hasSharing', 'sharingDetails',
        'hasGas', 'gasConsumption', 'gasBill',
        'gasUsage', // object: heating, hotWater, technology, cooking
        'hotWaterConsumption', 'steamConsumption', 'otherConsumption',
        'hasCogeneration', 'cogenerationDetails', 'cogenerationPhotos', // file upload
        'energyNotes',
    ],
    
    // Metadata fields
    'metadata' => [
        'stepNotes', // object with keys 1-8
        'uploadedFiles', // object with file references
        'submittedBy', // object: id, name, email
        'formId', 'formSubmittedAt', 'gdprToken',
    ],
];

// Flatten all fields
$flatFields = [];
foreach ($allFormFields as $step => $fields) {
    foreach ($fields as $field) {
        $flatFields[$field] = $step;
    }
}

// Get AUTO_MAPPING
$autoMapping = RaynetCustomFields::getAutoMapping();
$formFields = RaynetCustomFields::FORM_FIELDS;

// Analyze coverage
$mapped = [];
$notMapped = [];
$inMappingNotInForm = [];

foreach ($flatFields as $field => $step) {
    if (isset($autoMapping[$field])) {
        $mapped[$field] = [
            'step' => $step,
            'target' => $autoMapping[$field]['target'],
            'inFormFields' => isset($formFields[$field]),
        ];
    } else {
        $notMapped[$field] = $step;
    }
}

// Check for fields in AUTO_MAPPING that aren't in form
foreach ($autoMapping as $field => $config) {
    if (!isset($flatFields[$field])) {
        $inMappingNotInForm[$field] = $config['target'];
    }
}

// Output results
echo "=======================================================\n";
echo "FORM FIELDS VS AUTO_MAPPING ANALYSIS\n";
echo "=======================================================\n\n";

echo "✅ MAPPED FIELDS (" . count($mapped) . "):\n";
echo str_repeat("-", 60) . "\n";
foreach ($mapped as $field => $info) {
    $formFieldStatus = $info['inFormFields'] ? '✓' : '✗';
    printf("  %-35s | %-8s | target: %-7s | FORM_FIELDS: %s\n", 
        $field, $info['step'], $info['target'], $formFieldStatus);
}

echo "\n\n❌ NOT MAPPED FIELDS (" . count($notMapped) . "):\n";
echo str_repeat("-", 60) . "\n";
foreach ($notMapped as $field => $step) {
    $isComplex = in_array($field, [
        'companyDetails', 'additionalContacts', 'weekdayPattern', 'weekendPattern',
        'goals', 'documentationTypes', 'proposedSteps', 'agreements', 'gasUsage',
        'stepNotes', 'uploadedFiles', 'submittedBy'
    ]);
    $isFile = strpos($field, 'File') !== false || strpos($field, 'Photos') !== false 
        || in_array($field, ['sitePhotos', 'visualizations', 'billingDocuments', 'cogenerationPhotos']);
    $isRaynet = strpos($field, 'raynet') !== false;
    
    $type = $isComplex ? '(complex object)' : ($isFile ? '(file upload)' : ($isRaynet ? '(raynet internal)' : ''));
    printf("  %-35s | %-8s %s\n", $field, $step, $type);
}

echo "\n\n⚠️  IN AUTO_MAPPING BUT NOT IN FORM LIST (" . count($inMappingNotInForm) . "):\n";
echo str_repeat("-", 60) . "\n";
foreach ($inMappingNotInForm as $field => $target) {
    printf("  %-35s | target: %s\n", $field, $target);
}

// Summary
echo "\n\n=======================================================\n";
echo "SUMMARY\n";
echo "=======================================================\n";
echo "Total form fields found:     " . count($flatFields) . "\n";
echo "Mapped in AUTO_MAPPING:      " . count($mapped) . "\n";
echo "Not mapped:                  " . count($notMapped) . "\n";
echo "Coverage:                    " . round(count($mapped) / count($flatFields) * 100, 1) . "%\n";

// Categorize not-mapped by type
$complexObjects = 0;
$fileUploads = 0;
$raynetInternal = 0;
$shouldMap = 0;

foreach ($notMapped as $field => $step) {
    if (in_array($field, ['companyDetails', 'additionalContacts', 'weekdayPattern', 'weekendPattern',
        'goals', 'documentationTypes', 'proposedSteps', 'agreements', 'gasUsage', 'stepNotes', 'uploadedFiles', 'submittedBy'])) {
        $complexObjects++;
    } elseif (strpos($field, 'File') !== false || strpos($field, 'Photos') !== false 
        || in_array($field, ['sitePhotos', 'visualizations', 'billingDocuments', 'cogenerationPhotos', 
                            'distributionCurvesFile', 'projectDocumentationFiles', 'connectionContractFile', 
                            'connectionApplicationFile'])) {
        $fileUploads++;
    } elseif (strpos($field, 'raynet') !== false) {
        $raynetInternal++;
    } else {
        $shouldMap++;
    }
}

echo "\nNot mapped breakdown:\n";
echo "  - Complex objects (nested):  $complexObjects\n";
echo "  - File uploads (skip):       $fileUploads\n";
echo "  - Raynet internal (skip):    $raynetInternal\n";
echo "  - SHOULD BE MAPPED:          $shouldMap\n";

// List fields that should be mapped
if ($shouldMap > 0) {
    echo "\n🔴 FIELDS THAT SHOULD BE ADDED TO AUTO_MAPPING:\n";
    echo str_repeat("-", 60) . "\n";
    foreach ($notMapped as $field => $step) {
        $isComplex = in_array($field, ['companyDetails', 'additionalContacts', 'weekdayPattern', 'weekendPattern',
            'goals', 'documentationTypes', 'proposedSteps', 'agreements', 'gasUsage', 'stepNotes', 'uploadedFiles', 'submittedBy']);
        $isFile = strpos($field, 'File') !== false || strpos($field, 'Photos') !== false 
            || in_array($field, ['sitePhotos', 'visualizations', 'billingDocuments', 'cogenerationPhotos',
                                'distributionCurvesFile', 'projectDocumentationFiles', 'connectionContractFile', 
                                'connectionApplicationFile']);
        $isRaynet = strpos($field, 'raynet') !== false;
        
        if (!$isComplex && !$isFile && !$isRaynet) {
            printf("  %-35s | %s\n", $field, $step);
        }
    }
}
