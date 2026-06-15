<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);
gjc_ensure_stall_application_workflow_schema($db);
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
        FIELD(sa.status,'pending','awaiting_meetup','initially_approved','awaiting_approval','active','rejected','expired'),
        sa.created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Group by status
$grouped = [
    'pending'           => [],
    'awaiting_meetup'   => [],
    'awaiting_approval' => [],
    'active'            => [],
    'rejected'          => [],
];
foreach ($apps as $a) {
    $key = $a['status'];
    if ($key === 'initially_approved') $key = 'awaiting_meetup';
    if ($key === 'approved') $key = 'active';
    if (!array_key_exists($key, $grouped)) $key = 'rejected';
    $grouped[$key][] = $a;
}

$statusMeta = [
    'pending'           => ['label' => 'Pending Review',   'color' => '#f59e0b', 'bg' => '#fffbeb'],
    'awaiting_meetup'   => ['label' => 'Awaiting Meet-up', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
    'awaiting_approval' => ['label' => 'Awaiting Approval','color' => '#7c3aed', 'bg' => '#f5f3ff'],
    'active'            => ['label' => 'Approved / Active','color' => '#10b981', 'bg' => '#ecfdf5'],
    'rejected'          => ['label' => 'Rejected / Expired','color' => '#ef4444', 'bg' => '#fef2f2'],
];

function docLink(string $path): string {
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $label = $ext === 'pdf' ? 'PDF' : 'IMG';
    $url  = ADMIN_URL . '/doc?f=' . rawurlencode(ltrim($path, '/'));
    return "<a href=\"" . htmlspecialchars($url) . "\" target=\"_blank\" class=\"doc-link\" title=\"" . htmlspecialchars(basename($path)) . "\">{$label}</a>";
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
            width:38px; height:32px; border-radius:8px; font-size:11px; font-weight:800;
            color:#064420;
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
        .modal-doc-icon { font-size:11px; font-weight:800; color:#064420; }
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
        .action-panel { display:none; margin-top:14px; padding:14px; border-radius:14px; background:#f9fafb; border:1px solid #e5e7eb; }
        .action-panel.is-open { display:block; }
        .action-panel-title { font-size:12px; font-weight:800; color:#064420; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px; }
        .action-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .action-field { display:flex; flex-direction:column; gap:5px; }
        .action-field.full { grid-column:1/-1; }
        .action-field label { font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; }
        .action-field input,
        .action-field textarea {
            width:100%; padding:10px 12px; border-radius:10px; border:1.5px solid #d1d5db;
            font-family:inherit; font-size:13px; outline:none; color:#111827; background:#fff;
        }
        .action-field textarea { min-height:70px; resize:vertical; }
        .action-field input:focus,
        .action-field textarea:focus { border-color:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.12); }
        .btn-panel-primary {
            margin-top:12px; padding:10px 18px; background:#064420; color:#fff;
            border:none; border-radius:50px; font-size:13px; font-weight:800;
            cursor:pointer; font-family:inherit;
        }

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
                ['label'=>'Total',             'key'=>null,                'color'=>'#6b7280'],
                ['label'=>'Pending Review',    'key'=>'pending',           'color'=>'#f59e0b'],
                ['label'=>'Awaiting Meet-up',  'key'=>'awaiting_meetup',   'color'=>'#3b82f6'],
                ['label'=>'Awaiting Approval', 'key'=>'awaiting_approval', 'color'=>'#7c3aed'],
                ['label'=>'Active',            'key'=>'active',            'color'=>'#10b981'],
                ['label'=>'Rejected',          'key'=>'rejected',          'color'=>'#ef4444'],
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
                    <div class="empty-state-icon">No</div>
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
                            <span class="app-field-val"><?= htmlspecialchars($app['contact_number']) ?> &middot; <?= htmlspecialchars($app['email']) ?></span>
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
            <button class="modal-close" onclick="closeDetail()">&times;</button>
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
const APPS = <?= json_encode(array_values($apps), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const BASE_URL = '<?= BASE_URL ?>';
const DOC_URL  = '<?= ADMIN_URL ?>/doc?f=';
const API_URL  = '<?= ADMIN_URL ?>/api/stall_applications';

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
    const status = normalizeStatus(app.status);

    const docNames = {
        profile_picture:  'Photo',
        business_permit:  'Biz Permit',
        sanitary_permit:  'Sanitary',
        gjc_requirements: 'GJC Req',
        clearance:        'Clearance',
    };

    const docIcons = {
        profile_picture: 'IMG',
        business_permit: 'DOC',
        sanitary_permit: 'DOC',
        gjc_requirements:'DOC',
        clearance:       'DOC',
    };

    const docHtml = Object.keys(docNames).map(field => {
        const path = app[field] || '';
        const ext  = path.split('.').pop().toLowerCase();
        const icon = ext === 'pdf' ? 'PDF' : docIcons[field];
        const url  = DOC_URL + encodeURIComponent(path.replace(/^\//, ''));
        return `<a href="${url}" target="_blank" class="modal-doc-item">
            <span class="modal-doc-icon">${icon}</span>
            <span class="modal-doc-label">${docNames[field]}</span>
        </a>`;
    }).join('');

    const meetupHtml = app.meetup_scheduled_at ? `
        <div class="modal-section">
            <div class="modal-section-title">Meet-up Schedule</div>
            <div class="modal-grid">
                <div class="modal-field"><label>Date & Time</label><p>${esc(formatDateTime(app.meetup_scheduled_at))}</p></div>
                <div class="modal-field"><label>Location</label><p>${esc(app.meetup_location || '')}</p></div>
                ${app.meetup_notes ? `<div class="modal-field" style="grid-column:1/-1"><label>Notes</label><p>${esc(app.meetup_notes)}</p></div>` : ''}
            </div>
        </div>` : '';

    const paymentHtml = app.down_payment_recorded_at ? `
        <div class="modal-section">
            <div class="modal-section-title">Down Payment</div>
            <div class="modal-grid">
                <div class="modal-field"><label>Amount</label><p>&#8369;${parseFloat(app.down_payment_amount || 0).toLocaleString('en-PH',{minimumFractionDigits:2})}</p></div>
                <div class="modal-field"><label>Reference</label><p>${esc(app.down_payment_reference || 'Manual record')}</p></div>
                ${app.down_payment_notes ? `<div class="modal-field" style="grid-column:1/-1"><label>Notes</label><p>${esc(app.down_payment_notes)}</p></div>` : ''}
            </div>
        </div>` : '';

    document.getElementById('modal-title').textContent =
        app.business_name + ' - ' + app.stall_id;

    document.getElementById('modal-body').innerHTML = `
        <div class="modal-section">
            <div class="modal-section-title">Business Information</div>
            <div class="modal-grid">
                <div class="modal-field"><label>Business Name</label><p>${esc(app.business_name)}</p></div>
                <div class="modal-field"><label>Stall</label><p>${esc(app.stall_id)} - ${esc(app.stall_label||app.stall_id)}</p></div>
                <div class="modal-field"><label>Proprietor</label><p>${esc(app.proprietor_name)}</p></div>
                <div class="modal-field"><label>Contact</label><p>${esc(app.contact_number)}</p></div>
                <div class="modal-field" style="grid-column:1/-1"><label>Email</label><p>${esc(app.email)}</p></div>
                ${app.stall_rate ? `<div class="modal-field"><label>Monthly Rate</label><p>&#8369;${parseFloat(app.stall_rate||0).toLocaleString('en-PH',{minimumFractionDigits:2})}</p></div>` : ''}
                ${app.contract_ref ? `<div class="modal-field"><label>Contract Ref</label><p style="font-family:monospace;color:#15803d">${esc(app.contract_ref)}</p></div>` : ''}
            </div>
        </div>
        <div class="modal-section">
            <div class="modal-section-title">Submitted Documents</div>
            <div class="modal-doc-grid">${docHtml}</div>
        </div>
        ${meetupHtml}
        ${paymentHtml}
        ${app.rejection_reason ? `
        <div class="modal-section">
            <div class="modal-section-title" style="color:#ef4444;border-color:#fecaca">Rejection Reason</div>
            <p style="font-size:13px;color:#374151">${esc(app.rejection_reason)}</p>
        </div>` : ''}
        <!-- Reject reason panel (hidden until reject clicked) -->
        <div class="reject-panel" id="rejectPanel">
            <label style="font-size:12px;font-weight:700;color:#ef4444;display:block;margin-bottom:6px">Rejection Reason *</label>
            <textarea id="rejectReason" placeholder="Enter reason for rejection..."></textarea>
            <button class="btn-confirm-reject" onclick="submitReject(${app.id})">Confirm Rejection</button>
        </div>
        <div class="action-panel" id="meetupPanel">
            <div class="action-panel-title">Schedule Meet-up</div>
            <div class="action-grid">
                <div class="action-field"><label>Date</label><input type="date" id="meetupDate"></div>
                <div class="action-field"><label>Time</label><input type="time" id="meetupTime"></div>
                <div class="action-field full"><label>Location</label><input type="text" id="meetupLocation" placeholder="e.g. GJC Finance Office"></div>
                <div class="action-field full"><label>Notes</label><textarea id="meetupNotes" placeholder="Optional instructions for the applicant"></textarea></div>
            </div>
            <button class="btn-panel-primary" onclick="submitScheduleMeetup(${app.id})">Send Meet-up Email</button>
        </div>
        <div class="action-panel" id="paymentPanel">
            <div class="action-panel-title">Record Down Payment</div>
            <div class="action-grid">
                <div class="action-field"><label>Amount</label><input type="number" min="0" step="0.01" id="downPaymentAmount" placeholder="0.00"></div>
                <div class="action-field"><label>Reference</label><input type="text" id="downPaymentReference" placeholder="Receipt / GCash ref"></div>
                <div class="action-field full"><label>Notes</label><textarea id="downPaymentNotes" placeholder="Optional payment notes"></textarea></div>
            </div>
            <button class="btn-panel-primary" onclick="submitDownPayment(${app.id})">Record Down Payment</button>
        </div>
    `;

    // Build action buttons based on status
    let actionsHtml = '';
    if (status === 'pending') {
        actionsHtml = `
            <button class="btn-modal-approve" onclick="toggleActionPanel('meetupPanel')">
                Schedule Meet-up
            </button>
            <button class="btn-modal-reject" onclick="toggleRejectPanel()">
                Reject
            </button>`;
    } else if (status === 'awaiting_meetup') {
        actionsHtml = `
            <button class="btn-modal-approve" onclick="toggleActionPanel('paymentPanel')">
                Record Down Payment
            </button>
            <button class="btn-modal-reject" onclick="toggleRejectPanel()">Reject</button>`;
    } else if (status === 'awaiting_approval') {
        actionsHtml = `
            <button class="btn-modal-approve" onclick="submitFinalApproval(${app.id})">
                Approve &amp; Send Credentials
            </button>
            <button class="btn-modal-reject" onclick="toggleRejectPanel()">Reject</button>`;
    } else if (status === 'active') {
        actionsHtml = `
            <div style="background:#ecfdf5;border-radius:12px;padding:14px;flex:1;font-size:13px;color:#065f46;font-weight:600">
                Application fully approved. Merchant account is active.
            </div>`;
    } else {
        actionsHtml = `
            <div style="background:#fef2f2;border-radius:12px;padding:14px;flex:1;font-size:13px;color:#991b1b;font-weight:600">
                This application has been ${status}. No further actions available.
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
    document.querySelectorAll('.action-panel').forEach(el => el.classList.remove('is-open'));
}

function toggleActionPanel(id) {
    document.getElementById('rejectPanel').style.display = 'none';
    document.querySelectorAll('.action-panel').forEach(el => {
        el.classList.toggle('is-open', el.id === id && !el.classList.contains('is-open'));
    });
}

// â”€â”€ API calls â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function submitScheduleMeetup(appId) {
    const date = document.getElementById('meetupDate').value;
    const time = document.getElementById('meetupTime').value;
    const place = document.getElementById('meetupLocation').value.trim();
    const notes = document.getElementById('meetupNotes').value.trim();
    if (!date || !time || !place) {
        toast('Please enter the meet-up date, time, and location.', 'error');
        return;
    }

    const btn = document.querySelector('.btn-modal-approve');
    if (btn) { btn.disabled = true; btn.textContent = 'Processing...'; }

    post({
        action: 'schedule_meetup',
        app_id: appId,
        meetup_date: date,
        meetup_time: time,
        meetup_location: place,
        meetup_notes: notes
    })
        .then(res => {
            if (res.success) {
                toast(res.message, 'success');
                setTimeout(() => location.reload(), 1400);
            } else {
                toast(res.message, 'error');
                if (btn) { btn.disabled = false; btn.textContent = 'Schedule Meet-up'; }
            }
        })
        .catch(() => {
            toast('Unable to contact the server. Please try again.', 'error');
            if (btn) { btn.disabled = false; btn.textContent = 'Schedule Meet-up'; }
        });
}

function submitDownPayment(appId) {
    const amount = document.getElementById('downPaymentAmount').value;
    const reference = document.getElementById('downPaymentReference').value.trim();
    const notes = document.getElementById('downPaymentNotes').value.trim();
    if (!amount || Number(amount) <= 0) {
        toast('Please enter the down payment amount.', 'error');
        return;
    }

    const btn = document.querySelector('.btn-modal-approve');
    if (btn) { btn.disabled = true; btn.textContent = 'Processing...'; }

    post({
        action: 'record_down_payment',
        app_id: appId,
        down_payment_amount: amount,
        down_payment_reference: reference,
        down_payment_notes: notes
    }).then(res => {
        if (res.success) {
            toast(res.message, 'success');
            setTimeout(() => location.reload(), 1400);
        } else {
            toast(res.message, 'error');
            if (btn) { btn.disabled = false; btn.textContent = 'Record Down Payment'; }
        }
    }).catch(() => {
        toast('Unable to contact the server. Please try again.', 'error');
        if (btn) { btn.disabled = false; btn.textContent = 'Record Down Payment'; }
    });
}

function submitFinalApproval(appId) {
    if (!confirm('Approve this application and create the merchant account?')) return;

    const btn = document.querySelector('.btn-modal-approve');
    if (btn) { btn.disabled = true; btn.textContent = 'Processing...'; }

    post({ action: 'final_approval', app_id: appId })
        .then(res => {
            if (res.success) {
                toast(res.message, 'success');
                setTimeout(() => location.reload(), 1600);
            } else {
                toast(res.message, 'error');
                if (btn) { btn.disabled = false; btn.textContent = 'Approve & Send Credentials'; }
            }
        })
        .catch(() => {
            toast('Unable to contact the server. Please try again.', 'error');
            if (btn) { btn.disabled = false; btn.textContent = 'Approve & Send Credentials'; }
        });
}

function submitReject(appId) {
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { toast('Please enter a rejection reason.', 'error'); return; }

    post({ action: 'reject', app_id: appId, rejection_reason: reason })
        .then(res => {
            toast(res.success ? res.message : res.message, res.success ? 'success' : 'error');
            if (res.success) setTimeout(() => location.reload(), 1400);
        })
        .catch(() => {
            toast('Unable to contact the server. Please try again.', 'error');
        });
}

function post(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k,v]) => fd.append(k, v));
    return fetch(API_URL, { method:'POST', body:fd }).then(async r => {
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch (err) {
            console.error('Invalid stall application API response:', text);
            return {
                success: false,
                message: 'The server returned an invalid response. Check the PHP error log for details.'
            };
        }
    });
}

// â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function normalizeStatus(status) {
    if (status === 'initially_approved') return 'awaiting_meetup';
    if (status === 'approved') return 'active';
    return status || 'rejected';
}
function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
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
