<?php
/**
 * Script pro spuštění databázové migrace - přidání MERK API sloupců
 */

echo "🔧 Spouštím databázovou migraci pro MERK API sloupce...\n\n";

// Database configuration - use centralized config
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();
    
    echo "✅ Připojení k databázi úspěšné\n\n";
    
    // Kontrola současné struktury tabulky
    echo "📋 Kontrola současné struktury tabulky 'forms'...\n";
    $stmt = $pdo->query("DESCRIBE forms");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existingColumns = array_column($columns, 'Field');
    echo "Současné sloupce: " . implode(', ', $existingColumns) . "\n\n";
    
    // Sloupce které chceme přidat
    $newColumns = [
        'ico' => "VARCHAR(20) COMMENT 'IČO společnosti'",
        'dic' => "VARCHAR(30) COMMENT 'DIČ společnosti'",
        'company_address' => "TEXT COMMENT 'Adresa společnosti'",
        'merk_api_data' => "LONGTEXT COMMENT 'JSON data z MERK API obsahující všechny dostupné údaje'",
        'merk_api_fetched_at' => "TIMESTAMP NULL COMMENT 'Časové razítko posledního načtení z MERK API'",
        'merk_api_source' => "VARCHAR(20) DEFAULT NULL COMMENT 'Zdroj dat: MERK, ARES, nebo MANUAL'"
    ];
    
    echo "🔄 Přidávám nové sloupce...\n";
    
    foreach ($newColumns as $columnName => $definition) {
        if (!in_array($columnName, $existingColumns)) {
            $sql = "ALTER TABLE forms ADD COLUMN $columnName $definition";
            
            // Určíme pozici sloupce
            if ($columnName === 'ico') {
                $sql .= " AFTER phone";
            } elseif ($columnName === 'dic') {
                $sql .= " AFTER ico";
            } elseif ($columnName === 'company_address') {
                $sql .= " AFTER dic";
            } elseif ($columnName === 'merk_api_data') {
                $sql .= " AFTER company_address";
            } elseif ($columnName === 'merk_api_fetched_at') {
                $sql .= " AFTER merk_api_data";
            } elseif ($columnName === 'merk_api_source') {
                $sql .= " AFTER merk_api_fetched_at";
            }
            
            $pdo->exec($sql);
            echo "  ✅ Přidán sloupec: $columnName\n";
        } else {
            echo "  ⚠️  Sloupec '$columnName' již existuje\n";
        }
    }
    
    echo "\n🔧 Vytvářím indexy...\n";
    
    // Indexy pro lepší výkon
    $indexes = [
        'idx_forms_ico' => 'ico',
        'idx_forms_dic' => 'dic',
        'idx_merk_api_fetched' => 'merk_api_fetched_at'
    ];
    
    foreach ($indexes as $indexName => $column) {
        try {
            $pdo->exec("CREATE INDEX $indexName ON forms($column)");
            echo "  ✅ Vytvořen index: $indexName\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "  ⚠️  Index '$indexName' již existuje\n";
            } else {
                echo "  ❌ Chyba při vytváření indexu '$indexName': " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n📊 Kontrola finální struktury tabulky...\n";
    $stmt = $pdo->query("DESCRIBE forms");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Finální sloupce:\n";
    foreach ($finalColumns as $column) {
        $null = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $column['Default'] ? "DEFAULT '{$column['Default']}'" : '';
        echo "  - {$column['Field']}: {$column['Type']} $null $default\n";
    }
    
    echo "\n🎉 Migrace dokončena úspěšně!\n";
    echo "\n📝 Výsledek:\n";
    echo "- Přidány sloupce pro IČO, DIČ, adresu společnosti\n";
    echo "- Přidán sloupec pro ukládání kompletních MERK API dat\n";
    echo "- Přidány metadata sloupce pro sledování zdroje a času načtení\n";
    echo "- Vytvořeny indexy pro lepší výkon\n";
    echo "\n✨ Formuláře nyní budou ukládat MERK API data do databáze při submitu!\n";
    
} catch (PDOException $e) {
    echo "❌ Chyba databáze: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Obecná chyba: " . $e->getMessage() . "\n";
    exit(1);
}
?>
