<?php
// htdocs/register_process.php — handles the public application form.
// Creates a PENDING application (no account). The admin contacts the applicant
// on WhatsApp, confirms payment, then activates the account.

require_once __DIR__ . '/config/security/session.php';
require_once __DIR__ . '/config/security/helpers.php';
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/register.php');
}

csrf_verify();

$full_name       = trim($_POST['full_name'] ?? '');
$whatsapp_phone  = trim($_POST['whatsapp_phone'] ?? '');
$email           = trim($_POST['email'] ?? '');
$learning_info   = trim($_POST['learning_info'] ?? '');

/* Keep form values so the student doesn't retype everything on error. */
$_SESSION['reg_old'] = [
    'full_name'      => $full_name,
    'whatsapp_phone' => $whatsapp_phone,
    'email'          => $email,
    'learning_info'  => $learning_info,
];

$fail = function ($message) {
    redirect('/register.php?error=' . urlencode($message));
};

/* ---- Basic validation ---- */
if ($full_name === '' || strlen($full_name) > 100) {
    $fail('Please enter your full name.');
}
if (mb_strlen($full_name) < 3) {
    $fail('Please enter your full name (at least 3 characters).');
}
if ($whatsapp_phone === '') {
    $fail('Please enter your WhatsApp phone number.');
}
if ($learning_info === '' || mb_strlen($learning_info) < 10) {
    $fail('Please tell us a little about your learning (at least 10 characters).');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $fail('Please enter a valid email address.');
}
if ($email !== '' && strlen($email) > 120) {
    $fail('Email address is too long.');
}

/* ---- Normalize phone to international digits (e.g. 0801... -> 234801...) ---- */
$phone_intl = normalize_phone_to_intl($whatsapp_phone);
if (strlen($phone_intl) < 10 || strlen($phone_intl) > 15) {
    $fail('Please enter a valid WhatsApp phone number.');
}

/* ---- Uniqueness checks (against existing students AND pending applications) ---- */
try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
    $stmt->bind_param("s", $phone_intl);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $fail('This phone number is already registered. If you are an existing student, please login instead.');
    }
} catch (Throwable $e) { /* phone column may not exist — ignore */ }

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($email !== '' && $stmt->get_result()->num_rows > 0) {
        $fail('This email address is already registered. Please login instead.');
    }
} catch (Throwable $e) { /* ignore */ }

if (db_table_exists($conn, 'applications')) {
    try {
        $stmt = $conn->prepare("SELECT id FROM applications WHERE whatsapp_phone = ? AND status <> 'rejected' LIMIT 1");
        $stmt->bind_param("s", $phone_intl);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $fail('An application with this phone number is already being reviewed. We will contact you soon.');
        }
    } catch (Throwable $e) { /* ignore */ }
}

/* ---- Insert the pending application ---- */
try {
    $stmt = $conn->prepare("
        INSERT INTO applications (full_name, whatsapp_phone, email, learning_info, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("ssss", $full_name, $phone_intl, $email, $learning_info);
    $stmt->execute();
} catch (Throwable $e) {
    $fail('We could not save your application right now. Please try again in a moment.');
}

/* All good — clear the sticky form and confirm. */
unset($_SESSION['reg_old']);
redirect('/register.php?success=1');
