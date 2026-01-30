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
        $tracker->logActivity($_SESSION['user_id'], 'page_view', 'Zobrazení admin dashboard');
    } catch (Exception $e) {
        error_log("Activity logging error: " . $e->getMessage());
    }
}

// Zabránit output buffering chybám
ob_start();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a href="admin-dashboard.php" class="border-primary-500 text-primary-600 border-b-2 py-4 px-1 text-sm font-medium">
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
            <div class="mb-8">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Dashboard
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Přehled klíčových metrik a statistik
                </p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-primary-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm">👥</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Celkem uživatelů</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="total-users">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm">📝</span>
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
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm">✅</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Dokončené formuláře</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="completed-forms">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm">📊</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Úspěšnost</dt>
                                    <dd class="text-lg font-medium text-gray-900" id="success-rate">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Users Chart -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Vývoj uživatelů</h3>
                    <canvas id="usersChart" width="400" height="200"></canvas>
                </div>

                <!-- Forms Chart -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Formuláře podle stavu</h3>
                    <canvas id="formsChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="mt-8">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Poslední aktivita</h3>
                    </div>
                    <div class="p-6">
                        <div id="recent-activity" class="space-y-3">
                            <div class="animate-pulse">
                                <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="admin-activity.php" class="text-primary-600 hover:text-primary-500 text-sm font-medium">
                                Zobrazit všechnu aktivitu →
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Console logging utility
        const log = {
            info: (msg, data = null) => {
                console.log(`[Dashboard] ${msg}`, data);
            },
            error: (msg, error = null) => {
                console.error(`[Dashboard] ${msg}`, error);
            },
            warn: (msg, data = null) => {
                console.warn(`[Dashboard] ${msg}`, data);
            }
        };

        // Load dashboard data
        async function loadDashboardData() {
            log.info('Loading dashboard data...');
            
            try {
                const response = await fetch('get-admin-stats.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'quick_stats' })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                log.info('Dashboard data loaded', data);

                if (data.success) {
                    updateDashboardStats(data.stats);
                    updateCharts(data.stats);
                    loadRecentActivity();
                } else {
                    throw new Error(data.error || 'Unknown error');
                }
            } catch (error) {
                log.error('Failed to load dashboard data', error);
                showErrorMessage('Nepodařilo se načíst data dashboardu');
            }
        }

        function updateDashboardStats(overview) {
            document.getElementById('total-users').textContent = overview.total_users || '0';
            document.getElementById('total-forms').textContent = overview.total_forms || '0';
            document.getElementById('completed-forms').textContent = overview.completed_forms || '0';
            document.getElementById('success-rate').textContent = (overview.success_rate || '0') + '%';
        }

        function updateCharts(overview) {
            // Users Chart
            const usersCtx = document.getElementById('usersChart').getContext('2d');
            new Chart(usersCtx, {
                type: 'line',
                data: {
                    labels: overview.users_trend?.labels || [],
                    datasets: [{
                        label: 'Uživatelé',
                        data: overview.users_trend?.data || [],
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Forms Chart - pie chart pro zobrazení statusů
            const formsCtx = document.getElementById('formsChart').getContext('2d');
            new Chart(formsCtx, {
                type: 'pie',
                data: {
                    labels: overview.forms_trend?.labels || [],
                    datasets: [{
                        label: 'Formuláře podle stavu',
                        data: overview.forms_trend?.data || [],
                        backgroundColor: [
                            'rgba(239, 68, 68, 0.8)',   // Červená pro Rozpracováno
                            'rgba(245, 158, 11, 0.8)',  // Oranžová pro Odesláno
                            'rgba(34, 197, 94, 0.8)'    // Zelená pro Potvrzeno
                        ],
                        borderColor: [
                            'rgb(239, 68, 68)',
                            'rgb(245, 158, 11)', 
                            'rgb(34, 197, 94)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }

        async function loadRecentActivity() {
            try {
                const response = await fetch('get-admin-stats.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'activity_log',
                        page: 1,
                        per_page: 5
                    })
                });

                const data = await response.json();
                
                if (data.success && data.activities) {
                    const container = document.getElementById('recent-activity');
                    container.innerHTML = data.activities.map(activity => `
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-2 h-2 bg-primary-500 rounded-full"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 truncate">
                                    ${activity.activity_description}
                                </p>
                                <p class="text-xs text-gray-500">
                                    ${activity.user_name || 'Systém'} • ${new Date(activity.created_at).toLocaleString('cs-CZ')}
                                </p>
                            </div>
                        </div>
                    `).join('');
                } else {
                    document.getElementById('recent-activity').innerHTML = `
                        <p class="text-gray-500 text-sm">Žádné aktivity k zobrazení</p>
                    `;
                }
            } catch (error) {
                log.error('Failed to load recent activity', error);
            }
        }

        function showErrorMessage(message) {
            // Create toast notification instead of alert
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-red-500 text-white p-4 rounded-lg shadow-lg z-50';
            toast.innerHTML = `
                <div class="flex items-center">
                    <span class="mr-2">❌</span>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">✕</button>
                </div>
            `;
            document.body.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            log.info('Dashboard initializing...');
            loadDashboardData();
        });
    </script>
</body>
</html>
