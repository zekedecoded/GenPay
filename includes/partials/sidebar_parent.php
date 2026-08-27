<?php
$currentPage = $currentPage ?? '';
?>
<aside class="parent-sidebar" id="parentSidebar">
    <div class="parent-brand">
        <div class="parent-brand-logo">
            <img src="<?= ICONS_URL ?>/gp_logo.png" alt="GenPay Logo">
        </div>
        <div class="parent-brand-text">
            <h4>GenPay</h4>
            <span>Parent Portal</span>
        </div>
    </div>
    <nav class="parent-menu">
        <a href="<?= PARENT_URL ?>/dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high parent-nav-icon"></i>
            <span class="parent-nav-text">Dashboard</span>
        </a>
        <a href="<?= PARENT_URL ?>/cart.php" class="<?= $currentPage === 'cart' ? 'active' : '' ?>">
            <i class="fa-solid fa-cart-shopping parent-nav-icon"></i>
            <span class="parent-nav-text">Shop Cart</span>
        </a>
        <a href="<?= PARENT_URL ?>/allowance.php" class="<?= $currentPage === 'allowance' ? 'active' : '' ?>">
            <i class="fa-solid fa-hand-holding-dollar parent-nav-icon"></i>
            <span class="parent-nav-text">Send Allowance</span>
        </a>
        <a href="<?= PARENT_URL ?>/activity.php" class="<?= $currentPage === 'activity' ? 'active' : '' ?>">
            <i class="fa-solid fa-list-check parent-nav-icon"></i>
            <span class="parent-nav-text">Activity Trail</span>
        </a>
        <a href="<?= PARENT_URL ?>/profile.php" class="<?= $currentPage === 'profile' ? 'active' : '' ?>">
            <i class="fa-solid fa-user parent-nav-icon"></i>
            <span class="parent-nav-text">Profile</span>
        </a>
    </nav>
    <a href="<?= BASE_URL ?>/logout.php" class="parent-logout"
       onclick="openLogoutModal(event);">
        <i class="fa-solid fa-arrow-right-from-bracket parent-logout-icon"></i>
        <span>Logout</span>
    </a>
</aside>

<!-- Backdrop for the phone drawer (<=576px). It is a sibling of the sidebar
     on purpose: parent_shell.css shows it with `.parent-sidebar.collapsed ~
     .parent-scrim`, so opening the drawer reveals it with no JS at all. The
     only script needed is tap-to-close, wired here so all parent pages get
     it from one place instead of seven inline copies. -->
<div class="parent-scrim" id="parentScrim" aria-hidden="true"></div>
<script>
(function () {
    var scrim = document.getElementById('parentScrim');
    if (!scrim) { return; }
    scrim.addEventListener('click', function () {
        var sb = document.getElementById('parentSidebar');
        if (sb) { sb.classList.remove('collapsed'); }
    });
}());
</script>
<?php require __DIR__ . '/logout_modal.php'; ?>
<?php require __DIR__ . '/back_to_dashboard.php'; ?>
