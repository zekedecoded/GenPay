<?php
require_once __DIR__ . '/../connection/config.php';
$studentName = "Test Student";
$studentID = "2024-00001";

$currentBalance = 0;
$totalReceived = 0;
$totalSpent = 0;

$transactions = [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Transaction History | EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=20">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="student-layout">

        <!-- SIDEBAR -->
        <aside class="student-sidebar" id="studentSidebar">

            <div class="student-brand">
                <div class="student-brand-logo">
                    <img src="<?= ICONS_URL ?>/GenDeJesusFavicon.png" alt="GJC Logo">
                </div>

                <div class="student-brand-text">
                    <h4>GJC EduPay</h4>
                    <span>Student Portal</span>
                </div>
            </div>

            <nav class="student-menu">
                <a href="<?= STUDENT_URL ?>/dashboard.php">
                    <img src="<?= ICONS_URL ?>/dashboard.png" class="student-nav-icon">
                    <span class="student-nav-text">Dashboard</span>
                </a>

                <a href="<?= STUDENT_URL ?>/scan.php">
                    <img src="<?= ICONS_URL ?>/qr.png" class="student-nav-icon">
                    <span class="student-nav-text">Scan &amp; Pay</span>
                </a>

                <a href="<?= STUDENT_URL ?>/history.php" class="active">
                    <img src="<?= ICONS_URL ?>/transactions.png" class="student-nav-icon">
                    <span class="student-nav-text">History</span>
                </a>

                <a href="<?= STUDENT_URL ?>/profile.php">
                    <img src="<?= ICONS_URL ?>/users.png" class="student-nav-icon">
                    <span class="student-nav-text">Profile</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="student-logout">
                <img src="<?= ICONS_URL ?>/logout.png" class="student-logout-icon">
                <span>Logout</span>
            </a>

        </aside>

        <!-- MAIN -->
        <main class="student-main">

            <!-- HEADER -->
            <header class="student-topbar">
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">☰</button>

                <div>
                    <h1>Transaction History</h1>
                    <p>Track all your wallet activity and payments.</p>
                </div>

                <div class="student-user">
                    <span><?php echo $studentName; ?></span>
                    <div class="student-avatar">
                        <?php echo strtoupper(substr($studentName,0,1)); ?>
                    </div>
                </div>
            </header>

            <!-- STATS (ibang layout kaysa screenshot) -->
            <section class="student-history-stats mb-4">

                <div class="history-stat-card">
                    <span>Current Balance</span>
                    <h2>₱<?php echo number_format($currentBalance,2); ?></h2>
                </div>

                <div class="history-stat-card">
                    <span>Total Received</span>
                    <h2>₱<?php echo number_format($totalReceived,2); ?></h2>
                </div>

                <div class="history-stat-card">
                    <span>Total Spent</span>
                    <h2>₱<?php echo number_format($totalSpent,2); ?></h2>
                </div>

            </section>

            <!-- MAIN TABLE -->
            <section class="student-premium-panel">

                <div class="student-panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3>All Transactions</h3>
                        <p>Complete list of your wallet activity.</p>
                    </div>

                    <span class="student-count">
                        <?php echo count($transactions); ?> Records
                    </span>
                </div>

                <?php if(empty($transactions)): ?>
                <div class="student-empty-state">
                    <div class="student-empty-icon">
                        <img src="<?= ICONS_URL ?>/wallet.png">
                    </div>

                    <h3>No transactions yet</h3>
                    <p>Start using your wallet to see activity here.</p>
                </div>

                <?php else: ?>

                <div class="table-responsive">
                    <table class="table student-premium-table align-middle">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach($transactions as $t): ?>
                            <tr>
                                <td><?php echo $t['ref']; ?></td>
                                <td><?php echo $t['desc']; ?></td>
                                <td><span class="student-type-pill"><?php echo $t['type']; ?></span></td>
                                <td>₱<?php echo number_format($t['amount'],2); ?></td>
                                <td><span class="student-status"><?php echo $t['status']; ?></span></td>
                                <td><?php echo $t['date']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php endif; ?>

            </section>

        </main>

    </div>

    <script>
    function toggleStudentSidebar() {
        document.getElementById("studentSidebar").classList.toggle("collapsed");
    }
    </script>

</body>

</html>
