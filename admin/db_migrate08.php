<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

/* ============ CURRENT SCHEMA STATUS ============ */
$missing = [];

if (!db_column_exists($conn, 'users', 'exam_selected')) $missing[] = 'users.exam_selected';

$pending = count($missing);

/* ============ RUN MIGRATION ============ */
$steps = [];
$ran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ran = true;

    /* users.exam_selected — marks a student as a qualified participant for the
       CURRENT exam term. Admin picks the list before/while exam mode runs;
       non-selected students keep following normal lessons. */
    if (!db_column_exists($conn, 'users', 'exam_selected')) {
        try {
            $conn->query("ALTER TABLE users ADD COLUMN exam_selected TINYINT(1) NOT NULL DEFAULT 0 AFTER exam_access");
            $steps[] = ['users.exam_selected', 'added'];
        } catch (Throwable $e) {
            $steps[] = ['users.exam_selected', 'ERROR — ' . $e->getMessage()];
        }
    } else {
        $steps[] = ['users.exam_selected', 'already present'];
    }
}

$missing_after = [];
if (!db_column_exists($conn, 'users', 'exam_selected')) $missing_after[] = 'users.exam_selected';
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
    <h1>Database Migration — Exam Participants Selection</h1>
    <p>Adds the <code>users.exam_selected</code> column, which lets the admin choose which students are qualified to participate in each exam term. Safe to run again — it checks whether the column already exists before touching it.</p>
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

        <?php if (empty($missing_after)): ?>
            <div class="alert alert-success" style="margin-top:12px;"><?= ui_icon('check-circle', 16) ?> All schema objects are now in place. Go to <a href="exam_settings.php">Exam Settings</a> to pick which students participate in the current exam.</div>
        <?php else: ?>
            <div class="alert alert-warning" style="margin-top:12px;"><?= ui_icon('alert', 16) ?> Still missing: <?= htmlspecialchars(implode(', ', $missing_after)) ?>. Review the errors above and retry.</div>
        <?php endif; ?>
    </div>
<?php else: ?>

    <div class="card animate-rise d1" style="max-width:760px;">
        <h3 style="margin-top:0;">Current Schema Status</h3>
        <?php if ($pending === 0): ?>
            <div class="alert alert-success" style="margin-top:0;"><?= ui_icon('check-circle', 16) ?> Nothing to do — the database already has the <code>users.exam_selected</code> column.</div>
        <?php else: ?>
            <div class="alert alert-warning" style="margin-top:0;"><?= ui_icon('alert', 16) ?> <strong><?= $pending ?></strong> object(s) missing: <code><?= htmlspecialchars(implode('</code>, <code>', $missing)) ?></code></div>
            <p class="small text-muted">
                <strong>users.exam_selected</strong> is what the new "select qualified exam participants" flow relies on.
                Until it is added, every student stays <em>unselected</em> (so nobody is blocked during an exam term).
            </p>
            <form method="POST" onsubmit="return confirm('Run the exam-participants schema migration now?');">
                <?= csrf_field() ?>
                <button class="btn btn-gold btn-lg" type="submit"><?= ui_icon('refresh', 17) ?> Run Migration</button>
            </form>
        <?php endif; ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
            <a class="btn btn-ghost" href="exam_settings.php"><?= ui_icon('clock', 16) ?> Exam Settings</a>
            <a class="btn btn-ghost" href="dashboard.php"><?= ui_icon('grid', 16) ?> Dashboard</a>
        </div>
    </div>

<?php endif; ?>

<?php ui_page_end(); ?>

</body>
</html>