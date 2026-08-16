<?php
require '../config/security/helpers.php';
require_role('admin');
require '../auth/auth_check.php';
require '../config/db.php';

$rec_id   = (int)$_POST['rec_id'];
$status   = $_POST['status']; // accepted or rejected
$rating   = $_POST['rating'] ?? null;
$feedback = $_POST['feedback'] ?? null;

// Optional: handle uploaded admin audio feedback
$adminAudioFile = null;
if (isset($_FILES['admin_audio']) && $_FILES['admin_audio']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['admin_audio']['tmp_name'];
    $ext = pathinfo($_FILES['admin_audio']['name'], PATHINFO_EXTENSION);
    $newFileName = 'feedback_' . $rec_id . '_' . time() . '.' . $ext;
    $uploadPath = __DIR__ . '/../uploads/admin_feedback/' . $newFileName;

    if (move_uploaded_file($tmpName, $uploadPath)) {
        $adminAudioFile = $newFileName;
    }
}

/* Fetch recitation */
$stmt = $conn->prepare("
    SELECT sr.student_id, sr.learning_plan_id, l.surah_id, l.from_verse, l.to_verse
    FROM student_recitation sr
    JOIN lessons l ON l.id = sr.learning_plan_id
    WHERE sr.id = ?
");
$stmt->bind_param("i", $rec_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    ui_message_page('danger', 'Invalid Recitation', 'The recitation could not be found. It may have already been deleted.', 'teaching.php', 'Back to Teaching Dashboard', 'close');
}

/* Update recitation status, written feedback, rating, and audio feedback */
if ($adminAudioFile) {
    $stmt = $conn->prepare("
        UPDATE student_recitation
        SET status = ?, rating = ?, feedback = ?, admin_audio_feedback = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssssi", $status, $rating, $feedback, $adminAudioFile, $rec_id);
} else {
    $stmt = $conn->prepare("
        UPDATE student_recitation
        SET status = ?, rating = ?, feedback = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssi", $status, $rating, $feedback, $rec_id);
}

$stmt->execute();

/* IF ACCEPTED → ADVANCE STUDENT PROGRESS */
if ($status === 'accepted') {
    $verses_completed = ($data['to_verse'] - $data['from_verse']) + 1;
    $stmt = $conn->prepare("
        UPDATE student_learning
        SET completed_verses = completed_verses + ?
        WHERE student_id = ? AND surah_id = ?
    ");
    $stmt->bind_param(
        "iii",
        $verses_completed,
        $data['student_id'],
        $data['surah_id']
    );
    $stmt->execute();
}

/* Mark lesson as reviewed */
$conn->query("UPDATE lessons SET status='reviewed' WHERE id={$data['learning_plan_id']}");

/* Styled confirmation page (replaces the old bare "OK" white screen) */
$accepted = ($status === 'accepted');
$msg = $accepted
    ? 'The recitation was <strong>accepted</strong>. The student&rsquo;s progress has been advanced and feedback was saved.'
    : 'The recitation was <strong>rejected</strong>. The student can view the feedback and re-record their recitation.';
if ($rating) {
    $msg .= '<br><br>Rating: <strong>' . htmlspecialchars($rating) . '</strong>';
}
if ($feedback) {
    $msg .= '<br>Feedback: ' . nl2br(htmlspecialchars($feedback));
}
ui_message_page(
    $accepted ? 'success' : 'danger',
    $accepted ? 'Recitation Accepted' : 'Recitation Rejected',
    $msg,
    'teaching.php',
    'Back to Teaching Dashboard',
    $accepted ? 'check-circle' : 'close'
);
?>