<?php
require '../config/security/helpers.php';
require_role('student');
require '../auth/auth_check.php';
require '../config/db.php';

$student_id = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Reset Learning Progress</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= ui_css() ?>
</head>
<?php ui_page_start('student', 'dashboard', 'Danger Zone', 'Reset'); ?>

<div class="animate-rise" style="max-width:640px;margin:0 auto;">
    <div class="alert alert-warning" style="display:flex;">
        <?= ui_icon('alert', 18) ?>
        <span>This action is <strong>irreversible</strong>. Please read carefully before continuing.</span>
    </div>

    <div class="card card-danger" style="padding:28px;">
        <h3 style="margin-top:0;color:var(--danger);display:flex;align-items:center;gap:8px;"><?= ui_icon('refresh') ?> Reset All Learning Progress</h3>

        <p>If you proceed, the following will happen:</p>
        <ul class="small" style="padding-left:20px;">
            <li>Your current learning plan will be deleted</li>
            <li>All recitation submissions will be removed</li>
            <li>Completed surahs will become <strong>uncompleted</strong></li>
            <li>Admin feedback and lesson audio will be erased</li>
            <li>You will need to start a new surah from the beginning</li>
        </ul>

        <form method="post" action="reset_progress_action.php"
              onsubmit="return confirm('Are you absolutely sure? This cannot be undone.');" style="margin-top:20px;">
            <?= csrf_field() ?>
            <button class="btn btn-danger btn-block btn-lg">
                <?= ui_icon('refresh', 18) ?> YES, RESET MY ENTIRE LEARNING PROGRESS
            </button>
        </form>
    </div>
</div>

<?php ui_page_end(); ?>

</body>
</html>