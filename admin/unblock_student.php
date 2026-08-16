<?php
require_once __DIR__ . '/../config/security/helpers.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_role('admin');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) redirect('students.php');

$conn->prepare("UPDATE users SET blocked = 0 WHERE id = ?")
     ->bind_param("i", $id)
     ->execute();

redirect("student_detail.php?id=$id");