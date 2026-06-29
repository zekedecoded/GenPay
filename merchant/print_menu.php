<?php
// ============================================================
//  merchant/print_menu.php
//  Printable cardboard-menu sheet: every available product with its
//  scan QR (encodes the SKU, same value student/api/cart.php looks up),
//  plus the merchant's static Wallet QR for checkout.
// ============================================================
session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['merchant']);

$currentUser = gjc_current_user($db);
$ownerMerchId = gjc_merchant_owner_id($db, (int) $currentUser['id']);
$wallet = gjc_merchant_wallet($db, $ownerMerchId);
$currentPage = 'inventory';

$stmt = $db->prepare(
    "SELECT m.stall_name, m.stall_id, s.label AS stall_label
       FROM merchant m
       LEFT JOIN stalls s ON s.stall_id = m.stall_id
      WHERE m.userID = ?
      LIMIT 1"
);
$stmt->execute([$ownerMerchId]);
$merchant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$merchantName = $merchant['stall_name'] ?? $currentUser['name'];

$items = [];
if (gjc_table_exists($db, 'merchant_inventory')) {
    $stmt = $db->prepare(
        "SELECT sku, product_name, description, category, price
           FROM merchant_inventory
          WHERE merchant_user_id = ? AND is_available = 1 AND is_restricted = 0 AND sku IS NOT NULL AND sku != ''
          ORDER BY category ASC, product_name ASC"
    );
    $stmt->execute([$ownerMerchId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[$row['category']][] = $row;
    }
}

$skuLessCount = 0;
if (gjc_table_exists($db, 'merchant_inventory')) {
    $skuStmt = $db->prepare(
        "SELECT COUNT(*) FROM merchant_inventory
          WHERE merchant_user_id = ? AND is_available = 1 AND is_restricted = 0 AND (sku IS NULL OR sku = '')"
    );
    $skuStmt->execute([$ownerMerchId]);
    $skuLessCount = (int) $skuStmt->fetchColumn();
}

$walletQrPayload = json_encode([
    'type' => 'merchant_wallet',
    'merchant_wallet_id' => $wallet['id'],
    'merchant_user_id' => $ownerMerchId,
    'merchant' => $merchantName,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$walletQrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&ecc=H&margin=10&data=' . rawurlencode($walletQrPayload);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Menu | <?= gjc_e($merchantName) ?></title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=18">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .menu-print-toolbar {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }
        .menu-sheet {
            background: #fff; border-radius: 20px; padding: 32px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08); max-width: 900px; margin: 0 auto;
        }
        .menu-sheet-header { text-align: center; margin-bottom: 24px; }
        .menu-sheet-header h1 { font-weight: 900; color: #064420; margin: 0 0 4px; }
        .menu-sheet-header p { color: #6b7280; margin: 0; font-size: 13px; }
        .menu-category-title {
            font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em;
            color: #064420; margin: 24px 0 12px; padding-bottom: 6px; border-bottom: 2px solid #e5e7eb;
        }
        .menu-item-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px;
        }
        .menu-item-card {
            border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px;
            text-align: center; break-inside: avoid;
        }
        .menu-item-card img { width: 110px; height: 110px; margin-bottom: 8px; }
        .menu-item-name { font-weight: 800; font-size: 13.5px; color: #102018; margin: 0 0 2px; }
        .menu-item-desc { font-size: 11px; color: #9ca3af; margin: 0 0 4px; }
        .menu-item-price { font-weight: 900; font-size: 15px; color: #064420; margin: 0; }
        .menu-item-sku { font-size: 10px; color: #9ca3af; }
        .menu-checkout-box {
            margin-top: 32px; padding: 20px; border-radius: 16px;
            background: #f8fafc; border: 1px dashed #064420;
            display: flex; align-items: center; gap: 20px; justify-content: center; flex-wrap: wrap;
        }
        .menu-checkout-box img { width: 130px; height: 130px; }
        .menu-checkout-text strong { display: block; color: #064420; font-size: 15px; margin-bottom: 4px; }
        .menu-checkout-text span { font-size: 12.5px; color: #6b7280; }

        @media print {
            .merchant-sidebar, .merchant-topbar, .menu-print-toolbar, .menu-skuless-note {
                display: none !important;
            }
            .merchant-main { margin: 0 !important; width: 100% !important; padding: 0 !important; }
            .menu-sheet { box-shadow: none; max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="merchant-layout">
    <?php require __DIR__ . '/../includes/partials/' . (gjc_is_merchant_staff() ? 'sidebar_merchant_staff.php' : 'sidebar_merchant_admin.php'); ?>

    <main class="merchant-main">
        <div class="menu-print-toolbar">
            <div>
                <h1 class="mb-0" style="font-size:22px">Print Full Menu</h1>
                <p class="text-muted mb-0">Every available item with its scan QR, ready for your cardboard menu.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= MERCHANT_URL ?>/inventory.php" class="btn btn-outline-secondary">Back to Inventory</a>
                <button type="button" class="btn btn-success" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Menu</button>
            </div>
        </div>

        <?php if ($skuLessCount > 0): ?>
        <div class="alert alert-warning menu-skuless-note">
            &#9888; <?= $skuLessCount ?> available item(s) are missing a SKU and were left off this menu &mdash;
            add a SKU to each in <a href="<?= MERCHANT_URL ?>/inventory.php">Inventory</a> so students can scan them.
        </div>
        <?php endif; ?>

        <div class="menu-sheet">
            <div class="menu-sheet-header">
                <h1><?= gjc_e($merchantName) ?></h1>
                <p><?= !empty($merchant['stall_id']) ? 'Stall ' . gjc_e($merchant['stall_id']) : '' ?><?= !empty($merchant['stall_label']) ? ' &middot; ' . gjc_e($merchant['stall_label']) : '' ?></p>
                <p>Scan an item's QR with the GenPay Shop Cart, then scan the Wallet QR below to pay.</p>
            </div>

            <?php if (empty($items)): ?>
            <p class="text-center text-muted py-5">No available products with a SKU yet. Add SKUs in Inventory first.</p>
            <?php else: ?>
                <?php foreach ($items as $category => $catItems): ?>
                <div class="menu-category-title"><?= gjc_e(ucwords($category)) ?></div>
                <div class="menu-item-grid">
                    <?php foreach ($catItems as $item): ?>
                    <?php $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&ecc=H&margin=8&data=' . rawurlencode($item['sku']); ?>
                    <div class="menu-item-card">
                        <img src="<?= htmlspecialchars($qrUrl) ?>" alt="<?= gjc_e($item['product_name']) ?> QR">
                        <div class="menu-item-name"><?= gjc_e($item['product_name']) ?></div>
                        <?php if ($item['description']): ?>
                        <div class="menu-item-desc"><?= gjc_e($item['description']) ?></div>
                        <?php endif; ?>
                        <div class="menu-item-price"><?= gjc_money($item['price']) ?></div>
                        <div class="menu-item-sku"><?= gjc_e($item['sku']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="menu-checkout-box">
                <img src="<?= htmlspecialchars($walletQrImageUrl) ?>" alt="Shop Wallet QR">
                <div class="menu-checkout-text">
                    <strong>Step 2: Pay your total</strong>
                    <span>Scan this Wallet QR in the Shop Cart's "Pay Now" mode once you're done adding items.</span>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
</body>
</html>
