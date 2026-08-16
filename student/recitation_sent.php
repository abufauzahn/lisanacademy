<?php
require '../config/security/helpers.php';
require '../auth/auth_check.php';
require '../config/db.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];

/* Look up the most recent pending recitation so the confirmation can name it. */
$stmt = $conn->prepare("
    SELECT l.from_verse, l.to_verse, s.name_en AS surah_name, s.name_ar AS surah_name_ar
    FROM student_recitation sr
    JOIN lessons l ON l.id = sr.learning_plan_id
    JOIN surahs s ON s.id = l.surah_id
    WHERE sr.student_id = ? AND sr.status = 'pending'
    ORDER BY sr.id DESC LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();

if ($rec) {
    $surah = htmlspecialchars($rec['surah_name']);
    $ar = arabic_text($rec['surah_name_ar'] ?? '');
    if ($ar !== '') $surah .= ' <span class="arabic">' . htmlspecialchars($ar) . '</span>';
    $msg = 'Your recitation of <strong>' . $surah . '</strong>'
         . ' (verses ' . (int)$rec['from_verse'] . '–' . (int)$rec['to_verse'] . ')'
         . ' has been received and is now <strong>awaiting review</strong> by your teacher.';
} else {
    $msg = 'Your recitation has been received and is now <strong>awaiting review</strong> by your teacher.';
}

ui_message_page('success', 'Recitation Submitted', $msg, 'my_learning.php', 'Back to My Learning');
