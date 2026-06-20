<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['merchant']);

$currentUser = gjc_current_user($db);
$ownerMerchId = gjc_merchant_owner_id($db, (int) $currentUser['id']);
$wallet = gjc_merchant_wallet($db, $ownerMerchId);
$collectedBalance = $wallet['balance'];
$merchantName = $currentUser['name'];

$currentPage = 'qrcode';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate QR | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=11">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="merchant-layout">

        <?php require __DIR__ . '/../includes/partials/' . (gjc_is_merchant_staff() ? 'sidebar_merchant_staff.php' : 'sidebar_merchant_admin.php'); ?>

        <main class="merchant-main">

            <header class="merchant-topbar">
                <button class="merchant-menu-btn" onclick="toggleMerchantSidebar()">Menu</button>

                <div>
                    <h1>Generate Payment QR</h1>
                    <p>Create item-based QR codes for quick student wallet payments.</p>
                </div>

                <div class="merchant-user">
                    <span><?php echo gjc_e($merchantName); ?></span>
                    <div class="merchant-avatar">
                        <img src="<?= ICONS_URL ?>/store.png" alt="Merchant">
                    </div>
                </div>
            </header>

            <section class="qr-balance-card mb-4">
                <div>
                    <span>Collected Balance</span>
                    <h2><?php echo gjc_money($collectedBalance); ?></h2>
                    <p><?php echo gjc_e($merchantName); ?></p>
                </div>

                <div class="qr-balance-badge">
                    Merchant
                </div>
            </section>

            <section class="qr-layout-grid mb-4">

                <div class="merchant-premium-panel qr-form-panel">
                    <div class="merchant-panel-header">
                        <div>
                            <h3>Payment Details</h3>
                            <p>Enter the item price and description to generate a temporary QR code.</p>
                        </div>
                    </div>

                    <form id="qrGenerateForm" class="qr-form" onsubmit="event.preventDefault(); generateQR();">

                        <div class="qr-field">
                            <label>Price (&#8369;)</label>

                            <div class="qr-money-input">
                                <span>&#8369;</span>
                                <input type="number" name="price" id="qrPrice" placeholder="0.00" min="1" step="0.01" required>
                            </div>
                        </div>

                        <div class="qr-field">
                            <label>Item Description</label>
                            <input type="text" name="description" id="qrDesc"
                                placeholder="e.g. Lunch Combo, Bottled Water, Notebook..." required>
                        </div>

                        <button type="submit" class="qr-generate-btn">
                            Generate QR Code
                        </button>

                    </form>
                </div>

                <div class="merchant-premium-panel qr-preview-panel">
                    <div class="qr-preview-empty" id="qrPreviewArea">
                        <div class="qr-preview-icon" id="qrIconArea">
                                <img src="<?= ICONS_URL ?>/qr.png" alt="">
                        </div>

                        <h3 id="qrTitle">No QR Generated Yet</h3>
                        <p id="qrSubtitle">Fill in the price and item description, then click Generate QR Code to create one.</p>
                        
                        <div id="qrCodeContainer" style="display:none; text-align:center; margin-top:20px;"></div>
                    </div>
                </div>

            </section>

            <section class="qr-bottom-grid">

                <div class="merchant-premium-panel">
                    <div class="merchant-panel-header">
                        <div>
                            <h3>QR Instructions</h3>
                            <p>How merchant payment QR codes work.</p>
                        </div>
                    </div>

                    <div class="qr-instruction-list">
                        <div>
                            <strong>1</strong>
                            <span>Enter the price and item description.</span>
                        </div>

                        <div>
                            <strong>2</strong>
                            <span>Show the QR code to the student customer.</span>
                        </div>

                        <div>
                            <strong>3</strong>
                            <span>Student scans and confirms the wallet payment.</span>
                        </div>

                        <div>
                            <strong>4</strong>
                            <span>Payment appears in your sales history.</span>
                        </div>
                    </div>
                </div>

                <div class="merchant-premium-panel">
                    <div class="merchant-panel-header">
                        <div>
                            <h3>QR Settings</h3>
                            <p>Temporary payment QR status.</p>
                        </div>
                    </div>

                    <div class="qr-setting-box">
                        <span>QR Expiration</span>
                        <strong>15 minutes</strong>
                        <p>Each generated QR code automatically expires for transaction safety.</p>
                    </div>
                </div>

            </section>

        </main>

    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>

    <script>
    function toggleMerchantSidebar() {
        document.getElementById("merchantSidebar").classList.toggle("collapsed");
    }

    function generateQR() {
        const price = document.getElementById('qrPrice').value;
        const desc = document.getElementById('qrDesc').value;
        const merchant = "<?php echo addslashes($merchantName); ?>";
        const merchantWalletId = <?php echo (int) $wallet['id']; ?>;
        
        const payload = JSON.stringify({
            merchant: merchant,
            merchant_wallet_id: merchantWalletId,
            price: price,
            desc: desc,
            type: "payment"
        });
        
        document.getElementById('qrTitle').textContent = "QR Code Generated";
        document.getElementById('qrSubtitle').innerHTML = "Scan this code to pay <strong>&#8369;" + parseFloat(price).toFixed(2) + "</strong> for " + desc;
        document.getElementById('qrIconArea').style.display = 'none';
        
        const container = document.getElementById('qrCodeContainer');
        container.style.display = 'block';
        container.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(payload) + '" alt="QR Code">';
    }
    </script>

</body>

</html>
