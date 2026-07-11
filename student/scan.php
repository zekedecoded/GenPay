<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['student']);

if (isset($_SESSION['force_change'])) {
    header('Location: ' . BASE_URL . '/change_password.php');
    exit();
}

$currentUser = gjc_current_user($db);
$wallet      = gjc_student_wallet($db, (int) $currentUser['id']);
$studentName = $currentUser['name'];
$balance     = (float) $wallet['balance'];

// Real school-issued ID (GJC2026-0001); the padded userID is only a fallback
// for accounts that never got a student_info row.
$studentID = 'GJC-' . str_pad((string) $currentUser['id'], 5, '0', STR_PAD_LEFT);
if (gjc_table_exists($db, 'student_info')) {
    $sidStmt = $db->prepare("SELECT studentID FROM student_info WHERE userID = ? LIMIT 1");
    $sidStmt->execute([(int) $currentUser['id']]);
    $realID = trim((string) $sidStmt->fetchColumn());
    if ($realID !== '') {
        $studentID = $realID;
    }
}

// Last 5 scan-and-pay purchases, with the merchant's public label
// (stall name, falling back to the account name).
$recentPayments = [];
if ($wallet['id'] > 0 && gjc_table_exists($db, 'transactions')) {
    $rpStmt = $db->prepare(
        "SELECT t.reference_no, t.amount, t.notes, t.created_at, mw.user_id AS merchant_user_id
           FROM transactions t
           LEFT JOIN merchant_wallets mw ON mw.id = t.merchant_wallet_id
          WHERE t.student_wallet_id = ?
            AND t.transaction_type = 'payment'
            AND t.status = 'completed'
          ORDER BY t.created_at DESC, t.id DESC
          LIMIT 5"
    );
    $rpStmt->execute([$wallet['id']]);
    foreach ($rpStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $desc = trim((string) preg_replace('/^(POS QR Sale|Cart Order):\s*/i', '', (string) ($row['notes'] ?? '')));
        $recentPayments[] = [
            'reference'   => (string) $row['reference_no'],
            'merchant'    => $row['merchant_user_id']
                ? (gjc_merchant_display_name($db, (int) $row['merchant_user_id']) ?: 'Merchant')
                : 'Merchant',
            'description' => $desc !== '' ? $desc : 'Merchant payment',
            'amount'      => (float) $row['amount'],
            'date'        => date('M j, Y g:i A', strtotime((string) $row['created_at'])),
        ];
    }
}

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$currentPage = 'scan';
$csrfToken = gjc_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan &amp; Pay | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student_dashboard.css?v=12">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student_scan.css?v=2">

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
</head>

<body class="sd-body sp-page">

    <div class="sd-layout">

        <?php require __DIR__ . '/../includes/partials/sidebar_student.php'; ?>

        <main class="sd-main">

            <?php
            $topbarTitle = 'Scan &amp; Pay';
            $topbarSubtitle = 'Scan a merchant QR code, review the payment details, and confirm the charge from your wallet.';
            require __DIR__ . '/../includes/partials/topbar_student.php';
            ?>

            <div class="sd-content">

                <!-- Balance card -->
                <section class="sd-balance">
                    <div>
                        <span class="sd-balance-label">Current Balance</span>
                        <div class="sd-balance-amount">
                            <span id="sdBalance"><?= gjc_gc_amount($balance) ?></span><span class="sd-unit">GC</span>
                        </div>
                        <div class="sd-gc-row">
                            <span class="sd-gc-badge">&#8776; &#8369;<span id="sdBalancePhp"><?= number_format($balance, 2) ?></span></span>
                            <span class="sd-gc-rate">&#8369;10 = 1 GC</span>
                        </div>
                        <div class="sd-balance-actions">
                            <a class="sd-btn-ghost" href="<?= STUDENT_URL ?>/transfer.php"><i class="fa-solid fa-paper-plane"></i>Send</a>
                            <a class="sd-btn-ghost" href="<?= STUDENT_URL ?>/withdraw.php"><i class="fa-solid fa-money-bill-wave"></i>Withdraw</a>
                        </div>
                    </div>
                    <div class="sd-balance-holder">
                        <strong><?= $e($studentName) ?></strong>
                        <span class="sd-holder-id"><?= $e($studentID) ?></span>
                        <span class="sd-role-badge">STUDENT</span>
                    </div>
                </section>

                <!-- Scanner + guide -->
                <section class="sp-grid">

                    <div class="sd-panel sp-scanner">

                        <!-- Mobile-only chrome (full-screen scanner) -->
                        <div class="sp-mobile-bar sp-mobile-only">
                            <a href="<?= DASHBOARD_URL ?>" class="sp-mobile-iconbtn" aria-label="Back to dashboard">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                            <h2>Scan &amp; Pay</h2>
                            <button type="button" class="sp-mobile-iconbtn" id="switchCameraBtnM" aria-label="Switch camera">
                                <i class="fa-solid fa-camera-rotate"></i>
                            </button>
                        </div>

                        <div class="sd-panel-head">
                            <div>
                                <h3>Scan Merchant QR</h3>
                                <p>Use the live camera below and keep the code inside the frame until the payment card appears.</p>
                            </div>
                            <span class="sp-status" id="cameraStatus">Starting Camera</span>
                        </div>

                        <div class="sp-frame" id="scanFrame">
                            <video id="qrVideo" autoplay playsinline muted></video>
                            <canvas id="qrCanvas" hidden></canvas>

                            <span class="sp-corner tl" aria-hidden="true"></span>
                            <span class="sp-corner tr" aria-hidden="true"></span>
                            <span class="sp-corner bl" aria-hidden="true"></span>
                            <span class="sp-corner br" aria-hidden="true"></span>
                            <span class="sp-scanline" id="scanLine" aria-hidden="true"></span>

                            <div class="sp-frame-msg" id="frameMsg">
                                <div>
                                    <strong id="frameMsgTitle">Opening camera&hellip;</strong>
                                    <small id="frameMsgSub"></small>
                                    <button type="button" class="sp-retry-btn" id="retryCameraBtn" hidden>
                                        <i class="fa-solid fa-rotate-right me-1"></i> Retry Camera
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="sp-mobile-caption sp-mobile-only">
                            <strong>Align the merchant&rsquo;s QR code</strong>
                            <span>Payment details appear automatically</span>
                        </div>

                        <div class="sp-mobile-toast" id="mobileToast">
                            <div id="mobileToastText"></div>
                        </div>

                        <button type="button" class="sp-mobile-pill sp-mobile-only" id="manualBtnMobile">
                            <i class="fa-solid fa-keyboard"></i> Enter code manually
                        </button>

                        <div class="sp-toolbar">
                            <button type="button" class="sp-btn" id="resumeScanBtn">Resume Scan</button>
                            <button type="button" class="sp-btn" id="switchCameraBtn">Switch Camera</button>
                            <button type="button" class="sp-btn sp-btn--gold" id="manualBtn">Enter code manually</button>
                        </div>

                        <div class="sp-result">
                            <span class="sp-result-label">Scan Result</span>
                            <div class="sp-result-text" id="scanResultText">No QR detected yet.</div>
                        </div>

                    </div>

                    <div class="sd-panel sp-guide">
                        <div class="sd-panel-head">
                            <div>
                                <h3>Payment Guide</h3>
                                <p>Follow these steps when paying a merchant.</p>
                            </div>
                        </div>

                        <div class="sp-steps">
                            <div class="sp-step">
                                <strong>1</strong>
                                <span>Ask the merchant to generate a fresh payment QR for the item you are buying.</span>
                            </div>
                            <div class="sp-step">
                                <strong>2</strong>
                                <span>Allow camera access so the scanner can read the QR code.</span>
                            </div>
                            <div class="sp-step">
                                <strong>3</strong>
                                <span>Review the merchant name, item, and amount before you continue.</span>
                            </div>
                            <div class="sp-step">
                                <strong>4</strong>
                                <span>Tap Pay Now only when the payment details match the purchase.</span>
                            </div>
                        </div>

                        <div class="sp-note">
                            Camera scanning works on <strong>localhost</strong> or secure HTTPS pages.
                            If the rear camera is unavailable, try <strong>Switch Camera</strong> &mdash;
                            or use <strong>Enter code manually</strong> with the code shown on the merchant&rsquo;s screen.
                        </div>
                    </div>

                </section>

                <!-- Recent payments -->
                <section class="sd-panel sp-recent">
                    <div class="sd-panel-head">
                        <div>
                            <h3>Recent Payments</h3>
                            <p>Your latest scan-and-pay transactions.</p>
                        </div>
                        <a href="<?= STUDENT_URL ?>/history.php" class="sd-viewall">View All</a>
                    </div>

                    <?php if (empty($recentPayments)): ?>
                    <div class="sd-empty">
                        <i class="fa-regular fa-credit-card"></i>
                        No transactions yet. Scan a merchant QR code to start paying with your wallet.
                    </div>
                    <?php else: ?>
                    <ul class="sd-txn-list">
                        <?php foreach ($recentPayments as $payment): ?>
                        <li class="sd-txn sd-txn--payment">
                            <div class="sd-txn-icon"><i class="fa-solid fa-store"></i></div>
                            <div class="sd-txn-info">
                                <div class="sd-txn-ref"><?= $e($payment['description']) ?></div>
                                <div class="sp-txn-merchant"><?= $e($payment['merchant']) ?> &middot; <?= $e($payment['reference']) ?></div>
                            </div>
                            <div class="sd-txn-right">
                                <div class="sd-txn-amount">&minus;<?= gjc_gc_amount($payment['amount']) ?> GC</div>
                                <div class="sd-txn-php">&#8776; &#8369;<?= number_format($payment['amount'], 2) ?></div>
                                <div class="sd-txn-date"><?= $e($payment['date']) ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </section>

            </div>

        </main>

    </div>

    <?php require __DIR__ . '/../includes/partials/bottom_nav_student.php'; ?>

    <!-- Manual code entry -->
    <div class="modal fade sp-modal" id="manualModal" tabindex="-1" aria-labelledby="manualModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="manualModalTitle">Enter Payment Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p style="font-size:13px;color:var(--sd-muted);margin-bottom:14px">
                        Type the manual entry code shown under the QR on the merchant&rsquo;s screen.
                    </p>
                    <input type="text" class="sp-code-input" id="manualCodeInput"
                           placeholder="XXXX-XXXX" maxlength="14" autocomplete="off" spellcheck="false"
                           autocapitalize="characters" aria-label="Merchant payment code">
                    <div class="sp-code-error" id="manualCodeError"></div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="sp-btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="sp-btn-pay" id="manualSubmitBtn">Find Payment</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment confirmation -->
    <div class="modal fade sp-modal" id="confirmModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
         aria-labelledby="confirmModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="confirmModalTitle">Confirm Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">

                    <div id="payConfirmView">
                        <div class="sp-pay-amount">
                            <span>Amount to pay</span>
                            <strong id="cmAmount">--</strong>
                        </div>
                        <div class="sp-pay-rows">
                            <div class="sp-pay-row">
                                <label>Merchant</label>
                                <strong id="cmMerchant">--</strong>
                            </div>
                            <div class="sp-pay-row">
                                <label>Item</label>
                                <strong id="cmItem">--</strong>
                            </div>
                            <div class="sp-pay-row">
                                <label>Peso value</label>
                                <strong id="cmPhp">--</strong>
                            </div>
                        </div>
                        <div class="sp-pay-error" id="payError"></div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="sp-btn-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="sp-btn-pay" id="payNowBtn">
                                <i class="fa-solid fa-lock me-1"></i> Pay Now
                            </button>
                        </div>
                    </div>

                    <div id="paySuccessView" class="sp-success" hidden>
                        <div class="sp-success-icon"><i class="fa-solid fa-check"></i></div>
                        <h4>Payment Complete</h4>
                        <p id="paySuccessMsg">Your wallet has been charged.</p>
                        <div class="sp-success-ref" id="paySuccessRef">--</div>
                        <div class="d-flex justify-content-center mt-4">
                            <button type="button" class="sp-btn-pay" id="paySuccessDone">Done</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
    <script>
    const CSRF = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
    const VALIDATE_API = "<?= STUDENT_URL ?>/api/validate_qr.php";
    const PAY_API = "<?= STUDENT_URL ?>/pay_qr.php";
    const PESOS_PER_GC = <?= GJC_PESOS_PER_GC ?>;

    const video = document.getElementById("qrVideo");
    const canvas = document.getElementById("qrCanvas");
    const canvasContext = canvas.getContext("2d", { willReadFrequently: true });
    const scanFrame = document.getElementById("scanFrame");
    const cameraStatus = document.getElementById("cameraStatus");
    const frameMsg = document.getElementById("frameMsg");
    const frameMsgTitle = document.getElementById("frameMsgTitle");
    const frameMsgSub = document.getElementById("frameMsgSub");
    const retryCameraBtn = document.getElementById("retryCameraBtn");
    const scanResultText = document.getElementById("scanResultText");
    const mobileToast = document.getElementById("mobileToast");
    const mobileToastText = document.getElementById("mobileToastText");

    const manualModal = bootstrap.Modal.getOrCreateInstance(document.getElementById("manualModal"));
    const manualInput = document.getElementById("manualCodeInput");
    const manualError = document.getElementById("manualCodeError");
    const manualSubmitBtn = document.getElementById("manualSubmitBtn");

    const confirmModalEl = document.getElementById("confirmModal");
    const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
    const payConfirmView = document.getElementById("payConfirmView");
    const paySuccessView = document.getElementById("paySuccessView");
    const payError = document.getElementById("payError");
    const payNowBtn = document.getElementById("payNowBtn");

    const money = n => "₱" + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    // Smart GC formatting: whole numbers stay whole ("2"), otherwise up to 2 decimals ("14.59").
    const gc = pesos => (+((pesos / PESOS_PER_GC).toFixed(2))).toLocaleString(undefined, { maximumFractionDigits: 2 });

    let activeStream = null;
    let scanLoopRunning = false;
    let currentFacingMode = "environment";
    let scanningPaused = false;
    let pendingToken = null;
    let paymentDone = false;
    let lastDetectedPayload = "";
    let toastTimer = null;

    // ── UI helpers ──────────────────────────────────────────────────────
    function setStatus(text, tone = "") {
        cameraStatus.className = "sp-status" + (tone ? " is-" + tone : "");
        cameraStatus.textContent = text;
    }

    function showResult(message, tone = "") {
        scanResultText.className = "sp-result-text" + (tone ? " is-" + tone : "");
        scanResultText.textContent = message;

        // Mirror into the floating toast on the mobile full-screen scanner.
        mobileToastText.textContent = message;
        mobileToast.className = "sp-mobile-toast is-visible" + (tone === "error" ? " is-error" : "");
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => mobileToast.classList.remove("is-visible"), 4500);
    }

    function resetResult() {
        scanResultText.className = "sp-result-text";
        scanResultText.textContent = "No QR detected yet.";
        mobileToast.classList.remove("is-visible");
    }

    function showFrameMsg(title, sub = "", isError = false, withRetry = false) {
        frameMsg.style.display = "grid";
        frameMsg.className = "sp-frame-msg" + (isError ? " is-error" : "");
        frameMsgTitle.textContent = title;
        frameMsgSub.textContent = sub;
        retryCameraBtn.hidden = !withRetry;
    }

    function hideFrameMsg() {
        frameMsg.style.display = "none";
    }

    function setPaused(paused) {
        scanningPaused = paused;
        scanFrame.classList.toggle("is-paused", paused);
    }

    // ── Camera ──────────────────────────────────────────────────────────
    function stopScannerStream() {
        if (activeStream) {
            activeStream.getTracks().forEach(track => track.stop());
            activeStream = null;
        }
    }

    async function startScanner(facingMode = currentFacingMode) {
        stopScannerStream();
        setPaused(false);
        currentFacingMode = facingMode;
        showFrameMsg("Opening camera…");
        setStatus("Starting Camera");

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: currentFacingMode }
            });
            activeStream = stream;
            video.srcObject = stream;
            hideFrameMsg();
            setStatus("Camera Active", "active");
            if (!scanLoopRunning) {
                scanLoopRunning = true;
                requestAnimationFrame(scanLoop);
            }
        } catch (error) {
            setStatus("Camera Blocked", "blocked");
            showFrameMsg(
                "Camera access denied.",
                "Please allow camera permissions, or enter the merchant's code manually.",
                true,
                true
            );
        }
    }

    function scanLoop() {
        if (!activeStream) {
            scanLoopRunning = false;
            return;
        }

        if (!scanningPaused && video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvasContext.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = canvasContext.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "attemptBoth",
            });

            if (code && code.data && code.data !== lastDetectedPayload) {
                lastDetectedPayload = code.data;
                handleDetected(code.data);
            }
        }

        requestAnimationFrame(scanLoop);
    }

    // ── QR payload → token ─────────────────────────────────────────────
    function extractToken(raw) {
        const str = String(raw || "").trim();
        if (!str) {
            return null;
        }
        if (/^[0-9a-f]{32,64}$/i.test(str)) {
            return str;
        }
        try {
            const obj = JSON.parse(str);
            if (obj && obj.type === "payment" && obj.token) {
                return String(obj.token);
            }
        } catch (ignored) {}
        try {
            const url = new URL(str);
            const token = url.searchParams.get("token");
            if (token) {
                return token;
            }
        } catch (ignored) {}
        return null;
    }

    // ── Server validation (single source of truth for payment details) ──
    async function validateCode(code) {
        let data;
        try {
            const response = await fetch(VALIDATE_API, {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF },
                body: JSON.stringify({ code })
            });
            data = await response.json();
        } catch (error) {
            throw new Error("Could not reach the server. Check your connection and try again.");
        }
        if (!data.success) {
            throw new Error(data.message || "Unable to validate this code.");
        }
        return data;
    }

    async function handleDetected(raw) {
        const token = extractToken(raw);
        if (!token) {
            showResult("Unsupported QR format. Ask the merchant for a GenPay payment QR.", "error");
            return;
        }

        setPaused(true);
        setStatus("QR Detected", "active");
        showResult("QR detected — fetching payment details…");

        try {
            const data = await validateCode(token);
            showResult("Review the payment details and confirm.", "ok");
            openConfirm(data);
        } catch (error) {
            showResult(error.message, "error");
            setPaused(false);
            if (activeStream) {
                setStatus("Camera Active", "active");
            }
        }
    }

    // ── Confirmation + payment ──────────────────────────────────────────
    function openConfirm(data) {
        pendingToken = data.token;
        paymentDone = false;

        document.getElementById("cmAmount").textContent = gc(data.amount) + " GC";
        document.getElementById("cmMerchant").textContent = data.merchant || "--";
        document.getElementById("cmItem").textContent = data.description || "--";
        document.getElementById("cmPhp").textContent = money(data.amount);

        payError.style.display = "none";
        payError.textContent = "";
        payNowBtn.disabled = false;
        payNowBtn.innerHTML = '<i class="fa-solid fa-lock me-1"></i> Pay Now';
        payConfirmView.hidden = false;
        paySuccessView.hidden = true;

        setPaused(true);
        confirmModal.show();
    }

    async function payNow() {
        if (!pendingToken) {
            return;
        }

        payNowBtn.disabled = true;
        payNowBtn.textContent = "Processing…";
        payError.style.display = "none";

        let result;
        try {
            const response = await fetch(PAY_API, {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF },
                body: JSON.stringify({ token: pendingToken })
            });
            const text = await response.text();
            try {
                result = JSON.parse(text);
            } catch (parseError) {
                throw new Error("Payment server returned an invalid response. Please refresh and log in again.");
            }
        } catch (error) {
            payError.textContent = error.message || "Payment request failed.";
            payError.style.display = "block";
            payNowBtn.disabled = false;
            payNowBtn.innerHTML = '<i class="fa-solid fa-lock me-1"></i> Pay Now';
            return;
        }

        if (result.success) {
            paymentDone = true;
            pendingToken = null;

            document.getElementById("paySuccessMsg").textContent =
                gc(result.amount) + " GC (≈ " + money(result.amount) + ") paid to " + (result.merchant || "the merchant") + ".";
            document.getElementById("paySuccessRef").textContent = result.reference || "";

            if (typeof result.balance === "number") {
                document.getElementById("sdBalance").textContent = gc(result.balance);
                document.getElementById("sdBalancePhp").textContent =
                    Number(result.balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            payConfirmView.hidden = true;
            paySuccessView.hidden = false;
            showResult("Payment completed. Reference: " + (result.reference || ""), "ok");
            stopScannerStream();
            return;
        }

        // Distinct server errors: insufficient_balance, expired, already_paid,
        // invalid_code, wallet_frozen, limit_reached… Terminal ones close the
        // door on retrying the same code.
        payError.textContent = result.message || "Payment failed.";
        payError.style.display = "block";

        const terminal = ["expired", "already_paid", "invalid_code", "out_of_stock"];
        if (terminal.includes(result.code)) {
            payNowBtn.disabled = true;
            payNowBtn.textContent = "Code no longer valid";
            pendingToken = null;
        } else {
            payNowBtn.disabled = false;
            payNowBtn.innerHTML = '<i class="fa-solid fa-lock me-1"></i> Pay Now';
        }
    }

    payNowBtn.addEventListener("click", payNow);

    document.getElementById("paySuccessDone").addEventListener("click", () => window.location.reload());

    // Cancel / X / programmatic hide: if nothing was paid, resume scanning.
    confirmModalEl.addEventListener("hidden.bs.modal", function () {
        if (paymentDone) {
            window.location.reload();
            return;
        }
        pendingToken = null;
        lastDetectedPayload = "";
        setPaused(false);
        resetResult();
        if (activeStream) {
            setStatus("Camera Active", "active");
        }
    });

    // ── Manual code entry ───────────────────────────────────────────────
    function openManualModal() {
        manualInput.value = "";
        manualError.style.display = "none";
        manualSubmitBtn.disabled = false;
        manualSubmitBtn.textContent = "Find Payment";
        setPaused(true);
        manualModal.show();
    }

    document.getElementById("manualBtn").addEventListener("click", openManualModal);
    document.getElementById("manualBtnMobile").addEventListener("click", openManualModal);

    document.getElementById("manualModal").addEventListener("shown.bs.modal", () => manualInput.focus());

    document.getElementById("manualModal").addEventListener("hidden.bs.modal", function () {
        // Only resume if the confirm modal isn't taking over.
        if (!confirmModalEl.classList.contains("show") && !paymentDone) {
            lastDetectedPayload = "";
            setPaused(false);
        }
    });

    async function submitManualCode() {
        const code = manualInput.value.trim();
        if (code.replace(/[^0-9a-zA-Z]/g, "").length < 6) {
            manualError.textContent = "Enter the full code shown on the merchant's screen.";
            manualError.style.display = "block";
            return;
        }

        manualSubmitBtn.disabled = true;
        manualSubmitBtn.textContent = "Checking…";
        manualError.style.display = "none";

        try {
            const data = await validateCode(code);
            manualModal.hide();
            openConfirm(data);
        } catch (error) {
            manualError.textContent = error.message;
            manualError.style.display = "block";
        } finally {
            manualSubmitBtn.disabled = false;
            manualSubmitBtn.textContent = "Find Payment";
        }
    }

    manualSubmitBtn.addEventListener("click", submitManualCode);
    manualInput.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
            event.preventDefault();
            submitManualCode();
        }
    });

    // ── Toolbar ─────────────────────────────────────────────────────────
    document.getElementById("resumeScanBtn").addEventListener("click", function () {
        lastDetectedPayload = "";
        setPaused(false);
        resetResult();
        if (activeStream) {
            setStatus("Camera Active", "active");
        } else {
            startScanner();
        }
    });

    function switchCamera() {
        const next = currentFacingMode === "environment" ? "user" : "environment";
        lastDetectedPayload = "";
        resetResult();
        startScanner(next);
    }

    document.getElementById("switchCameraBtn").addEventListener("click", switchCamera);
    document.getElementById("switchCameraBtnM").addEventListener("click", switchCamera);

    retryCameraBtn.addEventListener("click", () => startScanner());

    window.addEventListener("beforeunload", stopScannerStream);

    // ── Boot ────────────────────────────────────────────────────────────
    resetResult();
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        setStatus("Camera Unsupported", "blocked");
        showFrameMsg(
            "This browser does not support camera scanning.",
            "Use Enter code manually instead — the merchant's screen shows a short code under the QR.",
            true,
            false
        );
    } else {
        startScanner();
    }
    </script>

</body>

</html>
