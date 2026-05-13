<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['merchant']);

$currentUser = gjc_current_user($db);
$wallet = gjc_merchant_wallet($db, $currentUser['id']);
$merchantWalletId = (int) $wallet['id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Visitor QR Scanner | Merchant Portal</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=12">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
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
                <button class="merchant-menu-btn" onclick="toggleMerchantSidebar()">&#9776;</button>
                <div>
                    <h1>Visitor QR Scanner</h1>
                    <p>Validate visitor vouchers, review remaining balance, and confirm merchant payments.</p>
                </div>

                <div class="merchant-user">
                    <span><?php echo gjc_e($currentUser['name']); ?></span>
                    <div class="merchant-avatar">
                        <img src="<?= ICONS_URL ?>/store.png" alt="Merchant">
                    </div>
                </div>
            </header>

            <section class="merchant-scanner-grid">
                <div class="merchant-premium-panel merchant-scanner-panel">
                    <div class="merchant-panel-header merchant-scanner-header">
                        <div>
                            <h3>Live Voucher Camera</h3>
                            <p>Point the camera at a visitor voucher QR. We will pause scanning once a valid voucher is detected.</p>
                        </div>
                        <span class="merchant-scan-status" id="merchantScanStatus">Starting camera</span>
                    </div>

                    <div class="merchant-reader-shell">
                        <div id="reader" class="merchant-reader"></div>
                        <div class="merchant-reader-hint" id="merchantReaderHint">
                            Align the QR inside the frame to validate the voucher.
                        </div>
                    </div>

                    <div class="merchant-scan-toolbar">
                        <button type="button" class="merchant-scan-btn secondary" id="resumeScanBtn">Resume Scan</button>
                        <button type="button" class="merchant-scan-btn secondary" id="clearScanBtn">Clear Result</button>
                    </div>

                    <div class="merchant-manual-panel">
                        <label for="manualHash">Manual Voucher Payload or Hash</label>
                        <textarea id="manualHash" class="form-control" rows="3" placeholder="Paste the voucher JSON payload or QR hash here..."></textarea>
                        <button type="button" class="merchant-scan-btn" id="manualValidateBtn">Validate Voucher</button>
                    </div>
                </div>

                <div class="merchant-premium-panel merchant-voucher-panel">
                    <div class="merchant-panel-header">
                        <div>
                            <h3>Voucher Details</h3>
                            <p>Review the visitor balance before collecting payment.</p>
                        </div>
                    </div>

                    <div id="voucherResult" class="merchant-voucher-card is-empty">
                        <div class="merchant-voucher-empty">
                            <div class="merchant-voucher-empty-icon">
                                <img src="<?= ICONS_URL ?>/visitors.png" alt="">
                            </div>
                            <h4>No voucher selected</h4>
                            <p>Scan a voucher or paste its payload to load the visitor details.</p>
                        </div>

                        <div class="merchant-voucher-content">
                            <div class="merchant-voucher-top">
                                <div>
                                    <span class="merchant-voucher-label">Visitor</span>
                                    <h4 id="vName">Visitor Name</h4>
                                    <small id="vCode">VCH-XXXXX</small>
                                </div>
                                <div class="merchant-voucher-balance">
                                    <span>Available Balance</span>
                                    <strong>₱<span id="vBal">0.00</span></strong>
                                </div>
                            </div>

                            <div class="merchant-voucher-meta">
                                <div>
                                    <span>Expires</span>
                                    <strong id="vExpiry">--</strong>
                                </div>
                                <div>
                                    <span>Refund Rule</span>
                                    <strong id="vRefundable">Non-refundable</strong>
                                </div>
                                <div>
                                    <span>Validity</span>
                                    <strong id="vMinutesLeft">--</strong>
                                </div>
                            </div>

                            <div id="vWarning" class="merchant-inline-note warning d-none"></div>
                            <div id="voucherError" class="merchant-inline-note danger d-none"></div>

                            <form class="merchant-pay-form" id="payForm">
                                <label for="payAmount">Payment Amount</label>
                                <div class="merchant-pay-row">
                                    <div class="merchant-pay-input">
                                        <span>₱</span>
                                        <input type="number" id="payAmount" class="form-control" placeholder="0.00" min="0.01" step="0.01">
                                    </div>
                                    <button type="button" class="merchant-scan-btn" id="confirmVoucherPaymentBtn">Confirm Payment</button>
                                </div>
                                <small>Use the exact amount to collect from this visitor voucher.</small>
                            </form>
                        </div>
                    </div>

                    <div id="successMsg" class="merchant-inline-note success d-none"></div>
                </div>
            </section>
        </main>
    </div>

    <script>
    const API_URL = '<?= MERCHANT_URL ?>/api/scan_voucher.php';
    const walletId = <?= $merchantWalletId ?>;
    const scanStatus = document.getElementById('merchantScanStatus');
    const readerHint = document.getElementById('merchantReaderHint');
    const voucherResult = document.getElementById('voucherResult');
    const voucherError = document.getElementById('voucherError');
    const voucherWarning = document.getElementById('vWarning');
    const successMsg = document.getElementById('successMsg');
    const payAmountInput = document.getElementById('payAmount');
    const manualHashInput = document.getElementById('manualHash');
    const resumeScanBtn = document.getElementById('resumeScanBtn');
    const clearScanBtn = document.getElementById('clearScanBtn');
    const manualValidateBtn = document.getElementById('manualValidateBtn');
    const confirmVoucherPaymentBtn = document.getElementById('confirmVoucherPaymentBtn');

    let currentHash = null;
    let scannerPaused = false;
    let lastScannedHash = '';

    function toggleMerchantSidebar() {
        document.getElementById("merchantSidebar").classList.toggle("collapsed");
    }

    function setScanStatus(text, tone = '') {
        scanStatus.className = 'merchant-scan-status';
        if (tone) {
            scanStatus.classList.add(tone);
        }
        scanStatus.textContent = text;
    }

    function parseVoucherInput(rawValue) {
        const value = String(rawValue || '').trim();
        if (!value) {
            return '';
        }

        try {
            const payload = JSON.parse(value);
            if (payload && payload.type === 'VISITOR_VOUCHER' && payload.hash) {
                return String(payload.hash).trim();
            }
        } catch (error) {
            // Treat the raw input as a plain hash.
        }

        return value;
    }

    function resetMessages() {
        voucherError.classList.add('d-none');
        voucherWarning.classList.add('d-none');
        successMsg.classList.add('d-none');
        voucherError.textContent = '';
        voucherWarning.textContent = '';
        successMsg.textContent = '';
    }

    function resetVoucherCard() {
        voucherResult.classList.add('is-empty');
        currentHash = null;
        lastScannedHash = '';
        payAmountInput.value = '';
        document.getElementById('vName').textContent = 'Visitor Name';
        document.getElementById('vCode').textContent = 'VCH-XXXXX';
        document.getElementById('vBal').textContent = '0.00';
        document.getElementById('vExpiry').textContent = '--';
        document.getElementById('vRefundable').textContent = 'Non-refundable';
        document.getElementById('vMinutesLeft').textContent = '--';
        resetMessages();
    }

    function fillVoucherCard(data) {
        const voucher = data.voucher || {};
        voucherResult.classList.remove('is-empty');
        document.getElementById('vName').textContent = voucher.visitor_name || 'Visitor';
        document.getElementById('vCode').textContent = voucher.voucher_code || 'VCH-XXXXX';
        document.getElementById('vBal').textContent = Number(data.remaining ?? voucher.remaining_balance ?? 0).toFixed(2);
        document.getElementById('vExpiry').textContent = voucher.expires_at || '--';
        document.getElementById('vRefundable').textContent = Number(voucher.is_refundable || 0) === 1 ? 'Refundable' : 'Non-refundable';
        document.getElementById('vMinutesLeft').textContent = typeof data.minutes_left === 'number'
            ? data.minutes_left + ' min left'
            : 'Ready to use';
        payAmountInput.value = Number(data.remaining ?? voucher.remaining_balance ?? 0).toFixed(2);

        if (data.warning) {
            voucherWarning.textContent = data.warning;
            voucherWarning.classList.remove('d-none');
        } else {
            voucherWarning.classList.add('d-none');
        }
    }

    function showVoucherError(message) {
        resetMessages();
        voucherError.textContent = message;
        voucherError.classList.remove('d-none');
        voucherResult.classList.remove('is-empty');
        setScanStatus('Voucher not usable', 'blocked');
    }

    function showSuccess(message) {
        successMsg.innerHTML = message;
        successMsg.classList.remove('d-none');
    }

    function pauseScanner() {
        scannerPaused = true;
        setScanStatus('Voucher loaded', 'active');
        readerHint.textContent = 'Voucher loaded. Review the details, then confirm the payment or resume scanning.';
    }

    function resumeScanner() {
        scannerPaused = false;
        lastScannedHash = '';
        setScanStatus('Camera active', 'active');
        readerHint.textContent = 'Align the QR inside the frame to validate the voucher.';
    }

    async function validateQR(rawInput) {
        resetMessages();
        const hash = parseVoucherInput(rawInput);
        if (!hash) {
            showVoucherError('Enter or scan a valid voucher payload.');
            return;
        }

        try {
            setScanStatus('Validating voucher', 'pending');
            const fd = new FormData();
            fd.append('action', 'validate');
            fd.append('qr_hash', hash);

            const res = await fetch(API_URL, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success || !data.valid) {
                resetVoucherCard();
                showVoucherError(data.error || 'Invalid voucher.');
                return;
            }

            currentHash = hash;
            lastScannedHash = hash;
            fillVoucherCard(data);
            pauseScanner();
        } catch (error) {
            showVoucherError('Connection error while validating the voucher.');
        }
    }

    async function processPayment() {
        resetMessages();
        if (!currentHash) {
            showVoucherError('Scan a voucher before confirming payment.');
            return;
        }

        const amount = parseFloat(payAmountInput.value);
        if (Number.isNaN(amount) || amount <= 0) {
            showVoucherError('Enter a valid payment amount.');
            return;
        }

        confirmVoucherPaymentBtn.disabled = true;
        confirmVoucherPaymentBtn.textContent = 'Processing...';

        try {
            const fd = new FormData();
            fd.append('action', 'pay');
            fd.append('qr_hash', currentHash);
            fd.append('amount', amount);
            fd.append('merchant_wallet_id', walletId);

            const res = await fetch(API_URL, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) {
                showVoucherError(data.error || 'Payment failed.');
                return;
            }

            resetVoucherCard();
            showSuccess(
                '<strong>Payment successful.</strong><br>' +
                'Received ₱' + amount.toFixed(2) + ' from ' + data.visitor_name + '.<br>' +
                '<small>Ref: ' + data.reference + '</small>'
            );
            setScanStatus('Payment posted', 'active');
            readerHint.textContent = 'Ready for the next voucher.';
            resumeScanner();
        } catch (error) {
            showVoucherError('Connection error while processing payment.');
        } finally {
            confirmVoucherPaymentBtn.disabled = false;
            confirmVoucherPaymentBtn.textContent = 'Confirm Payment';
        }
    }

    function onScanSuccess(decodedText) {
        if (scannerPaused) {
            return;
        }

        const hash = parseVoucherInput(decodedText);
        if (!hash || hash === lastScannedHash) {
            return;
        }

        validateQR(decodedText);
    }

    const html5QrcodeScanner = new Html5QrcodeScanner(
        'reader',
        { fps: 10, qrbox: { width: 240, height: 240 } },
        false
    );

    html5QrcodeScanner.render(
        onScanSuccess,
        () => {}
    );

    setScanStatus('Starting camera', 'pending');

    resumeScanBtn.addEventListener('click', () => {
        resetMessages();
        resumeScanner();
    });

    clearScanBtn.addEventListener('click', () => {
        manualHashInput.value = '';
        resetVoucherCard();
        resumeScanner();
    });

    manualValidateBtn.addEventListener('click', () => {
        validateQR(manualHashInput.value);
    });

    confirmVoucherPaymentBtn.addEventListener('click', processPayment);

    setTimeout(() => {
        if (!scannerPaused) {
            setScanStatus('Camera active', 'active');
        }
    }, 1400);

    resetVoucherCard();
    </script>
</body>

</html>
