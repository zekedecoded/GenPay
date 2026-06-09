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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan & Pay | EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=12">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

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
                <a href="<?= DASHBOARD_URL ?>">
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
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">&#9776;</button>

                <div>
                    <h1>Scan &amp; Pay</h1>
                    <p>Scan a merchant QR code, review the payment details, and confirm the charge from your wallet.</p>
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
                            <p>Use the live camera below and keep the code inside the frame until the payment card appears.</p>
                        </div>

                        <span class="scan-status-badge" id="cameraStatus">Starting Camera</span>
                    </div>

                    <div class="scan-camera-box">
                        <video id="qrVideo" autoplay playsinline></video>
                        <canvas id="qrCanvas" hidden></canvas>

                        <div class="scan-camera-overlay" aria-hidden="true">
                            <div class="scan-corner top-left"></div>
                            <div class="scan-corner top-right"></div>
                            <div class="scan-corner bottom-left"></div>
                            <div class="scan-corner bottom-right"></div>
                            <div class="scan-line"></div>
                        </div>

                        <div class="scan-camera-message" id="cameraMessage">
                            Opening camera...
                        </div>
                    </div>

                    <div class="scan-toolbar">
                        <button type="button" class="scan-action-btn secondary" id="resumeScannerBtn">Resume Scan</button>
                        <button type="button" class="scan-action-btn secondary" id="switchCameraBtn">Switch Camera</button>
                    </div>

                    <div class="scan-result-box" id="scanResultBox">
                        <span>Scan Result</span>
                        <div class="scan-result-empty" id="scanResultEmpty">No QR detected yet.</div>
                        <div class="scan-payment-card d-none" id="scanPaymentCard">
                            <div class="scan-payment-grid">
                                <div>
                                    <label>Merchant</label>
                                    <strong id="scanMerchantName">--</strong>
                                </div>
                                <div>
                                    <label>Item</label>
                                    <strong id="scanItemDesc">--</strong>
                                </div>
                                <div>
                                    <label>Amount</label>
                                    <strong id="scanAmount">--</strong>
                                </div>
                            </div>
                            <button type="button" class="scan-action-btn" id="scanPayNowBtn">Pay Now</button>
                        </div>
                        <div id="scanResultText" class="scan-result-note d-none"></div>
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
                            <span>Ask the merchant to generate a fresh payment QR for the item you are buying.</span>
                        </div>

                        <div>
                            <strong>2</strong>
                            <span>Allow camera access so the scanner can read the QR code.</span>
                        </div>

                        <div>
                            <strong>3</strong>
                            <span>Review the merchant name, item, and amount before you continue.</span>
                        </div>

                        <div>
                            <strong>4</strong>
                            <span>Tap Pay Now only when the payment details match the purchase.</span>
                        </div>
                    </div>

                    <div class="scan-note">
                        Camera scanning works on <strong>localhost</strong> or secure HTTPS pages. If the rear camera is unavailable, try <strong>Switch Camera</strong>.
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
                    <table class="table student-premium-table align-middle js-datatable" id="studentRecentPaymentsTable" data-page-length="8">
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
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?= JS_URL ?>/admin_datatables.js"></script>

    <script>
    function toggleStudentSidebar() {
        document.getElementById("studentSidebar").classList.toggle("collapsed");
    }

    const video = document.getElementById("qrVideo");
    const canvas = document.getElementById("qrCanvas");
    const canvasContext = canvas.getContext("2d");
    const cameraMessage = document.getElementById("cameraMessage");
    const cameraStatus = document.getElementById("cameraStatus");
    const scanResultEmpty = document.getElementById("scanResultEmpty");
    const scanResultText = document.getElementById("scanResultText");
    const scanPaymentCard = document.getElementById("scanPaymentCard");
    const scanMerchantName = document.getElementById("scanMerchantName");
    const scanItemDesc = document.getElementById("scanItemDesc");
    const scanAmount = document.getElementById("scanAmount");
    const scanPayNowBtn = document.getElementById("scanPayNowBtn");
    const resumeScannerBtn = document.getElementById("resumeScannerBtn");
    const switchCameraBtn = document.getElementById("switchCameraBtn");

    let activeStream = null;
    let currentFacingMode = "environment";
    let scanningPaused = false;
    let pendingPayment = null;
    let lastDetectedPayload = "";

    function setCameraStatus(text, tone = "") {
        cameraStatus.className = "scan-status-badge";
        if (tone) {
            cameraStatus.classList.add(tone);
        }
        cameraStatus.textContent = text;
    }

    function stopScannerStream() {
        if (activeStream) {
            activeStream.getTracks().forEach((track) => track.stop());
            activeStream = null;
        }
    }

    function resetScanCard() {
        pendingPayment = null;
        scanPaymentCard.classList.add("d-none");
        scanResultText.classList.add("d-none");
        scanResultText.textContent = "";
        scanResultText.className = "scan-result-note d-none";
        scanResultEmpty.classList.remove("d-none");
        scanResultEmpty.textContent = "No QR detected yet.";
    }

    function showScanMessage(message, tone = "note") {
        scanPaymentCard.classList.add("d-none");
        scanResultEmpty.classList.add("d-none");
        scanResultText.classList.remove("d-none");
        scanResultText.className = tone === "error" ? "scan-result-error" : "scan-result-note";
        scanResultText.textContent = message;
    }

    function showPaymentCard(data) {
        pendingPayment = data;
        scanResultEmpty.classList.add("d-none");
        scanResultText.classList.add("d-none");
        scanMerchantName.textContent = data.merchant || "--";
        scanItemDesc.textContent = data.desc || "--";
        scanAmount.textContent = "₱" + parseFloat(data.price).toFixed(2);
        scanPaymentCard.classList.remove("d-none");
    }

    async function startScanner(facingMode = currentFacingMode) {
        stopScannerStream();
        scanningPaused = false;
        currentFacingMode = facingMode;
        cameraMessage.style.display = "grid";
        cameraMessage.innerHTML = "Opening camera...";
        setCameraStatus("Starting Camera");

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: currentFacingMode
                }
            });

            activeStream = stream;
            video.srcObject = stream;
            cameraMessage.style.display = "none";
            setCameraStatus("Camera Active", "active");

            requestAnimationFrame(scanQRCode);
        } catch (error) {
            setCameraStatus("Camera Blocked", "blocked");
            cameraMessage.innerHTML = "Camera access denied.<br>Please allow camera permissions.";
        }
    }

    function scanQRCode() {
        if (scanningPaused) {
            requestAnimationFrame(scanQRCode);
            return;
        }

        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            canvasContext.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = canvasContext.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "dontInvert",
            });

            if (code) {
                if (code.data === lastDetectedPayload) {
                    requestAnimationFrame(scanQRCode);
                    return;
                }

                try {
                    const data = JSON.parse(code.data);
                    if (data.type === 'payment') {
                        lastDetectedPayload = code.data;
                        scanningPaused = true;
                        showPaymentCard(data);
                        setCameraStatus("QR Detected", "active");
                        return;
                    }

                    showScanMessage("Unsupported QR format.", "error");
                } catch (error) {
                    showScanMessage(code.data, "note");
                }
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
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                merchant_wallet_id: merchantWalletId,
                amount: price,
                description: desc
            })
        });

        const result = await response.json();
        if (result.success) {
            alert("Payment completed. Reference: " + result.reference);
            stopScannerStream();
            window.location.reload();
            return;
        }

        alert(result.message || "Payment failed.");
        scanningPaused = false;
        setCameraStatus("Camera Active", "active");
    }

    scanPayNowBtn.addEventListener("click", function() {
        if (!pendingPayment) {
            return;
        }

        payNow(
            pendingPayment.merchant,
            pendingPayment.price,
            pendingPayment.desc,
            parseInt(pendingPayment.merchant_wallet_id || 0, 10)
        );
    });

    resumeScannerBtn.addEventListener("click", function() {
        lastDetectedPayload = "";
        scanningPaused = false;
        resetScanCard();
        setCameraStatus("Camera Active", "active");
    });

    switchCameraBtn.addEventListener("click", function() {
        const nextFacingMode = currentFacingMode === "environment" ? "user" : "environment";
        lastDetectedPayload = "";
        resetScanCard();
        startScanner(nextFacingMode);
    });

    window.addEventListener("beforeunload", stopScannerStream);

    resetScanCard();

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        setCameraStatus("Camera Unsupported", "blocked");
        cameraMessage.innerHTML = "This browser does not support camera scanning.";
    } else {
        startScanner();
    }
    </script>

</body>

</html>
