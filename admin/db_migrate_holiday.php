<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

/* ============ CURRENT SCHEMA STATUS ============ */
$holiday_keys = [
    'holiday_mode', 'holiday_started_at', 'holiday_duration_days',
    'holiday_ends_at', 'holiday_resumption_date', 'holiday_message',
];

function holiday_setting_exists($conn, $key) {
    try {
        $stmt = $conn->prepare("SELECT setting_key FROM app_settings WHERE setting_key = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param("s", $key);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

$missing = [];
foreach ($holiday_keys as $k) {
    if (!holiday_setting_exists($conn, $k)) $missing[] = "app_settings.$k";
}
if (!db_table_exists($conn, 'donations'))        $missing[] = 'donations table';
if (!db_table_exists($conn, 'student_invites'))  $missing[] = 'student_invites table';
if (!db_column_exists($conn, 'users', 'referral_code')) $missing[] = 'users.referral_code';
if (!db_column_exists($conn, 'users', 'next_term_discount')) $missing[] = 'users.next_term_discount';
if (!db_column_exists($conn, 'users', 'discount_term')) $missing[] = 'users.discount_term';
if (db_table_exists($conn, 'student_invites')) {
    if (!db_column_exists($conn, 'student_invites', 'status'))            $missing[] = 'student_invites.status';
    if (!db_column_exists($conn, 'student_invites', 'joined_student_id')) $missing[] = 'student_invites.joined_student_id';
    if (!db_column_exists($conn, 'student_invites', 'rewarded_at'))       $missing[] = 'student_invites.rewarded_at';
}

$pending = count($missing);

/* ============ RUN MIGRATION ============ */
$steps = [];
$ran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ran = true;

    /* 1. app_settings holiday keys */
    $key_seed_sql = "INSERT INTO app_settings (setting_key, setting_value) VALUES
        ('holiday_mode', 'off'),
        ('holiday_started_at', ''),
        ('holiday_duration_days', ''),
        ('holiday_ends_at', ''),
        ('holiday_resumption_date', ''),
        ('holiday_message', '')
        ON DUPLICATE KEY UPDATE setting_value = setting_value";
    try {
        $conn->query($key_seed_sql);
        $steps[] = ['app_settings holiday keys', 'seeded'];
    } catch (Throwable $e) {
        $steps[] = ['app_settings holiday keys', 'ERROR — ' . $e->getMessage()];
    }

    /* 2. donations table */
    if (!db_table_exists($conn, 'donations')) {
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS donations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                method VARCHAR(50) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX(student_id)
            )");
            $steps[] = ['donations table', 'created'];
        } catch (Throwable $e) {
            $steps[] = ['donations table', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['donations table', 'already present'];
    }

    /* 3. student_invites table */
    if (!db_table_exists($conn, 'student_invites')) {
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS student_invites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                friend_name VARCHAR(100) NOT NULL,
                friend_phone VARCHAR(30) NOT NULL,
                status ENUM('invited','joined','rewarded') NOT NULL DEFAULT 'invited',
                joined_student_id INT NULL,
                rewarded_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX(student_id)
            )");
            $steps[] = ['student_invites table', 'created'];
        } catch (Throwable $e) {
            $steps[] = ['student_invites table', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['student_invites table', 'already present'];
    }

    /* 3b. student_invites.status */
    if (db_table_exists($conn, 'student_invites') && !db_column_exists($conn, 'student_invites', 'status')) {
        try {
            $conn->query("ALTER TABLE student_invites ADD COLUMN status ENUM('invited','joined','rewarded') NOT NULL DEFAULT 'invited' AFTER friend_phone");
            $steps[] = ['student_invites.status', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['student_invites.status', 'ERROR — ' . $e->getMessage()];
        }
    } elseif (db_table_exists($conn, 'student_invites')) {
        $steps[] = ['student_invites.status', 'already present'];
    }

    /* 3c. student_invites.joined_student_id */
    if (db_table_exists($conn, 'student_invites') && !db_column_exists($conn, 'student_invites', 'joined_student_id')) {
        try {
            $conn->query("ALTER TABLE student_invites ADD COLUMN joined_student_id INT NULL AFTER status");
            $steps[] = ['student_invites.joined_student_id', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['student_invites.joined_student_id', 'ERROR — ' . $e->getMessage()];
        }
    } elseif (db_table_exists($conn, 'student_invites')) {
        $steps[] = ['student_invites.joined_student_id', 'already present'];
    }

    /* 3d. student_invites.rewarded_at */
    if (db_table_exists($conn, 'student_invites') && !db_column_exists($conn, 'student_invites', 'rewarded_at')) {
        try {
            $conn->query("ALTER TABLE student_invites ADD COLUMN rewarded_at DATETIME NULL AFTER joined_student_id");
            $steps[] = ['student_invites.rewarded_at', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['student_invites.rewarded_at', 'ERROR — ' . $e->getMessage()];
        }
    } elseif (db_table_exists($conn, 'student_invites')) {
        $steps[] = ['student_invites.rewarded_at', 'already present'];
    }

    /* 4. users.referral_code + backfill */
    if (!db_column_exists($conn, 'users', 'referral_code')) {
        try {
            $conn->query("ALTER TABLE users ADD COLUMN referral_code VARCHAR(30) NULL UNIQUE");
            $steps[] = ['users.referral_code', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['users.referral_code', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['users.referral_code', 'already present'];
    }
    if (db_column_exists($conn, 'users', 'referral_code')) {
        try {
            $conn->query("UPDATE users SET referral_code = CONCAT('LMA-', YEAR(CURDATE()), '-', LPAD(id, 4, '0')) WHERE referral_code IS NULL");
            $steps[] = ['users.referral_code backfill', 'done'];
        } catch (Throwable $e) {
            $steps[] = ['users.referral_code backfill', 'ERROR — ' . $e->getMessage()];
        }
    }

    /* 4b. users.next_term_discount */
    if (!db_column_exists($conn, 'users', 'next_term_discount')) {
        try {
            $conn->query("ALTER TABLE users ADD COLUMN next_term_discount TINYINT NOT NULL DEFAULT 0");
            $steps[] = ['users.next_term_discount', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['users.next_term_discount', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['users.next_term_discount', 'already present'];
    }

    /* 4c. users.discount_term */
    if (!db_column_exists($conn, 'users', 'discount_term')) {
        try {
            $conn->query("ALTER TABLE users ADD COLUMN discount_term VARCHAR(60) NULL");
            $steps[] = ['users.discount_term', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['users.discount_term', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['users.discount_term', 'already present'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Holiday Mode Migration</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('admin', 'holiday', 'Holiday Mode Migration', 'System'); ?>

<div class="page-hero animate-rise">
    <h1>Database Migration — Holiday Mode</h1>
    <p>Applies the schema update from <code>db_update05.md</code> in one click. Safe to run again — every step checks whether the table/column/setting already exists before touching it.</p>
</div>

<?php if ($ran): ?>
    <div class="card animate-rise d1" style="max-width:760px;">
        <h3 style="margin-top:0;"><?= ui_icon('check-circle', 18) ?> Migration Result</h3>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Object</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($steps as $st): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($st[0]) ?></code></td>
                            <td>
                                <?php if (strncmp($st[1], 'ERROR', 5) === 0): ?>
                                    <span class="badge badge-red">Failed</span>
                                    <div class="small text-muted"><?= htmlspecialchars($st[1]) ?></div>
                                <?php elseif ($st[1] === 'already present'): ?>
                                    <span class="badge badge-grey"><?= htmlspecialchars($st[1]) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-green"><?= htmlspecialchars($st[1]) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $missing_after = [];
    foreach ($holiday_keys as $k) {
        if (!holiday_setting_exists($conn, $k)) $missing_after[] = "app_settings.$k";
    }
    if (!db_table_exists($conn, 'donations'))       $missing_after[] = 'donations table';
    if (!db_table_exists($conn, 'student_invites')) $missing_after[] = 'student_invites table';
    if (!db_column_exists($conn, 'users', 'referral_code')) $missing_after[] = 'users.referral_code';
    if (!db_column_exists($conn, 'users', 'next_term_discount')) $missing_after[] = 'users.next_term_discount';
    if (!db_column_exists($conn, 'users', 'discount_term')) $missing_after[] = 'users.discount_term';
    if (db_table_exists($conn, 'student_invites')) {
        if (!db_column_exists($conn, 'student_invites', 'status'))            $missing_after[] = 'student_invites.status';
        if (!db_column_exists($conn, 'student_invites', 'joined_student_id')) $missing_after[] = 'student_invites.joined_student_id';
        if (!db_column_exists($conn, 'student_invites', 'rewarded_at'))       $missing_after[] = 'student_invites.rewarded_at';
    }
    ?>

    <?php if (empty($missing_after)): ?>
        <div class="alert alert-success" style="margin-top:12px;"><?= ui_icon('check-circle', 16) ?> All schema objects are now in place. Try <a href="holiday_settings.php">Holiday Settings</a>.</div>
    <?php else: ?>
        <div class="alert alert-warning" style="margin-top:12px;"><?= ui_icon('alert', 16) ?> Still missing: <?= htmlspecialchars(implode(', ', $missing_after)) ?>. Review the errors above and retry.</div>
    <?php endif; ?>
<?php else: ?>

    <div class="card animate-rise d1" style="max-width:760px;">
        <h3 style="margin-top:0;">Current Schema Status</h3>
        <?php if ($pending === 0): ?>
            <div class="alert alert-success" style="margin-top:0;"><?= ui_icon('check-circle', 16) ?> Nothing to do — the database already has every table, column and setting this feature needs.</div>
        <?php else: ?>
            <div class="alert alert-warning" style="margin-top:0;"><?= ui_icon('alert', 16) ?> <strong><?= $pending ?></strong> object(s) missing: <code><?= htmlspecialchars(implode('</code>, <code>', $missing)) ?></code></div>
            <p class="small text-muted">
                These missing objects are what <strong>holiday_settings.php</strong>, <strong>holiday.php</strong>,
                <strong>invite.php</strong> and <strong>donate.php</strong> rely on. Running the migration below applies
                the documented <code>db_update05.md</code> changes safely (idempotent, re-runnable).
            </p>
            <form method="POST" onsubmit="return confirm('Run the holiday mode schema migration now?');">
                <?= csrf_field() ?>
                <button class="btn btn-gold btn-lg" type="submit"><?= ui_icon('refresh', 17) ?> Run Migration</button>
            </form>
        <?php endif; ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
            <a class="btn btn-ghost" href="holiday_settings.php"><?= ui_icon('clock', 16) ?> Back to Holiday Settings</a>
            <a class="btn btn-ghost" href="invites.php"><?= ui_icon('users', 16) ?> Invites &amp; Referral Codes</a>
        </div>
    </div>

<?php endif; ?>

<?php ui_page_end(); ?>

</body>
</html>
