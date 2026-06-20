<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);
$currentUser = gjc_current_user($db);
$currentPage = 'leases';

// â”€â”€ Summary stats â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$totalLeases   = 0;
$activeLeases  = 0;
$overdueLeases = 0;
$monthlyTotal  = 0.0;

if (gjc_table_exists($db, 'merchant_leases')) {
    $row = $db->query(
        "SELECT COUNT(*)                                                             AS total,
                SUM(status = 'active')                                               AS active_count,
                SUM(status = 'active' AND next_due_date < CURDATE())                 AS overdue_count,
                COALESCE(SUM(CASE WHEN status = 'active' THEN monthly_rent ELSE 0 END), 0) AS monthly_total
           FROM merchant_leases"
    )->fetch(PDO::FETCH_ASSOC);

    $totalLeases   = (int)   ($row['total']         ?? 0);
    $activeLeases  = (int)   ($row['active_count']  ?? 0);
    $overdueLeases = (int)   ($row['overdue_count'] ?? 0);
    $monthlyTotal  = (float) ($row['monthly_total'] ?? 0.0);
}

// â”€â”€ Pagination â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$leases     = [];
$totalRows  = 0;

if (gjc_table_exists($db, 'merchant_leases')) {
    $totalRows = (int) $db->query("SELECT COUNT(*) FROM merchant_leases")->fetchColumn();

    $stmt = $db->prepare(
        "SELECT ml.*, u.first_name, u.last_name, u.email
           FROM merchant_leases ml
           LEFT JOIN users u ON u.userID = ml.merchant_user_id
          ORDER BY ml.next_due_date ASC
          LIMIT ? OFFSET ?"
    );
    $stmt->execute([$perPage, $offset]);
    $leases = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));
$today      = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leases &amp; Rent | GenPay Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=3">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="admin-layout">

    <!-- â”€â”€ Sidebar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <?php require __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

    <!-- â”€â”€ Main â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <main class="admin-main">

        <header class="topbar">
            <button class="menu-btn" onclick="toggleSidebar()">&#9776;</button>
            <div>
                <h1>Leases &amp; Rent</h1>
                <p>Manage merchant stall contracts and rental payment tracking.</p>
            </div>
            <div class="admin-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="avatar"><img src="<?= ICONS_URL ?>/admin.png" alt="Admin"></div>
            </div>
        </header>

        <!-- â”€â”€ Summary Cards â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
        <section class="row g-4 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-icon"><img src="<?= ICONS_URL ?>/encashments.png" alt=""></div>
                    <span>Total Leases</span>
                    <h2><?= $totalLeases ?></h2>
                    <p>All contract records</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-icon"><img src="<?= ICONS_URL ?>/merchants.png" alt=""></div>
                    <span>Active Leases</span>
                    <h2><?= $activeLeases ?></h2>
                    <p>Currently running contracts</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="metric-card" style="border-left:4px solid #ef4444;">
                    <div class="metric-icon"><img src="<?= ICONS_URL ?>/pending-encashments.png" alt=""></div>
                    <span>Overdue Payments</span>
                    <h2 style="color:#ef4444"><?= $overdueLeases ?></h2>
                    <p>Past due date today</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-icon"><img src="<?= ICONS_URL ?>/volume.png" alt=""></div>
                    <span>Monthly Revenue</span>
                    <h2><?= gjc_money($monthlyTotal) ?></h2>
                    <p>Active lease total</p>
                </div>
            </div>
        </section>

        <!-- â”€â”€ Leases Table â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
        <section class="premium-panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div>
                    <h3>Lease Contracts</h3>
                    <p>All merchant stall lease agreements.</p>
                </div>
                <button class="view-btn" data-bs-toggle="modal" data-bs-target="#leaseModal"
                        onclick="resetLeaseForm()">+ New Lease</button>
            </div>

            <div class="table-responsive">
                <table class="table premium-table align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Merchant</th>
                            <th>Stall</th>
                            <th>Monthly Rent</th>
                            <th>Lease Period</th>
                            <th>Next Due</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($leases)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                No lease contracts found. Click <strong>+ New Lease</strong> to add one.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($leases as $l): ?>
                        <?php
                            $isOverdue = ($l['status'] === 'active') && (($l['next_due_date'] ?? '') < $today);
                            $statusBadge = match ($l['status'] ?? 'pending') {
                                'active'     => 'badge-success',
                                'expired'    => 'badge-danger',
                                'terminated' => 'badge-secondary',
                                default      => 'badge-warning',
                            };
                        ?>
                        <tr>
                            <td><?= (int) $l['id'] ?></td>
                            <td>
                                <strong><?= gjc_e(trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''))) ?></strong><br>
                                <small class="text-muted"><?= gjc_e($l['email'] ?? '') ?></small>
                            </td>
                            <td>
                                <?= gjc_e($l['stall_name'] ?? '') ?><br>
                                <small class="text-muted">#<?= gjc_e($l['stall_number'] ?? '') ?></small>
                            </td>
                            <td><?= gjc_money($l['monthly_rent'] ?? 0) ?></td>
                            <td>
                                <small>
                                    <?= gjc_e($l['lease_start'] ?? '') ?>
                                    &ndash;
                                    <?= gjc_e($l['lease_end'] ?? '') ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($isOverdue): ?>
                                    <span style="color:#ef4444;font-weight:700;">
                                        <?= gjc_e($l['next_due_date'] ?? '') ?>
                                    </span>
                                <?php else: ?>
                                    <?= gjc_e($l['next_due_date'] ?? 'â€”') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="<?= $statusBadge ?>">
                                    <?= ucfirst(gjc_e($l['status'] ?? 'pending')) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="editLease(<?= htmlspecialchars(json_encode($l), ENT_QUOTES) ?>)">
                                    Edit
                                </button>
                                <button class="btn btn-sm btn-outline-success"
                                        onclick="recordPayment(<?= (int) $l['id'] ?>, <?= htmlspecialchars(json_encode($l['stall_name'] ?? ''), ENT_QUOTES) ?>)">
                                    Payment
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="d-flex justify-content-center mt-3">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">Next &raquo;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </section>

    </main>
</div>

<!-- â”€â”€ New / Edit Lease Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="modal fade" id="leaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="leaseModalTitle">New Lease Contract</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="leaseForm" novalidate>
                    <input type="hidden" name="action"   value="create_lease" id="leaseAction">
                    <input type="hidden" name="lease_id" value=""             id="leaseId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Merchant User ID <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="merchant_user_id" id="merchantUserId"
                                   required min="1" placeholder="Numeric User ID">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Stall Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="stall_number" id="stallNumber"
                                   required placeholder="e.g. A-01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Stall Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="stall_name" id="stallName"
                                   required placeholder="e.g. Green Hill Canteen">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Monthly Rent (â‚±) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="monthly_rent" id="monthlyRent"
                                   step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Security Deposit (â‚±)</label>
                            <input type="number" class="form-control" name="deposit_amount" id="depositAmount"
                                   step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status" id="leaseStatus">
                                <option value="pending">Pending</option>
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Lease Start <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="lease_start" id="leaseStart" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Lease End <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="lease_end" id="leaseEnd" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Contract Notes</label>
                            <textarea class="form-control" name="contract_notes" id="contractNotes" rows="3"
                                      placeholder="Terms, conditions, special arrangements..."></textarea>
                        </div>
                    </div>
                    <div id="leaseFormMsg" class="mt-3"></div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="login-btn" style="flex:1" id="leaseSubmitBtn">Save Lease</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- â”€â”€ Record Payment Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header">
                <h5 class="modal-title">Record Rent Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm" novalidate>
                    <input type="hidden" name="action"   value="record_payment">
                    <input type="hidden" name="lease_id" id="payLeaseId">
                    <p class="text-muted mb-3">
                        Recording payment for stall: <strong id="payStallName"></strong>
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Amount Paid (â‚±) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="amount_paid" step="0.01" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Period Covered <span class="text-danger">*</span></label>
                            <input type="month" class="form-control" name="period_covered" required
                                   value="<?= date('Y-m') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="payment_date" required
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes (optional)</label>
                            <input type="text" class="form-control" name="notes"
                                   placeholder="Receipt no., payment method, etc.">
                        </div>
                    </div>
                    <div id="payFormMsg" class="mt-3"></div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="login-btn" style="flex:1" id="paySubmitBtn">Record Payment</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}

/* â”€â”€ Lease Modal helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function resetLeaseForm() {
    document.getElementById('leaseModalTitle').textContent = 'New Lease Contract';
    document.getElementById('leaseAction').value = 'create_lease';
    document.getElementById('leaseId').value     = '';
    document.getElementById('leaseForm').reset();
    document.getElementById('leaseFormMsg').innerHTML = '';
    const btn = document.getElementById('leaseSubmitBtn');
    btn.disabled = false;
    btn.textContent = 'Save Lease';
}

function editLease(lease) {
    document.getElementById('leaseModalTitle').textContent = 'Edit Lease Contract';
    document.getElementById('leaseAction').value           = 'update_lease';
    document.getElementById('leaseId').value               = lease.id;
    document.getElementById('merchantUserId').value        = lease.merchant_user_id ?? '';
    document.getElementById('stallNumber').value           = lease.stall_number     ?? '';
    document.getElementById('stallName').value             = lease.stall_name       ?? '';
    document.getElementById('monthlyRent').value           = lease.monthly_rent     ?? '';
    document.getElementById('depositAmount').value         = lease.deposit_amount   ?? 0;
    document.getElementById('leaseStatus').value           = lease.status           ?? 'pending';
    document.getElementById('leaseStart').value            = lease.lease_start      ?? '';
    document.getElementById('leaseEnd').value              = lease.lease_end        ?? '';
    document.getElementById('contractNotes').value         = lease.contract_notes   ?? '';
    document.getElementById('leaseFormMsg').innerHTML      = '';
    const btn = document.getElementById('leaseSubmitBtn');
    btn.disabled = false;
    btn.textContent = 'Save Changes';
    new bootstrap.Modal(document.getElementById('leaseModal')).show();
}

function recordPayment(leaseId, stallName) {
    document.getElementById('payLeaseId').value         = leaseId;
    document.getElementById('payStallName').textContent = stallName;
    document.getElementById('payFormMsg').innerHTML     = '';
    document.getElementById('paySubmitBtn').disabled    = false;
    document.getElementById('paySubmitBtn').textContent = 'Record Payment';
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

/* â”€â”€ Lease form submission â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
document.getElementById('leaseForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('leaseSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Savingâ€¦';
    try {
        const resp = await fetch('<?= ADMIN_URL ?>/api/leases.php', {
            method: 'POST',
            body: new FormData(this)
        });
        const data = await resp.json();
        const msg  = document.getElementById('leaseFormMsg');
        if (data.success) {
            msg.innerHTML = '<div class="alert alert-success">Saved successfully. Refreshingâ€¦</div>';
            setTimeout(() => location.reload(), 1200);
        } else {
            msg.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Unknown error.') + '</div>';
            btn.disabled = false;
            btn.textContent = 'Save Lease';
        }
    } catch (err) {
        document.getElementById('leaseFormMsg').innerHTML =
            '<div class="alert alert-danger">Network error. Please try again.</div>';
        btn.disabled = false;
        btn.textContent = 'Save Lease';
    }
});

/* â”€â”€ Payment form submission â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
document.getElementById('paymentForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('paySubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Savingâ€¦';
    try {
        const resp = await fetch('<?= ADMIN_URL ?>/api/leases.php', {
            method: 'POST',
            body: new FormData(this)
        });
        const data = await resp.json();
        const msg  = document.getElementById('payFormMsg');
        if (data.success) {
            msg.innerHTML = '<div class="alert alert-success">Payment recorded. Refreshingâ€¦</div>';
            setTimeout(() => location.reload(), 1200);
        } else {
            msg.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Unknown error.') + '</div>';
            btn.disabled = false;
            btn.textContent = 'Record Payment';
        }
    } catch (err) {
        document.getElementById('payFormMsg').innerHTML =
            '<div class="alert alert-danger">Network error. Please try again.</div>';
        btn.disabled = false;
        btn.textContent = 'Record Payment';
    }
});
</script>
</body>
</html>
