<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);
gjc_ensure_operational_tables($db);

$pendingEncashments = (int) $db->query("SELECT COUNT(*) FROM encashment_requests WHERE status = 'pending'")->fetchColumn();
$releasedToday = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM encashment_requests WHERE status = 'released' AND DATE(released_at) = CURDATE()")->fetchColumn();
$encashmentQueue = $pendingEncashments;

$pendingRequests = $db->query(
    "SELECT * FROM encashment_requests
      WHERE status = 'pending'
      ORDER BY created_at ASC
      LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

$encashmentHistory = $db->query(
    "SELECT * FROM encashment_requests
      ORDER BY created_at DESC
      LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'encashments';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <title>Encashments | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=3">
    <link rel="stylesheet" href="<?= CSS_URL ?>/encashments.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="admin-layout">

        <?php require __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

        <main class="admin-main">

            <header class="topbar">
                <button class="menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>

                <div>
                    <h1>Encashments</h1>
                    <p>Review merchant withdrawal requests, release funds, and monitor encashment history.</p>
                </div>

                <div class="admin-user">
                    <span>Admin</span>
                    <div class="avatar">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                </div>
            </header>

            <section class="encash-stats-grid mb-4">

                <div class="encash-stat-card">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <span>Pending Encashments</span>
                    <h2><?php echo $pendingEncashments; ?></h2>
                    <p>Awaiting disbursement</p>
                </div>

                <div class="encash-stat-card">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <span>Released Today</span>
                    <h2><?php echo gjc_money($releasedToday); ?></h2>
                    <p>Total released amount</p>
                </div>

                <div class="encash-stat-card">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-money-check-dollar"></i>
                    </div>
                    <span>Encashment Queue</span>
                    <h2><?php echo $encashmentQueue; ?></h2>
                    <p>Requests waiting in queue</p>
                </div>

            </section>

            <section class="encash-panel mb-4" id="pending-encashments">

                <div class="encash-panel-header">
                    <div>
                        <h3>Pending Encashment Requests</h3>
                        <p>View, release, or reject merchant encashment requests.</p>
                    </div>

                    <a href="#pending-encashments" class="create-encash-btn">
                        <i class="fa-solid fa-plus"></i> Create Encashment
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table encash-table align-middle js-datatable" id="pendingEncashmentsTable" data-page-length="10">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Merchant Name</th>
                                <th>Merchant ID</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($pendingRequests as $request): ?>
                            <tr>
                                <?php $merchantName = gjc_user_label($db, (int) $request['user_id']); ?>
                                <td><?php echo gjc_e($request["reference_no"]); ?></td>
                                <td>
                                    <div class="encash-user-cell">
                                        <div class="encash-avatar">
                                            <?php echo gjc_e(strtoupper(substr($merchantName, 0, 1))); ?>
                                        </div>
                                        <strong><?php echo gjc_e($merchantName); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo 'MER-' . str_pad((string) $request['user_id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td class="amount-text"><?php echo gjc_money($request["amount"]); ?></td>
                                <td><span class="method-pill"><?php echo gjc_e($request["method"]); ?></span></td>
                                <td><?php echo gjc_e(date('M d, h:i A', strtotime($request["created_at"]))); ?></td>
                                <td>
                                    <div class="encash-actions">
                                        <button type="button" class="release-btn"
                                            onclick="releaseEncashment(<?php echo (int) $request['id']; ?>, <?php echo (int) $request['merchant_wallet_id']; ?>, <?php echo (float) $request['amount']; ?>)">Release</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            </section>

            <section class="encash-panel">

                <div class="encash-panel-header">
                    <div>
                        <h3>Recent Encashment History</h3>
                        <p>Latest released, rejected, and processing merchant withdrawals.</p>
                    </div>

                    <a href="<?= ADMIN_URL ?>/encashment_history.php" class="history-link">View All</a>
                </div>

                <div class="table-responsive">
                    <table class="table encash-table align-middle js-datatable" id="encashmentHistoryTable" data-page-length="10">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Merchant Name</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($encashmentHistory as $history): ?>
                            <tr>
                                <td><?php echo gjc_e($history["reference_no"]); ?></td>
                                <td><?php echo gjc_e(gjc_user_label($db, (int) $history['user_id'])); ?></td>
                                <td class="amount-text"><?php echo gjc_money($history["amount"]); ?></td>
                                <td><span class="method-pill"><?php echo gjc_e($history["method"]); ?></span></td>
                                <td>
                                    <span class="encash-status <?php echo strtolower($history["status"]); ?>">
                                        <?php echo gjc_e(ucfirst($history["status"])); ?>
                                    </span>
                                </td>
                                <td><?php echo gjc_e(date('M d, h:i A', strtotime($history["created_at"]))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            </section>

        </main>

    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
    <?php require __DIR__ . '/../includes/partials/datatables_assets.php'; ?>

    <script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }

    async function releaseEncashment(encashmentId, merchantWalletId, amount) {
        if (!confirm("Release this encashment request?")) {
            return;
        }

        const form = new FormData();
        form.append("encashment_id", encashmentId);
        form.append("merchant_wallet_id", merchantWalletId);
        form.append("amount", amount);

        const response = await fetch("release_encashment.php", {
            method: "POST",
            body: form
        });
        const result = await response.json();
        alert(result.message || (result.success ? "Encashment released." : "Release failed."));
        if (result.success) {
            window.location.reload();
        }
    }
    </script>

</body>

</html>
