<?php
session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);
$currentUser = gjc_current_user($db);
$currentPage = 'stall_applications';
$adminId     = gjc_user_id();

// Fetch all stall applications with stall info, grouped by status
$apps = $db->query(
    "SELECT sa.*,
            s.label        AS stall_label,
            s.monthly_rate AS stall_rate,
            s.row_label, s.col_number,
            approver.first_name AS approved_fname,
            approver.last_name  AS approved_lname
     FROM stall_applications sa
     LEFT JOIN stalls s        ON s.stall_id = sa.stall_id
     LEFT JOIN users  approver ON approver.userID = sa.initially_approved_by
     ORDER BY
        FIELD(sa.status,'pending','initially_approved','active','rejected'),
        sa.created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Group by status
$grouped = [
    'pending'             => [],
    'initially_approved'  => [],
    'active'              => [],
    'rejected'            => [],
];
foreach ($apps as $a) {
    $key = $a['status'];
    if (!array_key_exists($key, $grouped)) $key = 'rejected';
    $grouped[$key][] = $a;
}

$statusMeta = [
    'pending'            => ['label' => 'Pending Review',      'color' => '#f59e0b', 'bg' => '#fffbeb'],
    'initially_approved' => ['label' => 'Initially Approved',  'color' => '#3b82f6', 'bg' => '#eff6ff'],
    'active'             => ['label' => 'Active / Approved',   'color' => '#10b981', 'bg' => '#ecfdf5'],
    'rejected'           => ['label' => 'Rejected / Expired',  'color' => '#ef4444', 'bg' => '#fef2f2'],
];

function docLink(string $path): string {
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $icon = $ext === 'pdf' ? 'ðŸ“„' : 'ðŸ–¼ï¸';
    $url  = ADMIN_URL . '/doc.php?f=' . urlencode(ltrim($path, '/'));
    return "<a href=\"" . htmlspecialchars($url) . "\" target=\"_blank\" class=\"doc-link\" title=\"" . htmlspecialchars(basename($path)) . "\">{$icon}</a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stall Applications | GJC EduPay Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* â”€â”€ Status tabs â”€â”€ */
        .status-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px; }
        .status-tab {
            padding:7px 18px; border-radius:50px; font-size:12px; font-weight:700;
            cursor:pointer; border:2px solid transparent; transition:all .18s;
            display:flex; align-items:center; gap:6px;
        }
        .status-tab.active, .status-tab:hover { border-color: currentColor; }
        .tab-badge {
            font-size:11px; font-weight:800; border-radius:50px;
            padding:1px 7px; background:rgba(0,0,0,.12); color:inherit;
        }

        /* â”€â”€ App cards â”€â”€ */
        .app-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(360px,1fr)); gap:18px; }
        .app-card {
            background:#fff; border-radius:16px; border:1px solid #e5e7eb;
            box-shadow:0 1px 4px rgba(0,0,0,.06); overflow:hidden;
            transition:box-shadow .2s;
        }
        .app-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.1); }
        .app-card-header {
            padding:16px 18px 12px; display:flex; align-items:flex-start;
            justify-content:space-between; gap:10px;
            border-bottom:1px solid #f3f4f6;
        }
        .app-stall-badge {
            font-size:22px; font-weight:900; line-height:1;
        }
        .app-status-chip {
            font-size:10px; font-weight:800; text-transform:uppercase;
            letter-spacing:.07em; padding:3px 10px; border-radius:50px;
        }
        .app-card-body { padding:14px 18px; }
        .app-field { margin-bottom:9px; }
        .app-field-label { font-size:10px; font-weight:700; text-transform:uppercase;
            letter-spacing:.06em; color:#9ca3af; display:block; margin-bottom:2px; }
        .app-field-val { font-size:13px; font-weight:600; color:#1f2937; }
        .doc-links { display:flex; gap:6px; flex-wrap:wrap; margin-top:4px; }
        .doc-link {
            display:inline-flex; align-items:center; justify-content:center;
            width:32px; height:32px; border-radius:8px; font-size:16px;
            background:#f3f4f6; border:1px solid #e5e7eb;
            text-decoration:none; transition:background .15s;
        }
        .doc-link:hover { background:#e5e7eb; }
        .app-card-footer {
            padding:12px 18px; background:#f9fafb; border-top:1px solid #f3f4f6;
            display:flex; gap:8px; flex-wrap:wrap; align-items:center;
        }
        .app-date { font-size:11px; color:#9ca3af; margin-left:auto; }

        /* â”€â”€ Buttons â”€â”€ */
        .btn-approve {
            background:linear-gradient(135deg,#4ade80,#22c55e); color:#052e16;
            border:none; border-radius:50px; padding:8px 18px;
            font-size:12px; font-weight:800; cursor:pointer;
            transition:all .18s; font-family:inherit;
        }
        .btn-approve:hover { transform:translateY(-1px); box-shadow:0 4px 14px rgba(34,197,94,.35); }
        .btn-reject {
            background:#fff; color:#ef4444; border:1.5px solid #fca5a5;
            border-radius:50px; padding:8px 16px;
            font-size:12px; font-weight:700; cursor:pointer; transition:all .18s; font-family:inherit;
        }
        .btn-reject:hover { background:#fee2e2; }

        /* â”€â”€ Detail modal â”€â”€ */
        .modal-overlay {
            display:none; position:fixed; inset:0; z-index:3000;
            background:rgba(5,46,22,.5); backdrop-filter:blur(5px);
            align-items:center; justify-content:center; padding:20px;
        }
        .modal-overlay.is-open { display:flex; }
        .modal-box {
            background:#fff; border-radius:20px; width:100%; max-width:560px;
            max-height:90vh; overflow-y:auto;
            box-shadow:0 20px 60px rgba(0,0,0,.2);
            animation: slideUp .22s ease;
        }
        @keyframes slideUp { from{transform:translateY(24px);opacity:0} to{transform:translateY(0);opacity:1} }
        .modal-head {
            padding:22px 24px 16px; border-bottom:1px solid #e5e7eb;
            display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:#fff; z-index:1;
        }
        .modal-head h4 { font-size:18px; font-weight:800; margin:0; }
        .modal-close {
            background:#f3f4f6; border:none; border-radius:50%;
            width:32px; height:32px; cursor:pointer; font-size:18px;
            display:flex; align-items:center; justify-content:center;
            color:#6b7280; transition:background .15s; line-height:1;
        }
        .modal-close:hover { background:#e5e7eb; }
        .modal-body-content { padding:20px 24px 24px; }
        .modal-section { margin-bottom:20px; }
        .modal-section-title {
            font-size:11px; font-weight:800; text-transform:uppercase;
            letter-spacing:.08em; color:#15803d; margin-bottom:12px;
            padding-bottom:6px; border-bottom:1px solid #dcfce7;
        }
        .modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .modal-field label { font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; display:block; margin-bottom:3px; }
        .modal-field p { font-size:14px; font-weight:600; color:#111827; margin:0; }
        .modal-doc-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; }
        .modal-doc-item {
            display:flex; flex-direction:column; align-items:center; gap:4px;
            padding:10px 6px; background:#f9fafb; border-radius:10px;
            border:1px solid #e5e7eb; text-decoration:none; transition:background .15s;
        }
        .modal-doc-item:hover { background:#f0fdf4; border-color:#86efac; }
        .modal-doc-icon { font-size:22px; }
        .modal-doc-label { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; text-align:center; }
        .modal-actions { display:flex; gap:10px; flex-wrap:wrap; padding:0 24px 24px; border-top:1px solid #f3f4f6; padding-top:18px; }
        .btn-modal-approve {
            flex:1; padding:13px; background:linear-gradient(135deg,#4ade80,#22c55e);
            color:#052e16; border:none; border-radius:50px; font-size:14px;
            font-weight:800; cursor:pointer; font-family:inherit; transition:all .18s;
        }
        .btn-modal-approve:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(34,197,94,.35); }
        .btn-modal-reject {
            padding:13px 24px; background:#fff; color:#ef4444;
            border:2px solid #fca5a5; border-radius:50px; font-size:14px;
            font-weight:700; cursor:pointer; font-family:inherit; transition:background .18s;
        }
        .btn-modal-reject:hover { background:#fee2e2; }

        /* â”€â”€ Reject reason input â”€â”€ */
        .reject-panel { display:none; margin-top:14px; }
        .reject-panel textarea {
            width:100%; padding:10px 14px; border-radius:10px;
            border:2px solid #fca5a5; font-family:inherit; font-size:13px;
            color:#111; resize:vertical; min-height:80px; outline:none;
        }
        .reject-panel textarea:focus { border-color:#ef4444; }
        .btn-confirm-reject {
            margin-top:8px; padding:10px 22px; background:#ef4444; color:#fff;
            border:none; border-radius:50px; font-size:13px; font-weight:700;
            cursor:pointer; font-family:inherit; transition:background .15s;
        }
        .btn-confirm-reject:hover { background:#dc2626; }

        /* Empty state */
        .empty-state { text-align:center; padding:48px 20px; color:#9ca3af; }
        .empty-state-icon { font-size:48px; margin-bottom:12px; }
        .empty-state-text { font-size:14px; font-weight:600; }

        /* Toast */
        .toast-wrap {
            position:fixed; bottom:24px; right:24px; z-index:9999;
            display:flex; flex-direction:column; gap:8px;
        }
        .toast {
            padding:12px 20px; border-radius:12px; font-size:13px; font-weight:700;
            box-shadow:0 4px 16px rgba(0,0,0,.15); animation:fadeIn .2s ease;
            max-width:320px;
        }
        .toast--success { background:#064420; color:#4ade80; }
        .toast--error   { background:#7f1d1d; color:#fca5a5; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

    <main class="admin-main">
        <header class="topbar">
            <button class="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">&#9776;</button>
            <div>
                <h1>Stall Applications</h1>
                <p>Review public vendor applications and manage initial approvals.</p>
            </div>
            <div class="admin-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="avatar"><img src="<?= ICONS_URL ?>/admin.png" alt="Admin"></div>
            </div>
        </header>

        <!-- Summary metric cards -->
        <section class="row g-3 mb-4">
            <?php
            $metricDefs = [
                ['label'=>'Total',              'key'=>null,               'color'=>'#6b7280'],
                ['label'=>'Pending Review',     'key'=>'pending',          'color'=>'#f59e0b'],
                ['label'=>'Initially Approved', 'key'=>'initially_approved','color'=>'#3b82f6'],
                ['label'=>'Active',             'key'=>'active',           'color'=>'#10b981'],
                ['label'=>'Rejected',           'key'=>'rejected',         'color'=>'#ef4444'],
            ];
            foreach ($metricDefs as $m):
                $cnt = $m['key'] ? count($grouped[$m['key']]) : count($apps);
            ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="metric-card" style="padding:16px;border-top:3px solid <?= $m['color'] ?>">
                    <span style="font-size:11px;color:#6b7280"><?= $m['label'] ?></span>
                    <h2 style="color:<?= $m['color'] ?>"><?= $cnt ?></h2>
                </div>
            </div>
            <?php endforeach; ?>
        </section>

        <!-- Status filter tabs -->
        <div class="status-tabs" id="statusTabs">
            <?php foreach ($statusMeta as $key => $meta): ?>
            <div class="status-tab" id="tab-<?= $key ?>"
                 style="color:<?= $meta['color'] ?>;background:<?= $meta['bg'] ?>"
                 onclick="filterTab('<?= $key ?>')">
                <?= $meta['label'] ?>
                <span class="tab-badge"><?= count($grouped[$key]) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Application grids per status -->
        <?php foreach ($grouped as $status => $statusApps): ?>
        <div class="app-section" id="section-<?= $status ?>" style="display:none">
            <?php if (empty($statusApps)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">ðŸ“­</div>
                    <div class="empty-state-text">No <?= strtolower($statusMeta[$status]['label']) ?> applications.</div>
                </div>
            <?php else: ?>
            <div class="app-grid">
                <?php foreach ($statusApps as $app):
                    $meta = $statusMeta[$status];
                ?>
                <div class="app-card">
                    <div class="app-card-header">
                        <div>
                            <div class="app-stall-badge" style="color:<?= $meta['color'] ?>">
                                <?= htmlspecialchars($app['stall_id']) ?>
                            </div>
                            <div style="font-size:12px;color:#6b7280;margin-top:3px">
                                <?= htmlspecialchars($app['stall_label'] ?? $app['stall_id']) ?>
                            </div>
                        </div>
                        <span class="app-status-chip"
                              style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>">
                            <?= $meta['label'] ?>
                        </span>
                    </div>
                    <div class="app-card-body">
                        <div class="app-field">
                            <span class="app-field-label">Business</span>
                            <span class="app-field-val"><?= htmlspecialchars($app['business_name']) ?></span>
                        </div>
                        <div class="app-field">
                            <span class="app-field-label">Proprietor</span>
                            <span class="app-field-val"><?= htmlspecialchars($app['proprietor_name']) ?></span>
                        </div>
                        <div class="app-field">
                            <span class="app-field-label">Contact</span>
                            <span class="app-field-val"><?= htmlspecialchars($app['contact_number']) ?> Â· <?= htmlspecialchars($app['email']) ?></span>
                        </div>
                        <div class="app-field">
                            <span class="app-field-label">Documents</span>
                            <div class="doc-links">
                                <?= docLink($app['profile_picture']) ?>
                                <?= docLink($app['business_permit']) ?>
                                <?= docLink($app['sanitary_permit']) ?>
                                <?= docLink($app['gjc_requirements']) ?>
                                <?= docLink($app['clearance']) ?>
                            </div>
                        </div>
                        <?php if ($app['contract_ref']): ?>
                        <div class="app-field">
                            <span class="app-field-label">Contract Ref</span>
                            <span class="app-field-val" style="color:#15803d;font-family:monospace"><?= htmlspecialchars($app['contract_ref']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="app-card-footer">
                        <button class="btn-approve" onclick="openDetail(<?= $app['id'] ?>)">
                            Review &amp; Action
                        </button>
                        <span class="app-date"><?= date('M j, Y', strtotime($app['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

    </main>
</div>

<!-- Detail / Action Modal -->
<div class="modal-overlay" id="detailModal" onclick="if(event.target===this)closeDetail()">
    <div class="modal-box">
        <div class="modal-head">
            <h4 id="modal-title">Application Detail</h4>
            <button class="modal-close" onclick="closeDetail()">Ã—</button>
        </div>
        <div class="modal-body-content" id="modal-body">
            <!-- Populated by JS -->
        </div>
        <div class="modal-actions" id="modal-actions">
            <!-- Populated by JS -->
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="toast-wrap" id="toastWrap"></div>

<script>
// â”€â”€ Raw application data from PHP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const APPS = <?= json_encode(array_values($apps), JSON_HEX_TAG) ?>;
const BASE_URL = '<?= BASE_URL ?>';
const DOC_URL  = '<?= ADMIN_URL ?>/doc.php?f=';
const API_URL  = '<?= ADMIN_URL ?>/api/stall_applications.php';

// â”€â”€ Tab filter â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function filterTab(status) {
    document.querySelectorAll('.app-section').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.status-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('section-' + status).style.display = 'block';
    document.getElementById('tab-' + status).classList.add('active');
}
// Default: show pending
filterTab('pending');

// â”€â”€ Detail modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function openDetail(appId) {
    const app = APPS.find(a => a.id == appId);
    if (!app) return;

    const docNames = {
        profile_picture:  'Photo',
        business_permit:  'Biz Permit',
        sanitary_permit:  'Sanitary',
        gjc_requirements: 'GJC Req',
        clearance:        'Clearance',
    };

    const docIcons = {
        profile_picture: 'ðŸ–¼ï¸',
        business_permit: 'ðŸ“‹',
        sanitary_permit: 'ðŸ¥',
        gjc_requirements:'ðŸŽ“',
        clearance:       'âœ…',
    };

    const docHtml = Object.keys(docNames).map(field => {
        const path = app[field] || '';
        const ext  = path.split('.').pop().toLowerCase();
        const icon = ext === 'pdf' ? 'ðŸ“„' : docIcons[field];
        const url  = DOC_URL + encodeURIComponent(path.replace(/^\//, ''));
        return `<a href="${url}" target="_blank" class="modal-doc-item">
            <span class="modal-doc-icon">${icon}</span>
            <span class="modal-doc-label">${docNames[field]}</span>
        </a>`;
    }).join('');

    document.getElementById('modal-title').textContent =
        app.business_name + ' â€” ' + app.stall_id;

    document.getElementById('modal-body').innerHTML = `
        <div class="modal-section">
            <div class="modal-section-title">Business Information</div>
            <div class="modal-grid">
                <div class="modal-field"><label>Business Name</label><p>${esc(app.business_name)}</p></div>
                <div class="modal-field"><label>Stall</label><p>${esc(app.stall_id)} â€” ${esc(app.stall_label||app.stall_id)}</p></div>
                <div class="modal-field"><label>Proprietor</label><p>${esc(app.proprietor_name)}</p></div>
                <div class="modal-field"><label>Contact</label><p>${esc(app.contact_number)}</p></div>
                <div class="modal-field" style="grid-column:1/-1"><label>Email</label><p>${esc(app.email)}</p></div>
                ${app.monthly_rate ? `<div class="modal-field"><label>Monthly Rate</label><p>â‚±${parseFloat(app.stall_rate||0).toLocaleString('en-PH',{minimumFractionDigits:2})}</p></div>` : ''}
                ${app.contract_ref ? `<div class="modal-field"><label>Contract Ref</label><p style="font-family:monospace;color:#15803d">${esc(app.contract_ref)}</p></div>` : ''}
            </div>
        </div>
        <div class="modal-section">
            <div class="modal-section-title">Submitted Documents</div>
            <div class="modal-doc-grid">${docHtml}</div>
        </div>
        ${app.rejection_reason ? `
        <div class="modal-section">
            <div class="modal-section-title" style="color:#ef4444;border-color:#fecaca">Rejection Reason</div>
            <p style="font-size:13px;color:#374151">${esc(app.rejection_reason)}</p>
        </div>` : ''}
        <!-- Reject reason panel (hidden until reject clicked) -->
        <div class="reject-panel" id="rejectPanel">
            <label style="font-size:12px;font-weight:700;color:#ef4444;display:block;margin-bottom:6px">Rejection Reason *</label>
            <textarea id="rejectReason" placeholder="Enter reason for rejectionâ€¦"></textarea>
            <button class="btn-confirm-reject" onclick="submitReject(${app.id})">Confirm Rejection</button>
        </div>
    `;

    // Build action buttons based on status
    let actionsHtml = '';
    if (app.status === 'pending') {
        actionsHtml = `
            <button class="btn-modal-approve" onclick="submitInitialApproval(${app.id})">
                âœ… Grant Initial Approval
            </button>
            <button class="btn-modal-reject" onclick="toggleRejectPanel()">
                Reject
            </button>`;
    } else if (app.status === 'initially_approved') {
        actionsHtml = `
            <a href="${BASE_URL}/admin/stall_verify.php?app_id=${app.id}" class="btn-modal-approve" style="text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px">
                ðŸ“‹ Proceed to Step 2.2 â€” Contract &amp; Payment
            </a>
            <button class="btn-modal-reject" onclick="toggleRejectPanel()">Reject</button>`;
    } else if (app.status === 'active') {
        actionsHtml = `
            <div style="background:#ecfdf5;border-radius:12px;padding:14px;flex:1;font-size:13px;color:#065f46;font-weight:600">
                âœ… Application fully approved. Merchant account is active.
            </div>`;
    } else {
        actionsHtml = `
            <div style="background:#fef2f2;border-radius:12px;padding:14px;flex:1;font-size:13px;color:#991b1b;font-weight:600">
                âŒ This application has been ${app.status}. No further actions available.
            </div>`;
    }
    document.getElementById('modal-actions').innerHTML = actionsHtml;
    document.getElementById('detailModal').classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

function closeDetail() {
    document.getElementById('detailModal').classList.remove('is-open');
    document.body.style.overflow = '';
}

function toggleRejectPanel() {
    const p = document.getElementById('rejectPanel');
    p.style.display = p.style.display === 'block' ? 'none' : 'block';
}

// â”€â”€ API calls â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function submitInitialApproval(appId) {
    const btn = document.querySelector('.btn-modal-approve');
    if (btn) { btn.disabled = true; btn.textContent = 'Processingâ€¦'; }

    post({ action: 'initial_approval', app_id: appId })
        .then(res => {
            if (res.success) {
                toast(res.message, 'success');
                setTimeout(() => location.reload(), 1400);
            } else {
                toast(res.message, 'error');
                if (btn) { btn.disabled = false; btn.textContent = 'âœ… Grant Initial Approval'; }
            }
        });
}

function submitReject(appId) {
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { toast('Please enter a rejection reason.', 'error'); return; }

    post({ action: 'reject', app_id: appId, rejection_reason: reason })
        .then(res => {
            toast(res.success ? res.message : res.message, res.success ? 'success' : 'error');
            if (res.success) setTimeout(() => location.reload(), 1400);
        });
}

function post(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k,v]) => fd.append(k, v));
    return fetch(API_URL, { method:'POST', body:fd }).then(r => r.json());
}

// â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function toast(msg, type='success') {
    const t = document.createElement('div');
    t.className = `toast toast--${type}`;
    t.textContent = msg;
    document.getElementById('toastWrap').appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

// Close on Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetail(); });
</script>

</body>
</html>
