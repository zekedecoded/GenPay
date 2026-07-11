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
<?php require __DIR__ . '/logout_modal.php'; ?>
