<?php
require_once __DIR__ . '/../../connection/config.php';

$pendingEncashments = 5;
$releasedToday = 12600;
$encashmentQueue = 4;

$pendingRequests = [
    ["reference"=>"ENC-001","merchant"=>"Campus Canteen","merchant_id"=>"MER-001","amount"=>2500,"method"=>"Cashier Release","time"=>"10:20 AM"],
    ["reference"=>"ENC-002","merchant"=>"School Supplies Store","merchant_id"=>"MER-002","amount"=>1800,"method"=>"Finance Office","time"=>"09:55 AM"],
    ["reference"=>"ENC-003","merchant"=>"Snack Booth","merchant_id"=>"MER-003","amount"=>950,"method"=>"Cashier Release","time"=>"09:15 AM"]
];

$encashmentHistory = [
    ["reference"=>"ENC-090","merchant"=>"Campus Canteen","amount"=>3000,"method"=>"Finance Office","status"=>"Released","time"=>"Yesterday"],
    ["reference"=>"ENC-089","merchant"=>"Snack Booth","amount"=>1200,"method"=>"Cashier Release","status"=>"Processing","time"=>"Yesterday"],
    ["reference"=>"ENC-088","merchant"=>"School Supplies Store","amount"=>800,"method"=>"Finance Office","status"=>"Rejected","time"=>"Apr 23"]
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Encashments | GJC EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/encashments.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="admin-layout">

        <aside class="admin-sidebar" id="sidebar">

            <div class="brand-box">
                <div class="brand-logo">
                    <img src="<?= ICONS_URL ?>/edupay.png" alt="Logo">
                </div>

                <div class="brand-text">
                    <h4>GJC EduPay</h4>
                    <span>Admin Portal</span>
                </div>
            </div>

            <nav class="sidebar-menu">
                <a href="<?= ADMIN_URL ?>/dashboard.php">
                    <img src="<?= ICONS_URL ?>/dashboard.png" class="nav-icon" alt="">
                    <span class="nav-text">Dashboard</span>
                </a>

                <a href="<?= ADMIN_URL ?>/users.php">
                    <img src="<?= ICONS_URL ?>/users.png" class="nav-icon" alt="">
                    <span class="nav-text">Users</span>
                </a>

                <a href="<?= ADMIN_URL ?>/topups.php">
                    <img src="<?= ICONS_URL ?>/topups.png" class="nav-icon" alt="">
                    <span class="nav-text">Top-ups</span>
                </a>

                <a href="<?= ADMIN_URL ?>/encashments.php" class="active">
                    <img src="<?= ICONS_URL ?>/encashments.png" class="nav-icon" alt="">
                    <span class="nav-text">Encashments</span>
                </a>

                <a href="<?= ADMIN_URL ?>/transactions.php">
                    <img src="<?= ICONS_URL ?>/transactions.png" class="nav-icon" alt="">
                    <span class="nav-text">Transactions</span>
                </a>

                <a href="<?= ADMIN_URL ?>/visitors.php">
                    <img src="<?= ICONS_URL ?>/visitors.png" class="nav-icon" alt="">
                    <span class="nav-text">Visitors</span>
                </a>

                <a href="<?= ADMIN_URL ?>/settings.php">
                    <img src="<?= ICONS_URL ?>/settings.png" class="nav-icon" alt="">
                    <span class="nav-text">Settings</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">
                <img src="<?= ICONS_URL ?>/logout.png" class="logout-icon" alt="">
                <span>Logout</span>
            </a>

        </aside>

        <main class="admin-main">

            <header class="topbar">
                <button class="menu-btn" onclick="toggleSidebar()">☰</button>

                <div>
                    <h1>Encashments</h1>
                    <p>Review merchant withdrawal requests, release funds, and monitor encashment history.</p>
                </div>

                <div class="admin-user">
                    <span>Admin</span>
                    <div class="avatar">
                        <img src="<?= ICONS_URL ?>/admin.png" alt="Admin">
                    </div>
                </div>
            </header>

            <section class="encash-stats-grid mb-4">

                <div class="encash-stat-card">
                    <div class="stat-icon-wrap">
                        <img src="<?= ICONS_URL ?>/pending-encashments.png" alt="">
                    </div>
                    <span>Pending Encashments</span>
                    <h2><?php echo $pendingEncashments; ?></h2>
                    <p>Awaiting disbursement</p>
                </div>

                <div class="encash-stat-card">
                    <div class="stat-icon-wrap">
                        <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                    </div>
                    <span>Released Today</span>
                    <h2>₱<?php echo number_format($releasedToday, 2); ?></h2>
                    <p>Total released amount</p>
                </div>

                <div class="encash-stat-card">
                    <div class="stat-icon-wrap">
                        <img src="<?= ICONS_URL ?>/encashments.png" alt="">
                    </div>
                    <span>Encashment Queue</span>
                    <h2><?php echo $encashmentQueue; ?></h2>
                    <p>Requests waiting in queue</p>
                </div>

            </section>

            <section class="encash-panel mb-4">

                <div class="encash-panel-header">
                    <div>
                        <h3>Pending Encashment Requests</h3>
                        <p>View, release, or reject merchant encashment requests.</p>
                    </div>

                    <a href="create_encashment.php" class="create-encash-btn">
                        <span>+</span> Create Encashment
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table encash-table align-middle">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Merchant Name</th>
                                <th>Merchant ID</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($pendingRequests as $request): ?>
                            <tr>
                                <td><?php echo $request["reference"]; ?></td>
                                <td>
                                    <div class="encash-user-cell">
                                        <div class="encash-avatar">
                                            <?php echo strtoupper(substr($request["merchant"], 0, 1)); ?>
                                        </div>
                                        <strong><?php echo $request["merchant"]; ?></strong>
                                    </div>
                                </td>
                                <td><?php echo $request["merchant_id"]; ?></td>
                                <td class="amount-text">₱<?php echo number_format($request["amount"], 2); ?></td>
                                <td><span class="method-pill"><?php echo $request["method"]; ?></span></td>
                                <td><?php echo $request["time"]; ?></td>
                                <td>
                                    <div class="encash-actions">
                                        <a href="view_encashment.php?ref=<?php echo $request["reference"]; ?>"
                                            class="details-btn">View</a>
                                        <a href="release_encashment.php?ref=<?php echo $request["reference"]; ?>"
                                            class="release-btn">Release</a>
                                        <a href="reject_encashment.php?ref=<?php echo $request["reference"]; ?>"
                                            class="reject-btn">Reject</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            </section>

            <section class="encash-panel">

                <div class="encash-panel-header">
                    <div>
                        <h3>Recent Encashment History</h3>
                        <p>Latest released, rejected, and processing merchant withdrawals.</p>
                    </div>

                    <a href="encashment_history.php" class="history-link">View All</a>
                </div>

                <div class="table-responsive">
                    <table class="table encash-table align-middle">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Merchant Name</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($encashmentHistory as $history): ?>
                            <tr>
                                <td><?php echo $history["reference"]; ?></td>
                                <td><?php echo $history["merchant"]; ?></td>
                                <td class="amount-text">₱<?php echo number_format($history["amount"], 2); ?></td>
                                <td><span class="method-pill"><?php echo $history["method"]; ?></span></td>
                                <td>
                                    <span class="encash-status <?php echo strtolower($history["status"]); ?>">
                                        <?php echo $history["status"]; ?>
                                    </span>
                                </td>
                                <td><?php echo $history["time"]; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            </section>

        </main>

    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>

    <script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }
    </script>

</body>

</html>
