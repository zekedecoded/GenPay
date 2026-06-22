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
            <h4>GenPay</h4>
            <span>Merchant Admin</span>
        </div>
    </div>
    <nav class="merchant-menu">
        <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high merchant-nav-icon"></i>
            <span class="merchant-nav-text">Dashboard</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/inventory.php" class="<?= $currentPage === 'inventory' ? 'active' : '' ?>">
            <i class="fa-solid fa-boxes-stacked merchant-nav-icon"></i>
            <span class="merchant-nav-text">Inventory</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/pos.php" class="<?= $currentPage === 'pos' ? 'active' : '' ?>">
            <i class="fa-solid fa-cash-register merchant-nav-icon"></i>
            <span class="merchant-nav-text">POS</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/staff.php" class="<?= $currentPage === 'staff' ? 'active' : '' ?>">
            <i class="fa-solid fa-users merchant-nav-icon"></i>
            <span class="merchant-nav-text">Staff</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/qr_scanner.php" class="<?= $currentPage === 'qr_scanner' ? 'active' : '' ?>">
            <i class="fa-solid fa-person-walking merchant-nav-icon"></i>
            <span class="merchant-nav-text">Scan Voucher</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/encash.php" class="<?= $currentPage === 'encash' ? 'active' : '' ?>">
            <i class="fa-solid fa-money-check-dollar merchant-nav-icon"></i>
            <span class="merchant-nav-text">Encash</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/history.php" class="<?= $currentPage === 'history' ? 'active' : '' ?>">
            <i class="fa-solid fa-receipt merchant-nav-icon"></i>
            <span class="merchant-nav-text">Sales History</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/settings.php" class="<?= $currentPage === 'settings' ? 'active' : '' ?>">
            <i class="fa-solid fa-gear merchant-nav-icon"></i>
            <span class="merchant-nav-text">Business Profile</span>
        </a>
    </nav>
    <a href="<?= BASE_URL ?>/logout.php" class="merchant-logout"
       onclick="openLogoutModal(event);">
        <i class="fa-solid fa-arrow-right-from-bracket merchant-logout-icon"></i>
        <span>Logout</span>
    </a>
</aside>
<?php require __DIR__ . '/logout_modal.php'; ?>
