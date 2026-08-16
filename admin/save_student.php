<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

if (
    empty($_POST['name']) ||
    empty($_POST['email']) ||
    empty($_POST['password'])
) {
    redirect('add_student.php?error=' . urlencode('All fields are required'));
}

$name  = trim($_POST['name']);
$email = trim($_POST['email']);
$pass  = $_POST['password'];
$phone = trim($_POST['phone'] ?? '');

/* check email uniqueness */
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    redirect('add_student.php?error=' . urlencode('Email already exists'));
}

/* hash password securely */
$hashed = password_hash($pass, PASSWORD_DEFAULT);

/* insert student */
$has_phone = db_column_exists($conn, 'users', 'phone');
if ($has_phone) {
    $stmt = $conn->prepare("
        INSERT INTO users (name, email, phone, password, role, device_type, suspended, blocked)
        VALUES (?, ?, ?, ?, 'student', 'iphone', 0, 0)
    ");
    $stmt->bind_param("ssss", $name, $email, $phone, $hashed);
} else {
    $stmt = $conn->prepare("
        INSERT INTO users (name, email, password, role, device_type, suspended, blocked)
        VALUES (?, ?, ?, 'student', 'iphone', 0, 0)
    ");
    $stmt->bind_param("sss", $name, $email, $hashed);
}
$stmt->execute();
$new_student_id = (int)$conn->insert_id;

/* Referral link: if this new student's phone matches a pending friend invite,
   mark that invite as 'joined' (the friend has now registered). */
if ($phone !== '' && db_column_exists($conn, 'student_invites', 'status')) {
    try {
        $stmt = $conn->prepare("
            UPDATE student_invites
            SET status = 'joined', joined_student_id = ?
            WHERE friend_phone = ? AND status = 'invited'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("is", $new_student_id, $phone);
        $stmt->execute();
    } catch (Throwable $e) { /* ignore */ }
}

/* success */
redirect('students.php');