<?php
// Partial: Student sidebar — included by dashboard.php
// Requires: $currentPage string (e.g. 'dashboard', 'scan', 'cart', 'transfer', 'topup', 'history', 'profile')
$currentPage = $currentPage ?? '';
?>
<aside class="student-sidebar" id="studentSidebar">
    <div class="student-brand">
        <div class="student-brand-logo">
            <img src="<?= ICONS_URL ?>/GenPay_logo.png" alt="GenPay Logo">
        </div>
        <div class="student-brand-text">
            <h4>GenPay</h4>
            <span>Student Portal</span>
        </div>
    </div>
    <nav class="student-menu">
        <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high student-nav-icon"></i>
            <span class="student-nav-text">Dashboard</span>
        </a>
        <a href="<?= STUDENT_URL ?>/cart.php" class="<?= $currentPage === 'cart' ? 'active' : '' ?>">
            <i class="fa-solid fa-cart-shopping student-nav-icon"></i>
            <span class="student-nav-text">Shop Cart</span>
        </a>
        <a href="<?= STUDENT_URL ?>/transfer.php" class="<?= $currentPage === 'transfer' ? 'active' : '' ?>">
            <i class="fa-solid fa-money-bill-transfer student-nav-icon"></i>
            <span class="student-nav-text">Transfer Tokens</span>
        </a>
        <a href="<?= STUDENT_URL ?>/topup_request.php" class="<?= $currentPage === 'topup' ? 'active' : '' ?>">
            <i class="fa-solid fa-circle-plus student-nav-icon"></i>
            <span class="student-nav-text">Top-Up</span>
        </a>
        <a href="<?= STUDENT_URL ?>/history.php" class="<?= $currentPage === 'history' ? 'active' : '' ?>">
            <i class="fa-solid fa-receipt student-nav-icon"></i>
            <span class="student-nav-text">History</span>
        </a>
        <a href="<?= STUDENT_URL ?>/profile.php" class="<?= $currentPage === 'profile' ? 'active' : '' ?>">
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
