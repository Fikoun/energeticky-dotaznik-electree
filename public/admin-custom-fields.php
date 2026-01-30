<?php
session_start();

// Kontrola oprávnění
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /');
    exit();
}

// Log page view activity
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/UserActivityTracker.php';
        $tracker = new UserActivityTracker();
        $tracker->logActivity($_SESSION['user_id'], 'page_view', 'Zobrazení vlastních polí Raynet');
    } catch (Exception $e) {
        error_log("Activity logging error: " . $e->getMessage());
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vlastní pole Raynet - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                            400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                            800: '#075985', 900: '#0c4a6e'
                        },
                        raynet: {
                            50: '#fdf4ff', 100: '#fae8ff', 200: '#f5d0fe', 300: '#f0abfc',
                            400: '#e879f9', 500: '#d946ef', 600: '#c026d3', 700: '#a21caf',
                            800: '#86198f', 900: '#701a75'
                        }
                    }
                }
            }
        };
    </script>
    <style>
        .tab-active {
            border-bottom: 2px solid #0ea5e9;
            color: #0284c7;
        }
        .field-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        .mapping-row:hover {
            background-color: #f9fafb;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Header -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-xl font-bold text-gray-900">Admin Panel</h1>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="admin-dashboard.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-4 px-1 text-sm font-medium">
                            📊 Dashboard
                        </a>
                        <a href="admin-users.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-4 px-1 text-sm font-medium">
                            👥 Uživatelé
                        </a>
                        <a href="admin-forms.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-4 px-1 text-sm font-medium">
                            📝 Formuláře
                        </a>
                        <a href="admin-sync.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-4 px-1 text-sm font-medium">
                            ⌘ Synchronizace
                        </a>
                        <a href="admin-custom-fields.php" class="border-primary-500 text-primary-600 border-b-2 py-4 px-1 text-sm font-medium">
                            🔧 Vlastní pole
                        </a>
                        <a href="admin-activity.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-4 px-1 text-sm font-medium">
                            📋 Aktivita
                        </a>
                        <a href="admin-settings.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-4 px-1 text-sm font-medium">
                            ⚙️ Nastavení
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="text-sm text-gray-700 mr-4">
                        <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
                    </span>
                    <a href="logout.php" class="text-sm text-gray-500 hover:text-gray-700">
                        Odhlásit se
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Page Header -->
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate flex items-center">
                        <span class="mr-3">🔧</span> Vlastní pole Raynet
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Správa vlastních polí v Raynet CRM a jejich mapování na formulářová data
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="refreshData()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium flex items-center">
                        <span class="mr-2">🔄</span> Obnovit
                    </button>
                    <button onclick="openCreateFieldModal()" class="bg-raynet-600 hover:bg-raynet-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                        <span class="mr-2">➕</span> Vytvořit pole
                    </button>
                </div>
            </div>

            <!-- Info Banner -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <span class="text-2xl">ℹ️</span>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">O vlastních polích</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>Vlastní pole (Custom Fields) umožňují ukládat data z formulářů přímo do Raynet CRM. 
                            Pro každé pole z formuláře můžete vytvořit odpovídající pole v Raynet a namapovat je.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="bg-white shadow rounded-lg">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <button onclick="switchTab('auto')" id="tab-auto" class="tab-active px-6 py-4 text-sm font-medium">
                            🤖 Auto-mapování
                        </button>
                        <button onclick="switchTab('raynet')" id="tab-raynet" class="px-6 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                            ☁️ Pole v Raynet
                        </button>
                        <button onclick="switchTab('mapping')" id="tab-mapping" class="px-6 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                            🔗 Ruční mapování
                        </button>
                        <button onclick="switchTab('form')" id="tab-form" class="px-6 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                            📝 Pole formuláře
                        </button>
                    </nav>
                </div>
                
                <!-- Auto-Mapping Tab -->
                <div id="tab-content-auto" class="p-6">
                    <div class="mb-6 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">🤖 Inteligentní auto-mapování</h3>
                            <p class="text-sm text-gray-500 mt-1">Automaticky navržené mapování polí formuláře na Raynet CRM</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="detectExistingFields()" id="detect-fields-btn" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                                🔍 Detekovat existující pole
                            </button>
                            <button onclick="createAllCustomFields()" id="create-all-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                ⚡ Vytvořit všechna pole v Raynet
                            </button>
                            <button onclick="applyAutoMapping()" id="apply-mapping-btn" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                💾 Aplikovat mapování
                            </button>
                        </div>
                    </div>
                    
                    <!-- Summary cards -->
                    <div class="grid grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <div class="text-2xl font-bold text-blue-700" id="stat-native">-</div>
                            <div class="text-sm text-blue-600">Nativní pole Raynet</div>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                            <div class="text-2xl font-bold text-purple-700" id="stat-address">-</div>
                            <div class="text-sm text-purple-600">Adresa/Kontakt</div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                            <div class="text-2xl font-bold text-green-700" id="stat-person">-</div>
                            <div class="text-sm text-green-600">Kontaktní osoba</div>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
                            <div class="text-2xl font-bold text-orange-700" id="stat-custom">-</div>
                            <div class="text-sm text-orange-600">Vlastní pole (k vytvoření)</div>
                        </div>
                    </div>
                    
                    <div id="auto-mapping-container" class="space-y-6">
                        <div class="animate-pulse">
                            <div class="h-40 bg-gray-200 rounded mb-4"></div>
                            <div class="h-40 bg-gray-200 rounded"></div>
                        </div>
                    </div>
                </div>

                <!-- Raynet Fields Tab -->
                <div id="tab-content-raynet" class="hidden p-6">
                    <div class="mb-4 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Vlastní pole pro entitu Firma (Company)</h3>
                        <button onclick="loadRaynetFields(true)" class="text-primary-600 hover:text-primary-800 text-sm">
                            🔄 Znovu načíst z Raynet
                        </button>
                    </div>
                    
                    <div id="raynet-fields-container" class="space-y-3">
                        <div class="animate-pulse">
                            <div class="h-16 bg-gray-200 rounded mb-2"></div>
                            <div class="h-16 bg-gray-200 rounded mb-2"></div>
                            <div class="h-16 bg-gray-200 rounded"></div>
                        </div>
                    </div>
                    
                    <div id="raynet-fields-empty" class="hidden text-center py-8 text-gray-500">
                        <span class="text-4xl mb-2 block">📭</span>
                        <p>V Raynet nejsou žádná vlastní pole.</p>
                        <p class="text-sm mt-2">Vytvořte nové pole kliknutím na tlačítko "Vytvořit pole" výše.</p>
                    </div>
                </div>

                <!-- Mapping Tab -->
                <div id="tab-content-mapping" class="hidden p-6">
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Mapování polí formuláře → Raynet</h3>
                            <p class="text-sm text-gray-500 mt-1">Vyberte, která pole z formulářů se mají synchronizovat do Raynet</p>
                        </div>
                        <button onclick="saveMapping()" id="save-mapping-btn" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:opacity-50">
                            💾 Uložit mapování
                        </button>
                    </div>
                    
                    <div id="mapping-container" class="border rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Pole formuláře
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Typ
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        → Pole v Raynet
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Akce
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="mapping-table-body" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                        Načítám...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm text-yellow-800">
                            <strong>💡 Tip:</strong> Pokud pole v Raynet neexistuje, můžete ho vytvořit přímo z tohoto rozhraní 
                            kliknutím na "Vytvořit v Raynet" u daného pole.
                        </p>
                    </div>
                </div>

                <!-- Form Fields Tab -->
                <div id="tab-content-form" class="hidden p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Dostupná pole formuláře</h3>
                        <p class="text-sm text-gray-500 mt-1">Přehled všech polí, která lze synchronizovat z formulářů do Raynet</p>
                    </div>
                    
                    <div id="form-fields-container" class="space-y-6">
                        <div class="animate-pulse">
                            <div class="h-8 bg-gray-200 rounded w-1/4 mb-4"></div>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="h-20 bg-gray-200 rounded"></div>
                                <div class="h-20 bg-gray-200 rounded"></div>
                                <div class="h-20 bg-gray-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Field Modal -->
    <div id="createFieldModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-6 border w-full max-w-lg shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Vytvořit nové pole v Raynet</h3>
                <button onclick="closeCreateFieldModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="text-2xl">&times;</span>
                </button>
            </div>
            
            <form id="createFieldForm" onsubmit="submitCreateField(event)">
                <div class="space-y-4">
                    <!-- Label -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Název pole *</label>
                        <input type="text" id="field-label" name="label" required minlength="3"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
                               placeholder="např. Roční spotřeba">
                    </div>
                    
                    <!-- Group Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Skupina *</label>
                        <input type="text" id="field-group" name="group_name" required minlength="3" value="EnergyForms"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
                               placeholder="např. EnergyForms">
                    </div>
                    
                    <!-- Data Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Typ dat *</label>
                        <select id="field-type" name="data_type" required onchange="toggleEnumValues()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="STRING">Text (krátký)</option>
                            <option value="TEXT">Text (dlouhý)</option>
                            <option value="BIG_DECIMAL">Číslo</option>
                            <option value="BOOLEAN">Ano/Ne</option>
                            <option value="DATE">Datum</option>
                            <option value="DATETIME">Datum a čas</option>
                            <option value="ENUMERATION">Výběr z možností</option>
                            <option value="HYPERLINK">Odkaz</option>
                            <option value="MONETARY">Peněžní částka</option>
                            <option value="PERCENT">Procento</option>
                        </select>
                    </div>
                    
                    <!-- Enumeration values (shown only for ENUMERATION type) -->
                    <div id="enum-values-container" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hodnoty výběru (jedna na řádek)</label>
                        <textarea id="field-enum-values" name="enumeration_values" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
                                  placeholder="Hodnota 1&#10;Hodnota 2&#10;Hodnota 3"></textarea>
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Popis</label>
                        <input type="text" id="field-description" name="description"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
                               placeholder="Volitelný popis pole">
                    </div>
                    
                    <!-- Options -->
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="field-show-list" name="show_in_list" checked
                                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="ml-2 text-sm text-gray-700">Zobrazit v seznamu</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" id="field-show-filter" name="show_in_filter" checked
                                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="ml-2 text-sm text-gray-700">Zobrazit ve filtrech</span>
                        </label>
                    </div>
                    
                    <!-- Form field to pre-select (optional) -->
                    <div id="form-field-select-container" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mapovat na pole formuláře</label>
                        <select id="field-form-field" name="form_field"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="">-- Vybrat později --</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeCreateFieldModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Zrušit
                    </button>
                    <button type="submit" id="create-field-btn"
                            class="px-4 py-2 bg-raynet-600 hover:bg-raynet-700 text-white rounded-md">
                        Vytvořit pole
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // State
        let currentTab = 'raynet';
        let csrfToken = null;
        let raynetFields = [];
        let formFields = {};
        let formFieldsByStep = {};
        let currentMapping = {};
        let dataTypes = {};
        let preselectedFormField = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', async function() {
            await getCSRFToken();
            await loadDataTypes();
            await loadAutoMapping(); // Load auto-mapping first (default tab)
            await loadRaynetFields();
            await loadFormFields();
            await loadMapping();
        });

        // API helper
        async function apiCall(data, includeToken = false) {
            if (includeToken && csrfToken) {
                data.csrf_token = csrfToken;
            }
            
            const response = await fetch('admin-custom-fields-api.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken || ''
                },
                body: JSON.stringify(data)
            });
            
            const responseData = await response.json();
            
            if (!response.ok) {
                throw new Error(responseData.error || `HTTP ${response.status}`);
            }
            
            return responseData;
        }

        async function getCSRFToken() {
            try {
                const data = await apiCall({ action: 'get_csrf_token' });
                if (data.success && data.csrf_token) {
                    csrfToken = data.csrf_token;
                }
            } catch (error) {
                console.error('Failed to get CSRF token', error);
            }
        }

        // Tab switching
        function switchTab(tab) {
            currentTab = tab;
            
            // Update tab buttons
            ['auto', 'raynet', 'mapping', 'form'].forEach(t => {
                const btn = document.getElementById(`tab-${t}`);
                const content = document.getElementById(`tab-content-${t}`);
                
                if (t === tab) {
                    btn.classList.add('tab-active');
                    btn.classList.remove('text-gray-500');
                    content.classList.remove('hidden');
                } else {
                    btn.classList.remove('tab-active');
                    btn.classList.add('text-gray-500');
                    content.classList.add('hidden');
                }
            });
            
            // Load auto-mapping if switching to that tab
            if (tab === 'auto' && !autoMappingData) {
                loadAutoMapping();
            }
        }
        
        // Auto-mapping data
        let autoMappingData = null;

        // Load data types
        async function loadDataTypes() {
            try {
                const data = await apiCall({ action: 'get_data_types' });
                if (data.success) {
                    dataTypes = data.data;
                }
            } catch (error) {
                console.error('Failed to load data types', error);
            }
        }

        // Load Raynet fields
        async function loadRaynetFields(forceRefresh = false) {
            const container = document.getElementById('raynet-fields-container');
            const emptyState = document.getElementById('raynet-fields-empty');
            
            container.innerHTML = `
                <div class="animate-pulse">
                    <div class="h-16 bg-gray-200 rounded mb-2"></div>
                    <div class="h-16 bg-gray-200 rounded mb-2"></div>
                    <div class="h-16 bg-gray-200 rounded"></div>
                </div>
            `;
            emptyState.classList.add('hidden');
            
            try {
                const data = await apiCall({ 
                    action: 'get_company_fields',
                    force_refresh: forceRefresh
                });
                
                if (data.success) {
                    raynetFields = data.data || [];
                    renderRaynetFields();
                }
            } catch (error) {
                console.error('Failed to load Raynet fields', error);
                container.innerHTML = `
                    <div class="text-center py-8 text-red-500">
                        <span class="text-4xl mb-2 block">❌</span>
                        <p>Chyba při načítání polí z Raynet</p>
                        <p class="text-sm mt-2">${escapeHtml(error.message)}</p>
                    </div>
                `;
            }
        }

        function renderRaynetFields() {
            const container = document.getElementById('raynet-fields-container');
            const emptyState = document.getElementById('raynet-fields-empty');
            
            if (!raynetFields || raynetFields.length === 0) {
                container.innerHTML = '';
                emptyState.classList.remove('hidden');
                return;
            }
            
            emptyState.classList.add('hidden');
            
            // Group by groupName
            const grouped = {};
            raynetFields.forEach(field => {
                const group = field.groupName || 'Ostatní';
                if (!grouped[group]) {
                    grouped[group] = [];
                }
                grouped[group].push(field);
            });
            
            let html = '';
            
            for (const [groupName, fields] of Object.entries(grouped)) {
                html += `
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-500 mb-3 uppercase tracking-wide">${escapeHtml(groupName)}</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                `;
                
                fields.forEach(field => {
                    const typeName = dataTypes[field.dataType] || field.dataType;
                    html += `
                        <div class="field-card border rounded-lg p-4 bg-white transition-all duration-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0">
                                    <h5 class="font-medium text-gray-900">${escapeHtml(field.label)}</h5>
                                    <p class="text-xs text-gray-500 mt-1 font-mono truncate">${escapeHtml(field.name)}</p>
                                </div>
                                <div class="flex items-center gap-2 ml-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                        ${escapeHtml(typeName)}
                                    </span>
                                    <button onclick="deleteField('${escapeHtml(field.name)}', '${escapeHtml(field.label)}')" 
                                            class="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50 transition-colors"
                                            title="Smazat pole z Raynet">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                            ${field.description ? `<p class="text-sm text-gray-600 mt-2">${escapeHtml(field.description)}</p>` : ''}
                            <div class="mt-3 flex space-x-2">
                                ${field.showInListView ? '<span class="text-xs text-green-600">📋 Seznam</span>' : ''}
                                ${field.showInFilterView ? '<span class="text-xs text-blue-600">🔍 Filtr</span>' : ''}
                            </div>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }

        // View mode for form fields: 'step' or 'group'
        let formFieldsViewMode = 'group';
        let formFieldsByGroup = {};
        let formFieldGroups = {};

        // Load form fields
        async function loadFormFields() {
            try {
                const data = await apiCall({ action: 'get_form_fields' });
                
                if (data.success) {
                    formFields = data.data.fields || {};
                    formFieldsByStep = data.data.by_step || {};
                    formFieldsByGroup = data.data.by_group || {};
                    formFieldGroups = data.data.groups || {};
                    renderFormFields();
                    populateFormFieldSelect();
                }
            } catch (error) {
                console.error('Failed to load form fields', error);
            }
        }

        function renderFormFields() {
            const container = document.getElementById('form-fields-container');
            
            // Show toggle buttons and render based on mode
            let toggleHtml = `
                <div class="flex justify-end mb-4 gap-2">
                    <button onclick="setFormFieldsViewMode('group')" 
                            class="px-3 py-1 text-sm rounded-lg ${formFieldsViewMode === 'group' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}">
                        📦 Podle skupiny Raynet
                    </button>
                    <button onclick="setFormFieldsViewMode('step')" 
                            class="px-3 py-1 text-sm rounded-lg ${formFieldsViewMode === 'step' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}">
                        📋 Podle kroku formuláře
                    </button>
                </div>
            `;
            
            if (formFieldsViewMode === 'group') {
                container.innerHTML = toggleHtml + renderFormFieldsByGroup();
            } else {
                container.innerHTML = toggleHtml + renderFormFieldsByStep();
            }
        }
        
        function setFormFieldsViewMode(mode) {
            formFieldsViewMode = mode;
            renderFormFields();
        }
        
        function renderFormFieldsByGroup() {
            if (!formFieldsByGroup || Object.keys(formFieldsByGroup).length === 0) {
                return '<p class="text-gray-500">Žádná pole k zobrazení</p>';
            }
            
            // Define group colors for visual distinction
            const groupColors = {
                'EnergyForms - Společnost': 'border-blue-300 bg-blue-50',
                'EnergyForms - Energetické zdroje': 'border-green-300 bg-green-50',
                'EnergyForms - Spotřeba': 'border-yellow-300 bg-yellow-50',
                'EnergyForms - Lokalita': 'border-purple-300 bg-purple-50',
                'EnergyForms - Technické údaje': 'border-orange-300 bg-orange-50',
                'EnergyForms - Fakturace': 'border-pink-300 bg-pink-50',
                'EnergyForms - Metadata': 'border-gray-300 bg-gray-50'
            };
            
            const groupIcons = {
                'EnergyForms - Společnost': '🏢',
                'EnergyForms - Energetické zdroje': '⚡',
                'EnergyForms - Spotřeba': '📊',
                'EnergyForms - Lokalita': '📍',
                'EnergyForms - Technické údaje': '🔧',
                'EnergyForms - Fakturace': '💰',
                'EnergyForms - Metadata': '📋'
            };
            
            let html = '<p class="text-sm text-gray-600 mb-4">Pole jsou seskupena podle toho, jak se zobrazí v Raynet CRM. Každá skupina bude v Raynet viditelná jako samostatná sekce.</p>';
            
            for (const [groupName, fields] of Object.entries(formFieldsByGroup)) {
                const groupDescription = formFieldGroups[groupName] || '';
                const colorClass = groupColors[groupName] || 'border-gray-300 bg-gray-50';
                const icon = groupIcons[groupName] || '📁';
                const fieldCount = Object.keys(fields).length;
                
                html += `
                    <div class="mb-6 border-2 ${colorClass} rounded-xl overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-200 bg-white bg-opacity-50">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900">
                                        ${icon} ${escapeHtml(groupName)}
                                    </h4>
                                    ${groupDescription ? `<p class="text-xs text-gray-500 mt-1">${escapeHtml(groupDescription)}</p>` : ''}
                                </div>
                                <span class="text-xs text-gray-500">${fieldCount} polí</span>
                            </div>
                        </div>
                        <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                `;
                
                for (const [fieldName, fieldDef] of Object.entries(fields)) {
                    const typeName = dataTypes[fieldDef.type] || fieldDef.type;
                    const isMapped = currentMapping[fieldName] ? true : false;
                    
                    html += `
                        <div class="field-card border rounded-lg p-3 bg-white transition-all duration-200 ${isMapped ? 'ring-2 ring-green-400' : ''}">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0">
                                    <h5 class="font-medium text-gray-900 text-sm truncate">${escapeHtml(fieldDef.label)}</h5>
                                    <p class="text-xs text-gray-400 mt-0.5 font-mono truncate">${escapeHtml(fieldName)}</p>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 ml-2 flex-shrink-0">
                                    ${escapeHtml(typeName)}
                                </span>
                            </div>
                            ${isMapped ? `
                                <div class="mt-2 text-xs text-green-600 truncate">
                                    ✅ ${escapeHtml(currentMapping[fieldName])}
                                </div>
                            ` : `
                                <button onclick="createFieldForFormField('${fieldName}')" 
                                        class="mt-2 text-xs text-primary-600 hover:text-primary-800">
                                    ➕ Vytvořit v Raynet
                                </button>
                            `}
                        </div>
                    `;
                }
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            return html;
        }
        
        function renderFormFieldsByStep() {
            if (!formFieldsByStep || Object.keys(formFieldsByStep).length === 0) {
                return '<p class="text-gray-500">Žádná pole k zobrazení</p>';
            }
            
            const stepNames = {
                0: 'Metadata',
                1: 'Identifikační údaje',
                2: 'Energetické zdroje',
                3: 'Profil spotřeby',
                4: 'Požadavky na baterii',
                5: 'Technické požadavky',
                6: 'Finance',
                7: 'Další informace',
                8: 'Potvrzení'
            };
            
            let html = '';
            
            for (const [step, fields] of Object.entries(formFieldsByStep)) {
                const stepName = stepNames[step] || `Krok ${step}`;
                
                html += `
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-500 mb-3 uppercase tracking-wide">
                            Krok ${step}: ${escapeHtml(stepName)}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                `;
                
                for (const [fieldName, fieldDef] of Object.entries(fields)) {
                    const typeName = dataTypes[fieldDef.type] || fieldDef.type;
                    const isMapped = currentMapping[fieldName] ? true : false;
                    
                    html += `
                        <div class="field-card border rounded-lg p-4 bg-white transition-all duration-200 ${isMapped ? 'border-green-300 bg-green-50' : ''}">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h5 class="font-medium text-gray-900">${escapeHtml(fieldDef.label)}</h5>
                                    <p class="text-xs text-gray-500 mt-1 font-mono">${escapeHtml(fieldName)}</p>
                                </div>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                    ${escapeHtml(typeName)}
                                </span>
                            </div>
                            ${isMapped ? `
                                <div class="mt-2 text-xs text-green-600">
                                    ✅ Mapováno na: ${escapeHtml(currentMapping[fieldName])}
                                </div>
                            ` : `
                                <button onclick="createFieldForFormField('${fieldName}')" 
                                        class="mt-2 text-xs text-primary-600 hover:text-primary-800">
                                    ➕ Vytvořit v Raynet
                                </button>
                            `}
                        </div>
                    `;
                }
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            return html;
        }

        function populateFormFieldSelect() {
            const select = document.getElementById('field-form-field');
            if (!select) return;
            
            select.innerHTML = '<option value="">-- Vybrat později --</option>';
            
            for (const [fieldName, fieldDef] of Object.entries(formFields)) {
                const option = document.createElement('option');
                option.value = fieldName;
                option.textContent = `${fieldDef.label} (${fieldName})`;
                select.appendChild(option);
            }
        }

        // Load mapping
        async function loadMapping() {
            try {
                const data = await apiCall({ action: 'get_mapping' });
                
                if (data.success) {
                    currentMapping = data.data || {};
                    renderMappingTable();
                }
            } catch (error) {
                console.error('Failed to load mapping', error);
            }
        }

        function renderMappingTable() {
            const tbody = document.getElementById('mapping-table-body');
            
            if (!formFields || Object.keys(formFields).length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                            Načítám pole formuláře...
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            
            for (const [fieldName, fieldDef] of Object.entries(formFields)) {
                const typeName = dataTypes[fieldDef.type] || fieldDef.type;
                const mappedTo = currentMapping[fieldName] || '';
                
                html += `
                    <tr class="mapping-row">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">${escapeHtml(fieldDef.label)}</div>
                            <div class="text-xs text-gray-500 font-mono">${escapeHtml(fieldName)}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                ${escapeHtml(typeName)}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <select id="mapping-${fieldName}" 
                                    onchange="updateMapping('${fieldName}', this.value)"
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="">-- Neposílat --</option>
                                ${raynetFields.map(rf => `
                                    <option value="${escapeHtml(rf.name)}" ${mappedTo === rf.name ? 'selected' : ''}>
                                        ${escapeHtml(rf.label)} (${rf.name})
                                    </option>
                                `).join('')}
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <button onclick="createFieldForFormField('${fieldName}')" 
                                    class="text-sm text-raynet-600 hover:text-raynet-800">
                                ➕ Vytvořit v Raynet
                            </button>
                        </td>
                    </tr>
                `;
            }
            
            tbody.innerHTML = html;
        }

        function updateMapping(formField, raynetField) {
            if (raynetField) {
                currentMapping[formField] = raynetField;
            } else {
                delete currentMapping[formField];
            }
        }

        async function saveMapping() {
            const btn = document.getElementById('save-mapping-btn');
            btn.disabled = true;
            btn.innerHTML = '💾 Ukládám...';
            
            try {
                const data = await apiCall({
                    action: 'save_mapping',
                    mapping: currentMapping
                }, true);
                
                if (data.success) {
                    showToast(data.message || 'Mapování uloženo', 'success');
                    // Re-render form fields to show updated mapping status
                    renderFormFields();
                }
            } catch (error) {
                console.error('Failed to save mapping', error);
                showToast('Chyba při ukládání: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '💾 Uložit mapování';
            }
        }

        // Create field modal
        function openCreateFieldModal() {
            document.getElementById('createFieldModal').classList.remove('hidden');
            document.getElementById('createFieldForm').reset();
            document.getElementById('field-group').value = 'EnergyForms';
            toggleEnumValues();
            
            // Show form field select
            document.getElementById('form-field-select-container').classList.remove('hidden');
            
            // Pre-select form field if set
            if (preselectedFormField) {
                document.getElementById('field-form-field').value = preselectedFormField;
                const fieldDef = formFields[preselectedFormField];
                if (fieldDef) {
                    document.getElementById('field-label').value = fieldDef.label;
                    document.getElementById('field-type').value = fieldDef.type;
                    document.getElementById('field-description').value = `EnergyForms: ${preselectedFormField}`;
                }
                preselectedFormField = null;
            }
        }

        function closeCreateFieldModal() {
            document.getElementById('createFieldModal').classList.add('hidden');
        }

        function toggleEnumValues() {
            const type = document.getElementById('field-type').value;
            const container = document.getElementById('enum-values-container');
            
            if (type === 'ENUMERATION') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }

        function createFieldForFormField(formFieldName) {
            preselectedFormField = formFieldName;
            openCreateFieldModal();
        }

        async function deleteField(fieldName, fieldLabel) {
            if (!confirm(`Opravdu chcete smazat pole "${fieldLabel}" z Raynet?\n\nTechnický název: ${fieldName}\n\n⚠️ VAROVÁNÍ: Tato akce je nevratná! Data uložená v tomto poli budou ztracena.`)) {
                return;
            }
            
            // Double confirmation for safety
            if (!confirm(`Jste si opravdu jisti? Pole "${fieldLabel}" bude trvale odstraněno ze všech záznamů v Raynet.`)) {
                return;
            }
            
            showToast('Mažu pole...', 'info');
            
            try {
                const data = await apiCall({
                    action: 'delete_field',
                    entity_type: 'Company',
                    field_name: fieldName
                }, true);
                
                if (data.success) {
                    showToast(data.message || 'Pole úspěšně smazáno', 'success');
                    
                    // Reload Raynet fields to reflect the deletion
                    await loadRaynetFields(true);
                    
                    // Also update mapping table if we're on that tab
                    renderMappingTable();
                    
                    // Remove from current mapping if it was mapped
                    for (const [formField, raynetField] of Object.entries(currentMapping)) {
                        if (raynetField === fieldName) {
                            delete currentMapping[formField];
                        }
                    }
                } else {
                    showToast('Chyba: ' + (data.error || 'Neznámá chyba'), 'error');
                }
            } catch (error) {
                console.error('Failed to delete field', error);
                showToast('Chyba při mazání: ' + error.message, 'error');
            }
        }

        async function submitCreateField(event) {
            event.preventDefault();
            
            const btn = document.getElementById('create-field-btn');
            btn.disabled = true;
            btn.innerHTML = 'Vytvářím...';
            
            try {
                const formData = {
                    action: 'create_field',
                    entity_type: 'Company',
                    label: document.getElementById('field-label').value,
                    group_name: document.getElementById('field-group').value,
                    data_type: document.getElementById('field-type').value,
                    description: document.getElementById('field-description').value,
                    show_in_list: document.getElementById('field-show-list').checked,
                    show_in_filter: document.getElementById('field-show-filter').checked,
                };
                
                // Add enum values if applicable
                if (formData.data_type === 'ENUMERATION') {
                    const enumText = document.getElementById('field-enum-values').value;
                    formData.enumeration_values = enumText.split('\n').map(v => v.trim()).filter(v => v);
                }
                
                const data = await apiCall(formData, true);
                
                if (data.success) {
                    showToast(data.message || 'Pole vytvořeno', 'success');
                    closeCreateFieldModal();
                    
                    // Auto-map if form field was selected
                    const formField = document.getElementById('field-form-field').value;
                    if (formField && data.data && data.data.fieldName) {
                        currentMapping[formField] = data.data.fieldName;
                        await saveMapping();
                    }
                    
                    // Reload fields
                    await loadRaynetFields(true);
                    renderMappingTable();
                }
            } catch (error) {
                console.error('Failed to create field', error);
                showToast('Chyba při vytváření: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Vytvořit pole';
            }
        }

        // =====================================================================
        // AUTO-MAPPING FUNCTIONS
        // =====================================================================
        
        async function loadAutoMapping() {
            try {
                const data = await apiCall({ action: 'get_auto_mapping' });
                
                if (data.success) {
                    autoMappingData = data.data;
                    renderAutoMapping();
                    updateAutoMappingStats();
                }
            } catch (error) {
                console.error('Failed to load auto-mapping', error);
                document.getElementById('auto-mapping-container').innerHTML = `
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                        Chyba při načítání auto-mapování: ${escapeHtml(error.message)}
                    </div>
                `;
            }
        }
        
        function updateAutoMappingStats() {
            if (!autoMappingData || !autoMappingData.by_target) return;
            
            const byTarget = autoMappingData.by_target;
            document.getElementById('stat-native').textContent = Object.keys(byTarget.native || {}).length;
            document.getElementById('stat-address').textContent = Object.keys(byTarget.address || {}).length;
            document.getElementById('stat-person').textContent = Object.keys(byTarget.person || {}).length;
            document.getElementById('stat-custom').textContent = Object.keys(byTarget.custom || {}).length;
        }
        
        function renderAutoMapping() {
            const container = document.getElementById('auto-mapping-container');
            if (!autoMappingData) {
                container.innerHTML = '<p class="text-gray-500">Žádná data</p>';
                return;
            }
            
            const byTarget = autoMappingData.by_target;
            const formFields = autoMappingData.form_fields;
            
            let html = '';
            
            // Native fields section
            if (byTarget.native && Object.keys(byTarget.native).length > 0) {
                html += renderMappingSection(
                    'Nativní pole Raynet',
                    '🔵',
                    'Tato pole se mapují přímo na standardní pole v Raynet Company',
                    'bg-blue-50 border-blue-200',
                    byTarget.native,
                    formFields,
                    'native'
                );
            }
            
            // Address/Contact section
            if (byTarget.address && Object.keys(byTarget.address).length > 0) {
                html += renderMappingSection(
                    'Adresa a kontaktní údaje',
                    '🟣',
                    'Tato pole se ukládají do adresy a kontaktních údajů firmy',
                    'bg-purple-50 border-purple-200',
                    byTarget.address,
                    formFields,
                    'address'
                );
            }
            
            // Person entity section
            if (byTarget.person && Object.keys(byTarget.person).length > 0) {
                html += renderMappingSection(
                    'Kontaktní osoba',
                    '🟢',
                    'Tato pole se synchronizují do propojené kontaktní osoby (Person)',
                    'bg-green-50 border-green-200',
                    byTarget.person,
                    formFields,
                    'person'
                );
            }
            
            // Custom fields by group
            const customByGroup = autoMappingData.custom_by_group;
            if (customByGroup && Object.keys(customByGroup).length > 0) {
                html += `
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">🟠 Vlastní pole (Custom Fields)</h4>
                        <p class="text-sm text-gray-500 mb-4">Tato pole je potřeba vytvořit v Raynet jako vlastní pole. Jsou rozdělena do skupin pro lepší přehlednost.</p>
                `;
                
                const groupColors = {
                    'EnergyForms - Společnost': 'border-l-blue-500',
                    'EnergyForms - Energetické zdroje': 'border-l-green-500',
                    'EnergyForms - Spotřeba': 'border-l-yellow-500',
                    'EnergyForms - Lokalita': 'border-l-purple-500',
                    'EnergyForms - Technické údaje': 'border-l-orange-500',
                    'EnergyForms - Fakturace': 'border-l-pink-500',
                    'EnergyForms - Metadata': 'border-l-gray-500'
                };
                
                const groupIcons = {
                    'EnergyForms - Společnost': '🏢',
                    'EnergyForms - Energetické zdroje': '⚡',
                    'EnergyForms - Spotřeba': '📊',
                    'EnergyForms - Lokalita': '📍',
                    'EnergyForms - Technické údaje': '🔧',
                    'EnergyForms - Fakturace': '💰',
                    'EnergyForms - Metadata': '📋'
                };
                
                for (const [groupName, fields] of Object.entries(customByGroup)) {
                    const colorClass = groupColors[groupName] || 'border-l-gray-500';
                    const icon = groupIcons[groupName] || '📁';
                    const fieldCount = Object.keys(fields).length;
                    
                    html += `
                        <div class="mb-4 bg-orange-50 border border-orange-200 rounded-lg overflow-hidden border-l-4 ${colorClass}">
                            <div class="px-4 py-3 bg-white bg-opacity-50 border-b border-orange-100 flex justify-between items-center">
                                <div>
                                    <span class="font-medium text-gray-900">${icon} ${escapeHtml(groupName)}</span>
                                    <span class="ml-2 text-sm text-gray-500">(${fieldCount} polí)</span>
                                </div>
                                <button onclick="createGroupFields('${escapeHtml(groupName)}')" 
                                        class="text-sm bg-orange-600 hover:bg-orange-700 text-white px-3 py-1 rounded">
                                    ➕ Vytvořit skupinu
                                </button>
                            </div>
                            <div class="p-4">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-gray-500">
                                            <th class="pb-2">Pole formuláře</th>
                                            <th class="pb-2">Typ</th>
                                            <th class="pb-2">→ Navrhované pole Raynet</th>
                                            <th class="pb-2">Popis</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;
                    
                    for (const [fieldName, config] of Object.entries(fields)) {
                        const typeName = dataTypes[config.type] || config.type;
                        html += `
                            <tr class="border-t border-orange-100">
                                <td class="py-2">
                                    <span class="font-medium text-gray-900">${escapeHtml(config.label)}</span>
                                    <span class="text-xs text-gray-400 block font-mono">${escapeHtml(fieldName)}</span>
                                </td>
                                <td class="py-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                        ${escapeHtml(typeName)}
                                    </span>
                                </td>
                                <td class="py-2 font-mono text-xs text-orange-700">${escapeHtml(config.suggestedName || '')}</td>
                                <td class="py-2 text-gray-500 text-xs">${escapeHtml(config.description || '')}</td>
                            </tr>
                        `;
                    }
                    
                    html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }
                
                html += `</div>`;
            }
            
            container.innerHTML = html;
        }
        
        function renderMappingSection(title, icon, description, colorClass, fields, formFieldsDef, targetType) {
            let html = `
                <div class="mb-6 ${colorClass} border rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b bg-white bg-opacity-50">
                        <h4 class="font-semibold text-gray-900">${icon} ${escapeHtml(title)}</h4>
                        <p class="text-sm text-gray-500">${escapeHtml(description)}</p>
                    </div>
                    <div class="p-4">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="pb-2">Pole formuláře</th>
                                    <th class="pb-2">→ Pole Raynet</th>
                                    <th class="pb-2">Popis</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            for (const [fieldName, config] of Object.entries(fields)) {
                const fieldDef = formFieldsDef[fieldName] || {};
                html += `
                    <tr class="border-t">
                        <td class="py-2">
                            <span class="font-medium text-gray-900">${escapeHtml(fieldDef.label || fieldName)}</span>
                            <span class="text-xs text-gray-400 block font-mono">${escapeHtml(fieldName)}</span>
                        </td>
                        <td class="py-2">
                            <code class="text-xs bg-white px-2 py-1 rounded border">${escapeHtml(config.raynetField || config.suggestedName || '-')}</code>
                        </td>
                        <td class="py-2 text-gray-500 text-xs">${escapeHtml(config.description || '')}</td>
                    </tr>
                `;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            return html;
        }
        
        async function detectExistingFields() {
            const btn = document.getElementById('detect-fields-btn');
            btn.disabled = true;
            btn.innerHTML = '⏳ Hledám...';
            
            try {
                // First just detect without applying
                const detectData = await apiCall({ action: 'detect_mapping' });
                
                if (!detectData.success) {
                    showToast('Chyba: ' + (detectData.error || 'Neznámá chyba'), 'error');
                    return;
                }
                
                const detection = detectData.data;
                const matchedCount = detection.matched?.length || 0;
                const unmatchedCount = detection.unmatched?.length || 0;
                
                if (matchedCount === 0) {
                    showToast(`Žádná pole nebyla nalezena. Ujistěte se, že pole v Raynetu mají skupinu "EnergyForms - ...".`, 'warning');
                    return;
                }
                
                // Show confirmation dialog
                let message = `Nalezeno ${matchedCount} polí k namapování:\n\n`;
                detection.matched.forEach(m => {
                    message += `• ${m.label} → ${m.formField}\n`;
                });
                
                if (unmatchedCount > 0) {
                    message += `\n(${unmatchedCount} polí nebylo rozpoznáno)`;
                }
                
                message += '\n\nChcete aplikovat toto mapování?';
                
                if (!confirm(message)) {
                    return;
                }
                
                // Apply the detected mapping
                const applyData = await apiCall({ action: 'apply_detected_mapping' }, true);
                
                if (applyData.success) {
                    showToast(`Mapování úspěšně aplikováno (${matchedCount} polí)`, 'success');
                    await loadMapping();
                } else {
                    showToast('Chyba: ' + (applyData.error || 'Neznámá chyba'), 'error');
                }
            } catch (error) {
                console.error('Failed to detect fields', error);
                showToast('Chyba při detekci: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '🔍 Detekovat existující pole';
            }
        }
        
        async function applyAutoMapping() {
            const btn = document.getElementById('apply-mapping-btn');
            btn.disabled = true;
            btn.innerHTML = '⏳ Ukládám...';
            
            try {
                const data = await apiCall({ action: 'apply_auto_mapping' }, true);
                
                if (data.success) {
                    showToast(`Mapování úspěšně aplikováno (${data.data.field_count} polí)`, 'success');
                    // Reload mapping in other tabs
                    await loadMapping();
                } else {
                    showToast('Chyba: ' + (data.error || 'Neznámá chyba'), 'error');
                }
            } catch (error) {
                console.error('Failed to apply mapping', error);
                showToast('Chyba při ukládání: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '💾 Aplikovat mapování';
            }
        }
        
        async function createAllCustomFields() {
            if (!confirm('Opravdu chcete vytvořit všechna vlastní pole v Raynet?\n\nToto vytvoří ' + 
                         document.getElementById('stat-custom').textContent + ' nových polí.')) {
                return;
            }
            
            const btn = document.getElementById('create-all-btn');
            btn.disabled = true;
            btn.innerHTML = '⏳ Vytvářím pole...';
            
            try {
                // Get all custom fields to create
                const customFields = Object.keys(autoMappingData.by_target.custom || {});
                
                if (customFields.length === 0) {
                    showToast('Žádná pole k vytvoření', 'info');
                    return;
                }
                
                const data = await apiCall({
                    action: 'create_fields_batch',
                    entity_type: 'Company',
                    form_fields: customFields
                    // group_name not passed - will use each field's defined group
                }, true);
                
                if (data.success) {
                    showToast(data.message, 'success');
                    // Reload Raynet fields to show newly created ones
                    await loadRaynetFields(true);
                    await loadMapping();
                } else {
                    showToast('Chyba: ' + (data.error || 'Neznámá chyba'), 'error');
                }
            } catch (error) {
                console.error('Failed to create fields', error);
                showToast('Chyba při vytváření: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '⚡ Vytvořit všechna pole v Raynet';
            }
        }
        
        async function createGroupFields(groupName) {
            if (!confirm(`Vytvořit všechna pole ve skupině "${groupName}"?`)) {
                return;
            }
            
            try {
                // Get fields for this group
                const groupFields = autoMappingData.custom_by_group[groupName];
                if (!groupFields) {
                    showToast('Skupina nenalezena', 'error');
                    return;
                }
                
                const fieldNames = Object.keys(groupFields);
                showToast(`Vytvářím ${fieldNames.length} polí...`, 'info');
                
                const data = await apiCall({
                    action: 'create_fields_batch',
                    entity_type: 'Company',
                    form_fields: fieldNames
                }, true);
                
                if (data.success) {
                    // Show detailed result with errors if any
                    const result = data.data;
                    let message = data.message;
                    
                    if (result.errors && result.errors.length > 0) {
                        console.error('Field creation errors:', result.errors);
                        message += '\n\nChyby:';
                        result.errors.forEach(err => {
                            message += `\n• ${err.label || err.field}: ${err.error}`;
                        });
                        showToast(message, 'warning');
                    } else {
                        showToast(message, 'success');
                    }
                    
                    await loadRaynetFields(true);
                    await loadMapping();
                } else {
                    showToast('Chyba: ' + (data.error || 'Neznámá chyba'), 'error');
                }
            } catch (error) {
                console.error('Failed to create group fields', error);
                showToast('Chyba: ' + error.message, 'error');
            }
        }

        // Refresh data
        async function refreshData() {
            showToast('Obnovuji data...', 'info');
            await loadRaynetFields(true);
            await loadFormFields();
            await loadMapping();
            showToast('Data obnovena', 'success');
        }

        // Utilities
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message, type = 'info') {
            const bgColor = type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-500' : 'bg-blue-500';
            const icon = type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️';
            
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 ${bgColor} text-white p-4 rounded-lg shadow-lg z-50 max-w-sm`;
            toast.innerHTML = `
                <div class="flex items-center">
                    <span class="mr-2">${icon}</span>
                    <span class="flex-1">${escapeHtml(message)}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">✕</button>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) toast.remove();
            }, 5000);
        }
    </script>
</body>
</html>
