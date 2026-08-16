<?php
require '../config/security/helpers.php';
require_role('admin');
require '../auth/auth_check.php';
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('teaching.php');
}

$rec_id = intval($_POST['rec_id'] ?? 0);
$rating = clean($_POST['rating'] ?? '');
$feedback = clean($_POST['feedback'] ?? '');

if ($rec_id <= 0) {
    ui_message_page('danger', 'Invalid Request', 'Missing recitation ID.', 'teaching.php', 'Back to Teaching Dashboard', 'close');
}

$stmt = $conn->prepare("UPDATE student_recitation SET rating=?, feedback=?, status='reviewed' WHERE id=?");
$stmt->bind_param("ssi", $rating, $feedback, $rec_id);
if ($stmt->execute()) {
    ui_message_page('success', 'Feedback Submitted', 'The feedback was submitted successfully and is now visible to the student.', 'teaching.php', 'Back to Teaching Dashboard', 'chat');
}
ui_message_page('danger', 'Submission Failed', 'The feedback could not be saved. Please try again.', 'teaching.php', 'Back to Teaching Dashboard', 'close');
?>