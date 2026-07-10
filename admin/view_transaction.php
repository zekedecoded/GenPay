<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);

$source = trim((string) ($_GET['source'] ?? 'ledger'));
$ref = trim((string) ($_GET['ref'] ?? ''));
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$transaction = gjc_find_admin_transaction($db, $source, $ref, $id ?: null);

if (!$transaction) {
    http_response_code(404);
}

$meta = $transaction['meta'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <title>Transaction Details | GenPay</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=12">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
</head>

<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-1" style="font-size: 28px;">Transaction Details</h1>
                <p class="text-muted mb-0">Review the selected wallet movement or request record.</p>
            </div>
            <a href="<?= ADMIN_URL ?>/transactions.php" class="btn btn-outline-secondary">Back to Transactions</a>
        </div>

        <?php if (!$transaction): ?>
        <div class="alert alert-warning">Transaction record not found.</div>
        <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <small class="text-muted d-block">Reference</small>
                            <strong><?php echo gjc_e($transaction['ref']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Type</small>
                            <strong><?php echo gjc_e($transaction['type_label']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Amount</small>
                            <strong><?php echo gjc_money($transaction['amount']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Status</small>
                            <strong><?php echo gjc_e($transaction['status_label']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Recorded At</small>
                            <strong><?php echo gjc_e($transaction['time_label']); ?></strong>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <small class="text-muted d-block">Sender</small>
                            <strong><?php echo gjc_e($transaction['sender']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Receiver</small>
                            <strong><?php echo gjc_e($transaction['receiver']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Source</small>
                            <strong><?php echo gjc_e(ucwords(str_replace('_', ' ', $transaction['source']))); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Notes</small>
                            <strong><?php echo gjc_e($transaction['notes'] !== '' ? $transaction['notes'] : 'None'); ?></strong>
                        </div>
                    </div>
                </div>

                <?php if (!empty($meta)): ?>
                <hr class="my-4">
                <h2 style="font-size: 18px;" class="mb-3">Raw Record</h2>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="transactionRawRecordTable">
                        <tbody>
                            <?php foreach ($meta as $key => $value): ?>
                            <tr>
                                <th style="width: 220px;"><?php echo gjc_e(ucwords(str_replace('_', ' ', (string) $key))); ?></th>
                                <td><?php echo gjc_e(is_scalar($value) || $value === null ? (string) $value : json_encode($value)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/../includes/partials/datatables_assets.php'; ?>
</body>

</html>
