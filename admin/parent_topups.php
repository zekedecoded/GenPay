<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);
gjc_ensure_parent_schema($db);
gjc_ensure_parent_wallet_schema($db);

$pendingCount = (int) $db->query("SELECT COUNT(*) FROM parent_topup_requests WHERE status = 'pending'")->fetchColumn();
$loadedToday  = (float) $db->query("SELECT COALESCE(SUM(credited_amount), 0) FROM parent_topup_requests WHERE status = 'approved' AND DATE(processed_at) = CURDATE()")->fetchColumn();

$pendingTopups = $db->query(
    "SELECT ptr.*, u.first_name, u.last_name
       FROM parent_topup_requests ptr
       JOIN parents p ON p.id = ptr.parent_id
       JOIN users u ON u.userID = p.user_id
      WHERE ptr.status = 'pending'
      ORDER BY ptr.requested_at ASC
      LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

$topupHistory = $db->query(
    "SELECT ptr.*, u.first_name, u.last_name
       FROM parent_topup_requests ptr
       JOIN parents p ON p.id = ptr.parent_id
       JOIN users u ON u.userID = p.user_id
      ORDER BY ptr.requested_at DESC
      LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$currentPage = 'parent_topups';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <title>Parent Top-ups | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=17">
    <link rel="stylesheet" href="<?= CSS_URL ?>/topups.css?v=4">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/gjc-clear.css?v=12">
</head>

<body class="gp-theme">

    <div class="admin-layout">

        <?php require __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

        <main class="admin-main">

            <header class="topbar">
                <button class="menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>

                <div>
                    <h1>Parent Top-ups</h1>
                    <p>Review pending parent wallet top-up requests and monitor recent activity.</p>
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
                    <h2><?= $pendingCount ?></h2>
                    <p>Awaiting finance approval</p>
                </div>

                <div class="topup-stat-card">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <span>Loaded Today</span>
                    <h2><?= gjc_money($loadedToday) ?></h2>
                    <p>Total parent wallet load volume</p>
                </div>

            </section>

            <section class="topup-panel mb-4" id="pending-parent-topups">

                <div class="topup-panel-header">
                    <div>
                        <h3>Pending Requests</h3>
                        <p>Approve or reject incoming parent wallet top-up requests.</p>
                    </div>

                    <a href="<?= ADMIN_URL ?>/topups.php" class="create-topup-btn">
                        <i class="fa-solid fa-paper-plane"></i> Send GenCoin
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table topup-table align-middle js-datatable" id="pendingParentTopupsTable" data-page-length="10">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Parent</th>
                                <th>Amount</th>
                                <th>Source</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($pendingTopups as $topup):
                                $parentName = trim($topup['first_name'] . ' ' . $topup['last_name']);
                            ?>
                            <tr>
                                <td><?= $e($topup['reference_no']) ?></td>
                                <td>
                                    <div class="topup-user-cell">
                                        <div class="topup-avatar"><?= $e(strtoupper(substr($parentName, 0, 1))) ?></div>
                                        <strong><?= $e($parentName) ?></strong>
                                    </div>
                                </td>
                                <td class="amount-text"><?= gjc_money((float) $topup['amount']) ?></td>
                                <td><span class="method-pill"><?= $e(ucfirst($topup['source'])) ?></span></td>
                                <td><?= $e(date('M d, h:i A', strtotime($topup['requested_at']))) ?></td>
                                <td>
                                    <div class="topup-actions">
                                        <button type="button" class="approve-btn" onclick="approveParentTopup(<?= (int) $topup['id'] ?>)">Approve</button>
                                        <button type="button" class="reject-btn" onclick="rejectParentTopup(<?= (int) $topup['id'] ?>)">Reject</button>
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
                        <h3>Recent History</h3>
                        <p>Latest approved, rejected, and cancelled parent top-up records.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table topup-table align-middle js-datatable" id="parentTopupHistoryTable" data-page-length="10">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Parent</th>
                                <th>Amount</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($topupHistory as $history):
                                $parentName = trim($history['first_name'] . ' ' . $history['last_name']);
                            ?>
                            <tr>
                                <td><?= $e($history['reference_no']) ?></td>
                                <td><?= $e($parentName) ?></td>
                                <td class="amount-text"><?= gjc_money((float) $history['amount']) ?></td>
                                <td><span class="method-pill"><?= $e(ucfirst($history['source'])) ?></span></td>
                                <td><span class="topup-status <?= strtolower($history['status']) ?>"><?= $e(ucfirst($history['status'])) ?></span></td>
                                <td><?= $e(date('M d, h:i A', strtotime($history['requested_at']))) ?></td>
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

    const PARENT_TOPUPS_API = 'api/parent_topups.php';

    async function approveParentTopup(id) {
        if (!confirm('Approve this parent top-up request?')) return;
        try {
            const res = await fetch(PARENT_TOPUPS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'approve', id: id }),
            });
            const data = await res.json();
            alert(data.message || (data.success ? 'Approved.' : (data.error || 'Failed.')));
            if (data.success) window.location.reload();
        } catch (err) {
            alert('Network error. Please try again.');
        }
    }

    async function rejectParentTopup(id) {
        if (!confirm('Reject this parent top-up request?')) return;
        try {
            const res = await fetch(PARENT_TOPUPS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reject', id: id }),
            });
            const data = await res.json();
            alert(data.message || (data.success ? 'Rejected.' : (data.error || 'Failed.')));
            if (data.success) window.location.reload();
        } catch (err) {
            alert('Network error. Please try again.');
        }
    }
    </script>

</body>

</html>
