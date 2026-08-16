<?php
require '../config/security/helpers.php';
require '../auth/auth_check.php';
require '../config/db.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);
$base_sql = "
    SELECT sr.id, sr.rating, sr.feedback, l.from_verse, l.to_verse,
           s.name_en AS surah_name, s.name_ar AS surah_name_ar
    FROM student_recitation sr
    JOIN lessons l ON l.id = sr.learning_plan_id
    JOIN surahs s ON s.id = l.surah_id
    WHERE sr.student_id = ? AND sr.status = 'rejected' AND sr.student_deleted = 0
";
if ($id > 0) {
    $stmt = $conn->prepare($base_sql . " AND sr.id = ? LIMIT 1");
    $stmt->bind_param("ii", $student_id, $id);
} else {
    $stmt = $conn->prepare($base_sql . " ORDER BY sr.id DESC LIMIT 1");
    $stmt->bind_param("i", $student_id);
}
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();

if (!$rec) {
    ui_message_page('info', 'No Feedback Found', 'There is nothing to review right now.', 'feedback.php', 'Back to Feedback');
}

/* Mark this recitation's feedback as seen so it is not re-shown as new. */
$mark = $conn->prepare("UPDATE student_recitation SET feedback_seen = 1 WHERE id = ?");
$mark->bind_param("i", $rec['id']);
$mark->execute();

$surah = htmlspecialchars($rec['surah_name']);
$ar = arabic_text($rec['surah_name_ar'] ?? '');
if ($ar !== '') $surah .= ' <span class="arabic">' . htmlspecialchars($ar) . '</span>';
$verses = 'verses ' . (int)$rec['from_verse'] . '–' . (int)$rec['to_verse'];

$msg = 'Your recitation of <strong>' . $surah . '</strong> (' . $verses . ') needs a little more work.';
$teacher = trim((string)$rec['feedback']);
if ($teacher !== '') {
    $msg .= '<br><br>' . nl2br(htmlspecialchars($teacher));
}
$msg .= '<br><br>Please review your teacher’s feedback, then re-record your recitation from <strong>My Lessons</strong>.';

ui_message_page('danger', 'Recitation Rejected', $msg, 'feedback.php', 'View Feedback');
