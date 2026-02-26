<?php
session_set_cookie_params(["path" => "/", "httponly" => true, "samesite" => "Lax"]);
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
        $tracker->logActivity($_SESSION['user_id'], 'page_view', 'Zobrazení synchronizace Raynet');
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
    <title>Synchronizace Raynet - Admin Panel</title>
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
        .sync-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
        .tab-active {
            border-bottom: 2px solid #0ea5e9;
            color: #0284c7;
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
                        <a href="admin-sync.php" class="border-primary-500 text-primary-600 border-b-2 py-4 px-1 text-sm font-medium">
                            ⌘ Synchronizace
                        </a>
                        <a href="admin-custom-fields.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-4 px-1 text-sm font-medium">
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
                        <span class="mr-3">⌘</span> Synchronizace Raynet
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Správa synchronizace formulářů s Raynet CRM
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <!-- Raynet Status Indicator -->
                    <div id="raynet-status-indicator" class="flex items-center px-3 py-2 rounded-md bg-gray-100">
                        <span id="raynet-status-dot" class="w-2 h-2 rounded-full bg-gray-400 mr-2 animate-pulse"></span>
                        <span id="raynet-status-text" class="text-sm text-gray-600">Načítám...</span>
                    </div>
                    <button onclick="testConnection()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium flex items-center">
                      Test připojení
                    </button>
                    <button onclick="syncAllPending()" id="syncAllBtn" class="bg-raynet-600 hover:bg-raynet-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                        Synchronizovat vše
                    </button>
                </div>
            </div>

            <!-- Connection Status Banner -->
            <div id="connection-status" class="mb-6 hidden">
                <!-- Will be populated by JS -->
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <!-- Synced -->
                <div class="bg-white rounded-lg shadow p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                            <span class="text-2xl">✅</span>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Synchronizováno</p>
                            <p class="text-2xl font-semibold text-green-600" id="stat-synced">-</p>
                        </div>
                    </div>
                </div>
                
                <!-- Pending -->
                <div class="bg-white rounded-lg shadow p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                            <span class="text-2xl">⏳</span>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Ve frontě</p>
                            <p class="text-2xl font-semibold text-yellow-600" id="stat-pending">-</p>
                        </div>
                    </div>
                </div>
                
                <!-- Errors -->
                <div class="bg-white rounded-lg shadow p-5 cursor-pointer hover:shadow-md transition-shadow" onclick="showErrors()">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-100 rounded-md p-3">
                            <span class="text-2xl">❌</span>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Chyby</p>
                            <p class="text-2xl font-semibold text-red-600" id="stat-errors">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Last Sync Info -->
            <div class="bg-white rounded-lg shadow p-4 mb-6 flex items-center justify-between">
                <div class="flex items-center">
                    <span class="text-gray-500 mr-2">🕐</span>
                    <span class="text-sm text-gray-600">Poslední synchronizace: </span>
                    <span class="text-sm font-medium text-gray-900 ml-1" id="last-sync-time">-</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500" id="rate-limit-info"></span>
                    <button onclick="refreshStats()" class="text-primary-600 hover:text-primary-800 text-sm font-medium">
                        🔄 Obnovit
                    </button>
                </div>
            </div>

            <!-- Recent Errors Section -->
            <div id="recent-errors-section" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 hidden">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-medium text-red-800 flex items-center">
                        <span class="mr-2">⚠️</span> Poslední chyby synchronizace
                    </h3>
                    <button onclick="retryAllErrors()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                        Zkusit znovu vše
                    </button>
                </div>
                <div id="recent-errors-list" class="space-y-2">
                    <!-- Errors will be populated here -->
                </div>
            </div>

            <!-- Tabs -->
            <div class="bg-white shadow rounded-lg">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <button onclick="switchTab('local')" id="tab-local" class="tab-active px-6 py-4 text-sm font-medium">
                            📝 Lokální formuláře
                        </button>
                        <button onclick="switchTab('raynet')" id="tab-raynet" class="px-6 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                            ☁️ Raynet firmy
                        </button>
                        <button onclick="switchTab('logs')" id="tab-logs" class="px-6 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                            📋 Logy
                        </button>
                        <button onclick="switchTab('tester')" id="tab-tester" class="px-6 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                            🔬 Tester polí
                        </button>
                    </nav>
                </div>

                <!-- Filters -->
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <div class="flex flex-wrap gap-4 items-center">
                        <!-- Local tab filters -->
                        <div id="filters-local" class="flex flex-wrap gap-4 items-center">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Stav synchronizace</label>
                                <select id="filter-status" onchange="loadLocalForms(1)" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    <option value="all">Všechny</option>
                                    <option value="synced">Synchronizované</option>
                                    <option value="pending">Čekající</option>
                                    <option value="error">S chybou</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Raynet tab filters -->
                        <div id="filters-raynet" class="hidden flex flex-wrap gap-4 items-center">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Hledat firmu</label>
                                <input type="text" id="raynet-search" placeholder="Název firmy..." 
                                       class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            </div>
                            <button onclick="loadRaynetCompanies(1)" class="mt-5 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm">
                                Hledat
                            </button>
                        </div>
                        
                        <!-- Tester tab filters -->
                        <div id="filters-tester" class="hidden flex flex-wrap gap-4 items-center">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Form ID (synchronizovaný formulář)</label>
                                <div class="flex gap-2">
                                    <input type="text" id="tester-form-id" placeholder="ID formuláře..."
                                           class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 w-72"
                                           onkeypress="if(event.key==='Enter') runFieldComparison()">
                                    <button onclick="runFieldComparison()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm">
                                        🔬 Porovnat
                                    </button>
                                </div>
                            </div>
                            <div class="text-xs text-gray-400 mt-5">Vyberte synchronizovaný formulář z tabulky nebo zadejte ID přímo</div>
                        </div>

                        <!-- Logs tab filters -->
                        <div id="filters-logs" class="hidden flex flex-wrap gap-4 items-center">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Úroveň</label>
                                <select id="filter-log-level" onchange="loadLogs(1)" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    <option value="">Všechny</option>
                                    <option value="debug">Debug</option>
                                    <option value="info">Info</option>
                                    <option value="warning">Warning</option>
                                    <option value="error">Error</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <button onclick="clearOldLogs()" class="mt-5 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm">
                                Vymazat staré logy
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div id="tab-content-local">
                    <div id="local-forms-table" class="overflow-x-auto">
                        <div class="animate-pulse p-6">
                            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <div id="local-pagination" class="px-6 py-4 border-t border-gray-200 hidden">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Zobrazeno <span id="local-showing-start">1</span> až <span id="local-showing-end">20</span> z <span id="local-total">0</span>
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <button id="local-prev-btn" onclick="changeLocalPage(-1)" class="px-3 py-1 border rounded-lg hover:bg-gray-50 disabled:opacity-50">
                                    Předchozí
                                </button>
                                <span id="local-page-info" class="px-3 py-1 text-sm text-gray-700">1 / 1</span>
                                <button id="local-next-btn" onclick="changeLocalPage(1)" class="px-3 py-1 border rounded-lg hover:bg-gray-50 disabled:opacity-50">
                                    Další
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab-content-raynet" class="hidden">
                    <div id="raynet-companies-table" class="overflow-x-auto">
                        <div class="text-center py-8 text-gray-500">
                            Klikněte na "Hledat" pro načtení firem z Raynet
                        </div>
                    </div>
                    
                    <!-- Raynet Pagination -->
                    <div id="raynet-pagination" class="px-6 py-4 border-t border-gray-200 hidden">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-700">Stránka <span id="raynet-page">1</span></p>
                            </div>
                            <div class="flex space-x-2">
                                <button id="raynet-prev-btn" onclick="changeRaynetPage(-1)" class="px-3 py-1 border rounded-lg hover:bg-gray-50 disabled:opacity-50">
                                    Předchozí
                                </button>
                                <button id="raynet-next-btn" onclick="changeRaynetPage(1)" class="px-3 py-1 border rounded-lg hover:bg-gray-50 disabled:opacity-50">
                                    Další
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ══════════════════ TESTER TAB ══════════════════ -->
                <div id="tab-content-tester" class="hidden">
                    <div id="tester-result" class="p-6">
                        <div class="text-center py-12 text-gray-400">
                            <div class="text-5xl mb-3">🔬</div>
                            <p class="text-lg font-medium text-gray-500">Porovnání polí</p>
                            <p class="text-sm mt-1">Zadejte ID synchronizovaného formuláře a klikněte na Porovnat.</p>
                            <p class="text-sm text-gray-400 mt-1">Ukazuje co EnergyForms odešle vs. co je reálně v Raynet.</p>
                        </div>
                    </div>
                </div>

                <div id="tab-content-logs" class="hidden">
                    <div id="logs-table" class="overflow-x-auto">
                        <div class="animate-pulse p-6">
                            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        </div>
                    </div>
                    
                    <!-- Logs Pagination -->
                    <div id="logs-pagination" class="px-6 py-4 border-t border-gray-200 hidden">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Zobrazeno <span id="logs-showing-start">1</span> až <span id="logs-showing-end">50</span> z <span id="logs-total">0</span>
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <button id="logs-prev-btn" onclick="changeLogsPage(-1)" class="px-3 py-1 border rounded-lg hover:bg-gray-50 disabled:opacity-50">
                                    Předchozí
                                </button>
                                <span id="logs-page-info" class="px-3 py-1 text-sm text-gray-700">1 / 1</span>
                                <button id="logs-next-btn" onclick="changeLogsPage(1)" class="px-3 py-1 border rounded-lg hover:bg-gray-50 disabled:opacity-50">
                                    Další
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Progress Modal -->
    <div id="syncProgressModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="text-center">
                <div class="sync-pulse text-4xl mb-4">🔄</div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Probíhá synchronizace</h3>
                <p class="text-sm text-gray-500" id="sync-progress-text">Zpracovávám formuláře...</p>
                <div class="mt-4 w-full bg-gray-200 rounded-full h-2">
                    <div id="sync-progress-bar" class="bg-primary-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // State
        let currentTab = 'local';
        let localPage = 1;
        let raynetPage = 1;
        let logsPage = 1;
        const pageSize = 20;
        const logsPageSize = 50;
        let csrfToken = null;
        let stats = null;

        // Logging utility
        const log = {
            info: (msg, data = null) => console.log(`[Sync] ${msg}`, data || ''),
            error: (msg, error = null) => console.error(`[Sync] ${msg}`, error || ''),
            warn: (msg, data = null) => console.warn(`[Sync] ${msg}`, data || '')
        };
        
        // Log errors to backend
        async function logToBackend(level, message, context = {}) {
            try {
                await fetch('admin-sync-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'log_frontend_error',
                        level: level,
                        message: message,
                        context: context
                    })
                });
            } catch (e) {
                console.error('Failed to log to backend:', e);
            }
        }

        // API helper
        async function apiCall(data, includeToken = false) {
            if (includeToken && csrfToken) {
                data.csrf_token = csrfToken;
            }
            
            const response = await fetch('admin-sync-api.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken || ''
                },
                body: JSON.stringify(data)
            });
            
            // Get response text first to check if it's valid JSON
            const responseText = await response.text();
            
            let responseData;
            try {
                responseData = JSON.parse(responseText);
            } catch (e) {
                const errorDetails = {
                    status: response.status,
                    action: data.action,
                    responseText: responseText.substring(0, 500),
                    error: e.message
                };
                
                console.error('Invalid JSON response:', errorDetails);
                
                // Log to backend
                logToBackend('error', `Invalid JSON response from ${data.action}: ${e.message}`, errorDetails);
                
                throw new Error(`Server vrátil neplatnou odpověď (${response.status}). Zkontrolujte console pro detaily.`);
            }
            
            if (!response.ok) {
                const errorDetails = {
                    status: response.status,
                    statusText: response.statusText,
                    action: data.action,
                    error: responseData.error,
                    details: responseData.details
                };
                
                console.error('API Error:', errorDetails);
                
                // Log to backend
                logToBackend('error', `API Error ${response.status} on ${data.action}: ${responseData.error}`, errorDetails);
                
                throw new Error(responseData.error || `HTTP ${response.status}`);
            }
            
            return responseData;
        }

        async function getCSRFToken() {
            try {
                const data = await apiCall({ action: 'get_csrf_token' });
                if (data.success && data.csrf_token) {
                    csrfToken = data.csrf_token;
                    log.info('CSRF token obtained');
                }
            } catch (error) {
                log.error('Failed to get CSRF token', error);
            }
        }

        // Load stats
        async function loadStats() {
            try {
                const data = await apiCall({ action: 'stats' });
                
                if (data.success) {
                    stats = data.data;
                    updateStatsUI(stats);
                } else {
                    // Still update raynet status even if stats fail
                    updateRaynetStatus({ connected: false, configured: false, message: 'Chyba načítání' });
                }
            } catch (error) {
                log.error('Failed to load stats', error);
                showToast('Nepodařilo se načíst statistiky', 'error');
                // Update raynet status to show error
                updateRaynetStatus({ connected: false, configured: false, message: 'Chyba načítání' });
            }
        }

        function updateStatsUI(stats) {
            log.info('Updating stats UI', stats);
            
            // Local stats (removed stat-local-total as card was removed)
            document.getElementById('stat-synced').textContent = stats.local.synced_forms;
            document.getElementById('stat-pending').textContent = stats.local.pending_forms;
            document.getElementById('stat-errors').textContent = stats.local.error_forms;
            
            // Last sync time
            if (stats.last_sync_time) {
                const date = new Date(stats.last_sync_time);
                document.getElementById('last-sync-time').textContent = date.toLocaleString('cs-CZ');
            } else {
                document.getElementById('last-sync-time').textContent = 'Nikdy';
            }
            
            // Raynet status
            log.info('Raynet status data:', stats.raynet);
            updateRaynetStatus(stats.raynet);
            
            // Recent errors
            updateRecentErrors(stats.recent_errors);
            
            // Rate limit
            if (stats.raynet.rate_limit_remaining) {
                document.getElementById('rate-limit-info').textContent = 
                    `API limit: ${stats.raynet.rate_limit_remaining} požadavků`;
            }
        }

        function updateRaynetStatus(raynet) {
            log.info('updateRaynetStatus called with:', raynet);
            
            const indicator = document.getElementById('raynet-status-indicator');
            const dot = document.getElementById('raynet-status-dot');
            const text = document.getElementById('raynet-status-text');
            
            log.info('Checking raynet.connected:', raynet.connected);
            log.info('Checking raynet.configured:', raynet.configured);
            
            if (raynet.connected) {
                log.info('Status: Connected');
                indicator.className = 'flex items-center px-3 py-2 rounded-md bg-green-50 border border-green-200';
                dot.className = 'w-2 h-2 rounded-full bg-green-500 mr-2';
                text.className = 'text-sm text-green-700';
                text.textContent = 'Připojeno';
            } else if (raynet.configured) {
                log.info('Status: Configured but not connected');
                indicator.className = 'flex items-center px-3 py-2 rounded-md bg-red-50 border border-red-200';
                dot.className = 'w-2 h-2 rounded-full bg-red-500 mr-2';
                text.className = 'text-sm text-red-700';
                text.textContent = 'Chyba';
            } else {
                log.info('Status: Not configured');
                indicator.className = 'flex items-center px-3 py-2 rounded-md bg-yellow-50 border border-yellow-200';
                dot.className = 'w-2 h-2 rounded-full bg-yellow-500 mr-2';
                text.className = 'text-sm text-yellow-700';
                text.textContent = 'Nekonfigurováno';
            }
        }

        function updateRecentErrors(errors) {
            const section = document.getElementById('recent-errors-section');
            const list = document.getElementById('recent-errors-list');
            
            if (!errors || errors.length === 0) {
                section.classList.add('hidden');
                return;
            }
            
            section.classList.remove('hidden');
            
            list.innerHTML = errors.map(error => `
                <div class="bg-white rounded p-3 flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">${escapeHtml(error.company_name || 'Bez názvu')}</span>
                        <span class="text-gray-500 text-sm ml-2">#${error.id}</span>
                        <p class="text-sm text-red-600 mt-1">${escapeHtml(error.raynet_sync_error || 'Neznámá chyba')}</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="retrySingleForm('${error.id}')" class="text-primary-600 hover:text-primary-800 text-sm">
                            🔄 Zkusit znovu
                        </button>
                        <button onclick="clearError('${error.id}')" class="text-gray-500 hover:text-gray-700 text-sm">
                            ✕ Smazat
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Load local forms
        async function loadLocalForms(page = 1) {
            localPage = page;
            const filter = document.getElementById('filter-status').value;
            
            try {
                const data = await apiCall({
                    action: 'list_local_forms',
                    page: page,
                    per_page: pageSize,
                    filter: filter
                });
                
                if (data.success) {
                    displayLocalForms(data.data.forms);
                    updateLocalPagination(data.data.pagination);
                }
            } catch (error) {
                log.error('Failed to load local forms', error);
                showToast('Nepodařilo se načíst formuláře', 'error');
            }
        }

        function displayLocalForms(forms) {
            const container = document.getElementById('local-forms-table');
            
            if (!forms || forms.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-4xl mb-2">📭</div>
                        <p class="text-gray-500">Žádné formuláře k zobrazení</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Firma</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kontakt</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stav sync</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Raynet ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Synchronizováno</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Akce</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${forms.map(form => `
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${escapeHtml(String(form.id).substring(0, 12))}...
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">${escapeHtml(form.company_name || '-')}</div>
                                    <div class="text-sm text-gray-500">${escapeHtml(form.email || '')}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">${escapeHtml(form.contact_person || '-')}</div>
                                    <div class="text-sm text-gray-500">${escapeHtml(form.phone || '')}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${form.sync_status.class}">
                                        ${form.sync_status.label}
                                    </span>
                                    ${form.raynet_sync_error ? `
                                        <div class="text-xs text-red-600 mt-1 max-w-xs truncate" title="${escapeHtml(form.raynet_sync_error)}">
                                            ${escapeHtml(form.raynet_sync_error.substring(0, 50))}...
                                        </div>
                                    ` : ''}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${form.raynet_company_id ? `
                                        <a href="https://app.raynet.cz/electree/?view=DetailView&en=Company&ei=${form.raynet_company_id}" target="_blank" 
                                           class="text-primary-600 hover:underline">
                                            ${form.raynet_company_id}
                                        </a>
                                    ` : '-'}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${form.synced_at_formatted || '-'}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2 flex-wrap">
                                    ${form.sync_status.status === 'error' ? `
                                        <button onclick="retrySingleForm('${form.id}')" class="text-primary-600 hover:text-primary-900">
                                            🔄 Znovu
                                        </button>
                                        <button onclick="clearError('${form.id}')" class="text-gray-500 hover:text-gray-700">
                                            ✕
                                        </button>
                                    ` : form.sync_status.status === 'pending' ? `
                                        <button onclick="syncSingleForm('${form.id}')" class="text-primary-600 hover:text-primary-900">
                                            🔄 Synchronizovat
                                        </button>
                                    ` : `
                                        <button onclick="syncSingleForm('${form.id}')" class="text-gray-500 hover:text-gray-700">
                                            🔄 Přesync
                                        </button>
                                    `}
                                    ${form.raynet_company_id ? `
                                        <button onclick="runFieldComparison('${form.id}')"
                                                class="text-purple-600 hover:text-purple-800 text-xs px-2 py-1 border border-purple-200 rounded" title="Porovnat pole s Raynet">
                                            🔬 Tester
                                        </button>
                                    ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        function updateLocalPagination(pagination) {
            const container = document.getElementById('local-pagination');
            
            if (pagination.total_count > 0) {
                container.classList.remove('hidden');
                
                const start = (pagination.page - 1) * pagination.per_page + 1;
                const end = Math.min(pagination.page * pagination.per_page, pagination.total_count);
                
                document.getElementById('local-showing-start').textContent = start;
                document.getElementById('local-showing-end').textContent = end;
                document.getElementById('local-total').textContent = pagination.total_count;
                document.getElementById('local-page-info').textContent = `${pagination.page} / ${pagination.total_pages}`;
                
                document.getElementById('local-prev-btn').disabled = pagination.page <= 1;
                document.getElementById('local-next-btn').disabled = pagination.page >= pagination.total_pages;
            } else {
                container.classList.add('hidden');
            }
        }

        function changeLocalPage(delta) {
            loadLocalForms(localPage + delta);
        }

        // Load Raynet companies
        async function loadRaynetCompanies(page = 1) {
            raynetPage = page;
            const search = document.getElementById('raynet-search').value;
            
            const container = document.getElementById('raynet-companies-table');
            container.innerHTML = `
                <div class="animate-pulse p-6">
                    <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                </div>
            `;
            
            try {
                const data = await apiCall({
                    action: 'list_raynet_companies',
                    page: page,
                    per_page: pageSize,
                    search: search
                });
                
                if (data.success) {
                    displayRaynetCompanies(data.data.companies);
                    document.getElementById('raynet-pagination').classList.remove('hidden');
                    document.getElementById('raynet-page').textContent = page;
                    document.getElementById('raynet-prev-btn').disabled = page <= 1;
                    document.getElementById('raynet-next-btn').disabled = data.data.companies.length < pageSize;
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8 text-red-500">
                            <div class="text-4xl mb-2">❌</div>
                            <p>${escapeHtml(data.error || 'Nepodařilo se načíst firmy z Raynet')}</p>
                        </div>
                    `;
                }
            } catch (error) {
                log.error('Failed to load Raynet companies', error);
                container.innerHTML = `
                    <div class="text-center py-8 text-red-500">
                        <div class="text-4xl mb-2">❌</div>
                        <p>Chyba při načítání firem z Raynet</p>
                    </div>
                `;
            }
        }

        function displayRaynetCompanies(companies) {
            const container = document.getElementById('raynet-companies-table');
            
            if (!companies || companies.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-4xl mb-2">☁️</div>
                        <p class="text-gray-500">Žádné firmy nalezeny</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Název firmy</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IČO</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">DIČ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stav</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ext ID</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Akce</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${companies.map(company => `
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${company.id || '-'}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">${escapeHtml(company.name || '-')}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${escapeHtml(company.regNumber || '-')}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${escapeHtml(company.taxNumber || '-')}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${getStateClass(company.state)}">
                                        ${getStateLabel(company.state)}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${company.extId ? `
                                        <span class="text-xs bg-gray-100 px-2 py-1 rounded">${escapeHtml(company.extId)}</span>
                                    ` : '-'}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-3">
                                        <button onclick="viewCompanyJson(${company.id})" 
                                                class="text-gray-600 hover:text-gray-900 text-xs font-medium px-2 py-1 border border-gray-300 rounded hover:bg-gray-50">
                                            View JSON
                                        </button>
                                        <a href="https://app.raynet.cz/electree/?view=DetailView&en=Company&ei=${company.id}" target="_blank" 
                                           class="text-primary-600 hover:text-primary-900">
                                            Otevřít v Raynet →
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        function getStateClass(state) {
            switch(state) {
                case 'A_POTENTIAL': return 'bg-blue-100 text-blue-800';
                case 'B_ACTUAL': return 'bg-green-100 text-green-800';
                case 'C_DEFERRED': return 'bg-yellow-100 text-yellow-800';
                case 'D_UNATTRACTIVE': return 'bg-gray-100 text-gray-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function getStateLabel(state) {
            switch(state) {
                case 'A_POTENTIAL': return 'Potenciální';
                case 'B_ACTUAL': return 'Aktivní';
                case 'C_DEFERRED': return 'Odložený';
                case 'D_UNATTRACTIVE': return 'Nezajímavý';
                default: return state || '-';
            }
        }

        function changeRaynetPage(delta) {
            loadRaynetCompanies(raynetPage + delta);
        }

        // ========== Logs Functions ==========

        async function loadLogs(page = 1) {
            try {
                logsPage = page;
                const level = document.getElementById('filter-log-level').value;
                const offset = (page - 1) * logsPageSize;
                
                const data = await apiCall({
                    action: 'get_logs',
                    level: level || null,
                    limit: logsPageSize,
                    offset: offset
                });
                
                if (data.success) {
                    displayLogs(data.data.logs);
                    updateLogsPagination(data.data.pagination);
                } else {
                    throw new Error(data.error || 'Failed to load logs');
                }
            } catch (error) {
                log.error('Failed to load logs', error);
                document.getElementById('logs-table').innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-red-500 text-5xl mb-4">⚠️</div>
                        <p class="text-gray-700 mb-2">Chyba při načítání logů</p>
                        <p class="text-gray-500 text-sm">${error.message}</p>
                    </div>
                `;
            }
        }

        function displayLogs(logs) {
            const container = document.getElementById('logs-table');
            
            if (!logs || logs.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-5xl mb-3">📋</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Žádné logy</h3>
                        <p class="text-gray-500">Nebyly nalezeny žádné záznamy</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Úroveň</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zpráva</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${logs.map(l => `
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    #${l.id}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getLogLevelClass(l.level)}">
                                        ${getLogLevelIcon(l.level)} ${l.level.toUpperCase()}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xl truncate" title="${escapeHtml(l.message)}">
                                        ${escapeHtml(l.message)}
                                    </div>
                                    ${l.form_id ? `<div class="text-xs text-gray-500 mt-1">Form #${l.form_id}</div>` : ''}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${l.created_at_formatted}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button onclick='showLogDetail(${JSON.stringify(l).replace(/'/g, "&#39;")})' 
                                            class="text-primary-600 hover:text-primary-900">
                                        Zobrazit
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        function getLogLevelClass(level) {
            switch(level) {
                case 'debug': return 'bg-gray-100 text-gray-800';
                case 'info': return 'bg-blue-100 text-blue-800';
                case 'warning': return 'bg-yellow-100 text-yellow-800';
                case 'error': return 'bg-red-100 text-red-800';
                case 'critical': return 'bg-red-200 text-red-900';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function getLogLevelIcon(level) {
            switch(level) {
                case 'debug': return '🔍';
                case 'info': return 'ℹ️';
                case 'warning': return '⚠️';
                case 'error': return '❌';
                case 'critical': return '🚨';
                default: return '📝';
            }
        }

        function showLogDetail(logData) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.innerHTML = `
                <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Detail logu #${logData.id}</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Úroveň</label>
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full ${getLogLevelClass(logData.level)}">
                                ${getLogLevelIcon(logData.level)} ${logData.level.toUpperCase()}
                            </span>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Zpráva</label>
                            <div class="bg-gray-50 p-3 rounded text-sm">${escapeHtml(logData.message)}</div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Datum</label>
                            <div class="text-sm text-gray-600">${logData.created_at_formatted}</div>
                        </div>
                        
                        ${logData.form_id ? `
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Formulář</label>
                                <a href="enhanced-admin-form-detail-modern.php?form_id=${logData.form_id}" target="_blank" 
                                   class="text-primary-600 hover:text-primary-800">
                                    Form #${logData.form_id} →
                                </a>
                            </div>
                        ` : ''}
                        
                        ${logData.ip_address ? `
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">IP adresa</label>
                                <div class="text-sm text-gray-600">${logData.ip_address}</div>
                            </div>
                        ` : ''}
                        
                        ${logData.context ? `
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kontext (JSON)</label>
                                <pre class="bg-gray-900 text-green-400 p-4 rounded text-xs overflow-x-auto max-h-96">${JSON.stringify(logData.context, null, 2)}</pre>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button onclick="this.closest('.fixed').remove()" 
                                class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                            Zavřít
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }

        function updateLogsPagination(pagination) {
            const container = document.getElementById('logs-pagination');
            
            if (pagination.total > 0) {
                container.classList.remove('hidden');
                
                const start = pagination.offset + 1;
                const end = Math.min(pagination.offset + pagination.limit, pagination.total);
                
                document.getElementById('logs-showing-start').textContent = start;
                document.getElementById('logs-showing-end').textContent = end;
                document.getElementById('logs-total').textContent = pagination.total;
                document.getElementById('logs-page-info').textContent = `${logsPage} / ${pagination.pages}`;
                
                const prevBtn = document.getElementById('logs-prev-btn');
                const nextBtn = document.getElementById('logs-next-btn');
                
                prevBtn.disabled = logsPage <= 1;
                nextBtn.disabled = logsPage >= pagination.pages;
            } else {
                container.classList.add('hidden');
            }
        }

        function changeLogsPage(delta) {
            logsPage = Math.max(1, logsPage + delta);
            loadLogs(logsPage);
        }

        async function clearOldLogs() {
            if (!confirm('Opravdu chcete vymazat logy starší než 30 dní?')) {
                return;
            }
            
            try {
                showToast('Mažu staré logy...', 'info');
                
                const data = await apiCall({
                    action: 'clear_logs',
                    days_old: 30
                }, true);
                
                if (data.success) {
                    showToast(data.message || 'Staré logy byly vymazány', 'success');
                    loadLogs(1);
                } else {
                    showToast(data.error || 'Mazání selhalo', 'error');
                }
            } catch (error) {
                log.error('Failed to clear logs', error);
                showToast('Chyba při mazání logů', 'error');
            }
        }

        // ========== End Logs Functions ==========

        // Tab switching
        function switchTab(tab) {
            currentTab = tab;
            const tabs    = ['local', 'raynet', 'logs', 'tester'];
            
            // Update tab buttons
            tabs.forEach(t => {
                const btn = document.getElementById('tab-' + t);
                btn.classList.toggle('tab-active',  t === tab);
                btn.classList.toggle('text-gray-500', t !== tab);
            });
            
            // Show/hide content & filters
            tabs.forEach(t => {
                document.getElementById('tab-content-' + t).classList.toggle('hidden', t !== tab);
                document.getElementById('filters-' + t).classList.toggle('hidden', t !== tab);
            });
            
            // Load data for tab
            if (tab === 'logs') {
                loadLogs(1);
            }
        }

        function setFilter(filter) {
            document.getElementById('filter-status').value = filter;
            switchTab('local');
            loadLocalForms(1);
        }

        function showErrors() {
            switchTab('logs');
            document.getElementById('filter-log-level').value = 'error';
            loadLogs(1);
        }

        // Sync actions
        async function syncSingleForm(formId) {
            try {
                showToast('Načítám data pro porovnání...', 'info');
                
                console.log('[Sync] Loading preview for form:', formId);
                
                // First get preview data
                const previewData = await apiCall({
                    action: 'preview_sync',
                    form_id: formId
                }, true);
                
                console.log('[Sync] Preview data:', previewData);
                
                if (!previewData.success) {
                    showToast(previewData.error || 'Nepodařilo se načíst data', 'error');
                    return;
                }
                
                // Show comparison modal
                showSyncComparisonModal(formId, previewData.data);
                
            } catch (error) {
                console.error('[Sync] Error during preview:', error);
                log.error('Preview failed', error);
                
                const errorMsg = error.message || 'Chyba při načítání dat';
                showToast(errorMsg, 'error');
            }
        }
        
        async function viewCompanyJson(companyId) {
            try {
                showToast('Načítání dat z Raynet...', 'info');
                
                const response = await fetch('/public/admin-sync-api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        action: 'get_company_json',
                        company_id: companyId,
                        csrf_token: csrfToken
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const result = await response.json();
                
                if (!result.success) {
                    showToast(result.error || 'Nepodařilo se načíst data', 'error');
                    return;
                }
                
                showJsonModal(companyId, result.data);
                
            } catch (error) {
                console.error('Error fetching company JSON:', error);
                showToast('Chyba při načítání dat: ' + error.message, 'error');
            }
        }
        
        function showJsonModal(companyId, jsonData) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.innerHTML = `
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
                        <!-- Header -->
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Raynet Company JSON</h3>
                                <p class="text-sm text-gray-500 mt-1">Company ID: ${companyId}</p>
                            </div>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- JSON Content -->
                        <div class="flex-1 overflow-auto p-6">
                            <div class="mb-4">
                                <button onclick="copyJsonToClipboard(this)" 
                                        class="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Kopírovat do schránky</span>
                                </button>
                            </div>
                            <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm font-mono" id="jsonContent">${escapeHtml(JSON.stringify(jsonData, null, 2))}</pre>
                        </div>
                        
                        <!-- Footer -->
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <button onclick="this.closest('.fixed').remove()" 
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                                Zavřít
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Click outside to close
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }
        
        function copyJsonToClipboard(button) {
            const jsonContent = document.getElementById('jsonContent').textContent;
            navigator.clipboard.writeText(jsonContent).then(() => {
                const originalText = button.innerHTML;
                button.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>Zkopírováno!</span>
                `;
                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Nepodařilo se zkopírovat do schránky');
            });
        }

        function showSyncComparisonModal(formId, data) {
            // If there are multiple candidates, show selection modal
            if (data.has_multiple_matches && data.candidates && data.candidates.length > 1) {
                showCandidateSelectionModal(formId, data);
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.innerHTML = `
                <div class="relative top-10 mx-auto p-6 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-900">Porovnání dat před synchronizací</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded">
                        <p class="text-sm text-blue-800">
                            <strong>📋 Kontrola:</strong> Prosím zkontrolujte data před synchronizací do Raynet CRM
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <!-- Local Data -->
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <h4 class="font-semibold text-lg mb-3 text-gray-900">📝 Lokální data (EnergyForms)</h4>
                            <dl class="space-y-2 text-sm">
                                <div>
                                    <dt class="font-medium text-gray-700">Firma:</dt>
                                    <dd class="text-gray-900">${escapeHtml(data.local.company_name || '-')}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-700">IČO:</dt>
                                    <dd class="text-gray-900">${escapeHtml(data.local.ico || '-')}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-700">Email:</dt>
                                    <dd class="text-gray-900">${escapeHtml(data.local.email || '-')}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-700">Kontaktní osoba:</dt>
                                    <dd class="text-gray-900">${escapeHtml(data.local.contact_person || '-')}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-700">Telefon:</dt>
                                    <dd class="text-gray-900">${escapeHtml(data.local.phone || '-')}</dd>
                                </div>
                            </dl>
                        </div>
                        
                        <!-- Raynet Data -->
                        <div class="border rounded-lg p-4 ${data.raynet_exists ? 'bg-yellow-50' : 'bg-green-50'}">
                            <h4 class="font-semibold text-lg mb-3 text-gray-900">☁️ Co bude v Raynet</h4>
                            ${data.raynet_exists ? `
                                <div class="mb-3 p-2 bg-yellow-100 border border-yellow-300 rounded text-sm text-yellow-800">
                                    ⚠️ Firma již existuje - bude aktualizována
                                </div>
                                ${data.match_reason ? `
                                    <div class="mb-3 p-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-700">
                                        <strong>🔍 Důvod shody:</strong> ${escapeHtml(data.match_reason)}
                                    </div>
                                ` : ''}
                                <dl class="space-y-2 text-sm">
                                    <div>
                                        <dt class="font-medium text-gray-700">Současný název:</dt>
                                        <dd class="text-gray-900">${escapeHtml(data.raynet?.name || '-')}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-700">Raynet ID:</dt>
                                        <dd class="text-gray-900">
                                            <a href="https://app.raynet.cz/electree/?view=DetailView&en=Company&ei=${data.raynet?.id}" 
                                               target="_blank" class="text-primary-600 hover:underline">
                                                #${data.raynet?.id} →
                                            </a>
                                        </dd>
                                    </div>
                                </dl>
                            ` : `
                                <div class="mb-3 p-2 bg-green-100 border border-green-300 rounded text-sm text-green-800">
                                    ✨ Nová firma - bude vytvořena
                                </div>
                                <dl class="space-y-2 text-sm">
                                    <div>
                                        <dt class="font-medium text-gray-700">Název:</dt>
                                        <dd class="text-gray-900">${escapeHtml(data.local.company_name || '-')}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-700">Stav:</dt>
                                        <dd class="text-gray-900">A_POTENTIAL (Potenciální zákazník)</dd>
                                    </div>
                                </dl>
                            `}
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button onclick="this.closest('.fixed').remove()" 
                                class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                            ✕ Zrušit
                        </button>
                        <button onclick="confirmSync('${formId}', ${data.raynet_exists ? data.raynet?.id : 'null'})" 
                                class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                            ✓ Potvrdit a synchronizovat
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        function showCandidateSelectionModal(formId, data) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.innerHTML = `
                <div class="relative top-10 mx-auto p-6 border w-full max-w-5xl shadow-lg rounded-md bg-white">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-900">🔍 Nalezeno více možných shod</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded">
                        <p class="text-sm text-yellow-800">
                            <strong>⚠️ Pozor:</strong> Bylo nalezeno více možných firem v Raynet. Vyberte správnou firmu pro synchronizaci, nebo vytvořte novou.
                        </p>
                    </div>
                    
                    <!-- Local Data Summary -->
                    <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">📝 Lokální data:</h4>
                        <div class="grid grid-cols-4 gap-4 text-sm">
                            <div><span class="text-gray-500">Firma:</span> <strong>${escapeHtml(data.local.company_name || '-')}</strong></div>
                            <div><span class="text-gray-500">IČO:</span> <strong>${escapeHtml(data.local.ico || '-')}</strong></div>
                            <div><span class="text-gray-500">Email:</span> <strong>${escapeHtml(data.local.email || '-')}</strong></div>
                            <div><span class="text-gray-500">Kontakt:</span> <strong>${escapeHtml(data.local.contact_person || '-')}</strong></div>
                        </div>
                    </div>
                    
                    <!-- Candidate List -->
                    <div class="mb-6">
                        <h4 class="font-semibold text-gray-900 mb-3">☁️ Nalezené firmy v Raynet:</h4>
                        <div class="space-y-3">
                            ${data.candidates.map((candidate, index) => `
                                <div class="border rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition-colors candidate-option" 
                                     onclick="selectCandidate('${formId}', ${candidate.id}, this)">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <input type="radio" name="candidate" value="${candidate.id}" 
                                                   class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                                            <div class="ml-3">
                                                <div class="font-medium text-gray-900">${escapeHtml(candidate.name || '-')}</div>
                                                <div class="text-sm text-gray-500">
                                                    IČO: ${escapeHtml(candidate.regNumber || '-')} | 
                                                    Email: ${escapeHtml(candidate.email || '-')}
                                                </div>
                                                <div class="text-xs text-blue-600 mt-1">
                                                    ${escapeHtml(candidate.match_reason || '')}
                                                </div>
                                            </div>
                                        </div>
                                        <a href="https://app.raynet.cz/electree/?view=DetailView&en=Company&ei=${candidate.id}" 
                                           target="_blank" class="text-primary-600 hover:text-primary-800 text-sm"
                                           onclick="event.stopPropagation()">
                                            Zobrazit v Raynet →
                                        </a>
                                    </div>
                                </div>
                            `).join('')}
                            
                            <!-- Create New Option -->
                            <div class="border-2 border-dashed border-green-300 rounded-lg p-4 hover:bg-green-50 cursor-pointer transition-colors candidate-option"
                                 onclick="selectCandidate('${formId}', null, this)">
                                <div class="flex items-center">
                                    <input type="radio" name="candidate" value="new" 
                                           class="h-4 w-4 text-green-600 focus:ring-green-500">
                                    <div class="ml-3">
                                        <div class="font-medium text-green-700">✨ Vytvořit novou firmu</div>
                                        <div class="text-sm text-green-600">
                                            Žádná z výše uvedených firem neodpovídá - vytvořit nový záznam v Raynet
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button onclick="this.closest('.fixed').remove()" 
                                class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                            ✕ Zrušit
                        </button>
                        <button id="confirmSyncBtn" onclick="confirmSelectedSync('${formId}')" 
                                class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            ✓ Synchronizovat vybranou firmu
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        let selectedCandidateId = null;
        
        function selectCandidate(formId, candidateId, element) {
            // Remove selection from all
            document.querySelectorAll('.candidate-option').forEach(el => {
                el.classList.remove('ring-2', 'ring-primary-500', 'bg-primary-50');
            });
            
            // Add selection to clicked
            element.classList.add('ring-2', 'ring-primary-500', 'bg-primary-50');
            element.querySelector('input[type="radio"]').checked = true;
            
            // Store selected ID
            selectedCandidateId = candidateId;
            
            // Enable confirm button
            document.getElementById('confirmSyncBtn').disabled = false;
        }
        
        async function confirmSelectedSync(formId) {
            // Close modal
            document.querySelector('.fixed.inset-0').remove();
            
            await confirmSync(formId, selectedCandidateId);
            selectedCandidateId = null;
        }
        
        async function confirmSync(formId, selectedCompanyId = null) {
            // Close modal if exists
            const modal = document.querySelector('.fixed.inset-0');
            if (modal) modal.remove();
            
            try {
                showToast('Synchronizuji formulář...', 'info');
                
                console.log('[Sync] Confirming sync for form:', formId, 'with selected company:', selectedCompanyId);
                
                const data = await apiCall({
                    action: 'sync_form',
                    form_id: formId,
                    target_company_id: selectedCompanyId // null = create new, ID = link to existing
                }, true);
                
                console.log('[Sync] Response:', data);
                
                if (data.success) {
                    showToast(data.message || 'Synchronizace úspěšná', 'success');
                    loadLocalForms(localPage);
                    loadStats();
                } else {
                    showToast(data.message || 'Synchronizace selhala', 'error');
                }
            } catch (error) {
                console.error('[Sync] Error during single form sync:', error);
                log.error('Sync failed', error);
                
                const errorMsg = error.message || 'Chyba při synchronizaci';
                showToast(errorMsg, 'error');
            }
        }

        async function retrySingleForm(formId) {
            await syncSingleForm(formId);
        }

        async function syncAllPending() {
            const btn = document.getElementById('syncAllBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="sync-pulse mr-2">🔄</span> Synchronizuji...';
            
            showSyncProgress();
            
            try {
                const data = await apiCall({
                    action: 'sync_all_pending'
                }, true);
                
                hideSyncProgress();
                
                if (data.success) {
                    showToast(data.message || `Synchronizováno ${data.data.success} formulářů`, 'success');
                    loadLocalForms(localPage);
                    loadStats();
                } else {
                    showToast(data.message || 'Synchronizace selhala', 'error');
                }
            } catch (error) {
                hideSyncProgress();
                log.error('Sync all failed', error);
                
                // Show detailed error message
                const errorMsg = error.message || 'Chyba při hromadné synchronizaci';
                showToast(errorMsg, 'error');
                
                // Log to console for debugging
                console.error('Sync all pending error:', error);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span class="mr-2">🔄</span> Synchronizovat vše';
            }
        }

        async function retryAllErrors() {
            showSyncProgress();
            
            try {
                const data = await apiCall({
                    action: 'retry_errors'
                }, true);
                
                hideSyncProgress();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    loadLocalForms(localPage);
                    loadStats();
                }
            } catch (error) {
                hideSyncProgress();
                log.error('Retry errors failed', error);
                showToast('Chyba při opakování synchronizace', 'error');
            }
        }

        async function clearError(formId) {
            try {
                const data = await apiCall({
                    action: 'clear_error',
                    form_id: formId
                }, true);
                
                if (data.success) {
                    showToast(data.message, 'success');
                    loadLocalForms(localPage);
                    loadStats();
                }
            } catch (error) {
                log.error('Clear error failed', error);
                showToast('Chyba při mazání', 'error');
            }
        }

        async function testConnection() {
            try {
                showToast('Testuji připojení...', 'info');
                
                const data = await apiCall({ action: 'test_connection' });
                
                if (data.connected) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
                
                loadStats();
            } catch (error) {
                log.error('Connection test failed', error);
                showToast('Test připojení selhal', 'error');
            }
        }

        function refreshStats() {
            loadStats();
            
            // Reload current tab data
            if (currentTab === 'local') {
                loadLocalForms(localPage);
            } else if (currentTab === 'raynet') {
                const search = document.getElementById('raynet-search').value;
                if (search) {
                    loadRaynetCompanies(raynetPage);
                }
            } else if (currentTab === 'logs') {
                loadLogs(logsPage);
            }
            
            showToast('Data obnovena', 'info');
        }

        // Progress modal
        function showSyncProgress() {
            document.getElementById('syncProgressModal').classList.remove('hidden');
        }

        function hideSyncProgress() {
            document.getElementById('syncProgressModal').classList.add('hidden');
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

        // ══════════════════════════════════════════════════════════════════
        // 🔬 FIELD COMPARISON TESTER
        // ══════════════════════════════════════════════════════════════════

        /**
         * Called when admin clicks 🔬 Porovnat, OR from the action button in the forms table.
         */
        async function runFieldComparison(formId = null) {
            const inputEl = document.getElementById('tester-form-id');
            const id = formId || inputEl.value.trim();
            if (!id) { showToast('Zadejte ID formuláře', 'error'); return; }

            // Pre-fill input & switch to tester tab
            if (formId) {
                inputEl.value = formId;
                switchTab('tester');
            }

            const container = document.getElementById('tester-result');
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-16">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600 mb-4"></div>
                    <p class="text-gray-500 text-sm">Načítám data z Raynet...</p>
                </div>`;

            try {
                const data = await apiCall({ action: 'field_comparison', form_id: id });
                if (!data.success) throw new Error(data.error);
                renderComparisonResult(container, data.data);
            } catch (err) {
                log.error('Field comparison failed', err);
                container.innerHTML = `
                    <div class="text-center py-12">
                        <div class="text-red-400 text-5xl mb-4">⚠️</div>
                        <h3 class="text-lg font-medium text-gray-800 mb-2">Chyba při načítání</h3>
                        <p class="text-sm text-gray-500">${escapeHtml(err.message)}</p>
                    </div>`;
            }
        }

        function renderComparisonResult(container, data) {
            const meta    = data.meta;
            const summary = data.summary;
            const linked  = meta.raynet_linked;

            const statusBadge = (s) => {
                switch (s) {
                    case 'match':    return '<span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800">✓ Shoda</span>';
                    case 'mismatch': return '<span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-red-100 text-red-800">✗ Rozdíl</span>';
                    case 'missing':  return '<span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-800">⚠ Chybí</span>';
                    case 'extra':    return '<span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-800">ℹ Extra</span>';
                    default:         return '<span class="inline-block px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-500">–</span>';
                }
            };

            const rowClass = (s) => {
                switch (s) {
                    case 'mismatch': return 'bg-red-50 border-l-4 border-red-400';
                    case 'missing':  return 'bg-amber-50 border-l-4 border-amber-400';
                    case 'match':    return 'bg-green-50';
                    case 'extra':    return 'bg-blue-50';
                    default:         return 'bg-white';
                }
            };

            const buildTable = (rows, title) => {
                if (!rows || rows.length === 0) return '';
                const TABLE_ROWS = rows.map(r => `
                    <tr class="${rowClass(r.status)} hover:brightness-95 transition-all">
                        <td class="px-4 py-2.5 text-xs font-medium text-gray-700 whitespace-nowrap w-44">${escapeHtml(r.label)}</td>
                        <td class="px-4 py-2.5 text-xs text-gray-400 font-mono whitespace-nowrap w-52">${escapeHtml(r.key)}</td>
                        <td class="px-4 py-2.5 text-sm text-gray-900 max-w-xs">
                            <div class="truncate" title="${escapeHtml(r.local_value ?? '')}">${
                                r.local_value !== null
                                    ? `<span class="text-blue-800 font-medium">${escapeHtml(r.local_value)}</span>`
                                    : '<span class="text-gray-300 italic">–</span>'
                            }</div>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-gray-900 max-w-xs">
                            <div class="truncate" title="${escapeHtml(r.raynet_value ?? '')}">${
                                r.raynet_value !== null
                                    ? `<span class="${r.status === 'mismatch' ? 'text-red-700 font-semibold' : 'text-gray-800'}">${escapeHtml(r.raynet_value)}</span>`
                                    : '<span class="text-gray-300 italic">–</span>'
                            }</div>
                        </td>
                        <td class="px-4 py-2.5 text-center">${statusBadge(r.status)}</td>
                    </tr>`).join('');

                return `
                    <div class="mb-8">
                        <h4 class="text-base font-semibold text-gray-800 mb-2">${title}</h4>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase w-44">Pole</th>
                                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase w-52">Klíč (API)</th>
                                        <th class="px-4 py-2 text-left text-xs font-bold text-blue-600 uppercase">📤 Odesíláme (local)</th>
                                        <th class="px-4 py-2 text-left text-xs font-bold text-purple-600 uppercase">☁️ V Raynet (live)</th>
                                        <th class="px-4 py-2 text-center text-xs font-bold text-gray-500 uppercase">Stav</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">${TABLE_ROWS}</tbody>
                            </table>
                        </div>
                    </div>`;
            };

            const summaryPill = (count, label, color) =>
                `<span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-full ${color}">${count} ${label}</span>`;

            const raynetUrl = meta.raynet_company_id
                ? `https://app.raynet.cz/electree/?view=DetailView&en=Company&ei=${meta.raynet_company_id}`
                : null;

            container.innerHTML = `
                <!-- Header -->
                <div class="px-6 pt-6 pb-4 border-b border-gray-200">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">🔬 Výsledek porovnání polí</h3>
                            <p class="text-sm text-gray-500 mt-0.5">
                                Formulář <strong>#${escapeHtml(String(meta.form_id))}</strong> &mdash;
                                ${escapeHtml(meta.company_name || '?')}
                                ${meta.contact_person ? '&bull; ' + escapeHtml(meta.contact_person) : ''}
                            </p>
                            ${meta.fetch_error ? `<p class="text-xs text-amber-600 mt-1">⚠️ ${escapeHtml(meta.fetch_error)}</p>` : ''}
                        </div>
                        <div class="flex flex-wrap gap-2 items-center">
                            ${linked ? '' : '<span class="text-xs text-amber-700 bg-amber-100 px-2 py-1 rounded">⚠️ Formulář není synchronizován – zobrazena jen lokální data</span>'}
                            ${meta.raynet_company_id ? `<a href="${raynetUrl}" target="_blank" class="text-xs text-primary-600 hover:underline px-3 py-1 border border-primary-200 rounded">Otevřít v Raynet →</a>` : ''}
                            <button onclick="runFieldComparison('${escapeHtml(String(meta.form_id))}')"
                                    class="text-xs text-gray-600 hover:text-gray-800 px-3 py-1 border border-gray-200 rounded">
                                🔄 Obnovit
                            </button>
                        </div>
                    </div>

                    <!-- Summary pills -->
                    <div class="flex flex-wrap gap-2 mt-4">
                        <span class="text-xs text-gray-500 self-center">Firma:</span>
                        ${summaryPill(summary.company_match,    'shoda',   'bg-green-100 text-green-800')}
                        ${summaryPill(summary.company_mismatch, 'rozdíl',  'bg-red-100 text-red-800')}
                        ${summaryPill(summary.company_missing,  'chybí',   'bg-amber-100 text-amber-800')}
                        <span class="mx-2 text-gray-300">|</span>
                        <span class="text-xs text-gray-500 self-center">Osoba:</span>
                        ${summaryPill(summary.person_match,     'shoda',   'bg-green-100 text-green-800')}
                        ${summaryPill(summary.person_mismatch,  'rozdíl',  'bg-red-100 text-red-800')}
                        ${summaryPill(summary.person_missing,   'chybí',   'bg-amber-100 text-amber-800')}
                    </div>
                </div>

                <!-- Legend -->
                <div class="px-6 pt-4 pb-2 flex flex-wrap gap-3 bg-gray-50 border-b border-gray-100">
                    <span class="text-xs text-gray-500">Legenda:</span>
                    ${statusBadge('match')}   <span class="text-xs text-gray-500 mr-2">Hodnoty shodné</span>
                    ${statusBadge('mismatch')}<span class="text-xs text-gray-500 mr-2">Hodnoty se liší</span>
                    ${statusBadge('missing')} <span class="text-xs text-gray-500 mr-2">Odesíláno, ale chybí v Raynet</span>
                    ${statusBadge('extra')}   <span class="text-xs text-gray-500">V Raynet navíc (neodesíláno)</span>
                </div>

                <!-- Tables -->
                <div class="p-6 overflow-y-auto max-h-[70vh] space-y-2">
                    ${buildTable(data.company_rows, '🏢 Firma (Company)')}
                    ${buildTable(data.person_rows, '👤 Kontaktní osoba (Person)')}
                </div>
            `;
        }

        // ══════════════════════════════════════════════════════════════════
        // Initialize
        document.addEventListener('DOMContentLoaded', async function() {
            log.info('Sync page initializing...');
            
            await getCSRFToken();
            await loadStats();
            await loadLocalForms(1);
            
            // Search on Enter
            document.getElementById('raynet-search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loadRaynetCompanies(1);
                }
            });
            
            log.info('Sync page initialized');
        });
    </script>
</body>
</html>
