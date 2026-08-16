<?php
require '../config/security/helpers.php';
require_role('student');
include '../auth/auth_check.php';
require '../config/db.php';

$student_id = (int)$_SESSION['user_id'];

// Fetch latest announcements from the database
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");

// Mark announcements as seen for this student
if ($announcements && $announcements->num_rows > 0) {
    while($row = $announcements->fetch_assoc()) {
        $announcement_id = (int)$row['id'];
        // Insert a new read record or update if it already exists
        $conn->query("
            INSERT INTO announcement_reads (announcement_id, student_id, seen)
            VALUES ($announcement_id, $student_id, 1)
            ON DUPLICATE KEY UPDATE seen=1
        ");
    }
    // Re-fetch announcements so we can display them normally
    $announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Announcements - Student Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= ui_css() ?>
</head>
<?php ui_page_start('student', 'announcements', 'Announcements', 'Updates'); ?>

<div class="page-hero animate-rise">
    <h1>Announcements</h1>
    <p>Stay up to date with the latest from your academy.</p>
</div>

<div style="margin-bottom:20px;">
    <a class="btn btn-gold" href="suggestions.php"><?= ui_icon('bulb', 16) ?> Suggestions &amp; Advice</a>
</div>

<?php if ($announcements && $announcements->num_rows > 0): ?>
    <?php while($row = $announcements->fetch_assoc()): ?>
        <div class="card animate-rise d1">
            <div class="card-title">
                <h3 style="margin:0;"><?php echo htmlspecialchars($row['title']); ?></h3>
            </div>
            <p style="margin:0 0 10px;"><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
            <span class="small text-muted"><?php echo date("d M Y H:i", strtotime($row['created_at'])); ?></span>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="empty animate-rise">
        <div class="empty-icon"><?= ui_icon('bell', 40) ?></div>
        <div class="empty-title">No announcements yet</div>
    </div>
<?php endif; ?>

<?php ui_page_end(); ?>

</body>
</html>