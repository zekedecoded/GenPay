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

// Colors match the student side's own transaction-type mapping exactly
// (assets/css/student_dashboard.css .sd-txn--*) so a parent viewing this
// ledger sees the same type-per-color coding their child sees on their
// own dashboard/history pages.
$typeLabels = [
    'cash_in'       => ['label' => 'Top-Up',   'icon' => 'fa-circle-plus',    'color' => '#15803d', 'bg' => '#dcf3e4'],
    'payment'       => ['label' => 'POS',       'icon' => 'fa-store',          'color' => '#b45309', 'bg' => '#fdf1d8'],
    'p2p_transfer'  => ['label' => 'Transfer',  'icon' => 'fa-money-bill-transfer', 'color' => '#2563eb', 'bg' => '#e3edfd'],
    'voucher_payment' => ['label' => 'Voucher', 'icon' => 'fa-ticket',         'color' => '#7c3aed', 'bg' => '#efe7fb'],
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
    <link rel="stylesheet" href="<?= CSS_URL ?>/parent_shell.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/parent_student.css?v=3">
</head>
<body>
<div class="parent-layout">

    <?php require __DIR__ . '/../includes/partials/sidebar_parent.php'; ?>

    <main class="parent-main">

        <?php
        $topbarTitle = 'Transaction Ledger';
        $topbarSubtitle = 'Read-only view of ' . htmlspecialchars($studentName) . "'s wallet activity.";
        require __DIR__ . '/../includes/partials/topbar_parent.php';
        ?>

        <div class="parent-content">

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
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="num">Amount</th>
                            <th class="num">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($txnsWithBalance as $t):
                            $isCredit = $t['transaction_type'] === 'cash_in';
                            $meta = $typeLabels[$t['transaction_type']] ?? ['label' => ucfirst($t['transaction_type']), 'icon' => 'fa-circle', 'color' => '#5b6b61', 'bg' => '#eef1ef'];
                        ?>
                        <tr class="txn-row">
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($t['created_at']))) ?><br>
                                <small class="txn-time"><?= htmlspecialchars(date('g:i A', strtotime($t['created_at']))) ?></small>
                            </td>
                            <td>
                                <span class="type-chip" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>">
                                    <i class="fa-solid <?= $meta['icon'] ?>"></i>
                                    <?= $meta['label'] ?>
                                </span>
                            </td>
                            <td class="txn-desc">
                                <?= htmlspecialchars($t['notes'] ?? $t['reference_no']) ?>
                            </td>
                            <td class="txn-amount <?= $isCredit ? 'credit' : 'debit' ?>">
                                <?= $isCredit ? '+' : '−' ?>&#8369;<?= number_format((float)$t['amount'], 2) ?>
                            </td>
                            <td class="txn-balance">
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
