<?php
session_start();
require_once __DIR__ . "/../connection/config.php";
require_once __DIR__ . "/../connection/pdo.php";
require_once __DIR__ . "/../connection/app.php";

gjc_require_role(["merchant"]);
if (
    !gjc_is_merchant_admin() &&
    (gjc_current_role() !== "merchant" || gjc_is_merchant_staff())
) {
    header("Location: " . DASHBOARD_URL);
    exit();
}

$currentUser = gjc_current_user($db);
$merchantUserId = $currentUser["id"];

// Fetch staff accounts created by this merchant admin
$staffList = [];
$stmt = $db->prepare(
    "SELECT userID, first_name, last_name, email, contact_number, sub_role, created_at, status
       FROM users
      WHERE merchant_owner_id = ? AND roleID = 6
      ORDER BY created_at DESC",
);
$stmt->execute([$merchantUserId]);
$staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = "staff";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management | GenPay Merchant</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=19">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="merchant-layout">
    <?php require __DIR__ .
        "/../includes/partials/" .
        (gjc_is_merchant_staff()
            ? "sidebar_merchant_staff.php"
            : "sidebar_merchant_admin.php"); ?>

    <main class="merchant-main">
        <header class="merchant-topbar">
            <button class="merchant-menu-btn" onclick="document.getElementById('merchantSidebar').classList.toggle('collapsed')">&#9776;</button>
            <div><h1>Staff Management</h1><p>Create and manage staff accounts for your stall.</p></div>
            <div class="merchant-user">
                <span><?= gjc_e($currentUser["name"]) ?></span>
                <div class="merchant-avatar"><i class="fa-solid fa-store"></i></div>
            </div>
        </header>

        <section class="merchant-premium-panel">
            <div class="merchant-panel-header d-flex justify-content-between align-items-center">
                <div>
                    <h3>Staff Accounts</h3>
                    <p><?= count($staffList) ?> staff member(s) registered.</p>
                </div>
                <button class="merchant-view-btn" data-bs-toggle="modal" data-bs-target="#staffModal">+ Add Staff</button>
            </div>

            <!-- Status Filter -->
            <div class="d-flex gap-2 mb-3">
                <button class="btn btn-sm btn-success active" id="filter-Active" onclick="setStatusFilter('Active')">Active Only</button>
                <button class="btn btn-sm btn-outline-secondary" id="filter-Inactive" onclick="setStatusFilter('Inactive')">Inactive</button>
                <button class="btn btn-sm btn-outline-secondary" id="filter-All" onclick="setStatusFilter('All')">Show All</button>
            </div>

            <div class="table-responsive">
                <table class="table merchant-premium-table align-middle" id="staffTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Position</th>
                            <th>Date Hired</th>
                            <th style="cursor:pointer" onclick="sortByStatus()" title="Sort by status">
                                Status <i class="fa-solid fa-sort ms-1" id="statusSortIcon"></i>
                            </th>
                            <th>Active</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                    <?php if (empty($staffList)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No staff accounts yet. Add your first staff member.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($staffList as $s): ?>
                        <?php $isActive = ($s['status'] ?? 'Active') === 'Active'; ?>
                        <tr data-status="<?= $isActive ? 'Active' : 'Inactive' ?>">
                            <td><strong><?= gjc_e($s["first_name"] . " " . $s["last_name"]) ?></strong></td>
                            <td><?= gjc_e($s["email"]) ?></td>
                            <td><?= gjc_e($s["contact_number"]) ?></td>
                            <td><span class="merchant-type-pill">Merchant Staff</span></td>
                            <td><?= date("M d, Y", strtotime($s["created_at"])) ?></td>
                            <td>
                                <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?> status-badge">
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        <?= $isActive ? 'checked' : '' ?>
                                        onchange="toggleStaffStatus(<?= (int) $s['userID'] ?>, this)">
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header"><h5 class="modal-title">Create Staff Account</h5></div>
            <div class="modal-body">
                <form id="staffForm">
                    <input type="hidden" name="action" value="create_staff">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" placeholder="09XXXXXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Temporary Password *</label>
                            <input type="password" class="form-control" name="password" required minlength="6">
                        </div>
                    </div>
                    <div id="staffMsg" class="mt-3"></div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="login-btn" style="flex:1" id="staffSubmitBtn">Create Account</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
const STAFF_API = '<?= MERCHANT_URL ?>/api/staff.php';

// ── Create staff ──────────────────────────────────────────────────────────────
document.getElementById('staffForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('staffSubmitBtn');
    btn.disabled = true; btn.textContent = 'Creating...';
    const r = await fetch(STAFF_API, { method:'POST', body: new FormData(this) });
    const d = await r.json();
    const msg = document.getElementById('staffMsg');
    if (d.success) {
        msg.innerHTML = '<div class="alert alert-success">Account created! Reloading...</div>';
        setTimeout(() => location.reload(), 1500);
    } else {
        msg.innerHTML = `<div class="alert alert-danger">${d.message}</div>`;
        btn.disabled = false; btn.textContent = 'Create Account';
    }
});

// ── Toggle staff Active / Inactive ───────────────────────────────────────────
async function toggleStaffStatus(userId, checkbox) {
    checkbox.disabled = true;
    const f = new FormData();
    f.append('action', 'toggle_staff_status');
    f.append('user_id', userId);
    try {
        const r = await fetch(STAFF_API, { method:'POST', body:f });
        const d = await r.json();
        if (d.success) {
            const row = checkbox.closest('tr');
            const isActive = d.new_status === 'Active';
            row.dataset.status = d.new_status;
            const badge = row.querySelector('.status-badge');
            badge.className = 'badge status-badge ' + (isActive ? 'bg-success' : 'bg-secondary');
            badge.textContent = d.new_status;
            checkbox.checked = isActive;
            applyStatusFilter();
        } else {
            checkbox.checked = !checkbox.checked;
            alert(d.message);
        }
    } catch {
        checkbox.checked = !checkbox.checked;
        alert('Network error. Please try again.');
    }
    checkbox.disabled = false;
}

// ── Status filter ─────────────────────────────────────────────────────────────
let currentFilter = 'Active';

function setStatusFilter(value) {
    currentFilter = value;
    ['Active','Inactive','All'].forEach(v => {
        const btn = document.getElementById('filter-' + v);
        if (!btn) return;
        const isActive = v === value;
        btn.className = 'btn btn-sm ' + (isActive
            ? (v === 'Active' ? 'btn-success active' : v === 'Inactive' ? 'btn-secondary active' : 'btn-dark active')
            : 'btn-outline-secondary');
    });
    applyStatusFilter();
}

function applyStatusFilter() {
    document.querySelectorAll('#staffTableBody tr[data-status]').forEach(row => {
        const show = currentFilter === 'All' || row.dataset.status === currentFilter;
        row.style.display = show ? '' : 'none';
    });
}

// ── Sort by status ────────────────────────────────────────────────────────────
let statusSortAsc = true;

function sortByStatus() {
    const tbody = document.getElementById('staffTableBody');
    const rows = Array.from(tbody.querySelectorAll('tr[data-status]'));
    rows.sort((a, b) => {
        const av = a.dataset.status === 'Active' ? 0 : 1;
        const bv = b.dataset.status === 'Active' ? 0 : 1;
        return statusSortAsc ? av - bv : bv - av;
    });
    statusSortAsc = !statusSortAsc;
    const icon = document.getElementById('statusSortIcon');
    if (icon) icon.className = 'fa-solid ms-1 ' + (statusSortAsc ? 'fa-sort-down' : 'fa-sort-up');
    rows.forEach(row => tbody.appendChild(row));
}

// Default: show Active only on page load
document.addEventListener('DOMContentLoaded', () => setStatusFilter('Active'));
</script>
</body>
</html>
