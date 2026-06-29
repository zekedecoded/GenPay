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
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Dashboard | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=18">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=IBM+Plex+Mono:wght@600;700&display=swap"
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
                        <i class="fa-solid fa-store"></i>
                    </div>
                </div>
            </header>

            <section class="row g-4 mb-4">
                <div class="col-12">
                    <div class="gc-ticket">
                        <div class="gc-ticket-side gc-ticket-balance">
                            <span class="gc-ticket-icon">
                                <i class="fa-solid fa-coins"></i>
                            </span>
                            <div>
                                <span class="gc-ticket-label">GenCoin Balance</span>
                                <div class="gc-ticket-figure"><?php echo number_format($currentBalance / 10, 1); ?><small>GC</small></div>
                                <span class="gc-ticket-note"><?= $canEncash ? 'Available for encashment' : 'Store wallet balance' ?></span>
                            </div>
                        </div>

                        <div class="gc-ticket-seam" aria-hidden="true"></div>

                        <div class="gc-ticket-side gc-ticket-equiv">
                            <div>
                                <span class="gc-ticket-label">PHP Equivalent</span>
                                <div class="gc-ticket-figure gc-ticket-figure-peso"><?php echo gjc_money($currentBalance); ?></div>
                                <span class="gc-ticket-note">Fixed rate &middot; &#8369;10 = 1.0 GenCoin</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="row g-4 mb-4">

                <div class="col-12 col-md-6 col-xl-4">
                    <div class="merchant-metric-card">
                        <div class="merchant-metric-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <span>Today's Sales</span>
                        <h2 id="todaysSalesValue"><?php echo gjc_money($todaysSales); ?></h2>
                        <p>Today's received payments</p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-4">
                    <div class="merchant-metric-card">
                        <div class="merchant-metric-icon">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                        <span>Total Earned</span>
                        <h2 id="totalEarnedValue"><?php echo gjc_money($totalEarned); ?></h2>
                        <p>Lifetime merchant earnings</p>
                    </div>
                </div>

                <?php if ($canEncash): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="merchant-metric-card">
                        <div class="merchant-metric-icon">
                            <i class="fa-solid fa-money-check-dollar"></i>
                        </div>
                        <span>Encashment</span>
                        <h2><?php echo $encashmentStatus; ?></h2>
                        <p>Request at anytime</p>
                    </div>
                </div>
                <?php endif; ?>

            </section>

            <section class="row g-4 mb-4">
                <div class="col-12">
                    <div class="merchant-premium-panel">
                        <div class="merchant-panel-header d-flex justify-content-between align-items-center">
                            <div>
                                <h3>
                                    <span class="me-dot me-dot-green me-pulse" style="margin-right:6px"></span>
                                    Live Order Queue
                                </h3>
                                <p>Orders from the POS and the student Shop Cart, updated automatically.</p>
                            </div>
                            <a href="<?= MERCHANT_URL ?>/pos.php" class="merchant-view-btn">Open POS</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table merchant-premium-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Source</th>
                                        <th>Items</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="liveQueueBody">
                                    <tr id="liveQueueEmpty">
                                        <td colspan="7" class="text-center text-muted py-4">Loading live orders&hellip;</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
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
                            <i class="fa-solid fa-shop me-pill-icon"></i>  Economy Status
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
                                <i class="fa-solid fa-shop"></i>
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
                                <i class="fa-solid fa-vault"></i>
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
                                <i class="fa-solid fa-graduation-cap"></i>
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
                                <i class="fa-solid fa-money-check-dollar"></i>
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

    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderDetailsBody">
                    <div class="text-center text-muted py-4">Loading&hellip;</div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
    <?php require __DIR__ . '/../includes/partials/datatables_assets.php'; ?>
    <script src="<?= JS_URL ?>/merchant_chart.js?v=10"></script>

    <script>
    function toggleMerchantSidebar() {
        document.getElementById("merchantSidebar").classList.toggle("collapsed");
    }

    // ── Live Order Queue ─────────────────────────────────────────────────────
    const LIVE_QUEUE_API = '<?= MERCHANT_URL ?>/api/pos.php';
    const liveQueueBody = document.getElementById('liveQueueBody');
    let lastQueueSignature = '';

    const QUEUE_BADGES = {
        pending: '<span class="badge bg-warning text-dark">Pending</span>',
        paid:    '<span class="badge bg-success">Paid</span>',
        voided:  '<span class="badge bg-secondary">Voided</span>',
        expired: '<span class="badge bg-secondary">Expired</span>',
    };

    const QUEUE_SOURCE_BADGES = {
        pos:          '<span class="badge bg-light text-dark border"><i class="fa-solid fa-cash-register"></i> POS</span>',
        cart:         '<span class="badge bg-light text-dark border"><i class="fa-solid fa-cart-shopping"></i> Shop Cart</span>',
        cart_pending: '<span class="badge bg-light text-dark border"><i class="fa-solid fa-cart-shopping"></i> Shop Cart</span>',
    };

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function renderLiveQueue(orders) {
        if (!orders.length) {
            liveQueueBody.innerHTML = `
                <tr id="liveQueueEmpty">
                    <td colspan="7" class="text-center text-muted py-4">No orders yet. Generate a payment QR from the POS, or wait for a Shop Cart checkout.</td>
                </tr>`;
            return;
        }

        liveQueueBody.innerHTML = orders.map(order => {
            const time = new Date(order.created_at.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const badge = QUEUE_BADGES[order.status] || `<span class="badge bg-light text-dark">${order.status}</span>`;
            const sourceBadge = QUEUE_SOURCE_BADGES[order.source] || '';
            const isVoidable = (order.source === 'pos' || order.source === 'cart_pending') && order.status === 'pending';
            const voidBtn = isVoidable
                ? `<button type="button" class="btn btn-sm btn-outline-danger" onclick="voidLiveOrder(${order.id}, '${order.source}', this)">&times; Void</button>`
                : '';

            return `
                <tr data-order-uid="${order.uid}">
                    <td>#${order.id}</td>
                    <td>${sourceBadge}</td>
                    <td>${escapeHtml(order.description)}</td>
                    <td>&#8369;${Number(order.amount).toFixed(2)}</td>
                    <td>${badge}</td>
                    <td>${time}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="viewOrderDetails(${order.id}, '${order.source}')">View</button>
                        ${voidBtn}
                    </td>
                </tr>`;
        }).join('');
    }

    function flashLiveQueue() {
        liveQueueBody.classList.remove('queue-flash');
        // Restart the animation even if it's already mid-flash from a rapid update.
        void liveQueueBody.offsetWidth;
        liveQueueBody.classList.add('queue-flash');
    }

    async function refreshLiveQueue() {
        try {
            const fd = new FormData();
            fd.append('action', 'list_queue');
            const res = await fetch(LIVE_QUEUE_API, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) return;

            const signature = JSON.stringify(data.orders.map(o => [o.uid, o.status, o.amount]));
            if (signature === lastQueueSignature) return;

            const isFirstLoad = lastQueueSignature === '';
            lastQueueSignature = signature;
            renderLiveQueue(data.orders);
            if (!isFirstLoad) flashLiveQueue();
        } catch (error) {
            // Keep showing the last known queue state on a transient network error.
        }
    }

    async function voidLiveOrder(orderId, source, btn) {
        btn.disabled = true;
        btn.textContent = 'Voiding...';
        try {
            const fd = new FormData();
            fd.append('action', 'void_order');
            fd.append('order_id', orderId);
            fd.append('source', source);
            const res = await fetch(LIVE_QUEUE_API, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) {
                alert(data.message || 'Unable to void this order.');
            }
        } finally {
            lastQueueSignature = '';
            refreshLiveQueue();
        }
    }

    refreshLiveQueue();
    setInterval(refreshLiveQueue, 3000);

    // ── View full order details ────────────────────────────────────────────────
    const orderDetailsModalEl = document.getElementById('orderDetailsModal');
    const orderDetailsModal = bootstrap.Modal.getOrCreateInstance(orderDetailsModalEl);
    const orderDetailsBody = document.getElementById('orderDetailsBody');

    async function viewOrderDetails(orderId, source) {
        orderDetailsBody.innerHTML = '<div class="text-center text-muted py-4">Loading&hellip;</div>';
        orderDetailsModal.show();

        try {
            const fd = new FormData();
            fd.append('action', 'view_order');
            fd.append('order_id', orderId);
            fd.append('source', source);
            const res = await fetch(LIVE_QUEUE_API, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) {
                orderDetailsBody.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(data.message || 'Unable to load this order.')}</div>`;
                return;
            }

            const items = Array.isArray(data.items) ? data.items : [];
            const itemRows = items.length
                ? items.map(item => {
                    const qty = Number(item.qty || 0);
                    const price = Number(item.price || 0);
                    const lineTotal = item.line_total != null ? Number(item.line_total) : qty * price;
                    return `
                        <tr>
                            <td>${escapeHtml(item.name || '')}</td>
                            <td class="text-center">${qty}</td>
                            <td class="text-end">&#8369;${price.toFixed(2)}</td>
                            <td class="text-end">&#8369;${lineTotal.toFixed(2)}</td>
                        </tr>`;
                }).join('')
                : `<tr><td colspan="4" class="text-muted text-center">${escapeHtml(data.description || 'No itemized details recorded.')}</td></tr>`;

            const badge = QUEUE_BADGES[data.status] || `<span class="badge bg-light text-dark">${data.status}</span>`;
            const submittedTime = data.created_at ? new Date(data.created_at.replace(' ', 'T')).toLocaleString() : '--';
            const paidTime = data.paid_at ? new Date(data.paid_at.replace(' ', 'T')).toLocaleString() : null;

            orderDetailsBody.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1">${escapeHtml(data.reference || ('#' + orderId))}</h5>
                        <small class="text-muted">${escapeHtml(data.student_name || 'Student')}</small>
                    </div>
                    ${badge}
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>${itemRows}</tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between fw-bold mb-3" style="font-size:16px;border-top:2px solid #e5e7eb;padding-top:10px;">
                    <span>Total</span>
                    <span>&#8369;${Number(data.amount || 0).toFixed(2)}</span>
                </div>
                <div style="font-size:12.5px;color:#6b7280;">
                    <div>Submitted: ${submittedTime}</div>
                    ${paidTime ? `<div>Paid: ${paidTime}</div>` : ''}
                </div>
            `;
        } catch (error) {
            orderDetailsBody.innerHTML = '<div class="alert alert-danger mb-0">Unable to reach the server. Please try again.</div>';
        }
    }

    // ── Live Sales Summary (Today's Sales / Total Earned) ───────────────────────
    const todaysSalesValue = document.getElementById('todaysSalesValue');
    const totalEarnedValue = document.getElementById('totalEarnedValue');

    async function refreshSalesSummary() {
        try {
            const fd = new FormData();
            fd.append('action', 'get_sales_summary');
            const res = await fetch(LIVE_QUEUE_API, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) return;

            todaysSalesValue.innerHTML = '&#8369;' + Number(data.todays_sales).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            totalEarnedValue.innerHTML = '&#8369;' + Number(data.total_earned).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch (error) {
            // Keep showing the last known values on a transient network error.
        }
    }

    setInterval(refreshSalesSummary, 5000);
    </script>

</body>

</html>
