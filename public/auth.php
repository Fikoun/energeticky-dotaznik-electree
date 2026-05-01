<?php
/**
 * Autentizační helper pro admin rozhraní
 */

// Spustit session pouze pokud není již spuštěná
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

// Kontrola přihlášení pro admin rozhraní
function requireAuth($role = null) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header('Location: /');
        exit();
    }

    if ($role === 'admin' && $_SESSION['user_role'] !== 'admin') {
        header('Location: /');
        exit();
    }
}
?>
