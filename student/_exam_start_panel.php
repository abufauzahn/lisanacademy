<?php
/* Shared start/retake panel for student/exam.php.
   Expects: $tier (exam_tier_info), $max_days_available, and $attempt (may be null). */

$is_retake = $attempt && $attempt['status'] === 'rejected';
?>
<div class="card animate-rise" style="max-width:820px;">
    <div class="card-title">
        <h3 style="margin:0;display:flex;align-items:center;gap:10px;">
            <?= $is_retake ? ui_icon('refresh', 18) : ui_icon('notes', 18) ?>
            <?= $is_retake ? 'Start Your Retake' : 'Start Your Examination' ?>
        </h3>
    </div>

    <p class="small text-muted" style="margin:0 0 6px;">
        Based on your Qur'an completion, your exam is prepared for your level:
    </p>

    <div class="stat-grid" style="margin-bottom:14px;">
        <div class="stat-card stat-green">
            <span class="stat-ico"><?= ui_icon('book-open', 20) ?></span>
            <span class="stat-label">Completion</span>
            <span class="stat-value" style="font-size:1.5rem;"><?= (int)$tier['percent'] ?>%</span>
            <span class="stat-sub">Of the Qur'an</span>
        </div>
        <div class="stat-card stat-gold">
            <span class="stat-ico"><?= ui_icon('notes', 20) ?></span>
            <span class="stat-label">Questions</span>
            <span class="stat-value" style="font-size:1.5rem;"><?= (int)$tier['questions'] ?></span>
            <span class="stat-sub"><?= htmlspecialchars($tier['label']) ?> tier</span>
        </div>
        <div class="stat-card stat-blue">
            <span class="stat-ico"><?= ui_icon('calendar', 20) ?></span>
            <span class="stat-label">Days</span>
            <span class="stat-value" style="font-size:1.5rem;"><?= (int)$max_days_available ?></span>
            <span class="stat-sub">Max <?= $tier['max_days'] > 1 ? 'spread' : 'single day' ?></span>
        </div>
    </div>

    <form method="POST" action="exam_start.php" onsubmit="return confirm('<?= $is_retake ? 'Start your retake now?' : 'Start your exam now?' ?> Your questions will be generated once and cannot be changed.');">
        <?= csrf_field() ?>

        <?php if ($tier['max_days'] > 1 && $max_days_available > 1): ?>
            <div class="form-group">
                <label class="form-label" for="days">Choose how many days to complete your exam <span class="req">*</span></label>
                <select class="form-select" name="days" id="days">
                    <?php for ($d = 1; $d <= $max_days_available; $d++): ?>
                        <option value="<?= $d ?>" <?= $d === min($tier['max_days'], $max_days_available) ? 'selected' : '' ?>>
                            <?= $d . ' day' . ($d > 1 ? 's' : '') ?>
                            <?php if ($d > 1): ?>(<?= (int)ceil($tier['questions'] / $d) ?> questions per day)<?php endif; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <small class="text-muted" style="font-size:.78rem;">
                    Your <?= (int)$tier['questions'] ?> questions will be spread evenly across the days you choose. You
                    must finish all days before the exam window closes.
                </small>
            </div>
        <?php else: ?>
            <p class="small text-muted" style="margin:0 0 12px;">
                You will answer all <?= (int)$tier['questions'] ?> questions in one sitting.
            </p>
        <?php endif; ?>

        <button type="submit" class="btn btn-gold btn-lg btn-block">
            <?= ui_icon($is_retake ? 'refresh' : 'send', 18) ?>
            <?= $is_retake ? 'Start Retake' : 'Generate &amp; Start Exam' ?>
        </button>
        <p class="small text-muted" style="margin:10px 0 0;">
            Your questions are drawn from the surahs you have completed and are fixed once generated.
        </p>
    </form>
</div>
