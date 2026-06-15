<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/CirculationEngine.php';

$engine = new CirculationEngine($db);
$snap = $engine->getCirculationSnapshot();
$cap = max((float) ($snap['cap'] ?? 0), 0);
$vault = (float) ($snap['vault'] ?? 0);
$distributed = max(0, $cap - $vault);
$drift = abs((float) ($snap['circulation_drift'] ?? 0));
$isBalanced = $drift < 0.01;

$currentPage = 'economy';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <title>System Economy | GJC EduPay</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="admin-layout">

        <?php require __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

        <main class="admin-main">

            <header class="topbar">
                <button class="menu-btn" onclick="toggleSidebar()">Menu</button>

                <div>
                    <h1>System Economy</h1>
                    <p>Track the closed-loop EduPay money supply, vault reserve, wallet pools, and minting controls.</p>
                </div>

                <div class="admin-user">
                    <span>Admin</span>
                    <div class="avatar">
                        <img src="<?= ICONS_URL ?>/admin.png" alt="Admin">
                    </div>
                </div>
            </header>

            <section class="economy-overview mb-4">
                <div class="economy-balance-card">
                    <span>Authorized Money Supply</span>
                    <h2>Php <?= number_format($cap, 2) ?></h2>
                    <p><?= $isBalanced ? 'Economy is balanced and ready for transactions.' : 'Drift detected. Review circulation immediately.' ?></p>
                </div>

                <div class="economy-mini-card">
                    <span>Vault Reserve</span>
                    <strong>Php <?= number_format($vault, 2) ?></strong>
                    <small>Available for cashier top-ups</small>
                </div>

                <div class="economy-mini-card">
                    <span>Distributed Balance</span>
                    <strong>Php <?= number_format($distributed, 2) ?></strong>
                    <small>Held by wallets and vouchers</small>
                </div>

                <div class="economy-mini-card <?= $isBalanced ? 'economy-ok' : 'economy-alert' ?>">
                    <span>Integrity Status</span>
                    <strong><?= $isBalanced ? 'Balanced' : 'Drift Php ' . number_format($drift, 2) ?></strong>
                    <small>Last snapshot: <?= $snap['as_of'] ?? 'N/A' ?></small>
                </div>
            </section>

            <?php require INCLUDES_PATH . '/circulation_widget.php'; ?>

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

