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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History | EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=13">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="merchant-layout">

        <aside class="merchant-sidebar" id="merchantSidebar">

            <div class="merchant-brand">
                <div class="merchant-brand-logo">
                    <img src="<?= ICONS_URL ?>/GenDeJesusFavicon.png" alt="GJC Logo">
                </div>

                <div class="merchant-brand-text">
                    <h4>GJC EduPay</h4>
                    <span>Merchant Portal</span>
                </div>
            </div>

            <nav class="merchant-menu">
                <a href="<?= MERCHANT_URL ?>/dashboard.php">
                    <img src="<?= ICONS_URL ?>/dashboard.png" class="merchant-nav-icon" alt="">
                    <span class="merchant-nav-text">Dashboard</span>
                </a>

                <a href="<?= MERCHANT_URL ?>/qrcode.php">
                    <img src="<?= ICONS_URL ?>/qr.png" class="merchant-nav-icon" alt="">
                    <span class="merchant-nav-text">Generate QR</span>
                </a>

                <a href="<?= MERCHANT_URL ?>/qr_scanner.php">
                    <img src="<?= ICONS_URL ?>/visitors.png" class="merchant-nav-icon" alt="">
                    <span class="merchant-nav-text">Scan Voucher</span>
                </a>

                <a href="<?= MERCHANT_URL ?>/encash.php">
                    <img src="<?= ICONS_URL ?>/encashments.png" class="merchant-nav-icon" alt="">
                    <span class="merchant-nav-text">Encash</span>
                </a>

                <a href="<?= MERCHANT_URL ?>/history.php" class="active">
                    <img src="<?= ICONS_URL ?>/transactions.png" class="merchant-nav-icon" alt="">
                    <span class="merchant-nav-text">History</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="merchant-logout">
                <img src="<?= ICONS_URL ?>/logout.png" class="merchant-logout-icon" alt="">
                <span>Logout</span>
            </a>

        </aside>

        <main class="merchant-main">

            <header class="merchant-topbar">
                <button class="merchant-menu-btn" onclick="toggleMerchantSidebar()">☰</button>

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
