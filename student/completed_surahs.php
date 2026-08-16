<?php
require '../config/security/helpers.php';
require_role('student');
include '../auth/auth_check.php';
include '../config/db.php';

$id = intval($_SESSION['user_id']);

// Fetch completed surahs for this student
$stmt = $conn->prepare("
    SELECT s.id AS surah_id, s.name_en AS surah, s.name_ar, s.total_verses AS verses
    FROM student_learning sl
    JOIN surahs s ON s.id = sl.surah_id
    WHERE sl.student_id = ? AND sl.status = 'completed'
    ORDER BY s.id ASC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Completed Surahs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= ui_css() ?>
</head>
<?php ui_page_start('student', 'learning', 'Completed Surahs', 'Achievements'); ?>

<div class="page-hero animate-rise">
    <h1>Completed Surahs</h1>
    <p>Your journey, one surah at a time — may Allah bless your progress.</p>
    <?php if ($result->num_rows > 0): ?>
        <a class="btn btn-gold mt-2" href="certificate.php"><?= ui_icon('gem', 16) ?> View / Download Certificate</a>
    <?php endif; ?>
</div>

<?php if($result->num_rows > 0): ?>
    <div class="table-wrap animate-rise d1">
    <table class="table">
        <thead>
            <tr>
                <th>Surah</th>
                <th>Verses</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['surah']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['verses']); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <div class="empty animate-rise">
        <div class="empty-icon"><?= ui_icon('book', 40) ?></div>
        <div class="empty-title">No surahs completed yet</div>
        <p class="small" style="margin:0;">Start your first surah in My Learning.</p>
    </div>
<?php endif; ?>

<?php ui_page_end(); ?>

</body>
</html>