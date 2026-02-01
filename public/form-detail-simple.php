<?php
// Inline form detail s vlastním připojením - pro testování produkce
session_start();

// Zabránit cache
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Získání ID formuláře z URL
$form_id = $_GET['id'] ?? null;

if (!$form_id) {
    die("<h1>Chyba</h1><p>ID formuláře nebylo poskytnuto</p>");
}

// Databázové připojení - use centralized config
try {
    require_once __DIR__ . '/../config/database.php';
    $conn = getMysqliConnection();
    
    // Načtení dat z databáze
    $stmt = $conn->prepare("SELECT * FROM forms WHERE id = ?");
    $stmt->bind_param("s", $form_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $form_data = $result->fetch_assoc();

    if (!$form_data) {
        die("<h1>Chyba</h1><p>Formulář s ID '" . htmlspecialchars($form_id) . "' nenalezen</p>");
    }

    // Dekódování dat formuláře
    $decoded_data = json_decode($form_data['form_data'], true);
    if (!$decoded_data) {
        die("<h1>Chyba</h1><p>Nelze dekódovat data formuláře</p>");
    }

    $conn->close();
    
} catch (Exception $e) {
    die("<h1>Databázová chyba</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Detail formuláře - Test</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-100 p-8'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow p-6'>
            <h1 class='text-2xl font-bold mb-4'>Detail formuláře</h1>
            <p><strong>ID:</strong> " . htmlspecialchars($form_id) . "</p>
            <p><strong>Status:</strong> " . htmlspecialchars($form_data['status']) . "</p>
            <p><strong>Vytvořeno:</strong> " . htmlspecialchars($form_data['created_at']) . "</p>
            <p><strong>Název společnosti:</strong> " . htmlspecialchars($decoded_data['companyName'] ?? 'Nevyplněno') . "</p>
            <p><strong>Počet dat:</strong> " . count($decoded_data) . "</p>
            
            <h2 class='text-xl font-semibold mt-6 mb-4'>Ukázka dat:</h2>
            <div class='bg-gray-50 p-4 rounded overflow-auto'>
                <pre style='white-space: pre-wrap; font-size: 12px;'>" . 
                htmlspecialchars(print_r(array_slice($decoded_data, 0, 10, true), true)) . 
                "</pre>
            </div>
            
            <div class='mt-6'>
                <a href='admin-forms.php' class='bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600'>
                    ← Zpět na seznam
                </a>
            </div>
        </div>
    </div>
</body>
</html>";
?>
