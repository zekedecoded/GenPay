<?php
// Partial: Merchant Staff sidebar — included by dashboard.php
// Requires: $currentPage string (e.g. 'dashboard', 'pos', 'inventory', 'qrcode')
$currentPage = $currentPage ?? '';
?>
<aside class="merchant-sidebar" id="merchantSidebar">
    <div class="merchant-brand">
        <div class="merchant-brand-logo">
            <img src="<?= ICONS_URL ?>/gp_logo.png" alt="GenPay Logo">
        </div>
        <div class="merchant-brand-text">
            <h4>GenPay</h4>
            <span>Cashier Staff</span>
        </div>
    </div>
    <nav class="merchant-menu">
        <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high merchant-nav-icon"></i>
            <span class="merchant-nav-text">Dashboard</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/pos.php" class="<?= $currentPage === 'pos' ? 'active' : '' ?>">
            <i class="fa-solid fa-cash-register merchant-nav-icon"></i>
            <span class="merchant-nav-text">POS / Transactions</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/inventory.php" class="<?= $currentPage === 'inventory' ? 'active' : '' ?>">
            <i class="fa-solid fa-boxes-stacked merchant-nav-icon"></i>
            <span class="merchant-nav-text">Inventory Stock</span>
        </a>
    </nav>
    <a href="<?= BASE_URL ?>/logout.php" class="merchant-logout"
       onclick="openLogoutModal(event);">
        <i class="fa-solid fa-arrow-right-from-bracket merchant-logout-icon"></i>
        <span>Logout</span>
    </a>
</aside>
<?php require __DIR__ . '/logout_modal.php'; ?>
