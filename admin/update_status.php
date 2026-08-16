<?php
require '../config/security/helpers.php';
require_role('admin');
require '../auth/auth_check.php';
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('teaching.php');
}

$rec_id = intval($_POST['rec_id'] ?? 0);
$status = ($_POST['status'] ?? '') === 'accepted' ? 'accepted' : 'rejected';

if ($rec_id <= 0) {
    ui_message_page('danger', 'Invalid Request', 'Missing recitation ID.', 'teaching.php', 'Back to Teaching Dashboard', 'close');
}

$stmt = $conn->prepare("UPDATE student_recitation SET status=? WHERE id=?");
$stmt->bind_param("si", $status, $rec_id);
if ($stmt->execute()) {
    $ok = $status === 'accepted';
    ui_message_page(
        $ok ? 'success' : 'danger',
        $ok ? 'Recitation Accepted' : 'Recitation Rejected',
        'The recitation status was updated to <strong>' . htmlspecialchars($status) . '</strong>.',
        'teaching.php',
        'Back to Teaching Dashboard',
        $ok ? 'check-circle' : 'close'
    );
}
ui_message_page('danger', 'Update Failed', 'The recitation status could not be updated. Please try again.', 'teaching.php', 'Back to Teaching Dashboard', 'close');
?>