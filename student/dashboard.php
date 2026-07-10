<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['student']);

$currentUser = gjc_current_user($db);
$wallet      = gjc_student_wallet($db, $currentUser['id']);
$studentName = $currentUser['name'];
$studentID   = 'GJC-' . str_pad((string) $currentUser['id'], 5, '0', STR_PAD_LEFT);
$balance     = $wallet['balance'];
$totalSpent  = 0.0;
$totalTxns   = 0;
$status      = 'Active';

$transactions = [];
if ($wallet['id'] > 0 && gjc_table_exists($db, 'transactions')) {
    $stmt = $db->prepare(
        "SELECT reference_no, transaction_type, amount, created_at
           FROM transactions
          WHERE student_wallet_id = ?
          ORDER BY created_at DESC
          LIMIT 15"
    );
    $stmt->execute([$wallet['id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE student_wallet_id = ?");
    $countStmt->execute([$wallet['id']]);
    $totalTxns = (int) $countStmt->fetchColumn();

    $sumStmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM transactions
          WHERE student_wallet_id = ? AND transaction_type IN ('payment', 'voucher_payment')"
    );
    $sumStmt->execute([$wallet['id']]);
    $totalSpent = (float) $sumStmt->fetchColumn();
}

if (isset($_SESSION['force_change'])) {
    header('Location: ' . BASE_URL . '/change_password.php');
    exit();
}

// Money coming IN vs going OUT — drives the +/− sign + colour of each row.
$incomingTypes = ['cash_in', 'topup', 'top_up'];
$txnMeta = static function (string $type) use ($incomingTypes): array {
    return [
        'incoming' => in_array($type, $incomingTypes, true),
        'label'    => ucwords(str_replace('_', ' ', $type)),
    ];
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=58">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
                <a href="<?= DASHBOARD_URL ?>" class="active">
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
                <a href="<?= STUDENT_URL ?>/history.php">
                    <i class="fa-solid fa-receipt student-nav-icon"></i>
                    <span class="student-nav-text">History</span>
                </a>
                <a href="<?= STUDENT_URL ?>/profile.php">
                    <i class="fa-solid fa-user student-nav-icon"></i>
                    <span class="student-nav-text">Profile</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="student-logout" onclick="openLogoutModal(event);">
                <i class="fa-solid fa-arrow-right-from-bracket student-logout-icon"></i>
                <span>Logout</span>
            </a>

        </aside>
        <?php require __DIR__ . '/../includes/partials/logout_modal.php'; ?>

        <main class="student-main">

            <header class="student-topbar">
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">&#9776;</button>
                <div>
                    <h1>My Wallet</h1>
                    <p>Send, withdraw, and pay — all from one place.</p>
                </div>
                <div class="student-user">
                    <span><?= gjc_e($studentName) ?></span>
                    <div class="student-avatar"><?= strtoupper(substr($studentName, 0, 1)) ?></div>
                </div>
            </header>

            <!-- Wallet + Quick Actions -->
            <section class="row g-4 mb-4">
                <div class="col-12 col-xl-8">
                    <div class="student-wallet-card h-100">
                        <div>
                            <span>Available Balance</span>
                            <h2><span id="walletGencoinValue"><?= number_format($balance / 10, 1) ?></span> <small style="font-size:22px;font-weight:800;opacity:.92">GenCoin</small></h2>
                            <div class="student-wallet-gc">
                                <i class="fa-solid fa-peso-sign"></i>
                                <span id="walletBalanceValue"><?= gjc_money($balance) ?></span>
                                <em>&#8369;10 = 1 GenCoin</em>
                            </div>
                            <p><?= gjc_e($studentName) ?> &middot; <?= gjc_e($studentID) ?></p>

                            <div class="student-wallet-actions">
                                <a href="<?= STUDENT_URL ?>/transfer.php"><i class="fa-solid fa-paper-plane me-2"></i>Send GenCoin</a>
                                <a href="<?= STUDENT_URL ?>/withdraw.php"><i class="fa-solid fa-money-bill-wave me-2"></i>Withdraw</a>
                            </div>
                        </div>
                        <div class="student-wallet-badge">Student</div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="student-quick-panel h-100">
                        <h3>Quick Actions</h3>
                        <p>Everything you need, one tap away.</p>
                        <div class="student-quick-actions">
                            <a href="<?= STUDENT_URL ?>/topup_request.php"><span><i class="fa-solid fa-circle-plus me-2"></i>Top-Up Wallet</span><b>&rsaquo;</b></a>
                            <a href="<?= STUDENT_URL ?>/scan.php"><span><i class="fa-solid fa-qrcode me-2"></i>Scan &amp; Pay</span><b>&rsaquo;</b></a>
                            <a href="<?= STUDENT_URL ?>/cart.php"><span><i class="fa-solid fa-cart-shopping me-2"></i>Shop Cart</span><b>&rsaquo;</b></a>
                            <a href="<?= STUDENT_URL ?>/history.php"><span><i class="fa-solid fa-receipt me-2"></i>Full History</span><b>&rsaquo;</b></a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Metrics -->
            <section class="row g-4 mb-4">
                <div class="col-12 col-md-4">
                    <div class="student-metric-card">
                        <div class="student-metric-icon"><i class="fa-solid fa-arrow-trend-up" style="font-size:22px;color:#fff"></i></div>
                        <span>Total Spent</span>
                        <h2 id="totalSpentValue"><?= gjc_money($totalSpent) ?></h2>
                        <p>All successful payments</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="student-metric-card">
                        <div class="student-metric-icon"><i class="fa-solid fa-list-ul" style="font-size:22px;color:#fff"></i></div>
                        <span>Total Transactions</span>
                        <h2 id="totalTxnsValue"><?= $totalTxns ?></h2>
                        <p>Wallet activity count</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="student-metric-card">
                        <div class="student-metric-icon"><i class="fa-solid fa-circle-check" style="font-size:22px;color:#fff"></i></div>
                        <span>Account Status</span>
                        <h2><?= $status ?></h2>
                        <p>Student wallet access</p>
                    </div>
                </div>
            </section>

            <!-- Recent Transactions -->
            <section class="student-premium-panel">
                <div class="student-panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Recent Transactions</h3>
                        <p>Latest payments, transfers, and top-up activity from your wallet.</p>
                    </div>
                    <a href="<?= STUDENT_URL ?>/history.php" class="student-view-btn">View All</a>
                </div>

                <?php if (empty($transactions)): ?>
                <div class="student-empty-state">
                    <div class="student-empty-icon">
                        <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                    </div>
                    <h3>No transactions yet</h3>
                    <p>Top up your wallet or scan a merchant QR to get started.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table student-premium-table align-middle js-datatable" id="studentDashboardTransactionsTable" data-page-length="8">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>GenCoin</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): $m = $txnMeta($t['transaction_type']); ?>
                            <tr>
                                <td style="font-family:monospace;font-size:13px"><?= gjc_e($t['reference_no']) ?></td>
                                <td><span class="student-type-pill"><?= gjc_e($m['label']) ?></span></td>
                                <td style="font-weight:800;color:<?= $m['incoming'] ? '#16a34a' : 'var(--text-main)' ?>">
                                    <?= $m['incoming'] ? '+' : '−' ?><?= gjc_money($t['amount']) ?>
                                </td>
                                <td style="font-weight:700;color:var(--emerald-800)"><?= number_format($t['amount'] / 10, 1) ?> GC</td>
                                <td><?= gjc_e(date('M d, Y · h:i A', strtotime($t['created_at']))) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

        </main>

    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
    <?php require __DIR__ . '/../includes/partials/datatables_assets.php'; ?>

    <script>
    function toggleStudentSidebar() {
        document.getElementById("studentSidebar").classList.toggle("collapsed");
    }
    document.querySelector(".student-menu a.active")?.scrollIntoView({ inline: "center", block: "nearest" });

    // Keep balance + metrics fresh (e.g. after a top-up or transfer lands).
    const walletBalanceValue = document.getElementById("walletBalanceValue");
    const walletGencoinValue = document.getElementById("walletGencoinValue");
    const totalSpentValue    = document.getElementById("totalSpentValue");
    const totalTxnsValue      = document.getElementById("totalTxnsValue");

    async function refreshWalletStats() {
        try {
            const fd = new FormData();
            fd.append("action", "get_wallet_stats");
            const res  = await fetch("<?= STUDENT_URL ?>/api/wallet.php", { method: "POST", body: fd });
            const data = await res.json();
            if (!data.success) return;

            const php = Number(data.balance);
            if (walletGencoinValue) walletGencoinValue.textContent = (php / 10).toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 });
            if (walletBalanceValue) walletBalanceValue.innerHTML   = "&#8369;" + php.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (totalSpentValue)    totalSpentValue.innerHTML      = "&#8369;" + Number(data.total_spent).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (totalTxnsValue)     totalTxnsValue.textContent     = data.total_txns;
        } catch (error) {
            // Keep the last known values on a transient network error.
        }
    }
    setInterval(refreshWalletStats, 5000);
    </script>

</body>

</html>
