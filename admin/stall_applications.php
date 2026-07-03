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
require_once __DIR__ . "/../connection/config.php";
require_once __DIR__ . "/../connection/pdo.php";
require_once __DIR__ . "/../connection/app.php";
require_once __DIR__ . "/../connection/StallManager.php";

gjc_require_role(["finance"]);
gjc_ensure_stall_application_workflow_schema($db);
gjc_ensure_archived_rejections_schema($db);
gjc_ensure_meeting_scheduling_schema($db);
$currentUser = gjc_current_user($db);
$currentPage = "stall_applications";
$adminId = gjc_user_id();

// Only submitted, not-yet-awarded applications - awarded ones are merchant
// accounts now and live under Users, not here.
$apps = $db
    ->query(
        "SELECT sa.*, s.label AS stall_label
     FROM stall_applications sa
     LEFT JOIN stalls s ON s.stall_id = sa.stall_id
     WHERE sa.status NOT IN ('active', 'expired')
     ORDER BY sa.current_step ASC, sa.created_at ASC",
    )
    ->fetchAll(PDO::FETCH_ASSOC);

// Vacant stalls available for assignment at Step 4
$stallMgr = new StallManager($db);
$vacantStalls = array_values(
    array_filter($stallMgr->allStalls(), fn($s) => $s["status"] === "vacant"),
);

$archivedCount = (int) $db
    ->query("SELECT COUNT(*) FROM archived_rejections WHERE reactivated = 0")
    ->fetchColumn();

const STEP_LABELS = [
    1 => "Review Requirements",
    2 => "Meeting",
    3 => "Down Payment",
    4 => "Approval / Award",
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
    <title>Stall Applications | GenPay Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/stall_applications.css?v=1">
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/partials/sidebar_admin.php"; ?>

    <main class="admin-main">
        <header class="topbar">
            <button class="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h1>Stall Applications</h1>
                <p>Manage incoming vendor applications.</p>
            </div>

            <div class="admin-user">
                <span><?= gjc_e($currentUser["name"]) ?></span>
                <div class="avatar"><i class="fa-solid fa-user-tie"></i></div>
            </div>
        </header>

        <!-- Search, sort, and archived rejections - kept out of the topbar
             so it stays consistent with every other admin page. -->
        <div class="app-toolbar">
            <div class="input-group input-group-sm app-toolbar-search">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="search" id="appSearch" class="form-control" placeholder="Search business, proprietor, or email&hellip;">
            </div>

            <div class="input-group input-group-sm app-toolbar-sort">
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

            <button type="button" class="btn btn-outline-secondary btn-sm app-toolbar-archived" data-bs-toggle="modal" data-bs-target="#archivedModal" onclick="loadArchived()">
                Archived Rejections <span class="badge bg-danger ms-1"><?= $archivedCount ?></span>
            </button>
        </div>

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
                    <span class="sfc-count"><?= count(
                        array_filter(
                            $apps,
                            fn($a) => (int) $a["current_step"] === $stepNum,
                        ),
                    ) ?></span>
                    <span class="sfc-label"><?= htmlspecialchars(
                        $stepLabel,
                    ) ?></span>
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
                            <th>Date Submitted</th>
                        </tr>
                    </thead>
                    <tbody id="appTableBody">
                        <?php foreach ($apps as $app): ?>
                        <tr class="app-row" data-app-id="<?= (int) $app[
                            "id"
                        ] ?>"
                            data-step="<?= (int) $app["current_step"] ?>"
                            data-search="<?= htmlspecialchars(
                                strtolower(
                                    $app["business_name"] .
                                        " " .
                                        $app["proprietor_name"] .
                                        " " .
                                        $app["email"],
                                ),
                            ) ?>"
                            data-bs-toggle="collapse" data-bs-target="#detail-<?= (int) $app[
                                "id"
                            ] ?>" aria-expanded="false">
                            <td><span class="chevron"><i class="fa-solid fa-chevron-right"></i></span></td>
                            <td class="fw-semibold"><?= htmlspecialchars(
                                $app["business_name"],
                            ) ?></td>
                            <td><?= htmlspecialchars(
                                $app["proprietor_name"],
                            ) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars(
                                $app["contact_number"],
                            ) ?><br><?= htmlspecialchars($app["email"]) ?></td>
                            <td><span class="badge bg-danger step-badge">Step <?= (int) $app[
                                "current_step"
                            ] ?> &middot; <?= STEP_LABELS[
     (int) $app["current_step"]
 ] ?></span></td>
                            <td class="small text-muted"><?= date(
                                "M j, Y",
                                strtotime($app["created_at"]),
                            ) ?></td>
                        </tr>
                        <tr class="collapse app-detail-row" id="detail-<?= (int) $app[
                            "id"
                        ] ?>" data-app-id="<?= (int) $app["id"] ?>">
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

<!-- Award Stall Confirm Modal -->
<div class="modal fade" id="awardConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Stall Award</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">You are about to award <strong id="awardStallLabel"></strong> and create a merchant account for:</p>
                <p class="fw-semibold mb-0" id="awardApplicantLabel"></p>
                <p class="small text-muted" id="awardBusinessLabel"></p>
                <p class="small text-muted mt-2 mb-0">Login credentials will be emailed to the applicant. This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="awardConfirmBtn">Approve &amp; Award</button>
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
const APPS = <?= json_encode(
    array_values($apps),
    JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_INVALID_UTF8_SUBSTITUTE,
) ?>;
const VACANT_STALLS = <?= json_encode(
    $vacantStalls,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>;
const MEETING_TIME_SLOTS = <?= json_encode(gjc_meeting_time_slots()) ?>;
const STEP_LABELS = <?= json_encode(STEP_LABELS) ?>;
let DP_DEFAULT_AMOUNT = <?= (float) gjc_down_payment_default_amount($db) ?>;
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
        const savedDate  = app.meetup_scheduled_at ? String(app.meetup_scheduled_at).slice(0, 10) : '';
        const savedLoc   = esc(app.meetup_location  || '');
        const savedNotes = esc(app.meetup_notes     || '');
        return `
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" class="form-control" id="meetDate-${app.id}" min="${todayStr()}" value="${savedDate}" onchange="refreshMeetingSlots(${app.id})">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Time</label>
                    <select class="form-select" id="meetTime-${app.id}" disabled>
                        <option value="">${savedDate ? 'Loading…' : 'Pick a date first'}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Location</label>
                    <input type="text" class="form-control" id="meetLoc-${app.id}" placeholder="e.g. GJC Finance Office" value="${savedLoc}">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Notes</label>
                    <textarea class="form-control" id="meetNotes-${app.id}" rows="2" placeholder="Optional instructions for the applicant">${savedNotes}</textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success" onclick="saveMeeting(${app.id})">Accept &amp; Next</button>
                <button type="button" class="btn btn-outline-danger" onclick="openDecline(${app.id})">Decline</button>
            </div>`;
    }

    if (app.status === 'down_payment') {
        const dpDefault = DP_DEFAULT_AMOUNT > 0 ? DP_DEFAULT_AMOUNT.toFixed(2) : '';
        return `
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Amount</label>
                    <div class="input-group">
                        <input type="number" min="0" step="0.01" class="form-control" id="dpAmount-${app.id}" placeholder="0.00" value="${esc(dpDefault)}">
                        <button type="button" class="btn btn-outline-secondary btn-sm" title="Set as default for future applications" onclick="setDpDefault(${app.id})"><i class="fa-solid fa-bookmark"></i></button>
                    </div>
                    ${DP_DEFAULT_AMOUNT > 0 ? `<div class="form-text">Default: &#8369;${parseFloat(DP_DEFAULT_AMOUNT).toLocaleString('en-PH', {minimumFractionDigits:2})}</div>` : ''}
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
        const preferred = (app.preferred_stall_id || '').trim();
        const preferredVacant = preferred && VACANT_STALLS.some(s => s.stall_id === preferred);
        const options = VACANT_STALLS.map(s => {
            const sel = s.stall_id === preferred ? ' selected' : '';
            return `<option value="${esc(s.stall_id)}"${sel}>${esc(s.stall_id)} - ${esc(s.label)} (${money(s.monthly_rate)}/mo)</option>`;
        }).join('');
        const disabled = VACANT_STALLS.length === 0 ? 'disabled' : '';

        let preferredNote = '';
        if (preferred) {
            if (preferredVacant) {
                preferredNote = `<div class="small text-success mt-1"><i class="fa-solid fa-circle-check me-1"></i>Applicant's preferred stall <strong>${esc(preferred)}</strong> is available and pre-selected.</div>`;
            } else {
                preferredNote = `<div class="small text-warning mt-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Applicant preferred <strong>${esc(preferred)}</strong> but it is no longer vacant. Please assign a different stall.</div>`;
            }
        }

        return `
            <div class="mb-3" style="max-width:420px">
                <label class="form-label small fw-semibold">Assign Stall <span class="text-danger">*</span></label>
                <select class="form-select" id="awardStall-${app.id}" ${disabled}>
                    ${options || '<option value="">No vacant stalls available</option>'}
                </select>
                ${preferredNote}
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

    // If the meeting form has a pre-saved date, load booked slots immediately
    // so the time dropdown is ready when the admin opens the row.
    if (app.status === 'meeting' && app.meetup_scheduled_at) {
        const savedTime = String(app.meetup_scheduled_at).slice(11, 16);
        refreshMeetingSlots(app.id, savedTime);
    }

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
        if (res.success) {
            const patch = { status: res.status, current_step: res.current_step };
            if (res.proposed_slot) {
                // Mirror the DB-persisted proposal into the in-memory app so
                // renderPanel pre-fills correctly and survives re-renders.
                patch.meetup_scheduled_at = res.proposed_slot.date + ' ' + res.proposed_slot.time + ':00';
                patch.meetup_location     = res.proposed_slot.location;
            }
            mergeAndRender(id, patch);
        }
    });
}

function todayStr() {
    return new Date().toISOString().slice(0, 10);
}

function slotLabel(slot) {
    const [h, m] = slot.split(':').map(Number);
    const period = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 === 0 ? 12 : h % 12;
    return `${h12}:${String(m).padStart(2, '0')} ${period}`;
}

// Refetch which fixed slots are already booked on the chosen date, then
// rebuild the dropdown so a taken slot can't be picked twice.
function refreshMeetingSlots(id, preselectTime = null) {
    const dateInput = document.getElementById(`meetDate-${id}`);
    const select = document.getElementById(`meetTime-${id}`);
    const date = dateInput.value;

    if (!date) {
        select.disabled = true;
        select.innerHTML = '<option value="">Pick a date first</option>';
        return;
    }

    select.disabled = true;
    select.innerHTML = '<option value="">Loading...</option>';

    post({ action: 'get_booked_slots', meetup_date: date }).then(res => {
        if (!res.success) {
            select.innerHTML = '<option value="">Failed to load slots</option>';
            return;
        }
        const booked = new Set(res.booked || []);
        const options = (res.slots || MEETING_TIME_SLOTS).map(slot => {
            const taken = booked.has(slot);
            return `<option value="${slot}" ${taken ? 'disabled' : ''}>${slotLabel(slot)}${taken ? ' - Already booked' : ''}</option>`;
        }).join('');
        select.innerHTML = `<option value="">Select a time</option>${options}`;
        select.disabled = false;
        if (preselectTime) select.value = preselectTime;
    });
}

// ── Down payment preview / confirm before sending ──
function saveMeeting(id) {
    const date = document.getElementById(`meetDate-${id}`).value;
    const time = document.getElementById(`meetTime-${id}`).value;
    const loc  = document.getElementById(`meetLoc-${id}`).value.trim();
    const notes = document.getElementById(`meetNotes-${id}`).value.trim();
    if (!date || !time || !loc) { toast('Please enter the meeting date, time, and location.', 'error'); return; }
    post({ action: 'save_meeting', app_id: id, meetup_date: date, meetup_time: time, meetup_location: loc, meetup_notes: notes })
        .then(res => {
            toast(res.message, res.success ? 'success' : 'error');
            if (res.success) {
                mergeAndRender(id, { status: res.status, current_step: res.current_step });
            } else if (/just booked/i.test(res.message)) {
                refreshMeetingSlots(id);
            }
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

let awardTargetId = null;

function awardStall(id) {
    const stallId = document.getElementById(`awardStall-${id}`).value;
    if (!stallId) { toast('Please select a stall to award.', 'error'); return; }
    const app = findApp(id);
    awardTargetId = id;
    document.getElementById('awardStallLabel').textContent = `Stall ${stallId}`;
    document.getElementById('awardApplicantLabel').textContent = app.proprietor_name;
    document.getElementById('awardBusinessLabel').textContent = app.business_name;
    new bootstrap.Modal(document.getElementById('awardConfirmModal')).show();
}

document.getElementById('awardConfirmBtn').addEventListener('click', function () {
    const id = awardTargetId;
    const stallId = document.getElementById(`awardStall-${id}`).value;
    bootstrap.Modal.getInstance(document.getElementById('awardConfirmModal')).hide();
    post({ action: 'award_stall', app_id: id, stall_id: stallId }).then(res => {
        toast(res.message, res.success ? 'success' : 'error');
        if (res.success) mergeAndRender(id, { status: res.status, current_step: res.current_step, stall_id: stallId });
    });
});

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

// ── Down payment default amount (inline, set from the Step 3 panel) ──
function setDpDefault(id) {
    const amount = parseFloat(document.getElementById(`dpAmount-${id}`).value) || 0;
    if (amount <= 0) { toast('Enter an amount first to set as default.', 'error'); return; }
    post({ action: 'save_down_payment_settings', default_amount: amount }).then(res => {
        toast(res.message, res.success ? 'success' : 'error');
        if (res.success) {
            DP_DEFAULT_AMOUNT = amount;
            // Re-render only this row's panel so the "Default:" hint updates.
            const app = findApp(id);
            if (app) renderRow(app);
        }
    });
}

// ── Global search + step filter (combined) ──
let currentStepFilter = '0';

function applyFilters() {
    const q = document.getElementById('appSearch').value.trim().toLowerCase();
    document.querySelectorAll('.app-row').forEach(row => {
        const matchesSearch = row.dataset.search.includes(q);
        const matchesStep = currentStepFilter === '0' || row.dataset.step === currentStepFilter;
        const visible = matchesSearch && matchesStep;

        // A row being filtered out should collapse its expanded detail row too -
        // otherwise switching tabs/search leaves an orphaned open detail behind.
        if (!visible && row.getAttribute('aria-expanded') === 'true') {
            row.setAttribute('aria-expanded', 'false');
            const detail = document.getElementById(`detail-${row.dataset.appId}`);
            if (detail) {
                detail.classList.remove('show');
            }
        }

        row.style.display = visible ? '' : 'none';
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
