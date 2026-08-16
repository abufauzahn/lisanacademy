<?php
require '../config/security/helpers.php';
require '../config/db.php';

$lesson_id = intval($_POST['lesson_id']);
$student_id = (int)($_SESSION['user_id'] ?? 0);
if ($student_id <= 0) exit('Not authorized');

if (student_exam_locked($conn, $student_id)) {
    header("Location: exam_defaulted.php");
    exit;
}

$conn->query("UPDATE lessons SET status = 'admin_audio_completed' WHERE id = $lesson_id AND student_id = $student_id");

if ($conn->affected_rows > 0) {
    echo "Lesson marked as completed. You can now record your recitation.";
} else {
    echo "Error updating status or not authorized.";
}