<?php
require '../config/security/helpers.php';
require_role('student');
require '../auth/auth_check.php';
require '../config/db.php';

$student_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, email, profile_image, device_type FROM users WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
<title>My Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= ui_css() ?>
</head>
<?php ui_page_start('student', 'profile', 'Profile', 'Account'); ?>

<div class="animate-rise" style="max-width:560px;margin:0 auto;">

    <div class="card" style="padding:30px;">
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success"><?= ui_icon('check-circle', 16) ?> Your profile has been updated successfully.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <div class="profile-head">
            <img class="profile-img" src="../uploads/profile_pics/<?= $user['profile_image'] ? htmlspecialchars($user['profile_image']) : 'default.png' ?>"
                 alt="Profile Picture" onerror="this.style.display='none'">
            <h2 style="margin:0;">My Profile</h2>
        </div>

        <form method="POST" action="update_profile.php" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input class="form-input" type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" readonly>
                <p class="small text-muted" style="margin:6px 0 0;">
                    <?= ui_icon('info', 13) ?> Your name appears on your certificate. To change it, contact the academy and an admin will update it for you.
                </p>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email (cannot change)</label>
                <input class="form-input" type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
            </div>

            <div class="form-group">
                <label class="form-label" for="profile_image">Profile Picture</label>
                <input class="form-input" type="file" id="profile_image" name="profile_image" accept="image/*">
            </div>

            <div class="form-group">
                <label class="form-label" for="device_type">Device Type</label>
                <select class="form-select" id="device_type" name="device_type" required>
                    <option value="android" <?= ($user['device_type'] ?? 'android') === 'android' ? 'selected' : '' ?>>
                        Android
                    </option>
                    <option value="iphone" <?= ($user['device_type'] ?? '') === 'iphone' ? 'selected' : '' ?>>
                        iPhone
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">New Password <span class="small text-muted">(leave empty if unchanged)</span></label>
                <input class="form-input" type="password" id="password" name="password">
            </div>

            <button type="submit" class="btn btn-block btn-lg">Save Changes</button>
        </form>
    </div>
</div>

<?php ui_page_end(); ?>

</body>
</html>