<?php
// Partial: Merchant Admin sidebar — included by dashboard.php
// Requires: $currentPage string (e.g. 'dashboard', 'inventory', 'staff', etc.)
$currentPage = $currentPage ?? '';
?>
<aside class="merchant-sidebar" id="merchantSidebar">
    <div class="merchant-brand">
        <div class="merchant-brand-logo">
            <img src="<?= ICONS_URL ?>/GenPay_logo.png" alt="GenPay Logo">
        </div>
        <div class="merchant-brand-text">
            <h4>GJC EduPay</h4>
            <span>Merchant Admin</span>
        </div>
    </div>
    <nav class="merchant-menu">
        <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/dashboard.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">Dashboard</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/inventory.php" class="<?= $currentPage === 'inventory' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/analytics.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">Inventory</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/pos.php" class="<?= $currentPage === 'pos' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/payment.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">POS</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/staff.php" class="<?= $currentPage === 'staff' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/users.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">Staff</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/qrcode.php" class="<?= $currentPage === 'qrcode' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/qr.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">Generate QR</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/qr_scanner.php" class="<?= $currentPage === 'qr_scanner' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/visitors.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">Scan Voucher</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/encash.php" class="<?= $currentPage === 'encash' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/encashments.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">Encash</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/history.php" class="<?= $currentPage === 'history' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/transactions.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">Sales History</span>
        </a>
    </nav>
    <a href="<?= BASE_URL ?>/logout.php" class="merchant-logout">
        <img src="<?= ICONS_URL ?>/logout.png" class="merchant-logout-icon" alt="">
        <span>Logout</span>
    </a>
</aside>
