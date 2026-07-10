<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['student']);
gjc_ensure_operational_tables($db);

$currentUser = gjc_current_user($db);
$wallet      = gjc_student_wallet($db, $currentUser['id']);
$studentName = $currentUser['name'];
$studentID   = 'GJC-' . str_pad((string) $currentUser['id'], 5, '0', STR_PAD_LEFT);
$balance     = $wallet['balance'];
$notice = '';
$error  = '';

// Is this wallet frozen by a parent/guardian? Frozen wallets cannot cash out.
$isFrozen = false;
if ($wallet['id'] > 0) {
    $fz = $db->prepare("SELECT is_frozen FROM student_wallets WHERE id = ?");
    $fz->execute([$wallet['id']]);
    $isFrozen = (int) $fz->fetchColumn() === 1;
}

// Total already queued (pending) so we never let requests exceed the balance.
$pendingTotal = 0.0;
if ($wallet['id'] > 0) {
    $pStmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests
          WHERE user_id = ? AND status = 'pending'"
    );
    $pStmt->execute([$currentUser['id']]);
    $pendingTotal = (float) $pStmt->fetchColumn();
}
$withdrawable = max(0, $balance - $pendingTotal);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

    if ($isFrozen) {
        $error = 'Your wallet is frozen by a parent or guardian. Withdrawals are disabled.';
    } elseif (!$amount || $amount <= 0) {
        $error = 'Enter a valid withdrawal amount.';
    } elseif ($amount < 1.00) {
        $error = 'Minimum withdrawal amount is ₱1.00.';
    } elseif ($wallet['id'] <= 0) {
        $error = 'Your student wallet is not ready. Contact the finance office.';
    } elseif ($amount > $balance) {
        $error = 'Requested amount is higher than your available balance.';
    } elseif ($amount > $withdrawable) {
        $error = 'You already have ' . gjc_money($pendingTotal) . ' in pending withdrawals. '
               . 'Together they would exceed your balance. Available to request: ' . gjc_money($withdrawable) . '.';
    } else {
        $reference = gjc_reference('WTH');
        $stmt = $db->prepare(
            "INSERT INTO withdrawal_requests
                (user_id, student_wallet_id, amount, method, status, reference_no)
             VALUES (?, ?, ?, 'Cashier Release', 'pending', ?)"
        );
        $stmt->execute([$currentUser['id'], $wallet['id'], $amount, $reference]);
        $notice = "Withdrawal request {$reference} was submitted for cashier release.";

        // Refresh the pending/withdrawable figures after a successful submit.
        $pendingTotal += (float) $amount;
        $withdrawable = max(0, $balance - $pendingTotal);
    }
}

$stmt = $db->prepare(
    "SELECT reference_no, amount, method, status, created_at
       FROM withdrawal_requests
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT 8"
);
$stmt->execute([$currentUser['id']]);
$recentWithdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=58">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

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
            <a href="<?= STUDENT_URL ?>/transfer.php"><i class="fa-solid fa-money-bill-transfer student-nav-icon"></i><span class="student-nav-text">Send GenCoin</span></a>
            <a href="<?= STUDENT_URL ?>/withdraw.php" class="active"><i class="fa-solid fa-money-bill-wave student-nav-icon"></i><span class="student-nav-text">Withdraw</span></a>
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
            <div><h1>Withdraw</h1><p>Cash out your GenCoins at the cashier.</p></div>
            <div class="student-user">
                <span><?= gjc_e($studentName) ?></span>
                <div class="student-avatar"><?= strtoupper(substr($studentName, 0, 1)) ?></div>
            </div>
        </header>

        <style>
        .wd-wrap { display:grid; grid-template-columns: 1.1fr 1fr; gap:20px; align-items:start; }
        @media (max-width: 900px) { .wd-wrap { grid-template-columns: 1fr; } }
        .wd-balance { background:linear-gradient(150deg,#117039,#116a38); color:#fff; border-radius:20px;
                    padding:22px 24px; box-shadow:0 14px 34px rgba(17, 112, 57,.22); }
        .wd-balance span.lbl { font-size:12px; text-transform:uppercase; letter-spacing:.08em; opacity:.8; }
        .wd-balance .gc { font-size:34px; font-weight:800; line-height:1.1; margin-top:4px; }
        .wd-balance .php { font-size:14px; opacity:.85; margin-top:2px; }
        .wd-balance .rate { font-size:11px; opacity:.6; margin-top:2px; }
        .wd-meta { display:flex; gap:20px; margin-top:16px; padding-top:14px; border-top:1px solid rgba(255,255,255,.15); }
        .wd-meta div span { display:block; font-size:11px; opacity:.7; text-transform:uppercase; letter-spacing:.05em; }
        .wd-meta div strong { font-size:15px; font-weight:700; }
        .wd-card { background:#fff; border:1px solid #e5e7eb; border-radius:20px; padding:24px; }
        .wd-card h3 { margin:0 0 4px; font-size:18px; font-weight:800; color:#117039; }
        .wd-card p.sub { margin:0 0 18px; font-size:13px; color:#6b7280; }
        .wd-flabel { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
        .wd-input-wrap { position:relative; }
        .wd-input-wrap .peso-sign { position:absolute; left:14px; top:50%; transform:translateY(-50%);
                    font-size:18px; font-weight:700; color:#117039; }
        .wd-input-wrap input { padding-left:34px; }
        .wd-equiv { font-size:13px; color:#116a38; font-weight:600; margin-top:6px; min-height:18px; }
        .wd-chips { display:flex; flex-wrap:wrap; gap:8px; margin:12px 0 4px; }
        .wd-chip { border:1px solid #bbf7d4; background:#f0fdf6; color:#116a38; border-radius:999px;
                    padding:7px 14px; font-size:13px; font-weight:700; cursor:pointer; transition:.15s; }
        .wd-chip:hover { background:#dcfce9; }
        .wd-note { display:flex; gap:10px; font-size:12px; color:#64748b; background:#f9fafb;
                    border-radius:12px; padding:12px 14px; margin:16px 0; }
        .wd-submit { width:100%; border:0; border-radius:14px; padding:14px; font-size:15px; font-weight:800;
                    color:#fff; background:linear-gradient(135deg,#117039,#116a38); cursor:pointer; transition:.15s; }
        .wd-submit:hover { opacity:.92; }
        .wd-submit:disabled { background:#a7f3d0; color:#065f46; cursor:not-allowed; }
        .wd-status { padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; text-transform:capitalize; }
        .wd-status.pending  { background:#fef3c7; color:#92400e; }
        .wd-status.released { background:#dcfce9; color:#166534; }
        .wd-status.rejected { background:#fee2e2; color:#991b1b; }
        </style>

        <?php if ($notice): ?>
        <div class="alert alert-success d-flex align-items-center" style="border-radius:14px">
            <i class="fa-solid fa-circle-check me-2"></i><div><?= gjc_e($notice) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center" style="border-radius:14px">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><div><?= gjc_e($error) ?></div>
        </div>
        <?php endif; ?>

        <section class="wd-wrap mb-4">

            <!-- Balance + request form -->
            <div>
                <div class="wd-balance mb-3">
                    <span class="lbl">Available Balance</span>
                    <div class="gc"><?= number_format($balance / 10, 1) ?> <span style="font-size:18px;font-weight:600">GenCoin</span></div>
                    <div class="php"><?= gjc_money($balance) ?></div>
                    <div class="rate">&#8369;10 = 1 GenCoin</div>
                    <div class="wd-meta">
                        <div><span>Pending</span><strong><?= gjc_money($pendingTotal) ?></strong></div>
                        <div><span>Free to Withdraw</span><strong><?= gjc_money($withdrawable) ?></strong></div>
                    </div>
                </div>

                <div class="wd-card">
                    <h3>Request a Withdrawal</h3>
                    <p class="sub">Submit a cash-out request. A cashier will review it and hand over the cash at the finance office.</p>

                    <?php if ($isFrozen): ?>
                    <div class="alert alert-warning" style="border-radius:12px;font-size:13px">
                        <i class="fa-solid fa-snowflake me-1"></i> Your wallet is currently frozen by a parent or guardian, so withdrawals are disabled.
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="withdrawForm" autocomplete="off">
                        <label class="wd-flabel" for="amount">Amount to Withdraw</label>
                        <div class="wd-input-wrap">
                            <span class="peso-sign">&#8369;</span>
                            <input type="number" class="form-control form-control-lg" id="amount" name="amount"
                                min="1" max="<?= $withdrawable ?>" step="0.01" placeholder="0.00"
                                <?= ($isFrozen || $withdrawable < 1) ? 'disabled' : '' ?>>
                        </div>
                        <div class="wd-equiv" id="wdEquiv"></div>

                        <div class="wd-chips">
                            <?php foreach ([50, 100, 200, 500] as $chip): ?>
                                <?php if ($chip <= $withdrawable): ?>
                                <button type="button" class="wd-chip" data-amt="<?= $chip ?>">&#8369;<?= $chip ?></button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($withdrawable >= 1): ?>
                            <button type="button" class="wd-chip" data-amt="<?= $withdrawable ?>">Max (<?= gjc_money($withdrawable) ?>)</button>
                            <?php endif; ?>
                        </div>

                        <div class="wd-note">
                            <i class="fa-solid fa-circle-info" style="margin-top:2px"></i>
                            <span>Your balance is held until the cashier releases the cash. It is only deducted when the request is marked <strong>Released</strong>. You can cancel by visiting the finance office before release.</span>
                        </div>

                        <button type="submit" class="wd-submit" id="wdSubmit"
                            <?= ($isFrozen || $withdrawable < 1) ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-money-bill-wave me-1"></i>
                            <?= $withdrawable < 1 ? 'No Balance to Withdraw' : 'Submit Withdrawal Request' ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- History -->
            <div class="wd-card">
                <h3>Recent Withdrawals</h3>
                <p class="sub">Your latest cash-out requests and their status.</p>

                <?php if (empty($recentWithdrawals)): ?>
                <div class="text-center py-4" style="color:#9ca3af">
                    <i class="fa-solid fa-money-bill-wave" style="font-size:32px;opacity:.5"></i>
                    <p class="mt-2 mb-0" style="font-size:13px">No withdrawals yet.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr style="font-size:12px;text-transform:uppercase;color:#6b7280">
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentWithdrawals as $w): ?>
                            <tr>
                                <td style="font-family:monospace;font-size:12px"><?= gjc_e($w['reference_no']) ?></td>
                                <td style="font-weight:700"><?= gjc_money($w['amount']) ?></td>
                                <td><span class="wd-status <?= gjc_e(strtolower($w['status'])) ?>"><?= gjc_e($w['status']) ?></span></td>
                                <td style="font-size:12px;color:#6b7280"><?= gjc_e(date('M d, h:i A', strtotime($w['created_at']))) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </section>
    </main>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
function toggleStudentSidebar() {
    document.getElementById('studentSidebar').classList.toggle('collapsed');
}
document.querySelector('.student-menu a.active')?.scrollIntoView({ inline: 'center', block: 'nearest' });

const wdAmount = document.getElementById('amount');
const wdEquiv  = document.getElementById('wdEquiv');
const WD_MAX   = <?= json_encode((float) $withdrawable) ?>;

function wdRender() {
    const php = parseFloat(wdAmount.value) || 0;
    wdEquiv.textContent = php > 0
        ? '≈ ' + (php / 10).toLocaleString('en-PH', { maximumFractionDigits: 2 }) + ' GenCoin'
        : '';
}
if (wdAmount) {
    wdAmount.addEventListener('input', wdRender);
    document.querySelectorAll('.wd-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            wdAmount.value = parseFloat(chip.dataset.amt).toFixed(2);
            wdRender();
            wdAmount.focus();
        });
    });
    document.getElementById('withdrawForm').addEventListener('submit', e => {
        const php = parseFloat(wdAmount.value) || 0;
        if (php < 1 || php > WD_MAX) {
            e.preventDefault();
            wdEquiv.textContent = 'Enter an amount between ₱1.00 and ₱' + WD_MAX.toLocaleString('en-PH', { minimumFractionDigits: 2 }) + '.';
            wdEquiv.style.color = '#dc2626';
        }
    });
}
</script>
</body>
</html>
