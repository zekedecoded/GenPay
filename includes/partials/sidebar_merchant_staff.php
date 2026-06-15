<?php
// Partial: Merchant Staff sidebar — included by dashboard.php
// Requires: $currentPage string (e.g. 'dashboard', 'pos', 'inventory', 'qrcode')
$currentPage = $currentPage ?? '';
?>
<aside class="merchant-sidebar" id="merchantSidebar">
    <div class="merchant-brand">
        <div class="merchant-brand-logo">
            <img src="<?= ICONS_URL ?>/GenPay_logo.png" alt="GenPay Logo">
        </div>
        <div class="merchant-brand-text">
            <h4>GJC EduPay</h4>
            <span>Cashier Staff</span>
        </div>
    </div>
    <nav class="merchant-menu">
        <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/dashboard.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">Dashboard</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/pos.php" class="<?= $currentPage === 'pos' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/payment.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">POS / Transactions</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/inventory.php" class="<?= $currentPage === 'inventory' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/analytics.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">Inventory Stock</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/qrcode.php" class="<?= $currentPage === 'qrcode' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/qr.png" class="merchant-nav-icon" alt="">
            <span class="merchant-nav-text">Generate QR</span>
        </a>
    </nav>
    <a href="<?= BASE_URL ?>/logout.php" class="merchant-logout">
        <img src="<?= ICONS_URL ?>/logout.png" class="merchant-logout-icon" alt="">
        <span>Logout</span>
    </a>
</aside>
