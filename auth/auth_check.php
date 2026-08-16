<?php
// auth/auth_check.php

require_once __DIR__ . '/../config/security/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT role, suspended, blocked 
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* Account deleted (e.g. a graduate purged after one week) — force logout. */
if (!$user) {
    session_destroy();
    header("Location: /auth/login.php?error=account_deleted");
    exit;
}

/* -------- SUSPENDED -------- */
if ($user['suspended'] == 1) {
    session_destroy();
    header("Location: /auth/login.php?error=suspended");
    exit;
}

/* -------- BLOCKED STUDENT -------- */
/*
 Allow blocked students to view ONLY blocked.php
 Prevent infinite redirect
*/
$currentPage = basename($_SERVER['PHP_SELF']);

if (
    $user['role'] === 'student' &&
    $user['blocked'] == 1 &&
    $currentPage !== 'blocked.php'
) {
    header("Location: /student/blocked.php");
    exit;
}

/* -------- HOLIDAY MODE -------- */
/*
 When holiday mode is active, students may only visit the holiday notice page
 and the whitelisted holiday features (certificate, exam result, invite,
 donate, fees, announcements, suggestions, profile). Every other student page
 is redirected to the holiday notice page. Admin access is unaffected.
*/
if (
    $user['role'] === 'student' &&
    holiday_mode_on($conn) &&
    !holiday_allowed_student_page($currentPage)
) {
    header("Location: /student/holiday.php");
    exit;
}