<?php
/**
 * System Logout Page
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

start_system_session();

if (isset($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], $_SESSION['user_name'] ?? 'User', 'LOGOUT', 'Auth', (string)$_SESSION['user_id'], 'User logged out');
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: ' . BASE_URL . '/login.php');
exit;
