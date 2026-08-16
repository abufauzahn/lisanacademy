<?php
// auth/logout.php

require_once __DIR__ . '/../config/security/session.php';

// Unset all session variables
$_SESSION = [];

// Destroy session
session_destroy();

// Remove session cookie (important on shared hosting)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Redirect to login
header('Location: /auth/login.php');
exit;
