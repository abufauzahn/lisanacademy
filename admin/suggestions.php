<?php
require '../auth/auth_check.php';
require '../config/security/helpers.php';
require_role('admin');
require '../config/db.php';

// Mark as read
if (isset($_GET['mark_read'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$_GET['csrf_token'])) {
        http_response_code(403);
        exit('Invalid or expired session. Please go back, refresh the page and try again.');
    }
    $id = (int)$_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE suggestions SET status='read' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

// Fetch suggestions
$suggestions = mysqli_query($conn, "
    SELECT s.*, u.name, u.email 
    FROM suggestions s 
    JOIN users u ON s.student_id = u.id 
    ORDER BY s.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Suggestions</title>
<?= ui_css() ?>
</head>
<?php ui_page_start('admin', 'suggestions', 'Suggestions', 'Feedback'); ?>

<div class="page-hero animate-rise">
    <h1>Suggestions &amp; Complaints</h1>
    <p><?php echo date('l, d M Y - H:i'); ?> — what students are telling you.</p>
</div>

<?php if(mysqli_num_rows($suggestions) === 0): ?>
    <div class="empty animate-rise">
        <div class="empty-icon"><?= ui_icon('bulb', 40) ?></div>
        <div class="empty-title">No suggestions submitted yet</div>
    </div>
<?php endif; ?>

<?php while($row = mysqli_fetch_assoc($suggestions)): ?>
    <div class="card animate-rise">
        <div class="card-title" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;">
            <h3 style="margin:0;"><?php echo htmlspecialchars($row['title'] ?? 'Suggestion'); ?></h3>
            <span class="badge <?= $row['status'] === 'read' ? 'badge-grey' : 'badge-gold' ?>">Status: <?php echo htmlspecialchars($row['status']); ?></span>
        </div>

        <p class="small text-muted" style="margin:0 0 10px;">
            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
            (<?php echo htmlspecialchars($row['email']); ?>)
        </p>

        <p style="margin:0 0 14px;"><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>

        <?php if($row['status'] !== 'read'): ?>
            <a class="btn btn-sm" href="?mark_read=<?php echo (int)$row['id']; ?>&csrf_token=<?= urlencode(csrf_token()) ?>">Mark as Read</a>
        <?php endif; ?>
    </div>
<?php endwhile; ?>

<?php ui_page_end(); ?>

</body>
</html>