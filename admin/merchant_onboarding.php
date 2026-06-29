<?php
session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);
$currentUser = gjc_current_user($db);
$currentPage = 'onboarding';
$adminId     = gjc_user_id();

// Fetch all applications
$applications = [];
if (gjc_table_exists($db, 'merchant_applications')) {
    $applications = $db->query(
        "SELECT * FROM merchant_applications ORDER BY created_at ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// Group by stage
$stages = [
    'submitted'         => [],
    'compliance_review' => [],
    'exec_review'       => [],
    'approved'          => [],
    'rejected'          => [],
];
foreach ($applications as $app) {
    $stage = $app['stage'] ?? 'submitted';
    if (isset($stages[$stage])) $stages[$stage][] = $app;
}

$stageLabels = [
    'submitted'         => 'Submitted',
    'compliance_review' => 'Compliance Review',
    'exec_review'       => 'Exec Review',
    'approved'          => 'Approved',
    'rejected'          => 'Rejected',
];
$stageColors = [
    'submitted'         => '#3b82f6',
    'compliance_review' => '#f59e0b',
    'exec_review'       => '#8b5cf6',
    'approved'          => '#10b981',
    'rejected'          => 'var(--gjc-alert)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Onboarding | GenPay Admin</title>
    <meta name="description" content="Multi-stage merchant vendor application pipeline for GenPay.">
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=4">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .pipeline-board {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 12px;
            align-items: flex-start;
        }
        .pipeline-col {
            flex: 0 0 240px;
            background: #f8fafc;
            border-radius: 14px;
            padding: 14px;
            min-height: 280px;
        }
        .pipeline-col-header {
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #fff;
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pipeline-count {
            background: rgba(255,255,255,.25);
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .pipeline-card {
            background: #fff;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
            border: 1px solid #e5e7eb;
            transition: box-shadow .2s;
        }
        .pipeline-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); }
        .pipeline-card h6 { font-weight: 700; font-size: 13px; margin: 0 0 3px; }
        .pipeline-card .pc-meta { font-size: 11px; color: #6b7280; margin: 0 0 8px; }
        .pipeline-card .pc-actions { display: flex; gap: 5px; flex-wrap: wrap; }
        .pipeline-empty { text-align: center; color: #9ca3af; font-size: 12px; padding: 20px 0; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

    <main class="admin-main">
        <header class="topbar">
            <button class="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h1>Merchant Onboarding</h1>
                <p>Multi-stage vendor application pipeline with compliance and executive review.</p>
            </div>
            <div class="admin-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="avatar"><i class="fa-solid fa-user-tie"></i></div>
            </div>
        </header>

        <!-- Summary Cards -->
        <section class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="metric-card" style="padding:16px">
                    <span style="font-size:12px">Total</span>
                    <h2><?= count($applications) ?></h2>
                </div>
            </div>
            <?php foreach ($stages as $key => $apps): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="metric-card" style="padding:16px;border-top:3px solid <?= $stageColors[$key] ?>">
                    <span style="font-size:11px;color:#6b7280"><?= $stageLabels[$key] ?></span>
                    <h2 style="color:<?= $stageColors[$key] ?>"><?= count($apps) ?></h2>
                </div>
            </div>
            <?php endforeach; ?>
        </section>

        <div class="d-flex justify-content-end mb-3">
            <button class="view-btn" data-bs-toggle="modal" data-bs-target="#newAppModal" id="btn-new-application">
                <i class="fa-solid fa-store"></i> Register Application
            </button>
        </div>

        <!-- Pipeline Kanban Board -->
        <div class="pipeline-board">
            <?php foreach ($stages as $stageKey => $apps): ?>
            <div class="pipeline-col">
                <div class="pipeline-col-header" style="background:<?= $stageColors[$stageKey] ?>">
                    <span><?= $stageLabels[$stageKey] ?></span>
                    <span class="pipeline-count"><?= count($apps) ?></span>
                </div>

                <?php if (empty($apps)): ?>
                    <div class="pipeline-empty">No applications</div>
                <?php endif; ?>

                <?php foreach ($apps as $app): ?>
                <div class="pipeline-card">
                    <h6><?= gjc_e($app['business_name']) ?></h6>
                    <p class="pc-meta">
                        <?= gjc_e($app['owner_name']) ?><br>
                        <?= gjc_e($app['owner_email']) ?><br>
                        <em><?= date('M d, Y', strtotime($app['created_at'])) ?></em>
                    </p>
                    <div class="pc-actions">
                        <?php if ($stageKey === 'submitted'): ?>
                            <button class="btn btn-sm btn-outline-warning"
                                onclick="advanceApp(<?= (int)$app['id'] ?>, 'compliance_review')">
                                &rarr; Compliance
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="rejectApp(<?= (int)$app['id'] ?>)">
                                Reject
                            </button>
                        <?php elseif ($stageKey === 'compliance_review'): ?>
                            <button class="btn btn-sm btn-outline-primary"
                                onclick="advanceApp(<?= (int)$app['id'] ?>, 'exec_review')">
                                &rarr; Exec Review
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="rejectApp(<?= (int)$app['id'] ?>)">
                                Reject
                            </button>
                        <?php elseif ($stageKey === 'exec_review'): ?>
                            <button class="btn btn-sm btn-outline-success"
                                onclick="approveApp(<?= (int)$app['id'] ?>)">
                                &#10003; Approve
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="rejectApp(<?= (int)$app['id'] ?>)">
                                Reject
                            </button>
                        <?php elseif ($stageKey === 'approved'): ?>
                            <span class="badge-success" style="font-size:11px">&#10003; Activated</span>
                            <?php if ($app['generated_user_id']): ?>
                                <small class="text-muted">UID: <?= (int)$app['generated_user_id'] ?></small>
                            <?php endif; ?>
                        <?php elseif ($stageKey === 'rejected'): ?>
                            <span class="badge-danger" style="font-size:11px">&#10007; Rejected</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<!-- Register Application Modal -->
<div class="modal fade" id="newAppModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header"><h5 class="modal-title">Register Vendor Application</h5></div>
            <div class="modal-body">
                <form id="newAppForm">
                    <input type="hidden" name="action" value="submit_application">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Business Name *</label>
                            <input type="text" class="form-control" name="business_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Owner Full Name *</label>
                            <input type="text" class="form-control" name="owner_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Owner Email *</label>
                            <input type="email" class="form-control" name="owner_email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Number *</label>
                            <input type="text" class="form-control" name="owner_contact" required placeholder="09XXXXXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Preferred Stall No.</label>
                            <input type="text" class="form-control" name="stall_number" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Products to be Sold *</label>
                            <textarea class="form-control" name="product_types" rows="3" required
                                placeholder="e.g. Rice meals, Fresh juices, Bread and pastries, Snacks"></textarea>
                        </div>
                    </div>
                    <div id="newAppMsg" class="mt-3"></div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="login-btn" style="flex:1" id="newAppBtn">Submit Application</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header"><h5 class="modal-title">Reject Application</h5></div>
            <div class="modal-body">
                <form id="rejectForm">
                    <input type="hidden" name="action" value="reject_application">
                    <input type="hidden" name="app_id" id="rejectAppId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rejection Reason *</label>
                        <textarea class="form-control" name="rejection_reason" rows="3" required
                            placeholder="Provide a clear reason for rejection..."></textarea>
                    </div>
                    <div id="rejectMsg" class="mb-2"></div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger" style="flex:1">Confirm Rejection</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
const API = '<?= ADMIN_URL ?>/api/merchant_onboarding.php';

async function advanceApp(id, nextStage) {
    const f = new FormData();
    f.append('action', 'advance_stage');
    f.append('app_id', id);
    f.append('next_stage', nextStage);
    const r = await fetch(API, { method: 'POST', body: f });
    const d = await r.json();
    if (d.success) location.reload();
    else alert('Error: ' + d.message);
}

function rejectApp(id) {
    document.getElementById('rejectAppId').value = id;
    document.getElementById('rejectMsg').innerHTML = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

async function approveApp(id) {
    if (!confirm('Approve this application and automatically create a Merchant Admin account?\n\nA temporary password will be shown â€” share it securely with the vendor.')) return;
    const f = new FormData();
    f.append('action', 'approve_application');
    f.append('app_id', id);
    const r = await fetch(API, { method: 'POST', body: f });
    const d = await r.json();
    if (d.success) {
        alert('Approved!\n\n' + d.message);
        location.reload();
    } else {
        alert('Failed: ' + d.message);
    }
}

document.getElementById('newAppForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('newAppBtn');
    btn.disabled = true; btn.textContent = 'Submitting...';
    const r = await fetch(API, { method: 'POST', body: new FormData(this) });
    const d = await r.json();
    const msg = document.getElementById('newAppMsg');
    if (d.success) {
        msg.innerHTML = '<div class="alert alert-success">Application submitted and entered into the pipeline! Reloading...</div>';
        setTimeout(() => location.reload(), 1400);
    } else {
        msg.innerHTML = '<div class="alert alert-danger">' + d.message + '</div>';
        btn.disabled = false; btn.textContent = 'Submit Application';
    }
});

document.getElementById('rejectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const r = await fetch(API, { method: 'POST', body: new FormData(this) });
    const d = await r.json();
    const msg = document.getElementById('rejectMsg');
    if (d.success) {
        msg.innerHTML = '<div class="alert alert-success">Application rejected. Reloading...</div>';
        setTimeout(() => location.reload(), 1200);
    } else {
        msg.innerHTML = '<div class="alert alert-danger">' + d.message + '</div>';
    }
});
</script>
</body>
</html>
