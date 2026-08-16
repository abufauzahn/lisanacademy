<?php
require '../config/security/helpers.php';
require_role('student');
require '../auth/auth_check.php';
require '../config/db.php';

$student_id = (int)$_SESSION['user_id'];

csrf_verify();

$conn->begin_transaction();

try {

    /* Get all lesson IDs */
    $lessonIds = [];
    $res = $conn->query("
        SELECT id FROM lessons WHERE student_id = $student_id
    ");
    while ($r = $res->fetch_assoc()) {
        $lessonIds[] = (int)$r['id'];
    }

    if (!empty($lessonIds)) {
        $ids = implode(',', $lessonIds);

        /* Delete admin audio */
        $conn->query("DELETE FROM admin_audio WHERE learning_plan_id IN ($ids)");

        /* Delete student recitations */
        $conn->query("DELETE FROM student_recitation WHERE learning_plan_id IN ($ids)");

        /* Delete lessons */
        $conn->query("DELETE FROM lessons WHERE id IN ($ids)");
    }

    /* Delete learning plan */
    $conn->query("
        DELETE FROM student_learning WHERE student_id = $student_id
    ");

    $conn->commit();

    header("Location: dashboard.php");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    exit("Reset failed. Please contact support.");
}