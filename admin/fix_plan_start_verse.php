<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

/* ============================================================
   One-off admin utility: fix a plan's start_verse.
   When a student's plan was created before student_learning.start_verse
   existed (or the column was missing), the plan silently began at verse 1
   and a lesson was requested from the wrong place. This tool lets the admin
   set the correct starting verse for a plan and removes any lesson that was
   requested but never recited (so the next request is regenerated from the
   correct verse). Lessons that already have a recitation are left untouched.
============================================================ */

$done = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $student_id = (int)($_POST['student_id'] ?? 0);
    $surah_id   = (int)($_POST['surah_id'] ?? 0);
    $start_verse = (int)($_POST['start_verse'] ?? 0);

    if ($student_id <= 0 || $surah_id <= 0 || $start_verse < 1) {
        $done = ['error', 'Invalid input. Please choose a student and a valid starting verse.'];
    } else {
        $t = $conn->prepare("SELECT total_verses FROM surahs WHERE id = ?");
        $t->bind_param("i", $surah_id);
        $t->execute();
        $total_verses = (int)($t->get_result()->fetch_assoc()['total_verses'] ?? 0);

        if ($total_verses > 0 && $start_verse > $total_verses) {
            $done = ['error', "Starting verse $start_verse is beyond the surah's $total_verses verses."];
        } else {
            $conn->begin_transaction();
            try {
                $u = $conn->prepare("
                    UPDATE student_learning
                    SET start_verse = ?
                    WHERE student_id = ? AND surah_id = ?
                ");
                $u->bind_param("iii", $start_verse, $student_id, $surah_id);
                $u->execute();
                $updated_plan = $conn->affected_rows;

                /* Delete lessons for this surah that were requested but never
                   recited (no student_recitation row at all). Lessons that
                   already have a recitation are kept — the admin handles those
                   through the normal review flow. */
                $del = $conn->prepare("
                    DELETE l FROM lessons l
                    WHERE l.student_id = ? AND l.surah_id = ?
                      AND NOT EXISTS (
                          SELECT 1 FROM student_recitation sr
                          WHERE sr.learning_plan_id = l.id
                      )
                ");
                $del->bind_param("ii", $student_id, $surah_id);
                $del->execute();
                $deleted_lessons = $conn->affected_rows;

                $conn->commit();

                $parts = [];
                if ($updated_plan > 0) $parts[] = "plan updated to start at verse $start_verse";
                if ($deleted_lessons > 0) $parts[] = "$deleted_lessons never-recited lesson(s) removed";
                $msg = $parts
                    ? 'Done — ' . implode(', ', $parts) . '. The student can now request the next portion and it will begin at the corrected verse.'
                    : 'No matching active plan was updated (check the student/surah and the plan status).';

                $done = ['success', $msg];
            } catch (Throwable $e) {
                $conn->rollback();
                $done = ['error', 'Update failed: ' . $e->getMessage()];
            }
        }
    }
}

$has_start_verse = db_ensure_start_verse_column($conn);

/* List active plans with their current start verse and lesson overview. */
$plans = $conn->query("
    SELECT sl.id, sl.student_id, u.name, u.email,
           sl.surah_id, s.name_en AS surah_name, s.total_verses,
           sl.verses_per_request"
           . ($has_start_verse ? ", sl.start_verse" : ", 1 AS start_verse") . ",
           sl.completed_requests, sl.status,
           (SELECT COUNT(*) FROM lessons l WHERE l.student_id = sl.student_id AND l.surah_id = sl.surah_id) AS lesson_count
    FROM student_learning sl
    JOIN users u ON u.id = sl.student_id
    JOIN surahs s ON s.id = sl.surah_id
    WHERE sl.status = 'active'
    ORDER BY u.name ASC, sl.surah_id ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fix Plan Start Verse</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('admin', 'dashboard', 'Fix Plan Start Verse', 'Utilities'); ?>

<div class="page-hero animate-rise">
    <h1>Fix Plan Start Verse</h1>
    <p>Set the correct starting verse for a student&rsquo;s active plan and clear any lesson that was requested from the wrong place.</p>
</div>

<?php if ($done): ?>
    <div class="alert alert-<?= $done[0] === 'success' ? 'success' : 'danger' ?> animate-rise">
        <?= ui_icon($done[0] === 'success' ? 'check-circle' : 'close', 16) ?>
        <span style="flex:1;"><?= $done[1] ?></span>
    </div>
<?php endif; ?>

<?php if (!$has_start_verse): ?>
    <div class="alert alert-warning animate-rise">
        <?= ui_icon('alert', 16) ?>
        <span style="flex:1;">The <code>student_learning.start_verse</code> column is missing. It will be created automatically when a student starts a new plan (or run <a href="db_migrate07.php">db_migrate07</a>).</span>
    </div>
<?php endif; ?>

<div class="card animate-rise d1" style="max-width:980px;">
    <h3 style="margin-top:0;"><?= ui_icon('book', 18) ?> Active Plans</h3>
    <?php if ($plans && $plans->num_rows > 0): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Surah</th>
                        <th>Verses / req</th>
                        <th>Current start</th>
                        <th>Lessons</th>
                        <th style="min-width:230px;">Correct start verse</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $plans->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($p['name']) ?>
                                <div class="small text-muted"><?= htmlspecialchars($p['email']) ?></div>
                            </td>
                            <td>
                                <?= htmlspecialchars($p['surah_name']) ?>
                                <div class="small text-muted"><?= (int)$p['total_verses'] ?> verses</div>
                            </td>
                            <td><?= (int)$p['verses_per_request'] ?></td>
                            <td>
                                <span class="badge badge-<?= (int)$p['start_verse'] === 1 ? 'red' : 'green' ?>"><?= (int)$p['start_verse'] ?></span>
                            </td>
                            <td><?= (int)$p['lesson_count'] ?></td>
                            <td>
                                <form method="POST" action="fix_plan_start_verse.php" style="display:flex;gap:8px;align-items:center;margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="student_id" value="<?= (int)$p['student_id'] ?>">
                                    <input type="hidden" name="surah_id" value="<?= (int)$p['surah_id'] ?>">
                                    <input class="form-input" type="number" name="start_verse" min="1" max="<?= (int)$p['total_verses'] ?>" value="<?= (int)$p['start_verse'] ?>" required style="width:90px;">
                                    <button class="btn btn-sm" type="submit"><?= ui_icon('check', 14) ?> Apply</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted" style="margin:10px 0 0;">
            <?= ui_icon('info', 14) ?> Applying a start verse also removes any lesson for that surah that was requested but never recited, so the next request is generated from the corrected verse. Lessons that already have a recitation are left untouched.
        </p>
    <?php else: ?>
        <div class="empty">
            <div class="empty-icon"><?= ui_icon('check-circle', 40) ?></div>
            <div class="empty-title">No active plans</div>
            <p class="small" style="margin:0;">Nothing to fix right now.</p>
        </div>
    <?php endif; ?>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
        <a class="btn btn-ghost" href="dashboard.php"><?= ui_icon('grid', 16) ?> Dashboard</a>
    </div>
</div>

<?php ui_page_end(); ?>
</body>
</html>
