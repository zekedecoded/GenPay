<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/CirculationEngine.php';

gjc_require_role(['student']);

$currentUser = gjc_current_user($db);
$wallet = gjc_student_wallet($db, $currentUser['id']);
$studentName = $currentUser['name'];
$studentID = 'GJC-' . str_pad((string) $currentUser['id'], 5, '0', STR_PAD_LEFT);
$balance = $wallet['balance'];
$totalSpent = 0;
$totalTxns = 0;
$status = "Active";

$transactions = [];
if ($wallet['id'] > 0 && gjc_table_exists($db, 'transactions')) {
    $stmt = $db->prepare(
        "SELECT reference_no, transaction_type, amount, created_at
           FROM transactions
          WHERE student_wallet_id = ?
          ORDER BY created_at DESC
          LIMIT 8"
    );
    $stmt->execute([$wallet['id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM transactions WHERE student_wallet_id = ?"
    );
    $countStmt->execute([$wallet['id']]);
    $totalTxns = (int) $countStmt->fetchColumn();

    $sumStmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM transactions
          WHERE student_wallet_id = ? AND transaction_type IN ('payment', 'voucher_payment')"
    );
    $sumStmt->execute([$wallet['id']]);
    $totalSpent = (float) $sumStmt->fetchColumn();
}
?>

<?php


if (isset($_SESSION['force_change'])) {
    header('Location: ' . BASE_URL . '/change_password.php');
    exit();
}
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
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=51">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
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
                    <span class="student-nav-text">Transfer Tokens</span>
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

            <a href="<?= BASE_URL ?>/logout.php" class="student-logout"
               onclick="openLogoutModal(event);">
                <i class="fa-solid fa-arrow-right-from-bracket student-logout-icon"></i>
                <span>Logout</span>
            </a>

        </aside>
        <?php require __DIR__ . '/../includes/partials/logout_modal.php'; ?>

        <main class="student-main">

            <header class="student-topbar">
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">Menu</button>

                <div>
                    <h1>My Wallet</h1>
                    <p>View your balance, scan payments, and track wallet activity.</p>
                </div>

                <div class="student-user">
                    <span><?php echo gjc_e($studentName); ?></span>
                    <div class="student-avatar">
                        <?php echo strtoupper(substr($studentName, 0, 1)); ?>
                    </div>
                </div>
            </header>

            <section class="student-wallet-grid mb-4">

                <div class="student-wallet-card">
                    <div>
                        <span>Available Balance</span>
                        <h2 id="walletBalanceValue"><?php echo gjc_money($balance); ?></h2>
                        <div class="student-wallet-gc">
                            <i class="fa-solid fa-coins" aria-hidden="true"></i>
                            <span id="walletGencoinValue"><?php echo number_format($balance / 10, 1); ?></span> GenCoin
                            <em>&#8369;10 = 1 GenCoin</em>
                        </div>
                        <p><?php echo gjc_e($studentName); ?> &middot; <?php echo gjc_e($studentID); ?></p>

                        <div class="student-wallet-actions">
                            <a href="<?= STUDENT_URL ?>/cart.php">Shop Cart</a>
                            <a href="<?= STUDENT_URL ?>/topup_request.php">Top-Up</a>
                        </div>
                    </div>

                    <div class="student-wallet-badge">Student</div>
                </div>

                <div class="student-quick-panel">
                    <h3>Quick Actions</h3>
                    <p>Use your wallet for campus payments.</p>

                    <div class="student-quick-actions">
                        <a href="<?= STUDENT_URL ?>/topup_request.php">
                            <span>Request Top-Up</span>
                            <b>›</b>
                        </a>

                        <a href="<?= STUDENT_URL ?>/history.php">
                            <span>Full History</span>
                            <b>›</b>
                        </a>
                    </div>
                </div>

            </section>

            <section class="row g-4 mb-4">

                <div class="col-12 col-md-4">
                    <div class="student-metric-card">
                        <div class="student-metric-icon">
                            <img src="<?= ICONS_URL ?>/payment.png" alt="">
                        </div>
                        <span>Total Spent</span>
                        <h2 id="totalSpentValue"><?php echo gjc_money($totalSpent); ?></h2>
                        <p>All successful payments</p>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="student-metric-card">
                        <div class="student-metric-icon">
                            <img src="<?= ICONS_URL ?>/transactions.png" alt="">
                        </div>
                        <span>Total Transactions</span>
                        <h2 id="totalTxnsValue"><?php echo $totalTxns; ?></h2>
                        <p>Wallet activity count</p>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="student-metric-card">
                        <div class="student-metric-icon">
                            <img src="<?= ICONS_URL ?>/users.png" alt="">
                        </div>
                        <span>Account Status</span>
                        <h2><?php echo $status; ?></h2>
                        <p>Student wallet access</p>
                    </div>
                </div>

            </section>

            <?php
            $ce = new CirculationEngine($db);
            $ceSnap = $ce->getCirculationSnapshot();
            $ceCap = max((float) ($ceSnap['cap'] ?? 1), 0.01);
            $ceVault = (float) ($ceSnap['vault'] ?? 0);
            $ceCirculation = (float) ($ceSnap['total_in_circulation'] ?? 0);
            $ceStudentTotal = (float) ($ceSnap['student_wallets_total'] ?? 0);
            $ceMerchantTotal = (float) ($ceSnap['merchant_wallets_total'] ?? 0);
            $ceVoucherTotal = (float) ($ceSnap['active_vouchers_total'] ?? 0);
            $ceBalanced = abs((float) ($ceSnap['circulation_drift'] ?? 0)) < 0.01;
            $studShare = min(100, max(0, round(($ceStudentTotal / $ceCap) * 100, 1)));
            $vaultShare = min(100, max(0, round(($ceVault / $ceCap) * 100, 1)));
            ?>

            <section class="student-premium-panel st-simple-economy mb-4">
                <div class="student-panel-header">
                    <div>
                        <h3>System Economy</h3>
                        <p>Quick status of the campus wallet circulation.</p>
                    </div>
                    <span class="st-simple-status <?= $ceBalanced ? 'is-ok' : 'is-review' ?>">
                        <?= $ceBalanced ? 'Balanced' : 'Review' ?>
                    </span>
                </div>

                <div class="st-simple-economy-main">
                    <div>
                        <span>Current Circulation</span>
                        <strong><?= gjc_money($ceCirculation) ?></strong>
                        <small>of <?= gjc_money($ceCap) ?> authorized cap</small>
                    </div>
                    <div class="st-simple-bar">
                        <div style="width: <?= $cePct($ceCirculation) ?>%"></div>
                    </div>
                </div>

                <div class="st-simple-economy-grid">
                    <div>
                        <span>Student Wallets</span>
                        <strong><?= gjc_money($ceStudentTotal) ?></strong>
                        <small><?= $studShare ?>% of cap</small>
                    </div>
                    <div>
                        <span>Cashier Vault</span>
                        <strong><?= gjc_money($ceVault) ?></strong>
                        <small><?= $vaultShare ?>% available</small>
                    </div>
                    <div>
                        <span>Merchants + Vouchers</span>
                        <strong><?= gjc_money($ceMerchantTotal + $ceVoucherTotal) ?></strong>
                        <small>Payment pool</small>
                    </div>
                </div>
            </section>

            <section class="st-economy-panel mb-4" hidden>

                <div class="st-economy-header">
                    <div class="st-economy-title-row">
                        <span class="st-economy-pill"> System Economy Status</span>
                        <span class="st-econ-badge <?= $ceBalanced ? 'st-econ-ok' : 'st-econ-err' ?>">
                            <?= $ceBalanced
                                ? '<span class="st-dot st-dot-green"></span> Economy Balanced'
                                : '<span class="st-dot st-dot-red st-pulse"></span> Under Review' ?>
                        </span>
                    </div>
                    <p class="st-economy-sub">The GenPay campus economy is a closed system - every peso is tracked and accounted for.</p>
                </div>

                <div class="st-economy-grid">

                    
                    <div class="st-econ-card st-econ-cap">
                        <div class="st-econ-card-glow"></div>
                        <div class="st-econ-icon">
                            <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                        </div>
                        <span class="st-econ-label">Circulation Cap</span>
                        <div class="st-econ-value">₱<?= number_format($ceCap, 2) ?></div>
                        <div class="st-econ-desc">Total authorized points in the system</div>
                    </div>

                    
                    <div class="st-econ-card st-econ-vault">
                        <div class="st-econ-card-glow"></div>
                        <div class="st-econ-icon">
                            <img src="<?= ICONS_URL ?>/pending-topups.png" alt="">
                        </div>
                        <span class="st-econ-label">Cashier Vault</span>
                        <div class="st-econ-value">₱<?= number_format($ceVault, 2) ?></div>
                        <div class="st-econ-bar">
                            <div class="st-econ-bar-fill" style="width:<?= $vaultShare ?>%"></div>
                        </div>
                        <div class="st-econ-desc"><?= $vaultShare ?>% of cap · Available for top-ups</div>
                    </div>

                    
                    <div class="st-econ-card st-econ-students">
                        <div class="st-econ-card-glow"></div>
                        <div class="st-econ-icon">
                            <img src="<?= ICONS_URL ?>/students.png" alt="">
                        </div>
                        <span class="st-econ-label">Student Wallets</span>
                        <div class="st-econ-value">₱<?= number_format((float)($ceSnap['student_wallets_total'] ?? 0), 2) ?></div>
                        <div class="st-econ-bar">
                            <div class="st-econ-bar-fill" style="width:<?= $studShare ?>%"></div>
                        </div>
                        <div class="st-econ-desc"><?= $studShare ?>% of cap · All student balances</div>
                    </div>

                    
                    <div class="st-econ-card <?= $ceBalanced ? 'st-econ-healthy' : 'st-econ-warn' ?>">
                        <div class="st-econ-card-glow"></div>
                        <div class="st-econ-icon">
                            <img src="<?= ICONS_URL ?>/transactions.png" alt="">
                        </div>
                        <span class="st-econ-label">Economy Health</span>
                        <div class="st-econ-value"><?= $ceBalanced ? ' Healthy' : ' Review' ?></div>
                        <div class="st-econ-desc">
                            <?= $ceBalanced
                                ? 'All pools are in balance. Transactions are safe.'
                                : 'Economy is under review. Contact the cashier.' ?>
                        </div>
                    </div>

                </div>

                
                <div class="st-economy-tip">
                    <span>Your wallet balance is part of this closed-loop economy. Points can only move - they are never created during a transaction.</span>
                </div>

            </section>

            <section class="student-premium-panel">

                <div class="student-panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Recent Transactions</h3>
                        <p>Latest payments and top-up activity from your wallet.</p>
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
                                <th>Description</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo gjc_e($transaction["reference_no"]); ?></td>
                                <td><span class="student-type-pill"><?php echo gjc_e(ucwords(str_replace('_', ' ', $transaction["transaction_type"]))); ?></span></td>
                                <td><?php echo gjc_money($transaction["amount"]); ?></td>
                                <td><?php echo gjc_e(date('M d, h:i A', strtotime($transaction["created_at"]))); ?></td>
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

    // ── Live wallet stats — keeps balance/spent/transaction count fresh
    // without a manual reload (e.g. after a transfer or top-up lands). ────────
    const walletBalanceValue = document.getElementById("walletBalanceValue");
    const walletGencoinValue = document.getElementById("walletGencoinValue");
    const totalSpentValue = document.getElementById("totalSpentValue");
    const totalTxnsValue = document.getElementById("totalTxnsValue");

    async function refreshWalletStats() {
        try {
            const fd = new FormData();
            fd.append("action", "get_wallet_stats");
            const res = await fetch("<?= STUDENT_URL ?>/api/wallet.php", { method: "POST", body: fd });
            const data = await res.json();
            if (!data.success) return;

            walletBalanceValue.innerHTML = "&#8369;" + Number(data.balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (walletGencoinValue) walletGencoinValue.textContent = (Number(data.balance) / 10).toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 });
            totalSpentValue.innerHTML = "&#8369;" + Number(data.total_spent).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            totalTxnsValue.textContent = data.total_txns;
        } catch (error) {
            // Keep showing the last known values on a transient network error.
        }
    }

    setInterval(refreshWalletStats, 5000);
    </script>

</body>

</html>
