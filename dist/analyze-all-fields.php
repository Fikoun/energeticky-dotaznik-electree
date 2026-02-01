<?php
// Script pro komplexní analýzu všech dat formuláře
require_once __DIR__ . '/../config/database.php';

$conn = getMysqliConnection();

$form_id = 'form_admin_68b6dfca8d028_68cbdf6312e24';
$stmt = $conn->prepare("SELECT form_data FROM forms WHERE id = ?");
$stmt->bind_param("s", $form_id);
$stmt->execute();
$result = $stmt->get_result();
$form_data = $result->fetch_assoc();

if ($form_data) {
    $decoded_data = json_decode($form_data['form_data'], true);
    
    echo "=== KOMPLETNÍ ANALÝZA VŠECH POLÍ ===\n\n";
    
    $organized_by_probable_step = [
        1 => [], // Identifikační údaje
        2 => [], // Parametry odběrného místa  
        3 => [], // Spotřeba a rozložení
        4 => [], // Analýza spotřeby a akumulace
        5 => [], // Cíle a optimalizace
        6 => [], // Místo realizace
        7 => [], // Připojení k síti
        8 => []  // Fakturace a bilancování
    ];
    
    foreach ($decoded_data as $key => $value) {
        // Klasifikace podle klíčů
        $step = 0;
        
        // Krok 1 - Identifikace
        if (preg_match('/^(company|ico|dic|contact|email|phone|address|customer)/i', $key)) {
            $step = 1;
        }
        // Krok 2 - Parametry odběru
        elseif (preg_match('/^(hasFve|fve|transformer|circuit|main|reserved|shares|receives)/i', $key)) {
            $step = 2;
        }
        // Krok 3 - Spotřeba
        elseif (preg_match('/^(monthly|yearly|daily|consumption|distribution|territory|measurement|weekday|weekend|critical)/i', $key)) {
            $step = 3;
        }
        // Krok 4 - Akumulace
        elseif (preg_match('/^(energy|battery|backup|price|audit|load)/i', $key)) {
            $step = 4;
        }
        // Krok 5 - Cíle
        elseif (preg_match('/^(goal|priority)/i', $key)) {
            $step = 5;
        }
        // Krok 6 - Realizace  
        elseif (preg_match('/^(outdoor|indoor|accessibility|documentation|site|electrical|building|roof)/i', $key)) {
            $step = 6;
        }
        // Krok 7 - Připojení
        elseif (preg_match('/^(grid|power|connection|application|attorney|specialist|proposed)/i', $key)) {
            $step = 7;
        }
        // Krok 8 - Fakturace
        elseif (preg_match('/^(electricity|distribution|system|ote|billing|spot|fix|current|price|gas|heating|cooling|cogeneration)/i', $key)) {
            $step = 8;
        }
        
        if ($step > 0) {
            $organized_by_probable_step[$step][] = $key;
        } else {
            // Nezařazené
            echo "NEZAŘAZENO: $key\n";
        }
    }
    
    // Výpis podle kroků
    foreach ($organized_by_probable_step as $step_num => $fields) {
        echo "=== KROK $step_num ===\n";
        foreach ($fields as $field) {
            $type = is_array($decoded_data[$field]) ? 'array' : gettype($decoded_data[$field]);
            echo "'$field', // $type\n";
        }
        echo "\n";
    }
    
} else {
    echo "Formulář nenalezen\n";
}

$conn->close();
?>
