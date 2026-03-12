<?php
// church/logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Wipe all session data
$_SESSION = [];

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Redirect to login with a logout flag so we can show a message
header('Location: /church/login.php?logged_out=1');
exit;