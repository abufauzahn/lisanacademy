<?php
// Production-safe by default: error detail is written to the server log only.
// Flip $APP_DEBUG to true in config/db.local.php (never committed) to see full
// error messages on screen during development.
$APP_DEBUG = false;

// Register the exception handler FIRST, before loading the local config, so a
// missing/broken db.local.php or a failed database connection still renders a
// readable page and logs the cause — never a bare HTTP 500 with no explanation.
set_exception_handler(function (Throwable $e) use (&$APP_DEBUG) {
    error_log('Uncaught: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!empty($APP_DEBUG)) {
        echo '<div style="font-family:system-ui,Segoe UI,Arial,sans-serif;padding:18px;line-height:1.6;background:#fff;border:1px solid #e5e7eb;border-left:4px solid #dc2626;">'
           . '<h3 style="margin:0 0 8px;color:#b91c1c;">Application error</h3>'
           . '<p style="margin:0 0 6px;"><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>'
           . '<p style="margin:0;color:#6b7280;">' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>'
           . '</div>';
    } else {
        echo '<div style="font-family:system-ui,Segoe UI,Arial,sans-serif;padding:18px;line-height:1.6;background:#fff;border:1px solid #e5e7eb;border-left:4px solid #dc2626;">'
           . '<h3 style="margin:0 0 8px;color:#b91c1c;">Something went wrong</h3>'
           . '<p style="margin:0;">Please try again in a moment. The issue has been logged.</p>'
           . '</div>';
    }
    exit(1);
});

require_once __DIR__ . '/db.local.php';

if (!empty($APP_DEBUG)) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
}
error_reporting(E_ALL);

// Enable mysqli exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create connection (credentials live in db.local.php)
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('DB connect failed: ' . $e->getMessage() . ' (host=' . ($db_host ?? 'unknown') . ', db=' . ($db_name ?? 'unknown') . ')');
    throw $e;
}

// App timezone (Nigeria) — keep PHP and MySQL sessions aligned so that
// NOW() writes, exam close windows and date() displays all agree.
date_default_timezone_set('Africa/Lagos');
try {
    $conn->query("SET time_zone = '+01:00'");
} catch (Throwable $e) {
    // Some shared hosts lock the MySQL session time zone; code keeps running.
}
?>