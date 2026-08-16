<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

csrf_verify();

$student_id = (int)($_POST['id'] ?? 0);
if ($student_id <= 0) {
    redirect('students.php');
}

/* -------- SAFETY: ensure student exists -------- */
$stmt = $conn->prepare("SELECT id FROM users WHERE id=? AND role='student'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    redirect('students.php');
}

/* -------- DELETE IN CORRECT ORDER -------- */
$conn->begin_transaction();

try {
    // student recitations
    $stmt = $conn->prepare("DELETE FROM student_recitation WHERE student_id=?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // lessons
    $stmt = $conn->prepare("DELETE FROM lessons WHERE student_id=?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // learning plans
    $stmt = $conn->prepare("DELETE FROM student_learning WHERE student_id=?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // finally user
    $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role='student'");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    exit('Failed to delete student. Please try again.');
}

redirect('students.php');