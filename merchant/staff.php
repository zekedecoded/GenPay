<?php
session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['merchant']);
if (!gjc_is_merchant_admin() && (gjc_current_role() !== 'merchant' || gjc_is_merchant_staff())) {
    header('Location: ' . DASHBOARD_URL);
    exit;
}

$currentUser    = gjc_current_user($db);
$merchantUserId = $currentUser['id'];

// Fetch staff accounts created by this merchant admin
$staffList = [];
$stmt = $db->prepare(
    "SELECT userID, first_name, last_name, email, contact_number, sub_role, created_at
       FROM users
      WHERE merchant_owner_id = ? AND roleID = 6
      ORDER BY created_at DESC"
);
$stmt->execute([$merchantUserId]);
$staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management | GJC EduPay Merchant</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=11">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="merchant-layout">
    <?php require __DIR__ . '/../includes/partials/' . (gjc_is_merchant_staff() ? 'sidebar_merchant_staff.php' : 'sidebar_merchant_admin.php'); ?>

    <main class="merchant-main">
        <header class="merchant-topbar">
            <button class="merchant-menu-btn" onclick="document.getElementById('merchantSidebar').classList.toggle('collapsed')">&#9776;</button>
            <div><h1>Staff Management</h1><p>Create and manage cashier staff accounts for your stall.</p></div>
            <div class="merchant-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="merchant-avatar"><img src="<?= ICONS_URL ?>/store.png" alt=""></div>
            </div>
        </header>

        <section class="merchant-premium-panel">
            <div class="merchant-panel-header d-flex justify-content-between align-items-center">
                <div><h3>Cashier Accounts</h3><p><?= count($staffList) ?> staff member(s) registered.</p></div>
                <button class="merchant-view-btn" data-bs-toggle="modal" data-bs-target="#staffModal">+ Add Cashier</button>
            </div>

            <div class="table-responsive">
                <table class="table merchant-premium-table align-middle">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Contact</th><th>Role</th><th>Since</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">No cashier accounts yet. Add your first staff member.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($staffList as $s): ?>
                        <tr>
                            <td><strong><?= gjc_e($s['first_name'] . ' ' . $s['last_name']) ?></strong></td>
                            <td><?= gjc_e($s['email']) ?></td>
                            <td><?= gjc_e($s['contact_number']) ?></td>
                            <td><span class="merchant-type-pill">Merchant Staff</span></td>
                            <td><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger"
                                    onclick="deactivateStaff(<?= (int)$s['userID'] ?>, '<?= gjc_e($s['first_name']) ?>')">
                                    Deactivate
                                </button>
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
            <div class="modal-header"><h5 class="modal-title">Create Cashier Account</h5></div>
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

async function deactivateStaff(userId, name) {
    if (!confirm(`Deactivate ${name}'s account? They will no longer be able to log in.`)) return;
    const f = new FormData();
    f.append('action', 'deactivate_staff'); f.append('user_id', userId);
    const r = await fetch(STAFF_API, { method:'POST', body:f });
    const d = await r.json();
    if (d.success) location.reload(); else alert(d.message);
}
</script>
</body>
</html>
