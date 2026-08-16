<?php
require '../config/security/helpers.php';
require_role('student');
require '../auth/auth_check.php';
require '../config/db.php';

$student_id = (int)$_SESSION['user_id'];

if (!isset($_POST['recitation_id'])) {
    header("Location: feedback.php");
    exit;
}

csrf_verify();

$recitation_id = (int)$_POST['recitation_id'];

$stmt = $conn->prepare("
    UPDATE student_recitation
    SET student_deleted = 1
    WHERE id = ? AND student_id = ?
");
$stmt->bind_param("ii", $recitation_id, $student_id);
$stmt->execute();

header("Location: feedback.php");
exit;
