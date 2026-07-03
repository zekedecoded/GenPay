<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);
$currentUser = gjc_current_user($db);
$currentPage = 'leases';

// ── Summary stats ───────────────────────────────────────────────────────
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

// ── Filters ──────────────────────────────────────────────────────────────
$q              = trim((string) ($_GET['q'] ?? ''));
$statusFilter   = trim((string) ($_GET['status'] ?? ''));
$overdueOnly    = (int) ($_GET['overdue'] ?? 0) === 1;
$allowedStatusF = ['pending', 'active', 'expired', 'terminated'];
if (!in_array($statusFilter, $allowedStatusF, true)) {
    $statusFilter = '';
}

// ── Pagination ───────────────────────────────────────────────────────────
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$leases     = [];
$totalRows  = 0;
$thisMonth  = date('Y-m');

if (gjc_table_exists($db, 'merchant_leases')) {
    $where  = [];
    $params = [];

    if ($q !== '') {
        $where[] = "(ml.stall_name LIKE ? OR ml.stall_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $needle = '%' . $q . '%';
        array_push($params, $needle, $needle, $needle, $needle, $needle);
    }
    if ($statusFilter !== '') {
        $where[] = 'ml.status = ?';
        $params[] = $statusFilter;
    }
    if ($overdueOnly) {
        $where[] = "ml.status = 'active' AND ml.next_due_date < CURDATE()";
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $db->prepare(
        "SELECT COUNT(*)
           FROM merchant_leases ml
           LEFT JOIN users u ON u.userID = ml.merchant_user_id
          {$whereSql}"
    );
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT ml.*, u.first_name, u.last_name, u.email,
                COALESCE((
                    SELECT SUM(rp.amount_paid) FROM merchant_rent_payments rp
                     WHERE rp.lease_id = ml.id AND rp.period_covered = ?
                ), 0) AS paid_this_month
           FROM merchant_leases ml
           LEFT JOIN users u ON u.userID = ml.merchant_user_id
          {$whereSql}
          ORDER BY
            CASE WHEN ml.status = 'active' AND ml.next_due_date < CURDATE() THEN 0 ELSE 1 END,
            ml.next_due_date ASC
          LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute(array_merge([$thisMonth], $params));
    $leases = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));
$today      = date('Y-m-d');

function gjc_lease_qs(array $overrides = []): string
{
    $base = [
        'q'       => $_GET['q']       ?? '',
        'status'  => $_GET['status']  ?? '',
        'overdue' => $_GET['overdue'] ?? '',
        'page'    => $_GET['page']    ?? '',
    ];
    $merged = array_filter(array_merge($base, $overrides), static fn ($v) => $v !== '' && $v !== null);
    return htmlspecialchars(http_build_query($merged), ENT_QUOTES);
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
    <title>Leases &amp; Rent | GenPay Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=5">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="admin-layout">

    <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
    <?php require __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

    <!-- ── Main ─────────────────────────────────────────────────────────── -->
    <main class="admin-main">

        <header class="topbar">
            <button class="menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h1>Leases &amp; Rent</h1>
                <p>Manage merchant stall contracts and rental payment tracking.</p>
            </div>
            <div class="admin-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="avatar"><i class="fa-solid fa-user-tie"></i></div>
            </div>
        </header>

        <!-- ── Summary Cards ────────────────────────────────────────────── -->
        <section class="row g-4 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <a href="?<?= gjc_lease_qs(['status' => '', 'overdue' => '', 'page' => '']) ?>" class="text-decoration-none">
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fa-solid fa-file-signature"></i></div>
                        <span>Total Leases</span>
                        <h2><?= $totalLeases ?></h2>
                        <p>All contract records</p>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <a href="?<?= gjc_lease_qs(['status' => 'active', 'overdue' => '', 'page' => '']) ?>" class="text-decoration-none">
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fa-solid fa-store"></i></div>
                        <span>Active Leases</span>
                        <h2><?= $activeLeases ?></h2>
                        <p>Currently running contracts</p>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <a href="?<?= gjc_lease_qs(['status' => '', 'overdue' => '1', 'page' => '']) ?>" class="text-decoration-none">
                    <div class="metric-card" style="border-left:4px solid var(--gjc-alert);">
                        <div class="metric-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <span>Overdue Payments</span>
                        <h2 style="color:var(--gjc-alert)"><?= $overdueLeases ?></h2>
                        <p>Past due date today</p>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    <span>Monthly Revenue</span>
                    <h2><?= gjc_money($monthlyTotal) ?></h2>
                    <p>Active lease total</p>
                </div>
            </div>
        </section>

        <!-- ── Leases Table ─────────────────────────────────────────────── -->
        <section class="premium-panel">
            <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3>Lease Contracts</h3>
                    <p>All merchant stall lease agreements.</p>
                </div>
                <button class="view-btn" data-bs-toggle="modal" data-bs-target="#leaseModal"
                        onclick="openNewLeaseModal()">+ New Lease</button>
            </div>

            <form method="get" class="lease-filter-bar">
                <input type="search" name="q" class="form-control" placeholder="Search merchant, stall, email..."
                       value="<?= gjc_e($q) ?>">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="pending"     <?= $statusFilter === 'pending'     ? 'selected' : '' ?>>Pending</option>
                    <option value="active"      <?= $statusFilter === 'active'      ? 'selected' : '' ?>>Active</option>
                    <option value="expired"     <?= $statusFilter === 'expired'     ? 'selected' : '' ?>>Expired</option>
                    <option value="terminated"  <?= $statusFilter === 'terminated'  ? 'selected' : '' ?>>Terminated</option>
                </select>
                <label class="lease-filter-check">
                    <input type="checkbox" name="overdue" value="1" <?= $overdueOnly ? 'checked' : '' ?>>
                    Overdue only
                </label>
                <button type="submit" class="btn btn-outline-secondary">Filter</button>
                <?php if ($q !== '' || $statusFilter !== '' || $overdueOnly): ?>
                    <a href="<?= ADMIN_URL ?>/leases.php" class="btn btn-link">Reset</a>
                <?php endif; ?>
            </form>

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
                            <th>This Month</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($leases)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                No lease contracts match your filters.
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
                            $paidThisMonth = (float) ($l['paid_this_month'] ?? 0);
                            $rent          = (float) ($l['monthly_rent'] ?? 0);
                            if ($l['status'] !== 'active') {
                                $monthBadge = 'badge-secondary';
                                $monthLabel = 'N/A';
                            } elseif ($paidThisMonth >= $rent && $rent > 0) {
                                $monthBadge = 'badge-success';
                                $monthLabel = 'Paid';
                            } elseif ($paidThisMonth > 0) {
                                $monthBadge = 'badge-warning';
                                $monthLabel = 'Partial';
                            } else {
                                $monthBadge = 'badge-danger';
                                $monthLabel = 'Unpaid';
                            }
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
                                    &rarr;
                                    <?= gjc_e($l['lease_end'] ?? '') ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($isOverdue): ?>
                                    <span style="color:var(--gjc-alert);font-weight:700;">
                                        <?= gjc_e($l['next_due_date'] ?? '') ?>
                                    </span>
                                <?php else: ?>
                                    <?= gjc_e($l['next_due_date'] ?? '&mdash;') ?>
                                <?php endif; ?>
                            </td>
                            <td><span class="<?= $monthBadge ?>"><?= gjc_e($monthLabel) ?></span></td>
                            <td>
                                <span class="<?= $statusBadge ?>">
                                    <?= ucfirst(gjc_e($l['status'] ?? 'pending')) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary js-open-ledger" data-lease-id="<?= (int) $l['id'] ?>">
                                    Manage
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
                            <a class="page-link" href="?<?= gjc_lease_qs(['page' => $page - 1]) ?>">&laquo; Prev</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= gjc_lease_qs(['page' => $i]) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= gjc_lease_qs(['page' => $page + 1]) ?>">Next &raquo;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </section>

    </main>
</div>

<!-- ── New Lease Modal ──────────────────────────────────────────────────── -->
<div class="modal fade" id="leaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header">
                <h5 class="modal-title">New Lease Contract</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="leaseForm" novalidate>
                    <input type="hidden" name="action" value="create_lease">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Merchant <span class="text-danger">*</span></label>
                            <select class="form-select" name="merchant_user_id" id="merchantUserId" required>
                                <option value="">Loading merchants&hellip;</option>
                            </select>
                            <small class="text-muted" id="merchantPickerHint"></small>
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
                            <label class="form-label fw-semibold">Monthly Rent (&#8369;) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="monthly_rent" id="monthlyRent"
                                   step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Security Deposit (&#8369;)</label>
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

<!-- ── Lease Ledger Modal (manage existing lease) ───────────────────────── -->
<div class="modal fade" id="ledgerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content custom-modal">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="ledgerTitle">Lease Ledger</h5>
                    <small class="text-muted" id="ledgerSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ledgerAlert"></div>
                <div id="ledgerLoading" class="text-center text-muted py-5">Loading lease ledger&hellip;</div>
                <div id="ledgerContent" class="d-none">

                    <div id="ledgerStats" class="row g-3 mb-3"></div>

                    <ul class="nav nav-tabs" id="ledgerTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ledgerPaymentsPane" type="button">Payment History</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#ledgerEditPane" type="button">Edit Contract</button>
                        </li>
                    </ul>

                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="ledgerPaymentsPane">
                            <form class="row g-3" id="ledgerPaymentForm">
                                <input type="hidden" name="action" value="record_payment">
                                <input type="hidden" name="lease_id" id="ledgerPayLeaseId">
                                <div class="col-md-3">
                                    <label class="form-label">Amount Paid (&#8369;)</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" name="amount_paid" id="ledgerPayAmount" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Period Covered</label>
                                    <input type="month" class="form-control" name="period_covered" id="ledgerPayPeriod" required value="<?= date('Y-m') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Payment Date</label>
                                    <input type="date" class="form-control" name="payment_date" required value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Method</label>
                                    <select class="form-select" name="payment_method">
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="check">Check</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label">Notes (optional)</label>
                                    <input type="text" class="form-control" name="notes" placeholder="Receipt no., remarks...">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button class="btn btn-success w-100" type="submit">Record Payment</button>
                                </div>
                            </form>

                            <hr>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>Period</th>
                                            <th>Payment Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ledgerPaymentsBody"></tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between align-items-center" id="ledgerPaymentPager"></div>
                        </div>

                        <div class="tab-pane fade" id="ledgerEditPane">
                            <form class="row g-3" id="ledgerEditForm">
                                <input type="hidden" name="action" value="update_lease">
                                <input type="hidden" name="lease_id" id="ledgerEditLeaseId">
                                <div class="col-md-4">
                                    <label class="form-label">Stall Number</label>
                                    <input type="text" class="form-control" name="stall_number" id="ledgerStallNumber">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Stall Name</label>
                                    <input type="text" class="form-control" name="stall_name" id="ledgerStallName">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Monthly Rent (&#8369;)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="monthly_rent" id="ledgerMonthlyRent">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Deposit (&#8369;)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="deposit_amount" id="ledgerDeposit">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Lease Start</label>
                                    <input type="date" class="form-control" name="lease_start" id="ledgerLeaseStart">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Lease End</label>
                                    <input type="date" class="form-control" name="lease_end" id="ledgerLeaseEnd">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Next Due Date</label>
                                    <input type="date" class="form-control" name="next_due_date" id="ledgerNextDue">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="ledgerStatus">
                                        <option value="pending">Pending</option>
                                        <option value="active">Active</option>
                                        <option value="expired">Expired</option>
                                        <option value="terminated">Terminated</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Contract Notes</label>
                                    <textarea class="form-control" rows="2" name="contract_notes" id="ledgerNotes"></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}
window.leaseApiConfig = { endpoint: '<?= ADMIN_URL ?>/api/leases.php' };
</script>
<script src="<?= JS_URL ?>/admin_leases.js"></script>
</body>
</html>
