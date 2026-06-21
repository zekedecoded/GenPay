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
$dailyPct    = min(100, round(($dailySent / $dailyLimit) * 100, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Tokens | GenPay</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=41">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="student-layout">
    <aside class="student-sidebar" id="studentSidebar">
        <div class="student-brand">
            <div class="student-brand-logo"><img src="<?= ICONS_URL ?>/GenDeJesusFavicon.png" alt="GJC Logo"></div>
            <div class="student-brand-text"><h4>GenPay</h4><span>Student Portal</span></div>
        </div>
        <nav class="student-menu">
            <a href="<?= DASHBOARD_URL ?>"><img src="<?= ICONS_URL ?>/dashboard.png" class="student-nav-icon" alt=""><span class="student-nav-text">Dashboard</span></a>
            <a href="<?= STUDENT_URL ?>/scan.php"><img src="<?= ICONS_URL ?>/qr.png" class="student-nav-icon" alt=""><span class="student-nav-text">Scan &amp; Pay</span></a>
            <a href="<?= STUDENT_URL ?>/transfer.php" class="active"><img src="<?= ICONS_URL ?>/payment.png" class="student-nav-icon" alt=""><span class="student-nav-text">Transfer Tokens</span></a>
            <a href="<?= STUDENT_URL ?>/topup_request.php"><img src="<?= ICONS_URL ?>/topups.png" class="student-nav-icon" alt=""><span class="student-nav-text">Top-Up</span></a>
            <a href="<?= STUDENT_URL ?>/history.php"><img src="<?= ICONS_URL ?>/transactions.png" class="student-nav-icon" alt=""><span class="student-nav-text">History</span></a>
            <a href="<?= STUDENT_URL ?>/profile.php"><img src="<?= ICONS_URL ?>/users.png" class="student-nav-icon" alt=""><span class="student-nav-text">Profile</span></a>
        </nav>
        <a href="<?= BASE_URL ?>/logout.php" class="student-logout">
            <img src="<?= ICONS_URL ?>/logout.png" class="student-logout-icon" alt=""><span>Logout</span>
        </a>
    </aside>

    <main class="student-main">
        <header class="student-topbar">
            <button class="student-menu-btn" onclick="toggleStudentSidebar()">&#9776;</button>
            <div><h1>Transfer Tokens</h1><p>Send EduCoins instantly to another student.</p></div>
            <div class="student-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="student-avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></div>
            </div>
        </header>

        <section class="student-wallet-grid mb-4">
            <!-- Wallet Balance Card -->
            <div class="student-wallet-card">
                <div>
                    <span>Available Balance</span>
                    <h2><?= gjc_money($balance) ?></h2>
                    <p style="margin-top:4px;opacity:.8"><?= gjc_token_display($balance) ?> EduCoins</p>
                    <p style="font-size:12px;opacity:.65;margin:0"><?= gjc_e($currentUser['name']) ?></p>
                </div>
                <div class="student-wallet-badge">Student</div>
            </div>

            <!-- Daily Limit Panel -->
            <div class="student-quick-panel">
                <h3>Daily Transfer Limit</h3>
                <p>Maximum ₱5,000 per day. Resets at midnight.</p>
                <div style="margin:12px 0 6px">
                    <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:600;margin-bottom:6px">
                        <span>Sent today: <?= gjc_money($dailySent) ?></span>
                        <span>Remaining: <?= gjc_money($dailyRemaining) ?></span>
                    </div>
                    <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden">
                        <div style="height:100%;width:<?= $dailyPct ?>%;background:<?= $dailyPct >= 90 ? '#ef4444' : '#10b981' ?>;border-radius:4px;transition:width .5s"></div>
                    </div>
                    <small style="color:#64748b;font-size:11px"><?= $dailyPct ?>% of daily limit used</small>
                </div>
            </div>
        </section>

        <section class="student-premium-panel">
            <div class="student-panel-header">
                <div><h3>Send EduCoins</h3><p>Transfer tokens to another enrolled student using their Student ID.</p></div>
            </div>

            <div style="max-width:520px;margin:0 auto;padding:8px 0 24px">
                <form id="transferForm">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Recipient Student ID</label>
                        <input type="text" class="form-control form-control-lg" id="recipientStudentId"
                            name="recipient_student_id" required
                            placeholder="e.g. 2024-00123"
                            autocomplete="off">
                        <div id="recipientPreview" style="margin-top:8px;font-size:13px;color:#10b981;font-weight:600;"></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Amount (₱)</label>
                        <input type="number" class="form-control form-control-lg" id="transferAmount"
                            name="amount" min="1" max="<?= min($balance, $dailyRemaining) ?>"
                            step="0.01" required placeholder="Enter amount in PHP">
                        <div id="tokenPreview" style="margin-top:6px;font-size:13px;color:#64748b;"></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Message (optional)</label>
                        <input type="text" class="form-control" name="message" maxlength="255"
                            placeholder="e.g. For lunch, Happy birthday!">
                    </div>

                    <div id="transferMsg" class="mb-3"></div>

                    <button type="submit" class="login-btn" style="width:100%;font-size:16px;padding:14px" id="transferBtn">
                        Send Tokens
                    </button>
                </form>

                <div class="student-empty-state" style="padding:20px 0 0;text-align:center;">
                    <p style="font-size:12px;color:#9ca3af;margin:0">&#8377;10 = 1 EduCoin &bull; Transfers are instant and irreversible &bull; Daily limit: &#8377;5,000</p>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
function toggleStudentSidebar() {
    document.getElementById('studentSidebar').classList.toggle('collapsed');
}

// Live token preview
document.getElementById('transferAmount').addEventListener('input', function() {
    const php = parseFloat(this.value) || 0;
    const tokens = (php / 10).toFixed(1);
    document.getElementById('tokenPreview').textContent =
        php > 0 ? `= ${tokens} EduCoins` : '';
});

// Recipient lookup on blur
document.getElementById('recipientStudentId').addEventListener('blur', async function() {
    const sid = this.value.trim();
    if (!sid) return;
    const f = new FormData();
    f.append('action', 'lookup');
    f.append('student_id', sid);
    try {
        const r = await fetch('<?= STUDENT_URL ?>/api/transfer.php', { method:'POST', body:f });
        const d = await r.json();
        const preview = document.getElementById('recipientPreview');
        if (d.success && d.name) {
            preview.textContent = ' Found: ' + d.name;
            preview.style.color = '#10b981';
        } else {
            preview.textContent = ' Student not found';
            preview.style.color = '#ef4444';
        }
    } catch(e) {}
});

document.getElementById('transferForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('transferBtn');
    btn.disabled = true; btn.textContent = 'Processing...';
    const msg = document.getElementById('transferMsg');
    msg.innerHTML = '';

    try {
        const f = new FormData(this);
        f.append('action', 'transfer');
        const r = await fetch('<?= STUDENT_URL ?>/api/transfer.php', { method:'POST', body:f });
        const d = await r.json();
        if (d.success) {
            msg.innerHTML = `<div class="alert alert-success">
                <strong>&#10003; Transfer Successful!</strong><br>
                Ref: ${d.reference} &bull; ${d.message}
            </div>`;
            this.reset();
            document.getElementById('recipientPreview').textContent = '';
            document.getElementById('tokenPreview').textContent = '';
            setTimeout(() => location.reload(), 2500);
        } else {
            msg.innerHTML = `<div class="alert alert-danger">${d.message}</div>`;
        }
    } catch(e) {
        msg.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
    } finally {
        btn.disabled = false; btn.textContent = 'Send Tokens';
    }
});
</script>
</body>
</html>
