<?php
session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/VoucherEngine.php';

gjc_require_role(['admin', 'cashier', 'sub-admin', 'super-admin']);

$ve = new VoucherEngine($db);

$stats = $ve->getSummaryStats();
$activeVisitors     = $stats['active_count'] ?? 0;
$totalVisitorFunds  = $stats['active_pool_value'] ?? 0;
$expiredSessions    = $stats['expired_count'] ?? 0;

$visitors = $ve->listVouchers('active', 50);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Visitors | GJC EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/visitors.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="admin-layout">

        <aside class="admin-sidebar" id="sidebar">

            <div class="brand-box">
                <div class="brand-logo">
                    <img src="<?= ICONS_URL ?>/edupay.png" alt="Logo">
                </div>

                <div class="brand-text">
                    <h4>GJC EduPay</h4>
                    <span>Admin Portal</span>
                </div>
            </div>

            <nav class="sidebar-menu">
                <a href="<?= ADMIN_URL ?>/dashboard.php">
                    <img src="<?= ICONS_URL ?>/dashboard.png" class="nav-icon" alt="">
                    <span class="nav-text">Dashboard</span>
                </a>

                <a href="<?= ADMIN_URL ?>/users.php">
                    <img src="<?= ICONS_URL ?>/users.png" class="nav-icon" alt="">
                    <span class="nav-text">Users</span>
                </a>

                <a href="<?= ADMIN_URL ?>/topups.php">
                    <img src="<?= ICONS_URL ?>/topups.png" class="nav-icon" alt="">
                    <span class="nav-text">Top-ups</span>
                </a>

                <a href="<?= ADMIN_URL ?>/encashments.php">
                    <img src="<?= ICONS_URL ?>/encashments.png" class="nav-icon" alt="">
                    <span class="nav-text">Encashments</span>
                </a>

                <a href="<?= ADMIN_URL ?>/transactions.php">
                    <img src="<?= ICONS_URL ?>/transactions.png" class="nav-icon" alt="">
                    <span class="nav-text">Transactions</span>
                </a>

                <a href="<?= ADMIN_URL ?>/economy.php">
                    <img src="<?= ICONS_URL ?>/wallet.png" class="nav-icon" alt="">
                    <span class="nav-text">Economy</span>
                </a>

                <a href="<?= ADMIN_URL ?>/visitors.php" class="active">
                    <img src="<?= ICONS_URL ?>/visitors.png" class="nav-icon" alt="">
                    <span class="nav-text">Visitors</span>
                </a>

                <a href="<?= ADMIN_URL ?>/settings.php">
                    <img src="<?= ICONS_URL ?>/settings.png" class="nav-icon" alt="">
                    <span class="nav-text">Settings</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">
                <img src="<?= ICONS_URL ?>/logout.png" class="logout-icon" alt="">
                <span>Logout</span>
            </a>

        </aside>

        <main class="admin-main visitors-page">

            <header class="topbar">
                <button class="menu-btn" onclick="toggleSidebar()">☰</button>

                <div>
                    <h1>Visitors</h1>
                    <p>Manage guest accounts, visitor wallet funds, and temporary sessions.</p>
                </div>

                <div class="ms-auto me-4">
                    <button type="button" class="visitor-primary-btn" data-bs-toggle="modal" data-bs-target="#mintVoucherModal">
                        <span>+</span>
                        Mint New Voucher
                    </button>
                </div>

                <div class="admin-user">
                    <span>Admin</span>
                    <div class="avatar">
                        <img src="<?= ICONS_URL ?>/admin.png" alt="Admin">
                    </div>
                </div>
            </header>

            <section class="visitor-stats-grid">

                <div class="visitor-stat-card">
                    <div class="visitor-icon yellow">
                        <img src="<?= ICONS_URL ?>/visitors.png" alt="">
                    </div>

                    <div class="visitor-stat-copy">
                        <span>Active Visitors</span>
                        <h2><?php echo $activeVisitors; ?></h2>
                        <p>Usable guest vouchers in circulation</p>
                    </div>
                </div>

                <div class="visitor-stat-card">
                    <div class="visitor-icon blue">
                        <img src="<?= ICONS_URL ?>/registered_today.png" alt="">
                    </div>

                    <div class="visitor-stat-copy">
                        <span>All-Time Issued</span>
                        <h2><?php echo $stats['total_all_time'] ?? 0; ?></h2>
                        <p>Total visitor vouchers created</p>
                    </div>
                </div>

                <div class="visitor-stat-card">
                    <div class="visitor-icon green">
                        <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                    </div>

                    <div>
                        <span>Total Visitor Funds</span>
                        <h2>₱<?php echo number_format($totalVisitorFunds, 0); ?></h2>
                    </div>
                </div>

                <div class="visitor-stat-card cleanup-card">
                    <div class="visitor-icon red">
                        <img src="<?= ICONS_URL ?>/cleanup_visitors.png" alt="">
                    </div>

                    <div>
                        <span>Expired Sessions</span>
                        <h2><?php echo $expiredSessions; ?></h2>
                        <a href="<?= ADMIN_URL ?>/cleanup_visitors.php" class="cleanup-btn">Run Cleanup</a>
                    </div>
                </div>

            </section>

            <section class="visitors-table-panel">

                <div class="visitors-table-header">
                    <div>
                        <h3>
                            <img src="<?= ICONS_URL ?>/visitors.png" alt="">
                            All Visitors
                        </h3>
                        <p>Temporary guest accounts with wallet balance and session status.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table visitors-table align-middle js-datatable" id="visitorsTable" data-page-length="10" data-empty-message="No active visitors found.">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Expires</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($visitors as $v): ?>
                            <tr>
                                <td>
                                    <div class="visitor-name-cell">
                                        <strong><?php echo htmlspecialchars($v['visitor_name']); ?></strong>
                                        <small><?php echo date('M d, Y h:i A', strtotime($v['created_at'])); ?></small>
                                    </div>
                                    <div style="font-size:11px;color:#888;margin-top:2px;">
                                        Code: <?php echo htmlspecialchars($v['voucher_code']); ?>
                                    </div>
                                </td>

                                <td><?php echo htmlspecialchars($v['visitor_contact'] ?: '—'); ?></td>

                                <td class="visitor-balance">
                                    ₱<?php echo number_format($v['remaining_balance'], 2); ?>
                                    <div style="font-size:11px;color:#888;font-weight:normal;">
                                        Original: ₱<?php echo number_format($v['initial_value'], 2); ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="status-group">
                                        <span class="visitor-status active" style="text-transform: capitalize;">
                                            <?php echo htmlspecialchars($v['status']); ?>
                                        </span>

                                        <?php if ($v['computed_status'] === 'expired_pending'): ?>
                                        <span class="visitor-status expired">Expiring</span>
                                        <?php else: ?>
                                        <span class="visitor-status valid">Valid</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <?php echo date('M d, h:i A', strtotime($v['expires_at'])); ?>
                                    <?php if($v['minutes_until_expiry'] > 0): ?>
                                    <br><small style="color:#22c55e"><?= $v['minutes_until_expiry'] ?>m left</small>
                                    <?php else: ?>
                                    <br><small style="color:#ef4444">Past Expiry</small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="visitor-actions">
                                        <button type="button" class="load-cash-btn" onclick="alert('Voucher Code: <?= $v['voucher_code'] ?>\nRemaining: ₱<?= number_format($v['remaining_balance'], 2) ?>')">
                                            View
                                        </button>

                                        <a class="print-voucher-btn" href="<?= ADMIN_URL ?>/print_voucher.php?id=<?= (int) $v['id'] ?>" target="_blank" rel="noopener">
                                            Print
                                        </a>

                                        <?php if ($v['is_refundable']): ?>
                                        <button type="button" class="refund-btn"
                                            onclick="return confirmRefund('<?php echo htmlspecialchars(addslashes($v['visitor_name'])); ?>', '<?php echo number_format($v['remaining_balance'], 2); ?>')">
                                            Refund
                                        </button>
                                        <?php else: ?>
                                        <button type="button" class="refund-btn" style="opacity:0.5;cursor:not-allowed;" title="Non-refundable">
                                            No Refund
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($visitors)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No active visitors found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                    </table>
                </div>

            </section>

        </main>

    </div>

    </div>

    <div class="modal fade" id="mintVoucherModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content visitor-load-modal">
                <div class="visitor-load-header">
                    <h5><span>▣</span> Mint Visitor Voucher</h5>
                    <button type="button" class="visitor-modal-close" data-bs-dismiss="modal">×</button>
                </div>
                <div class="visitor-load-body">
                    <div id="mintAlert" class="alert d-none" style="font-size:13px; font-weight:600"></div>

                    <div id="mintFormWrapper">
                        <div class="mb-3">
                            <label class="form-label" style="font-size:13px; color:#6b7280; font-weight:600">Visitor Full Name</label>
                            <input type="text" id="mintName" class="form-control" placeholder="e.g. Juan Dela Cruz" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:13px; color:#6b7280; font-weight:600">Contact / ID Info (Optional)</label>
                            <input type="text" id="mintContact" class="form-control" placeholder="e.g. 09123456789">
                        </div>

                        <label class="load-label mt-2">Amount to Load (from Vault)</label>
                        <div class="load-money-field mb-3">
                            <span>₱</span>
                            <input type="number" id="mintAmount" placeholder="0.00" min="1" step="0.01" required>
                        </div>

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" id="mintRefundable">
                            <label class="form-check-label" for="mintRefundable" style="font-size:13px; font-weight:600; color:#4b5563">
                                Allow Cash Refund?
                            </label>
                            <div style="font-size:11px; color:#9ca3af; margin-top:2px;">
                                If unchecked (non-refundable), any unused balance on expiry automatically returns to the school vault.
                            </div>
                        </div>
                    </div>

                    <div id="mintSuccessWrapper" class="text-center d-none">
                        <div class="alert alert-success" style="font-size:14px; font-weight:700;">Voucher Minted Successfully!</div>
                        <div id="qrPlaceholder" style="width: 200px; height: 200px; margin: 0 auto; background: #f3f4f6; border: 2px dashed #ccc; display: grid; place-items: center;">
                            <span class="text-muted small">QR Code renders here</span>
                        </div>
                        <p class="mt-3 fw-bold fs-5 text-success" id="successCode">VCH-XXXX</p>
                        <div class="voucher-success-actions">
                            <a id="printVoucherLink" class="visitor-load-btn" href="#" target="_blank" rel="noopener">Print Voucher</a>
                            <button type="button" class="visitor-cancel-btn" data-bs-dismiss="modal">Done</button>
                        </div>
                        <textarea id="rawPayload" class="form-control mt-2" rows="3" readonly style="font-size:10px; font-family:monospace;"></textarea>
                        <small class="text-muted mt-2 d-block">Use this JSON string in the scanner's manual input if QR camera is disabled.</small>
                    </div>

                </div>
                <div class="visitor-load-footer" id="mintFooter">
                    <button type="button" class="visitor-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="visitor-load-btn" onclick="submitMint()">Mint Voucher</button>
                </div>
            </div>
        </div>
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

    function confirmRefund(visitorName, balance) {
        return confirm("Process cash refund of ₱" + balance + " for " + visitorName + "?");
    }

    async function submitMint() {
        const name = document.getElementById('mintName').value;
        const contact = document.getElementById('mintContact').value;
        const amount = document.getElementById('mintAmount').value;
        const refundable = document.getElementById('mintRefundable').checked ? 1 : 0;
        const alertBox = document.getElementById('mintAlert');

        if(!name || !amount) {
            alertBox.className = "alert alert-danger";
            alertBox.textContent = "Name and amount are required.";
            return;
        }

        const btn = document.querySelector('#mintFooter .visitor-load-btn');
        const origText = btn.textContent;
        btn.textContent = "Minting...";
        btn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('action', 'create');
            fd.append('visitor_name', name);
            fd.append('visitor_contact', contact);
            fd.append('amount', amount);
            if(refundable) fd.append('is_refundable', '1');

            const res = await fetch('<?= ADMIN_URL ?>/api/voucher.php', { method: 'POST', body: fd });
            const data = await res.json();

            if(!data.success) {
                alertBox.className = "alert alert-danger";
                alertBox.textContent = data.error || "Minting failed.";
            } else {
                document.getElementById('mintFormWrapper').classList.add('d-none');
                document.getElementById('mintFooter').classList.add('d-none');
                
                const successWrapper = document.getElementById('mintSuccessWrapper');
                successWrapper.classList.remove('d-none');
                
                document.getElementById('successCode').textContent = data.voucher_code;
                document.getElementById('rawPayload').value = data.qr_payload;
                document.getElementById('printVoucherLink').href = '<?= ADMIN_URL ?>/print_voucher.php?id=' + encodeURIComponent(data.voucher_id);
                
                document.getElementById('qrPlaceholder').innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(data.qr_payload) + '" alt="QR">';
                
                document.getElementById('mintVoucherModal').addEventListener('hidden.bs.modal', function () {
                    window.location.reload();
                });
            }
        } catch (e) {
            alertBox.className = "alert alert-danger";
            alertBox.textContent = "Network error occurred.";
        } finally {
            btn.textContent = origText;
            btn.disabled = false;
        }
    }
    </script>

</body>

</html>
