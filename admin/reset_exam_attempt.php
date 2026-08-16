<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('exams.php');
}

csrf_verify();

$attempt_id = (int)($_POST['attempt_id'] ?? 0);
if ($attempt_id <= 0) redirect('exams.php');

/* Any attempt may be deleted — pending (reset) or already reviewed. */
$stmt = $conn->prepare("SELECT status, student_id FROM exam_attempts WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) redirect('exams.php');

$deleted_status  = (string)$row['status'];
$deleted_student = (int)($row['student_id'] ?? 0);

/* Fetch answer audio files so we can delete them from disk */
$res = $conn->query("SELECT audio_file FROM exam_answers WHERE attempt_id = $attempt_id");
while ($row = $res->fetch_assoc()) {
    if (!empty($row['audio_file'])) {
        $file = __DIR__ . '/../uploads/exam_audio/' . basename($row['audio_file']);
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

$conn->query("DELETE FROM exam_answers WHERE attempt_id = $attempt_id");
$conn->query("DELETE FROM exam_attempts WHERE id = $attempt_id");

/* Deleting an already-reviewed attempt clears the student's exam/lock flags so
   they are not left locked by a record that no longer exists, and the admin can
   re-manage them from the Defaulters & Payments page. */
if ($deleted_status !== 'pending' && $deleted_student > 0) {
    $conn->query("UPDATE users SET exam_defaulted = 0, exam_owed = 0, exam_access = 0 WHERE id = $deleted_student");
}

redirect('exams.php');
