<?php
/**
 * Public Form Auth API
 *
 * Validates a public form token and optional password.
 * Used by the frontend to verify access before showing the form.
 *
 * GET  ?action=validate&token=XXX        – Check if token is valid, returns metadata
 * POST ?action=authenticate               – Validate token + password, returns session info
 */
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    $pdo = getDbConnection();
    $logger = new Logger($pdo);

    $action = $_GET['action'] ?? null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $action ?? $input['action'] ?? null;
    } else {
        $input = $_GET;
    }

    switch ($action) {
        case 'validate':
            handleValidate($pdo, $input);
            break;
        case 'authenticate':
            handleAuthenticate($pdo, $input, $logger);
            break;
        default:
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Neznámá akce']);
            exit;
    }

} catch (Exception $e) {
    ob_end_clean();
    error_log("Public form auth error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Interní chyba serveru']);
}

function handleValidate(PDO $pdo, ?array $input): void
{
    $token = trim($input['token'] ?? '');
    if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Neplatný token']);
        return;
    }

    $link = fetchActiveLink($pdo, $token);
    if (!$link) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Odkaz je neplatný, vypršel nebo byl již použit']);
        return;
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'requires_password' => !empty($link['password_hash']),
            'recipient_email' => $link['recipient_email'],
            'recipient_name' => $link['recipient_name'],
            'owner_name' => $link['owner_name'],
            'expires_at' => $link['expires_at'],
        ],
    ]);
}

function handleAuthenticate(PDO $pdo, ?array $input, Logger $logger): void
{
    $token = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Neplatný token']);
        return;
    }

    $link = fetchActiveLink($pdo, $token);
    if (!$link) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Odkaz je neplatný, vypršel nebo byl již použit']);
        return;
    }

    // Check password if required
    if (!empty($link['password_hash'])) {
        if (empty($password) || !password_verify($password, $link['password_hash'])) {
            ob_end_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Nesprávné heslo']);
            return;
        }
    }

    $logger->info(Logger::TYPE_AUTH, 'Public form link authenticated', [
        'link_id' => $link['id'],
        'recipient_email' => $link['recipient_email'],
    ]);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'token' => $token,
            'link_id' => (int)$link['id'],
            'owner_user_id' => $link['owner_user_id'],
            'recipient_email' => $link['recipient_email'],
            'recipient_name' => $link['recipient_name'],
            'authenticated' => true,
        ],
    ]);
}

/**
 * Fetch an active, non-expired link by token, joining owner name.
 */
function fetchActiveLink(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare("
        SELECT pl.*, u.name AS owner_name
        FROM public_form_links pl
        JOIN users u ON u.id = pl.owner_user_id
        WHERE pl.token = ?
          AND pl.status = 'active'
          AND (pl.expires_at IS NULL OR pl.expires_at > NOW())
    ");
    $stmt->execute([$token]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    return $link ?: null;
}
