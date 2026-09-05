<?php
// Partial: Merchant Admin sidebar — included by dashboard.php and friends.
// Exact copy of includes/partials/sidebar_student.php — same sd-sidebar /
// sd-brand / sd-menu / sd-logout markup, classes, and active-highlight JS.
// Desktop only — hidden under 768px, where bottom_nav_merchant.php takes over.
// Requires: $currentPage string (e.g. 'dashboard', 'inventory', 'staff', etc.)
$currentPage = $currentPage ?? '';
?>
<aside class="sd-sidebar" id="sdSidebar">
    <div class="sd-brand">
        <div class="sd-brand-logo"><img src="<?= ICONS_URL ?>/gp_logo.png" alt="GenPay"></div>
        <div class="sd-brand-text">
            <h4>GenPay</h4>
            <span>Merchant Admin</span>
        </div>
    </div>

    <nav class="sd-menu">
        <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/inventory.php" class="<?= $currentPage === 'inventory' ? 'active' : '' ?>">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span>Inventory</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/pos.php" class="<?= $currentPage === 'pos' ? 'active' : '' ?>">
            <i class="fa-solid fa-cash-register"></i>
            <span>POS</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/staff.php" class="<?= $currentPage === 'staff' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i>
            <span>Staff</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/encash.php" class="<?= $currentPage === 'encash' ? 'active' : '' ?>">
            <i class="fa-solid fa-money-check-dollar"></i>
            <span>Encash</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/rent.php" class="<?= $currentPage === 'rent' ? 'active' : '' ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>Rent &amp; Lease</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/history.php" class="<?= $currentPage === 'history' ? 'active' : '' ?>">
            <i class="fa-solid fa-receipt"></i>
            <span>Sales History</span>
        </a>
        <a href="<?= MERCHANT_URL ?>/settings.php" class="<?= $currentPage === 'settings' ? 'active' : '' ?>">
            <i class="fa-solid fa-gear"></i>
            <span>Business Profile</span>
        </a>
    </nav>

    <a href="<?= BASE_URL ?>/logout.php" class="sd-logout" onclick="openLogoutModal(event);">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span>Logout</span>
    </a>
</aside>
<script>
/* Rail collapse. The state has to survive navigation — every item in the rail
   is a link, so without persisting it the sidebar sprang back open on the next
   page load. The class is restored inline right after the <aside> (before
   first paint, so there is no flash of the wide rail and no width transition),
   and re-saved by watching the element itself. One key across the student,
   merchant-admin and merchant-staff shells: it is the same component. */
function toggleSdSidebar() {
    var el = document.getElementById('sdSidebar');
    if (el) el.classList.toggle('collapsed');
}
(function () {
    var el = document.getElementById('sdSidebar');
    if (!el) return;
    var KEY = 'gjc.sdSidebarCollapsed';
    try {
        if (localStorage.getItem(KEY) === '1') el.classList.add('collapsed');
    } catch (e) {}
    new MutationObserver(function () {
        try {
            localStorage.setItem(KEY, el.classList.contains('collapsed') ? '1' : '0');
        } catch (e) {}
    }).observe(el, { attributes: true, attributeFilter: ['class'] });
})();
</script>
<?php require __DIR__ . '/logout_modal.php'; ?>
<script>
// Instant gold highlight on click (the logout_modal.php handler only covers
// the legacy .student-menu sidebars).
document.addEventListener('click', function (e) {
    var link = e.target.closest('.sd-menu > a');
    if (!link || link.classList.contains('active')) return;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var current = link.parentElement.querySelector(':scope > a.active');
    if (current) current.classList.remove('active');
    link.classList.add('active');
});
</script>
