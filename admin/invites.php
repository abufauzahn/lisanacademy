<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

/* ============ REWARD INVITER ============
   Manual fallback: the invited friend has registered & started learning.
   Grants the inviting student a 15% next-term discount. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $invite_id = (int)($_POST['invite_id'] ?? 0);
    if ($invite_id > 0) reward_invite($conn, $invite_id);
    redirect('invites.php?rewarded=1');
}

/* All friend invites submitted by students. */
$invites = [];
try {
    $res = $conn->query("
        SELECT si.id, si.friend_name, si.friend_phone, si.status, si.created_at, si.rewarded_at,
               u.id AS student_id, u.name AS student_name, u.email AS student_email
        FROM student_invites si
        LEFT JOIN users u ON u.id = si.student_id
        ORDER BY si.created_at DESC
    ");
    while ($row = $res->fetch_assoc()) $invites[] = $row;
} catch (Throwable $e) {
    $invites = [];
}

/* Every student + their referral code. */
$students = [];
try {
    $res = $conn->query("
        SELECT id, name, email, referral_code
        FROM users
        WHERE role = 'student'
        ORDER BY name ASC
    ");
    while ($row = $res->fetch_assoc()) $students[] = $row;
} catch (Throwable $e) {
    $students = [];
}

/* Students who earned the 15% next-term discount. */
$discounted = [];
try {
    $res = $conn->query("
        SELECT id, name, email, discount_term
        FROM users
        WHERE role = 'student' AND next_term_discount = 1
        ORDER BY name ASC
    ");
    while ($row = $res->fetch_assoc()) $discounted[] = $row;
} catch (Throwable $e) {
    $discounted = [];
}

$schema_ready = db_table_exists($conn, 'student_invites')
    && db_column_exists($conn, 'users', 'referral_code')
    && db_column_exists($conn, 'student_invites', 'status')
    && db_column_exists($conn, 'users', 'next_term_discount');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invites &amp; Referral Codes</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('admin', 'holiday', 'Invites & Referral Codes', 'Holiday'); ?>

<div class="page-hero animate-rise">
    <h1><?= ui_icon('users', 20) ?> Invites &amp; Referral Codes</h1>
    <p>Students share their referral codes to invite friends. When an invited friend registers and starts learning, the student who invited them earns a <strong>15% discount on next term fees</strong>.</p>
</div>

<?php if (isset($_GET['rewarded']) && $_GET['rewarded'] === '1'): ?>
    <div class="alert alert-success animate-rise">
        <?= ui_icon('check-circle', 16) ?>
        <span style="flex:1;"><strong>Inviter rewarded.</strong> The student earned a 15% discount on their next term fees.</span>
    </div>
<?php endif; ?>

<?php if (!$schema_ready): ?>
    <div class="alert alert-warning animate-rise">
        <?= ui_icon('alert', 16) ?> The database is missing the holiday schema. Run
        <a href="db_migrate_holiday.php"><strong>Database Migration</strong></a> to enable invites &amp; referral codes.
    </div>
<?php else: ?>

    <div class="card animate-rise d1">
        <div class="card-title"><h3 style="margin-top:0;"><?= ui_icon('bell', 18) ?> Friend Invites</h3></div>
        <?php if (count($invites) === 0): ?>
            <div class="empty">
                <div class="empty-icon"><?= ui_icon('users', 40) ?></div>
                <div class="empty-title">No invites yet</div>
                <p class="small" style="margin:0;">Students haven't submitted any friend invites yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Friend</th>
                            <th>Friend Phone</th>
                            <th>Status</th>
                            <th>Invited</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invites as $inv): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($inv['student_name'] ?? 'Unknown') ?></strong><br>
                                    <span class="small text-muted"><?= htmlspecialchars($inv['student_email'] ?? '') ?></span>
                                </td>
                                <td><?= htmlspecialchars($inv['friend_name']) ?></td>
                                <td><?= htmlspecialchars($inv['friend_phone']) ?></td>
                                <td>
                                    <?php $st = $inv['status'] ?? 'invited'; ?>
                                    <?php if ($st === 'rewarded'): ?>
                                        <span class="badge badge-gold">Rewarded</span>
                                        <?php if (!empty($inv['rewarded_at'])): ?>
                                            <div class="small text-muted" style="margin-top:3px;"><?= date('d M Y', strtotime($inv['rewarded_at'])) ?></div>
                                        <?php endif; ?>
                                    <?php elseif ($st === 'joined'): ?>
                                        <span class="badge badge-blue">Joined</span>
                                    <?php else: ?>
                                        <span class="badge badge-grey">Invited</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('d M Y H:i', strtotime($inv['created_at'])) ?></td>
                                <td>
                                    <?php if ($st !== 'rewarded'): ?>
                                        <form method="POST" onsubmit="return confirm('Confirm that <?= htmlspecialchars(addslashes($inv['friend_name'])) ?> registered and started learning? The inviting student will earn a 15% discount on next term fees.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                                            <button class="btn btn-sm btn-gold" type="submit"><?= ui_icon('gift', 14) ?> Reward Inviter</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="small text-muted">15% off granted</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card animate-rise d2" style="margin-top:18px;">
        <div class="card-title"><h3 style="margin-top:0;"><?= ui_icon('gift', 18) ?> Student Referral Codes</h3></div>
        <?php if (count($students) === 0): ?>
            <div class="empty">
                <div class="empty-icon"><?= ui_icon('users', 40) ?></div>
                <div class="empty-title">No students yet</div>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Referral Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td class="small text-muted"><?= htmlspecialchars($s['email']) ?></td>
                                <td><code><?= htmlspecialchars($s['referral_code'] ?: '—') ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card animate-rise d3" style="margin-top:18px;">
        <div class="card-title"><h3 style="margin-top:0;"><?= ui_icon('check-circle', 18) ?> Students with a 15% Next-Term Discount</h3></div>
        <?php if (count($discounted) === 0): ?>
            <div class="empty">
                <div class="empty-icon"><?= ui_icon('gift', 40) ?></div>
                <div class="empty-title">No rewards yet</div>
                <p class="small" style="margin:0;">When an invited friend registers and starts learning, the inviting student appears here.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Discount Applies To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($discounted as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td class="small text-muted"><?= htmlspecialchars($s['email']) ?></td>
                                <td><span class="badge badge-gold">15% off <?= htmlspecialchars($s['discount_term'] ?: 'next term') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<div style="margin-top:16px;">
    <a class="btn btn-ghost" href="holiday_settings.php"><?= ui_icon('arrow-left', 16) ?> Back to Holiday Settings</a>
</div>

<?php ui_page_end(); ?>

</body>
</html>
