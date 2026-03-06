<?php
/**
 * Shared admin navigation bar.
 * Set $activePage before requiring this file.
 * Possible values: 'forms', 'sync', 'settings'
 */
$_navPage = $activePage ?? '';

function _navLinkClass(string $page, string $current): string {
    if ($page === $current) {
        return 'border-primary-500 text-primary-600 border-b-2 py-4 px-1 text-sm font-medium';
    }
    return 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-4 px-1 text-sm font-medium';
}
?>
    <!-- Navigation Header -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="admin-dashboard.php" class="text-xl font-bold text-gray-900 hover:text-primary-600">Admin Panel</a>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="admin-forms.php" class="<?= _navLinkClass('forms', $_navPage) ?>">
                            📝 Formuláře
                        </a>
                        <a href="admin-sync.php" class="<?= _navLinkClass('sync', $_navPage) ?>">
                            ⌘ Synchronizace
                        </a>
                        <a href="admin-settings.php" class="<?= _navLinkClass('settings', $_navPage) ?>">
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
