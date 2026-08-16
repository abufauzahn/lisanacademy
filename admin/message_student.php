<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

/* =====================
   VALIDATE STUDENT ID
===================== */
$student_id = (int)($_GET['id'] ?? 0);
if ($student_id <= 0) {
    redirect('students.php');
}

/* =====================
   FETCH STUDENT
   (users.phone may not exist on every DB — build the query safely)
===================== */
$has_phone = false;
try {
    $r = $conn->query("
        SELECT COUNT(*) c FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'users' AND column_name = 'phone'
    ")->fetch_assoc();
    $has_phone = !empty($r) && (int)$r['c'] > 0;
} catch (Throwable $e) { /* ignore */ }

$stmt = $conn->prepare($has_phone
    ? "SELECT id, name, email, phone FROM users WHERE id = ? AND role = 'student'"
    : "SELECT id, name, email FROM users WHERE id = ? AND role = 'student'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$student['phone'] = $has_phone ? (string)($student['phone'] ?? '') : '';

if (!$student) {
    redirect('students.php');
}

/* =====================
   GET LAST LESSON
===================== */
$lesson = $conn->query("
    SELECT id
    FROM lessons
    WHERE student_id = $student_id
    ORDER BY id DESC
    LIMIT 1
")->fetch_assoc();

$message = '';
$reminder = '';

if ($lesson) {
    $lesson_id = (int)$lesson['id'];

    /* ADMIN AUDIO */
    $admin_audio = $conn->query("
        SELECT id
        FROM admin_audio
        WHERE learning_plan_id = $lesson_id
        LIMIT 1
    ")->fetch_assoc();

    /* STUDENT RECITATION */
    $recitation = $conn->query("
        SELECT status
        FROM student_recitation
        WHERE learning_plan_id = $lesson_id
        ORDER BY id DESC
        LIMIT 1
    ")->fetch_assoc();

    /* ========= DECISION ========= */

    if ($recitation && $recitation['status'] === 'rejected') {

        $message = "Assalamualaikum {$student['name']},\n\n"
        ."Your last recitation was reviewed and needs correction. "
        ."Please go through the feedback carefully and recite again.";

        $reminder = "Reminder: The Qur’an is not rushed. Take your time, perfect it, and Allah will reward every effort.";

    } elseif ($recitation && $recitation['status'] === 'accepted') {

        $message = "Assalamualaikum {$student['name']},\n\n"
        ."Alhamdulillah! Your recitation has been accepted and you have completed this learning circle. "
        ."You may now request your next lesson.";

        $reminder = "Reminder: Consistency in the Qur’an brings light to the heart and barakah to time.";

    } elseif ($admin_audio) {

        $message = "Assalamualaikum {$student['name']},\n\n"
        ."Your new lesson has been sent. Please listen attentively, practice well, and submit your recitation for review.";

        $reminder = "Reminder: The best of you are those who learn the Qur’an and teach it.";

    } else {

        $message = "Assalamualaikum {$student['name']},\n\n"
        ."Your lesson request has been received. Please be patient while your teacher prepares your lesson.";

        $reminder = "Reminder: Patience with the Qur’an is never wasted time.";
    }

} else {

    /* NEVER REQUESTED / IDLE */
    $message = "Assalamualaikum {$student['name']},\n\n"
    ."We noticed that you have not requested a new lesson yet. "
    ."We hope all is well. Please kindly log in and request your next lesson.";

    $reminder = "Reminder: Do not let your time be lost in what does not benefit. The Qur’an deserves your best moments.";
}

/* FINAL MESSAGE */
$final_message = $message . "\n\n" . $reminder;

/* WhatsApp link — fall back to the academy number when the student has no phone on file */
$student_phone = !empty($student['phone'])
    ? $student['phone']
    : setting($conn, 'whatsapp_number', '2348029979040');
$whatsapp_link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $student_phone) . "?text=" . urlencode($final_message);
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Message Student</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('admin', 'students', 'Message Student', 'WhatsApp'); ?>

<div class="card animate-rise" style="max-width:640px;">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <div class="avatar letter" style="width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--emerald-700),var(--emerald-500));color:#fff;font-weight:700;font-size:1.2rem;"><?= htmlspecialchars(ui_initial($student['name'])) ?></div>
        <div>
            <h3 style="margin:0;"><?= htmlspecialchars($student['name']) ?></h3>
            <p class="small text-muted" style="margin:0;"><?= htmlspecialchars($student['email']) ?></p>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Message Preview</label>
        <textarea class="form-textarea" style="min-height:220px;" readonly><?= htmlspecialchars($final_message) ?></textarea>
    </div>

    <a href="<?= $whatsapp_link ?>" target="_blank">
        <button class="btn btn-gold btn-block btn-lg"><?= ui_icon('phone', 17) ?> Send via WhatsApp</button>
    </a>
</div>

<?php ui_page_end(); ?>

</body>
</html>