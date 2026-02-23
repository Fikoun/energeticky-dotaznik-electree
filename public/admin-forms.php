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
        $tracker->logActivity($_SESSION['user_id'], 'page_view', 'Zobrazení správy formulářů');
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
    <title>Správa formulářů - Admin Panel</title>
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
                        <a href="admin-forms.php" class="border-primary-500 text-primary-600 border-b-2 py-4 px-1 text-sm font-medium">
                            📝 Formuláře
                        </a>
                        <a href="admin-sync.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-4 px-1 text-sm font-medium">
                            ⌘ Synchronizace
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
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        Správa formulářů
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Přehled a správa všech odeslaných formulářů
                    </p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="exportForms()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        📊 Export
                    </button>
                    <button onclick="showStatsModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        📈 Statistiky
                    </button>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm">
                                    📝
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Celkem formulářů</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="total-forms">-</dd>
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
                                    ✅
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Zpracované</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="processed-forms">-</dd>
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
                                    ⏳
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Čekající</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="pending-forms">-</dd>
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
                                    📅
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Tento měsíc</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="monthly-forms">-</dd>
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
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hledat formuláře</label>
                        <input type="text" id="form-search" placeholder="Jméno, email, město..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="status-filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="">Všechny statusy</option>
                            <option value="pending">Čekající</option>
                            <option value="processing">Zpracovává se</option>
                            <option value="completed">Dokončeno</option>
                            <option value="cancelled">Zrušeno</option>
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
                    <button onclick="searchForms()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm">
                        Vyhledat
                    </button>
                    <button onclick="clearFormFilters()" class="text-gray-600 hover:text-gray-800 text-sm">
                        Vymazat filtry
                    </button>
                </div>
            </div>

            <!-- Forms Table -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Seznam formulářů</h3>
                </div>
                <div class="overflow-x-auto">
                    <div id="forms-table">
                        <div class="animate-pulse p-6">
                            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div id="forms-pagination" class="px-6 py-4 border-t border-gray-200 hidden">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Zobrazeno <span id="forms-showing-start">1</span> až <span id="forms-showing-end">20</span> z celkem <span id="forms-total">0</span> formulářů
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <button id="forms-prev-btn" onclick="changeFormsPage(currentFormsPage - 1)" 
                                    class="px-3 py-1 border rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Předchozí
                            </button>
                            <span id="forms-page-info" class="px-3 py-1 text-sm text-gray-700">Stránka 1 z 1</span>
                            <button id="forms-next-btn" onclick="changeFormsPage(currentFormsPage + 1)" 
                                    class="px-3 py-1 border rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Další
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Form Status Update Modal -->
    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Změnit status formuláře</h3>
                    <button onclick="hideStatusModal()" class="text-gray-400 hover:text-gray-600">
                        <span class="sr-only">Zavřít</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <form id="statusForm" onsubmit="submitStatusUpdate(event)">
                    <input type="hidden" id="statusFormId" name="form_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nový status</label>
                        <select id="newStatus" name="status" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="pending">Čekající</option>
                            <option value="processing">Zpracovává se</option>
                            <option value="completed">Dokončeno</option>
                            <option value="cancelled">Zrušeno</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Poznámka (volitelné)</label>
                        <textarea id="statusNote" name="note" rows="3" 
                                  placeholder="Důvod změny statusu..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideStatusModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Zrušit
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-primary-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-primary-700">
                            Uložit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Statistics Modal -->
    <div id="statsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Detailní statistiky formulářů</h3>
                    <button onclick="hideStatsModal()" class="text-gray-400 hover:text-gray-600">
                        <span class="sr-only">Zavřít</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <div id="statsContent">
                    <div class="animate-pulse">
                        <div class="h-4 bg-gray-200 rounded w-full mb-4"></div>
                        <div class="h-64 bg-gray-200 rounded mb-4"></div>
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Console logging utility
        const log = {
            info: (msg, data = null) => {
                console.log(`[Forms] ${msg}`, data);
            },
            error: (msg, error = null) => {
                console.error(`[Forms] ${msg}`, error);
            },
            warn: (msg, data = null) => {
                console.warn(`[Forms] ${msg}`, data);
            }
        };

        let currentFormsPage = 1;
        const formsPageSize = 20;

        // Load forms using the renamed API
        async function loadForms(page = 1, search = '', status = '', dateFrom = '', dateTo = '') {
            log.info('Loading forms...', { page, search, status, dateFrom, dateTo });
            currentFormsPage = page;
            
            try {
                const response = await fetch('admin-forms-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'list_forms',
                        page: page,
                        limit: formsPageSize,
                        search: search,
                        status_filter: status,
                        date_from: dateFrom,
                        date_to: dateTo
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                log.info('Forms data loaded', data);

                if (data.success) {
                    displayForms(data.forms || []);
                    updateFormsPagination(data.pagination || {});
                    updateFormsStats(data.forms || []);
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            } catch (error) {
                log.error('Failed to load forms', error);
                showToast('Nepodařilo se načíst seznam formulářů', 'error');
            }
        }

        function displayForms(forms) {
            const container = document.getElementById('forms-table');
            
            if (!forms || forms.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-lg mb-2">📝</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Žádné formuláře</h3>
                        <p class="text-gray-500">Nebyly nalezeny žádné formuláře odpovídající kritériím.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Zákazník</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kontakt</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Společnost</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Datum</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Akce</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${forms.map(form => `
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-primary-500 flex items-center justify-center text-white font-medium">
                                                ${(form.user_name || form.contact_person || 'U').charAt(0).toUpperCase()}
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">${form.user_name || form.contact_person || 'Neznámý'}</div>
                                            <div class="text-sm text-gray-500">${form.user_email || form.email || ''}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>${form.phone || '-'}</div>
                                    <div class="text-xs text-gray-400">ID: ${form.id}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        ${form.company_name || '-'}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getFormStatusClass(form.status)}">
                                        ${getFormStatusLabel(form.status)}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${form.created_at ? new Date(form.created_at).toLocaleDateString('cs-CZ') : '-'}
                                    <div class="text-xs text-gray-400">
                                        ${form.updated_at ? 'Aktualizováno: ' + new Date(form.updated_at).toLocaleDateString('cs-CZ') : ''}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="viewFormDetail('${form.id}')" class="text-blue-600 hover:text-blue-900 mr-3">
                                        Detail
                                    </button>
                                    <button onclick="syncWithRaynet('${form.id}')" class="text-purple-600 hover:text-purple-900 mr-3" title="Synchronizovat s Raynet">
                                        ⌘ Sync
                                    </button>
                                    <button onclick="changeFormStatus('${form.id}', '${form.status}')" class="text-green-600 hover:text-green-900 mr-3">
                                        Status
                                    </button>
                                    <button onclick="confirmDeleteForm('${form.id}', '${form.user_name || form.contact_person}')" class="text-red-600 hover:text-red-900">
                                        Smazat
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        function getFormStatusClass(status) {
            switch(status) {
                case 'pending':
                case 'draft': return 'bg-yellow-100 text-yellow-800';
                case 'processing': return 'bg-blue-100 text-blue-800';
                case 'completed':
                case 'confirmed':
                case 'submitted': return 'bg-green-100 text-green-800';
                case 'cancelled':
                case 'deleted': return 'bg-red-100 text-red-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function getFormStatusLabel(status) {
            switch(status) {
                case 'pending': return 'Čekající';
                case 'draft': return 'Rozpracováno';
                case 'processing': return 'Zpracovává se';
                case 'completed': return 'Dokončeno';
                case 'confirmed': return 'Potvrzeno';
                case 'submitted': return 'Odesláno';
                case 'cancelled': return 'Zrušeno';
                case 'deleted': return 'Smazáno';
                default: return status || 'Neznámý';
            }
        }

        function updateFormsPagination(pagination) {
            const container = document.getElementById('forms-pagination');
            
            if (pagination.total_count > 0) {
                container.classList.remove('hidden');
                
                const startRecord = (pagination.current_page - 1) * pagination.per_page + 1;
                const endRecord = Math.min(pagination.current_page * pagination.per_page, pagination.total_count);
                
                document.getElementById('forms-showing-start').textContent = startRecord;
                document.getElementById('forms-showing-end').textContent = endRecord;
                document.getElementById('forms-total').textContent = pagination.total_count;
                document.getElementById('forms-page-info').textContent = `Stránka ${pagination.current_page} z ${pagination.total_pages}`;
                
                const prevBtn = document.getElementById('forms-prev-btn');
                const nextBtn = document.getElementById('forms-next-btn');
                
                prevBtn.disabled = pagination.current_page <= 1;
                nextBtn.disabled = pagination.current_page >= pagination.total_pages;
            } else {
                container.classList.add('hidden');
            }
        }

        function updateFormsStats(forms) {
            const stats = {
                total: forms.length,
                completed: forms.filter(f => ['completed', 'confirmed', 'submitted'].includes(f.status)).length,
                pending: forms.filter(f => ['pending', 'draft'].includes(f.status)).length,
                monthly: forms.filter(f => {
                    if (!f.created_at) return false;
                    const created = new Date(f.created_at);
                    const now = new Date();
                    return created.getMonth() === now.getMonth() && created.getFullYear() === now.getFullYear();
                }).length
            };

            document.getElementById('total-forms').textContent = stats.total;
            document.getElementById('processed-forms').textContent = stats.completed;
            document.getElementById('pending-forms').textContent = stats.pending;
            document.getElementById('monthly-forms').textContent = stats.monthly;
        }

        function changeFormsPage(page) {
            const search = document.getElementById('form-search').value;
            const status = document.getElementById('status-filter').value;
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;
            loadForms(page, search, status, dateFrom, dateTo);
        }

        function searchForms() {
            const search = document.getElementById('form-search').value;
            const status = document.getElementById('status-filter').value;
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;
            loadForms(1, search, status, dateFrom, dateTo);
        }

        function clearFormFilters() {
            document.getElementById('form-search').value = '';
            document.getElementById('status-filter').value = '';
            document.getElementById('date-from').value = '';
            document.getElementById('date-to').value = '';
            loadForms(1);
        }

        function viewFormDetail(formId) {
            window.location.href = `form-detail.php?id=${formId}`;
        }

        function changeFormStatus(formId, currentStatus) {
            document.getElementById('statusFormId').value = formId;
            document.getElementById('newStatus').value = currentStatus;
            document.getElementById('statusNote').value = '';
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function hideStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        async function submitStatusUpdate(event) {
            event.preventDefault();
            log.info('Updating form status...');
            
            const formData = new FormData(event.target);
            const statusData = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('admin-forms-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'change_form_status',
                        form_id: statusData.form_id,
                        new_status: statusData.status,
                        note: statusData.note
                    })
                });

                const data = await response.json();
                log.info('Status update response', data);
                
                if (data.success) {
                    showToast('Status formuláře byl úspěšně změněn', 'success');
                    hideStatusModal();
                    loadForms(currentFormsPage);
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            } catch (error) {
                log.error('Failed to update form status', error);
                showToast('Nepodařilo se změnit status formuláře: ' + error.message, 'error');
            }
        }

        function confirmDeleteForm(formId, formName) {
            showConfirmModal(
                'Smazat formulář',
                `Opravdu chcete smazat formulář od "${formName}"?`,
                () => deleteForm(formId)
            );
        }

        async function deleteForm(formId) {
            log.info('Deleting form', formId);
            
            try {
                const response = await fetch('admin-forms-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'delete_form',
                        form_id: formId
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showToast('Formulář byl úspěšně smazán', 'success');
                    loadForms(currentFormsPage);
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            } catch (error) {
                log.error('Failed to delete form', error);
                showToast('Nepodařilo se smazat formulář: ' + error.message, 'error');
            }
        }

        function showStatsModal() {
            document.getElementById('statsModal').classList.remove('hidden');
            loadDetailedStats();
        }

        function hideStatsModal() {
            document.getElementById('statsModal').classList.add('hidden');
        }

        async function loadDetailedStats() {
            try {
                const response = await fetch('get-admin-stats.php');
                const data = await response.json();
                
                if (data.success) {
                    displayDetailedStats(data.stats);
                } else {
                    throw new Error(data.error || 'Failed to load stats');
                }
            } catch (error) {
                log.error('Failed to load detailed stats', error);
                document.getElementById('statsContent').innerHTML = `
                    <div class="text-center py-8">
                        <p class="text-red-500">Nepodařilo se načíst statistiky</p>
                    </div>
                `;
            }
        }

        function displayDetailedStats(stats) {
            document.getElementById('statsContent').innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-3">Formuláře podle statusu</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span>Čekající:</span>
                                <span class="font-medium">${stats.forms_by_status?.pending || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Zpracovává se:</span>
                                <span class="font-medium">${stats.forms_by_status?.processing || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Dokončeno:</span>
                                <span class="font-medium">${stats.forms_by_status?.completed || 0}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Zrušeno:</span>
                                <span class="font-medium">${stats.forms_by_status?.cancelled || 0}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-3">Průměrná doba zpracování</h4>
                        <div class="text-2xl font-bold text-primary-600">
                            ${stats.avg_processing_time || 'N/A'}
                        </div>
                        <p class="text-sm text-gray-500">hodin</p>
                    </div>
                </div>
                
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-3">Nejčastější společnosti</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                        ${(stats.top_companies || []).map(company => `
                            <div class="flex justify-between bg-white p-2 rounded">
                                <span class="truncate">${company.name}</span>
                                <span class="font-medium">${company.count}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        async function exportForms() {
            log.info('Exporting forms...');
            
            try {
                const search = document.getElementById('form-search').value;
                const status = document.getElementById('status-filter').value;
                const dateFrom = document.getElementById('date-from').value;
                const dateTo = document.getElementById('date-to').value;
                
                // Create CSV content
                const response = await fetch('admin-forms-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'list_forms',
                        page: 1,
                        limit: 1000, // Get all forms for export
                        search: search,
                        status_filter: status,
                        date_from: dateFrom,
                        date_to: dateTo
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    exportToCSV(data.forms);
                    showToast('Export byl úspěšně stažen', 'success');
                } else {
                    throw new Error(data.message || 'Export failed');
                }
            } catch (error) {
                log.error('Failed to export forms', error);
                showToast('Nepodařilo se exportovat formuláře', 'error');
            }
        }

        function exportToCSV(forms) {
            const headers = ['ID', 'Jméno', 'Email', 'Telefon', 'Společnost', 'Status', 'Vytvořeno', 'Aktualizováno'];
            const csvContent = [headers.join(',')];
            
            forms.forEach(form => {
                const row = [
                    form.id || '',
                    `"${(form.user_name || form.contact_person || '').replace(/"/g, '""')}"`,
                    form.user_email || form.email || '',
                    form.phone || '',
                    `"${(form.company_name || '').replace(/"/g, '""')}"`,
                    getFormStatusLabel(form.status),
                    form.created_at || '',
                    form.updated_at || ''
                ];
                csvContent.push(row.join(','));
            });
            
            const csvString = csvContent.join('\n');
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'formulare_' + new Date().toISOString().split('T')[0] + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
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

        // ========== Raynet Sync Functions ==========

        async function syncWithRaynet(formId) {
            log.info('Opening Raynet sync for form:', formId);
            
            const modal = document.getElementById('raynetSyncModal');
            const content = document.getElementById('raynetSyncContent');
            
            modal.classList.remove('hidden');
            
            try {
                // Search for matches in Raynet
                const response = await fetch(`../api/raynet-contact-sync.php?action=search-contact&form_id=${formId}`);
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Nepodařilo se vyhledat kontakt v Raynet');
                }
                
                displayRaynetMatches(formId, result.data);
                
            } catch (error) {
                log.error('Raynet sync search failed', error);
                content.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-red-500 text-5xl mb-4">⚠️</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Chyba při vyhledávání</h3>
                        <p class="text-gray-500">${error.message}</p>
                        <button onclick="hideRaynetSyncModal()" 
                                class="mt-4 px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">
                            Zavřít
                        </button>
                    </div>
                `;
            }
        }

        function displayRaynetMatches(formId, data) {
            const content = document.getElementById('raynetSyncContent');
            const localData = data.local_data;
            const matches = data.raynet_matches || [];
            const companyMatches = data.company_matches || [];
            
            // Check if already synced
            const alreadySynced = localData.already_synced.company_id || localData.already_synced.person_id;
            
            content.innerHTML = `
                <!-- Local Data -->
                <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-md font-semibold text-blue-900 mb-3">📋 Lokální data (Form #${localData.form_id})</h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">Kontaktní osoba:</span>
                            <span class="text-gray-900 ml-2">${localData.contact_person || '-'}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Email:</span>
                            <span class="text-gray-900 ml-2">${localData.email || '-'}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Společnost:</span>
                            <span class="text-gray-900 ml-2">${localData.company_name || '-'}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">IČO:</span>
                            <span class="text-gray-900 ml-2">${localData.ico || '-'}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Telefon:</span>
                            <span class="text-gray-900 ml-2">${localData.phone || '-'}</span>
                        </div>
                        ${alreadySynced ? `
                        <div class="col-span-2 mt-2 p-2 bg-green-100 border border-green-300 rounded">
                            <span class="font-medium text-green-800">✓ Již synchronizováno:</span>
                            <span class="text-green-700 ml-2">
                                Company #${localData.already_synced.company_id || '-'}, 
                                Person #${localData.already_synced.person_id || '-'}
                                (${localData.already_synced.synced_at || '-'})
                            </span>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Raynet Matches -->
                ${matches.length > 0 ? `
                    <div class="mb-6">
                        <h4 class="text-md font-semibold text-gray-900 mb-3">🔍 Nalezené shody v Raynet (${matches.length})</h4>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            ${matches.map((match, index) => `
                                <div class="border border-gray-300 rounded-lg p-4 hover:border-primary-500 transition-colors">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                                    match.match_type === 'email' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                                }">
                                                    ${match.match_type === 'email' ? '📧 Shoda emailem' : '👤 Shoda jménem'}
                                                </span>
                                                <span class="text-sm text-gray-600">
                                                    Score: ${match.match_score}%
                                                </span>
                                            </div>
                                            <h5 class="text-md font-medium text-gray-900">
                                                ${match.person.firstName || ''} ${match.person.lastName || ''}
                                                ${match.person.id ? `<span class="text-xs text-gray-500">(#${match.person.id})</span>` : ''}
                                            </h5>
                                        </div>
                                        <a href="https://app.raynet.cz/electree/?view=DetailView&en=Person&ei=${match.person.id}" 
                                           target="_blank" 
                                           class="text-primary-600 hover:text-primary-800 text-sm">
                                            Otevřít v Raynet →
                                        </a>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-3 text-sm mb-3">
                                        <div>
                                            <span class="font-medium text-gray-600">Email:</span>
                                            <span class="ml-2">${match.person.contactInfo?.email || '-'}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-600">Telefon:</span>
                                            <span class="ml-2">${match.person.contactInfo?.tel1 || '-'}</span>
                                        </div>
                                        ${match.company ? `
                                            <div class="col-span-2">
                                                <span class="font-medium text-gray-600">Společnost:</span>
                                                <span class="ml-2">${match.company.name || '-'}</span>
                                                ${match.company.id ? `
                                                    <a href="https://app.raynet.cz/electree/?view=DetailView&en=Company&ei=${match.company.id}" 
                                                       target="_blank" 
                                                       class="ml-2 text-primary-600 hover:text-primary-800 text-xs">
                                                        (Otevřít →)
                                                    </a>
                                                ` : ''}
                                            </div>
                                        ` : ''}
                                    </div>
                                    
                                    <div class="flex space-x-2 mt-3 pt-3 border-t border-gray-200">
                                        <button onclick="confirmRaynetSync(${formId}, ${match.person.id}, ${match.company?.id || null}, 'link')"
                                                class="flex-1 px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                            Pouze propojit
                                        </button>
                                        <button onclick="confirmRaynetSync(${formId}, ${match.person.id}, ${match.company?.id || null}, 'update')"
                                                class="flex-1 px-3 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                            Propojit a aktualizovat
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : `
                    <div class="mb-6 text-center py-8 bg-gray-50 rounded-lg">
                        <div class="text-gray-400 text-5xl mb-3">🔍</div>
                        <h4 class="text-md font-medium text-gray-900 mb-1">Žádné shody nenalezeny</h4>
                        <p class="text-gray-500 text-sm">V Raynet nebyl nalezen kontakt s odpovídajícím emailem ani jménem.</p>
                    </div>
                `}

                <!-- Company Matches (for context) -->
                ${companyMatches && companyMatches.length > 0 ? `
                    <div class="mb-6">
                        <h4 class="text-md font-semibold text-gray-900 mb-3">🏢 Nalezené společnosti (${companyMatches.length})</h4>
                        <div class="space-y-2">
                            ${companyMatches.map(company => `
                                <div class="flex justify-between items-center p-3 border border-gray-200 rounded">
                                    <div>
                                        <span class="font-medium text-gray-900">${company.name || '-'}</span>
                                        ${company.regNumber ? `<span class="text-sm text-gray-500 ml-2">(IČO: ${company.regNumber})</span>` : ''}
                                    </div>
                                    <a href="https://app.raynet.cz/electree/?view=DetailView&en=Company&ei=${company.id}" 
                                       target="_blank" 
                                       class="text-primary-600 hover:text-primary-800 text-sm">
                                        Otevřít →
                                    </a>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}

                <!-- Create New Option -->
                <div class="border-t pt-4">
                    <button onclick="confirmRaynetSync(${formId}, null, null, 'create')"
                            class="w-full px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium">
                        ➕ Vytvořit nový kontakt v Raynet
                    </button>
                </div>

                <!-- Close Button -->
                <div class="mt-4 text-center">
                    <button onclick="hideRaynetSyncModal()" 
                            class="px-6 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">
                        Zavřít
                    </button>
                </div>
            `;
        }

        async function confirmRaynetSync(formId, personId, companyId, mode) {
            log.info('Confirming Raynet sync', { formId, personId, companyId, mode });
            
            const content = document.getElementById('raynetSyncContent');
            content.innerHTML = `
                <div class="flex flex-col items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mb-4"></div>
                    <p class="text-gray-600">Synchronizuji kontakt...</p>
                </div>
            `;
            
            try {
                const response = await fetch('../api/raynet-contact-sync.php?action=confirm-sync', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        form_id: formId,
                        person_id: personId,
                        company_id: companyId,
                        update_mode: mode
                    })
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Synchronizace selhala');
                }
                
                content.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-green-500 text-6xl mb-4">✓</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Úspěšně synchronizováno</h3>
                        <p class="text-gray-600 mb-4">${result.message || 'Kontakt byl úspěšně synchronizován s Raynet'}</p>
                        <div class="text-sm text-gray-500 mb-4">
                            <div>Company ID: ${result.data.company_id || '-'}</div>
                            <div>Person ID: ${result.data.person_id || '-'}</div>
                            <div>Režim: ${result.data.mode}</div>
                        </div>
                        <button onclick="hideRaynetSyncModal(); loadForms();" 
                                class="px-6 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                            Zavřít
                        </button>
                    </div>
                `;
                
                showToast('Kontakt byl úspěšně synchronizován s Raynet', 'success');
                
            } catch (error) {
                log.error('Raynet sync confirmation failed', error);
                content.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-red-500 text-5xl mb-4">⚠️</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Chyba synchronizace</h3>
                        <p class="text-gray-500 mb-4">${error.message}</p>
                        <button onclick="hideRaynetSyncModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">
                            Zavřít
                        </button>
                    </div>
                `;
                showToast('Synchronizace selhala: ' + error.message, 'error');
            }
        }

        function hideRaynetSyncModal() {
            document.getElementById('raynetSyncModal').classList.add('hidden');
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            log.info('Forms page initializing...');
            loadForms();
            
            // Search on Enter key
            document.getElementById('form-search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchForms();
                }
            });
        });
    </script>

    <!-- Raynet Sync Modal -->
    <div id="raynetSyncModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-6xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Synchronizace kontaktu s Raynet</h3>
                    <button onclick="hideRaynetSyncModal()" class="text-gray-400 hover:text-gray-600">
                        <span class="sr-only">Zavřít</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <div id="raynetSyncContent">
                    <div class="flex justify-center py-12">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
