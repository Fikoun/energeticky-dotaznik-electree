<?php
session_start();

// Kontrola oprávnění
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /');
    exit();
}

// Načtení funkcí pro rozšířené údaje společností
require_once '../includes/enhanced-company-data.php';

$form_id = $_GET['id'] ?? '';

if (empty($form_id)) {
    echo "Neplatné ID formuláře";
    exit();
}

// Načtení dat formuláře
$form_data = null;
$enhanced_company_data = null;

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
    
    // Pokud máme formulář, pokusíme se načíst rozšířené údaje společnosti
    if ($form_data && !empty($form_data['form_data'])) {
        $decoded_form_data = json_decode($form_data['form_data'], true);
        
        // Hledáme IČO v datech formuláře
        $ico = null;
        $dic = null;
        
        // Možné cesty k IČO v datech formuláře
        $possible_ico_paths = [
            'ico',
            'companyIco',
            'step1.ico',
            'company.ico',
            'identification.ico'
        ];
        
        foreach ($possible_ico_paths as $path) {
            $value = getNestedValue($decoded_form_data, $path);
            if (!empty($value) && preg_match('/^\d{8}$/', $value)) {
                $ico = $value;
                break;
            }
        }
        
        // Podobně pro DIČ
        $possible_dic_paths = [
            'dic',
            'companyDic',
            'step1.dic', 
            'company.dic',
            'identification.dic'
        ];
        
        foreach ($possible_dic_paths as $path) {
            $value = getNestedValue($decoded_form_data, $path);
            if (!empty($value)) {
                $dic = $value;
                break;
            }
        }
        
        // Načtení rozšířených údajů společnosti
        if ($ico) {
            $enhanced_company_data = getEnhancedCompanyData($ico, $dic);
        }
    }
    
} catch (PDOException $e) {
    // Fallback mock data pro testování
    $form_data = [
        'id' => $form_id,
        'user_name' => 'Testovací uživatel',
        'user_email' => 'test@example.com',
        'status' => 'completed',
        'form_data' => '{"ico":"27082440","dic":"CZ27082440","companyName":"ALZA.CZ a.s.","step1":{"ico":"27082440","dic":"CZ27082440"}}',
        'created_at' => '2025-09-01 10:30:00',
        'updated_at' => '2025-09-01 11:15:00'
    ];
    
    // Pro testování načteme data pro Alzu
    $enhanced_company_data = getEnhancedCompanyData('27082440');
}

// Helper funkce pro získání vnořených hodnot
function getNestedValue($array, $path) {
    $keys = explode('.', $path);
    $current = $array;
    
    foreach ($keys as $key) {
        if (is_array($current) && isset($current[$key])) {
            $current = $current[$key];
        } else {
            return null;
        }
    }
    
    return $current;
}

// Helper funkce pro formátování dat
function formatValue($value, $type = 'text') {
    if (empty($value)) return '<span class="text-gray-400">Není uvedeno</span>';
    
    switch ($type) {
        case 'email':
            return '<a href="mailto:' . htmlspecialchars($value) . '" class="text-blue-600 hover:underline">' . htmlspecialchars($value) . '</a>';
        case 'phone':
            return '<a href="tel:' . htmlspecialchars($value) . '" class="text-blue-600 hover:underline">' . htmlspecialchars($value) . '</a>';
        case 'url':
            $url = strpos($value, 'http') === 0 ? $value : 'http://' . $value;
            return '<a href="' . htmlspecialchars($url) . '" target="_blank" class="text-blue-600 hover:underline">' . htmlspecialchars($value) . '</a>';
        case 'date':
            return date('d.m.Y', strtotime($value));
        case 'money':
            return number_format($value, 0, ',', ' ') . ' Kč';
        default:
            return htmlspecialchars($value);
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail formuláře #<?= htmlspecialchars($form_id) ?> | Administrace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .section-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .data-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .data-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        .data-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .data-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
        }
        .data-value {
            color: #111827;
        }
        .completeness-bar {
            width: 100%;
            background-color: #e5e7eb;
            border-radius: 9999px;
            height: 0.5rem;
        }
        .completeness-fill {
            height: 0.5rem;
            border-radius: 9999px;
            transition: all 0.3s;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Hlavička -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Detail formuláře #<?= htmlspecialchars($form_id) ?></h1>
                <p class="text-gray-600 mt-2">Kompletní přehled údajů formuláře a rozšířené informace o společnosti</p>
            </div>
            <div class="flex space-x-3">
                <a href="/public/admin-dashboard.html" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i>Zpět na dashboard
                </a>
                <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-print mr-2"></i>Tisk
                </button>
            </div>
        </div>

        <?php if ($form_data): ?>
        
        <!-- Základní informace o formuláři -->
        <div class="section-card">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-file-alt text-blue-500 mr-2"></i>Základní informace o formuláři
            </h2>
            <div class="data-grid">
                <div class="data-item">
                    <span class="data-label">ID formuláře</span>
                    <span class="data-value font-mono"><?= htmlspecialchars($form_data['id']) ?></span>
                </div>
                <div class="data-item">
                    <span class="data-label">Status</span>
                    <span class="data-value">
                        <?php
                        $status_class = match($form_data['status']) {
                            'completed' => 'bg-green-100 text-green-800',
                            'draft' => 'bg-yellow-100 text-yellow-800',
                            'submitted' => 'bg-blue-100 text-blue-800',
                            default => 'bg-gray-100 text-gray-800'
                        };
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs <?= $status_class ?>">
                            <?= ucfirst($form_data['status']) ?>
                        </span>
                    </span>
                </div>
                <div class="data-item">
                    <span class="data-label">Vytvořeno</span>
                    <span class="data-value"><?= formatValue($form_data['created_at'], 'date') ?></span>
                </div>
                <div class="data-item">
                    <span class="data-label">Poslední aktualizace</span>
                    <span class="data-value"><?= formatValue($form_data['updated_at'], 'date') ?></span>
                </div>
                <div class="data-item">
                    <span class="data-label">Vytvořil uživatel</span>
                    <span class="data-value"><?= htmlspecialchars($form_data['user_name'] ?? 'Neznámý') ?></span>
                </div>
                <div class="data-item">
                    <span class="data-label">Email uživatele</span>
                    <span class="data-value"><?= formatValue($form_data['user_email'], 'email') ?></span>
                </div>
            </div>
        </div>

        <?php if ($enhanced_company_data && $enhanced_company_data['success']): ?>
        <!-- Rozšířené údaje společnosti z MERK API -->
        <div class="section-card">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-building text-blue-500 mr-2"></i>Rozšířené údaje společnosti
                </h2>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">Zdroj dat:</span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                        <?= strtoupper($enhanced_company_data['source']) ?>
                    </span>
                    <?php if (isset($enhanced_company_data['data']['metadata']['completeness'])): ?>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">Úplnost dat:</span>
                        <div class="completeness-bar w-24">
                            <div class="completeness-fill bg-green-500" style="width: <?= $enhanced_company_data['data']['metadata']['completeness'] ?>%"></div>
                        </div>
                        <span class="text-sm font-medium"><?= $enhanced_company_data['data']['metadata']['completeness'] ?>%</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php $company = $enhanced_company_data['data']; ?>
            
            <!-- Základní identifikační údaje -->
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">
                    <i class="fas fa-id-card text-gray-500 mr-2"></i>Základní identifikační údaje
                </h3>
                <div class="data-grid">
                    <div class="data-item">
                        <span class="data-label">Název společnosti</span>
                        <span class="data-value font-semibold text-lg"><?= formatValue($company['basic_info']['name']) ?></span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">IČO</span>
                        <span class="data-value font-mono"><?= formatValue($company['basic_info']['regno']) ?></span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">DIČ</span>
                        <span class="data-value font-mono"><?= formatValue($company['basic_info']['regno']) ?></span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">Právní forma</span>
                        <span class="data-value">
                            <?= formatValue($company['basic_info']['legal_form']['text']) ?>
                            <?php if (!empty($company['basic_info']['legal_form']['code'])): ?>
                                <span class="text-gray-500 ml-2">(<?= $company['basic_info']['legal_form']['code'] ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">Status</span>
                        <span class="data-value"><?= formatValue($company['basic_info']['status']) ?></span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">Datum vzniku</span>
                        <span class="data-value"><?= formatValue($company['basic_info']['estab_date'], 'date') ?></span>
                    </div>
                </div>
            </div>

            <!-- Adresní údaje -->
            <?php if (!empty($company['address']['formatted'])): ?>
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">
                    <i class="fas fa-map-marker-alt text-gray-500 mr-2"></i>Adresní údaje
                </h3>
                <div class="data-grid">
                    <div class="data-item">
                        <span class="data-label">Úplná adresa</span>
                        <span class="data-value"><?= formatValue($company['address']['formatted']) ?></span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">Ulice a číslo</span>
                        <span class="data-value"><?= formatValue($company['address']['street'] . ' ' . $company['address']['number']) ?></span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">Město</span>
                        <span class="data-value"><?= formatValue($company['address']['city']) ?></span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">PSČ</span>
                        <span class="data-value font-mono"><?= formatValue($company['address']['postal_code']) ?></span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">Okres/Region</span>
                        <span class="data-value"><?= formatValue($company['address']['district'] ?: $company['address']['region']) ?></span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">Země</span>
                        <span class="data-value"><?= formatValue($company['address']['country']) ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Kontaktní údaje -->
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">
                    <i class="fas fa-address-book text-gray-500 mr-2"></i>Kontaktní údaje
                </h3>
                
                <!-- Kontaktní osoby -->
                <?php if (!empty($company['contacts'])): ?>
                <div class="mb-6">
                    <h4 class="font-medium text-gray-800 mb-3">Kontaktní osoby</h4>
                    <div class="space-y-3">
                        <?php foreach ($company['contacts'] as $contact): ?>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="data-grid">
                                <div class="data-item">
                                    <span class="data-label">Jméno</span>
                                    <span class="data-value font-medium"><?= formatValue($contact['name']) ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-label">Pozice</span>
                                    <span class="data-value"><?= formatValue($contact['position']) ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-label">Telefon</span>
                                    <span class="data-value"><?= formatValue($contact['phone'], 'phone') ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-label">Email</span>
                                    <span class="data-value"><?= formatValue($contact['email'], 'email') ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Telefony -->
                <?php if (!empty($company['phones'])): ?>
                <div class="mb-6">
                    <h4 class="font-medium text-gray-800 mb-3">Telefony</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <?php foreach ($company['phones'] as $phone): ?>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="data-value"><?= formatValue($phone['number'], 'phone') ?></div>
                            <div class="text-xs text-gray-500"><?= ucfirst($phone['type']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Emaily -->
                <?php if (!empty($company['emails'])): ?>
                <div class="mb-6">
                    <h4 class="font-medium text-gray-800 mb-3">Emailové adresy</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <?php foreach ($company['emails'] as $email): ?>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="data-value"><?= formatValue($email['email'], 'email') ?></div>
                            <div class="text-xs text-gray-500"><?= ucfirst($email['type']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Weby -->
                <?php if (!empty($company['webs'])): ?>
                <div class="mb-6">
                    <h4 class="font-medium text-gray-800 mb-3">Webové stránky</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach ($company['webs'] as $web): ?>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="data-value"><?= formatValue($web['url'], 'url') ?></div>
                            <div class="text-xs text-gray-500"><?= ucfirst($web['type']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Obchodní informace -->
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">
                    <i class="fas fa-chart-bar text-gray-500 mr-2"></i>Obchodní informace
                </h3>
                <div class="data-grid">
                    <div class="data-item">
                        <span class="data-label">Odvětví</span>
                        <span class="data-value">
                            <?= formatValue($company['business_info']['industry']['text']) ?>
                            <?php if (!empty($company['business_info']['industry']['code'])): ?>
                                <span class="text-gray-500 ml-2">(<?= $company['business_info']['industry']['code'] ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">Velikost společnosti</span>
                        <span class="data-value"><?= formatValue($company['business_info']['magnitude']['text']) ?></span>
                    </div>
                    <div class="data-item">
                        <span class="data-label">Počet zaměstnanců</span>
                        <span class="data-value"><?= formatValue($company['business_info']['employee_count']) ?></span>
                    </div>
                    <?php if (!empty($company['business_info']['revenue'])): ?>
                    <div class="data-item">
                        <span class="data-label">Roční obrat</span>
                        <span class="data-value">
                            <?php if (is_array($company['business_info']['revenue'])): ?>
                                <?php foreach ($company['business_info']['revenue'] as $key => $value): ?>
                                    <div><?= $key ?>: <?= formatValue($value, 'money') ?></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?= formatValue($company['business_info']['revenue'], 'money') ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Debug informace (pouze pro development) -->
            <?php if (isset($_GET['debug']) && $_GET['debug'] === '1' && isset($enhanced_company_data['raw_data'])): ?>
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">
                    <i class="fas fa-bug text-gray-500 mr-2"></i>Debug - Raw API Data
                </h3>
                <details class="bg-gray-50 p-4 rounded-lg">
                    <summary class="cursor-pointer font-medium mb-2">Zobrazit surová data z API</summary>
                    <pre class="text-xs bg-white p-4 rounded border overflow-auto max-h-96"><?= json_encode($enhanced_company_data['raw_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                </details>
            </div>
            <?php endif; ?>

        </div>
        <?php elseif ($enhanced_company_data && !$enhanced_company_data['success']): ?>
        <!-- Chyba při načítání údajů -->
        <div class="section-card">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-3"></i>
                    <div>
                        <h3 class="font-medium text-yellow-800">Nepodařilo se načíst rozšířené údaje společnosti</h3>
                        <p class="text-yellow-700 text-sm mt-1">
                            IČO nebylo nalezeno v databázi nebo nebylo možné načíst údaje z externích zdrojů.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Data formuláře -->
        <div class="section-card">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-database text-blue-500 mr-2"></i>Data formuláře
            </h2>
            <?php if (!empty($form_data['form_data'])): ?>
                <?php 
                $decoded_data = json_decode($form_data['form_data'], true);
                if ($decoded_data): 
                ?>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <details>
                        <summary class="cursor-pointer font-medium mb-2">Zobrazit strukturovaná data formuláře</summary>
                        <pre class="text-sm bg-white p-4 rounded border overflow-auto max-h-96 mt-2"><?= json_encode($decoded_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                    </details>
                </div>
                <?php else: ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-700">Chyba při dekódování JSON dat formuláře.</p>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-gray-500">Žádná data formuláře nejsou k dispozici.</p>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- Formulář nebyl nalezen -->
        <div class="section-card">
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-red-800 mb-2">Formulář nebyl nalezen</h3>
                <p class="text-red-700">Formulář s ID "<?= htmlspecialchars($form_id) ?>" neexistuje nebo nemáte oprávnění k jeho zobrazení.</p>
                <a href="/public/admin-dashboard.html" class="inline-block mt-4 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">
                    Zpět na dashboard
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Přidání interaktivity pro lepší UX
        document.addEventListener('DOMContentLoaded', function() {
            // Animace pro progress bar
            const progressBars = document.querySelectorAll('.completeness-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>
