<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['parent']);
gjc_ensure_parent_schema($db);

$parentUserId    = gjc_user_id();
$currentUser     = gjc_current_user($db);
$profileImg      = (string) ($currentUser['raw']['profile_img'] ?? '');
$profilePhotoUrl = ($profileImg !== '') ? (BASE_URL . '/' . ltrim($profileImg, '/')) : '';

$pStmt = $db->prepare("SELECT id FROM parents WHERE user_id = ?");
$pStmt->execute([$parentUserId]);
$parentRow = $pStmt->fetch(PDO::FETCH_ASSOC);
if (!$parentRow) {
    header('Location: ' . PARENT_URL . '/dashboard.php');
    exit;
}
$parentId = (int) $parentRow['id'];

$targetUid = (int) ($_GET['uid'] ?? 0);
if (!$targetUid) {
    header('Location: ' . PARENT_URL . '/dashboard.php');
    exit;
}

$linkChk = $db->prepare("SELECT 1 FROM parent_student_links WHERE parent_id = ? AND student_user_id = ?");
$linkChk->execute([$parentId, $targetUid]);
if (!$linkChk->fetch()) {
    header('Location: ' . PARENT_URL . '/dashboard.php');
    exit;
}

$sStmt = $db->prepare(
    "SELECT u.first_name, u.last_name, si.studentID,
            sw.id AS wallet_id, sw.balance, sw.is_frozen, sw.daily_spend_limit
       FROM users u
       LEFT JOIN student_info si ON si.userID = u.userID
       LEFT JOIN student_wallets sw ON sw.user_id = u.userID
      WHERE u.userID = ?"
);
$sStmt->execute([$targetUid]);
$student = $sStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    header('Location: ' . PARENT_URL . '/dashboard.php');
    exit;
}

$studentName = trim($student['first_name'] . ' ' . $student['last_name']);
$currentPage = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Controls — <?= htmlspecialchars($studentName) ?> | GenPay</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .control-card { background: #fff; border-radius: 14px; border: 1.5px solid #e2e8f0; padding: 24px 28px; margin-bottom: 18px; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
        .control-card h5 { font-weight: 700; font-size: 15px; color: #0d1f14; margin: 0 0 6px; }
        .control-card p { font-size: 13px; color: #64748b; margin: 0 0 18px; }
        .control-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .toggle-wrap { display: flex; align-items: center; gap: 14px; }
        .toggle-label { font-size: 14px; font-weight: 600; color: #1e293b; }
        .toggle-sublabel { font-size: 12px; color: #64748b; display: block; }
        .toggle-switch { position: relative; width: 48px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 26px; cursor: pointer; transition: .2s; }
        .toggle-slider:before { content:''; position: absolute; height: 20px; width: 20px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .2s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
        input:checked + .toggle-slider { background: #b91c1c; }
        input:checked + .toggle-slider:before { transform: translateX(22px); }
        .limit-form { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
        .limit-form label { font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px; }
        .limit-form input { border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 15px; font-weight: 600; width: 160px; }
        .limit-form input:focus { border-color: #0b5c2c; outline: none; box-shadow: 0 0 0 3px rgba(11,92,44,.1); }
        .limit-form .btn-save { background: linear-gradient(135deg, #064420, #0b5c2c); color: #fff; border: none; border-radius: 8px; padding: 9px 20px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .limit-form .btn-save:hover { filter: brightness(1.12); }
        .unlink-btn { background: none; border: 1.5px solid #fca5a5; color: #b91c1c; border-radius: 8px; padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .unlink-btn:hover { background: #fef2f2; }
        .status-bar { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
        .stat-pill { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 10px 16px; font-size: 13px; }
        .stat-pill strong { display: block; font-size: 18px; font-weight: 800; color: #0d1f14; }
        .stat-pill span { color: #64748b; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #0b5c2c; font-size: 13px; font-weight: 600; text-decoration: none; margin-bottom: 18px; }
        .back-link:hover { text-decoration: underline; }
        .flash-msg { padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 14px; }
        .flash-msg.success { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
        .flash-msg.error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }
    </style>
</head>
<body>
<div class="student-layout">

    <?php require __DIR__ . '/../includes/partials/sidebar_parent.php'; ?>

    <main class="student-main">

        <header class="student-topbar">
            <button class="student-menu-btn" onclick="toggleParentSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h1>Wallet Controls</h1>
                <p>Spending controls for <?= htmlspecialchars($studentName) ?>'s wallet.</p>
            </div>
            <div class="student-user">
                <span><?= htmlspecialchars($currentUser['name']) ?></span>
                <div class="student-avatar" style="<?= $profilePhotoUrl ? 'padding:0;overflow:hidden;' : '' ?>">
                    <?php if ($profilePhotoUrl): ?>
                        <img src="<?= htmlspecialchars($profilePhotoUrl) ?>" alt=""
                             style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                    <?php else: ?>
                        <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div style="padding: 24px 28px; max-width: 680px;">

            <a href="<?= PARENT_URL ?>/dashboard.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>

            <div id="globalMsg"></div>

            <!-- Wallet stats -->
            <div class="status-bar">
                <div class="stat-pill">
                    <strong>&#8369;<?= number_format((float)$student['balance'], 2) ?></strong>
                    <span>Current Balance</span>
                </div>
                <div class="stat-pill">
                    <strong><?= htmlspecialchars($student['studentID'] ?? 'N/A') ?></strong>
                    <span>School ID</span>
                </div>
                <div class="stat-pill">
                    <strong id="statusPillText"><?= (int)$student['is_frozen'] ? 'Frozen' : 'Active' ?></strong>
                    <span>Wallet Status</span>
                </div>
            </div>

            <!-- Freeze toggle -->
            <div class="control-card">
                <h5><i class="fa-solid fa-lock me-2" style="color:#b91c1c"></i>Freeze Wallet</h5>
                <p>When frozen, the student cannot make any POS purchases or token transfers. Cash top-ups by Finance are still allowed.</p>
                <div class="control-row">
                    <div class="toggle-wrap">
                        <label class="toggle-switch">
                            <input type="checkbox" id="freezeToggle" <?= $student['is_frozen'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <div>
                            <span class="toggle-label" id="freezeLabel"><?= $student['is_frozen'] ? 'Wallet is Frozen' : 'Wallet is Active' ?></span>
                            <span class="toggle-sublabel" id="freezeSub"><?= $student['is_frozen'] ? 'Toggle off to unfreeze.' : 'Toggle on to freeze immediately.' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily spending limit -->
            <div class="control-card">
                <h5><i class="fa-solid fa-gauge me-2" style="color:#0b5c2c"></i>Daily Spending Limit</h5>
                <p>Limits how much the student can spend in a single day across POS purchases and token transfers. Set to ₱0.00 to disable the limit.</p>
                <form class="limit-form" id="limitForm">
                    <div>
                        <label for="limitInput">Daily limit (₱)</label>
                        <input type="number" id="limitInput" min="0" step="1" placeholder="0.00"
                               value="<?= number_format((float)$student['daily_spend_limit'], 2, '.', '') ?>">
                    </div>
                    <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk me-1"></i>Save Limit</button>
                </form>
                <p style="margin-top:10px;margin-bottom:0;font-size:12px;color:#94a3b8;">
                    Today's spending is tracked in real time. The limit resets at midnight.
                </p>
            </div>

            <!-- View ledger link -->
            <div class="control-card" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div>
                    <h5 style="margin-bottom:4px;"><i class="fa-solid fa-receipt me-2" style="color:#0b5c2c"></i>Transaction Ledger</h5>
                    <p style="margin:0;">View the full read-only transaction history for this wallet.</p>
                </div>
                <a href="<?= PARENT_URL ?>/student.php?uid=<?= $targetUid ?>" class="btn-save" style="text-decoration:none;padding:9px 18px;font-size:13px;border-radius:8px;display:inline-block;">
                    <i class="fa-solid fa-eye me-1"></i>View Ledger
                </a>
            </div>

            <!-- Unlink student -->
            <div class="control-card" style="border-color:#fca5a5;">
                <h5 style="color:#b91c1c;"><i class="fa-solid fa-user-minus me-2"></i>Unlink Student</h5>
                <p>Remove your link to this student. You will no longer be able to view their wallet or apply controls. <strong>Existing controls (freeze, daily limit) will remain set</strong> on the wallet until a Finance administrator changes them.</p>
                <button class="unlink-btn" onclick="unlinkStudent()"><i class="fa-solid fa-trash me-1"></i>Unlink <?= htmlspecialchars($student['first_name']) ?></button>
            </div>

        </div>
    </main>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
function toggleParentSidebar() {
    document.getElementById('parentSidebar').classList.toggle('collapsed');
}

function showMsg(text, type) {
    const el = document.getElementById('globalMsg');
    el.innerHTML = `<div class="flash-msg ${type}">${text}</div>`;
    setTimeout(() => { el.innerHTML = ''; }, 3000);
}

async function apiPost(action, extra) {
    const res = await fetch('<?= PARENT_URL ?>/api/controls.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action, student_user_id: <?= $targetUid ?>, ...extra})
    });
    return res.json();
}

document.getElementById('freezeToggle').addEventListener('change', async function() {
    const toggle = this;
    const frozen = toggle.checked ? 1 : 0;
    toggle.disabled = true;

    try {
        const data = await apiPost('set_frozen', {value: frozen});
        if (data.success) {
            document.getElementById('freezeLabel').textContent    = frozen ? 'Wallet is Frozen' : 'Wallet is Active';
            document.getElementById('freezeSub').textContent      = frozen ? 'Toggle off to unfreeze.' : 'Toggle on to freeze immediately.';
            document.getElementById('statusPillText').textContent = frozen ? 'Frozen' : 'Active';
            showMsg(frozen ? 'Wallet frozen successfully.' : 'Wallet unfrozen.', 'success');
        } else {
            toggle.checked = !toggle.checked;
            showMsg(data.error || 'Failed to update.', 'error');
        }
    } catch(err) {
        toggle.checked = !toggle.checked;
        showMsg('Request failed. Check your connection.', 'error');
    } finally {
        toggle.disabled = false;
    }
});

document.getElementById('limitForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn    = this.querySelector('button[type="submit"]');
    const amount = parseFloat(document.getElementById('limitInput').value) || 0;
    const orig   = btn.innerHTML;
    btn.disabled  = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Saving...';
    try {
        const data = await apiPost('set_daily_limit', {amount});
        showMsg(data.success ? 'Daily limit saved.' : (data.error || 'Failed.'), data.success ? 'success' : 'error');
    } catch(err) {
        showMsg('Request failed. Check your connection.', 'error');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = orig;
    }
});

async function unlinkStudent() {
    if (!confirm('Remove your link to <?= htmlspecialchars(addslashes($studentName)) ?>? Your controls remain on the wallet.')) return;
    const data = await apiPost('unlink_student', {});
    if (data.success) {
        window.location.href = '<?= PARENT_URL ?>/dashboard.php';
    } else {
        showMsg(data.error || 'Failed to unlink.', 'error');
    }
}
</script>
</body>
</html>
