<?php
session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';

// Quick check for merchant
// if (!isset($_SESSION['userID'])) { header("Location: ../login.php"); exit; }

// For demo purposes, we fetch the first merchant wallet ID
$stmt = $db->query("SELECT id FROM merchant_wallets LIMIT 1");
$wallet = $stmt->fetch();
$merchantWalletId = $wallet ? $wallet['id'] : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visitor QR Scanner | Merchant Portal</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=10">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        .scanner-container {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        #reader { width: 100%; border-radius: 12px; overflow: hidden; border: 2px dashed #ddd; }
        .voucher-card {
            background: linear-gradient(135deg, var(--emerald-900), var(--emerald-700));
            color: white;
            padding: 20px;
            border-radius: 16px;
            margin-top: 20px;
            display: none;
        }
        .voucher-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 15px; margin-bottom: 15px; }
        .voucher-val { font-size: 28px; font-weight: 800; color: var(--gold-light); }
        .pay-form { display: none; margin-top: 15px; }
    </style>
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

                <a href="<?= MERCHANT_URL ?>/qr_scanner.php" class="active">
                    <img src="<?= ICONS_URL ?>/visitors.png" class="merchant-nav-icon" alt="">
                    <span class="merchant-nav-text">Scan Voucher</span>
                </a>

                <a href="<?= MERCHANT_URL ?>/encash.php">
                    <img src="<?= ICONS_URL ?>/encashments.png" class="merchant-nav-icon" alt="">
                    <span class="merchant-nav-text">Encash</span>
                </a>

                <a href="<?= MERCHANT_URL ?>/history.php">
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
                    <h1>Visitor QR Scanner</h1>
                    <p>Scan visitor vouchers to receive payments.</p>
                </div>
            </header>

            <div class="scanner-container">
                <div id="reader"></div>

                <div class="mt-4 text-center">
                    <p class="text-muted small">Or test with manual hash paste:</p>
                    <input type="text" id="manualHash" class="form-control mb-2" placeholder="Paste voucher hash here...">
                    <button class="btn btn-outline-secondary w-100" onclick="validateQR(document.getElementById('manualHash').value)">Validate Hash</button>
                </div>

                <!-- Result Card -->
                <div id="voucherResult" class="voucher-card">
                    <div class="voucher-header">
                        <div>
                            <h5 class="mb-0" id="vName">Visitor Name</h5>
                            <small id="vCode" style="color:#d1d5db">VCH-XXXXX</small>
                        </div>
                        <div class="text-end">
                            <small class="d-block" style="color:#d1d5db">Available Balance</small>
                            <span class="voucher-val">₱<span id="vBal">0.00</span></span>
                        </div>
                    </div>
                    
                    <div id="vWarning" class="alert alert-warning py-2 px-3 mb-3 d-none" style="font-size:13px; font-weight:600"></div>

                    <div class="pay-form" id="payForm">
                        <label class="form-label" style="font-size: 13px; color: #cbd5e1;">Payment Amount</label>
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-white border-0">₱</span>
                            <input type="number" id="payAmount" class="form-control border-0" placeholder="0.00" step="0.01">
                            <button class="btn btn-warning fw-bold" onclick="processPayment()">Confirm Payment</button>
                        </div>
                    </div>
                </div>

                <div id="errorMsg" class="alert alert-danger mt-3 d-none"></div>
                <div id="successMsg" class="alert alert-success mt-3 d-none"></div>
            </div>
        </main>
    </div>

    <script>
        const API_URL = '<?= MERCHANT_URL ?>/api/scan_voucher.php';
        let currentHash = null;
        const walletId = <?= $merchantWalletId ?>;

        function onScanSuccess(decodedText, decodedResult) {
            try {
                const payload = JSON.parse(decodedText);
                if(payload.type !== 'VISITOR_VOUCHER' || !payload.hash) {
                    showError("Invalid QR format. Not a visitor voucher.");
                    return;
                }
                validateQR(payload.hash);
            } catch(e) {
                validateQR(decodedText);
            }
        }

        const html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);
        html5QrcodeScanner.render(onScanSuccess);

        async function validateQR(hash) {
            hideMessages();
            if(!hash) return;
            
            try {
                const fd = new FormData();
                fd.append('action', 'validate');
                fd.append('qr_hash', hash);

                const res = await fetch(API_URL, { method: 'POST', body: fd });
                const data = await res.json();

                if(!data.success || !data.valid) {
                    showError(data.error || "Invalid voucher.");
                    return;
                }

                currentHash = hash;
                document.getElementById('vName').textContent = data.voucher.visitor_name;
                document.getElementById('vCode').textContent = data.voucher.voucher_code;
                document.getElementById('vBal').textContent = parseFloat(data.remaining).toFixed(2);
                
                const warnBox = document.getElementById('vWarning');
                if(data.warning) {
                    warnBox.textContent = data.warning;
                    warnBox.classList.remove('d-none');
                } else {
                    warnBox.classList.add('d-none');
                }

                document.getElementById('voucherResult').style.display = 'block';
                document.getElementById('payForm').style.display = 'block';

            } catch(err) {
                showError("Connection error while validating QR.");
            }
        }

        async function processPayment() {
            const amt = parseFloat(document.getElementById('payAmount').value);
            if(isNaN(amt) || amt <= 0) {
                showError("Enter a valid amount.");
                return;
            }

            try {
                const fd = new FormData();
                fd.append('action', 'pay');
                fd.append('qr_hash', currentHash);
                fd.append('amount', amt);
                fd.append('merchant_wallet_id', walletId);

                const res = await fetch(API_URL, { method: 'POST', body: fd });
                const data = await res.json();

                if(!data.success) {
                    showError(data.error || "Payment failed.");
                    return;
                }

                document.getElementById('voucherResult').style.display = 'none';
                document.getElementById('successMsg').innerHTML = `
                    <strong>Payment Successful!</strong><br>
                    Received ₱${amt.toFixed(2)} from ${data.visitor_name}.<br>
                    <small class="text-muted">Ref: ${data.reference}</small>
                `;
                document.getElementById('successMsg').classList.remove('d-none');
                document.getElementById('payAmount').value = '';
                currentHash = null;

                setTimeout(() => { document.getElementById('successMsg').classList.add('d-none'); }, 5000);

            } catch(err) {
                showError("Connection error while processing payment.");
            }
        }

        function showError(msg) {
            document.getElementById('voucherResult').style.display = 'none';
            document.getElementById('errorMsg').textContent = msg;
            document.getElementById('errorMsg').classList.remove('d-none');
            setTimeout(() => { document.getElementById('errorMsg').classList.add('d-none'); }, 5000);
        }

        function hideMessages() {
            document.getElementById('errorMsg').classList.add('d-none');
            document.getElementById('successMsg').classList.add('d-none');
        }
    </script>
</body>
</html>
