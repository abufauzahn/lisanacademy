<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('student');
$student_id = (int)$_SESSION['user_id'];

$code     = student_referral_code($conn, $student_id);

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name  = trim($_POST['friend_name'] ?? '');
    $phone = trim($_POST['friend_phone'] ?? '');

    if ($name === '' || $phone === '') {
        $error = 'Please enter your friend\'s name and phone number.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO student_invites (student_id, friend_name, friend_phone) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $student_id, $name, $phone);
            $stmt->execute();
            $flash = 'Invite sent — we\'ll reach out to <strong>' . htmlspecialchars($name) . '</strong> using your referral code.';
        } catch (Throwable $e) {
            $error = 'Could not save the invite. Please try again.';
        }
    }
}

/* This student's own invites. */
$invites = [];
try {
    $res = $conn->query("SELECT friend_name, friend_phone, status, created_at FROM student_invites WHERE student_id = $student_id ORDER BY created_at DESC LIMIT 20");
    while ($row = $res->fetch_assoc()) $invites[] = $row;
} catch (Throwable $e) {
    $invites = [];
}

/* The student's earned referral discount (15% off next term fees). */
$discount_term = '';
try {
    $stmt = $conn->prepare("SELECT next_term_discount, discount_term FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r && (int)$r['next_term_discount'] === 1) $discount_term = (string)($r['discount_term'] ?? '');
} catch (Throwable $e) {
    $discount_term = '';
}

/* Pre-built WhatsApp share message using the referral code. Uses the generic
   wa.me share form (no recipient number) so WhatsApp opens its contact picker
   and the student chooses who to share with, instead of DM-ing the academy. */
$share_text = "Assalamualaikum! Join me at Lisanun Mubeen Academy — a place where the Qur'an becomes your life companion. "
    . "When you register, please quote my referral code: $code. "
    . "Barakallahu feek!";
$share_url = 'https://wa.me/?text=' . rawurlencode($share_text);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invite Friends</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('student', 'holiday', 'Invite Friends', 'Referral'); ?>

<div class="page-hero animate-rise">
    <h1><?= ui_icon('users', 20) ?> Invite Friends to the Academy</h1>
    <p>Spread the blessing of learning the Qur’an — every friend who joins with your code is a reward for you.</p>
</div>

<div class="alert alert-success animate-rise d1" style="max-width:640px;">
    <?= ui_icon('gift', 18) ?>
    <span style="flex:1;"><strong>Referral reward: 15% off your next term fees.</strong> When a friend you invite registers and starts learning, you automatically earn a <strong>15% discount</strong> on your next term school fees.</span>
</div>

<?php if ($discount_term !== ''): ?>
    <div class="alert animate-rise d1" style="max-width:640px;background:var(--warning-bg);color:#92400e;border-color:var(--warning-border);">
        <?= ui_icon('check-circle', 18) ?>
        <span style="flex:1;"><strong>You’ve earned your 15% discount!</strong> It applies to your fees for <strong><?= htmlspecialchars($discount_term) ?></strong>.</span>
    </div>
<?php endif; ?>

<div class="card card-gold animate-rise d1" style="max-width:640px;">
    <div class="card-title"><h3 style="margin-top:0;">Your Referral Code</h3></div>
    <p class="small text-muted" style="margin:0 0 10px;">Share this code with friends. When they register, the academy will ask them to quote it.</p>
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;">
        <span class="badge badge-gold" style="font-size:1.1rem;padding:8px 16px;"><?= htmlspecialchars($code) ?></span>
        <button class="btn btn-sm btn-ghost" onclick="copyCode()"><?= ui_icon('notes', 15) ?> Copy</button>
    </div>
    <a class="btn btn-gold btn-lg mt-2" href="<?= htmlspecialchars($share_url) ?>" target="_blank">
        <?= ui_icon('send', 17) ?> Share via WhatsApp
    </a>
</div>

<div class="card animate-rise d2" style="max-width:640px;margin-top:18px;">
    <h3 style="margin-top:0;"><?= ui_icon('user', 18) ?> Tell us who you invited</h3>
    <p class="small text-muted" style="margin:0 0 14px;">Add your friend’s details so the academy can reach out to them.</p>

    <?php if ($flash !== ''): ?>
        <div class="alert alert-success"><?= ui_icon('check-circle', 16) ?> <span style="flex:1;"><?= $flash ?></span></div>
    <?php elseif ($error !== ''): ?>
        <div class="alert alert-danger"><?= ui_icon('alert', 16) ?> <span style="flex:1;"><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="friend_name">Friend's Full Name</label>
            <input class="form-input" type="text" id="friend_name" name="friend_name" required maxlength="100" placeholder="e.g. Ahmad Musa">
        </div>
        <div class="form-group">
            <label class="form-label" for="friend_phone">Friend's Phone Number</label>
            <input class="form-input" type="tel" id="friend_phone" name="friend_phone" required maxlength="30" placeholder="e.g. 08012345678">
        </div>
        <button class="btn btn-gold" type="submit"><?= ui_icon('check', 16) ?> Save Invite</button>
    </form>
</div>

<?php if (count($invites) > 0): ?>
    <div class="card animate-rise d3" style="max-width:640px;margin-top:18px;">
        <h3 style="margin-top:0;"><?= ui_icon('notes', 18) ?> Your Invites</h3>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Friend</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Invited</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invites as $inv): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($inv['friend_name']) ?></strong></td>
                            <td><?= htmlspecialchars($inv['friend_phone']) ?></td>
                            <td>
                                <?php $st = $inv['status'] ?? 'invited'; ?>
                                <?php if ($st === 'rewarded'): ?>
                                    <span class="badge badge-gold">Rewarded — you got 15% off</span>
                                <?php elseif ($st === 'joined'): ?>
                                    <span class="badge badge-blue">Joined the academy</span>
                                <?php else: ?>
                                    <span class="badge badge-grey">Invited</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div style="margin-top:16px;">
    <a class="btn btn-ghost" href="holiday.php"><?= ui_icon('arrow-left', 16) ?> Back to Holiday</a>
</div>

<?php ui_page_end(); ?>

<script>
function copyCode() {
    const code = <?= json_encode($code) ?>;
    const done = function () { alert('Referral code copied: ' + code); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(done, done);
    } else {
        done();
    }
}
</script>

</body>
</html>
