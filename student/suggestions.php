<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message !== '') {
        $stmt = $conn->prepare("
            INSERT INTO suggestions (student_id, message)
            VALUES (?, ?)
        ");
        $stmt->bind_param("is", $student_id, $message);
        $stmt->execute();
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Suggestions & Advice</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('student', 'suggestions', 'Suggestions', 'Feedback'); ?>

<div class="animate-rise" style="max-width:600px;margin:0 auto;">
    <div class="card" style="padding:28px;">
        <div class="text-center" style="margin-bottom:10px;">
            <div style="font-size:2.2rem;"><?= ui_icon('bulb', 34) ?></div>
            <h2 style="margin:6px 0 2px;">Suggestions &amp; Advice</h2>
            <p class="small text-muted" style="margin:0;">Help us improve Lisanun Mubeen Academy.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= ui_icon('check-circle', 16) ?> Thank you for your suggestion!</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="message">Your message</label>
                <textarea class="form-textarea" id="message" name="message" required placeholder="Write your suggestion or advice here..."></textarea>
            </div>
            <button type="submit" class="btn btn-block btn-lg">Send Suggestion</button>
        </form>
    </div>
</div>

<?php ui_page_end(); ?>

</body>
</html>