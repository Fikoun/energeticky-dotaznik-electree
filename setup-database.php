<?php
// Script pro vytvoření databázové struktury a testovacích uživatelů
// Pro ed.electree.cz

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Databáze Setup - ed.electree.cz</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; background: #e8f5e8; padding: 10px; border: 1px solid #4CAF50; margin: 5px 0; }
        .error { color: red; background: #ffe8e8; padding: 10px; border: 1px solid #f44336; margin: 5px 0; }
        .info { color: blue; background: #e8f4fd; padding: 10px; border: 1px solid #2196F3; margin: 5px 0; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ccc; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Databáze Setup Script</h1>
    <p><strong>Server:</strong> ed.electree.cz</p>
    <p><strong>Čas:</strong> <?= date('Y-m-d H:i:s') ?></p>

    <?php
    require_once __DIR__ . '/config/database.php';
    
    try {
        $pdo = getDbConnection();
        echo "<div class='success'>✅ Připojení k databázi úspěšné</div>";
        
        // 1. Zkontrolovat existenci tabulky
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            echo "<div class='info'>📊 Tabulka 'users' neexistuje, vytvářím...</div>";
            
            // Vytvořit tabulku
            $createTableSQL = "
                CREATE TABLE `users` (
                  `id` varchar(50) NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `email` varchar(255) NOT NULL,
                  `password_hash` varchar(255) DEFAULT NULL,
                  `role` enum('admin','partner','obchodnik','user') NOT NULL DEFAULT 'user',
                  `is_active` tinyint(1) NOT NULL DEFAULT 1,
                  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                  `last_login` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `name` (`name`),
                  UNIQUE KEY `email` (`email`),
                  KEY `role` (`role`),
                  KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $pdo->exec($createTableSQL);
            echo "<div class='success'>✅ Tabulka 'users' vytvořena</div>";
        } else {
            echo "<div class='info'>📊 Tabulka 'users' již existuje</div>";
        }
        
        // 2. Vytvořit testovací uživatele
        $testUsers = [
            [
                'id' => 'admin_001',
                'name' => 'admin',
                'email' => 'admin@electree.cz',
                'password' => 'admin123',
                'role' => 'admin'
            ],
            [
                'id' => 'partner_001', 
                'name' => 'partner',
                'email' => 'partner@electree.cz',
                'password' => 'partner123',
                'role' => 'partner'
            ],
            [
                'id' => 'obchodnik_001',
                'name' => 'obchodnik', 
                'email' => 'obchodnik@electree.cz',
                'password' => 'sales123',
                'role' => 'obchodnik'
            ],
            [
                'id' => 'user_001',
                'name' => 'Demo User',
                'email' => 'demo@electree.cz', 
                'password' => 'demo123',
                'role' => 'user'
            ]
        ];
        
        echo "<h2>Vytváření testovacích uživatelů:</h2>";
        
        foreach ($testUsers as $userData) {
            try {
                // Zkontrolovat, zda uživatel již existuje
                $stmt = $pdo->prepare("SELECT id FROM users WHERE name = ? OR email = ?");
                $stmt->execute([$userData['name'], $userData['email']]);
                $existingUser = $stmt->fetch();
                
                if ($existingUser) {
                    echo "<div class='info'>ℹ️ Uživatel '{$userData['name']}' již existuje</div>";
                    
                    // Aktualizovat heslo
                    $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE name = ?");
                    $stmt->execute([$hashedPassword, $userData['name']]);
                    echo "<div class='success'>✅ Heslo pro '{$userData['name']}' aktualizováno</div>";
                    
                } else {
                    // Vytvořit nového uživatele
                    $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (id, name, email, password_hash, role, is_active, created_at) 
                        VALUES (?, ?, ?, ?, ?, 1, NOW())
                    ");
                    $stmt->execute([
                        $userData['id'],
                        $userData['name'],
                        $userData['email'],
                        $hashedPassword,
                        $userData['role']
                    ]);
                    
                    echo "<div class='success'>✅ Uživatel '{$userData['name']}' vytvořen (heslo: {$userData['password']})</div>";
                }
                
            } catch (PDOException $e) {
                echo "<div class='error'>❌ Chyba při vytváření uživatele '{$userData['name']}': " . $e->getMessage() . "</div>";
            }
        }
        
        // 3. Zobrazit všechny uživatele
        echo "<h2>Přehled všech uživatelů:</h2>";
        $stmt = $pdo->query("SELECT id, name, email, role, is_active, created_at, last_login FROM users ORDER BY created_at");
        $users = $stmt->fetchAll();
        
        if (empty($users)) {
            echo "<div class='error'>❌ Žádní uživatelé nenalezeni!</div>";
        } else {
            echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Jméno</th><th>Email</th><th>Role</th><th>Aktivní</th><th>Vytvořen</th><th>Poslední přihlášení</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['name']}</td>";
                echo "<td>{$user['email']}</td>";
                echo "<td>{$user['role']}</td>";
                echo "<td>" . ($user['is_active'] ? 'Ano' : 'Ne') . "</td>";
                echo "<td>{$user['created_at']}</td>";
                echo "<td>{$user['last_login']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // 4. Test přihlášení admin uživatele
        echo "<h2>Test přihlášení admin uživatele:</h2>";
        
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE name = ?");
        $stmt->execute(['admin']);
        $adminUser = $stmt->fetch();
        
        if ($adminUser) {
            $passwordCheck = password_verify('admin123', $adminUser['password_hash']);
            if ($passwordCheck) {
                echo "<div class='success'>✅ Admin uživatel: přihlášení s 'admin' / 'admin123' funguje!</div>";
            } else {
                echo "<div class='error'>❌ Admin uživatel: heslo 'admin123' nefunguje</div>";
            }
        } else {
            echo "<div class='error'>❌ Admin uživatel nenalezen</div>";
        }
        
        echo "<div class='success'><h2>✅ Setup databáze dokončen!</h2></div>";
        echo "<div class='info'>";
        echo "<p><strong>Testovací účty:</strong></p>";
        echo "<ul>";
        echo "<li><strong>admin</strong> / admin123 (Administrator)</li>";
        echo "<li><strong>partner</strong> / partner123 (Partner)</li>";
        echo "<li><strong>obchodnik</strong> / sales123 (Obchodník)</li>";
        echo "<li><strong>Demo User</strong> / demo123 (Běžný uživatel)</li>";
        echo "</ul>";
        echo "<p>Nyní můžete testovat přihlášení na ed.electree.cz</p>";
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Databázová chyba: " . $e->getMessage() . "</div>";
        echo "<div class='error'>Kód chyby: " . $e->getCode() . "</div>";
    }
    ?>

</body>
</html>