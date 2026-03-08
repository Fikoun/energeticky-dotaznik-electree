<?php
/**
 * Factory Reset Script
 * 
 * Smaže všechny formuláře, synchronizace, logy, aktivitu a nahrané soubory.
 * Zachovává: uživatele (users), nastavení (settings, system_settings), role (user_roles).
 *
 * Spuštění: php wipe-run.php
 * S potvrzením bez interakce: php wipe-run.php --force
 */

require_once __DIR__ . '/config/database.php';

// ── Safety: CLI only ──────────────────────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Tento skript lze spustit pouze z příkazové řádky.');
}

// ── Confirmation ──────────────────────────────────────────────────────────────
$force = in_array('--force', $argv ?? [], true);

if (!$force) {
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║              ⚠  FACTORY RESET – WIPE ALL DATA  ⚠           ║\n";
    echo "╠══════════════════════════════════════════════════════════════╣\n";
    echo "║  Tato operace SMAŽE:                                       ║\n";
    echo "║    • Všechny formuláře (forms)                              ║\n";
    echo "║    • Všechny nahrané soubory (uploads)                      ║\n";
    echo "║    • Záznamy o souborech (form_files)                       ║\n";
    echo "║    • Logy (logs)                                            ║\n";
    echo "║    • Aktivitu uživatelů (user_activity, activity_log, …)    ║\n";
    echo "║    • Raynet synchronizaci                                   ║\n";
    echo "║                                                             ║\n";
    echo "║  Zachová:                                                   ║\n";
    echo "║    • Uživatele (users)                                      ║\n";
    echo "║    • Nastavení (settings, system_settings)                  ║\n";
    echo "║    • Role (user_roles)                                      ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Opravdu chcete pokračovat? Napište 'ANO' pro potvrzení: ";
    
    $input = trim(fgets(STDIN));
    if ($input !== 'ANO') {
        echo "Operace zrušena.\n";
        exit(0);
    }
}

// ── Run wipe ──────────────────────────────────────────────────────────────────

echo "\nSpouštím factory reset…\n\n";

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    echo "CHYBA: Nelze se připojit k databázi – " . $e->getMessage() . "\n";
    exit(1);
}

$errors = [];
$success = [];

// ── 1. Clear database tables ─────────────────────────────────────────────────

$tables = [
    'form_files'        => 'Záznamy souborů',
    'forms'             => 'Formuláře',
    'logs'              => 'Logy',
    'user_activity'     => 'Aktivita uživatelů',
    'activity_log'      => 'Log aktivit',
    'user_activity_log' => 'Log uživatelské aktivity',
];

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

foreach ($tables as $table => $label) {
    try {
        // Check if table exists before truncating
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $stmt->execute([$table]);
        
        if ($stmt->fetchColumn() == 0) {
            echo "  ⏭  $label ($table) – tabulka neexistuje, přeskakuji\n";
            continue;
        }

        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $pdo->exec("DELETE FROM `$table`");
        echo "  ✓  $label ($table) – smazáno $count záznamů\n";
        $success[] = $table;
    } catch (Exception $e) {
        echo "  ✗  $label ($table) – CHYBA: " . $e->getMessage() . "\n";
        $errors[] = "$table: " . $e->getMessage();
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// ── 2. Reset Raynet sync columns on users (if they exist) ────────────────────

try {
    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
    $raynetCols = ['last_login', 'total_login_time_minutes', 'login_streak'];
    $resetParts = [];
    foreach ($raynetCols as $col) {
        if (in_array($col, $cols, true)) {
            if ($col === 'last_login') {
                $resetParts[] = "`$col` = NULL";
            } else {
                $resetParts[] = "`$col` = 0";
            }
        }
    }
    if ($resetParts) {
        $pdo->exec("UPDATE `users` SET " . implode(', ', $resetParts));
        echo "  ✓  Uživatelské statistiky (login streak, čas) – resetováno\n";
    }
} catch (Exception $e) {
    echo "  ⏭  Reset uživatelských statistik – přeskočeno: " . $e->getMessage() . "\n";
}

// ── 3. Reset auto-increment counters ─────────────────────────────────────────

foreach ($success as $table) {
    try {
        $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
    } catch (Exception $e) {
        // Not critical – skip silently
    }
}
echo "  ✓  Auto-increment čítače – resetovány\n";

// ── 4. Delete uploaded files ─────────────────────────────────────────────────

$uploadsDir = __DIR__ . '/public/uploads';

if (is_dir($uploadsDir)) {
    $deleted = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            if (@unlink($item->getPathname())) {
                $deleted++;
            }
        }
    }
    echo "  ✓  Nahrané soubory – smazáno $deleted souborů\n";
} else {
    echo "  ⏭  Složka uploads neexistuje, přeskakuji\n";
}

// ── Summary ──────────────────────────────────────────────────────────────────

echo "\n";
if (empty($errors)) {
    echo "══════════════════════════════════════════════════════\n";
    echo "  Factory reset dokončen úspěšně.\n";
    echo "══════════════════════════════════════════════════════\n";
} else {
    echo "══════════════════════════════════════════════════════\n";
    echo "  Factory reset dokončen s " . count($errors) . " chybami:\n";
    foreach ($errors as $err) {
        echo "    – $err\n";
    }
    echo "══════════════════════════════════════════════════════\n";
    exit(1);
}
