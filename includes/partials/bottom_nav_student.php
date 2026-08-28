<?php
// Partial: fixed mobile bottom nav for student pages (visible under 768px).
// Same $currentPage contract as sidebar_student.php; needs
// assets/css/student_dashboard.css. Place just before </body>.
// .sd-scan-fab is the raised centre button. It opens the Shop Cart, which is
// itself a barcode scanner (jsQR) — so the class name and the fa-qrcode glyph
// agree again. bottom_nav_merchant.php shares the class but points its FAB at
// the POS with fa-cash-register.
$currentPage = $currentPage ?? '';
?>
<nav class="sd-bottomnav">
    <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>
    <a href="<?= STUDENT_URL ?>/transfer.php" class="<?= $currentPage === 'transfer' ? 'active' : '' ?>">
        <i class="fa-solid fa-paper-plane"></i>
        <span>Send</span>
    </a>
    <a href="<?= STUDENT_URL ?>/cart.php" class="sd-scan-fab" aria-label="Shop Cart">
        <i class="fa-solid fa-qrcode"></i>
    </a>
    <a href="<?= STUDENT_URL ?>/history.php" class="<?= $currentPage === 'history' ? 'active' : '' ?>">
        <i class="fa-solid fa-receipt"></i>
        <span>History</span>
    </a>
    <a href="<?= STUDENT_URL ?>/profile.php" class="<?= $currentPage === 'profile' ? 'active' : '' ?>">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>
</nav>
<?php require __DIR__ . '/back_to_dashboard.php'; ?>
