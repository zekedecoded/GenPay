<?php
// ============================================================
//  admin/stall_applications.php
//  Unified 4-step stall application pipeline (Bootstrap 5 native).
//  Source requirement: adviser feedback session (SIR EMMAN 4.mp3)
//
//  Step 1: Review Requirements   - Accept / Decline
//  Step 2: Meeting                - Accept / Decline
//  Step 3: Down Payment          - Next (forward-only)
//  Step 4: Approval / Award      - Approve & Award (forward-only)
//
//  Only in-flight submissions are listed here - once an application is
//  awarded (status='active') it becomes a merchant account and is managed
//  from admin/users.php instead, not this tab.
//
//  Each "Next" action auto-saves via fetch to admin/api/stall_applications.php
//  and re-renders the affected row in place (no full page reload).
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/StallManager.php';

gjc_require_role(['finance']);
gjc_ensure_stall_application_workflow_schema($db);
gjc_ensure_archived_rejections_schema($db);
$currentUser = gjc_current_user($db);
$currentPage = 'stall_applications';
$adminId     = gjc_user_id();

// Only submitted, not-yet-awarded applications - awarded ones are merchant
// accounts now and live under Users, not here.
$apps = $db->query(
    "SELECT sa.*, s.label AS stall_label
     FROM stall_applications sa
     LEFT JOIN stalls s ON s.stall_id = sa.stall_id
     WHERE sa.status NOT IN ('active', 'expired')
     ORDER BY sa.current_step ASC, sa.created_at ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Vacant stalls available for assignment at Step 4
$stallMgr = new StallManager($db);
$vacantStalls = array_values(array_filter($stallMgr->allStalls(), fn ($s) => $s['status'] === 'vacant'));

$archivedCount = (int) $db->query("SELECT COUNT(*) FROM archived_rejections WHERE reactivated = 0")->fetchColumn();

const STEP_LABELS = [
    1 => 'Review Requirements',
    2 => 'Meeting',
    3 => 'Down Payment',
    4 => 'Approval / Award',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stall Applications | GenPay Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=3">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* Page-specific sizing not covered by a Bootstrap 5 utility class. */
        .step-circle { width: 2.25rem; height: 2.25rem; line-height: 2.25rem; flex-shrink: 0; }
        .step-track { height: 4px; }
        #docFrame { width: 100%; height: 70vh; border: 0; }
        .app-row { cursor: pointer; }
        .app-row .chevron { display: inline-block; transition: transform .15s ease; }
        .app-row[aria-expanded="true"] .chevron { transform: rotate(90deg); }
        .app-detail-row > td { padding: 0; border-top: 0; }
        .app-detail-inner { padding: 1.25rem 1.5rem; }

        /* Step filter cards */
        .step-filter-card {
            width: 100%;
            border: 1px solid rgba(6, 68, 32, 0.12);
            background: #fff;
            border-radius: 16px;
            padding: 14px 18px;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 2px;
            cursor: pointer;
            transition: 0.18s ease;
        }
        .step-filter-card:hover {
            border-color: var(--emerald-600);
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(6, 68, 32, 0.08);
        }
        .step-filter-card.active {
            border-color: var(--emerald-700);
            background: var(--emerald-soft);
            box-shadow: 0 10px 24px rgba(6, 68, 32, 0.12);
        }
        .step-filter-card .sfc-count {
            font-size: 24px;
            font-weight: 800;
            color: var(--emerald-900);
            line-height: 1.1;
        }
        .step-filter-card.active .sfc-count { color: var(--emerald-800); }
        .step-filter-card .sfc-label {
            font-size: 12px;
            font-weight: 750;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .step-filter-card.active .sfc-label { color: var(--emerald-800); }
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
                <p>Submitted applications awaiting review &mdash; Review &rarr; Meeting &rarr; Down Payment &rarr; Approval.</p>
            </div>

            <!-- Global search (Bootstrap 5 input-group) -->
            <div class="input-group input-group-sm flex-grow-1 mx-3" style="max-width:360px">
                <span class="input-group-text bg-white">&#128269;</span>
                <input type="search" id="appSearch" class="form-control" placeholder="Search business, proprietor, or email&hellip;">
            </div>

            <!-- Sort control -->
            <div class="input-group input-group-sm me-3" style="max-width:230px">
                <label class="input-group-text bg-white" for="appSort">Sort</label>
                <select id="appSort" class="form-select">
                    <option value="submitted_desc">Date Submitted (Newest)</option>
                    <option value="submitted_asc">Date Submitted (Oldest)</option>
                    <option value="business_asc">Business Name (A&ndash;Z)</option>
                    <option value="business_desc">Business Name (Z&ndash;A)</option>
                    <option value="proprietor_asc">Proprietor (A&ndash;Z)</option>
                    <option value="proprietor_desc">Proprietor (Z&ndash;A)</option>
                </select>
            </div>

            <button type="button" class="btn btn-outline-secondary btn-sm me-3" data-bs-toggle="modal" data-bs-target="#archivedModal" onclick="loadArchived()">
                Archived Rejections <span class="badge bg-danger ms-1"><?= $archivedCount ?></span>
            </button>

            <div class="admin-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="avatar"><img src="<?= ICONS_URL ?>/admin.png" alt="Admin"></div>
            </div>
        </header>

        <!-- Filter by pipeline step (cards) -->
        <section class="row g-3 mb-3" id="stepFilterCards">
            <div class="col-6 col-md">
                <button type="button" class="step-filter-card active" data-step="0">
                    <span class="sfc-count"><?= count($apps) ?></span>
                    <span class="sfc-label">All Applications</span>
                </button>
            </div>
            <?php foreach (STEP_LABELS as $stepNum => $stepLabel): ?>
            <div class="col-6 col-md">
                <button type="button" class="step-filter-card" data-step="<?= $stepNum ?>">
                    <span class="sfc-count"><?= count(array_filter($apps, fn ($a) => (int) $a['current_step'] === $stepNum)) ?></span>
                    <span class="sfc-label"><?= htmlspecialchars($stepLabel) ?></span>
                </button>
            </div>
            <?php endforeach; ?>
        </section>

        <?php if (empty($apps)): ?>
        <div class="text-center text-muted py-5">
            <p class="fs-5 fw-semibold">No submitted applications awaiting review.</p>
        </div>
        <?php else: ?>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:2rem"></th>
                            <th>Business</th>
                            <th>Proprietor</th>
                            <th>Contact</th>
                            <th>Current Step</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody id="appTableBody">
                        <?php foreach ($apps as $app): ?>
                        <tr class="app-row" data-app-id="<?= (int) $app['id'] ?>"
                            data-step="<?= (int) $app['current_step'] ?>"
                            data-search="<?= htmlspecialchars(strtolower($app['business_name'] . ' ' . $app['proprietor_name'] . ' ' . $app['email'])) ?>"
                            data-bs-toggle="collapse" data-bs-target="#detail-<?= (int) $app['id'] ?>" aria-expanded="false">
                            <td><span class="chevron">&#9656;</span></td>
                            <td class="fw-semibold"><?= htmlspecialchars($app['business_name']) ?></td>
                            <td><?= htmlspecialchars($app['proprietor_name']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($app['contact_number']) ?><br><?= htmlspecialchars($app['email']) ?></td>
                            <td><span class="badge bg-danger step-badge">Step <?= (int) $app['current_step'] ?> &middot; <?= STEP_LABELS[(int) $app['current_step']] ?></span></td>
                            <td class="small text-muted"><?= date('M j, Y', strtotime($app['created_at'])) ?></td>
                        </tr>
                        <tr class="collapse app-detail-row" id="detail-<?= (int) $app['id'] ?>" data-app-id="<?= (int) $app['id'] ?>">
                            <td colspan="6">
                                <div class="app-detail-inner bg-light border-top">
                                    <div class="stepper mb-4"></div>
                                    <div class="step-panel"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Document Viewer Modal (Bootstrap 5 native, in-page preview via iframe) -->
<div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="docModalTitle">Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="docFrame" src="about:blank" title="Document preview"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Decline Modal (Bootstrap 5 native) -->
<div class="modal fade" id="declineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Decline Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">This application will be archived to <code>archived_rejections</code> and removed from the active pipeline.</p>
                <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                <textarea id="declineReason" class="form-control" rows="3" placeholder="Enter the reason for declining..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="declineConfirmBtn">Decline</button>
            </div>
        </div>
    </div>
</div>

<!-- Archived Rejections Modal -->
<div class="modal fade" id="archivedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Archived Rejections</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="archivedList" class="d-flex flex-column gap-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastWrap"></div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
const APPS = <?= json_encode(array_values($apps), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const VACANT_STALLS = <?= json_encode($vacantStalls, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const STEP_LABELS = <?= json_encode(STEP_LABELS) ?>;
const DOC_URL  = '<?= ADMIN_URL ?>/doc?f=';
const API_URL  = '<?= ADMIN_URL ?>/api/stall_applications';
const ARCHIVE_API_URL = '<?= ADMIN_URL ?>/api/archived_rejections';

let declineTargetId = null;

function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function money(v) {
    return '&#8369;' + parseFloat(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
}
function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'numeric', minute:'2-digit' });
}
function toast(msg, type = 'success') {
    const wrap = document.getElementById('toastWrap');
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type === 'success' ? 'success' : 'danger'} border-0`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${esc(msg)}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    wrap.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 4500 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}
function post(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    return fetch(API_URL, { method: 'POST', body: fd }).then(async r => {
        const text = await r.text();
        try { return JSON.parse(text); }
        catch (err) {
            console.error('Invalid API response:', text);
            return { success: false, message: 'The server returned an invalid response.' };
        }
    });
}

// ── Stepper (Bootstrap 5 utility-built stepper + progress track) ──
function renderStepper(app) {
    const step = app.current_step;
    let circles = '';
    for (let i = 1; i <= 4; i++) {
        let cls = 'bg-secondary';
        if (i < step) cls = 'bg-success';
        else if (i === step) cls = 'bg-danger';
        circles += `
            <div class="d-flex flex-column align-items-center" style="width:25%">
                <div class="step-circle rounded-circle ${cls} text-white d-flex align-items-center justify-content-center fw-bold">${i}</div>
                <div class="small text-center mt-1 ${i === step ? 'fw-bold text-danger' : 'text-muted'}">${STEP_LABELS[i]}</div>
            </div>`;
    }
    const pct = Math.round(((step - 1) / 4) * 100) + 12;
    return `
        <div class="d-flex justify-content-between">${circles}</div>
        <div class="progress step-track mt-2">
            <div class="progress-bar bg-danger" style="width:${pct}%"></div>
        </div>`;
}

// ── Document viewer (Bootstrap 5 modal + iframe, in-page preview) ──
function openDoc(path, label) {
    document.getElementById('docModalTitle').textContent = label;
    document.getElementById('docFrame').src = DOC_URL + encodeURIComponent(String(path).replace(/^\//, ''));
    new bootstrap.Modal(document.getElementById('docModal')).show();
}

function docButtons(app) {
    const docs = {
        'Profile Picture': app.profile_picture,
        'Business Permit': app.business_permit,
        'Sanitary Permit': app.sanitary_permit,
        'GJC Requirements': app.gjc_requirements,
        'Clearance': app.clearance,
    };
    return Object.entries(docs).map(([label, path]) => path
        ? `<button type="button" class="btn btn-outline-secondary btn-sm" onclick='openDoc(${JSON.stringify(path)}, ${JSON.stringify(label)})'>${esc(label)}</button>`
        : '').join(' ');
}

// ── Step content panel ──
function renderPanel(app) {
    if (app.status === 'review') {
        return `
            <div class="mb-3">
                <div class="fw-semibold small text-uppercase text-muted mb-2">Submitted Documents</div>
                <div class="d-flex flex-wrap gap-2">${docButtons(app)}</div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success" onclick="acceptReview(${app.id})">Accept</button>
                <button type="button" class="btn btn-outline-danger" onclick="openDecline(${app.id})">Decline</button>
            </div>`;
    }

    if (app.status === 'meeting') {
        return `
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" class="form-control" id="meetDate-${app.id}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Time</label>
                    <input type="time" class="form-control" id="meetTime-${app.id}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Location</label>
                    <input type="text" class="form-control" id="meetLoc-${app.id}" placeholder="e.g. GJC Finance Office">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Notes</label>
                    <textarea class="form-control" id="meetNotes-${app.id}" rows="2" placeholder="Optional instructions for the applicant"></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success" onclick="saveMeeting(${app.id})">Accept &amp; Next</button>
                <button type="button" class="btn btn-outline-danger" onclick="openDecline(${app.id})">Decline</button>
            </div>`;
    }

    if (app.status === 'down_payment') {
        return `
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Amount</label>
                    <input type="number" min="0" step="0.01" class="form-control" id="dpAmount-${app.id}" placeholder="0.00">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Reference</label>
                    <input type="text" class="form-control" id="dpRef-${app.id}" placeholder="Receipt / GCash ref">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Notes</label>
                    <input type="text" class="form-control" id="dpNotes-${app.id}" placeholder="Optional">
                </div>
            </div>
            <button type="button" class="btn btn-success" onclick="saveDownPayment(${app.id})">Next</button>`;
    }

    if (app.status === 'approval') {
        const options = VACANT_STALLS.map(s => `<option value="${esc(s.stall_id)}">${esc(s.stall_id)} - ${esc(s.label)} (${money(s.monthly_rate)}/mo)</option>`).join('');
        const disabled = VACANT_STALLS.length === 0 ? 'disabled' : '';
        return `
            <div class="mb-3" style="max-width:420px">
                <label class="form-label small fw-semibold">Assign Stall <span class="text-danger">*</span></label>
                <select class="form-select" id="awardStall-${app.id}" ${disabled}>
                    ${options || '<option value="">No vacant stalls available</option>'}
                </select>
            </div>
            <button type="button" class="btn btn-success" onclick="awardStall(${app.id})" ${disabled}>Approve &amp; Award</button>`;
    }

    return '';
}

function renderRow(app) {
    const detail = document.querySelector(`.app-detail-row[data-app-id="${app.id}"]`);
    if (!detail) return;
    detail.querySelector('.stepper').innerHTML = renderStepper(app);
    detail.querySelector('.step-panel').innerHTML = renderPanel(app);

    const row = document.querySelector(`.app-row[data-app-id="${app.id}"]`);
    if (row) {
        row.querySelector('.step-badge').textContent = `Step ${app.current_step} · ${STEP_LABELS[app.current_step]}`;
        row.dataset.step = app.current_step;
        applyFilters();
        updateStepCounts();
    }
}

function removeRow(id) {
    document.querySelector(`.app-row[data-app-id="${id}"]`)?.remove();
    document.querySelector(`.app-detail-row[data-app-id="${id}"]`)?.remove();
    updateStepCounts();
}

function findApp(id) { return APPS.find(a => a.id == id); }
function mergeAndRender(id, patch) {
    const app = findApp(id);
    Object.assign(app, patch);
    // Awarded applications become merchant accounts (see admin/users.php) and
    // are no longer "submitted applications" - drop them from this view.
    if (app.status === 'active') { removeRow(id); return; }
    renderRow(app);
}

// ── Step actions ──
function acceptReview(id) {
    post({ action: 'accept_review', app_id: id }).then(res => {
        toast(res.message, res.success ? 'success' : 'error');
        if (res.success) mergeAndRender(id, { status: res.status, current_step: res.current_step });
    });
}

function saveMeeting(id) {
    const date = document.getElementById(`meetDate-${id}`).value;
    const time = document.getElementById(`meetTime-${id}`).value;
    const loc  = document.getElementById(`meetLoc-${id}`).value.trim();
    const notes = document.getElementById(`meetNotes-${id}`).value.trim();
    if (!date || !time || !loc) { toast('Please enter the meeting date, time, and location.', 'error'); return; }
    post({ action: 'save_meeting', app_id: id, meetup_date: date, meetup_time: time, meetup_location: loc, meetup_notes: notes })
        .then(res => {
            toast(res.message, res.success ? 'success' : 'error');
            if (res.success) mergeAndRender(id, { status: res.status, current_step: res.current_step });
        });
}

function saveDownPayment(id) {
    const amount = document.getElementById(`dpAmount-${id}`).value;
    const ref = document.getElementById(`dpRef-${id}`).value.trim();
    const notes = document.getElementById(`dpNotes-${id}`).value.trim();
    if (!amount || Number(amount) <= 0) { toast('Please enter the down payment amount.', 'error'); return; }
    post({ action: 'save_down_payment', app_id: id, down_payment_amount: amount, down_payment_reference: ref, down_payment_notes: notes })
        .then(res => {
            toast(res.message, res.success ? 'success' : 'error');
            if (res.success) mergeAndRender(id, { status: res.status, current_step: res.current_step });
        });
}

function awardStall(id) {
    const stallId = document.getElementById(`awardStall-${id}`).value;
    if (!stallId) { toast('Please select a stall to award.', 'error'); return; }
    if (!confirm(`Award Stall ${stallId} and create the merchant account?`)) return;
    post({ action: 'award_stall', app_id: id, stall_id: stallId }).then(res => {
        toast(res.message, res.success ? 'success' : 'error');
        if (res.success) mergeAndRender(id, { status: res.status, current_step: res.current_step, stall_id: stallId });
    });
}

function openDecline(id) {
    declineTargetId = id;
    document.getElementById('declineReason').value = '';
    new bootstrap.Modal(document.getElementById('declineModal')).show();
}
document.getElementById('declineConfirmBtn').addEventListener('click', function () {
    const reason = document.getElementById('declineReason').value.trim();
    if (!reason) { toast('Please enter a decline reason.', 'error'); return; }
    post({ action: 'decline', app_id: declineTargetId, rejection_reason: reason }).then(res => {
        toast(res.message, res.success ? 'success' : 'error');
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('declineModal')).hide();
            removeRow(declineTargetId);
        }
    });
});

// ── Archived rejections list ──
function loadArchived() {
    const list = document.getElementById('archivedList');
    list.innerHTML = '<div class="text-muted small">Loading&hellip;</div>';
    const fd = new FormData(); fd.append('action', 'list');
    fetch(ARCHIVE_API_URL, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        if (!res.success || !res.rows.length) { list.innerHTML = '<div class="text-muted small">No archived rejections.</div>'; return; }
        list.innerHTML = res.rows.map(r => `
            <div class="border rounded p-3 d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold">${esc(r.business_name)} &middot; ${esc(r.proprietor_name)}</div>
                    <div class="small text-muted">${esc(r.email)} &middot; Declined at Step ${esc(r.rejected_at_step)} &middot; ${formatDateTime(r.rejected_at)}</div>
                    <div class="small mt-1"><strong>Reason:</strong> ${esc(r.rejection_reason)}</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="reactivate(${r.id})">Reactivate</button>
            </div>`).join('');
    });
}
function reactivate(id) {
    if (!confirm('Reactivate this application back to Step 1 - Review Requirements?')) return;
    const fd = new FormData(); fd.append('action', 'reactivate'); fd.append('id', id);
    fetch(ARCHIVE_API_URL, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        toast(res.message, res.success ? 'success' : 'error');
        if (res.success) { loadArchived(); setTimeout(() => location.reload(), 1200); }
    });
}

// ── Global search + step filter (combined) ──
let currentStepFilter = '0';

function applyFilters() {
    const q = document.getElementById('appSearch').value.trim().toLowerCase();
    document.querySelectorAll('.app-row').forEach(row => {
        const matchesSearch = row.dataset.search.includes(q);
        const matchesStep = currentStepFilter === '0' || row.dataset.step === currentStepFilter;
        row.style.display = (matchesSearch && matchesStep) ? '' : 'none';
    });
}
document.getElementById('appSearch').addEventListener('input', applyFilters);

// ── Step filter cards ──
function updateStepCounts() {
    const rows = document.querySelectorAll('.app-row');
    document.querySelectorAll('.step-filter-card').forEach(card => {
        const step = card.dataset.step;
        const count = step === '0'
            ? rows.length
            : Array.from(rows).filter(r => r.dataset.step === step).length;
        card.querySelector('.sfc-count').textContent = count;
    });
}
document.querySelectorAll('.step-filter-card').forEach(card => {
    card.addEventListener('click', function () {
        currentStepFilter = this.dataset.step;
        document.querySelectorAll('.step-filter-card').forEach(c => c.classList.toggle('active', c === this));
        applyFilters();
    });
});

// ── Sort control ──
const SORTERS = {
    submitted_desc:  (a, b) => b.created_at.localeCompare(a.created_at),
    submitted_asc:   (a, b) => a.created_at.localeCompare(b.created_at),
    business_asc:    (a, b) => a.business_name.localeCompare(b.business_name),
    business_desc:   (a, b) => b.business_name.localeCompare(a.business_name),
    proprietor_asc:  (a, b) => a.proprietor_name.localeCompare(b.proprietor_name),
    proprietor_desc: (a, b) => b.proprietor_name.localeCompare(a.proprietor_name),
};

function applySort() {
    const sorter = SORTERS[document.getElementById('appSort').value];
    if (!sorter) return;

    const tbody = document.getElementById('appTableBody');
    const rows = Array.from(tbody.querySelectorAll('.app-row'));
    const sorted = rows
        .map(row => ({ row, app: findApp(row.dataset.appId) }))
        .filter(entry => entry.app)
        .sort((a, b) => sorter(a.app, b.app));

    sorted.forEach(({ row }) => {
        const detail = tbody.querySelector(`.app-detail-row[data-app-id="${row.dataset.appId}"]`);
        tbody.appendChild(row);
        if (detail) tbody.appendChild(detail);
    });
}
document.getElementById('appSort').addEventListener('change', applySort);

// ── Initial render ──
APPS.forEach(renderRow);
</script>
</body>
</html>
