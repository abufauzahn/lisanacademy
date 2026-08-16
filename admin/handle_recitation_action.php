<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

$type   = $_POST['type']   ?? '';
$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$id || !$type || !$action) {
    die('Invalid request');
}

$status = ($action === 'accept') ? 'accepted' : 'rejected';

/* =========================
   AUDIO RECITATION
========================= */
if ($type === 'audio') {

    $rating  = $_POST['rating'] ?? null;
    $comment = $_POST['comment'] ?? '';

    $stmt = $conn->prepare("
        UPDATE student_recitation
        SET 
            status = ?,
            rating = ?,
            feedback = ?
        WHERE id = ?
    ");

    $stmt->bind_param("sssi", $status, $rating, $comment, $id);
    $stmt->execute();
}

/* =========================
   LIVE RECITATION
   (NO COMMENT STORED)
========================= */
if ($type === 'live') {

    $stmt = $conn->prepare("
        UPDATE live_recitation_requests
        SET status = ?
        WHERE id = ?
    ");

    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
}

header("Location: teaching.php");
exit;