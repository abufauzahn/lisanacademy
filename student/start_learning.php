<?php
require '../config/security/helpers.php';
require_role('student');
require '../auth/auth_check.php';
require '../config/db.php';

if (exam_mode_on($conn)) {
    header("Location: exam.php");
    exit;
}

if (student_exam_locked($conn, (int)$_SESSION['user_id'])) {
    header("Location: exam_defaulted.php");
    exit;
}

$student_id = (int)$_SESSION['user_id'];

// Validate input
if (!isset($_POST['surah_id'], $_POST['verses_per_request']) || 
    $_POST['surah_id'] === '' || $_POST['verses_per_request'] < 1) {
    header("Location: my_learning.php");
    exit;
}

$surah_id = (int) $_POST['surah_id'];
$verses_per_request = (int) $_POST['verses_per_request'];

// Check if there is already an active plan for this surah
$check = $conn->prepare("
    SELECT id 
    FROM student_learning 
    WHERE student_id = ? AND surah_id = ? AND status = 'active'
    LIMIT 1
");
$check->bind_param("ii", $student_id, $surah_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    // Already an active plan for this surah
    header("Location: my_learning.php");
    exit;
}

/* Create learning plan */
$stmt = $conn->prepare("
    INSERT INTO student_learning (
        student_id,
        surah_id,
        verses_per_request,
        completed_requests,
        status
    ) VALUES (?, ?, ?, 0, 'active')
");
$stmt->bind_param("iii", $student_id, $surah_id, $verses_per_request);
$stmt->execute();
$plan_id = $stmt->insert_id;

/* Get total verses of the surah */
$stmt = $conn->prepare("SELECT total_verses FROM surahs WHERE id = ?");
$stmt->bind_param("i", $surah_id);
$stmt->execute();
$total_verses = $stmt->get_result()->fetch_assoc()['total_verses'] ?? 0;

if ($total_verses > 0) {
    /* Create first lesson request (from verse 1) */
    $from_verse = 1;
    $to_verse = min($verses_per_request, $total_verses);

    $stmt = $conn->prepare("
        INSERT INTO lessons (student_id, surah_id, from_verse, to_verse, status)
        VALUES (?, ?, ?, ?, 'requested')
    ");
    $stmt->bind_param("iiii", $student_id, $surah_id, $from_verse, $to_verse);
    $stmt->execute();
}

/* Referral reward: if this student was invited by a friend and has now started
   learning, reward the friend who invited them with a 15% next-term discount. */
reward_inviter_for_joined_student($conn, $student_id);

header("Location: my_learning.php");
exit;