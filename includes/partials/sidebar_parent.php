<?php
$currentPage = $currentPage ?? '';
?>
<aside class="student-sidebar" id="parentSidebar">
    <div class="student-brand">
        <div class="student-brand-logo">
            <img src="<?= ICONS_URL ?>/GenPay_logo.png" alt="GenPay Logo">
        </div>
        <div class="student-brand-text">
            <h4>GenPay</h4>
            <span>Parent Portal</span>
        </div>
    </div>
    <nav class="student-menu">
        <a href="<?= PARENT_URL ?>/dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high student-nav-icon"></i>
            <span class="student-nav-text">Dashboard</span>
        </a>
        <a href="<?= PARENT_URL ?>/profile.php" class="<?= $currentPage === 'profile' ? 'active' : '' ?>">
            <i class="fa-solid fa-user student-nav-icon"></i>
            <span class="student-nav-text">Profile</span>
        </a>
    </nav>
    <a href="<?= BASE_URL ?>/logout.php" class="student-logout"
       onclick="openLogoutModal(event);">
        <i class="fa-solid fa-arrow-right-from-bracket student-logout-icon"></i>
        <span>Logout</span>
    </a>
</aside>
<?php require __DIR__ . '/logout_modal.php'; ?>
