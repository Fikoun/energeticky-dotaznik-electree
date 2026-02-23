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
        $tracker->logActivity($_SESSION['user_id'], 'page_view', 'Zobrazení nastavení systému');
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
    <title>Nastavení - Admin Panel</title>
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
                        <a href="admin-activity.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-4 px-1 text-sm font-medium">
                            📋 Aktivita
                        </a>
                        <a href="admin-settings.php" class="border-primary-500 text-primary-600 border-b-2 py-4 px-1 text-sm font-medium">
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
            <div class="mb-8">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Nastavení systému
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Správa rolí, systémových nastavení a konfigurace
                </p>
            </div>

            <!-- Settings Tabs -->
            <div class="bg-white shadow rounded-lg">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex" aria-label="Tabs">
                        <button onclick="showTab('roles')" 
                                class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm"
                                data-tab="roles">
                            👤 Role uživatelů
                        </button>
                        <button onclick="showTab('system')" 
                                class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm"
                                data-tab="system">
                            ⚙️ Systémové nastavení
                        </button>
                        <button onclick="showTab('email')" 
                                class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm"
                                data-tab="email">
                            📧 Email nastavení
                        </button>
                        <button onclick="showTab('security')" 
                                class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm"
                                data-tab="security">
                            🔒 Bezpečnost
                        </button>
                    </nav>
                </div>

                <!-- Roles Tab -->
                <div id="roles-tab" class="tab-content p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Správa rolí uživatelů</h3>
                            <p class="mt-1 text-sm text-gray-500">Definujte role a jejich oprávnění v systému</p>
                        </div>
                        <button onclick="showCreateRoleModal()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            + Nová role
                        </button>
                    </div>

                    <!-- Roles Table -->
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                        <div id="roles-table">
                            <div class="animate-pulse p-6">
                                <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                                <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Settings Tab -->
                <div id="system-tab" class="tab-content p-6 hidden">
                    <h3 class="text-lg font-medium text-gray-900 mb-6">Systémové nastavení</h3>
                    
                    <form id="systemSettingsForm" onsubmit="saveSystemSettings(event)">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Application Settings -->
                            <div class="space-y-4">
                                <h4 class="text-md font-medium text-gray-800 border-b pb-2">Aplikace</h4>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Název aplikace</label>
                                    <input type="text" id="app_name" name="app_name" value="Bateree Formulář"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Popis aplikace</label>
                                    <textarea id="app_description" name="app_description" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">Systém pro správu formulářů a uživatelů</textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                                    <select id="timezone" name="timezone" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                        <option value="Europe/Prague">Europe/Prague</option>
                                        <option value="UTC">UTC</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Performance Settings -->
                            <div class="space-y-4">
                                <h4 class="text-md font-medium text-gray-800 border-b pb-2">Výkon</h4>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Cache timeout (sekundy)</label>
                                    <input type="number" id="cache_timeout" name="cache_timeout" value="300" min="60" max="3600"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Session timeout (minuty)</label>
                                    <input type="number" id="session_timeout" name="session_timeout" value="60" min="15" max="480"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Max file upload size (MB)</label>
                                    <input type="number" id="max_file_size" name="max_file_size" value="10" min="1" max="100"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" 
                                    class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                Uložit nastavení
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Email Settings Tab -->
                <div id="email-tab" class="tab-content p-6 hidden">
                    <h3 class="text-lg font-medium text-gray-900 mb-6">Email nastavení</h3>
                    
                    <form id="emailSettingsForm" onsubmit="saveEmailSettings(event)">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- SMTP Settings -->
                            <div class="space-y-4">
                                <h4 class="text-md font-medium text-gray-800 border-b pb-2">SMTP Server</h4>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SMTP Host</label>
                                    <input type="text" id="smtp_host" name="smtp_host" placeholder="smtp.gmail.com"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SMTP Port</label>
                                    <input type="number" id="smtp_port" name="smtp_port" value="587" min="25" max="995"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Encryption</label>
                                    <select id="smtp_encryption" name="smtp_encryption" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Email Authentication -->
                            <div class="space-y-4">
                                <h4 class="text-md font-medium text-gray-800 border-b pb-2">Autentifikace</h4>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Username/Email</label>
                                    <input type="email" id="smtp_username" name="smtp_username"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                                    <input type="password" id="smtp_password" name="smtp_password"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">From Email</label>
                                    <input type="email" id="from_email" name="from_email" placeholder="noreply@example.com"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">From Name</label>
                                    <input type="text" id="from_name" name="from_name" placeholder="Bateree Support"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-between">
                            <button type="button" onclick="testEmailConnection()" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                Test připojení
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                Uložit email nastavení
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Settings Tab -->
                <div id="security-tab" class="tab-content p-6 hidden">
                    <h3 class="text-lg font-medium text-gray-900 mb-6">Bezpečnostní nastavení</h3>
                    
                    <form id="securitySettingsForm" onsubmit="saveSecuritySettings(event)">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Password Policy -->
                            <div class="space-y-4">
                                <h4 class="text-md font-medium text-gray-800 border-b pb-2">Zásady hesel</h4>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Minimální délka hesla</label>
                                    <input type="number" id="min_password_length" name="min_password_length" value="8" min="4" max="20"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <input type="checkbox" id="require_uppercase" name="require_uppercase" checked
                                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                        <label for="require_uppercase" class="ml-2 block text-sm text-gray-900">
                                            Vyžadovat velká písmena
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input type="checkbox" id="require_numbers" name="require_numbers" checked
                                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                        <label for="require_numbers" class="ml-2 block text-sm text-gray-900">
                                            Vyžadovat číslice
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input type="checkbox" id="require_special_chars" name="require_special_chars"
                                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                        <label for="require_special_chars" class="ml-2 block text-sm text-gray-900">
                                            Vyžadovat speciální znaky
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Features -->
                            <div class="space-y-4">
                                <h4 class="text-md font-medium text-gray-800 border-b pb-2">Bezpečnostní funkce</h4>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Max pokusů o přihlášení</label>
                                    <input type="number" id="max_login_attempts" name="max_login_attempts" value="5" min="3" max="10"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Lockout doba (minuty)</label>
                                    <input type="number" id="lockout_duration" name="lockout_duration" value="15" min="5" max="60"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>
                                
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <input type="checkbox" id="enable_two_factor" name="enable_two_factor"
                                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                        <label for="enable_two_factor" class="ml-2 block text-sm text-gray-900">
                                            Povolit 2FA (budoucí)
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input type="checkbox" id="log_security_events" name="log_security_events" checked
                                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                        <label for="log_security_events" class="ml-2 block text-sm text-gray-900">
                                            Logovat bezpečnostní události
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" 
                                    class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                Uložit bezpečnostní nastavení
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <!-- Role Create/Edit Modal -->
    <div id="roleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="roleModalTitle">Nová role</h3>
                    <button onclick="hideRoleModal()" class="text-gray-400 hover:text-gray-600">
                        <span class="sr-only">Zavřít</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <form id="roleForm" onsubmit="submitRoleForm(event)">
                    <input type="hidden" id="roleId" name="role_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Klíč role *</label>
                            <input type="text" id="roleKey" name="role_key" required pattern="^[a-z_]+$"
                                   placeholder="např. sales_manager"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <p class="text-xs text-gray-500 mt-1">Pouze malá písmena a podtržítka</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Název role *</label>
                            <input type="text" id="roleName" name="role_name" required
                                   placeholder="např. Obchodní manažer"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Popis role</label>
                        <textarea id="roleDescription" name="role_description" rows="3"
                                  placeholder="Popis odpovědností a oprávnění této role"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Oprávnění</label>
                        <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto border border-gray-200 rounded p-3">
                            <div class="flex items-center">
                                <input type="checkbox" id="perm_users_view" name="permissions[]" value="users_view"
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                <label for="perm_users_view" class="ml-2 text-sm text-gray-700">Zobrazit uživatele</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm_users_edit" name="permissions[]" value="users_edit"
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                <label for="perm_users_edit" class="ml-2 text-sm text-gray-700">Upravovat uživatele</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm_users_delete" name="permissions[]" value="users_delete"
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                <label for="perm_users_delete" class="ml-2 text-sm text-gray-700">Mazat uživatele</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm_forms_view" name="permissions[]" value="forms_view"
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                <label for="perm_forms_view" class="ml-2 text-sm text-gray-700">Zobrazit formuláře</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm_forms_edit" name="permissions[]" value="forms_edit"
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                <label for="perm_forms_edit" class="ml-2 text-sm text-gray-700">Upravovat formuláře</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm_reports_view" name="permissions[]" value="reports_view"
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                <label for="perm_reports_view" class="ml-2 text-sm text-gray-700">Zobrazit reporty</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm_settings_view" name="permissions[]" value="settings_view"
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                <label for="perm_settings_view" class="ml-2 text-sm text-gray-700">Zobrazit nastavení</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm_settings_edit" name="permissions[]" value="settings_edit"
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                <label for="perm_settings_edit" class="ml-2 text-sm text-gray-700">Upravovat nastavení</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="roleActive" name="is_active" checked
                                   class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                            <label for="roleActive" class="ml-2 block text-sm text-gray-900">
                                Aktivní role
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideRoleModal()" 
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

    <script>
        // Console logging utility
        const log = {
            info: (msg, data = null) => {
                console.log(`[Settings] ${msg}`, data);
            },
            error: (msg, error = null) => {
                console.error(`[Settings] ${msg}`, error);
            },
            warn: (msg, data = null) => {
                console.warn(`[Settings] ${msg}`, data);
            }
        };

        let csrfToken = null;
        let currentTab = 'roles';

        // Get CSRF token
        async function getCSRFToken() {
            try {
                const response = await fetch('admin-settings-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_csrf_token' })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                if (data.success && data.csrf_token) {
                    csrfToken = data.csrf_token;
                    log.info('CSRF token získán');
                } else {
                    throw new Error(data.message || 'Nepodařilo se získat CSRF token');
                }
            } catch (error) {
                log.error('Nepodařilo se získat CSRF token', error);
            }
        }

        // Secure API call with CSRF protection
        async function secureApiCall(requestData) {
            const modifyingActions = ['create_role', 'update_role', 'delete_role', 'save_settings'];
            
            if (modifyingActions.includes(requestData.action)) {
                if (!csrfToken) {
                    await getCSRFToken();
                }
                if (csrfToken) {
                    requestData.csrf_token = csrfToken;
                }
            }
            
            try {
                const response = await fetch('admin-settings-api.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken || ''
                    },
                    body: JSON.stringify(requestData)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return await response.json();
            } catch (error) {
                log.error('API call failed:', error);
                throw error;
            }
        }

        // Tab Management
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Remove active state from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('border-primary-500', 'text-primary-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            // Activate selected button
            const activeButton = document.querySelector(`[data-tab="${tabName}"]`);
            activeButton.classList.remove('border-transparent', 'text-gray-500');
            activeButton.classList.add('border-primary-500', 'text-primary-600');
            
            currentTab = tabName;
            
            // Load data for the tab
            if (tabName === 'roles') {
                loadRoles();
            } else if (tabName === 'system') {
                loadSystemSettings();
            } else if (tabName === 'email') {
                loadEmailSettings();
            } else if (tabName === 'security') {
                loadSecuritySettings();
            }
        }

        // Load roles
        async function loadRoles() {
            try {
                const data = await secureApiCall({ action: 'list_roles' });
                
                if (data.success) {
                    displayRoles(data.data || []);
                } else {
                    throw new Error(data.message || 'Failed to load roles');
                }
            } catch (error) {
                log.error('Failed to load roles', error);
                showToast('Nepodařilo se načíst role', 'error');
            }
        }

        function displayRoles(roles) {
            const container = document.getElementById('roles-table');
            
            if (!roles || roles.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-gray-400 text-lg mb-2">👤</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Žádné role</h3>
                        <p class="text-gray-500">Ještě nebyly definovány žádné role.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Popis</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Oprávnění</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Akce</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${roles.map(role => `
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">${role.role_name}</div>
                                        <div class="text-sm text-gray-500">${role.role_key}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs truncate">${role.role_description || '-'}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-500">
                                        ${role.permissions ? role.permissions.split(',').length + ' oprávnění' : '0 oprávnění'}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${role.is_active == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                        ${role.is_active == 1 ? 'Aktivní' : 'Neaktivní'}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="editRole('${role.id}')" class="text-primary-600 hover:text-primary-900 mr-3">
                                        Upravit
                                    </button>
                                    <button onclick="deleteRole('${role.id}', '${role.role_name}')" class="text-red-600 hover:text-red-900">
                                        Smazat
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        // Modal functions
        function showCreateRoleModal() {
            document.getElementById('roleModalTitle').textContent = 'Nová role';
            document.getElementById('roleForm').reset();
            document.getElementById('roleId').value = '';
            document.getElementById('roleActive').checked = true;
            showRoleModal();
        }

        function showRoleModal() {
            document.getElementById('roleModal').classList.remove('hidden');
        }

        function hideRoleModal() {
            document.getElementById('roleModal').classList.add('hidden');
        }

        // Settings functions
        async function loadSystemSettings() {
            // Load from API or set defaults
            log.info('Loading system settings...');
        }

        async function loadEmailSettings() {
            // Load from API or set defaults
            log.info('Loading email settings...');
        }

        async function loadSecuritySettings() {
            // Load from API or set defaults
            log.info('Loading security settings...');
        }

        async function saveSystemSettings(event) {
            event.preventDefault();
            // Implementation will be in API
            showToast('Systémové nastavení uloženo', 'success');
        }

        async function saveEmailSettings(event) {
            event.preventDefault();
            // Implementation will be in API
            showToast('Email nastavení uloženo', 'success');
        }

        async function saveSecuritySettings(event) {
            event.preventDefault();
            // Implementation will be in API
            showToast('Bezpečnostní nastavení uloženo', 'success');
        }

        async function testEmailConnection() {
            showToast('Testování email připojení...', 'info');
            // Implementation will be in API
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

        // Initialize page
        document.addEventListener('DOMContentLoaded', async function() {
            log.info('Settings page initializing...');
            
            try {
                // Initialize security
                await getCSRFToken();
                
                // Show default tab
                showTab('roles');
                
                log.info('Settings page initialized successfully');
            } catch (error) {
                log.error('Failed to initialize settings page', error);
                showToast('Chyba při inicializaci stránky', 'error');
            }
        });
    </script>
</body>
</html>
