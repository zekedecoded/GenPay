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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Cart | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=56">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>

    <link rel="stylesheet" href="<?= CSS_URL ?>/cart.css?v=1">
</head>

<body>

    <div class="student-layout">

        <aside class="student-sidebar" id="studentSidebar">

            <div class="student-brand">
                <div class="student-brand-logo">
                    <img src="<?= ICONS_URL ?>/GenDeJesusFavicon.png" alt="GJC Logo">
                </div>

                <div class="student-brand-text">
                    <h4>GenPay</h4>
                    <span>Student Portal</span>
                </div>
            </div>

            <nav class="student-menu">
                <a href="<?= DASHBOARD_URL ?>">
                    <i class="fa-solid fa-gauge-high student-nav-icon"></i>
                    <span class="student-nav-text">Dashboard</span>
                </a>

                <a href="<?= STUDENT_URL ?>/cart.php" class="active">
                    <i class="fa-solid fa-cart-shopping student-nav-icon"></i>
                    <span class="student-nav-text">Shop Cart</span>
                </a>

                <a href="<?= STUDENT_URL ?>/transfer.php">
                    <i class="fa-solid fa-money-bill-transfer student-nav-icon"></i>
                    <span class="student-nav-text">Send GenCoin</span>
                </a>

                <a href="<?= STUDENT_URL ?>/withdraw.php">
                    <i class="fa-solid fa-money-bill-wave student-nav-icon"></i>
                    <span class="student-nav-text">Withdraw</span>
                </a>

                <a href="<?= STUDENT_URL ?>/topup_request.php">
                    <i class="fa-solid fa-circle-plus student-nav-icon"></i>
                    <span class="student-nav-text">Top-Up</span>
                </a>

                <a href="<?= STUDENT_URL ?>/history.php">
                    <i class="fa-solid fa-receipt student-nav-icon"></i>
                    <span class="student-nav-text">History</span>
                </a>

                <a href="<?= STUDENT_URL ?>/profile.php">
                    <i class="fa-solid fa-user student-nav-icon"></i>
                    <span class="student-nav-text">Profile</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="student-logout"
               onclick="openLogoutModal(event);">
                <i class="fa-solid fa-arrow-right-from-bracket student-logout-icon"></i>
                <span>Logout</span>
            </a>

        </aside>
        <?php require __DIR__ . '/../includes/partials/logout_modal.php'; ?>

        <main class="student-main">

            <header class="student-topbar">
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">&#9776;</button>

                <div>
                    <h1>Shop Cart</h1>
                    <p>Scan each item's barcode, submit your order, then pay at the counter by scanning the shop's Wallet QR.</p>
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
                            <h3>Scanner</h3>
                            <p id="scannerHint">Point the camera at an item's barcode on the cardboard menu.</p>
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
                        <button type="button" class="scan-action-btn secondary" id="switchCameraBtn">Switch Camera</button>
                    </div>

                    <div id="cartAlerts" class="mt-3"></div>
                </div>

                <div class="student-premium-panel scan-guide-panel">
                    <div class="student-panel-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3 id="cartPanelTitle">Your Cart</h3>
                            <p id="cartMerchantHint">No items scanned yet.</p>
                        </div>
                    </div>

                    <div id="cartMerchantPillWrap"></div>

                    <!-- Builder view: shown while there's no pending order yet -->
                    <div id="cartBuilderView">
                        <div class="cart-line-list" id="cartLineList">
                            <div class="cart-empty" id="cartEmptyState">Scan an item to start your order.</div>
                        </div>

                        <div class="cart-total-row">
                            <span>Total</span>
                            <span id="cartTotal">&#8369;0.00</span>
                        </div>

                        <button type="button" class="scan-action-btn mt-3 w-100" id="submitOrderBtn" disabled>
                            Submit Order
                        </button>
                        <button type="button" class="scan-action-btn secondary mt-2 w-100" id="clearCartBtn">
                            &#10005; Clear Cart
                        </button>
                    </div>

                    <!-- Pending-order view: shown after Submit Order, until paid or cancelled -->
                    <div id="pendingOrderView" class="d-none">
                        <div class="text-center">
                            <span class="pending-order-badge">Awaiting Payment</span>
                            <h4 class="mb-1" id="pendingOrderRef">--</h4>
                        </div>

                        <ul class="list-unstyled" id="pendingOrderLineList" style="font-size:13px;color:#475569;"></ul>

                        <div class="cart-total-row">
                            <span>Total</span>
                            <span id="pendingOrderTotal">&#8369;0.00</span>
                        </div>

                        <p class="pending-order-note">Go to the counter and scan the shop's Wallet QR to pay.</p>

                        <button type="button" class="scan-action-btn secondary mt-2 w-100" id="cancelOrderBtn">
                            Cancel Order
                        </button>
                    </div>
                </div>

            </section>

        </main>

    </div>

    <div class="modal fade" id="cartPayConfirmModal" tabindex="-1" aria-labelledby="cartPayConfirmModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cartPayConfirmModalTitle">Confirm Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="scan-payment-card" style="margin-top:0;">
                        <div class="scan-payment-grid">
                            <div>
                                <label>Merchant</label>
                                <strong id="payConfirmMerchant">--</strong>
                            </div>
                            <div>
                                <label>Items</label>
                                <strong id="payConfirmItemCount">--</strong>
                            </div>
                            <div>
                                <label>Total</label>
                                <strong id="payConfirmTotal">--</strong>
                            </div>
                        </div>
                    </div>

                    <ul class="list-unstyled mt-3 mb-0" id="payConfirmLineList" style="font-size:13px;color:#475569;"></ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="scan-action-btn secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="scan-action-btn" id="cartPayConfirmBtn">Confirm Payment</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>

    <script>
    function toggleStudentSidebar() {
        document.getElementById("studentSidebar").classList.toggle("collapsed");
    }

    document.querySelector(".student-menu a.active")?.scrollIntoView({ inline: "center", block: "nearest" });

    const CART_API = "<?= STUDENT_URL ?>/api/cart.php";
    const CHECKOUT_API = "<?= STUDENT_URL ?>/api/checkout.php";

    const video = document.getElementById("qrVideo");
    const canvas = document.getElementById("qrCanvas");
    const canvasContext = canvas.getContext("2d");
    const cameraMessage = document.getElementById("cameraMessage");
    const cameraStatus = document.getElementById("cameraStatus");
    const scannerHint = document.getElementById("scannerHint");
    const switchCameraBtn = document.getElementById("switchCameraBtn");
    const submitOrderBtn = document.getElementById("submitOrderBtn");
    const clearCartBtn = document.getElementById("clearCartBtn");
    const cancelOrderBtn = document.getElementById("cancelOrderBtn");
    const cartAlerts = document.getElementById("cartAlerts");
    const cartLineList = document.getElementById("cartLineList");
    const cartTotal = document.getElementById("cartTotal");
    const cartMerchantHint = document.getElementById("cartMerchantHint");
    const cartMerchantPillWrap = document.getElementById("cartMerchantPillWrap");
    const cartPanelTitle = document.getElementById("cartPanelTitle");
    const cartBuilderView = document.getElementById("cartBuilderView");
    const pendingOrderView = document.getElementById("pendingOrderView");
    const pendingOrderRef = document.getElementById("pendingOrderRef");
    const pendingOrderLineList = document.getElementById("pendingOrderLineList");
    const pendingOrderTotal = document.getElementById("pendingOrderTotal");

    const cartPayConfirmModalEl = document.getElementById("cartPayConfirmModal");
    const cartPayConfirmModal = bootstrap.Modal.getOrCreateInstance(cartPayConfirmModalEl);
    const cartPayConfirmBtn = document.getElementById("cartPayConfirmBtn");
    const payConfirmMerchant = document.getElementById("payConfirmMerchant");
    const payConfirmItemCount = document.getElementById("payConfirmItemCount");
    const payConfirmTotal = document.getElementById("payConfirmTotal");
    const payConfirmLineList = document.getElementById("payConfirmLineList");

    let activeStream = null;
    let currentFacingMode = "environment";
    let scanCooldown = false;
    let scanningPaused = false;
    let lastDetectedPayload = "";
    let currentPendingOrder = null; // set once the student has Submitted an Order, cleared on pay/cancel
    let pendingWalletPayload = null;

    function setCameraStatus(text, tone = "") {
        cameraStatus.className = "scan-status-badge";
        if (tone) cameraStatus.classList.add(tone);
        cameraStatus.textContent = text;
    }

    function showAlert(message, type = "danger") {
        cartAlerts.innerHTML = `<div class="alert alert-${type} py-2 px-3 mb-0" style="font-size:13px">${message}</div>`;
        clearTimeout(window.__cartAlertTimer);
        window.__cartAlertTimer = setTimeout(() => { cartAlerts.innerHTML = ""; }, 4500);
    }

    function escapeHtml(value) {
        const div = document.createElement("div");
        div.textContent = value ?? "";
        return div.innerHTML;
    }

    function renderCart(snapshot) {
        const lines = snapshot.lines || [];

        if (snapshot.merchant_label) {
            cartMerchantHint.textContent = "Shopping at:";
            cartMerchantPillWrap.innerHTML = `<span class="cart-merchant-pill">${escapeHtml(snapshot.merchant_label)}</span>`;
        } else {
            cartMerchantHint.textContent = "No items scanned yet.";
            cartMerchantPillWrap.innerHTML = "";
        }

        if (!lines.length) {
            cartLineList.innerHTML = '<div class="cart-empty" id="cartEmptyState">Scan an item to start your order.</div>';
        } else {
            cartLineList.innerHTML = lines.map(line => `
                <div class="cart-line" data-item-id="${line.id}">
                    <div>
                        <div class="cart-line-name">${escapeHtml(line.name)}</div>
                        ${line.sku ? `<div class="cart-line-sku">SKU: ${escapeHtml(line.sku)}</div>` : ""}
                    </div>
                    <div class="cart-line-qty">
                        <button type="button" onclick="changeQty(${line.id}, ${line.qty - 1})">-</button>
                        <span>${line.qty}</span>
                        <button type="button" onclick="changeQty(${line.id}, ${line.qty + 1})">+</button>
                    </div>
                    <div class="cart-line-price">&#8369;${line.line_total.toFixed(2)}</div>
                    <button type="button" class="cart-line-remove" onclick="removeLine(${line.id})">&times;</button>
                </div>
            `).join("");
        }

        cartTotal.textContent = "₱" + Number(snapshot.total || 0).toFixed(2);
        submitOrderBtn.disabled = lines.length === 0;

        if (Array.isArray(snapshot.dropped) && snapshot.dropped.length) {
            const reasons = snapshot.dropped.map(d => `${escapeHtml(d.name)} (${escapeHtml(d.reason)})`).join(", ");
            showAlert(`Removed from cart: ${reasons}`, "warning");
        }
    }

    function renderPendingOrder(order) {
        currentPendingOrder = order || null;
        if (!order) return;

        pendingOrderRef.textContent = order.reference;
        const lines = order.lines || [];
        pendingOrderLineList.innerHTML = lines.map(line =>
            `<li>${line.qty}&times; ${escapeHtml(line.name)} <span class="text-muted">&mdash; ₱${line.line_total.toFixed(2)}</span></li>`
        ).join("");
        pendingOrderTotal.textContent = "₱" + Number(order.total || 0).toFixed(2);
    }

    function updateUiState() {
        const hasPending = !!currentPendingOrder;
        cartBuilderView.classList.toggle("d-none", hasPending);
        pendingOrderView.classList.toggle("d-none", !hasPending);
        cartPanelTitle.textContent = hasPending ? "Your Order" : "Your Cart";
        scannerHint.textContent = hasPending
            ? "Go to the counter and scan the shop's Wallet QR to pay."
            : "Point the camera at an item's barcode on the cardboard menu.";
    }

    async function callCartApi(action, extra = {}) {
        const form = new FormData();
        form.append("action", action);
        Object.entries(extra).forEach(([key, value]) => form.append(key, value));
        const response = await fetch(CART_API, { method: "POST", body: form });
        return response.json();
    }

    async function refreshCart() {
        try {
            const result = await callCartApi("get_cart");
            if (result.success) renderCart(result);
        } catch (error) {
            showAlert("Unable to load your cart. Check your connection.");
        }
        updateUiState();
    }

    async function loadPendingOrder() {
        try {
            const result = await callCartApi("get_pending_order");
            if (result.success) renderPendingOrder(result.order);
        } catch (error) {
            // Non-critical on page load — student can still build a cart.
        }
        updateUiState();
    }

    async function addItemByCode(code) {
        if (currentPendingOrder) {
            showAlert("You already have an order awaiting payment. Cancel it first to keep shopping.", "warning");
            return;
        }
        try {
            const result = await callCartApi("add_item", { code });
            if (!result.success) {
                showAlert(result.message || "Unable to add this item.", result.blocked ? "danger" : "warning");
                return;
            }
            renderCart(result);
            showAlert(result.message || "Item added.", "success");
        } catch (error) {
            showAlert("Unable to reach the server. Please try again.");
        }
    }

    async function changeQty(itemId, qty) {
        const result = await callCartApi("update_qty", { item_id: itemId, qty });
        if (result.success) renderCart(result);
    }

    async function removeLine(itemId) {
        const result = await callCartApi("remove_item", { item_id: itemId });
        if (result.success) renderCart(result);
    }

    clearCartBtn.addEventListener("click", async () => {
        const result = await callCartApi("clear_cart");
        if (result.success) {
            renderCart(result);
            showAlert("Cart cleared.", "success");
        }
    });

    submitOrderBtn.addEventListener("click", async () => {
        submitOrderBtn.disabled = true;
        submitOrderBtn.textContent = "Submitting...";

        try {
            const result = await callCartApi("submit_order");
            if (!result.success) {
                showAlert(result.message || "Unable to submit your order.", "warning");
                if (result.dropped) refreshCart();
                return;
            }

            renderPendingOrder(result.order);
            updateUiState();
            showAlert(result.message || "Order submitted.", "success");
        } catch (error) {
            showAlert("Unable to reach the server. Please try again.");
        } finally {
            submitOrderBtn.disabled = false;
            submitOrderBtn.textContent = "Submit Order";
        }
    });

    cancelOrderBtn.addEventListener("click", async () => {
        cancelOrderBtn.disabled = true;
        cancelOrderBtn.textContent = "Cancelling...";

        try {
            const result = await callCartApi("cancel_my_order");
            if (!result.success) {
                showAlert(result.message || "Unable to cancel this order.", "warning");
                return;
            }

            currentPendingOrder = null;
            updateUiState();
            refreshCart();
            showAlert("Order cancelled.", "success");
        } finally {
            cancelOrderBtn.disabled = false;
            cancelOrderBtn.textContent = "Cancel Order";
        }
    });

    function openPayConfirmModal(walletPayload) {
        pendingWalletPayload = walletPayload;
        scanningPaused = true;

        const order = currentPendingOrder || { lines: [], total: 0 };
        const lines = order.lines || [];

        payConfirmMerchant.textContent = walletPayload.merchant || order.merchant_label || "--";
        payConfirmItemCount.textContent = lines.reduce((sum, l) => sum + l.qty, 0) + " item(s)";
        payConfirmTotal.textContent = "₱" + Number(order.total || 0).toFixed(2);
        payConfirmLineList.innerHTML = lines.map(line =>
            `<li>${line.qty}&times; ${escapeHtml(line.name)} &mdash; ₱${line.line_total.toFixed(2)}</li>`
        ).join("");

        cartPayConfirmModal.show();
    }

    cartPayConfirmModalEl.addEventListener("hidden.bs.modal", () => {
        pendingWalletPayload = null;
        scanningPaused = false;
        lastDetectedPayload = "";
        setCameraStatus("Camera Active", "active");
    });

    cartPayConfirmBtn.addEventListener("click", async () => {
        if (!pendingWalletPayload) return;
        const walletPayload = pendingWalletPayload;

        cartPayConfirmBtn.disabled = true;
        cartPayConfirmBtn.textContent = "Processing...";

        try {
            const form = new FormData();
            form.append("action", "pay_order");
            form.append("merchant_wallet_id", walletPayload.merchant_wallet_id);
            form.append("merchant_user_id", walletPayload.merchant_user_id);
            const response = await fetch(CHECKOUT_API, { method: "POST", body: form });
            const result = await response.json();

            if (!result.success) {
                showAlert(result.message || "Payment failed.");
                cartPayConfirmModal.hide();
                return;
            }

            cartPayConfirmModal.hide();
            showAlert(`Payment completed. Reference: ${result.reference}`, "success");
            currentPendingOrder = null;
            updateUiState();
            refreshCart();
        } catch (error) {
            showAlert("Checkout isn't available right now. Please try again later.");
            cartPayConfirmModal.hide();
        } finally {
            cartPayConfirmBtn.disabled = false;
            cartPayConfirmBtn.textContent = "Confirm Payment";
        }
    });

    async function handleScannedPayload(raw) {
        let parsed = null;
        try { parsed = JSON.parse(raw); } catch (ignored) { /* not JSON, treat as plain code */ }

        if (currentPendingOrder) {
            if (!parsed || parsed.type !== "merchant_wallet") {
                showAlert("That's not a Shop Wallet QR. Ask the merchant for their wallet QR to pay.");
                return;
            }

            openPayConfirmModal(parsed);
            return;
        }

        // No pending order yet — treat the raw scanned text as an item's SKU/barcode.
        if (parsed && parsed.type === "merchant_wallet") {
            showAlert("Add items and tap \"Submit Order\" first, then scan the Wallet QR to pay.", "warning");
            return;
        }
        const code = parsed && parsed.sku ? parsed.sku : raw.trim();
        addItemByCode(code);
    }

    function stopScannerStream() {
        if (activeStream) {
            activeStream.getTracks().forEach(track => track.stop());
            activeStream = null;
        }
    }

    async function startScanner(facingMode = currentFacingMode) {
        stopScannerStream();
        currentFacingMode = facingMode;
        cameraMessage.style.display = "grid";
        cameraMessage.innerHTML = "Opening camera...";
        setCameraStatus("Starting Camera");

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: currentFacingMode } });
            activeStream = stream;
            video.srcObject = stream;
            cameraMessage.style.display = "none";
            setCameraStatus("Camera Active", "active");
            requestAnimationFrame(scanLoop);
        } catch (error) {
            setCameraStatus("Camera Blocked", "blocked");
            cameraMessage.innerHTML = "Camera access denied.<br>Please allow camera permissions.";
        }
    }

    function scanLoop() {
        if (scanningPaused) {
            requestAnimationFrame(scanLoop);
            return;
        }

        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvasContext.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = canvasContext.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "attemptBoth" });

            if (code && code.data && code.data !== lastDetectedPayload && !scanCooldown) {
                lastDetectedPayload = code.data;
                scanCooldown = true;
                handleScannedPayload(code.data).finally(() => {
                    setTimeout(() => {
                        scanCooldown = false;
                        lastDetectedPayload = "";
                    }, 1200);
                });
            }
        }
        requestAnimationFrame(scanLoop);
    }

    switchCameraBtn.addEventListener("click", () => {
        const nextFacingMode = currentFacingMode === "environment" ? "user" : "environment";
        startScanner(nextFacingMode);
    });

    window.addEventListener("beforeunload", stopScannerStream);

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        setCameraStatus("Camera Unsupported", "blocked");
        cameraMessage.innerHTML = "This browser does not support camera scanning.";
    } else {
        startScanner();
    }

    refreshCart();
    loadPendingOrder();
    </script>

</body>

</html>
