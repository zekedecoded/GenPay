<?php
// Partial: student topbar (shared header for all student pages).
// Including pages must set, before requiring this file:
//   $studentName    (string) - already required by every student page for the sidebar/greeting
//   $topbarTitle    (string) - final <h1> HTML (pre-escaped by the caller, same as before)
//   $topbarSubtitle (string) - final <p> HTML (pre-escaped by the caller, same as before)
// Optional:
//   $topbarShowBell        (bool)   - show the notification bell (dashboard, history)
//   $topbarAvatarPhotoUrl  (string) - profile photo URL; falls back to the initial avatar
$topbarShowBell = $topbarShowBell ?? false;
$topbarAvatarPhotoUrl = $topbarAvatarPhotoUrl ?? '';
$topbarAvatarInitial = strtoupper(substr((string) $studentName, 0, 1));
$__topbar_e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<header class="sd-topbar">
    <div class="sd-topbar-greet">
        <h1><?= $topbarTitle ?></h1>
        <p><?= $topbarSubtitle ?></p>
    </div>
    <div class="sd-topbar-tools">
        <?php if ($topbarShowBell): ?>
        <button type="button" class="sd-bell" aria-label="Notifications">
            <i class="fa-regular fa-bell"></i>
        </button>
        <?php endif; ?>
        <div class="sd-avatar" id="topbarAvatar" style="<?= $topbarAvatarPhotoUrl ? 'overflow:hidden;' : '' ?>">
            <?php if ($topbarAvatarPhotoUrl): ?>
                <img id="topbarAvatarImg" src="<?= $__topbar_e($topbarAvatarPhotoUrl) ?>" alt=""
                     style="width:100%;height:100%;object-fit:cover;display:block;">
            <?php else: ?>
                <?= $__topbar_e($topbarAvatarInitial) ?>
            <?php endif; ?>
        </div>
    </div>
</header>
