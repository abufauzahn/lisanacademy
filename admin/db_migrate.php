<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

/* ============ CURRENT SCHEMA STATUS ============ */
$users_missing = ['exam_defaulted', 'exam_owed', 'exam_access', 'exam_paid_at'];
$attempts_missing = ['term_id', 'mistakes_count'];

$missing = [];
foreach ($users_missing as $c) :
    if (!db_column_exists($conn, 'users', $c)) $missing[] = "users.$c";
endforeach;
foreach ($attempts_missing as $c) :
    if (!db_column_exists($conn, 'exam_attempts', $c)) $missing[] = "exam_attempts.$c";
endforeach;
if (!db_table_exists($conn, 'exam_terms')) $missing[] = 'exam_terms table';
if (db_table_exists($conn, 'exam_terms')) {
    if (!db_column_exists($conn, 'exam_terms', 'term_no'))     $missing[] = 'exam_terms.term_no';
    if (!db_column_exists($conn, 'exam_terms', 'school_year')) $missing[] = 'exam_terms.school_year';
}

$status_type = db_column_type($conn, 'exam_attempts', 'status');
$status_ok = ($status_type !== '' && stripos($status_type, 'approved') !== false);
if (!$status_ok && $status_type !== '') $missing[] = 'exam_attempts.status enum';
if (!db_column_exists($conn, 'exam_attempts', 'status')) $missing[] = 'exam_attempts.status column';

try {
    $stmt = $conn->prepare("SELECT setting_key FROM app_settings WHERE setting_key = 'current_term_id' LIMIT 1");
    $setting_exists = $stmt && $stmt->execute() && $stmt->get_result()->num_rows > 0;
} catch (Throwable $e) {
    $setting_exists = false;
}
if (!$setting_exists) $missing[] = "app_settings.current_term_id";

$pending = count($missing);

/* ============ RUN MIGRATION ============ */
$steps = [];
$ran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ran = true;

    /* 1. exam_terms table */
    if (!db_table_exists($conn, 'exam_terms')) {
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS exam_terms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                activated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                auto_close_at DATETIME NULL,
                deactivated_at DATETIME NULL,
                finalized TINYINT DEFAULT 0,
                INDEX(activated_at)
            )");
            $steps[] = ['exam_terms table', 'created'];
        } catch (Throwable $e) {
            $steps[] = ['exam_terms table', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['exam_terms table', 'already present'];
    }

    /* 1b. exam_terms.term_no (1=Jan–Apr, 2=May–Aug, 3=Sep–Dec) */
    if (db_table_exists($conn, 'exam_terms') && !db_column_exists($conn, 'exam_terms', 'term_no')) {
        try {
            $conn->query("ALTER TABLE exam_terms ADD COLUMN term_no TINYINT NULL AFTER auto_close_at");
            $steps[] = ['exam_terms.term_no', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['exam_terms.term_no', 'ERROR — ' . $e->getMessage()];
        }
    } elseif (db_table_exists($conn, 'exam_terms')) {
        $steps[] = ['exam_terms.term_no', 'already present'];
    }

    /* 1c. exam_terms.school_year */
    if (db_table_exists($conn, 'exam_terms') && !db_column_exists($conn, 'exam_terms', 'school_year')) {
        try {
            $conn->query("ALTER TABLE exam_terms ADD COLUMN school_year SMALLINT NULL AFTER term_no");
            $steps[] = ['exam_terms.school_year', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['exam_terms.school_year', 'ERROR — ' . $e->getMessage()];
        }
    } elseif (db_table_exists($conn, 'exam_terms')) {
        $steps[] = ['exam_terms.school_year', 'already present'];
    }

    /* 2. exam_attempts.term_id */
    if (!db_column_exists($conn, 'exam_attempts', 'term_id')) {
        try {
            $conn->query("ALTER TABLE exam_attempts ADD COLUMN term_id INT NULL AFTER student_id");
            $steps[] = ['exam_attempts.term_id', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['exam_attempts.term_id', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['exam_attempts.term_id', 'already present'];
    }

    /* 3. exam_attempts.mistakes_count */
    if (!db_column_exists($conn, 'exam_attempts', 'mistakes_count')) {
        try {
            $conn->query("ALTER TABLE exam_attempts ADD COLUMN mistakes_count TINYINT NULL AFTER status");
            $steps[] = ['exam_attempts.mistakes_count', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['exam_attempts.mistakes_count', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['exam_attempts.mistakes_count', 'already present'];
    }

    /* 4. exam_attempts.status enum upgrade (graded -> approved) */
    $type = db_column_type($conn, 'exam_attempts', 'status');
    if ($type === '') {
        $steps[] = ['exam_attempts.status', 'ERROR — column missing'];
    } elseif (stripos($type, 'approved') === false) {
        try {
            $conn->query("ALTER TABLE exam_attempts MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
            $conn->query("UPDATE exam_attempts SET status = 'approved' WHERE status = 'graded'");
            $steps[] = ['exam_attempts.status', "upgraded to ENUM('pending','approved','rejected') + 'graded' converted"];
        } catch (Throwable $e) {
            $steps[] = ['exam_attempts.status', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['exam_attempts.status', 'already upgraded'];
    }

    /* 5-8. users exam columns */
    $users_cols = [
        'exam_defaulted' => "ALTER TABLE users ADD COLUMN exam_defaulted TINYINT DEFAULT 0",
        'exam_owed'      => "ALTER TABLE users ADD COLUMN exam_owed TINYINT DEFAULT 0",
        'exam_access'    => "ALTER TABLE users ADD COLUMN exam_access TINYINT DEFAULT 0",
        'exam_paid_at'   => "ALTER TABLE users ADD COLUMN exam_paid_at DATETIME NULL",
    ];
    foreach ($users_cols as $col => $sql) {
        if (!db_column_exists($conn, 'users', $col)) {
            try {
                $conn->query($sql);
                $steps[] = ["users.$col", 'added'];
            } catch (Throwable $e) {
                $steps[] = ["users.$col", 'ERROR — ' . $e->getMessage()];
            }
        } else {
            $steps[] = ["users.$col", 'already present'];
        }
    }

    /* 9. app_settings.current_term_id */
    try {
        $conn->query("INSERT INTO app_settings (setting_key, setting_value) VALUES ('current_term_id', '')
                      ON DUPLICATE KEY UPDATE setting_value = setting_value");
        $steps[] = ['app_settings.current_term_id', 'seeded'];
    } catch (Throwable $e) {
        $steps[] = ['app_settings.current_term_id', 'ERROR — ' . $e->getMessage()];
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
    <h1>Database Migration — Exam Defaulters &amp; Payments</h1>
    <p>Applies the schema update from <code>db_update01.md</code> in one click. Safe to run again — every step checks whether the column/table already exists before touching it.</p>
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
    foreach ($users_missing as $c) :
        if (!db_column_exists($conn, 'users', $c)) $missing_after[] = "users.$c";
    endforeach;
    foreach ($attempts_missing as $c) :
        if (!db_column_exists($conn, 'exam_attempts', $c)) $missing_after[] = "exam_attempts.$c";
    endforeach;
    if (!db_table_exists($conn, 'exam_terms')) $missing_after[] = 'exam_terms table';
    if (db_table_exists($conn, 'exam_terms')) {
        if (!db_column_exists($conn, 'exam_terms', 'term_no'))     $missing_after[] = 'exam_terms.term_no';
        if (!db_column_exists($conn, 'exam_terms', 'school_year')) $missing_after[] = 'exam_terms.school_year';
    }
    $type = db_column_type($conn, 'exam_attempts', 'status');
    if ($type !== '' && stripos($type, 'approved') === false) $missing_after[] = 'exam_attempts.status enum';
    ?>

    <?php if (empty($missing_after)): ?>
        <div class="alert alert-success" style="margin-top:12px;"><?= ui_icon('check-circle', 16) ?> All schema objects are now in place. The exam pages should load normally — try <a href="exams.php">Exam Submissions</a> and <a href="exam_defaults.php">Defaulters &amp; Payments</a>.</div>
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
                These missing columns/tables are exactly what <strong>exam_defaults.php</strong>, <strong>exam_settings.php</strong>, and the
                <strong>exam submissions</strong> pages rely on. Running the migration below applies the documented
                <code>db_update01.md</code> changes safely (idempotent, re-runnable).
            </p>
            <form method="POST" onsubmit="return confirm('Run the exam schema migration now?');">
                <?= csrf_field() ?>
                <button class="btn btn-gold btn-lg" type="submit"><?= ui_icon('refresh', 17) ?> Run Migration</button>
            </form>
        <?php endif; ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
            <a class="btn btn-ghost" href="exam_settings.php"><?= ui_icon('clock', 16) ?> Back to Exam Settings</a>
            <a class="btn btn-ghost" href="exams.php"><?= ui_icon('notes', 16) ?> Exam Submissions</a>
        </div>
    </div>

<?php endif; ?>

<?php ui_page_end(); ?>

</body>
</html>