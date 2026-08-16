<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_role('student');
include '../auth/auth_check.php';
include '../config/db.php';

$student_id = (int)$_SESSION['user_id'];
$name       = $_SESSION['name'] ?? '';
$whatsapp   = setting($conn, 'whatsapp_number', '2348029979040');

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $amount = floatval($_POST['amount'] ?? 0);

    if ($amount <= 0) {
        $error_msg = 'Please enter a valid donation amount in Naira (₦).';
    } else {
        /* Record the donation request so the admin can see it. */
        try {
            $stmt = $conn->prepare("INSERT INTO donations (student_id, amount, method) VALUES (?, ?, 'WhatsApp Arrangement')");
            $stmt->bind_param("id", $student_id, $amount);
            $stmt->execute();
        } catch (Throwable $e) { /* ignore */ }

        /* Open WhatsApp with a pre-filled message carrying the amount. */
        $msg = "Assalamualaikum, I am " . $name . ". I would like to donate \u{20A6}" . number_format($amount)
             . " to Lisanun Mubeen Academy. Please share the payment details. JazakAllah khair.";
        redirect('https://wa.me/' . $whatsapp . '?text=' . rawurlencode($msg));
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Donate - Lisanun Mubeen Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= ui_css() ?>
</head>
<?php ui_page_start('student', 'holiday', 'Donate', 'Support'); ?>

<div class="animate-rise" style="max-width:560px;margin:0 auto;">
    <div class="card" style="padding:28px;">
        <div class="text-center" style="margin-bottom:10px;">
            <div style="font-size:2.2rem;"><?= ui_icon('gift', 34) ?></div>
            <h2 style="margin:6px 0 2px;">Donate to Lisanun Mubeen Academy</h2>
            <p class="small text-muted" style="margin:0;">Your support helps the academy continue teaching the Qur’an.</p>
        </div>

        <?php if ($error_msg !== ''): ?>
            <div class="alert alert-danger"><?= ui_icon('alert', 16) ?> <span style="flex:1;"><?= htmlspecialchars($error_msg) ?></span></div>
        <?php endif; ?>

        <div class="panel">
            <strong>How it works:</strong>
            <ul class="small" style="margin:8px 0 0;padding-left:20px;">
                <li>Enter how much you want to donate <strong>(in Naira, ₦)</strong>.</li>
                <li>Tap <strong>Donate via WhatsApp</strong> — a message with your amount opens automatically.</li>
                <li>We reply with the payment details, then confirm your donation when it arrives.</li>
            </ul>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="amount">Donation Amount (₦ Naira)</label>
                <input class="form-input" type="number" id="amount" name="amount" min="100" step="50" placeholder="e.g. 5000" required>
                <span class="small text-muted">Minimum donation: ₦100</span>
            </div>

            <button type="submit" class="btn btn-gold btn-block btn-lg"><?= ui_icon('send', 18) ?> Donate via WhatsApp</button>
        </form>
    </div>

    <div style="text-align:center;margin-top:14px;">
        <a class="btn btn-ghost" href="holiday.php"><?= ui_icon('arrow-left', 16) ?> Back to Holiday</a>
    </div>
</div>

<?php ui_page_end(); ?>

</body>
</html>
