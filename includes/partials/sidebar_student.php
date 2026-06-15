<?php
// Partial: Student sidebar — included by dashboard.php
// Requires: $currentPage string (e.g. 'dashboard', 'scan', 'transfer', 'topup', 'history', 'profile')
$currentPage = $currentPage ?? '';
?>
<aside class="student-sidebar" id="studentSidebar">
    <div class="student-brand">
        <div class="student-brand-logo">
            <img src="<?= ICONS_URL ?>/GenPay_logo.png" alt="GenPay Logo">
        </div>
        <div class="student-brand-text">
            <h4>GJC EduPay</h4>
            <span>Student Portal</span>
        </div>
    </div>
    <nav class="student-menu">
        <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/dashboard.png" class="student-nav-icon" alt="">
            <span class="student-nav-text">Dashboard</span>
        </a>
        <a href="<?= STUDENT_URL ?>/scan.php" class="<?= $currentPage === 'scan' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/qr.png" class="student-nav-icon" alt="">
            <span class="student-nav-text">Scan &amp; Pay</span>
        </a>
        <a href="<?= STUDENT_URL ?>/transfer.php" class="<?= $currentPage === 'transfer' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/payment.png" class="student-nav-icon" alt="">
            <span class="student-nav-text">Transfer Tokens</span>
        </a>
        <a href="<?= STUDENT_URL ?>/topup_request.php" class="<?= $currentPage === 'topup' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/topups.png" class="student-nav-icon" alt="">
            <span class="student-nav-text">Top-Up</span>
        </a>
        <a href="<?= STUDENT_URL ?>/history.php" class="<?= $currentPage === 'history' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/transactions.png" class="student-nav-icon" alt="">
            <span class="student-nav-text">History</span>
        </a>
        <a href="<?= STUDENT_URL ?>/profile.php" class="<?= $currentPage === 'profile' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/users.png" class="student-nav-icon" alt="">
            <span class="student-nav-text">Profile</span>
        </a>
    </nav>
    <a href="<?= BASE_URL ?>/logout.php" class="student-logout">
        <img src="<?= ICONS_URL ?>/logout.png" class="student-logout-icon" alt="">
        <span>Logout</span>
    </a>
</aside>
