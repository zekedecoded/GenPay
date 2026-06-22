<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['merchant']);

$currentUser = gjc_current_user($db);
$ownerMerchId = gjc_merchant_owner_id($db, (int) $currentUser['id']);
$wallet = gjc_merchant_wallet($db, $ownerMerchId);
$currentBalance = $wallet['balance'];

$transactions = [];
$completedCount = 0;
$refundedCount = 0;

if ($wallet['id'] > 0 && gjc_table_exists($db, 'transactions')) {
    $stmt = $db->prepare(
        "SELECT id, reference_no, transaction_type, amount, status, notes, created_at
           FROM transactions
          WHERE merchant_wallet_id = ?
          ORDER BY created_at DESC"
    );
    $stmt->execute([$wallet['id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($transactions as $t) {
        if ($t['status'] === 'completed') {
            $completedCount++;
        } elseif ($t['status'] === 'reversed') {
            $refundedCount++;
        }
    }
}

$currentPage = 'history';

$returnReasons = ['Defective item', 'Wrong item given', 'Customer cancelled', 'Other'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=16">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="merchant-layout">

        <?php require __DIR__ . '/../includes/partials/' . (gjc_is_merchant_staff() ? 'sidebar_merchant_staff.php' : 'sidebar_merchant_admin.php'); ?>

        <main class="merchant-main">

            <header class="merchant-topbar">
                <button class="merchant-menu-btn" onclick="toggleMerchantSidebar()">Menu</button>

                <div>
                    <h1>Sales History</h1>
                    <p>View all completed payments and merchant wallet transactions.</p>
                </div>

                <div class="merchant-user">
                    <span><?= gjc_e($currentUser['name']) ?></span>
                    <div class="merchant-avatar">
                        <i class="fa-solid fa-store"></i>
                    </div>
                </div>
            </header>

            <section class="history-summary-grid mb-4">

                <div class="history-balance-card">
                    <div>
                        <span>Current Balance</span>
                        <h2><?php echo gjc_money($currentBalance); ?></h2>
                        <p>Available merchant wallet balance</p>
                    </div>

                    <div class="history-balance-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>

                <div class="history-mini-card">
                    <span>Total Records</span>
                    <h3><?php echo count($transactions); ?></h3>
                    <p>All sales transactions</p>
                </div>

                <div class="history-mini-card">
                    <span>Completed</span>
                    <h3><?php echo $completedCount; ?></h3>
                    <p>Successful payments</p>
                </div>

                <div class="history-mini-card">
                    <span>Refunded</span>
                    <h3><?php echo $refundedCount; ?></h3>
                    <p>Returns issued</p>
                </div>

            </section>

            <section class="merchant-premium-panel">

                <div class="merchant-panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3>All Sales &amp; Transactions</h3>
                        <p>Complete list of payments received by your merchant account.</p>
                    </div>

                    <span class="history-balance-pill">
                        Balance: <?php echo gjc_money($currentBalance); ?>
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table merchant-premium-table align-middle js-datatable" id="merchantHistoryTable" data-page-length="10">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date &amp; Time</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>

                        <tbody id="historyTableBody">
                            <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">No transactions yet.</td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($transactions as $t): ?>
                            <tr data-txn-id="<?= (int) $t['id'] ?>">
                                <td><?php echo gjc_e($t['reference_no']); ?></td>
                                <td><?php echo gjc_e($t['notes'] ?: ucwords(str_replace('_', ' ', $t['transaction_type']))); ?></td>
                                <td class="merchant-amount">
                                    <?php echo $t['transaction_type'] === 'refund' ? '-' : '+'; ?><?php echo gjc_money($t['amount']); ?>
                                </td>
                                <td><span class="merchant-type-pill"><?php echo gjc_e(ucwords(str_replace('_', ' ', $t['transaction_type']))); ?></span></td>
                                <td class="txn-status-cell">
                                    <?php if ($t['status'] === 'reversed'): ?>
                                        <span class="badge bg-secondary">Refunded</span>
                                    <?php elseif ($t['status'] === 'completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= gjc_e(ucfirst($t['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo gjc_e(date('M d, Y h:i A', strtotime($t['created_at']))); ?></td>
                                <td class="text-end txn-action-cell">
                                    <?php if ($t['status'] === 'completed' && $t['transaction_type'] === 'payment'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="openReturnModal(<?= (int) $t['id'] ?>, '<?= gjc_e($t['reference_no']) ?>')">
                                        Issue Return
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </section>

        </main>

    </div>

    <div class="modal fade" id="returnModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content custom-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Issue Return</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Reversing transaction <strong id="returnTxnRef">--</strong>. This refunds the student's wallet and debits yours &mdash; the original sale record is kept, only its status changes.</p>
                    <form id="returnForm">
                        <input type="hidden" id="returnTxnId" value="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason</label>
                            <select class="form-select" id="returnReason" required>
                                <?php foreach ($returnReasons as $reason): ?>
                                <option value="<?= gjc_e($reason) ?>"><?= gjc_e($reason) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Notes (optional)</label>
                            <textarea class="form-control" id="returnNotes" rows="3" placeholder="Any extra detail for the audit log..."></textarea>
                        </div>
                        <div id="returnMsg"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmReturnBtn">Confirm Return</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?= JS_URL ?>/admin_datatables.js"></script>

    <script>
    function toggleMerchantSidebar() {
        document.getElementById("merchantSidebar").classList.toggle("collapsed");
    }

    const RETURNS_API = '<?= MERCHANT_URL ?>/api/returns.php';
    const returnModalEl = document.getElementById('returnModal');
    const returnModal = bootstrap.Modal.getOrCreateInstance(returnModalEl);

    function openReturnModal(txnId, refNo) {
        document.getElementById('returnTxnId').value = txnId;
        document.getElementById('returnTxnRef').textContent = refNo;
        document.getElementById('returnMsg').innerHTML = '';
        document.getElementById('returnNotes').value = '';
        returnModal.show();
    }

    document.getElementById('confirmReturnBtn').addEventListener('click', async function () {
        const btn = this;
        const txnId = document.getElementById('returnTxnId').value;
        const reason = document.getElementById('returnReason').value;
        const notes = document.getElementById('returnNotes').value;
        const msg = document.getElementById('returnMsg');

        btn.disabled = true;
        btn.textContent = 'Processing...';

        try {
            const form = new FormData();
            form.append('action', 'issue_return');
            form.append('transaction_id', txnId);
            form.append('reason', reason);
            form.append('notes', notes);

            const res = await fetch(RETURNS_API, { method: 'POST', body: form });
            const data = await res.json();

            if (!data.success) {
                msg.innerHTML = `<div class="alert alert-danger mb-0">${data.message}</div>`;
                btn.disabled = false;
                btn.textContent = 'Confirm Return';
                return;
            }

            msg.innerHTML = `<div class="alert alert-success mb-0">Return completed. Reference: ${data.reference}. Reloading...</div>`;
            setTimeout(() => location.reload(), 1300);
        } catch (error) {
            msg.innerHTML = '<div class="alert alert-danger mb-0">Unable to reach the server. Please try again.</div>';
            btn.disabled = false;
            btn.textContent = 'Confirm Return';
        }
    });
    </script>

</body>

</html>
