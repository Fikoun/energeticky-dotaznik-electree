<?php
session_set_cookie_params(["path" => "/", "httponly" => true, "samesite" => "Lax"]);
session_start();

// Any authenticated user can access this page
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit();
}

// Log page view activity
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/UserActivityTracker.php';
        $tracker = new UserActivityTracker();
        $tracker->logActivity($_SESSION['user_id'], 'page_view', 'Zobrazení nastavení Raynet API');
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
    <title>Raynet API nastavení</title>
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
    <?php $activePage = 'raynet'; require __DIR__ . '/admin-nav.php'; ?>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Page Header -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Raynet CRM – API přihlašovací údaje
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Nastavte si vlastní API klíč pro synchronizaci dat do Raynet CRM. Bez platného API klíče nelze synchronizovat formuláře.
                </p>
            </div>

            <!-- Status Card -->
            <div id="statusCard" class="mb-6 bg-white shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div id="statusIcon" class="flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center bg-gray-100">
                        <span class="text-xl">⏳</span>
                    </div>
                    <div class="ml-4">
                        <h3 id="statusTitle" class="text-lg font-medium text-gray-900">Načítání...</h3>
                        <p id="statusMessage" class="text-sm text-gray-500">Kontroluji stav přihlašovacích údajů</p>
                    </div>
                </div>
            </div>

            <!-- Credentials Form -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-5 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Přihlašovací údaje</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Zadejte své Raynet API údaje. Při uložení bude připojení automaticky otestováno.
                    </p>
                </div>

                <form id="credentialsForm" class="p-6 space-y-5">
                    <div>
                        <label for="raynet_username" class="block text-sm font-medium text-gray-700 mb-1">
                            Uživatelské jméno (e-mail)
                        </label>
                        <input type="email" id="raynet_username" name="username" required
                               placeholder="vas-email@firma.cz"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <p class="mt-1 text-xs text-gray-400">E-mail, kterým se přihlašujete do Raynet CRM</p>
                    </div>

                    <div>
                        <label for="raynet_api_key" class="block text-sm font-medium text-gray-700 mb-1">
                            API klíč
                        </label>
                        <div class="relative">
                            <input type="password" id="raynet_api_key" name="api_key" required
                                   placeholder="crm-xxxxxxxxxxxxxxxxxxxxxxxxx"
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <button type="button" onclick="toggleApiKeyVisibility()" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <span id="toggleIcon">👁</span>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Najdete v Raynet: Nastavení → API</p>
                    </div>

                    <div>
                        <label for="raynet_instance_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Název instance
                        </label>
                        <input type="text" id="raynet_instance_name" name="instance_name" required
                               placeholder="nazev-firmy"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <p class="mt-1 text-xs text-gray-400">Název vaší Raynet instance (z URL: nazev-firmy.raynet.cz)</p>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                        <div class="flex space-x-3">
                            <button type="button" onclick="testConnection()"
                                    id="testBtn"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                🔌 Otestovat připojení
                            </button>
                            <button type="button" onclick="clearCredentials()"
                                    id="clearBtn"
                                    class="inline-flex items-center px-4 py-2 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 hidden">
                                🗑️ Odebrat údaje
                            </button>
                        </div>
                        <button type="submit"
                                id="saveBtn"
                                class="inline-flex items-center px-6 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                            💾 Uložit a ověřit
                        </button>
                    </div>
                </form>
            </div>

            <!-- Help Section -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-5">
                <h4 class="text-sm font-medium text-blue-800 mb-2">📘 Kde najdu API klíč?</h4>
                <ol class="text-sm text-blue-700 space-y-1 list-decimal list-inside">
                    <li>Přihlaste se do svého Raynet účtu</li>
                    <li>Klikněte na svůj profil (vpravo nahoře) → <strong>Nastavení</strong></li>
                    <li>V sekci <strong>API</strong> najdete svůj API klíč</li>
                    <li>Název instance je část URL před <code>.raynet.cz</code></li>
                </ol>
            </div>

        </div>
    </div>

    <script>
        let csrfToken = null;

        async function getCSRFToken() {
            try {
                const response = await fetch('user-raynet-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_csrf_token' })
                });
                const data = await response.json();
                if (data.success) {
                    csrfToken = data.csrf_token;
                }
            } catch (e) {
                console.error('Failed to get CSRF token', e);
            }
        }

        async function apiCall(requestData) {
            if (csrfToken) {
                requestData.csrf_token = csrfToken;
            }
            const response = await fetch('user-raynet-api.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken || ''
                },
                body: JSON.stringify(requestData)
            });
            return await response.json();
        }

        function updateStatus(configured, message) {
            const icon = document.getElementById('statusIcon');
            const title = document.getElementById('statusTitle');
            const msg = document.getElementById('statusMessage');
            const clearBtn = document.getElementById('clearBtn');

            if (configured) {
                icon.className = 'flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center bg-green-100';
                icon.innerHTML = '<span class="text-xl">✅</span>';
                title.textContent = 'API klíč je nastaven';
                msg.textContent = message || 'Raynet synchronizace je aktivní';
                clearBtn.classList.remove('hidden');
            } else {
                icon.className = 'flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center bg-yellow-100';
                icon.innerHTML = '<span class="text-xl">⚠️</span>';
                title.textContent = 'API klíč není nastaven';
                msg.textContent = message || 'Bez API klíče nelze synchronizovat formuláře do Raynet';
                clearBtn.classList.add('hidden');
            }
        }

        async function loadStatus() {
            try {
                const data = await apiCall({ action: 'get_status' });
                if (data.success) {
                    updateStatus(data.configured);
                    if (data.configured && data.credentials) {
                        document.getElementById('raynet_username').value = data.credentials.username || '';
                        document.getElementById('raynet_instance_name').value = data.credentials.instance_name || '';
                        if (data.credentials.api_key_set) {
                            document.getElementById('raynet_api_key').placeholder = '••••••••••••••• (uloženo)';
                        }
                    }
                }
            } catch (e) {
                console.error('Failed to load status', e);
                updateStatus(false, 'Nepodařilo se načíst stav');
            }
        }

        function getFormValues() {
            return {
                username: document.getElementById('raynet_username').value.trim(),
                api_key: document.getElementById('raynet_api_key').value.trim(),
                instance_name: document.getElementById('raynet_instance_name').value.trim()
            };
        }

        async function testConnection() {
            const vals = getFormValues();
            if (!vals.username || !vals.api_key || !vals.instance_name) {
                showToast('Vyplňte všechna pole', 'error');
                return;
            }

            const btn = document.getElementById('testBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Testuji...';

            try {
                const result = await apiCall({ action: 'test_credentials', ...vals });
                if (result.success) {
                    showToast('Připojení k Raynet funguje!', 'success');
                } else {
                    showToast(result.error || 'Test selhal', 'error');
                }
            } catch (e) {
                showToast('Chyba při testování', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = '🔌 Otestovat připojení';
            }
        }

        async function clearCredentials() {
            if (!confirm('Opravdu chcete odebrat Raynet přihlašovací údaje? Synchronizace formulářů přestane fungovat.')) {
                return;
            }

            try {
                const result = await apiCall({ action: 'clear_credentials' });
                if (result.success) {
                    showToast('Přihlašovací údaje odstraněny', 'success');
                    document.getElementById('credentialsForm').reset();
                    document.getElementById('raynet_api_key').placeholder = 'crm-xxxxxxxxxxxxxxxxxxxxxxxxx';
                    updateStatus(false);
                } else {
                    showToast(result.error || 'Chyba', 'error');
                }
            } catch (e) {
                showToast('Chyba při odstraňování', 'error');
            }
        }

        document.getElementById('credentialsForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const vals = getFormValues();
            if (!vals.username || !vals.api_key || !vals.instance_name) {
                showToast('Vyplňte všechna pole', 'error');
                return;
            }

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Ověřuji a ukládám...';

            try {
                const result = await apiCall({ action: 'save_credentials', ...vals });
                if (result.success) {
                    showToast(result.message || 'Uloženo a ověřeno', 'success');
                    updateStatus(true);
                    document.getElementById('raynet_api_key').value = '';
                    document.getElementById('raynet_api_key').placeholder = '••••••••••••••• (uloženo)';
                } else {
                    showToast(result.error || 'Uložení selhalo', 'error');
                }
            } catch (e) {
                showToast('Chyba při ukládání', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = '💾 Uložit a ověřit';
            }
        });

        function toggleApiKeyVisibility() {
            const input = document.getElementById('raynet_api_key');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '🙈';
            } else {
                input.type = 'password';
                icon.textContent = '👁';
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
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 5000);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', async function() {
            await getCSRFToken();
            await loadStatus();
        });
    </script>
</body>
</html>
