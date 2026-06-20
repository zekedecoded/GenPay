<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/CirculationEngine.php';

gjc_require_role(['merchant']);

$currentUser = gjc_current_user($db);
$ownerMerchId = gjc_merchant_owner_id($db, (int) $currentUser['id']);
$wallet = gjc_merchant_wallet($db, $ownerMerchId);
$currentBalance = $wallet['balance'];
$canEncash = !gjc_is_merchant_staff();
$todaysSales = 0;
$totalEarned = 0;
$encashmentStatus = "Available";
$earningTypes = [CirculationEngine::TXN_PAYMENT, CirculationEngine::TXN_VOUCHER_PAYMENT];
$earningTypePlaceholders = implode(', ', array_fill(0, count($earningTypes), '?'));

$recentSales = [];
if ($wallet['id'] > 0 && gjc_table_exists($db, 'transactions')) {
    $stmt = $db->prepare(
        "SELECT reference_no, transaction_type, amount, created_at
           FROM transactions
          WHERE merchant_wallet_id = ?
            AND transaction_type IN ({$earningTypePlaceholders})
          ORDER BY created_at DESC
          LIMIT 8"
    );
    $stmt->execute(array_merge([$wallet['id']], $earningTypes));
    $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $todayStmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM transactions
          WHERE merchant_wallet_id = ?
            AND transaction_type IN ({$earningTypePlaceholders})
            AND DATE(created_at) = CURDATE()"
    );
    $todayStmt->execute(array_merge([$wallet['id']], $earningTypes));
    $todaysSales = (float) $todayStmt->fetchColumn();

    $totalStmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM transactions
          WHERE merchant_wallet_id = ?
            AND transaction_type IN ({$earningTypePlaceholders})"
    );
    $totalStmt->execute(array_merge([$wallet['id']], $earningTypes));
    $totalEarned = (float) $totalStmt->fetchColumn();
}

$currentPage = 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Dashboard | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=11">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

    <div class="merchant-layout">

        <?php require __DIR__ . '/../includes/partials/' . (gjc_is_merchant_staff() ? 'sidebar_merchant_staff.php' : 'sidebar_merchant_admin.php'); ?>

        <main class="merchant-main">

            <header class="merchant-topbar">
                <button class="merchant-menu-btn" onclick="toggleMerchantSidebar()">Menu</button>

                <div>
                    <h1>Merchant Dashboard</h1>
                    <p>Monitor sales, balance, QR payments<?= $canEncash ? ', and encashment activity' : '' ?>.</p>
                </div>

                <div class="merchant-user">
                    <span><?php echo gjc_e($currentUser['name']); ?></span>
                    <div class="merchant-avatar">
                        <img src="<?= ICONS_URL ?>/store.png" alt="Merchant">
                    </div>
                </div>
            </header>

            <section class="row g-4 mb-4">

                <div class="col-12 col-md-6 col-xl-3">
                    <div class="merchant-metric-card">
                        <div class="merchant-metric-icon">
                            <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                        </div>
                        <span>Current Balance</span>
                        <h2><?php echo gjc_money($currentBalance); ?></h2>
                        <p><?= $canEncash ? 'Available for encashment' : 'Store wallet balance' ?></p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <div class="merchant-metric-card">
                        <div class="merchant-metric-icon">
                            <img src="<?= ICONS_URL ?>/volume.png" alt="">
                        </div>
                        <span>Today's Sales</span>
                        <h2><?php echo gjc_money($todaysSales); ?></h2>
                        <p>Today's received payments</p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <div class="merchant-metric-card">
                        <div class="merchant-metric-icon">
                            <img src="<?= ICONS_URL ?>/payment.png" alt="">
                        </div>
                        <span>Total Earned</span>
                        <h2><?php echo gjc_money($totalEarned); ?></h2>
                        <p>Lifetime merchant earnings</p>
                    </div>
                </div>

                <?php if ($canEncash): ?>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="merchant-metric-card">
                        <div class="merchant-metric-icon">
                            <img src="<?= ICONS_URL ?>/encashments.png" alt="">
                        </div>
                        <span>Encashment</span>
                        <h2><?php echo $encashmentStatus; ?></h2>
                        <p>Request at anytime</p>
                    </div>
                </div>
                <?php endif; ?>

            </section>

            <section class="row g-4 mb-4">

                <div class="col-12 col-xl-8">
                    <div class="merchant-premium-panel">
                        <div class="merchant-panel-header">
                            <div>
                                <h3>7-Day Sales</h3>
                                <p>Daily merchant wallet payment performance</p>
                            </div>
                        </div>

                        <div class="merchant-chart-box">
                            <canvas id="merchantSalesChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="merchant-premium-panel h-100">
                        <div class="merchant-panel-header">
                            <div>
                                <h3>Quick Actions</h3>
                                <p>Frequently used merchant tools</p>
                            </div>
                        </div>

                        <div class="merchant-quick-actions">
                            <a href="<?= MERCHANT_URL ?>/qrcode.php">
                                <span>Generate Item QR</span>
                                <b>›</b>
                            </a>

                            <?php if ($canEncash): ?>
                            <a href="<?= MERCHANT_URL ?>/encash.php">
                                <span>Request Encashment</span>
                                <b>›</b>
                            </a>
                            <?php endif; ?>

                            <a href="<?= MERCHANT_URL ?>/history.php">
                                <span>Full History</span>
                                <b>›</b>
                            </a>
                        </div>

                        <?php if ($canEncash): ?>
                        <div class="merchant-note">
                            Encash your balance at the <strong>Accountancy Office</strong>. Your wallet holds digital
                            receipts only.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </section>

            <?php
            
            $mce        = new CirculationEngine($db);
            $mceSnap    = $mce->getCirculationSnapshot();
            $mceCap     = max((float)($mceSnap['cap']                  ?? 1), 0.01);
            $mceVault   = (float)($mceSnap['vault']                    ?? 0);
            $mceMerch   = (float)($mceSnap['merchant_wallets_total']   ?? 0);
            $mceStudents= (float)($mceSnap['student_wallets_total']    ?? 0);
            $mceBalanced = abs((float)($mceSnap['circulation_drift']   ?? 0)) < 0.01;
            $mceMerchPct = $mceCap > 0 ? round(($mceMerch   / $mceCap) * 100, 1) : 0;
            $mceVaultPct = $mceCap > 0 ? round(($mceVault   / $mceCap) * 100, 1) : 0;
            $mceStudPct  = $mceCap > 0 ? round(($mceStudents/ $mceCap) * 100, 1) : 0;
            ?>

            
            <section class="me-section mb-4">

                
                <div class="me-header">
                    <div class="me-header-left">
                        <span class="me-pill">
                            <img src="<?= ICONS_URL ?>/merchants.png" alt="" class="me-pill-icon">  Economy Status
                        </span>
                        <span class="me-status-badge <?= $mceBalanced ? 'me-badge-ok' : 'me-badge-err' ?>">
                            <span class="me-dot <?= $mceBalanced ? 'me-dot-green' : 'me-dot-red me-pulse' ?>"></span>
                            <?= $mceBalanced ? 'System Balanced' : 'Under Review' ?>
                        </span>
                    </div>
                    <p class="me-header-sub">Live snapshot of the campus economy. Your wallet is part of this closed loop.</p>
                </div>

                
                <div class="me-hero-strip">
                    <div class="me-hero-stat">
                        <span>Circulation Cap</span>
                        <strong>₱<?= number_format($mceCap, 2) ?></strong>
                        <small>Total authorized supply</small>
                    </div>
                    <div class="me-hero-divider"></div>
                    <div class="me-hero-stat">
                        <span>Cashier Vault</span>
                        <strong>₱<?= number_format($mceVault, 2) ?></strong>
                        <small>Ready for top-ups</small>
                    </div>
                    <div class="me-hero-divider"></div>
                    <div class="me-hero-stat">
                        <span>Student Pool</span>
                        <strong>₱<?= number_format($mceStudents, 2) ?></strong>
                        <small>Spendable by students</small>
                    </div>
                    <div class="me-hero-divider"></div>
                    <div class="me-hero-stat me-hero-highlight">
                        <span>Merchant Pool</span>
                        <strong>₱<?= number_format($mceMerch, 2) ?></strong>
                        <small><?= $canEncash ? 'Pending encashment' : 'Store earnings pool' ?></small>
                    </div>
                    <div class="me-hero-divider"></div>
                    <div class="me-hero-stat">
                        <span>Economy Health</span>
                        <strong class="<?= $mceBalanced ? 'me-text-green' : 'me-text-red' ?>">
                            <?= $mceBalanced ? ' Healthy' : ' Review' ?>
                        </strong>
                        <small><?= $mceBalanced ? 'All pools balanced' : 'Contact finance office' ?></small>
                    </div>
                </div>

                
                <div class="me-pool-grid">

                    
                    <div class="me-pool-card me-pool-merchant">
                        <div class="me-pool-glow"></div>
                        <div class="me-pool-top">
                            <div class="me-pool-icon">
                                <img src="<?= ICONS_URL ?>/merchants.png" alt="">
                            </div>
                            <span class="me-pool-badge">Your Pool</span>
                        </div>
                        <div class="me-pool-label">Merchant Wallets Total</div>
                        <div class="me-pool-value">₱<?= number_format($mceMerch, 2) ?></div>
                        <div class="me-pool-bar"><div class="me-pool-bar-fill" style="width:<?= $mceMerchPct ?>%"></div></div>
                        <div class="me-pool-meta"><?= $mceMerchPct ?>% of cap · <?= $canEncash ? 'Encashable at any time' : 'Tracked for merchant admin' ?></div>
                    </div>

                    
                    <div class="me-pool-card me-pool-vault">
                        <div class="me-pool-glow"></div>
                        <div class="me-pool-top">
                            <div class="me-pool-icon">
                                <img src="<?= ICONS_URL ?>/pending-topups.png" alt="">
                            </div>
                        </div>
                        <div class="me-pool-label">Cashier Vault Reserve</div>
                        <div class="me-pool-value">₱<?= number_format($mceVault, 2) ?></div>
                        <div class="me-pool-bar"><div class="me-pool-bar-fill" style="width:<?= $mceVaultPct ?>%"></div></div>
                        <div class="me-pool-meta"><?= $mceVaultPct ?>% of cap · Available for reloads</div>
                    </div>

                    
                    <div class="me-pool-card me-pool-students">
                        <div class="me-pool-glow"></div>
                        <div class="me-pool-top">
                            <div class="me-pool-icon">
                                <img src="<?= ICONS_URL ?>/students.png" alt="">
                            </div>
                        </div>
                        <div class="me-pool-label">Student Wallets Total</div>
                        <div class="me-pool-value">₱<?= number_format($mceStudents, 2) ?></div>
                        <div class="me-pool-bar"><div class="me-pool-bar-fill" style="width:<?= $mceStudPct ?>%"></div></div>
                        <div class="me-pool-meta"><?= $mceStudPct ?>% of cap · Potential incoming payments</div>
                    </div>

                    
                    <?php if ($canEncash): ?>
                    <div class="me-pool-card me-pool-tip">
                        <div class="me-pool-glow"></div>
                        <div class="me-pool-top">
                            <div class="me-pool-icon">
                                <img src="<?= ICONS_URL ?>/encashments.png" alt="">
                            </div>
                        </div>
                        <div class="me-pool-label">Encashment Flow</div>
                        <div class="me-pool-value" style="font-size:16px;line-height:1.4">
                            Your wallet → Vault → Finance Office
                        </div>
                        <div class="me-pool-meta">Points are converted to real PHP when you encash at the Accountancy Office.</div>
                    </div>
                    <?php endif; ?>

                </div>


                <?php if ($canEncash): ?>
                <div class="me-tip-row">
                    <span>Points in your merchant wallet can only be encashed - they cannot be used to pay other merchants. The campus economy is a closed loop; every peso is always tracked.</span>
                </div>
                <?php endif; ?>

            </section>

            <section class="merchant-premium-panel">
                <div class="merchant-panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Recent Sales</h3>
                        <p>Latest payments received by this merchant</p>
                    </div>

                    <a href="<?= MERCHANT_URL ?>/history.php" class="merchant-view-btn">View All</a>
                </div>

                <div class="table-responsive">
                    <table class="table merchant-premium-table align-middle js-datatable" id="merchantRecentSalesTable" data-page-length="8">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Time</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><?php echo gjc_e($sale["reference_no"]); ?></td>
                                <td><?php echo gjc_e(ucwords(str_replace('_', ' ', $sale["transaction_type"]))); ?></td>
                                <td class="merchant-amount">+<?php echo gjc_money($sale["amount"]); ?></td>
                                <td><span class="merchant-type-pill">Payment</span></td>
                                <td><?php echo gjc_e(date('M d, h:i A', strtotime($sale["created_at"]))); ?></td>
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
    <script src="<?= JS_URL ?>/merchant_chart.js?v=10"></script>

    <script>
    function toggleMerchantSidebar() {
        document.getElementById("merchantSidebar").classList.toggle("collapsed");
    }
    </script>

</body>

</html>
