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
        $tracker->logActivity($_SESSION['user_id'], 'page_view', 'Zobrazení activity logu');
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
    <title>Aktivita uživatelů - Admin Panel</title>
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
                        }
                    }
                }
            }
        };
    </script>
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
                        <a href="admin-activity.php" class="border-primary-500 text-primary-600 border-b-2 py-4 px-1 text-sm font-medium">
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
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Aktivita uživatelů
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Přehled všech aktivit v systému
                    </p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="exportActivity()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        📊 Export
                    </button>
                    <button onclick="clearOldActivity()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        🗑️ Vyčistit staré
                    </button>
                </div>
            </div>

            <!-- Activity Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm">
                                    📋
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Celkem aktivit</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="total-activities">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm">
                                    👥
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Aktivní uživatelé</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="active-users">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white text-sm">
                                    📅
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Dnes</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="today-activities">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center text-white text-sm">
                                    🔥
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Posledních 24h</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="recent-activities">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Uživatel</label>
                        <input type="text" id="user-search" placeholder="Jméno nebo email..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Typ akce</label>
                        <select id="action-filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="">Všechny akce</option>
                            <option value="login">Přihlášení</option>
                            <option value="logout">Odhlášení</option>
                            <option value="page_view">Zobrazení stránky</option>
                            <option value="form_submit">Odeslání formuláře</option>
                            <option value="admin_action">Admin akce</option>
                            <option value="user_action">Uživatelská akce</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Datum od</label>
                        <input type="date" id="date-from" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Datum do</label>
                        <input type="date" id="date-to" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                </div>
                <div class="mt-4 flex justify-between">
                    <button onclick="searchActivity()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm">
                        Vyhledat
                    </button>
                    <button onclick="clearActivityFilters()" class="text-gray-600 hover:text-gray-800 text-sm">
                        Vymazat filtry
                    </button>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Timeline aktivit</h3>
                </div>
                <div class="p-6">
                    <div id="activity-timeline">
                        <div class="animate-pulse">
                            <div class="h-4 bg-gray-200 rounded w-full mb-4"></div>
                            <div class="h-4 bg-gray-200 rounded w-full mb-4"></div>
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        </div>
                    </div>
                    
                    <!-- Load More -->
                    <div id="load-more-container" class="text-center mt-6 hidden">
                        <button onclick="loadMoreActivity()" id="load-more-btn" 
                                class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Načíst více aktivit
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Activity Detail Modal -->
    <div id="activityDetailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Detail aktivity</h3>
                    <button onclick="hideActivityDetailModal()" class="text-gray-400 hover:text-gray-600">
                        <span class="sr-only">Zavřít</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <div id="activityDetailContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Console logging utility
        const log = {
            info: (msg, data = null) => {
                console.log(`[Activity] ${msg}`, data);
            },
            error: (msg, error = null) => {
                console.error(`[Activity] ${msg}`, error);
            },
            warn: (msg, data = null) => {
                console.warn(`[Activity] ${msg}`, data);
            }
        };

        let currentActivityPage = 1;
        let isLoadingActivity = false;
        let hasMoreActivity = true;

        // Load activity data using renamed API
        async function loadActivity(page = 1, append = false, search = '', action = '', dateFrom = '', dateTo = '') {
            if (isLoadingActivity) return;
            
            log.info('Loading activity...', { page, append, search, action, dateFrom, dateTo });
            isLoadingActivity = true;
            
            try {
                const response = await fetch('admin-activity-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'get_activity_log',
                        page: page,
                        per_page: 50,
                        search: search,
                        action_type: action,
                        date_from: dateFrom,
                        date_to: dateTo
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                log.info('Activity data loaded', data);

                if (data.success) {
                    displayActivity(data.activities || [], append);
                    updateActivityStats(data.stats || {});
                    
                    hasMoreActivity = data.has_more || false;
                    document.getElementById('load-more-container').classList.toggle('hidden', !hasMoreActivity);
                    
                    if (append) {
                        currentActivityPage = page;
                    } else {
                        currentActivityPage = 1;
                    }
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            } catch (error) {
                log.error('Failed to load activity', error);
                showToast('Nepodařilo se načíst aktivitu', 'error');
            } finally {
                isLoadingActivity = false;
            }
        }

        function displayActivity(activities, append = false) {
            const container = document.getElementById('activity-timeline');
            
            if (!activities || activities.length === 0) {
                if (!append) {
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <div class="text-gray-400 text-lg mb-2">📋</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-1">Žádná aktivita</h3>
                            <p class="text-gray-500">Nebyly nalezeny žádné aktivity odpovídající kritériím.</p>
                        </div>
                    `;
                }
                return;
            }

            const activityHTML = activities.map(activity => {
                const timestamp = new Date(activity.timestamp || activity.created_at);
                const timeStr = timestamp.toLocaleString('cs-CZ');
                const timeAgo = getTimeAgo(timestamp);
                
                return `
                    <div class="relative pl-8 pb-8 border-l-2 border-gray-200 last:border-l-0">
                        <div class="absolute -left-2 top-0 w-4 h-4 bg-white border-2 ${getActivityColor(activity.action_type)} rounded-full"></div>
                        <div class="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 cursor-pointer transition-colors" 
                             onclick="showActivityDetail('${activity.id}')">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <span class="text-sm font-medium text-gray-900">
                                            ${activity.user_name || 'Neznámý uživatel'}
                                        </span>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getActionTypeClass(activity.action_type)}">
                                            ${getActionTypeLabel(activity.action_type)}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-2">
                                        ${activity.description || 'Bez popisu'}
                                    </p>
                                    <div class="flex items-center space-x-4 text-xs text-gray-500">
                                        <span>📧 ${activity.user_email || 'N/A'}</span>
                                        <span>🌐 IP: ${activity.ip_address || 'N/A'}</span>
                                        <span>📱 ${activity.user_agent ? getUserAgentInfo(activity.user_agent) : 'N/A'}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-gray-900">${timeAgo}</div>
                                    <div class="text-xs text-gray-500">${timeStr}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            if (append && container.innerHTML !== '') {
                container.innerHTML += activityHTML;
            } else {
                container.innerHTML = activityHTML;
            }
        }

        function getActivityColor(actionType) {
            switch(actionType) {
                case 'login': return 'border-green-500';
                case 'logout': return 'border-red-500';
                case 'page_view': return 'border-blue-500';
                case 'form_submit': return 'border-purple-500';
                case 'admin_action': return 'border-orange-500';
                default: return 'border-gray-500';
            }
        }

        function getActionTypeClass(actionType) {
            switch(actionType) {
                case 'login': return 'bg-green-100 text-green-800';
                case 'logout': return 'bg-red-100 text-red-800';
                case 'page_view': return 'bg-blue-100 text-blue-800';
                case 'form_submit': return 'bg-purple-100 text-purple-800';
                case 'admin_action': return 'bg-orange-100 text-orange-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function getActionTypeLabel(actionType) {
            switch(actionType) {
                case 'login': return 'Přihlášení';
                case 'logout': return 'Odhlášení';
                case 'page_view': return 'Zobrazení';
                case 'form_submit': return 'Formulář';
                case 'admin_action': return 'Admin';
                case 'user_action': return 'Akce';
                default: return actionType || 'Neznámý';
            }
        }

        function getUserAgentInfo(userAgent) {
            if (!userAgent) return 'Neznámý';
            
            if (userAgent.includes('Chrome')) return 'Chrome';
            if (userAgent.includes('Firefox')) return 'Firefox';
            if (userAgent.includes('Safari')) return 'Safari';
            if (userAgent.includes('Edge')) return 'Edge';
            if (userAgent.includes('Mobile')) return 'Mobil';
            
            return 'Ostatní';
        }

        function getTimeAgo(timestamp) {
            const now = new Date();
            const diff = now - timestamp;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);

            if (minutes < 1) return 'Právě teď';
            if (minutes < 60) return `Před ${minutes} min`;
            if (hours < 24) return `Před ${hours} h`;
            if (days < 7) return `Před ${days} dny`;
            return timestamp.toLocaleDateString('cs-CZ');
        }

        function updateActivityStats(stats) {
            document.getElementById('total-activities').textContent = stats.total || 0;
            document.getElementById('active-users').textContent = stats.active_users || 0;
            document.getElementById('today-activities').textContent = stats.today || 0;
            document.getElementById('recent-activities').textContent = stats.recent_24h || 0;
        }

        function loadMoreActivity() {
            const search = document.getElementById('user-search').value;
            const action = document.getElementById('action-filter').value;
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;
            loadActivity(currentActivityPage + 1, true, search, action, dateFrom, dateTo);
        }

        function searchActivity() {
            const search = document.getElementById('user-search').value;
            const action = document.getElementById('action-filter').value;
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;
            loadActivity(1, false, search, action, dateFrom, dateTo);
        }

        function clearActivityFilters() {
            document.getElementById('user-search').value = '';
            document.getElementById('action-filter').value = '';
            document.getElementById('date-from').value = '';
            document.getElementById('date-to').value = '';
            loadActivity(1);
        }

        async function showActivityDetail(activityId) {
            // TODO: Implementovat v API
            console.log('Zobrazení detailu aktivity není zatím implementováno');
            /*
            try {
                const response = await fetch('admin-activity-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'get_activity_detail',
                        activity_id: activityId
                    })
                });

                const data = await response.json();
                
                if (data.success && data.activity) {
                    displayActivityDetail(data.activity);
                    document.getElementById('activityDetailModal').classList.remove('hidden');
                } else {
                    throw new Error(data.message || 'Activity not found');
                }
            } catch (error) {
                log.error('Failed to load activity detail', error);
                showToast('Nepodařilo se načíst detail aktivity', 'error');
            }
        }

        function displayActivityDetail(activity) {
            const timestamp = new Date(activity.timestamp || activity.created_at);
            const timeStr = timestamp.toLocaleString('cs-CZ');
            
            document.getElementById('activityDetailContent').innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Uživatel</label>
                            <p class="mt-1 text-sm text-gray-900">${activity.user_name || 'Neznámý'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <p class="mt-1 text-sm text-gray-900">${activity.user_email || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Typ akce</label>
                            <p class="mt-1">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getActionTypeClass(activity.action_type)}">
                                    ${getActionTypeLabel(activity.action_type)}
                                </span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Čas</label>
                            <p class="mt-1 text-sm text-gray-900">${timeStr}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">IP adresa</label>
                            <p class="mt-1 text-sm text-gray-900">${activity.ip_address || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Prohlížeč</label>
                            <p class="mt-1 text-sm text-gray-900">${getUserAgentInfo(activity.user_agent)}</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Popis</label>
                        <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded-md">
                            ${activity.description || 'Bez popisu'}
                        </p>
                    </div>
                    
                    ${activity.additional_data ? `
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Dodatečná data</label>
                            <pre class="mt-1 text-xs text-gray-600 bg-gray-50 p-3 rounded-md overflow-auto max-h-40">
${JSON.stringify(JSON.parse(activity.additional_data), null, 2)}
                            </pre>
                        </div>
                    ` : ''}
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">User Agent</label>
                        <p class="mt-1 text-xs text-gray-600 bg-gray-50 p-3 rounded-md break-all">
                            ${activity.user_agent || 'N/A'}
                        </p>
                    </div>
                </div>
            `;
        }

        function hideActivityDetailModal() {
            document.getElementById('activityDetailModal').classList.add('hidden');
        }

        async function exportActivity() {
            // TODO: Implementovat v API
            console.log('Export aktivity není zatím implementován');
            /*
            log.info('Exporting activity...');
            
            try {
                const search = document.getElementById('user-search').value;
                const action = document.getElementById('action-filter').value;
                const dateFrom = document.getElementById('date-from').value;
                const dateTo = document.getElementById('date-to').value;
                
                const response = await fetch('admin-activity-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'export_activities',
                        search: search,
                        action_type: action,
                        date_from: dateFrom,
                        date_to: dateTo
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    exportToCSV(data.activities);
                    showToast('Export byl úspěšně stažen', 'success');
                } else {
                    throw new Error(data.message || 'Export failed');
                }
            } catch (error) {
                log.error('Failed to export activity', error);
                showToast('Nepodařilo se exportovat aktivitu', 'error');
            }
            */
        }

        function exportToCSV(activities) {
            const headers = ['Čas', 'Uživatel', 'Email', 'Typ akce', 'Popis', 'IP adresa'];
            const csvContent = [headers.join(',')];
            
            activities.forEach(activity => {
                const row = [
                    activity.timestamp || activity.created_at,
                    `"${(activity.user_name || '').replace(/"/g, '""')}"`,
                    activity.user_email || '',
                    getActionTypeLabel(activity.action_type),
                    `"${(activity.description || '').replace(/"/g, '""')}"`,
                    activity.ip_address || ''
                ];
                csvContent.push(row.join(','));
            });
            
            const csvString = csvContent.join('\n');
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'aktivita_' + new Date().toISOString().split('T')[0] + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        function clearOldActivity() {
            showConfirmModal(
                'Vyčistit starou aktivitu',
                'Opravdu chcete smazat aktivitu starší než 30 dní? Tuto akci nelze vrátit zpět.',
                async () => {
                    try {
                        const response = await fetch('admin-activity-api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'clear_old_activities' })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            showToast(`Bylo smazáno ${data.deleted_count || 0} starých aktivit`, 'success');
                            loadActivity(1); // Refresh the list
                        } else {
                            throw new Error(data.message || 'Clear failed');
                        }
                    } catch (error) {
                        log.error('Failed to clear old activity', error);
                        showToast('Nepodařilo se vyčistit starou aktivitu', 'error');
                    }
                }
            );
        }

        function showToast(message, type = 'info') {
            const bgColor = type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-500' : 'bg-blue-500';
            const icon = type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️';
            
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 ${bgColor} text-white p-4 rounded-lg shadow-lg z-50 max-w-sm`;
            toast.innerHTML = `
                <div class="flex items-center">
                    <span class="mr-2">${icon}</span>
                    <span class="flex-1">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">✕</button>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }

        function showConfirmModal(title, message, onConfirm) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3 text-center">
                        <h3 class="text-lg font-medium text-gray-900">${title}</h3>
                        <div class="mt-2 px-7 py-3">
                            <p class="text-sm text-gray-500">${message}</p>
                        </div>
                        <div class="flex justify-center space-x-3 px-4 py-3">
                            <button onclick="this.closest('.fixed').remove()" 
                                    class="px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md hover:bg-gray-400">
                                Zrušit
                            </button>
                            <button onclick="this.closest('.fixed').remove(); (${onConfirm})()" 
                                    class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md hover:bg-red-700">
                                Smazat
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // Auto-refresh activity every 30 seconds
        let refreshInterval;

        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                if (currentActivityPage === 1) {
                    log.info('Auto-refreshing activity...');
                    loadActivity(1);
                }
            }, 30000);
        }

        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            log.info('Activity page initializing...');
            loadActivity();
            startAutoRefresh();
            
            // Search on Enter key
            document.getElementById('user-search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchActivity();
                }
            });
            
            // Stop auto-refresh when page is hidden
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopAutoRefresh();
                } else {
                    startAutoRefresh();
                }
            });
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });
    </script>
</body>
</html>
