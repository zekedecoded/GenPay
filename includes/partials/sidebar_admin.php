<?php
// Partial: Admin sidebar — included by dashboard.php
// Requires: $currentPage string (e.g. 'dashboard', 'users', 'leases', etc.)
$currentPage = $currentPage ?? ""; ?>
<aside class="admin-sidebar" id="sidebar">
    <div class="brand-box">
        <div class="brand-logo">
            <img src="<?= ICONS_URL ?>/GenPay_logo.png" alt="Logo">
        </div>
        <div class="brand-text">
            <h4>GJC EduPay</h4>
            <span>Finance</span>
        </div>
    </div>
    <nav class="sidebar-menu">
        <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === "dashboard"
    ? "active"
    : "" ?>">
            <img src="<?= ICONS_URL ?>/dashboard.png" class="nav-icon" alt="">
            <span class="nav-text">Dashboard</span>
        </a>
        <a href="<?= ADMIN_URL ?>/users.php" class="<?= $currentPage === "users"
    ? "active"
    : "" ?>">
            <img src="<?= ICONS_URL ?>/users.png" class="nav-icon" alt="">
            <span class="nav-text">Users</span>
        </a>
        <a href="<?= ADMIN_URL ?>/leases.php" class="<?= $currentPage ===
"leases"
    ? "active"
    : "" ?>">
            <img src="<?= ICONS_URL ?>/encashments.png" class="nav-icon" alt="">
            <span class="nav-text">Leases &amp; Rent</span>
        </a>
        <a href="<?= ADMIN_URL ?>/restricted_products.php" class="<?= $currentPage ===
"restricted_products"
    ? "active"
    : "" ?>">
            <img src="<?= ICONS_URL ?>/settings.png" class="nav-icon" alt="">
            <span class="nav-text">Restricted Products</span>
        </a>
        <a href="<?= ADMIN_URL ?>/topups.php" class="<?= $currentPage ===
"topups"
    ? "active"
    : "" ?>">
            <img src="<?= ICONS_URL ?>/topups.png" class="nav-icon" alt="">
            <span class="nav-text">Top-up Log</span>
        </a>
        <a href="<?= ADMIN_URL ?>/encashments.php" class="<?= $currentPage ===
"encashments"
    ? "active"
    : "" ?>">
            <img src="<?= ICONS_URL ?>/encashments.png" class="nav-icon" alt="">
            <span class="nav-text">Encashments</span>
        </a>
        <a href="<?= ADMIN_URL ?>/transactions.php" class="<?= $currentPage ===
"transactions"
    ? "active"
    : "" ?>">
            <img src="<?= ICONS_URL ?>/transactions.png" class="nav-icon" alt="">
            <span class="nav-text">Transactions</span>
        </a>
        <a href="<?= ADMIN_URL ?>/economy.php" class="<?= $currentPage ===
"economy"
    ? "active"
    : "" ?>">
            <img src="<?= ICONS_URL ?>/wallet.png" class="nav-icon" alt="">
            <span class="nav-text">Economy</span>
        </a>
        <a href="<?= ADMIN_URL ?>/visitors.php" class="<?= $currentPage ===
"visitors"
    ? "active"
    : "" ?>">
            <img src="<?= ICONS_URL ?>/visitors.png" class="nav-icon" alt="">
            <span class="nav-text">Visitors</span>
        </a>
        <a href="<?= ADMIN_URL ?>/stall_applications.php" class="<?= $currentPage === 'stall_applications' ? 'active' : '' ?>">
            <img src="<?= ICONS_URL ?>/merchants.png" class="nav-icon" alt="">
            <span class="nav-text">Stall Applications</span>
            <?php
            // Badge: count pending stall applications
            try {
                $__pendingCount = $db->query("SELECT COUNT(*) FROM stall_applications WHERE status='pending'")->fetchColumn();
                if ($__pendingCount > 0): ?>
                <span style="background:#ef4444;color:#fff;font-size:10px;font-weight:800;border-radius:50px;padding:1px 7px;margin-left:auto;"><?= (int)$__pendingCount ?></span>
            <?php endif;
            } catch (Throwable $__e) {}
            ?>
        </a>
        <a href="<?= ADMIN_URL ?>/settings.php" class="<?= $currentPage ===
"settings"
    ? "active"
    : "" ?>">
            <img src="<?= ICONS_URL ?>/settings.png" class="nav-icon" alt="">
            <span class="nav-text">Settings</span>
        </a>
    </nav>
    <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">
        <img src="<?= ICONS_URL ?>/logout.png" class="logout-icon" alt="">
        <span>Logout</span>
    </a>
</aside>
