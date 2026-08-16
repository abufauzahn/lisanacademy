<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_role('admin');

csrf_verify();

/* True when called from the students.php grid via fetch(). */
$is_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

$student_id = (int)($_POST['id'] ?? 0);
if ($student_id <= 0) redirect('students.php');

// Fetch current suspended status
$stmt = $conn->prepare("SELECT suspended FROM users WHERE id=? AND role='student'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();

$new_status = $current['suspended'] ? 0 : 1; // toggle

$stmt = $conn->prepare("UPDATE users SET suspended=? WHERE id=?");
$stmt->bind_param("ii", $new_status, $student_id);
$stmt->execute();

if ($is_ajax) {
    echo $new_status ? 'Student suspended.' : 'Student unsuspended.';
    exit;
}

redirect("student_detail.php?id=$student_id");
?>