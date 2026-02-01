<?php
/**
 * Analýza struktury databáze - zjištění existujících tabulek
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();
    
    echo "<h1>📊 Analýza databázové struktury</h1>\n";
    
    // Získání seznamu všech tabulek
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>🗄️ Existující tabulky:</h2>\n<ul>\n";
    foreach ($tables as $table) {
        echo "<li><strong>$table</strong></li>\n";
    }
    echo "</ul>\n";
    
    // Kontrola klíčových tabulek pro čištění
    $requiredTables = ['users', 'forms', 'user_activity', 'user_activity_log'];
    $existingTables = [];
    
    echo "<h2>✅ Kontrola tabulek pro čištění:</h2>\n<ul>\n";
    
    foreach ($requiredTables as $tableName) {
        if (in_array($tableName, $tables)) {
            $existingTables[] = $tableName;
            
            // Spočítáme záznamy v tabulce
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$tableName`");
                $count = $stmt->fetch()['count'];
                echo "<li>✅ <strong>$tableName</strong> - existuje ($count záznamů)</li>\n";
            } catch (Exception $e) {
                echo "<li>⚠️ <strong>$tableName</strong> - existuje, ale nelze spočítat záznamy</li>\n";
            }
        } else {
            echo "<li>❌ <strong>$tableName</strong> - neexistuje</li>\n";
        }
    }
    echo "</ul>\n";
    
    // Detailní analýza tabulky users
    if (in_array('users', $existingTables)) {
        echo "<h2>👥 Analýza tabulky users:</h2>\n";
        
        $stmt = $pdo->query("SELECT email, name, role, is_active, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Email</th><th>Jméno</th><th>Role</th><th>Aktivní</th><th>Vytvořen</th></tr>\n";
        
        foreach ($users as $user) {
            $highlight = ($user['email'] === 'admin@electree.cz') ? ' style="background-color: #90EE90;"' : '';
            echo "<tr$highlight>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . ($user['is_active'] ? 'Ano' : 'Ne') . "</td>";
            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Kontrola admin@electree.cz
        $adminExists = false;
        foreach ($users as $user) {
            if ($user['email'] === 'admin@electree.cz') {
                $adminExists = true;
                break;
            }
        }
        
        if ($adminExists) {
            echo "<p>✅ <strong>Admin uživatel admin@electree.cz již existuje</strong></p>\n";
        } else {
            echo "<p>⚠️ <strong>Admin uživatel admin@electree.cz neexistuje - bude vytvořen</strong></p>\n";
        }
    }
    
    // Detailní analýza tabulky forms
    if (in_array('forms', $existingTables)) {
        echo "<h2>📋 Analýza tabulky forms:</h2>\n";
        
        $stmt = $pdo->query("
            SELECT f.id, f.user_id, f.company_name, f.contact_person, f.email, f.status, f.created_at,
                   u.email as user_email, u.name as user_name
            FROM forms f
            LEFT JOIN users u ON f.user_id = u.id
            ORDER BY f.created_at DESC
        ");
        $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Celkem formulářů:</strong> " . count($forms) . "</p>\n";
        
        if (count($forms) > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
            echo "<tr><th>ID</th><th>Společnost</th><th>Kontakt</th><th>Status</th><th>Uživatel</th><th>Vytvořen</th></tr>\n";
            
            foreach ($forms as $form) {
                $highlight = ($form['user_email'] === 'admin@electree.cz') ? ' style="background-color: #90EE90;"' : '';
                echo "<tr$highlight>";
                echo "<td>" . htmlspecialchars(substr($form['id'], 0, 20)) . "...</td>";
                echo "<td>" . htmlspecialchars($form['company_name'] ?: 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($form['contact_person'] ?: 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($form['status']) . "</td>";
                echo "<td>" . htmlspecialchars($form['user_email'] ?: 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($form['created_at']) . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    }
    
    // Generování aktualizovaného SQL skriptu
    echo "<h2>🔧 Doporučená úprava SQL skriptu:</h2>\n";
    echo "<p>Na základě analýzy databáze by SQL skript měl pracovat s těmito tabulkami:</p>\n";
    echo "<ul>\n";
    foreach ($existingTables as $table) {
        echo "<li><strong>$table</strong></li>\n";
    }
    echo "</ul>\n";
    
} catch (PDOException $e) {
    echo "<h2>❌ Chyba připojení k databázi</h2>\n";
    echo "<p>Chyba: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?>
