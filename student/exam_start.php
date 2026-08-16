<?php
require '../config/security/helpers.php';
require '../auth/auth_check.php';
require '../config/db.php';
require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('exam.php');
}

csrf_verify();

$student_id = (int)$_SESSION['user_id'];

/* Exam must be open globally or for this student only */
if (!can_take_exam($conn, $student_id)) {
    redirect('exam.php');
}
if (student_exam_locked($conn, $student_id) && !student_exam_access($conn, $student_id)) {
    redirect('exam.php');
}

/* Self-heal: make sure the columns this flow writes to exist (see
   student/submit_exam.php for the same guard). */
try {
    ensure_exam_submit_schema($conn);
} catch (Throwable $e) {
    error_log('Exam start schema check failed: ' . $e->getMessage());
}

/* Completed surahs — required to generate questions */
$completed_ids = [];
$res = $conn->query("SELECT surah_id FROM student_learning WHERE student_id = $student_id AND status = 'completed'");
while ($row = $res->fetch_assoc()) {
    $completed_ids[(int)$row['surah_id']] = true;
}
if (count($completed_ids) === 0) {
    redirect('exam.php');
}

/* No exam already in progress / awaiting review / passed (scoped to the current term) */
$term = exam_term_info($conn);
$term_id = $term ? (int)$term['id'] : 0;
$stmt = $conn->prepare("SELECT id, status FROM exam_attempts WHERE student_id = ? AND term_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ii", $student_id, $term_id);
$stmt->execute();
$latest = $stmt->get_result()->fetch_assoc();
if ($latest) {
    if ($latest['status'] === 'pending') redirect('exam.php');              // awaiting review
    if ($latest['status'] === 'approved') redirect('exam.php');             // already passed
    if ($latest['status'] === 'draft') redirect('exam.php');                // already in progress
    /* rejected -> allow a fresh retake */
}

/* Server-side tier: never trust the client's chosen count */
$tier = exam_tier_info($conn, $student_id);
$question_count = (int)$tier['questions'];
$max_days       = (int)$tier['max_days'];

/* How many days remain in the exam window (cap the day choice) */
$days_left_in_exam = null;
if ($term && empty($term['deactivated_at']) && !empty($term['auto_close_at'])) {
    $days_left_in_exam = (int)ceil((strtotime($term['auto_close_at']) - time()) / 86400);
    if ($days_left_in_exam < 0) $days_left_in_exam = 0;
}

$chosen_days = (int)($_POST['days'] ?? 1);
if ($chosen_days < 1) $chosen_days = 1;
if ($max_days > 1) {
    if ($chosen_days > $max_days) $chosen_days = $max_days;
    if ($days_left_in_exam !== null && $chosen_days > max(1, $days_left_in_exam)) {
        $chosen_days = max(1, $days_left_in_exam);
    }
} else {
    $chosen_days = 1;
}

/* Generate + distribute the questions */
$questions = exam_generate_questions($conn, $student_id, $question_count);
if (!$questions) {
    redirect('exam.php');
}
$questions = exam_distribute_days($questions, $chosen_days);

/* Defensive range validation — drop any degenerate question so it can
   never be persisted (e.g. a single-verse "176 to 176" window). */
$questions = array_values(array_filter($questions, function ($q) {
    $from = (int)$q['from'];
    $to   = (int)$q['to'];
    return $from >= 1 && $to >= $from && ($to - $from + 1) >= 8;
}));

$actual_count = count($questions);
if ($actual_count === 0) {
    redirect('exam.php');
}
$total_days   = max(1, min($chosen_days, $actual_count));

$term_id = $term ? (int)$term['id'] : 0;

/* Persist the draft attempt (the plan) */
$stmt = $conn->prepare("
    INSERT INTO exam_attempts (student_id, term_id, submitted_at, status, question_count, total_days, day_no)
    VALUES (?, ?, NULL, 'draft', ?, ?, 1)
");
$stmt->bind_param("iiii", $student_id, $term_id, $actual_count, $total_days);
$stmt->execute();
$attempt_id = (int)$conn->insert_id;

/* Persist every question with its day assignment */
$stmt = $conn->prepare("
    INSERT INTO exam_answers (attempt_id, surah_id, from_verse, to_verse, day_no, status)
    VALUES (?, ?, ?, ?, ?, 'pending')
");
foreach ($questions as $q) {
    $stmt->bind_param("iiiii", $attempt_id, $q['surah_id'], $q['from'], $q['to'], $q['day_no']);
    $stmt->execute();
}

redirect('exam.php');
