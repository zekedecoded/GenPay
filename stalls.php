<?php
require_once __DIR__ . "/connection/config.php";
require_once __DIR__ . "/connection/pdo.php";
require_once __DIR__ . "/connection/StallManager.php";

$stallMgr = new StallManager($db);
$stallMgr->flushExpiredPending(); // App-level expiry flush - runs before render
$stalls = $stallMgr->allStalls();

// Build a 5×2 grid: [rowLabel][colNumber] => stall
$grid = [];
foreach ($stalls as $s) {
    $grid[$s["row_label"]][$s["col_number"]] = $s;
}
ksort($grid);

// Status helpers
function statusClass(string $status): string
{
    return match ($status) {
        "occupied" => "stall--occupied",
        "pending_application" => "stall--pending",
        default => "stall--vacant",
    };
}
function statusLabel(string $status): string
{
    return match ($status) {
        "occupied" => "Occupied",
        "pending_application" => "Pending",
        default => "Vacant",
    };
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
    <title>Available Stalls | GenPay</title>
    <meta name="description" content="Browse available stalls at General de Jesus College. View real-time occupancy and apply online for a stall slot.">
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green-900: #052e16;
            --green-800: #064420;
            --green-700: #15803d;
            --green-500: #22c55e;
            --green-400: #4ade80;
            --green-100: #dcfce7;
            --gold:      #d4a017;
            --gold-light:#f6d860;
            --cream:     #fdfbf6;
            --red-500:   #ef4444;
            --red-100:   #fee2e2;
            --amber-500: #f59e0b;
            --amber-100: #fef3c7;
            --gray-100:  #f3f4f6;
            --gray-200:  #e5e7eb;
            --gray-600:  #4b5563;
            --gray-800:  #1f2937;
            --white:     #ffffff;
            --radius:    16px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.08);
            --shadow-md: 0 4px 20px rgba(0,0,0,.12);
            --shadow-lg: 0 20px 60px rgba(0,0,0,.18);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--cream);
            color: var(--gray-800);
            min-height: 100vh;
        }

        /* ── NAVBAR ── */
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 40px;
            background: rgba(253,251,246,.92);
            backdrop-filter: blur(14px);
            box-shadow: 0 2px 20px rgba(0,0,0,.06);
        }
        .navbar-brand {
            display: flex; align-items: center; gap: 10px;
            font-weight: 800; font-size: 20px; color: var(--green-800);
            text-decoration: none;
        }
        .navbar-brand img { width: 40px; height: 40px; object-fit: contain; }
        .navbar-links { display: flex; align-items: center; gap: 16px; }
        .btn-nav {
            padding: 9px 22px; border-radius: 50px; font-weight: 700;
            font-size: 14px; text-decoration: none; transition: all .2s;
        }
        .btn-nav--outline {
            border: 2px solid var(--green-800); color: var(--green-800);
            background: transparent;
        }
        .btn-nav--outline:hover { background: var(--green-800); color: #fff; }
        .btn-nav--solid {
            background: linear-gradient(135deg, var(--green-400), var(--green-500));
            color: var(--green-900); border: none;
            box-shadow: 0 4px 15px rgba(34,197,94,.3);
        }
        .btn-nav--solid:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(34,197,94,.4); }

        /* ── HERO ── */
        .hero {
            margin-top: 72px;
            padding: 64px 40px 48px;
            background: linear-gradient(135deg, var(--green-900) 0%, #0a5c2e 60%, #0d7a3e 100%);
            text-align: center; color: #fff; position: relative; overflow: hidden;
        }
        .hero::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse at 70% 50%, rgba(74,222,128,.12) 0%, transparent 60%);
        }
        .hero-tag {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
            border-radius: 50px; padding: 5px 16px; font-size: 13px;
            font-weight: 600; color: var(--green-400); margin-bottom: 18px;
            position: relative; z-index: 1;
        }
        .hero h1 { font-size: 42px; font-weight: 800; line-height: 1.15; margin-bottom: 14px; position: relative; z-index: 1; }
        .hero h1 span { color: var(--green-400); }
        .hero p { font-size: 16px; color: rgba(255,255,255,.78); max-width: 480px; margin: 0 auto 32px; position: relative; z-index: 1; }

        /* The one gold thing on the page: there is exactly one action here. */
        .btn-apply-now {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 16px 38px; border-radius: 50px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            color: var(--green-900); border: none;
            font-size: 16px; font-weight: 800; text-decoration: none;
            box-shadow: 0 8px 28px rgba(212,160,23,.4);
            transition: transform .2s, box-shadow .2s;
            position: relative; z-index: 1;
        }
        .btn-apply-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 34px rgba(212,160,23,.5);
            color: var(--green-900);
        }
        .btn-apply-now svg { width: 18px; height: 18px; flex-shrink: 0; }

        /* ── LEGEND ── */
        .legend {
            display: flex; justify-content: center; gap: 24px; flex-wrap: wrap;
            padding: 18px 40px;
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
        }
        .legend-item {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 600; color: var(--gray-600);
        }
        .legend-dot {
            width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
        }
        .legend-dot--vacant  { background: var(--green-500); }
        .legend-dot--occupied { background: var(--red-500); }
        .legend-dot--pending  { background: var(--amber-500); }

        /* ── MAIN CONTENT ── */
        .content {
            max-width: 1000px; margin: 0 auto; padding: 40px 24px 80px;
        }

        .section-title {
            font-size: 13px; font-weight: 700; color: var(--green-700);
            text-transform: uppercase; letter-spacing: .08em;
            margin-bottom: 16px;
        }

        /* ── STALL GRID ── */
        .stall-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }
        .stall-row-label {
            font-size: 11px; font-weight: 800; color: var(--gray-600);
            text-transform: uppercase; letter-spacing: .1em;
            margin-bottom: 8px; padding-left: 2px;
        }

        .stall {
            position: relative;
            border-radius: var(--radius);
            padding: 22px 16px 18px;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
            text-align: center;
            user-select: none;
            min-height: 130px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 8px;
            border: 2px solid transparent;
        }
        .stall:hover { transform: translateY(-4px); }

        .stall--vacant {
            background: linear-gradient(145deg, #f0fdf4, #dcfce7);
            border-color: #86efac;
            box-shadow: 0 4px 16px rgba(34,197,94,.15);
        }
        .stall--vacant:hover { box-shadow: 0 10px 30px rgba(34,197,94,.28); }

        .stall--occupied {
            background: linear-gradient(145deg, #fff1f2, #fee2e2);
            border-color: #fca5a5;
            box-shadow: 0 4px 16px rgba(239,68,68,.12);
            cursor: default;
        }

        /* Occupied cards with a merchant logo use it as the card's full
           background (set inline via style="background-image:...") with a
           soft dark veil so the centered ID circle and name stay legible
           over any logo's colors. */
        .stall--occupied.stall--has-logo {
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .stall--occupied.stall--has-logo::before {
            content: "";
            position: absolute; inset: 0;
            border-radius: var(--radius);
            background: linear-gradient(180deg, rgba(0,0,0,.15), rgba(0,0,0,.45));
        }
        .stall--occupied.stall--has-logo .stall-id-badge--circle,
        .stall--occupied.stall--has-logo .stall-name {
            position: relative; z-index: 1;
        }

        .stall--pending {
            background: linear-gradient(145deg, #fffbeb, #fef3c7);
            border-color: #fcd34d;
            box-shadow: 0 4px 16px rgba(245,158,11,.15);
            cursor: default;
        }

        .stall-id-badge {
            font-size: 20px; font-weight: 800; line-height: 1;
        }
        .stall--vacant  .stall-id-badge { color: var(--green-700); }
        .stall--occupied .stall-id-badge { color: #b91c1c; }
        .stall--pending  .stall-id-badge { color: #92400e; }

        /* Occupied cards use the merchant logo as the card background; the
           stall ID sits on top inside a circular badge, like a sticker. */
        .stall-id-badge--circle {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,.95);
            color: #b91c1c; font-size: 17px;
            border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,.25);
        }

        .stall-status-badge {
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .08em; padding: 3px 10px; border-radius: 50px;
        }
        .stall--vacant  .stall-status-badge { background: var(--green-500); color: #fff; }
        .stall--pending  .stall-status-badge { background: var(--amber-500); color: #fff; }

        .stall-name {
            font-size: 11px; font-weight: 600; color: #374151;
            max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .stall--occupied.stall--has-logo .stall-name {
            color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,.6);
        }
        .stall--occupied:not(.stall--has-logo) .stall-id-badge--circle {
            background: linear-gradient(135deg, var(--green-800), var(--green-700));
            color: #fff;
        }

        .stall-timer {
            font-size: 11px; font-weight: 700; color: #b45309;
            font-variant-numeric: tabular-nums;
        }

        .stall-apply-hint {
            font-size: 10px; font-weight: 600; color: var(--green-700);
            opacity: .7;
        }

        /* ── PEEK MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 2000;
            background: rgba(5,46,22,.55); backdrop-filter: blur(6px);
            align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.is-open { display: flex; animation: fadeIn .2s ease; }

        @keyframes fadeIn  { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(28px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-card {
            background: var(--white); border-radius: 24px;
            width: 100%; max-width: 440px;
            box-shadow: var(--shadow-lg);
            animation: slideUp .25s ease;
            overflow: hidden;
        }

        .modal-header {
            padding: 28px 28px 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex; align-items: flex-start; justify-content: space-between;
        }
        .modal-stall-id {
            font-size: 32px; font-weight: 800; color: var(--green-800); line-height: 1;
        }
        .modal-stall-label {
            font-size: 13px; color: var(--gray-600); margin-top: 4px;
        }
        .modal-status-chip {
            display: inline-block; padding: 5px 14px; border-radius: 50px;
            font-size: 12px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .07em;
        }
        .chip--vacant   { background: var(--green-100); color: var(--green-700); }
        .chip--pending  { background: var(--amber-100); color: #92400e; }

        .modal-body { padding: 24px 28px; }

        .modal-specs {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
            margin-bottom: 24px;
        }
        .spec-item { }
        .spec-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .07em; color: var(--gray-600); margin-bottom: 4px;
        }
        .spec-value { font-size: 16px; font-weight: 700; color: var(--gray-800); }
        .spec-value--green { color: var(--green-700); }

        .modal-merchant-block {
            background: var(--green-100); border-radius: 12px;
            padding: 14px 16px; margin-bottom: 18px;
            display: flex; align-items: center; gap: 12px;
        }
        .modal-merchant-avatar {
            width: 48px; height: 48px; border-radius: 50%;
            background: var(--green-700); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 800; flex-shrink: 0;
            object-fit: cover; overflow: hidden;
        }
        .modal-merchant-name { font-weight: 700; font-size: 15px; color: var(--green-800); }
        .modal-merchant-sub  { font-size: 12px; color: var(--green-700); }

        .modal-pending-block {
            background: var(--amber-100); border-radius: 12px;
            padding: 14px 16px; margin-bottom: 18px; text-align: center;
        }
        .modal-pending-text { font-size: 13px; font-weight: 600; color: #92400e; }
        .modal-pending-timer { font-size: 22px; font-weight: 800; color: #b45309; margin-top: 4px; }

        /* ── FOOTER ── */
        footer {
            background: var(--green-900); color: rgba(255,255,255,.7);
            text-align: center; padding: 24px 20px; font-size: 13px;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 640px) {
            .stall-row { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .navbar { padding: 12px 20px; }
            .hero { padding: 48px 20px 32px; }
            .hero h1 { font-size: 28px; }
            .content { padding: 28px 16px 60px; }
        }
        @media (max-width: 420px) {
            .stall-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="<?= BASE_URL ?>" class="navbar-brand">
        <img src="<?= ICONS_URL ?>/GenPay_logo.png" alt="GenPay Logo">
        GenPay
    </a>
    <div class="navbar-links">
        <a href="<?= BASE_URL ?>/stalls" class="btn-nav btn-nav--outline">View Stalls</a>
        <a href="<?= BASE_URL ?>/login.php" class="btn-nav btn-nav--solid">Portal Login</a>
    </div>
</nav>

<!-- HERO -->
<div class="hero">
    <div class="hero-tag">
        Now Leasing &middot; Live Occupancy
    </div>
    <h1>General De Jesus College <span>Stall Directory</span></h1>
    <p>See what's open below. Apply once and we'll assign your stall during review &mdash; no need to pick one here.</p>
    <a href="<?= BASE_URL ?>/apply" class="btn-apply-now">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        Apply Now
    </a>
</div>

<!-- LEGEND -->
<div class="legend">
    <div class="legend-item"><div class="legend-dot legend-dot--vacant"></div> Vacant</div>
    <div class="legend-item"><div class="legend-dot legend-dot--occupied"></div> Occupied</div>
    <div class="legend-item"><div class="legend-dot legend-dot--pending"></div> Pending Application</div>
</div>

<!-- STALL GRID -->
<div class="content">
    <?php foreach ($grid as $rowLabel => $cols): ?>
        <p class="section-title">Row <?= htmlspecialchars($rowLabel) ?></p>
        <div class="stall-row" role="list">
            <?php for ($col = 1; $col <= 5; $col++):

                $stall = $cols[$col] ?? null;
                if (!$stall) {
                    continue;
                }
                $cls = statusClass($stall["status"]);
                $lbl = statusLabel($stall["status"]);
                ?>
            <?php
            // Rental rate stays out of the public payload entirely - not just
            // unrendered - so it never appears in view-source/devtools either.
            $publicStall = $stall;
            unset($publicStall["monthly_rate"]);
            ?>
            <div class="stall <?= $cls,
                $stall["status"] === "occupied" && $stall["merchant_logo"]
                    ? " stall--has-logo"
                    : "" ?>"
                 id="stall-<?= htmlspecialchars($stall["stall_id"]) ?>"
                 role="listitem"
                 data-stall='<?= htmlspecialchars(
                     json_encode($publicStall),
                     ENT_QUOTES,
                 ) ?>'
                 <?php if (
                     $stall["status"] === "occupied" &&
                     $stall["merchant_logo"]
                 ): ?>
                 style="background-image:url('<?= htmlspecialchars(
                     BASE_URL . "/" . $stall["merchant_logo"],
                 ) ?>')"
                 <?php endif; ?>
                 <?php if ($stall["status"] === "vacant"): ?>
                 onclick="openModal(this)"
                 tabindex="0"
                 onkeydown="if(event.key==='Enter')openModal(this)"
                 aria-label="<?= htmlspecialchars(
                     $stall["label"],
                 ) ?> - Vacant, view details"
                 <?php elseif ($stall["status"] === "occupied"): ?>
                 onclick="openModal(this)"
                 tabindex="0"
                 onkeydown="if(event.key==='Enter')openModal(this)"
                 aria-label="<?= htmlspecialchars(
                     $stall["label"],
                 ) ?> - Operated by <?= htmlspecialchars(
     $stall["merchant_stall_name"],
 ) ?>"
                 <?php else: ?>
                 onclick="openModal(this)"
                 tabindex="0"
                 onkeydown="if(event.key==='Enter')openModal(this)"
                 aria-label="<?= htmlspecialchars(
                     $stall["label"],
                 ) ?> - Pending application"
                 <?php endif; ?>
            >
                <?php if ($stall["status"] === "occupied"): ?>
                    <!-- A tenant logo + company name implies occupancy; no "Occupied" text needed.
                         The logo is the card's background image, and the stall ID sits on
                         top inside a circular badge, like a sticker. -->
                    <div class="stall-id-badge stall-id-badge--circle"><?= htmlspecialchars(
                        $stall["stall_id"],
                    ) ?></div>
                    <div class="stall-name"><?= htmlspecialchars(
                        $stall["merchant_stall_name"],
                    ) ?></div>
                <?php else: ?>
                    <div class="stall-id-badge"><?= htmlspecialchars(
                        $stall["stall_id"],
                    ) ?></div>
                    <div class="stall-status-badge"><?= $lbl ?></div>
                    <?php if ($stall["status"] === "pending_application"): ?>
                    <div class="stall-timer"
                         data-expires="<?= htmlspecialchars(
                             $stall["pending_expires_at"] ?? "",
                         ) ?>">
                         --:--
                    </div>
                    <?php else: ?>
                    <div class="stall-apply-hint">View details</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php
            endfor; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- PEEK MODAL -->
<div class="modal-overlay" id="stallModal" role="dialog" aria-modal="true" aria-labelledby="modal-stall-id">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <div class="modal-stall-id" id="modal-stall-id">--</div>
                <div class="modal-stall-label" id="modal-stall-label">--</div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="modal-status-chip" id="modal-status-chip">--</span>
                <button type="button" class="btn-close" onclick="closeModal()" aria-label="Close"></button>
            </div>
        </div>

        <div class="modal-body">

            <!-- Specs grid (rental rate stays out of public view - finance-only) -->
            <div class="modal-specs">
                <div class="spec-item">
                    <div class="spec-label">Area</div>
                    <div class="spec-value" id="modal-area">--</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Location</div>
                    <div class="spec-value" id="modal-location">--</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Stall ID</div>
                    <div class="spec-value" id="modal-id-detail">--</div>
                </div>
            </div>

            <!-- Occupied block - logo + name implies occupancy, no "Occupied" text -->
            <div class="modal-merchant-block" id="modal-merchant-block" style="display:none">
                <div class="modal-merchant-avatar" id="modal-merchant-avatar">?</div>
                <div>
                    <div class="modal-merchant-name" id="modal-merchant-name">--</div>
                    <div class="modal-merchant-sub">Currently operating</div>
                </div>
            </div>

            <!-- Pending block -->
            <div class="modal-pending-block" id="modal-pending-block" style="display:none">
                <div class="modal-pending-text">⏳ An application is being processed</div>
                <div class="modal-pending-timer" id="modal-pending-timer">--:--</div>
                <div style="font-size:11px;color:#b45309;margin-top:4px;">Stall will reopen if not submitted in time</div>
            </div>

        </div>
    </div>
</div>

<footer>
    &copy; <?= date(
        "Y",
    ) ?> General de Jesus College &mdash; GenPay Stall Management
</footer>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let modalTimer = null;

function openModal(el) {
    const data = JSON.parse(el.dataset.stall);
    const modal = document.getElementById('stallModal');

    // Header
    document.getElementById('modal-stall-id').textContent   = data.stall_id;
    document.getElementById('modal-stall-label').textContent = data.label;
    document.getElementById('modal-id-detail').textContent   = data.stall_id;

    // Status chip - occupied has no chip; the logo + name below implies it
    const chip = document.getElementById('modal-status-chip');
    const chipMeta = { vacant: ['Vacant', 'chip--vacant'], pending_application: ['Pending', 'chip--pending'] }[data.status];
    chip.style.display = chipMeta ? 'inline-block' : 'none';
    if (chipMeta) { chip.textContent = chipMeta[0]; chip.className = 'modal-status-chip ' + chipMeta[1]; }

    // Specs (rental rate is finance-only, not shown publicly)
    document.getElementById('modal-area').textContent     = data.area_sqm ? data.area_sqm + ' m²' : 'N/A';
    document.getElementById('modal-location').textContent = 'Row ' + data.row_label + ', Slot ' + data.col_number;

    // Conditional blocks - this modal is informational only. Applying
    // happens exactly one way on this page: the gold button in the hero.
    const merchantBlock = document.getElementById('modal-merchant-block');
    const pendingBlock  = document.getElementById('modal-pending-block');

    merchantBlock.style.display = 'none';
    pendingBlock.style.display  = 'none';
    if (modalTimer) clearInterval(modalTimer);

    if (data.status === 'occupied') {
        merchantBlock.style.display = 'flex';
        const name = data.merchant_stall_name || 'This Stall';
        const avatar = document.getElementById('modal-merchant-avatar');
        document.getElementById('modal-merchant-name').textContent = name;
        if (data.merchant_logo) {
            avatar.innerHTML = `<img src="${BASE_URL}/${data.merchant_logo}" alt="${name} logo" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
        } else {
            avatar.textContent = name.charAt(0).toUpperCase();
        }

    } else if (data.status === 'pending_application') {
        pendingBlock.style.display = 'block';
        startTimer(data.pending_expires_at, 'modal-pending-timer');
    }

    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('stallModal').classList.remove('is-open');
    document.body.style.overflow = '';
    if (modalTimer) clearInterval(modalTimer);
}

function startTimer(expiresAt, targetId) {
    const el  = document.getElementById(targetId);
    const end = expiresAt ? new Date(expiresAt.replace(' ', 'T')).getTime() : 0;

    function tick() {
        const diff = Math.max(0, Math.round((end - Date.now()) / 1000));
        const m    = String(Math.floor(diff / 60)).padStart(2, '0');
        const s    = String(diff % 60).padStart(2, '0');
        if (el) el.textContent = m + ':' + s;
        if (diff <= 0 && modalTimer) clearInterval(modalTimer);
    }
    tick();
    modalTimer = setInterval(tick, 1000);
}

// Countdown timers on stall cards
document.querySelectorAll('.stall-timer[data-expires]').forEach(el => {
    const end = new Date(el.dataset.expires.replace(' ', 'T')).getTime();
    function tick() {
        const diff = Math.max(0, Math.round((end - Date.now()) / 1000));
        const m = String(Math.floor(diff / 60)).padStart(2, '0');
        const s = String(diff % 60).padStart(2, '0');
        el.textContent = m + ':' + s;
        if (diff <= 0) { clearInterval(t); location.reload(); }
    }
    tick();
    const t = setInterval(tick, 1000);
});

// Close modal on overlay click
document.getElementById('stallModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Close on Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>

</body>
</html>
