<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['student']);
gjc_ensure_operational_tables($db);

$currentUser = gjc_current_user($db);
$wallet = gjc_student_wallet($db, $currentUser['id']);
$studentName = $currentUser['name'];
$studentID = 'GJC-' . str_pad((string) $currentUser['id'], 5, '0', STR_PAD_LEFT);
$currentBalance = $wallet['balance'];
$notice = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $method = trim((string) ($_POST['payment_method'] ?? 'Cash at Cashier'));

    if (!$amount || $amount <= 0) {
        $error = 'Enter a valid top-up amount.';
    } elseif ($wallet['id'] <= 0) {
        $error = 'Your student wallet is not ready. Contact the finance office.';
    } else {
        $reference = gjc_reference('TOP');
        $stmt = $db->prepare(
            "INSERT INTO topup_requests
                (user_id, student_wallet_id, amount, payment_method, status, reference_no)
             VALUES (?, ?, ?, ?, 'pending', ?)"
        );
        $stmt->execute([$currentUser['id'], $wallet['id'], $amount, $method, $reference]);
        $notice = "Top-up request {$reference} was submitted for cashier approval.";
    }
}

$stmt = $db->prepare(
    "SELECT reference_no, amount, payment_method, status, created_at
       FROM topup_requests
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT 8"
);
$stmt->execute([$currentUser['id']]);
$recentTopups = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top-Up Wallet | EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=40">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="student-layout">

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
                <a href="<?= DASHBOARD_URL ?>">
                    <img src="<?= ICONS_URL ?>/dashboard.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">Dashboard</span>
                </a>

                <a href="<?= STUDENT_URL ?>/scan.php">
                    <img src="<?= ICONS_URL ?>/qr.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">Scan &amp; Pay</span>
                </a>

                <a href="<?= STUDENT_URL ?>/history.php">
                    <img src="<?= ICONS_URL ?>/transactions.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">History</span>
                </a>

                <a href="<?= STUDENT_URL ?>/profile.php">
                    <img src="<?= ICONS_URL ?>/users.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">Profile</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="student-logout">
                <img src="<?= ICONS_URL ?>/logout.png" class="student-logout-icon" alt="">
                <span>Logout</span>
            </a>

        </aside>

        <main class="student-main">

            <header class="student-topbar">
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">Menu</button>

                <div>
                    <h1>Top-Up Wallet</h1>
                    <p>Submit a request to add funds to your student wallet.</p>
                </div>

                <div class="student-user">
                    <span><?php echo $studentName; ?></span>
                    <div class="student-avatar">
                        <?php echo strtoupper(substr($studentName, 0, 1)); ?>
                    </div>
                </div>
            </header>

            <section class="topup-hero-card mb-4">
                <div>
                    <span>Current Balance</span>
                    <h2><?php echo gjc_money($currentBalance); ?></h2>
                    <p><?php echo gjc_e($studentName); ?> &middot; <?php echo gjc_e($studentID); ?></p>
                </div>

                <div class="topup-hero-badge">
                    Student Wallet
                </div>
            </section>

            <section class="topup-layout-grid mb-4">

                <div class="student-premium-panel topup-form-panel">
                    <div class="student-panel-header">
                        <div>
                            <h3>Request Top-Up</h3>
                            <p>Enter your desired amount and choose a payment method.</p>
                        </div>
                    </div>

                    <?php if ($notice): ?>
                    <div class="alert alert-success"><?php echo gjc_e($notice); ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo gjc_e($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="topup-form">

                        <div class="topup-field">
                            <label>Amount (&#8369;)</label>

                            <div class="topup-money-input">
                                <span>&#8369;</span>
                                <input type="number" name="amount" id="topupAmount" placeholder="0.00" min="1"
                                    step="0.01" required>
                            </div>
                        </div>

                        <div class="topup-quick-amounts">
                            <button type="button" onclick="setTopupAmount(100)">&#8369;100</button>
                            <button type="button" onclick="setTopupAmount(200)">&#8369;200</button>
                            <button type="button" onclick="setTopupAmount(500)">&#8369;500</button>
                            <button type="button" onclick="setTopupAmount(1000)">&#8369;1,000</button>
                            <button type="button" onclick="setTopupAmount(2000)">&#8369;2,000</button>
                        </div>

                        <div class="topup-field">
                            <label>Payment Method</label>

                            <div class="topup-method-grid">

                                <label class="topup-method-card selected">
                                    <input type="radio" name="payment_method" value="Cash at Cashier" checked>
                                    <div class="topup-method-icon">
                                        <img src="<?= ICONS_URL ?>/add_cash.png" alt="">
                                    </div>
                                    <div>
                                        <strong>Cash at Cashier</strong>
                                        <span>Bring cash to the Accountancy Office</span>
                                    </div>
                                </label>

                                <label class="topup-method-card">
                                    <input type="radio" name="payment_method" value="GCash">
                                    <div class="topup-method-icon">
                                        <img src="<?= ICONS_URL ?>/gcash.png" alt="">
                                    </div>
                                    <div>
                                        <strong>GCash</strong>
                                        <span>09XX Campus GCash number</span>
                                    </div>
                                </label>

                                <label class="topup-method-card">
                                    <input type="radio" name="payment_method" value="Maya">
                                    <div class="topup-method-icon">
                                        <img src="<?= ICONS_URL ?>/maya.png" alt="">
                                    </div>
                                    <div>
                                        <strong>Maya</strong>
                                        <span>Maya linked campus account</span>
                                    </div>
                                </label>

                            </div>
                        </div>

                        <button type="submit" class="topup-submit-btn">
                            Submit Top-Up Request
                        </button>

                    </form>
                </div>

                <div class="student-premium-panel topup-guide-panel">
                    <div class="student-panel-header">
                        <div>
                            <h3>Top-Up Guide</h3>
                            <p>How your wallet top-up request works.</p>
                        </div>
                    </div>

                    <div class="topup-guide-list">
                        <div>
                            <strong>1</strong>
                            <span>Enter the amount you want to add.</span>
                        </div>

                        <div>
                            <strong>2</strong>
                            <span>Select your preferred payment method.</span>
                        </div>

                        <div>
                            <strong>3</strong>
                            <span>Submit your request for cashier verification.</span>
                        </div>

                        <div>
                            <strong>4</strong>
                            <span>Your wallet balance updates after approval.</span>
                        </div>
                    </div>

                    <div class="topup-limit-card">
                        <span>Daily Top-Up Limit</span>
                        <strong>&#8369;5,000</strong>
                        <p>Requests above the limit may require manual approval.</p>
                    </div>
                </div>

            </section>

            <section class="student-premium-panel">

                <div class="student-panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Recent Top-Up Requests</h3>
                        <p>Track your latest wallet top-up submissions.</p>
                    </div>

                    <a href="history.php" class="student-view-btn">Full History</a>
                </div>

                <?php if (empty($recentTopups)): ?>
                <div class="student-empty-state">
                    <div class="student-empty-icon">
                        <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                    </div>
                    <h3>No top-up requests yet</h3>
                    <p>Submit your first top-up request to add funds to your wallet.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table student-premium-table align-middle js-datatable" id="studentTopupRequestsTable" data-page-length="8">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($recentTopups as $topup): ?>
                            <tr>
                                <td><?php echo gjc_e($topup["reference_no"]); ?></td>
                                <td><?php echo gjc_money($topup["amount"]); ?></td>
                                <td><span class="student-type-pill"><?php echo gjc_e($topup["payment_method"]); ?></span></td>
                                <td><span class="topup-status-pill"><?php echo gjc_e(ucfirst($topup["status"])); ?></span></td>
                                <td><?php echo gjc_e(date('M d, Y h:i A', strtotime($topup["created_at"]))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            </section>

        </main>

    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?= JS_URL ?>/admin_datatables.js"></script>

    <script>
    function toggleStudentSidebar() {
        document.getElementById("studentSidebar").classList.toggle("collapsed");
    }

    function setTopupAmount(amount) {
        document.getElementById("topupAmount").value = amount;
    }

    document.querySelectorAll(".topup-method-card").forEach(function(card) {
        card.addEventListener("click", function() {
            document.querySelectorAll(".topup-method-card").forEach(function(item) {
                item.classList.remove("selected");
            });

            card.classList.add("selected");
            card.querySelector("input").checked = true;
        });
    });
    </script>

</body>

</html>
