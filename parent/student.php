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

// Get parent record
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

// Verify this parent is linked to this student
$linkChk = $db->prepare(
    "SELECT 1 FROM parent_student_links WHERE parent_id = ? AND student_user_id = ?"
);
$linkChk->execute([$parentId, $targetUid]);
if (!$linkChk->fetch()) {
    header('Location: ' . PARENT_URL . '/dashboard.php?link_error=Access+denied.');
    exit;
}

// Student details
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

$walletId    = (int) ($student['wallet_id'] ?? 0);
$studentName = trim($student['first_name'] . ' ' . $student['last_name']);

// Transaction type filter
$filterType = trim($_GET['type'] ?? 'all');
$allowedTypes = ['all', 'cash_in', 'payment', 'p2p_transfer'];
if (!in_array($filterType, $allowedTypes, true)) $filterType = 'all';

// Fetch transactions
$txns = [];
if ($walletId && gjc_table_exists($db, 'transactions')) {
    $typeWhere = ($filterType !== 'all') ? "AND transaction_type = '{$filterType}'" : '';
    $tStmt = $db->prepare(
        "SELECT reference_no, transaction_type, amount, notes, created_at, status
           FROM transactions
          WHERE student_wallet_id = ? {$typeWhere}
          ORDER BY created_at DESC
          LIMIT 100"
    );
    $tStmt->execute([$walletId]);
    $txns = $tStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Compute running balance (most-recent first → reverse for running balance)
$runningBalance = (float) $student['balance'];
$txnsWithBalance = [];
foreach ($txns as $t) {
    $txnsWithBalance[] = array_merge($t, ['running_balance' => $runningBalance]);
    if ($t['transaction_type'] === 'cash_in') {
        $runningBalance -= (float) $t['amount'];
    } else {
        $runningBalance += (float) $t['amount'];
    }
}

$typeLabels = [
    'cash_in'       => ['label' => 'Top-Up',   'icon' => 'fa-circle-plus',    'color' => '#15803d', 'bg' => '#f0fdf4'],
    'payment'       => ['label' => 'POS',       'icon' => 'fa-store',          'color' => '#0369a1', 'bg' => '#f0f9ff'],
    'p2p_transfer'  => ['label' => 'Transfer',  'icon' => 'fa-money-bill-transfer', 'color' => '#7c3aed', 'bg' => '#f5f3ff'],
    'voucher_payment' => ['label' => 'Voucher', 'icon' => 'fa-ticket',         'color' => '#b45309', 'bg' => '#fffbeb'],
];

$currentPage = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($studentName) ?> — Ledger | GenPay</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .ledger-header { background: linear-gradient(135deg, #064420, #0b5c2c); border-radius: 14px; padding: 24px 28px; color: #fff; margin-bottom: 22px; }
        .ledger-header h2 { font-weight: 800; font-size: 20px; margin: 0 0 4px; }
        .ledger-header .balance-big { font-size: 32px; font-weight: 900; letter-spacing: -1px; }
        .ledger-header small { opacity: .75; font-size: 13px; }
        .status-chips { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .status-chip { background: rgba(255,255,255,.15); border-radius: 20px; padding: 4px 14px; font-size: 12px; font-weight: 600; }
        .status-chip.frozen { background: rgba(239,68,68,.25); }
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .filter-tab { background: #f8fafc; border: 1.5px solid #e2e8f0; color: #475569; border-radius: 20px; padding: 5px 16px; font-size: 13px; font-weight: 600; text-decoration: none; }
        .filter-tab.active, .filter-tab:hover { background: #0b5c2c; border-color: #0b5c2c; color: #fff; }
        .txn-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
        .txn-row td { background: #fff; padding: 13px 16px; font-size: 13px; }
        .txn-row td:first-child { border-radius: 10px 0 0 10px; }
        .txn-row td:last-child  { border-radius: 0 10px 10px 0; }
        .txn-row:hover td { background: #f8fafc; }
        .type-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .txn-amount.credit { color: #15803d; font-weight: 700; }
        .txn-amount.debit  { color: #b91c1c; font-weight: 700; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #0b5c2c; font-size: 13px; font-weight: 600; text-decoration: none; margin-bottom: 18px; }
        .back-link:hover { text-decoration: underline; }
        .empty-state { text-align: center; padding: 40px 16px; color: #94a3b8; }
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
                <h1>Transaction Ledger</h1>
                <p>Read-only view of <?= htmlspecialchars($studentName) ?>'s wallet activity.</p>
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

        <div style="padding: 24px 28px; max-width: 900px;">

            <a href="<?= PARENT_URL ?>/dashboard.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>

            <!-- Student wallet summary -->
            <div class="ledger-header">
                <h2><?= htmlspecialchars($studentName) ?></h2>
                <small><?= htmlspecialchars($student['studentID'] ?? 'N/A') ?></small>
                <div class="balance-big">&#8369;<?= number_format((float)$student['balance'], 2) ?></div>
                <small>Current Balance</small>
                <div class="status-chips">
                    <?php if ($student['is_frozen']): ?>
                    <span class="status-chip frozen"><i class="fa-solid fa-lock me-1"></i>Wallet Frozen</span>
                    <?php endif; ?>
                    <?php if ((float)$student['daily_spend_limit'] > 0): ?>
                    <span class="status-chip"><i class="fa-solid fa-gauge me-1"></i>Daily Limit: &#8369;<?= number_format((float)$student['daily_spend_limit'], 2) ?></span>
                    <?php endif; ?>
                    <span class="status-chip"><i class="fa-solid fa-sliders me-1"></i><a href="<?= PARENT_URL ?>/controls.php?uid=<?= $targetUid ?>" style="color:inherit;text-decoration:none;">Manage Controls</a></span>
                </div>
            </div>

            <!-- Filter tabs -->
            <?php
            $tabs = [
                'all'          => 'All Transactions',
                'cash_in'      => 'Top-Ups',
                'payment'      => 'POS Purchases',
                'p2p_transfer' => 'Transfers',
            ];
            ?>
            <div class="filter-tabs">
                <?php foreach ($tabs as $key => $label): ?>
                <a href="?uid=<?= $targetUid ?>&type=<?= $key ?>" class="filter-tab <?= $filterType === $key ? 'active' : '' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Transaction table -->
            <?php if (empty($txnsWithBalance)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-receipt" style="font-size:36px;margin-bottom:10px;"></i>
                <p>No transactions found<?= $filterType !== 'all' ? ' for this filter' : '' ?>.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="txn-table">
                    <thead>
                        <tr style="color:#94a3b8;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">
                            <th style="padding:6px 16px;">Date &amp; Time</th>
                            <th style="padding:6px 16px;">Type</th>
                            <th style="padding:6px 16px;">Description</th>
                            <th style="padding:6px 16px;text-align:right;">Amount</th>
                            <th style="padding:6px 16px;text-align:right;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($txnsWithBalance as $t):
                            $isCredit = $t['transaction_type'] === 'cash_in';
                            $meta = $typeLabels[$t['transaction_type']] ?? ['label' => ucfirst($t['transaction_type']), 'icon' => 'fa-circle', 'color' => '#475569', 'bg' => '#f8fafc'];
                        ?>
                        <tr class="txn-row">
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($t['created_at']))) ?><br>
                                <small style="color:#94a3b8;"><?= htmlspecialchars(date('g:i A', strtotime($t['created_at']))) ?></small>
                            </td>
                            <td>
                                <span class="type-chip" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>">
                                    <i class="fa-solid <?= $meta['icon'] ?>"></i>
                                    <?= $meta['label'] ?>
                                </span>
                            </td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#475569;">
                                <?= htmlspecialchars($t['notes'] ?? $t['reference_no']) ?>
                            </td>
                            <td class="txn-amount <?= $isCredit ? 'credit' : 'debit' ?>" style="text-align:right;">
                                <?= $isCredit ? '+' : '−' ?>&#8369;<?= number_format((float)$t['amount'], 2) ?>
                            </td>
                            <td style="text-align:right;font-weight:700;color:#1e293b;">
                                &#8369;<?= number_format($t['running_balance'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
function toggleParentSidebar() {
    document.getElementById('parentSidebar').classList.toggle('collapsed');
}
</script>
</body>
</html>
