<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request');
}

if (
    !isset($_POST['id'], $_POST['action']) ||
    !is_numeric($_POST['id']) ||
    !in_array($_POST['action'], ['accepted', 'rejected'], true)
) {
    exit('Invalid data');
}

$live_id = (int)$_POST['id'];
$action  = $_POST['action'];

// --- 1. Update live_recitation_requests table ---
$stmt = $conn->prepare("
    UPDATE live_recitation_requests
    SET status = ?
    WHERE id = ?
");
$stmt->bind_param("si", $action, $live_id);
if (!$stmt->execute()) {
    exit('Database error updating live recitation');
}

// --- 2. Fetch student_id and lesson_id for this request ---
$live = $conn->query("
    SELECT student_id, lesson_id
    FROM live_recitation_requests
    WHERE id = $live_id
")->fetch_assoc();

if (!$live) {
    exit('Live request not found');
}

$student_id = (int)$live['student_id'];
$lesson_id  = (int)$live['lesson_id'];

// --- 3. Insert into student_recitation for feedback tracking ---
if ($action === 'accepted' || $action === 'rejected') {
    $status_text = $action;
    $feedback_text = $action === 'accepted'
        ? 'Live recitation accepted by admin'
        : 'Live recitation rejected. Please re-record and resubmit';

    $stmt2 = $conn->prepare("
        INSERT INTO student_recitation
        (student_id, learning_plan_id, status, rating, feedback, submitted_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $rating_placeholder = 'Live Session';
    $stmt2->bind_param("iisss", $student_id, $lesson_id, $status_text, $rating_placeholder, $feedback_text);
    if (!$stmt2->execute()) {
        exit('Database error inserting feedback');
    }
}

echo "OK";