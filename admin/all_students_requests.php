<?php
require_once __DIR__ . '/../config/security/session.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

// ==========================
// Fetch all students ordered by number of requests
// ==========================
$students = $conn->query("
    SELECT u.id, u.name, COUNT(l.id) AS requests_count
    FROM users u
    LEFT JOIN lessons l ON l.student_id = u.id
    WHERE u.role='student'
    GROUP BY u.id
    ORDER BY requests_count DESC, u.name ASC
");

$total_students = $students->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Students Requests</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('admin', 'dashboard', 'Student Requests', 'Ranking'); ?>

<div class="page-hero animate-rise">
    <h1>All Students Requests</h1>
    <p><?= $total_students ?> student(s) ranked by lesson requests.</p>
</div>

<div class="card animate-rise d1" style="padding:10px;">
<?php if($students && $students->num_rows > 0): ?>
    <div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Requests</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; while($row = $students->fetch_assoc()): ?>
            <tr>
                <td><span class="badge <?= $i === 1 ? 'badge-gold' : 'badge-grey' ?>"><?= $i ?></span></td>
                <td>
                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                    <?php if ($i === 1): ?><span class="badge badge-gold"><?= ui_icon('trophy', 12) ?> Top</span><?php endif; ?>
                </td>
                <td><span class="badge badge-green"><?= (int)$row['requests_count'] ?></span></td>
            </tr>
            <?php $i++; endwhile; ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <div class="empty">
        <div class="empty-icon"><?= ui_icon('users', 40) ?></div>
        <div class="empty-title">No students found</div>
        <p class="small" style="margin:0;">Students will appear here once they join.</p>
    </div>
<?php endif; ?>
</div>

<?php ui_page_end(); ?>

</body>
</html>