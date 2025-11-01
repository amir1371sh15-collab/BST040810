<?php
// We only need the base configuration to get session_start() and BASE_URL.
require_once __DIR__ . '/config/db.php';

// --- The definitive logout process ---

// 1. Unset all of the session variables.
$_SESSION = [];

// 2. If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finally, destroy the session.
session_destroy();

// 4. Redirect to the login page (which is now index.php).
header('Location: ' . BASE_URL . 'index.php?status=loggedout');
exit;

