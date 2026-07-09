<?php
session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['student']);
$currentUser = gjc_current_user($db);
$wallet      = gjc_student_wallet($db, $currentUser['id']);
$balance     = $wallet['balance'];
$dailySent   = gjc_p2p_daily_sent($db, $currentUser['id']);
$dailyLimit  = 5000.00;
$dailyRemaining = max(0, $dailyLimit - $dailySent);
$sendMax     = min($balance, $dailyRemaining);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send GenCoin | GenPay</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=56">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="student-layout">
    <aside class="student-sidebar" id="studentSidebar">
        <div class="student-brand">
            <div class="student-brand-logo"><img src="<?= ICONS_URL ?>/GenDeJesusFavicon.png" alt="GJC Logo"></div>
            <div class="student-brand-text"><h4>GenPay</h4><span>Student Portal</span></div>
        </div>
        <nav class="student-menu">
            <a href="<?= DASHBOARD_URL ?>"><i class="fa-solid fa-gauge-high student-nav-icon"></i><span class="student-nav-text">Dashboard</span></a>
            <a href="<?= STUDENT_URL ?>/cart.php"><i class="fa-solid fa-cart-shopping student-nav-icon"></i><span class="student-nav-text">Shop Cart</span></a>
            <a href="<?= STUDENT_URL ?>/transfer.php" class="active"><i class="fa-solid fa-money-bill-transfer student-nav-icon"></i><span class="student-nav-text">Send GenCoin</span></a>
            <a href="<?= STUDENT_URL ?>/withdraw.php"><i class="fa-solid fa-money-bill-wave student-nav-icon"></i><span class="student-nav-text">Withdraw</span></a>
            <a href="<?= STUDENT_URL ?>/topup_request.php"><i class="fa-solid fa-circle-plus student-nav-icon"></i><span class="student-nav-text">Top-Up</span></a>
            <a href="<?= STUDENT_URL ?>/history.php"><i class="fa-solid fa-receipt student-nav-icon"></i><span class="student-nav-text">History</span></a>
            <a href="<?= STUDENT_URL ?>/profile.php"><i class="fa-solid fa-user student-nav-icon"></i><span class="student-nav-text">Profile</span></a>
        </nav>
        <a href="<?= BASE_URL ?>/logout.php" class="student-logout" onclick="openLogoutModal(event);">
            <i class="fa-solid fa-arrow-right-from-bracket student-logout-icon"></i><span>Logout</span>
        </a>
    </aside>
    <?php require __DIR__ . '/../includes/partials/logout_modal.php'; ?>

    <main class="student-main">
        <header class="student-topbar">
            <button class="student-menu-btn" onclick="toggleStudentSidebar()">&#9776;</button>
            <div><h1>Send GenCoin</h1><p>Send GenCoins instantly to another student.</p></div>
            <div class="student-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="student-avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></div>
            </div>
        </header>

        <style>
        .sg-shell { max-width: 480px; margin: 0 auto; }
        .sg-bal { background:linear-gradient(150deg,#0b5c2c,#116a38); color:#fff; border-radius:18px;
                    padding:16px 20px; display:flex; justify-content:space-between; align-items:center;
                    box-shadow:0 12px 28px rgba(11,92,44,.2); }
        .sg-bal .lbl { font-size:11px; text-transform:uppercase; letter-spacing:.08em; opacity:.8; }
        .sg-bal .gc { font-size:26px; font-weight:800; line-height:1.1; }
        .sg-bal .php { font-size:13px; opacity:.85; }
        .sg-bal .daily { text-align:right; font-size:12px; opacity:.9; }
        .sg-bal .daily strong { display:block; font-size:15px; font-weight:800; }
        .sg-card { background:#fff; border:1px solid #eef2ef; border-radius:20px; padding:24px; margin-top:16px;
                    box-shadow:0 10px 26px rgba(11,92,44,.06); }
        .sg-flabel { display:block; font-size:13px; font-weight:600; color:#374151; margin:0 0 6px; }
        .sg-flabel .opt { font-weight:400; color:#9ca3af; }
        .sg-recip { font-size:13px; font-weight:600; min-height:20px; margin:6px 0 0; }
        .sg-amount-wrap { position:relative; }
        .sg-amount-wrap .sign { position:absolute; left:16px; top:50%; transform:translateY(-50%);
                    font-size:22px; font-weight:800; color:#0b5c2c; }
        .sg-amount-wrap input { padding-left:40px; font-size:22px; font-weight:800; height:60px; }
        .sg-equiv { font-size:13px; color:#116a38; font-weight:600; margin-top:6px; min-height:18px; }
        .sg-hint { font-size:12px; color:#64748b; margin-top:4px; }
        .sg-send { width:100%; border:0; border-radius:14px; padding:15px; font-size:16px; font-weight:800;
                    color:#fff; background:linear-gradient(135deg,#0b5c2c,#116a38); cursor:pointer; margin-top:20px; transition:.15s; }
        .sg-send:hover:not(:disabled) { opacity:.92; }
        .sg-send:disabled { background:#cbd5e1; cursor:not-allowed; }
        .sg-success { background:#fff; border:1px solid #eef2ef; border-radius:20px; padding:34px 24px; text-align:center; margin-top:16px; }
        .sg-success-icon { width:76px; height:76px; border-radius:50%; background:#dcfce7; color:#16a34a;
                    display:grid; place-items:center; font-size:32px; margin:0 auto 16px; }
        .sg-btn-row { display:flex; gap:10px; max-width:320px; margin:22px auto 0; }
        .sg-btn { flex:1; border:0; border-radius:12px; padding:12px; font-size:14px; font-weight:700; cursor:pointer; }
        .sg-btn--ghost { background:#f1f5f9; color:#374151; }
        .sg-btn--primary { background:#116a38; color:#fff; }
        </style>

        <div class="sg-shell">
            <!-- Compact balance strip -->
            <div class="sg-bal">
                <div>
                    <div class="lbl">Available</div>
                    <div class="gc" id="sgGcBal"><?= number_format($balance / 10, 1) ?> <span style="font-size:14px;font-weight:600">GC</span></div>
                    <div class="php"><?= gjc_money($balance) ?></div>
                </div>
                <div class="daily">
                    Daily left
                    <strong><?= gjc_money($dailyRemaining) ?></strong>
                    of <?= gjc_money($dailyLimit) ?>
                </div>
            </div>

            <!-- Single-screen send form -->
            <div class="sg-card" id="sgForm">
                <label class="sg-flabel" for="sgRecipient">Send to (Student ID)</label>
                <input type="text" class="form-control form-control-lg" id="sgRecipient"
                    placeholder="e.g. 2024-00123" autocomplete="off">
                <div class="sg-recip" id="sgRecipMsg"></div>

                <label class="sg-flabel" for="sgAmount" style="margin-top:16px">Amount</label>
                <div class="sg-amount-wrap">
                    <span class="sign">&#8369;</span>
                    <input type="number" class="form-control" id="sgAmount"
                        min="1" max="<?= $sendMax ?>" step="0.01" placeholder="0.00">
                </div>
                <div class="sg-equiv" id="sgEquiv"></div>
                <div class="sg-hint">Available: <?= gjc_money($balance) ?> &middot; Daily remaining: <?= gjc_money($dailyRemaining) ?></div>

                <label class="sg-flabel" for="sgMessage" style="margin-top:16px">Message <span class="opt">(optional)</span></label>
                <input type="text" class="form-control" id="sgMessage" maxlength="255" placeholder="e.g. For lunch 🍜">

                <div id="sgError" style="color:#dc2626;font-size:13px;margin-top:10px;min-height:16px"></div>

                <button type="button" class="sg-send" id="sgSend" disabled>
                    <i class="fa-solid fa-paper-plane me-1"></i> <span id="sgSendLabel">Send</span>
                </button>
            </div>

            <!-- Success -->
            <div class="sg-success" id="sgSuccess" style="display:none">
                <div class="sg-success-icon"><i class="fa-solid fa-check"></i></div>
                <h3 style="margin:0 0 6px;color:#116a38;font-weight:800">Sent!</h3>
                <p id="sgSuccessMsg" style="color:#374151;font-size:14px;margin:0 0 6px"></p>
                <p style="color:#6b7280;font-size:12px;margin:0">Reference: <span id="sgSuccessRef" style="font-family:monospace"></span></p>
                <div class="sg-btn-row">
                    <button type="button" class="sg-btn sg-btn--ghost" onclick="location.href='<?= DASHBOARD_URL ?>'">Done</button>
                    <button type="button" class="sg-btn sg-btn--primary" onclick="location.reload()">Send Another</button>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
function toggleStudentSidebar() { document.getElementById('studentSidebar').classList.toggle('collapsed'); }
document.querySelector('.student-menu a.active')?.scrollIntoView({ inline: 'center', block: 'nearest' });

// ── Single-screen Send GenCoin (uses the same api/transfer.php lookup + transfer) ──
const SG_API = '<?= STUDENT_URL ?>/api/transfer.php';
const SG_MAX = <?= json_encode((float) $sendMax) ?>;
let sgName = '', sgSid = '', sgValidRecipient = false;

const sgRecipient = document.getElementById('sgRecipient');
const sgRecipMsg  = document.getElementById('sgRecipMsg');
const sgAmount    = document.getElementById('sgAmount');
const sgEquiv     = document.getElementById('sgEquiv');
const sgError     = document.getElementById('sgError');
const sgSend      = document.getElementById('sgSend');
const sgSendLabel = document.getElementById('sgSendLabel');

function sgFmt(n) { return '₱' + (+n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function sgGc(php) { return (php / 10).toLocaleString('en-PH', { maximumFractionDigits: 2 }); }

function sgRefresh() {
    const php = parseFloat(sgAmount.value) || 0;
    sgEquiv.textContent = php > 0 ? ('≈ ' + sgGc(php) + ' GenCoin (₱10 = 1 GC)') : '';
    sgSendLabel.textContent = php > 0 ? ('Send ' + sgFmt(php)) : 'Send';
    sgSend.disabled = !(sgValidRecipient && php >= 1 && php <= SG_MAX);
}

// Recipient lookup (on blur / Enter; edits reset the confirmed recipient)
sgRecipient.addEventListener('input', () => { sgValidRecipient = false; sgName = ''; sgSid = ''; sgRefresh(); });
sgRecipient.addEventListener('blur', sgLookup);
sgRecipient.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); sgLookup(); } });

async function sgLookup() {
    const sid = sgRecipient.value.trim();
    if (!sid) { sgRecipMsg.textContent = ''; return; }
    sgRecipMsg.textContent = 'Looking up…'; sgRecipMsg.style.color = '#6b7280';
    try {
        const f = new FormData(); f.append('action', 'lookup'); f.append('student_id', sid);
        const d = await (await fetch(SG_API, { method: 'POST', body: f })).json();
        if (d.success && d.name) {
            sgName = d.name; sgSid = sid; sgValidRecipient = true;
            sgRecipMsg.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i>' + d.name;
            sgRecipMsg.style.color = '#16a34a';
        } else {
            sgValidRecipient = false;
            sgRecipMsg.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>' + (d.message || 'Student not found');
            sgRecipMsg.style.color = '#dc2626';
        }
    } catch (e) {
        sgValidRecipient = false;
        sgRecipMsg.textContent = 'Network error. Try again.'; sgRecipMsg.style.color = '#dc2626';
    }
    sgRefresh();
}

sgAmount.addEventListener('input', () => { sgError.textContent = ''; sgRefresh(); });

sgSend.addEventListener('click', async () => {
    const php = parseFloat(sgAmount.value) || 0;
    sgError.textContent = '';
    if (!sgValidRecipient) { sgError.textContent = 'Enter a valid recipient Student ID first.'; return; }
    if (php < 1)      { sgError.textContent = 'Enter an amount of at least ₱1.00.'; return; }
    if (php > SG_MAX) { sgError.textContent = 'Amount exceeds your available balance / daily limit (' + sgFmt(SG_MAX) + ').'; return; }
    if (!confirm('Send ' + sgFmt(php) + ' to ' + sgName + '?\nThis is instant and cannot be undone.')) return;

    sgSend.disabled = true;
    sgSendLabel.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';
    try {
        const f = new FormData();
        f.append('action', 'transfer');
        f.append('recipient_student_id', sgSid);
        f.append('amount', php);
        f.append('message', document.getElementById('sgMessage').value.trim());
        const d = await (await fetch(SG_API, { method: 'POST', body: f })).json();
        if (d.success) {
            document.getElementById('sgForm').style.display = 'none';
            document.getElementById('sgSuccess').style.display = '';
            document.getElementById('sgSuccessMsg').textContent = d.message || 'Transfer complete.';
            document.getElementById('sgSuccessRef').textContent = d.reference || '—';
        } else {
            sgError.textContent = d.message || 'Transfer failed.';
            sgSend.disabled = false;
            sgRefresh();
        }
    } catch (e) {
        sgError.textContent = 'Network error. Please try again.';
        sgSend.disabled = false;
        sgRefresh();
    }
}, false);
</script>
</body>
</html>
