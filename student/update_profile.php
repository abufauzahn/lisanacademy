<?php
require '../config/security/helpers.php';
require_role('student');
require '../auth/auth_check.php';
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit;
}

csrf_verify();

$student_id  = (int)$_SESSION['user_id'];
$password    = $_POST['password'] ?? '';
$device_type = ($_POST['device_type'] ?? '') === 'iphone' ? 'iphone' : 'android';

/* --- Update device type --- */
$stmt = $conn->prepare("UPDATE users SET device_type=? WHERE id=?");
$stmt->bind_param("si", $device_type, $student_id);
$stmt->execute();

/* --- Update password if provided --- */
if (!empty($password)) {
    if (strlen($password) < 6) {
        header("Location: profile.php?error=Password must be at least 6 characters");
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param("si", $hash, $student_id);
    $stmt->execute();
}

/* --- Handle profile image upload --- */
if (!empty($_FILES['profile_image']['name'])) {
    $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];

    if (in_array($ext, $allowed)) {
        $filename = 'student_'.$student_id.'_'.time().'.'.$ext;
        $path = '../uploads/profile_pics/'.$filename;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $path)) {
            $stmt = $conn->prepare("UPDATE users SET profile_image=? WHERE id=?");
            $stmt->bind_param("si", $filename, $student_id);
            $stmt->execute();
        } else {
            header("Location: profile.php?error=Image upload failed");
            exit;
        }
    } else {
        header("Location: profile.php?error=Invalid image type");
        exit;
    }
}

header("Location: profile.php?updated=1");
exit;
