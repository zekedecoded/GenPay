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
    $totalTxns = count($transactions);
    $sumStmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM transactions
          WHERE student_wallet_id = ? AND transaction_type = 'payment'"
    );
    $sumStmt->execute([$wallet['id']]);
    $totalSpent = (float) $sumStmt->fetchColumn();
}
?>

<?php
// session_start();

if (isset($_SESSION['force_change'])) {
    header('Location: ' . BASE_URL . '/change_password.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Student Dashboard | EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=10">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">

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
                    <h4>GJC EduPay</h4>
                    <span>Student Portal</span>
                </div>
            </div>

            <nav class="student-menu">
                <a href="<?= STUDENT_URL ?>/dashboard.php" class="active">
                    <img src="<?= ICONS_URL ?>/dashboard.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">Dashboard</span>
                </a>

                <a href="<?= STUDENT_URL ?>/scan.php">
                    <img src="<?= ICONS_URL ?>/qr.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">Scan &amp; Pay</span>
                </a>

                <a href="<?= STUDENT_URL ?>/history.php">
                    <img src="<?= ICONS_URL ?>/transactions.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">History</span>
                </a>

                <a href="<?= STUDENT_URL ?>/profile.php">
                    <img src="<?= ICONS_URL ?>/users.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">Profile</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="student-logout">
                <img src="<?= ICONS_URL ?>/logout.png" class="student-logout-icon" alt="">
                <span>Logout</span>
            </a>

        </aside>

        <main class="student-main">

            <header class="student-topbar">
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">☰</button>

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
                        <h2><?php echo gjc_money($balance); ?></h2>
                        <p><?php echo gjc_e($studentName); ?> &middot; <?php echo gjc_e($studentID); ?></p>

                        <div class="student-wallet-actions">
                            <a href="<?= STUDENT_URL ?>/scan.php">Scan &amp; Pay</a>
                            <a href="<?= STUDENT_URL ?>/topup_request.php">Top-Up</a>
                        </div>
                    </div>

                    <div class="student-wallet-badge">Student</div>
                </div>

                <div class="student-quick-panel">
                    <h3>Quick Actions</h3>
                    <p>Use your wallet for campus payments.</p>

                    <div class="student-quick-actions">
                        <a href="<?= STUDENT_URL ?>/scan.php">
                            <span>Scan Merchant QR</span>
                            <b>›</b>
                        </a>

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
                        <h2><?php echo gjc_money($totalSpent); ?></h2>
                        <p>All successful payments</p>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="student-metric-card">
                        <div class="student-metric-icon">
                            <img src="<?= ICONS_URL ?>/transactions.png" alt="">
                        </div>
                        <span>Total Transactions</span>
                        <h2><?php echo $totalTxns; ?></h2>
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
            // ── Economy Status (student view) ────────────────
            $ce       = new CirculationEngine($db);
            $ceSnap   = $ce->getCirculationSnapshot();
            $ceCap    = max((float)($ceSnap['cap']   ?? 1), 0.01);
            $ceVault  = (float)($ceSnap['vault']      ?? 0);
            $ceBalanced = abs((float)($ceSnap['circulation_drift'] ?? 0)) < 0.01;
            // Student wallet share of cap
            $studShare = $ceCap > 0 ? round(((float)($ceSnap['student_wallets_total'] ?? 0) / $ceCap) * 100, 1) : 0;
            $vaultShare= $ceCap > 0 ? round(($ceVault / $ceCap) * 100, 1) : 0;
            ?>

            <section class="st-economy-panel mb-4">

                <div class="st-economy-header">
                    <div class="st-economy-title-row">
                        <span class="st-economy-pill">💰 System Economy Status</span>
                        <span class="st-econ-badge <?= $ceBalanced ? 'st-econ-ok' : 'st-econ-err' ?>">
                            <?= $ceBalanced
                                ? '<span class="st-dot st-dot-green"></span> Economy Balanced'
                                : '<span class="st-dot st-dot-red st-pulse"></span> Under Review' ?>
                        </span>
                    </div>
                    <p class="st-economy-sub">The GJC EduPay campus economy is a closed system — every peso is tracked and accounted for.</p>
                </div>

                <div class="st-economy-grid">

                    <!-- Circulation Cap -->
                    <div class="st-econ-card st-econ-cap">
                        <div class="st-econ-card-glow"></div>
                        <div class="st-econ-icon">
                            <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                        </div>
                        <span class="st-econ-label">Circulation Cap</span>
                        <div class="st-econ-value">₱<?= number_format($ceCap, 2) ?></div>
                        <div class="st-econ-desc">Total authorized points in the system</div>
                    </div>

                    <!-- Vault Reserve -->
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

                    <!-- Student Pool -->
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

                    <!-- Balance Integrity -->
                    <div class="st-econ-card <?= $ceBalanced ? 'st-econ-healthy' : 'st-econ-warn' ?>">
                        <div class="st-econ-card-glow"></div>
                        <div class="st-econ-icon">
                            <img src="<?= ICONS_URL ?>/transactions.png" alt="">
                        </div>
                        <span class="st-econ-label">Economy Health</span>
                        <div class="st-econ-value"><?= $ceBalanced ? '✓ Healthy' : '⚠ Review' ?></div>
                        <div class="st-econ-desc">
                            <?= $ceBalanced
                                ? 'All pools are in balance. Transactions are safe.'
                                : 'Economy is under review. Contact the cashier.' ?>
                        </div>
                    </div>

                </div>

                <!-- Tip row -->
                <div class="st-economy-tip">
                    <span>💡</span>
                    <span>Your wallet balance is part of this closed-loop economy. Points can only move — they are never created during a transaction.</span>
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
                    <table class="table student-premium-table align-middle">
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

    <script>
    function toggleStudentSidebar() {
        document.getElementById("studentSidebar").classList.toggle("collapsed");
    }
    </script>

</body>

</html>
