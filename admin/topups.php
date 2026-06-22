<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);
gjc_ensure_operational_tables($db);

$pendingRequests = (int) $db->query("SELECT COUNT(*) FROM topup_requests WHERE status = 'pending'")->fetchColumn();
$loadedToday = (float) $db->query("SELECT COALESCE(SUM(amount), 0) FROM topup_requests WHERE status = 'approved' AND DATE(approved_at) = CURDATE()")->fetchColumn();
$requestQueue = $pendingRequests;

$pendingTopups = $db->query(
    "SELECT * FROM topup_requests
      WHERE status = 'pending'
      ORDER BY created_at ASC
      LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

$topupHistory = $db->query(
    "SELECT * FROM topup_requests
      ORDER BY created_at DESC
      LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'topups';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <title>Top-ups | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=3">
    <link rel="stylesheet" href="<?= CSS_URL ?>/topups.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/gjc-clear.css?v=1">
</head>

<body>

    <div class="admin-layout">

        <?php require __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

        <main class="admin-main">

            <header class="topbar">
                <button class="menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>

                <div>
                    <h1>Top-ups</h1>
                    <p>Review pending requests, process wallet loads, and monitor recent top-up activity.</p>
                </div>

                <div class="admin-user">
                    <span>Admin</span>
                    <div class="avatar">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                </div>
            </header>

            <section class="topup-stats-grid mb-4">

                <div class="topup-stat-card">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <span>Pending Requests</span>
                    <h2><?php echo $pendingRequests; ?></h2>
                    <p>Awaiting cashier approval</p>
                </div>

                <div class="topup-stat-card">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <span>Loaded Today</span>
                    <h2><?php echo gjc_money($loadedToday); ?></h2>
                    <p>Total wallet load volume</p>
                </div>

                <div class="topup-stat-card">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                    </div>
                    <span>Top-up Request Queue</span>
                    <h2><?php echo $requestQueue; ?></h2>
                    <p>Requests waiting in queue</p>
                </div>

            </section>

            <section class="topup-panel mb-4" id="pending-topups">

                <div class="topup-panel-header">
                    <div>
                        <h3>Pending Requests</h3>
                        <p>Approve, reject, or view details of incoming top-up requests.</p>
                    </div>

                    <a href="#pending-topups" class="create-topup-btn">
                        <i class="fa-solid fa-plus"></i> Create Top-up
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table topup-table align-middle js-datatable" id="pendingTopupsTable" data-page-length="10">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Name</th>
                                <th>School ID</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($pendingTopups as $topup): ?>
                            <tr>
                                <?php $topupName = gjc_user_label($db, (int) $topup['user_id']); ?>
                                <td><?php echo gjc_e($topup["reference_no"]); ?></td>
                                <td>
                                    <div class="topup-user-cell">
                                        <div class="topup-avatar">
                                            <?php echo gjc_e(strtoupper(substr($topupName, 0, 1))); ?>
                                        </div>
                                        <strong><?php echo gjc_e($topupName); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo 'GJC-' . str_pad((string) $topup['user_id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td class="amount-text"><?php echo gjc_money($topup["amount"]); ?></td>
                                <td><span class="method-pill"><?php echo gjc_e($topup["payment_method"]); ?></span></td>
                                <td><?php echo gjc_e(date('M d, h:i A', strtotime($topup["created_at"]))); ?></td>
                                <td>
                                    <div class="topup-actions">
                                        <button type="button" class="approve-btn"
                                            onclick="approveTopup(<?php echo (int) $topup['id']; ?>, <?php echo (int) $topup['student_wallet_id']; ?>, <?php echo (float) $topup['amount']; ?>)">Approve</button>
                                        <button type="button" class="reject-btn"
                                            onclick="rejectTopup(<?php echo (int) $topup['id']; ?>)">Reject</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            </section>

            <section class="topup-panel">

                <div class="topup-panel-header">
                    <div>
                        <h3>Recent Top-up History</h3>
                        <p>Latest completed, rejected, and processing wallet load records.</p>
                    </div>

                    <a href="<?= ADMIN_URL ?>/topup_history.php" class="history-link">View All</a>
                </div>

                <div class="table-responsive">
                    <table class="table topup-table align-middle js-datatable" id="topupHistoryTable" data-page-length="10">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Name</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($topupHistory as $history): ?>
                            <tr>
                                <td><?php echo gjc_e($history["reference_no"]); ?></td>
                                <td><?php echo gjc_e(gjc_user_label($db, (int) $history['user_id'])); ?></td>
                                <td class="amount-text"><?php echo gjc_money($history["amount"]); ?></td>
                                <td><span class="method-pill"><?php echo gjc_e($history["payment_method"]); ?></span></td>
                                <td>
                                    <span class="topup-status <?php echo strtolower($history["status"]); ?>">
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
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?= JS_URL ?>/admin_datatables.js"></script>

    <script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }

    async function approveTopup(topupId, studentWalletId, amount) {
        if (!confirm("Approve this top-up request?")) {
            return;
        }

        const form = new FormData();
        form.append("topup_id", topupId);
        form.append("student_wallet_id", studentWalletId);
        form.append("amount", amount);

        const response = await fetch("approve_topup.php", {
            method: "POST",
            body: form
        });
        const result = await response.json();
        alert(result.message || (result.success ? "Top-up approved." : "Top-up failed."));
        if (result.success) {
            window.location.reload();
        }
    }

    async function rejectTopup(topupId) {
        if (!confirm("Reject this top-up request?")) {
            return;
        }

        const form = new FormData();
        form.append("topup_id", topupId);

        const response = await fetch("reject_topup.php", {
            method: "POST",
            body: form
        });
        const result = await response.json();
        alert(result.message || (result.success ? "Top-up rejected." : "Reject failed."));
        if (result.success) {
            window.location.reload();
        }
    }
    </script>

</body>

</html>
