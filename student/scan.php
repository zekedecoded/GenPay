<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['student']);

$currentUser = gjc_current_user($db);
$wallet = gjc_student_wallet($db, $currentUser['id']);
$studentName = $currentUser['name'];
$studentID = 'GJC-' . str_pad((string) $currentUser['id'], 5, '0', STR_PAD_LEFT);
$balance = $wallet['balance'];

$recentPayments = [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Scan & Pay | EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=11">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
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
                <a href="<?= STUDENT_URL ?>/dashboard.php">
                    <img src="<?= ICONS_URL ?>/dashboard.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">Dashboard</span>
                </a>

                <a href="<?= STUDENT_URL ?>/scan.php" class="active">
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
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">☰</button>

                <div>
                    <h1>Scan & Pay</h1>
                    <p>Scan a merchant QR code to pay using your student wallet.</p>
                </div>

                <div class="student-user">
                    <span><?php echo gjc_e($studentName); ?></span>
                    <div class="student-avatar">
                        <?php echo strtoupper(substr($studentName, 0, 1)); ?>
                    </div>
                </div>
            </header>

            <section class="scan-balance-card mb-4">
                <div>
                    <span>Current Balance</span>
                    <h2><?php echo gjc_money($balance); ?></h2>
                    <p><?php echo gjc_e($studentName); ?> &middot; <?php echo gjc_e($studentID); ?></p>
                </div>

                <div class="scan-balance-badge">
                    Student Wallet
                </div>
            </section>

            <section class="scan-layout-grid mb-4">

                <div class="student-premium-panel scan-camera-panel">
                    <div class="student-panel-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3>Scan Merchant QR</h3>
                            <p>Point your camera at the merchant’s QR code.</p>
                        </div>

                        <span class="scan-status-badge" id="cameraStatus">Starting Camera</span>
                    </div>

                    <div class="scan-camera-box">
                        <video id="qrVideo" autoplay playsinline></video>
                        <canvas id="qrCanvas" hidden></canvas>

                        <div class="scan-camera-message" id="cameraMessage">
                            Opening camera...
                        </div>
                    </div>

                    <div class="scan-result-box" id="scanResultBox">
                        <span>Scan Result</span>
                        <strong id="scanResultText">No QR detected yet.</strong>
                    </div>
                </div>

                <div class="student-premium-panel scan-guide-panel">
                    <div class="student-panel-header">
                        <div>
                            <h3>Payment Guide</h3>
                            <p>Follow these steps when paying a merchant.</p>
                        </div>
                    </div>

                    <div class="scan-guide-list">
                        <div>
                            <strong>1</strong>
                            <span>Ask the merchant to generate an item QR.</span>
                        </div>

                        <div>
                            <strong>2</strong>
                            <span>Allow camera access on your browser.</span>
                        </div>

                        <div>
                            <strong>3</strong>
                            <span>Scan the QR and review payment details.</span>
                        </div>

                        <div>
                            <strong>4</strong>
                            <span>Confirm the payment using your wallet balance.</span>
                        </div>
                    </div>

                    <div class="scan-note">
                        Camera scanning works on <strong>localhost</strong> or secure HTTPS pages.
                    </div>
                </div>

            </section>

            <section class="student-premium-panel">

                <div class="student-panel-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Recent Payments</h3>
                        <p>Your latest scan-and-pay transactions.</p>
                    </div>

                    <a href="history.php" class="student-view-btn">View All</a>
                </div>

                <?php if (empty($recentPayments)): ?>
                <div class="student-empty-state">
                    <div class="student-empty-icon">
                        <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                    </div>
                    <h3>No transactions yet</h3>
                    <p>Scan a merchant QR code to start paying with your wallet.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table student-premium-table align-middle">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Merchant</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td><?php echo $payment["description"]; ?></td>
                                <td><?php echo $payment["merchant"]; ?></td>
                                <td><?php echo gjc_money($payment["amount"]); ?></td>
                                <td><?php echo $payment["date"]; ?></td>
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

    <script>
    function toggleStudentSidebar() {
        document.getElementById("studentSidebar").classList.toggle("collapsed");
    }

    const video = document.getElementById("qrVideo");
    const canvas = document.getElementById("qrCanvas");
    const canvasContext = canvas.getContext("2d");
    const cameraMessage = document.getElementById("cameraMessage");
    const cameraStatus = document.getElementById("cameraStatus");
    const scanResultText = document.getElementById("scanResultText");

    async function startScanner() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: "environment"
                }
            });

            video.srcObject = stream;
            cameraMessage.style.display = "none";
            cameraStatus.textContent = "Camera Active";
            cameraStatus.classList.add("active");

            requestAnimationFrame(scanQRCode);
        } catch (error) {
            cameraStatus.textContent = "Camera Blocked";
            cameraStatus.classList.add("blocked");
            cameraMessage.innerHTML = "Camera access denied.<br>Please allow camera permissions.";
        }
    }

    function scanQRCode() {
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            canvasContext.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = canvasContext.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "dontInvert",
            });

            if (code) {
                try {
                    const data = JSON.parse(code.data);
                    if (data.type === 'payment') {
                        scanResultText.innerHTML = `
                            <div style="color: #0b5c2c; font-size: 15px; margin-top: 10px;">
                                <strong>Merchant:</strong> ${data.merchant}<br>
                                <strong>Item:</strong> ${data.desc}<br>
                                <strong>Price:</strong> &#8369;${parseFloat(data.price).toFixed(2)}
                            </div>
                            <button class="btn w-100 mt-3" style="background: linear-gradient(135deg, #f7d76d, #d9a928); color: #032014; font-weight: 800; border-radius: 12px; padding: 10px;" onclick="payNow('${data.merchant}', ${data.price}, '${data.desc}', ${parseInt(data.merchant_wallet_id || 0, 10)})">Pay Now</button>
                        `;
                    } else {
                         scanResultText.textContent = code.data;
                    }
                } catch(e) {
                    scanResultText.textContent = code.data;
                }

                cameraStatus.textContent = "QR Detected";
                cameraStatus.classList.remove("blocked");
                cameraStatus.classList.add("active");
            }
        }

        requestAnimationFrame(scanQRCode);
    }

    async function payNow(merchant, price, desc, merchantWalletId) {
        if (!merchantWalletId) {
            alert("This QR code is missing merchant wallet details. Ask the merchant to generate a new QR.");
            return;
        }

        if (!confirm("Pay PHP " + parseFloat(price).toFixed(2) + " to " + merchant + " for " + desc + "?")) {
            return;
        }

        const response = await fetch("pay_qr.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({
                merchant_wallet_id: merchantWalletId,
                amount: price,
                description: desc
            })
        });
        const result = await response.json();
        if (result.success) {
            alert("Payment completed. Reference: " + result.reference);
            window.location.reload();
            return;
        }

        alert(result.message || "Payment failed.");
    }

    startScanner();
    </script>

</body>

</html>
