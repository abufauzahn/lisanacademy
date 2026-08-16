<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('students.php');
}

csrf_verify();

$student_id = (int)($_POST['student_id'] ?? 0);
$name       = trim($_POST['name'] ?? '');

if ($student_id <= 0 || $name === '') {
    redirect('student_detail.php?id=' . $student_id . '&error=' . urlencode('Name is required'));
}

$stmt = $conn->prepare("SELECT id FROM users WHERE id=? AND role='student'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    redirect('students.php');
}

$stmt = $conn->prepare("UPDATE users SET name=? WHERE id=? AND role='student'");
$stmt->bind_param("si", $name, $student_id);
$stmt->execute();

redirect('student_detail.php?id=' . $student_id);
