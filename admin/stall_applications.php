<?php
// ============================================================
//  admin/stall_applications.php
//  ONE-STOP stall application management.
//
//  Submit -> meeting auto-scheduled at submission -> single in-person meeting
//  (verify docs + sign contract + pay) -> Awarded.
//
//  Two visible statuses: Pending for Verification and Awarded. Rejected and
//  Cancelled are kept as records (daily log / history) but are internal.
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../connection/config.php";
require_once __DIR__ . "/../connection/pdo.php";
require_once __DIR__ . "/../connection/app.php";
require_once __DIR__ . "/../connection/StallManager.php";

gjc_require_role(["finance"]);
gjc_ensure_stall_application_workflow_schema($db);
gjc_ensure_meeting_scheduling_schema($db);
$currentUser = gjc_current_user($db);
$currentPage = "stall_applications";
$adminId = gjc_user_id();

$apps = $db->query(
    "SELECT sa.*, s.label AS stall_label, s.monthly_rate AS stall_rate
       FROM stall_applications sa
       LEFT JOIN stalls s ON s.stall_id = sa.stall_id
      WHERE sa.status IN ('pending_verification','awarded','rejected','cancelled')
      ORDER BY (sa.status = 'pending_verification') DESC, sa.meetup_scheduled_at ASC, sa.created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Vacant stalls available for assignment at award.
$stallMgr = new StallManager($db);
$vacantStalls = array_values(
    array_filter($stallMgr->allStalls(), fn($s) => $s["status"] === "vacant")
);

// Today's meetings — the appointment log the admin works from, time-ordered.
$today = date("Y-m-d");
$todaySchedule = array_values(array_filter(
    $apps,
    fn($a) => $a["meetup_scheduled_at"] && substr($a["meetup_scheduled_at"], 0, 10) === $today
));
usort($todaySchedule, fn($a, $b) => strcmp((string) $a["meetup_scheduled_at"], (string) $b["meetup_scheduled_at"]));

$statusCounts = ["pending_verification" => 0, "awarded" => 0, "rejected" => 0, "cancelled" => 0];
foreach ($apps as $a) {
    if (isset($statusCounts[$a["status"]])) {
        $statusCounts[$a["status"]]++;
    }
}

$STATUS_META = [
    "pending_verification" => ["label" => "Pending for Verification", "badge" => "warning", "icon" => "fa-hourglass-half"],
    "awarded"              => ["label" => "Awarded",                   "badge" => "success", "icon" => "fa-circle-check"],
    "rejected"             => ["label" => "Rejected",                  "badge" => "danger",  "icon" => "fa-circle-xmark"],
    "cancelled"            => ["label" => "Cancelled",                 "badge" => "secondary", "icon" => "fa-ban"],
];

function sa_meeting_label(?string $dt): string
{
    if (!$dt) {
        return '<span class="text-muted">&mdash;</span>';
    }
    $ts = strtotime($dt);
    return '<span class="fw-semibold">' . date("M j, Y", $ts) . '</span><br><span class="small text-muted">' . date("g:i A", $ts) . '</span>';
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
    <title>Stall Applications | GenPay Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/stall_applications.css?v=2">
    <style>
    .sa-kpi { border:1px solid #e5e7eb; border-radius:16px; padding:16px 18px; background:#fff; }
    .sa-kpi .n { font-size:28px; font-weight:800; line-height:1; }
    .sa-kpi .l { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; }
    .today-card { border:1px solid #e5e7eb; border-radius:16px; background:#fff; overflow:hidden; }
    .today-head { padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
    .today-item { display:flex; align-items:center; gap:14px; padding:12px 20px; border-bottom:1px solid #f6f7f9; cursor:pointer; transition:.12s; }
    .today-item:last-child { border-bottom:0; }
    .today-item:hover { background:#f0fdf4; }
    .today-time { min-width:78px; font-weight:800; color:#064420; }
    .today-who { flex:1; min-width:0; }
    .today-who .b { font-weight:700; }
    .today-who .p { font-size:12px; color:#6b7280; }
    .sa-doc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; }
    .sa-doc { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; }
    .sa-doc .cap { display:flex; justify-content:space-between; align-items:center; padding:8px 10px; font-size:12px; font-weight:700; border-bottom:1px solid #f1f5f9; }
    .sa-doc iframe { width:100%; height:180px; border:0; display:block; background:#1e1e1e; }
    .sa-section { border:1px solid #e5e7eb; border-radius:14px; padding:16px; }
    .sa-section h6 { font-weight:800; margin-bottom:12px; }
    .sa-done-pill { font-size:11px; font-weight:800; color:#166534; background:#dcfce7; border-radius:999px; padding:3px 10px; }
    .filter-btn.active { background:#064420; color:#fff; border-color:#064420; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/partials/sidebar_admin.php"; ?>

    <main class="admin-main">
        <header class="topbar">
            <button class="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h1>Stall Applications</h1>
                <p>One-stop verification: documents, contract, and payment in a single meeting.</p>
            </div>
            <div class="admin-user">
                <span><?= gjc_e($currentUser["name"]) ?></span>
                <div class="avatar"><i class="fa-solid fa-user-tie"></i></div>
            </div>
        </header>

        <!-- Two visible KPIs -->
        <section class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="sa-kpi">
                    <div class="l"><i class="fa-solid fa-hourglass-half text-warning me-1"></i> Pending Verification</div>
                    <div class="n mt-2"><?= $statusCounts["pending_verification"] ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="sa-kpi">
                    <div class="l"><i class="fa-solid fa-circle-check text-success me-1"></i> Awarded (Active Tenants)</div>
                    <div class="n mt-2"><?= $statusCounts["awarded"] ?></div>
                </div>
            </div>
        </section>

        <!-- Today's schedule -->
        <section class="today-card mb-4">
            <div class="today-head">
                <div>
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-calendar-day text-success me-2"></i>Today's Schedule</h5>
                    <div class="small text-muted"><?= date("l, F j, Y") ?> &middot; meetings in time order</div>
                </div>
                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle"><?= count($todaySchedule) ?> meeting<?= count($todaySchedule) === 1 ? "" : "s" ?></span>
            </div>
            <?php if (empty($todaySchedule)): ?>
            <div class="p-4 text-center text-muted">No meetings scheduled for today.</div>
            <?php else: ?>
                <?php foreach ($todaySchedule as $a): $m = $STATUS_META[$a["status"]]; ?>
                <div class="today-item" onclick="openApp(<?= (int) $a["id"] ?>)">
                    <div class="today-time"><?= date("g:i A", strtotime($a["meetup_scheduled_at"])) ?></div>
                    <div class="today-who">
                        <div class="b"><?= gjc_e($a["business_name"]) ?></div>
                        <div class="p"><?= gjc_e($a["proprietor_name"]) ?> &middot; <?= gjc_e($a["contact_number"]) ?></div>
                    </div>
                    <span class="badge bg-<?= $m["badge"] ?>"><i class="fa-solid <?= $m["icon"] ?> me-1"></i><?= $m["label"] ?></span>
                    <button type="button" class="btn btn-sm btn-outline-success ms-2">Open</button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Toolbar: search + status filter -->
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <div class="input-group input-group-sm" style="max-width:320px">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="search" id="appSearch" class="form-control" placeholder="Search business, proprietor, or email&hellip;">
            </div>
            <div class="btn-group btn-group-sm ms-auto flex-wrap" role="group" id="statusFilter">
                <button type="button" class="btn btn-outline-secondary filter-btn active" data-status="all">All (<?= count($apps) ?>)</button>
                <button type="button" class="btn btn-outline-secondary filter-btn" data-status="pending_verification">Pending (<?= $statusCounts["pending_verification"] ?>)</button>
                <button type="button" class="btn btn-outline-secondary filter-btn" data-status="awarded">Awarded (<?= $statusCounts["awarded"] ?>)</button>
                <button type="button" class="btn btn-outline-secondary filter-btn" data-status="rejected">Rejected (<?= $statusCounts["rejected"] ?>)</button>
                <button type="button" class="btn btn-outline-secondary filter-btn" data-status="cancelled">Cancelled (<?= $statusCounts["cancelled"] ?>)</button>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Business</th>
                            <th>Proprietor</th>
                            <th>Contact</th>
                            <th>Meeting Schedule</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="appTableBody">
                        <?php if (empty($apps)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No applications yet.</td></tr>
                        <?php else: foreach ($apps as $app): $m = $STATUS_META[$app["status"]]; ?>
                        <tr class="app-row" data-app-id="<?= (int) $app["id"] ?>"
                            data-status="<?= $app["status"] ?>"
                            data-search="<?= htmlspecialchars(strtolower($app["business_name"] . " " . $app["proprietor_name"] . " " . $app["email"])) ?>"
                            style="cursor:pointer" onclick="openApp(<?= (int) $app["id"] ?>)">
                            <td class="fw-semibold"><?= gjc_e($app["business_name"]) ?></td>
                            <td><?= gjc_e($app["proprietor_name"]) ?></td>
                            <td class="small text-muted"><?= gjc_e($app["contact_number"]) ?><br><?= gjc_e($app["email"]) ?></td>
                            <td class="sa-meeting"><?= sa_meeting_label($app["meetup_scheduled_at"]) ?></td>
                            <td><span class="badge bg-<?= $m["badge"] ?> status-badge"><i class="fa-solid <?= $m["icon"] ?> me-1"></i><?= $m["label"] ?></span></td>
                            <td class="small text-muted"><?= date("M j, Y", strtotime($app["created_at"])) ?></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-success">Open</button></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Application / Meeting workspace modal -->
<div class="modal fade" id="appModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appModalTitle">Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="appModalBody"></div>
        </div>
    </div>
</div>

<!-- Reason modal (reject / cancel) -->
<div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reasonTitle">Reason</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small" id="reasonHelp"></p>
                <textarea id="reasonText" class="form-control" rows="3" placeholder="Enter the reason..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                <button type="button" class="btn btn-danger" id="reasonConfirm">Confirm</button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastWrap"></div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
const APPS = <?= json_encode(array_values($apps), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const VACANT_STALLS = <?= json_encode($vacantStalls, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const STATUS_META = <?= json_encode($STATUS_META) ?>;
const DOC_URL = '<?= ADMIN_URL ?>/doc?f=';
const API_URL = '<?= ADMIN_URL ?>/api/stall_applications';

let appModal, reasonModal, reasonAction = null, reasonAppId = null;
document.addEventListener('DOMContentLoaded', () => {
    appModal = new bootstrap.Modal(document.getElementById('appModal'));
    reasonModal = new bootstrap.Modal(document.getElementById('reasonModal'));
});

function esc(s) { return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function peso(v) { return '₱' + parseFloat(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }); }
function findApp(id) { return APPS.find(a => a.id == id); }
function fmtDateTime(v) {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? v : d.toLocaleString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'numeric', minute:'2-digit' });
}
function fmtDate(v) {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? v : d.toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
}
function toast(msg, ok = true) {
    const wrap = document.getElementById('toastWrap');
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${ok ? 'success' : 'danger'} border-0`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${esc(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    wrap.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 4500 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}
function post(data, isForm = false) {
    const body = isForm ? data : (() => { const fd = new FormData(); Object.entries(data).forEach(([k, v]) => fd.append(k, v)); return fd; })();
    return fetch(API_URL, { method: 'POST', body }).then(async r => {
        const t = await r.text();
        try { return JSON.parse(t); } catch (e) { console.error('Bad API response:', t); return { success: false, message: 'The server returned an invalid response.' }; }
    });
}

// ── Document grid (side-by-side preview) ──
function docGrid(app) {
    const docs = {
        'Profile Picture': app.profile_picture,
        'Business Permit': app.business_permit,
        'Sanitary Permit': app.sanitary_permit,
        'GJC Requirements': app.gjc_requirements,
        'Clearance': app.clearance,
    };
    const cells = Object.entries(docs).map(([label, path]) => {
        if (!path || path === 'pending_path') return '';
        const url = DOC_URL + encodeURIComponent(String(path).replace(/^\//, ''));
        return `<div class="sa-doc">
            <div class="cap"><span>${esc(label)}</span><a href="${url}" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i></a></div>
            <iframe src="${url}" loading="lazy"></iframe>
        </div>`;
    }).join('');
    return `<div class="sa-doc-grid">${cells}</div>`;
}

// ── Modal body per status ──
function openApp(id) {
    const app = findApp(id);
    if (!app) return;
    const m = STATUS_META[app.status];
    document.getElementById('appModalTitle').innerHTML =
        `${esc(app.business_name)} <span class="badge bg-${m.badge} ms-2"><i class="fa-solid ${m.icon} me-1"></i>${m.label}</span>`;
    document.getElementById('appModalBody').innerHTML =
        app.status === 'pending_verification' ? renderWorkspace(app) : renderSummary(app);
    if (app.status === 'pending_verification') wireWorkspace(app);
    appModal.show();
}

function applicantHeader(app) {
    return `<div class="d-flex flex-wrap gap-4 mb-3 pb-3 border-bottom">
        <div><div class="small text-muted">Proprietor</div><div class="fw-semibold">${esc(app.proprietor_name)}</div></div>
        <div><div class="small text-muted">Contact</div><div class="fw-semibold">${esc(app.contact_number)}</div></div>
        <div><div class="small text-muted">Email</div><div class="fw-semibold">${esc(app.email)}</div></div>
        <div><div class="small text-muted">Meeting</div><div class="fw-semibold">${fmtDateTime(app.meetup_scheduled_at)}</div></div>
        ${app.preferred_stall_id ? `<div><div class="small text-muted">Preferred Stall</div><div class="fw-semibold">${esc(app.preferred_stall_id)}</div></div>` : ''}
    </div>`;
}

function renderWorkspace(app) {
    const hasContract = !!app.contract_file;
    const payDone = parseFloat(app.deposit_amount) > 0 && parseFloat(app.advance_amount) > 0 && app.rental_start_date && (app.payment_schedule_day == 15 || app.payment_schedule_day == 30);
    const contractUrl = hasContract ? DOC_URL + encodeURIComponent(String(app.contract_file).replace(/^\//, '')) : '';

    const stallOpts = VACANT_STALLS.map(s =>
        `<option value="${esc(s.stall_id)}" data-rate="${s.monthly_rate}" ${s.stall_id === app.preferred_stall_id ? 'selected' : ''}>${esc(s.stall_id)} — ${esc(s.label)} (${peso(s.monthly_rate)}/mo)</option>`).join('');

    return `${applicantHeader(app)}
    <div class="row g-3">
        <div class="col-12">
            <div class="sa-section">
                <h6><i class="fa-solid fa-folder-open text-success me-2"></i>Submitted Documents <span class="small text-muted fw-normal">— compare against the originals the applicant brought</span></h6>
                ${docGrid(app)}
            </div>
        </div>

        <div class="col-lg-6">
            <div class="sa-section h-100">
                <h6><i class="fa-solid fa-file-signature text-success me-2"></i>Signed Contract ${hasContract ? '<span class="sa-done-pill ms-1">Uploaded</span>' : ''}</h6>
                ${hasContract ? `<p class="small mb-2"><a href="${contractUrl}" target="_blank" rel="noopener"><i class="fa-solid fa-file-arrow-down me-1"></i>View uploaded contract</a></p>` : '<p class="small text-muted">Upload the scanned contract after the applicant signs it.</p>'}
                <input type="file" class="form-control form-control-sm mb-2" id="contractFile-${app.id}" accept=".pdf,.jpg,.jpeg,.png">
                <button type="button" class="btn btn-sm btn-success" id="contractBtn-${app.id}"><i class="fa-solid fa-upload me-1"></i>${hasContract ? 'Replace Contract' : 'Upload Contract'}</button>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="sa-section h-100">
                <h6><i class="fa-solid fa-money-check-dollar text-success me-2"></i>Payment ${payDone ? '<span class="sa-done-pill ms-1">Recorded</span>' : ''}</h6>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small mb-1">2-Month Deposit</label>
                        <div class="input-group input-group-sm"><span class="input-group-text">₱</span>
                            <input type="number" min="0" step="0.01" class="form-control" id="dep-${app.id}" value="${app.deposit_amount ?? ''}"></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">1-Month Advance</label>
                        <div class="input-group input-group-sm"><span class="input-group-text">₱</span>
                            <input type="number" min="0" step="0.01" class="form-control" id="adv-${app.id}" value="${app.advance_amount ?? ''}"></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">Rental Start Date</label>
                        <input type="date" class="form-control form-control-sm" id="start-${app.id}" value="${app.rental_start_date ?? ''}">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">Recurring Payment</label>
                        <select class="form-select form-select-sm" id="sched-${app.id}">
                            <option value="">Choose…</option>
                            <option value="15" ${app.payment_schedule_day == 15 ? 'selected' : ''}>Every 15th</option>
                            <option value="30" ${app.payment_schedule_day == 30 ? 'selected' : ''}>Every 30th</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-sm btn-success mt-1" id="payBtn-${app.id}"><i class="fa-solid fa-floppy-disk me-1"></i>Save Payment</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="sa-section">
                <h6><i class="fa-solid fa-store text-success me-2"></i>Assign Stall &amp; Finalize</h6>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Stall <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="awardStall-${app.id}" ${VACANT_STALLS.length ? '' : 'disabled'}>
                            ${stallOpts || '<option value="">No vacant stalls available</option>'}
                        </select>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="askReason('reject', ${app.id})"><i class="fa-solid fa-circle-xmark me-1"></i>Reject</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="askReason('cancel', ${app.id})"><i class="fa-solid fa-user-slash me-1"></i>No-show / Cancel</button>
                        <button type="button" class="btn btn-success btn-sm" id="awardBtn-${app.id}"><i class="fa-solid fa-award me-1"></i>Award</button>
                    </div>
                </div>
                <div class="small text-muted mt-2"><i class="fa-solid fa-circle-info me-1"></i>Award requires the signed contract uploaded and payment recorded.</div>
            </div>
        </div>
    </div>`;
}

function renderSummary(app) {
    const contractUrl = app.contract_file ? DOC_URL + encodeURIComponent(String(app.contract_file).replace(/^\//, '')) : '';
    let outcome = '';
    if (app.status === 'awarded') {
        outcome = `<div class="alert alert-success"><i class="fa-solid fa-circle-check me-1"></i>Awarded <strong>Stall ${esc(app.stall_id)}</strong> on ${fmtDateTime(app.awarded_at)}.</div>
        <div class="row g-3">
            <div class="col-md-6"><div class="sa-section h-100"><h6>Payment</h6>
                <div class="d-flex justify-content-between"><span class="text-muted">2-Month Deposit</span><span class="fw-semibold">${peso(app.deposit_amount)}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">1-Month Advance</span><span class="fw-semibold">${peso(app.advance_amount)}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Rental Start</span><span class="fw-semibold">${fmtDate(app.rental_start_date)}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Recurring</span><span class="fw-semibold">Every ${esc(app.payment_schedule_day)}th</span></div>
            </div></div>
            <div class="col-md-6"><div class="sa-section h-100"><h6>Contract</h6>
                ${contractUrl ? `<a href="${contractUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-file-arrow-down me-1"></i>View signed contract</a>` : '<span class="text-muted">No contract on file.</span>'}
                <div class="small text-muted mt-2">Merchant login: ${esc(app.email)}</div>
            </div></div>
        </div>`;
    } else if (app.status === 'rejected') {
        outcome = `<div class="alert alert-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Rejected on ${fmtDateTime(app.reviewed_at)}.</div>
            <div class="sa-section"><h6>Reason</h6><p class="mb-0">${esc(app.rejection_reason || '—')}</p></div>`;
    } else if (app.status === 'cancelled') {
        outcome = `<div class="alert alert-secondary"><i class="fa-solid fa-ban me-1"></i>Cancelled on ${fmtDateTime(app.cancelled_at)}. The meeting slot was released.</div>
            <div class="sa-section"><h6>Reason</h6><p class="mb-0">${esc(app.cancel_reason || '—')}</p></div>`;
    }
    return `${applicantHeader(app)}${outcome}
        <div class="sa-section mt-3"><h6>Submitted Documents</h6>${docGrid(app)}</div>`;
}

function wireWorkspace(app) {
    const id = app.id;
    // Prefill deposit/advance from the selected stall's monthly rate when empty.
    const stallSel = document.getElementById(`awardStall-${id}`);
    const dep = document.getElementById(`dep-${id}`);
    const adv = document.getElementById(`adv-${id}`);
    function suggest() {
        const opt = stallSel?.selectedOptions?.[0];
        const rate = opt ? parseFloat(opt.dataset.rate || 0) : 0;
        if (rate > 0) {
            if (!dep.value) dep.value = (rate * 2).toFixed(2);
            if (!adv.value) adv.value = rate.toFixed(2);
        }
    }
    if (stallSel) { stallSel.addEventListener('change', suggest); suggest(); }

    document.getElementById(`contractBtn-${id}`).addEventListener('click', () => {
        const input = document.getElementById(`contractFile-${id}`);
        if (!input.files.length) { toast('Choose the signed contract file first.', false); return; }
        const fd = new FormData();
        fd.append('action', 'upload_contract');
        fd.append('app_id', id);
        fd.append('contract', input.files[0]);
        post(fd, true).then(res => {
            toast(res.message, res.success);
            if (res.success) { app.contract_file = res.contract_file; openApp(id); }
        });
    });

    document.getElementById(`payBtn-${id}`).addEventListener('click', () => {
        post({
            action: 'record_payment', app_id: id,
            deposit_amount: dep.value, advance_amount: adv.value,
            rental_start_date: document.getElementById(`start-${id}`).value,
            payment_schedule_day: document.getElementById(`sched-${id}`).value,
        }).then(res => {
            toast(res.message, res.success);
            if (res.success) {
                app.deposit_amount = dep.value;
                app.advance_amount = adv.value;
                app.rental_start_date = document.getElementById(`start-${id}`).value;
                app.payment_schedule_day = document.getElementById(`sched-${id}`).value;
                openApp(id);
            }
        });
    });

    document.getElementById(`awardBtn-${id}`).addEventListener('click', () => {
        const stallId = document.getElementById(`awardStall-${id}`).value;
        if (!stallId) { toast('Select a stall to award.', false); return; }
        if (!app.contract_file) { toast('Upload the signed contract before awarding.', false); return; }
        const payDone = parseFloat(app.deposit_amount) > 0 && parseFloat(app.advance_amount) > 0 && app.rental_start_date && (app.payment_schedule_day == 15 || app.payment_schedule_day == 30);
        if (!payDone) { toast('Record the payment before awarding.', false); return; }
        if (!confirm(`Award Stall ${stallId} and create a merchant account for ${app.proprietor_name}? This cannot be undone.`)) return;
        post({ action: 'award', app_id: id, stall_id: stallId }).then(res => {
            toast(res.message, res.success);
            if (res.success) { appModal.hide(); setTimeout(() => location.reload(), 1200); }
        });
    });
}

// ── Reason modal (reject / cancel) ──
function askReason(action, id) {
    reasonAction = action; reasonAppId = id;
    document.getElementById('reasonTitle').textContent = action === 'reject' ? 'Reject Application' : 'No-show / Cancel Application';
    document.getElementById('reasonHelp').textContent = action === 'reject'
        ? 'The application will be terminated. The applicant must submit a brand-new application.'
        : 'The slot will be released and the application cancelled. The applicant must re-apply. (Reason optional for a no-show.)';
    document.getElementById('reasonText').value = '';
    document.getElementById('reasonConfirm').className = 'btn ' + (action === 'reject' ? 'btn-danger' : 'btn-secondary');
    appModal.hide();
    reasonModal.show();
}
document.getElementById('reasonConfirm').addEventListener('click', () => {
    const reason = document.getElementById('reasonText').value.trim();
    if (reasonAction === 'reject' && !reason) { toast('A rejection reason is required.', false); return; }
    post({ action: reasonAction, app_id: reasonAppId, reason }).then(res => {
        toast(res.message, res.success);
        if (res.success) { reasonModal.hide(); setTimeout(() => location.reload(), 1200); }
    });
});

// ── Search + status filter ──
let statusFilter = 'all';
function applyFilters() {
    const q = document.getElementById('appSearch').value.trim().toLowerCase();
    document.querySelectorAll('.app-row').forEach(row => {
        const okSearch = row.dataset.search.includes(q);
        const okStatus = statusFilter === 'all' || row.dataset.status === statusFilter;
        row.style.display = (okSearch && okStatus) ? '' : 'none';
    });
}
document.getElementById('appSearch').addEventListener('input', applyFilters);
document.querySelectorAll('.filter-btn').forEach(btn => btn.addEventListener('click', function () {
    statusFilter = this.dataset.status;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.toggle('active', b === this));
    applyFilters();
}));
</script>
</body>
</html>
