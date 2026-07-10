<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['student']);

$currentUser = gjc_current_user($db);
$wallet = gjc_student_wallet($db, $currentUser['id']);
$studentName = $currentUser['name'];

$currentBalance = (float) $wallet['balance'];
$totalReceived = 0.0;
$totalSpent = 0.0;
$transactions = [];

if ($wallet['id'] > 0 && gjc_table_exists($db, 'transactions')) {
    // Aggregates run over the whole ledger (not the capped fetch below),
    // so the totals stay correct even past 200 lifetime transactions.
    $spentStmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM transactions
          WHERE student_wallet_id = ? AND transaction_type IN ('payment', 'voucher_payment')"
    );
    $spentStmt->execute([$wallet['id']]);
    $totalSpent = (float) $spentStmt->fetchColumn();

    $receivedStmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM transactions
          WHERE student_wallet_id = ? AND transaction_type IN ('cash_in', 'topup', 'refund')"
    );
    $receivedStmt->execute([$wallet['id']]);
    $totalReceived = (float) $receivedStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT reference_no, transaction_type, amount, status, notes, created_at
           FROM transactions
          WHERE student_wallet_id = ?
          ORDER BY created_at DESC, id DESC
          LIMIT 200"
    );
    $stmt->execute([$wallet['id']]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = (string) ($row['transaction_type'] ?? '');
        $amount = (float) ($row['amount'] ?? 0);

        $transactions[] = [
            'ref' => (string) ($row['reference_no'] ?: 'N/A'),
            'desc' => trim((string) ($row['notes'] ?? '')) ?: gjc_transaction_type_label($type),
            'type' => gjc_transaction_type_label($type),
            'amount' => $amount,
            'status' => gjc_transaction_status_label((string) ($row['status'] ?? 'completed')),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'date' => !empty($row['created_at']) ? date('M d, Y h:i A', strtotime((string) $row['created_at'])) : 'N/A',
        ];
    }
}

if (gjc_table_exists($db, 'topup_requests')) {
    $stmt = $db->prepare(
        "SELECT reference_no, amount, payment_method, status, created_at
           FROM topup_requests
          WHERE user_id = ? AND status <> 'approved'
          ORDER BY created_at DESC, id DESC
          LIMIT 100"
    );
    $stmt->execute([$currentUser['id']]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $transactions[] = [
            'ref' => (string) ($row['reference_no'] ?: 'TOPUP-REQ'),
            'desc' => 'Payment method: ' . (string) ($row['payment_method'] ?? 'Cash at Cashier'),
            'type' => 'Top-up Request',
            'amount' => (float) ($row['amount'] ?? 0),
            'status' => gjc_transaction_status_label((string) ($row['status'] ?? 'pending')),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'date' => !empty($row['created_at']) ? date('M d, Y h:i A', strtotime((string) $row['created_at'])) : 'N/A',
        ];
    }
}

usort($transactions, function (array $a, array $b): int {
    return strcmp($b['created_at'], $a['created_at']);
});
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=58">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="student-layout">

        <aside class="student-sidebar" id="studentSidebar">

            <div class="student-brand">
                <div class="student-brand-logo">
                    <img src="<?= ICONS_URL ?>/GenDeJesusFavicon.png" alt="GJC Logo">
                </div>

                <div class="student-brand-text">
                    <h4>GenPay</h4>
                    <span>Student Portal</span>
                </div>
            </div>

            <nav class="student-menu">
                <a href="<?= DASHBOARD_URL ?>">
                    <i class="fa-solid fa-gauge-high student-nav-icon"></i>
                    <span class="student-nav-text">Dashboard</span>
                </a>

                <a href="<?= STUDENT_URL ?>/cart.php">
                    <i class="fa-solid fa-cart-shopping student-nav-icon"></i>
                    <span class="student-nav-text">Shop Cart</span>
                </a>

                <a href="<?= STUDENT_URL ?>/transfer.php">
                    <i class="fa-solid fa-money-bill-transfer student-nav-icon"></i>
                    <span class="student-nav-text">Send GenCoin</span>
                </a>

                <a href="<?= STUDENT_URL ?>/withdraw.php">
                    <i class="fa-solid fa-money-bill-wave student-nav-icon"></i>
                    <span class="student-nav-text">Withdraw</span>
                </a>

                <a href="<?= STUDENT_URL ?>/topup_request.php">
                    <i class="fa-solid fa-circle-plus student-nav-icon"></i>
                    <span class="student-nav-text">Top-Up</span>
                </a>

                <a href="<?= STUDENT_URL ?>/history.php" class="active">
                    <i class="fa-solid fa-receipt student-nav-icon"></i>
                    <span class="student-nav-text">History</span>
                </a>

                <a href="<?= STUDENT_URL ?>/profile.php">
                    <i class="fa-solid fa-user student-nav-icon"></i>
                    <span class="student-nav-text">Profile</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="student-logout"
               onclick="openLogoutModal(event);">
                <i class="fa-solid fa-arrow-right-from-bracket student-logout-icon"></i>
                <span>Logout</span>
            </a>

        </aside>
        <?php require __DIR__ . '/../includes/partials/logout_modal.php'; ?>

        <main class="student-main">

            <header class="student-topbar">
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">&#9776;</button>

                <div>
                    <h1>Transaction History</h1>
                    <p>Track all your wallet activity and payments.</p>
                </div>

                <div class="student-user">
                    <span><?php echo gjc_e($studentName); ?></span>
                    <div class="student-avatar">
                        <?php echo gjc_e(strtoupper(substr($studentName, 0, 1))); ?>
                    </div>
                </div>
            </header>

            <section class="student-history-stats mb-4">

                <div class="history-stat-card">
                    <span>Current Balance</span>
                    <h2 id="historyBalanceValue"><?php echo gjc_money($currentBalance); ?></h2>
                </div>

                <div class="history-stat-card">
                    <span>Total Received</span>
                    <h2 id="historyReceivedValue"><?php echo gjc_money($totalReceived); ?></h2>
                </div>

                <div class="history-stat-card">
                    <span>Total Spent</span>
                    <h2 id="historySpentValue"><?php echo gjc_money($totalSpent); ?></h2>
                </div>

            </section>

            <section class="student-premium-panel">

                <div class="student-panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3>All Transactions</h3>
                        <p>Complete list of your wallet activity.</p>
                    </div>

                    <span class="student-count">
                        <?php echo count($transactions); ?> Records
                    </span>
                </div>

                <?php if (empty($transactions)): ?>
                <div class="student-empty-state">
                    <div class="student-empty-icon">
                        <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                    </div>

                    <h3>No transactions yet</h3>
                    <p>Start using your wallet to see activity here.</p>
                </div>

                <?php else: ?>

                <div class="table-responsive">
                    <table class="table student-premium-table align-middle js-datatable" id="studentHistoryTable" data-page-length="10">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><?php echo gjc_e($t['ref']); ?></td>
                                <td><?php echo gjc_e($t['desc']); ?></td>
                                <td><span class="student-type-pill"><?php echo gjc_e($t['type']); ?></span></td>
                                <td><?php echo gjc_money($t['amount']); ?></td>
                                <td><span class="student-status"><?php echo gjc_e($t['status']); ?></span></td>
                                <td><?php echo gjc_e($t['date']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php endif; ?>

            </section>

        </main>

    </div>

    <?php require __DIR__ . '/../includes/partials/datatables_assets.php'; ?>
    <script>
    function toggleStudentSidebar() {
        document.getElementById("studentSidebar").classList.toggle("collapsed");
    }

    document.querySelector(".student-menu a.active")?.scrollIntoView({ inline: "center", block: "nearest" });

    // ── Live wallet stats — balance/received/spent stay current without a
    // manual reload. The transaction table itself is left to a normal reload
    // since it's a DataTables instance; swapping its rows via raw innerHTML
    // would desync DataTables' internal state (pagination/search/sorting). ──
    const historyBalanceValue = document.getElementById("historyBalanceValue");
    const historyReceivedValue = document.getElementById("historyReceivedValue");
    const historySpentValue = document.getElementById("historySpentValue");

    async function refreshHistoryStats() {
        try {
            const fd = new FormData();
            fd.append("action", "get_wallet_stats");
            const res = await fetch("<?= STUDENT_URL ?>/api/wallet.php", { method: "POST", body: fd });
            const data = await res.json();
            if (!data.success) return;

            historyBalanceValue.innerHTML = "&#8369;" + Number(data.balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            historyReceivedValue.innerHTML = "&#8369;" + Number(data.total_received).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            historySpentValue.innerHTML = "&#8369;" + Number(data.total_spent).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch (error) {
            // Keep showing the last known values on a transient network error.
        }
    }

    setInterval(refreshHistoryStats, 5000);
    </script>

</body>

</html>
