<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

/* ============ CURRENT SCHEMA STATUS ============ */
$missing = [];

if (!db_table_exists($conn, 'applications')) $missing[] = 'applications table';
if (db_table_exists($conn, 'applications')) {
    if (!db_column_exists($conn, 'applications', 'status')) $missing[] = 'applications.status';
}

foreach (['question_count', 'total_days', 'day_no'] as $c) {
    if (!db_column_exists($conn, 'exam_attempts', $c)) $missing[] = "exam_attempts.$c";
}

foreach (['day_no', 'status', 'answered_at', 'audio_file'] as $c) {
    if (!db_column_exists($conn, 'exam_answers', $c)) $missing[] = "exam_answers.$c";
}

$status_type = db_column_type($conn, 'exam_attempts', 'status');
$status_ok   = ($status_type !== '' && stripos($status_type, 'draft') !== false);
if (!$status_ok) $missing[] = 'exam_attempts.status enum (draft)';

$pending = count($missing);

/* ============ RUN MIGRATION ============ */
$steps = [];
$ran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ran = true;

    /* 1. applications table */
    if (!db_table_exists($conn, 'applications')) {
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS applications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(100) NOT NULL,
                whatsapp_phone VARCHAR(30) NOT NULL,
                email VARCHAR(120) NULL,
                learning_info TEXT NULL,
                status ENUM('pending','contacted','payment_pending','payment_confirmed','active','rejected') NOT NULL DEFAULT 'pending',
                admin_note TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX(status),
                INDEX(created_at)
            )");
            $steps[] = ['applications table', 'created'];
        } catch (Throwable $e) {
            $steps[] = ['applications table', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['applications table', 'already present'];
    }

    /* 2. exam_attempts.question_count / total_days / day_no */
    $attempts_cols = [
        'question_count' => 'ALTER TABLE exam_attempts ADD COLUMN question_count TINYINT NULL AFTER mistakes_count',
        'total_days'     => 'ALTER TABLE exam_attempts ADD COLUMN total_days TINYINT NULL AFTER question_count',
        'day_no'         => 'ALTER TABLE exam_attempts ADD COLUMN day_no TINYINT NOT NULL DEFAULT 1 AFTER total_days',
    ];
    foreach ($attempts_cols as $col => $sql) {
        if (!db_column_exists($conn, 'exam_attempts', $col)) {
            try {
                $conn->query($sql);
                $steps[] = ["exam_attempts.$col", 'added'];
            } catch (Throwable $e) {
                $steps[] = ["exam_attempts.$col", 'ERROR — ' . $e->getMessage()];
            }
        } else {
            $steps[] = ["exam_attempts.$col", 'already present'];
        }
    }

    /* 3. exam_attempts.status enum upgrade (add draft) */
    $type = db_column_type($conn, 'exam_attempts', 'status');
    if ($type === '') {
        $steps[] = ['exam_attempts.status', 'ERROR — column missing'];
    } elseif (stripos($type, 'draft') === false) {
        try {
            $conn->query("ALTER TABLE exam_attempts MODIFY status ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft'");
            $steps[] = ['exam_attempts.status', "upgraded to ENUM('draft','pending','approved','rejected')"];
        } catch (Throwable $e) {
            $steps[] = ['exam_attempts.status', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['exam_attempts.status', 'already upgraded'];
    }

    /* 4. exam_answers.day_no / status / answered_at */
    $answers_cols = [
        'day_no'      => 'ALTER TABLE exam_answers ADD COLUMN day_no TINYINT NULL AFTER to_verse',
        'status'      => "ALTER TABLE exam_answers ADD COLUMN status ENUM('pending','submitted') NOT NULL DEFAULT 'pending' AFTER day_no",
        'answered_at' => 'ALTER TABLE exam_answers ADD COLUMN answered_at DATETIME NULL AFTER status',
        'audio_file'  => 'ALTER TABLE exam_answers ADD COLUMN audio_file VARCHAR(255) NULL AFTER answered_at',
    ];
    foreach ($answers_cols as $col => $sql) {
        if (!db_column_exists($conn, 'exam_answers', $col)) {
            try {
                $conn->query($sql);
                $steps[] = ["exam_answers.$col", 'added'];
            } catch (Throwable $e) {
                $steps[] = ["exam_answers.$col", 'ERROR — ' . $e->getMessage()];
            }
        } else {
            $steps[] = ["exam_answers.$col", 'already present'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Database Migration</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('admin', 'dashboard', 'Database Migration', 'System'); ?>

<div class="page-hero animate-rise">
    <h1>Database Migration — Applications &amp; Tiered Exams</h1>
    <p>Applies the schema update from <code>db_update06.md</code> in one click. Safe to run again — every step checks whether the column/table already exists before touching it.</p>
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
                                <?php elseif ($st[1] === 'already present' || $st[1] === 'already upgraded'): ?>
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
    if (!db_table_exists($conn, 'applications')) $missing_after[] = 'applications table';
    foreach (['question_count', 'total_days', 'day_no'] as $c) {
        if (!db_column_exists($conn, 'exam_attempts', $c)) $missing_after[] = "exam_attempts.$c";
    }
    foreach (['day_no', 'status', 'answered_at', 'audio_file'] as $c) {
        if (!db_column_exists($conn, 'exam_answers', $c)) $missing_after[] = "exam_answers.$c";
    }
    $type = db_column_type($conn, 'exam_attempts', 'status');
    if ($type !== '' && stripos($type, 'draft') === false) $missing_after[] = 'exam_attempts.status enum';
    ?>

    <?php if (empty($missing_after)): ?>
        <div class="alert alert-success" style="margin-top:12px;"><?= ui_icon('check-circle', 16) ?> All schema objects are now in place. Try <a href="applications.php">Applications</a> and the <a href="../student/exam.php">student exam</a>.</div>
    <?php else: ?>
        <div class="alert alert-warning" style="margin-top:12px;"><?= ui_icon('alert', 16) ?> Still missing: <?= htmlspecialchars(implode(', ', $missing_after)) ?>. Review the errors above and retry.</div>
    <?php endif; ?>
<?php else: ?>

    <div class="card animate-rise d1" style="max-width:760px;">
        <h3 style="margin-top:0;">Current Schema Status</h3>
        <?php if ($pending === 0): ?>
            <div class="alert alert-success" style="margin-top:0;"><?= ui_icon('check-circle', 16) ?> Nothing to do — the database already has every column and table this feature needs.</div>
        <?php else: ?>
            <div class="alert alert-warning" style="margin-top:0;"><?= ui_icon('alert', 16) ?> <strong><?= $pending ?></strong> object(s) missing: <code><?= htmlspecialchars(implode('</code>, <code>', $missing)) ?></code></div>
            <p class="small text-muted">
                These missing columns/tables are what the <strong>public registration</strong> and the <strong>tiered
                (3 / 7 / 10 question) exam</strong> features rely on. Running the migration below applies the documented
                <code>db_update06.md</code> changes safely (idempotent, re-runnable).
            </p>
            <form method="POST" onsubmit="return confirm('Run the applications + tiered-exam schema migration now?');">
                <?= csrf_field() ?>
                <button class="btn btn-gold btn-lg" type="submit"><?= ui_icon('refresh', 17) ?> Run Migration</button>
            </form>
        <?php endif; ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
            <a class="btn btn-ghost" href="dashboard.php"><?= ui_icon('grid', 16) ?> Dashboard</a>
        </div>
    </div>

<?php endif; ?>

<?php ui_page_end(); ?>

</body>
</html>
