<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

$holiday_keys = [
    'holiday_mode', 'holiday_started_at', 'holiday_duration_days',
    'holiday_ends_at', 'holiday_resumption_date', 'holiday_message',
];

function holiday_schema_missing($conn, $keys) {
    $missing = [];
    foreach ($keys as $k) {
        try {
            $stmt = $conn->prepare("SELECT setting_key FROM app_settings WHERE setting_key = ? LIMIT 1");
            $stmt->bind_param("s", $k);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) $missing[] = "app_settings.$k";
        } catch (Throwable $e) {
            $missing[] = "app_settings.$k";
        }
    }
    if (!db_table_exists($conn, 'donations'))       $missing[] = 'donations table';
    if (!db_table_exists($conn, 'student_invites')) $missing[] = 'student_invites table';
    if (!db_column_exists($conn, 'users', 'referral_code')) $missing[] = 'users.referral_code';
    return $missing;
}

$schema_ready = empty(holiday_schema_missing($conn, $holiday_keys));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'activate' && !$schema_ready) {
        redirect('db_migrate_holiday.php');
    }

    if ($action === 'activate') {
        $days   = (int)($_POST['days'] ?? 0);
        if ($days < 1 || $days > 90) $days = 14;
        $resume = trim($_POST['resumption_date'] ?? '');
        $msg    = trim($_POST['message'] ?? '');

        $set = function ($key, $val) use ($conn) {
            $val = $conn->real_escape_string($val);
            $conn->query("INSERT INTO app_settings (setting_key, setting_value) VALUES ('$key', '$val')
                          ON DUPLICATE KEY UPDATE setting_value = '$val'");
        };
        $set('holiday_mode',            'on');
        $set('holiday_started_at',      date('Y-m-d H:i:s'));
        $set('holiday_duration_days',   (string)$days);
        $set('holiday_ends_at',         date('Y-m-d H:i:s', strtotime("+$days days")));
        $set('holiday_resumption_date', $resume);
        $set('holiday_message',         $msg);
        redirect('holiday_settings.php?activated=1');
    } elseif ($action === 'deactivate') {
        try {
            $conn->query("INSERT INTO app_settings (setting_key, setting_value) VALUES ('holiday_mode', 'off')
                          ON DUPLICATE KEY UPDATE setting_value = 'off'");
        } catch (Throwable $e) { /* ignore */ }
        redirect('holiday_settings.php?deactivated=1');
    }
}

$holiday = holiday_mode_on($conn);
$info    = holiday_info($conn);
$days_left = holiday_days_left($conn);
$resume  = holiday_resumption_label($conn);

/* Stats */
$donations = 0; $invites = 0;
try {
    $donations = (int)$conn->query("SELECT COUNT(*) c FROM donations")->fetch_assoc()['c'];
} catch (Throwable $e) { $donations = 0; }
try {
    $invites = (int)$conn->query("SELECT COUNT(*) c FROM student_invites")->fetch_assoc()['c'];
} catch (Throwable $e) { $invites = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Holiday Settings</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('admin', 'holiday', 'Holiday Settings', 'Holiday'); ?>

<div class="page-hero animate-rise">
    <h1>Holiday Mode Control</h1>
    <p>Activate or deactivate holiday mode. While active, students are locked out of learning and see a holiday notice page.</p>
</div>

<?php if (!$schema_ready): ?>
    <div class="alert alert-warning animate-rise">
        <?= ui_icon('alert', 16) ?> The database is missing the holiday schema. Run
        <a href="db_migrate_holiday.php"><strong>Database Migration</strong></a> before activating holiday mode.
    </div>
<?php endif; ?>

<?php if (isset($_GET['activated']) && $_GET['activated'] === '1'): ?>
    <div class="alert alert-success animate-rise">
        <?= ui_icon('check-circle', 16) ?>
        <span style="flex:1;"><strong>Holiday mode activated.</strong> Students are now on the holiday notice page. It will turn off automatically when the duration ends (or you can deactivate it early).</span>
    </div>
<?php endif; ?>

<?php if (isset($_GET['deactivated']) && $_GET['deactivated'] === '1'): ?>
    <div class="alert alert-success animate-rise">
        <?= ui_icon('check-circle', 16) ?>
        <span style="flex:1;"><strong>Holiday mode deactivated.</strong> Students can now use the academy normally.</span>
    </div>
<?php endif; ?>

<div class="stat-grid animate-rise d1">
    <div class="stat-card <?= $holiday ? 'stat-gold' : 'stat-green' ?>">
        <span class="stat-ico"><?= ui_icon('clock') ?></span>
        <span class="stat-label">Holiday Mode</span>
        <span class="stat-value"><?= $holiday ? 'ON' : 'OFF' ?></span>
        <?php if ($holiday): ?>
            <span class="stat-sub">Started <?= $info['started_at'] ? date('d M Y H:i', strtotime($info['started_at'])) : '—' ?> · <?= $days_left !== null ? "~$days_left day(s) left" : '' ?></span>
        <?php else: ?>
            <span class="stat-sub">Students follow their normal lessons</span>
        <?php endif; ?>
    </div>
    <div class="stat-card stat-gold">
        <span class="stat-ico"><?= ui_icon('gift') ?></span>
        <span class="stat-label">Donation Requests</span>
        <span class="stat-value"><?= $donations ?></span>
        <span class="stat-sub">Recorded so far</span>
    </div>
    <div class="stat-card stat-blue">
        <span class="stat-ico"><?= ui_icon('users') ?></span>
        <span class="stat-label">Friend Invites</span>
        <span class="stat-value"><?= $invites ?></span>
        <span class="stat-sub">Submitted by students</span>
    </div>
</div>

<div class="card animate-rise d1" style="max-width:620px;">
    <h3 style="margin-top:0;"><?= $holiday ? 'Deactivate Holiday Mode' : 'Activate Holiday Mode' ?></h3>

    <?php if ($holiday): ?>
        <p class="small text-muted">
            While active, students <strong>cannot request lessons, recite, take exams or use their dashboard</strong> —
            they see the holiday notice page where they can download results / certificates, invite friends, donate and
            arrange next-term fees. Holiday mode turns off automatically on
            <strong><?= htmlspecialchars($resume !== '' ? $resume : '—') ?></strong>
            or when you deactivate it now.
        </p>
        <form method="POST" onsubmit="return confirm('Deactivate holiday mode now? Students will regain full access immediately.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="deactivate">
            <button class="btn btn-danger btn-lg"><?= ui_icon('close', 17) ?> Deactivate Holiday Mode Now</button>
        </form>
    <?php else: ?>
        <p class="small text-muted">
            Activating will immediately lock students out of learning and send them to the holiday notice page.
            Set how long the holiday lasts — the system <strong>deactivates it automatically</strong> once that duration is
            reached. You can also add a resumption date and a message shown to students.
        </p>
        <form method="POST" onsubmit="return confirm('Activate holiday mode now? Students will be locked out of learning immediately.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="activate">
            <div class="form-group">
                <label class="form-label" for="days">Holiday Duration (days)</label>
                <input class="form-input" type="number" id="days" name="days" min="1" max="90" value="14" required>
                <span class="small text-muted">Holiday mode auto-deactivates after this many days.</span>
            </div>
            <div class="form-group">
                <label class="form-label" for="resumption_date">Resumption Date (optional)</label>
                <input class="form-input" type="date" id="resumption_date" name="resumption_date">
                <span class="small text-muted">Shown to students as the day classes resume.</span>
            </div>
            <div class="form-group">
                <label class="form-label" for="message">Message to Students (optional)</label>
                <textarea class="form-textarea" id="message" name="message" rows="3" placeholder="e.g. Enjoy the break and stay close to the Qur'an. We resume on 1st September."></textarea>
            </div>
            <button class="btn btn-gold btn-lg"><?= ui_icon('check', 17) ?> Activate Holiday Mode</button>
        </form>
    <?php endif; ?>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
        <a class="btn btn-ghost" href="invites.php"><?= ui_icon('users', 16) ?> Invites &amp; Referral Codes</a>
        <a class="btn btn-ghost" href="db_migrate_holiday.php"><?= ui_icon('refresh', 16) ?> Schema Migration</a>
    </div>
</div>

<?php ui_page_end(); ?>

</body>
</html>
