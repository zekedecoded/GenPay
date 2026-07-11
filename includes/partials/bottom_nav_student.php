<?php
// Partial: fixed mobile bottom nav for student pages (visible under 768px).
// Same $currentPage contract as sidebar_student.php; needs
// assets/css/student_dashboard.css. Place just before </body>.
$currentPage = $currentPage ?? '';
?>
<nav class="sd-bottomnav">
    <a href="<?= DASHBOARD_URL ?>" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>
    <a href="<?= STUDENT_URL ?>/topup_request.php" class="<?= $currentPage === 'topup' ? 'active' : '' ?>">
        <i class="fa-solid fa-circle-plus"></i>
        <span>Top-Up</span>
    </a>
    <a href="<?= STUDENT_URL ?>/scan.php" class="sd-scan-fab" aria-label="Scan &amp; Pay">
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
