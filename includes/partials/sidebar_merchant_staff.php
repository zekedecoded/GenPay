<?php
// Partial: Merchant Staff sidebar — included by dashboard.php and friends.
// Same sd-sidebar / sd-brand / sd-menu / sd-logout markup as
// sidebar_merchant_admin.php / sidebar_student.php, just with staff's
// reduced link set and "Cashier Staff" brand subtitle.
// Requires: $currentPage string (e.g. 'dashboard', 'pos', 'inventory')
$currentPage = $currentPage ?? '';
?>
<aside class="sd-sidebar">
    <div class="sd-brand">
        <div class="sd-brand-logo"><img src="<?= ICONS_URL ?>/gp_logo.png" alt="GenPay"></div>
        <div class="sd-brand-text">
            <h4>GenPay</h4>
            <span>Cashier Staff</span>
        </div>
    </div>

    <nav class="sd-menu">
        <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/pos.php" class="<?= $currentPage === 'pos' ? 'active' : '' ?>">
            <i class="fa-solid fa-cash-register"></i>
            <span>POS / Transactions</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/inventory.php" class="<?= $currentPage === 'inventory' ? 'active' : '' ?>">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span>Inventory Stock</span>
        </a>
    </nav>

    <a href="<?= BASE_URL ?>/logout.php" class="sd-logout" onclick="openLogoutModal(event);">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span>Logout</span>
    </a>
</aside>
<?php require __DIR__ . '/logout_modal.php'; ?>
<script>
document.addEventListener('click', function (e) {
    var link = e.target.closest('.sd-menu > a');
    if (!link || link.classList.contains('active')) return;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var current = link.parentElement.querySelector(':scope > a.active');
    if (current) current.classList.remove('active');
    link.classList.add('active');
});
</script>
