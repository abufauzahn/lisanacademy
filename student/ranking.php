<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('student');

$logged_in_id = (int)$_SESSION['user_id'];

/* ----------------------------------
   FETCH STUDENT REQUEST COUNTS
----------------------------------- */
$result = $conn->query("
    SELECT 
        u.id,
        u.name,
        COUNT(l.id) AS total_requests
    FROM users u
    LEFT JOIN lessons l ON l.student_id = u.id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY total_requests DESC, u.name ASC
");

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

/* ----------------------------------
   DETERMINE TOP STATUS
----------------------------------- */
$top_requests = $students[0]['total_requests'] ?? 0;

$top_count = 0;
foreach ($students as $s) {
    if ($s['total_requests'] == $top_requests) {
        $top_count++;
    }
}

$show_star = ($top_requests > 0 && $top_count === 1);
$top_student = $show_star ? $students[0] : null;
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Ranking</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('student', 'ranking', 'Ranking', 'Leaderboard'); ?>

<!-- STAR CARD -->
<div class="quran-star-card animate-rise" onclick="toggleRanking()">

<?php if ($show_star): ?>
    <div class="quran-star-content">
        <h3><?= ui_icon('star', 20) ?> Real Qur’an Companion</h3>
        <p class="big"><?= htmlspecialchars($top_student['name']) ?></p>
        <span><?= (int)$top_student['total_requests'] ?> lesson requests</span>
    </div>
<?php else: ?>
    <div class="quran-star-content">
        <h3><?= ui_icon('book-open', 20) ?> Qur’an Reminder</h3>
        <p class="quran-reminder">
          “The most beloved deeds to Allah are those done consistently,
          even if they are small.”
        </p>
        <span><?= ui_icon('sprout', 14) ?> Be the one who takes the lead</span>
    </div>
<?php endif; ?>

</div>

<!-- RANKING LIST -->
<div id="rankingBox" style="display:none;">

<div class="table-wrap animate-rise d1">
<table class="table">
    <thead>
        <tr>
            <th>Rank</th>
            <th>Student</th>
            <th>Requests</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $position = 1;
    foreach ($students as $s):
        $is_you = ($s['id'] === $logged_in_id);
    ?>
        <tr <?= $is_you ? 'style="background:var(--emerald-50);"' : '' ?>>
            <td>
                <span class="badge <?= $position === 1 ? 'badge-gold' : 'badge-grey' ?>"><?= $position === 1 ? ui_icon('trophy', 14) : '#' . $position ?></span>
            </td>
            <td>
                <strong><?= htmlspecialchars($s['name']) ?></strong>
                <?php if ($is_you): ?>
                    <span class="badge badge-green" style="margin-left:6px;">You</span>
                <?php endif; ?>
            </td>
            <td><span class="badge badge-green"><?= (int)$s['total_requests'] ?></span></td>
        </tr>
    <?php $position++; endforeach; ?>
    </tbody>
</table>
</div>

<div class="panel text-center" style="margin-top:16px;">
  Rankings update automatically based on lesson requests.<br>
  Consistency is more beloved than competition <?= ui_icon('heart', 14) ?>
</div>
</div>

<?php ui_page_end(); ?>

<script>
function toggleRanking(){
  const box = document.getElementById('rankingBox');
  box.style.display = box.style.display === 'block' ? 'none' : 'block';
}
</script>

</body>
</html>