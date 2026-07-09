<?php
// ============================================================
//  merchant/settings.php
//  Business Profile - lets the merchant admin edit the display name
//  and logo that appear publicly on the Stall Directory (stalls.php).
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['merchant']);
if (gjc_is_merchant_staff()) {
    header('Location: ' . DASHBOARD_URL);
    exit;
}

$currentUser = gjc_current_user($db);
$userId = (int) $currentUser['id'];

$wallet = gjc_merchant_wallet($db, $userId);

$stmt = $db->prepare(
    "SELECT m.merchantID, m.stall_name, m.stall_id, s.label AS stall_label, u.profile_img
       FROM merchant m
       LEFT JOIN stalls s ON s.stall_id = m.stall_id
       LEFT JOIN users  u ON u.userID = m.userID
      WHERE m.userID = ?
      LIMIT 1"
);
$stmt->execute([$userId]);
$merchant = $stmt->fetch(PDO::FETCH_ASSOC);

$currentPage = 'settings';
$logoUrl = $merchant && $merchant['profile_img'] ? BASE_URL . '/' . $merchant['profile_img'] : null;

$walletDisplayName = $merchant['stall_name'] ?? $currentUser['name'];
$walletQrPayload = json_encode([
    'type' => 'merchant_wallet',
    'merchant_wallet_id' => $wallet['id'],
    'merchant_user_id' => $userId,
    'merchant' => $walletDisplayName,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$walletQrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&ecc=H&margin=12&data=' . rawurlencode($walletQrPayload);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Profile | GenPay Merchant</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=25">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant_settings.css?v=1">
</head>
<body>
<div class="merchant-layout">
    <?php require __DIR__ . '/../includes/partials/sidebar_merchant_admin.php'; ?>

    <main class="merchant-main">
        <header class="merchant-topbar">
            <button class="merchant-menu-btn" onclick="document.getElementById('merchantSidebar').classList.toggle('collapsed')">&#9776;</button>
            <div>
                <h1>Business Profile</h1>
                <p>This display name and logo appear publicly on the Stall Directory.</p>
            </div>
            <div class="merchant-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="merchant-avatar"><i class="fa-solid fa-store"></i></div>
            </div>
        </header>

        <?php if (!$merchant): ?>
        <section class="merchant-premium-panel">
            <p class="text-muted mb-0">No merchant record is linked to this account yet.</p>
        </section>
        <?php else: ?>
        <section class="merchant-premium-panel">
            <div class="merchant-panel-header">
                <div>
                    <h3>Stall Directory Listing</h3>
                    <p>Stall <?= gjc_e($merchant['stall_id'] ?? 'Not yet assigned') ?><?= $merchant['stall_label'] ? ' - ' . gjc_e($merchant['stall_label']) : '' ?></p>
                </div>
            </div>

            <form id="profileForm" enctype="multipart/form-data" class="mt-3">
                <div class="row g-4 align-items-start">
                    <div class="col-12 col-md-3 text-center">
                        <?php if ($logoUrl): ?>
                        <img id="logoPreview" class="profile-logo-preview" src="<?= htmlspecialchars($logoUrl) ?>" alt="Current logo">
                        <?php else: ?>
                        <div id="logoPreview" class="profile-logo-fallback"><?= htmlspecialchars(mb_substr($merchant['stall_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <label class="btn btn-outline-secondary btn-sm mt-3 w-100" for="logoInput">Change Logo</label>
                        <input type="file" id="logoInput" name="logo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="d-none">
                        <div class="form-text">JPG or PNG, max 5 MB.</div>
                    </div>
                    <div class="col-12 col-md-9">
                        <label class="form-label fw-semibold">Display Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="stall_name" id="stallNameInput"
                               value="<?= htmlspecialchars($merchant['stall_name']) ?>" maxlength="255" required>
                        <div class="form-text">Shown as your company name on the public Stall Directory.</div>

                        <div id="profileMsg" class="mt-3"></div>
                        <button type="submit" class="login-btn mt-3" id="profileSubmitBtn">Save Changes</button>
                    </div>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <section class="merchant-premium-panel mt-4" id="walletQrPanel">
            <div class="merchant-panel-header d-flex justify-content-between align-items-center">
                <div>
                    <h3>Shop Wallet QR</h3>
                    <p>Print this once and tape it to your cardboard menu. Students scan it to pay for their whole cart &mdash; it never expires and carries no fixed amount.</p>
                </div>
                <button type="button" class="merchant-view-btn" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print
                </button>
            </div>

            <div class="wallet-qr-print-card">
                <img src="<?= htmlspecialchars($walletQrImageUrl) ?>" alt="Shop Wallet QR" class="wallet-qr-image">
                <div class="wallet-qr-caption">
                    <strong><?= gjc_e($walletDisplayName) ?></strong>
                    <span>Scan to pay your GenPay cart total</span>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
const PROFILE_API = '<?= MERCHANT_URL ?>/api/profile.php';

document.getElementById('logoInput')?.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        let img = document.getElementById('logoPreview');
        if (img.tagName !== 'IMG') {
            const newImg = document.createElement('img');
            newImg.id = 'logoPreview';
            newImg.className = 'profile-logo-preview';
            img.replaceWith(newImg);
            img = newImg;
        }
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
});

document.getElementById('profileForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('profileSubmitBtn');
    const msg = document.getElementById('profileMsg');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    try {
        const r = await fetch(PROFILE_API, { method: 'POST', body: new FormData(this) });
        const d = await r.json();
        msg.innerHTML = `<div class="alert ${d.success ? 'alert-success' : 'alert-danger'} mb-0">${d.message}</div>`;
    } catch (err) {
        msg.innerHTML = '<div class="alert alert-danger mb-0">Unable to contact the server. Please try again.</div>';
    }
    btn.disabled = false;
    btn.textContent = 'Save Changes';
});
</script>
</body>
</html>
