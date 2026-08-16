<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

/* ============ HANDLE PAYMENT CONFIRMATION ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $student_id = (int)($_POST['student_id'] ?? 0);
    if ($student_id > 0 && users_has_exam_columns($conn)) {
        $stmt = $conn->prepare("UPDATE users SET exam_owed = 0, exam_access = 1, exam_paid_at = NOW() WHERE id = ? AND role = 'student'");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
    }
    redirect('exam_defaults.php');
}

/* ============ DEFAULTING STUDENTS ============ */
$schema_ready = users_has_exam_columns($conn) && exam_attempts_has_exam_columns($conn);
$defaulters = null;
if ($schema_ready) {
    $defaulters = $conn->query("
        SELECT u.id, u.name, u.email, u.exam_defaulted, u.exam_owed, u.exam_access, u.exam_paid_at,
               ea.status AS last_status, ea.mistakes_count AS last_mistakes
        FROM users u
        LEFT JOIN exam_attempts ea ON ea.id = (
            SELECT ea2.id FROM exam_attempts ea2 WHERE ea2.student_id = u.id ORDER BY ea2.id DESC LIMIT 1
        )
        WHERE u.role = 'student' AND u.exam_defaulted = 1
        ORDER BY u.name ASC
    ");
}

$term = exam_term_info($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exam Defaulters &amp; Payments</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('admin', 'exams', 'Exam Defaulters & Payments', 'Exams'); ?>

<div class="page-hero animate-rise">
    <h1>Defaulters &amp; Payments</h1>
    <p>Students who did not pass the exam term before it closed. Those who never submitted owe the N500 fee to reopen their exam; those who submitted but were rejected retake for free.</p>
</div>

<div class="card animate-rise d1" style="max-width:640px;">
    <p class="small" style="margin:0;">
        <strong>How it works:</strong>
        When an exam term closes, every student without an accepted result is locked from normal lessons.
        Students who <strong>never submitted</strong> owe the <strong>N500</strong> fee — after it is confirmed here,
        the exam opens for <strong>that student only</strong>. Students who <strong>did participate</strong> but were
        rejected retake for free. Once a retaken exam is <strong>accepted (3 mistakes or fewer)</strong>,
        the student automatically resumes normal lessons.
    </p>
    <?php if ($term && empty($term['deactivated_at'])): ?>
        <div class="alert alert-info" style="margin-top:12px;"><?= ui_icon('info', 15) ?> The exam term is <strong>still active</strong>. Students appear here once the term closes (10 days or manual deactivation) or once their submission is rejected.</div>
    <?php endif; ?>
</div>

<?php if (!$schema_ready): ?>
    <div class="alert alert-warning animate-rise">
        <?= ui_icon('alert', 16) ?> The database is missing the exam default/payment columns. Run
        <a href="db_migrate.php"><strong>Database Migration</strong></a> first — this page cannot be shown safely until then.
    </div>
<?php elseif ($defaulters->num_rows === 0): ?>
    <div class="empty animate-rise">
        <div class="empty-icon"><?= ui_icon('check-circle', 40) ?></div>
        <div class="empty-title">No defaulters right now</div>
        <p class="small" style="margin:0;">Students who miss the exam term will appear here after it closes.</p>
    </div>
<?php else: ?>
    <div class="table-wrap animate-rise d1">
        <table class="table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Status</th>
                    <th>Last Exam</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($s = $defaulters->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($s['name']) ?></strong><br>
                        <span class="small text-muted"><?= htmlspecialchars($s['email']) ?></span>
                    </td>
                    <td>
                        <?php if ($s['exam_owed']): ?>
                            <span class="badge badge-gold">Owes N500</span>
                        <?php elseif ($s['exam_access'] && !$s['exam_paid_at']): ?>
                            <span class="badge badge-blue">Retaking (free)</span>
                            <div class="small text-muted" style="margin-top:3px;">Participated but not passed yet</div>
                        <?php elseif ($s['exam_access']): ?>
                            <span class="badge badge-blue">Paid · Exam Open</span>
                            <?php if ($s['exam_paid_at']): ?>
                                <div class="small text-muted" style="margin-top:3px;">Paid <?= date('d M Y', strtotime($s['exam_paid_at'])) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge-red">Locked</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['last_status'] === 'draft'): ?>
                            <span class="badge badge-blue">Started · not finished</span>
                        <?php elseif ($s['last_status'] === 'rejected'): ?>
                            <span class="badge badge-red">Retake (<?= (int)$s['last_mistakes'] ?> mistakes)</span>
                        <?php elseif ($s['last_status']): ?>
                            <span class="small text-muted"><?= htmlspecialchars($s['last_status']) ?></span>
                        <?php else: ?>
                            <span class="small text-muted">No attempt</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['exam_owed']): ?>
                            <form method="POST" onsubmit="return confirm('Confirm payment of N500 for <?= htmlspecialchars(addslashes($s['name'])) ?>? The exam will open for them only.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-sm btn-gold" type="submit"><?= ui_icon('check', 14) ?> Payment Received → Open Exam</button>
                            </form>
                        <?php else: ?>
                            <span class="small text-muted">Awaiting accepted result</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div style="margin-top:16px;">
    <a class="btn btn-ghost" href="exam_settings.php"><?= ui_icon('arrow-left', 16) ?> Back to Exam Settings</a>
</div>

<?php ui_page_end(); ?>

</body>
</html>