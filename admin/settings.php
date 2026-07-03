<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);
$currentUser = gjc_current_user($db);
$serverTime = "Apr 25, 2026 12:34:46 AM";

$currentPage = 'settings';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <title>Settings | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=5">
    <link rel="stylesheet" href="<?= CSS_URL ?>/settings.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/gjc-clear.css?v=3">
</head>

<body>

    <div class="admin-layout">

        <?php require __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

        <main class="admin-main settings-page">

            <header class="topbar">
                <button class="menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>

                <div>
                    <h1>System Settings</h1>
                    <p>Configure visitor sessions, financial controls, and payment gateway options.</p>
                </div>

                <div class="admin-user">
                    <span><?= gjc_e($currentUser['name']) ?></span>
                    <div class="avatar">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                </div>
            </header>

            <section class="settings-panel mb-4">

                <div class="settings-panel-header">
                    <h3>
                        <i class="fa-solid fa-gear"></i>
                        System Configuration
                    </h3>
                </div>

                <form action="<?= ADMIN_URL ?>/save_settings.php" method="POST" class="settings-form">

                    <h4>Visitor Settings</h4>

                    <div class="settings-grid">
                        <div class="settings-field">
                            <label>Session Duration (hours)</label>
                            <small>Default visitor account expiry in hours</small>
                            <input type="number" name="session_duration" value="8">
                        </div>

                        <div class="settings-field">
                            <label>QR Token Validity (minutes)</label>
                            <small>Temporary QR code validity</small>
                            <input type="number" name="qr_validity" value="15">
                        </div>
                    </div>

                    <h4>Financial Controls</h4>

                    <div class="settings-grid">
                        <div class="settings-field money-field">
                            <label>Max Top-Up Per Day (₱)</label>
                            <small>&nbsp;</small>

                            <div class="input-with-prefix">
                                <span>₱</span>
                                <input type="number" name="max_topup" value="5000">
                            </div>
                        </div>

                        <div class="settings-field money-field">
                            <label>Default Spending Limit (₱)</label>
                            <small>0 means no spending limit</small>

                            <div class="input-with-prefix">
                                <span>₱</span>
                                <input type="number" name="spending_limit" value="0">
                            </div>
                        </div>
                    </div>

                    <h4>Simulated Payment Gateways</h4>

                    <div class="gateway-grid">

                        <label class="gateway-card">
                            <div>
                                <strong>GCash Gateway</strong>
                                <p>Show GCash option in top-up forms</p>
                            </div>

                            <input type="checkbox" checked>
                            <span class="switch"></span>
                        </label>

                        <label class="gateway-card">
                            <div>
                                <strong>Maya Gateway</strong>
                                <p>Show Maya option in top-up forms</p>
                            </div>

                            <input type="checkbox" checked>
                            <span class="switch"></span>
                        </label>

                    </div>

                    <div class="settings-note">
                        Simulated gateways generate fake reference numbers for testing. In production, replace with real
                        webhook integrations from GCash/Maya providers.
                    </div>

                    <button type="submit" class="save-settings-btn">
                        Save Settings
                    </button>

                </form>

            </section>

            <section class="settings-panel">

                <div class="settings-panel-header">
                    <h3>
                        <i class="fa-solid fa-circle-info"></i>
                        System Information
                    </h3>
                </div>

                <div class="system-info-list">

                    <div class="system-info-row">
                        <span>Application</span>
                        <strong>GenPay v1.0.0</strong>
                    </div>

                    <div class="system-info-row">
                        <span>Base URL</span>
                        <strong><?= BASE_URL ?></strong>
                    </div>

                    <div class="system-info-row">
                        <span>Database</span>
                        <strong>gjc_edupay_database</strong>
                    </div>

                    <div class="system-info-row">
                        <span>PHP Version</span>
                        <strong>8.3.19</strong>
                    </div>

                    <div class="system-info-row">
                        <span>Server Time</span>
                        <strong><?php echo $serverTime; ?></strong>
                    </div>

                    <div class="system-info-row">
                        <span>QR Library</span>
                        <div>
                            <strong class="warning-tag">Using CDN fallback</strong>
                            <small>Place qrlib.php at vendor/phpqrcode/qrlib.php for offline generation</small>
                        </div>
                    </div>

                </div>

            </section>

        </main>

    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>

    <script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }
    </script>

</body>

</html>
