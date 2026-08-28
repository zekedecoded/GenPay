<?php
// Partial: fixed mobile bottom nav for parent pages (visible under 768px).
// Mirrors includes/partials/bottom_nav_student.php exactly — same markup,
// same classes, same raised centre FAB — so the parent portal reads as the
// same product as the student one on a phone. Same $currentPage contract as
// sidebar_parent.php; needs assets/css/parent_shell.css. Place just before
// </body>.
//
// The five slots map 1:1 onto the parent sidebar's five entries, with Shop
// Cart raised into the FAB the way the student shell raises its own cart.
$currentPage = $currentPage ?? '';
?>
<nav class="parent-bottomnav">
    <a href="<?= PARENT_URL ?>/dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>
    <a href="<?= PARENT_URL ?>/allowance.php" class="<?= $currentPage === 'allowance' ? 'active' : '' ?>">
        <i class="fa-solid fa-paper-plane"></i>
        <span>Send</span>
    </a>
    <a href="<?= PARENT_URL ?>/cart.php" class="parent-cart-fab" aria-label="Shop Cart">
        <i class="fa-solid fa-qrcode"></i>
    </a>
    <a href="<?= PARENT_URL ?>/activity.php" class="<?= $currentPage === 'activity' ? 'active' : '' ?>">
        <i class="fa-solid fa-receipt"></i>
        <span>Activity</span>
    </a>
    <a href="<?= PARENT_URL ?>/profile.php" class="<?= $currentPage === 'profile' ? 'active' : '' ?>">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>
</nav>
