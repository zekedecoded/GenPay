<?php
// Partial: parent portal topbar (shared header for all parent pages).
// Including pages must set, before requiring this file:
//   $currentUser    (array) - from gjc_current_user($db); ['name'] is used
//   $topbarTitle    (string) - final <h1> HTML
//   $topbarSubtitle (string) - final <p> HTML
// Optional:
//   $profilePhotoUrl (string) - profile photo URL; falls back to the initial avatar
$profilePhotoUrl = $profilePhotoUrl ?? '';
$topbarInitial = strtoupper(substr((string) $currentUser['name'], 0, 1));
$__topbar_e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!-- The title and subtitle are DIRECT children of the header, not wrapped in
     a div: parent_shell.css lays the bar out as a named grid, and on a phone
     the subtitle drops onto its own full-width row under the title/avatar
     row. A wrapper would trap it in the title column. -->
<header class="parent-topbar">
    <button class="parent-menu-btn" aria-label="Toggle navigation" onclick="toggleParentSidebar()">
        <i class="fa-solid fa-bars"></i>
    </button>
    <h1><?= $topbarTitle ?></h1>
    <p><?= $topbarSubtitle ?></p>
    <div class="parent-user">
        <span class="parent-user-name"><?= $__topbar_e($currentUser['name']) ?></span>
        <div class="parent-avatar" id="topbarAvatar" style="<?= $profilePhotoUrl ? 'overflow:hidden;' : '' ?>">
            <?php if ($profilePhotoUrl): ?>
                <img id="topbarAvatarImg" src="<?= $__topbar_e($profilePhotoUrl) ?>" alt=""
                     style="width:100%;height:100%;object-fit:cover;display:block;">
            <?php else: ?>
                <span id="topbarAvatarInitial"><?= $__topbar_e($topbarInitial) ?></span>
            <?php endif; ?>
        </div>
    </div>
</header>
