<?php
session_start();

// Kontrola oprávnění
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /');
    exit();
}

$form_id = $_GET['id'] ?? '';

if (empty($form_id)) {
    echo "Neplatné ID formuláře";
    exit();
}

// Načtení dat formuláře
$form_data = null;

try {
    // Database configuration - use centralized config
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as user_name, u.email as user_email 
        FROM forms f 
        LEFT JOIN users u ON f.user_id = u.id 
        WHERE f.id = ?
    ");
    $stmt->execute([$form_id]);
    $form_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form_data) {
        echo "Formulář nenalezen";
        exit();
    }
    
} catch (PDOException $e) {
    echo "Chyba při načítání formuláře: " . $e->getMessage();
    exit();
}

// Zpracování úprav
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $update_fields = [];
        $update_values = [];
        
        // Aktualizace základních údajů
        if (isset($_POST['company_name'])) {
            $update_fields[] = "company_name = ?";
            $update_values[] = $_POST['company_name'];
        }
        
        if (isset($_POST['contact_person'])) {
            $update_fields[] = "contact_person = ?";
            $update_values[] = $_POST['contact_person'];
        }
        
        if (isset($_POST['phone'])) {
            $update_fields[] = "phone = ?";
            $update_values[] = $_POST['phone'];
        }
        
        if (isset($_POST['email'])) {
            $update_fields[] = "email = ?";
            $update_values[] = $_POST['email'];
        }
        
        // Aktualizace form_data JSON
        if (isset($_POST['form_data_json'])) {
            $form_data_json = $_POST['form_data_json'];
            // Validace JSON
            $decoded = json_decode($form_data_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $update_fields[] = "form_data = ?";
                $update_values[] = $form_data_json;
            } else {
                throw new Exception("Neplatný JSON formát v datech formuláře");
            }
        }
        
        // Aktualizace statusu
        if (isset($_POST['status'])) {
            $update_fields[] = "status = ?";
            $update_values[] = $_POST['status'];
        }
        
        // Aktualizace poznámek
        if (isset($_POST['notes'])) {
            $update_fields[] = "notes = ?";
            $update_values[] = $_POST['notes'];
        }
        
        // Aktualizace času změny
        $update_fields[] = "updated_at = NOW()";
        
        if (!empty($update_fields)) {
            $update_values[] = $form_id;
            $sql = "UPDATE forms SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($update_values);
            
            // Log aktivity
            if (isset($_SESSION['user_id'])) {
                $log_stmt = $pdo->prepare("
                    INSERT INTO user_activity_log (user_id, activity_type, activity_description, ip_address, created_at) 
                    VALUES (?, 'form_edit', ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    "Editace formuláře #$form_id",
                    $_SERVER['REMOTE_ADDR'] ?? 'neznámá'
                ]);
            }
        }
        
        $success_message = "Formulář byl úspěšně aktualizován.";
        
        // Znovu načti data
        $stmt = $pdo->prepare("
            SELECT f.*, u.name as user_name, u.email as user_email 
            FROM forms f 
            LEFT JOIN users u ON f.user_id = u.id 
            WHERE f.id = ?
        ");
        $stmt->execute([$form_id]);
        $form_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error_message = "Chyba při aktualizaci formuláře: " . $e->getMessage();
    }
}

// Dekóduj JSON data
$json_data = json_decode($form_data['form_data'], true);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editace formuláře #<?php echo htmlspecialchars($form_id); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Editace formuláře #<?php echo htmlspecialchars($form_id); ?></h1>
                        <p class="text-gray-600 mt-1">
                            Uživatel: <?php echo htmlspecialchars($form_data['user_name'] ?? 'Neznámý'); ?> 
                            (<?php echo htmlspecialchars($form_data['user_email'] ?? ''); ?>)
                        </p>
                    </div>
                    <button onclick="window.close()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                        Zavřít
                    </button>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Form Edit -->
            <form method="POST" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Základní informace</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="company_name" class="block text-sm font-medium text-gray-700 mb-2">Název firmy</label>
                            <input type="text" id="company_name" name="company_name" 
                                value="<?php echo htmlspecialchars($form_data['company_name'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-2">Kontaktní osoba</label>
                            <input type="text" id="contact_person" name="contact_person" 
                                value="<?php echo htmlspecialchars($form_data['contact_person'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Telefon</label>
                            <input type="text" id="phone" name="phone" 
                                value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email" 
                                value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Form Status -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Stav formuláře</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="draft" <?php echo $form_data['status'] === 'draft' ? 'selected' : ''; ?>>Rozpracován</option>
                                <option value="completed" <?php echo $form_data['status'] === 'completed' ? 'selected' : ''; ?>>Dokončen</option>
                                <option value="submitted" <?php echo $form_data['status'] === 'submitted' ? 'selected' : ''; ?>>Odeslán</option>
                                <option value="confirmed" <?php echo $form_data['status'] === 'confirmed' ? 'selected' : ''; ?>>Potvrzen</option>
                                <option value="approved" <?php echo $form_data['status'] === 'approved' ? 'selected' : ''; ?>>Schválen</option>
                                <option value="rejected" <?php echo $form_data['status'] === 'rejected' ? 'selected' : ''; ?>>Zamítnut</option>
                                <option value="cancelled" <?php echo $form_data['status'] === 'cancelled' ? 'selected' : ''; ?>>Zrušen</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Vytvořen</label>
                            <input type="text" value="<?php echo date('d.m.Y H:i:s', strtotime($form_data['created_at'])); ?>" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                        </div>
                    </div>
                </div>

                <!-- Form Data JSON Editor -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Data formuláře (JSON)</h3>
                    <div class="mb-4">
                        <button type="button" id="toggle-json-editor" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm">
                            Zobrazit/Skrýt JSON editor
                        </button>
                    </div>
                    
                    <!-- Formatted view -->
                    <div id="formatted-view" class="bg-gray-50 p-4 rounded-md">
                        <?php if ($json_data && is_array($json_data)): ?>
                            <div class="space-y-4">
                                <?php foreach ($json_data as $step => $stepData): ?>
                                    <div class="border-l-4 border-blue-500 pl-4">
                                        <h4 class="font-medium text-gray-900 mb-2"><?php echo getStepLabel($step); ?></h4>
                                        <?php if (is_array($stepData)): ?>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                                <?php foreach ($stepData as $key => $value): ?>
                                                    <div class="flex">
                                                        <span class="font-medium text-gray-700 w-1/2"><?php echo getFieldLabel($key); ?>:</span>
                                                        <span class="text-gray-900 w-1/2"><?php echo htmlspecialchars(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($stepData); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500">Žádná data formuláře k zobrazení</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- JSON Editor -->
                    <div id="json-editor" class="hidden">
                        <textarea name="form_data_json" rows="15" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm" placeholder="JSON data formuláře..."><?php echo htmlspecialchars(json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
                        <p class="text-sm text-gray-500 mt-2">
                            <strong>Pozor:</strong> Editujte pouze pokud víte, co děláte. Neplatný JSON způsobí chybu při ukládání.
                        </p>
                    </div>
                </div>

                <!-- Notes -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Poznámky</h3>
                    <textarea name="notes" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Přidejte poznámky k formuláři..."><?php echo htmlspecialchars($form_data['notes'] ?? ''); ?></textarea>
                </div>

                <!-- Submit -->
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="window.close()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Zrušit
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Uložit změny
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
