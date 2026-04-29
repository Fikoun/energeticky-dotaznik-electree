<?php
/**
 * Public Form Links API
 *
 * Allows registered users (salesmen/admins) to create, list, and manage
 * shareable form links for external users.
 *
 * Actions:
 *   create   – Create a new public link
 *   list     – List links created by the current user
 *   revoke   – Revoke (deactivate) a link
 *   detail   – Get link details
 */

// Session must start before ANY output (including ob_start)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

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

    // Require authenticated user
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nepřihlášený uživatel']);
        exit;
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$action) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? null;
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    } else {
        $input = $_GET;
    }

    switch ($action) {
        case 'create':
            handleCreate($pdo, $userId, $input, $logger);
            break;
        case 'list':
            handleList($pdo, $userId, $input);
            break;
        case 'revoke':
            handleRevoke($pdo, $userId, $input, $logger);
            break;
        case 'detail':
            handleDetail($pdo, $userId, $input);
            break;
        default:
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Neznámá akce: ' . $action]);
            exit;
    }

} catch (Exception $e) {
    ob_end_clean();
    error_log("Public links API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Interní chyba serveru']);
}

// ──────────────────────────────────────────────
// Action handlers
// ──────────────────────────────────────────────

function handleCreate(PDO $pdo, string $userId, ?array $input, Logger $logger): void
{
    $email = trim($input['email'] ?? '');
    $name = trim($input['name'] ?? '');
    $password = $input['password'] ?? null;
    $description = trim($input['description'] ?? '');
    $expiresInDays = (int)($input['expires_in_days'] ?? 30);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Neplatný email']);
        return;
    }

    // Generate secure token
    $token = bin2hex(random_bytes(32));

    // Hash password if provided
    $passwordHash = null;
    if (!empty($password)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"));

    $stmt = $pdo->prepare("
        INSERT INTO public_form_links (token, owner_user_id, recipient_email, recipient_name, password_hash, description, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$token, $userId, $email, $name ?: null, $passwordHash, $description ?: null, $expiresAt]);

    $linkId = $pdo->lastInsertId();

    // Build the public URL
    $baseUrl = rtrim($_SERVER['REQUEST_SCHEME'] ?? 'https', '/') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ed.electree.cz');
    $publicUrl = $baseUrl . '/?public=' . $token;

    $logger->info(Logger::TYPE_USER, 'Public form link created', [
        'link_id' => $linkId,
        'recipient_email' => $email,
        'has_password' => !empty($password),
        'expires_at' => $expiresAt,
    ]);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$linkId,
            'token' => $token,
            'url' => $publicUrl,
            'email' => $email,
            'name' => $name,
            'has_password' => !empty($password),
            'expires_at' => $expiresAt,
        ],
    ]);
}

function handleList(PDO $pdo, string $userId, ?array $input): void
{
    $status = $input['status'] ?? null;
    $page = max(1, (int)($input['page'] ?? 1));
    $limit = min(100, max(1, (int)($input['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    $where = "WHERE owner_user_id = ?";
    $params = [$userId];

    if ($status) {
        $where .= " AND status = ?";
        $params[] = $status;
    }

    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM public_form_links {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Get links
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare("
        SELECT id, token, recipient_email, recipient_name, description,
               password_hash IS NOT NULL AS has_password,
               form_id, status, expires_at, used_at, created_at
        FROM public_form_links
        {$where}
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast booleans
    foreach ($links as &$link) {
        $link['has_password'] = (bool)$link['has_password'];
        $link['id'] = (int)$link['id'];
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $links,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ],
    ]);
}

function handleRevoke(PDO $pdo, string $userId, ?array $input, Logger $logger): void
{
    $linkId = (int)($input['link_id'] ?? 0);
    if (!$linkId) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Chybí link_id']);
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE public_form_links SET status = 'revoked', updated_at = NOW()
        WHERE id = ? AND owner_user_id = ? AND status = 'active'
    ");
    $stmt->execute([$linkId, $userId]);

    if ($stmt->rowCount() === 0) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Odkaz nebyl nalezen nebo již není aktivní']);
        return;
    }

    $logger->info(Logger::TYPE_USER, 'Public form link revoked', ['link_id' => $linkId]);

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Odkaz byl zneplatněn']);
}

function handleDetail(PDO $pdo, string $userId, ?array $input): void
{
    $linkId = (int)($input['link_id'] ?? 0);
    if (!$linkId) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Chybí link_id']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT pl.*, f.company_name, f.contact_person, f.status AS form_status, f.created_at AS form_created_at
        FROM public_form_links pl
        LEFT JOIN forms f ON f.id = pl.form_id
        WHERE pl.id = ? AND pl.owner_user_id = ?
    ");
    $stmt->execute([$linkId, $userId]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$link) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Odkaz nebyl nalezen']);
        return;
    }

    $link['has_password'] = !empty($link['password_hash']);
    unset($link['password_hash']);

    ob_end_clean();
    echo json_encode(['success' => true, 'data' => $link]);
}
