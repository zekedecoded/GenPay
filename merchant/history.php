<?php
require_once __DIR__ . '/../connection/config.php';
$currentBalance = 165;

$transactions = [
    [
        "reference" => "TXN-20260408-2E23E",
        "description" => "Socks",
        "amount" => 100,
        "type" => "Payment",
        "status" => "Completed",
        "datetime" => "Apr 08, 2026 01:27 AM"
    ],
    [
        "reference" => "TXN-20260408-2DA65",
        "description" => "Matcha Donut",
        "amount" => 65,
        "type" => "Payment",
        "status" => "Completed",
        "datetime" => "Apr 07, 2026 11:10 PM"
    ]
];

$currentPage = 'history';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=13">
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
                    <span>Greg</span>
                    <div class="merchant-avatar">
                        <img src="<?= ICONS_URL ?>/store.png" alt="Merchant">
                    </div>
                </div>
            </header>

            <section class="history-summary-grid mb-4">

                <div class="history-balance-card">
                    <div>
                        <span>Current Balance</span>
                        <h2>₱<?php echo number_format($currentBalance, 2); ?></h2>
                        <p>Available merchant wallet balance</p>
                    </div>

                    <div class="history-balance-icon">
                        <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                    </div>
                </div>

                <div class="history-mini-card">
                    <span>Total Records</span>
                    <h3><?php echo count($transactions); ?></h3>
                    <p>All sales transactions</p>
                </div>

                <div class="history-mini-card">
                    <span>Completed</span>
                    <h3>2</h3>
                    <p>Successful payments</p>
                </div>

            </section>

            <section class="merchant-premium-panel">

                <div class="merchant-panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3>All Sales & Transactions</h3>
                        <p>Complete list of payments received by your merchant account.</p>
                    </div>

                    <span class="history-balance-pill">
                        Balance: ₱<?php echo number_format($currentBalance, 2); ?>
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
                                <th>Date & Time</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo $transaction["reference"]; ?></td>
                                <td><?php echo $transaction["description"]; ?></td>
                                <td class="merchant-amount">+₱<?php echo number_format($transaction["amount"], 2); ?>
                                </td>
                                <td><span class="merchant-type-pill"><?php echo $transaction["type"]; ?></span></td>
                                <td><span class="history-status-pill"><?php echo $transaction["status"]; ?></span></td>
                                <td><?php echo $transaction["datetime"]; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </section>

        </main>

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
    </script>

</body>

</html>
