<?php
require '../config/security/helpers.php';
require '../auth/auth_check.php';
require '../config/db.php';
require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('Invalid method');
csrf_verify();
if (!isset($_POST['audio_id']) || !is_numeric($_POST['audio_id'])) exit('Invalid audio ID');

$audio_id   = (int)$_POST['audio_id'];
$student_id = (int)$_SESSION['user_id'];

if (student_exam_locked($conn, $student_id)) {
    header("Location: exam_defaulted.php");
    exit;
}

$stmt = $conn->prepare("SELECT id FROM admin_audio WHERE id=? AND student_id=? AND acknowledged=0 LIMIT 1");
$stmt->bind_param("ii", $audio_id, $student_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) exit('Already acknowledged or unauthorized');

$update = $conn->prepare("UPDATE admin_audio SET acknowledged=1 WHERE id=?");
$update->bind_param("i", $audio_id);
if ($update->execute()) echo "OK";
else echo "Failed";
