<?php
/**
 * Centrální databázová konfigurace
 * Používá se ve všech souborech pro konzistentní připojení k databázi
 * Čte konfiguraci z .env souboru
 */

/**
 * Načte .env soubor a nastaví proměnné prostředí
 */
function loadEnv() {
    static $loaded = false;
    
    if ($loaded) {
        return;
    }
    
    // Hledáme .env soubor v různých možných lokacích
    $possiblePaths = [
        __DIR__ . '/../.env',           // Z config/ složky
        __DIR__ . '/../../.env',        // Z includes/ nebo api/ složky  
        dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/.env',
    ];
    
    $envPath = null;
    foreach ($possiblePaths as $path) {
        $realPath = realpath($path);
        if ($realPath && file_exists($realPath)) {
            $envPath = $realPath;
            break;
        }
    }
    
    if (!$envPath) {
        error_log("Warning: .env file not found in expected locations");
        return;
    }
    
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Přeskočit komentáře
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parsovat KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Odstranit uvozovky pokud jsou přítomny
            $value = trim($value, '"\'');
            
            // Nastavit jako proměnnou prostředí
            if (!getenv($key)) {
                putenv("$key=$value");
            }
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
            if (!isset($_SERVER[$key])) {
                $_SERVER[$key] = $value;
            }
        }
    }
    
    $loaded = true;
}

/**
 * Získá hodnotu z prostředí nebo vrátí výchozí hodnotu
 */
function env($key, $default = null) {
    loadEnv();
    
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
    
    return $value;
}

// Funkce pro získání databázového připojení
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    $host = env('DB_HOST');
    $dbname = env('DB_NAME');
    $username = env('DB_USERNAME');
    $password = env('DB_PASSWORD');
    
    if (!$host || !$dbname || !$username) {
        throw new Exception("Chybí databázová konfigurace. Zkontrolujte .env soubor.");
    }
    
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Chyba připojení k databázi: " . $e->getMessage());
    }
}

/**
 * Funkce pro získání mysqli připojení (pro zpětnou kompatibilitu)
 */
function getMysqliConnection() {
    static $conn = null;
    
    if ($conn !== null) {
        return $conn;
    }
    
    $host = env('DB_HOST');
    $dbname = env('DB_NAME');
    $username = env('DB_USERNAME');
    $password = env('DB_PASSWORD');
    
    if (!$host || !$dbname || !$username) {
        throw new Exception("Chybí databázová konfigurace. Zkontrolujte .env soubor.");
    }
    
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception("Chyba připojení k databázi: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Export proměnných pro zpětnou kompatibilitu
return [
    'host' => env('DB_HOST'),
    'dbname' => env('DB_NAME'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'pdo' => function() { return getDbConnection(); }
];
?>
