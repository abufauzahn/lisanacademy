<?php
// config/security/session.php

// Only start session if none exists
if(session_status() === PHP_SESSION_NONE){

    // Use a folder we know is writable
    $save_path = __DIR__ . '/../../tmp';
    if(!is_dir($save_path)){
        mkdir($save_path, 0777, true);
    }
    session_save_path($save_path);

    // Standard session settings
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}
?>
