<?php
session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['merchant']);
$currentUser    = gjc_current_user($db);
$merchantUserId = $currentUser['id'];
$isStaff        = gjc_is_merchant_staff();
$ownerMerchId   = gjc_merchant_owner_id($db, $merchantUserId);
$currentPage    = 'pos';

// Fetch inventory for POS (only available, non-restricted items)
$products = [];
if (gjc_table_exists($db, 'merchant_inventory')) {
    $stmt = $db->prepare(
        "SELECT id, sku, product_name, category, unit, price, stock_qty
           FROM merchant_inventory
          WHERE merchant_user_id = ? AND is_available = 1 AND is_restricted = 0 AND stock_qty > 0
          ORDER BY category ASC, product_name ASC"
    );
    $stmt->execute([$ownerMerchId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get merchant wallet for this POS context
$wallet = gjc_merchant_wallet($db, $ownerMerchId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Terminal | GenPay Merchant</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=16">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .pos-layout { display:grid; grid-template-columns:1fr 360px; gap:20px; padding:0 0 32px; }
        .pos-products-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:12px; }
        .pos-product-card {
            background:#fff; border-radius:12px; padding:14px 12px; cursor:pointer;
            border:2px solid transparent; transition:all .2s;
            box-shadow:0 1px 4px rgba(0,0,0,.06);
        }
        .pos-product-card:hover { border-color:#10b981; box-shadow:0 4px 12px rgba(16,185,129,.15); transform:translateY(-2px); }
        .pos-product-card.in-cart { border-color:#10b981; background:#f0fdf4; }
        .pos-product-card .product-name { font-weight:700; font-size:13px; margin:0 0 4px; }
        .pos-product-card .product-price { font-size:16px; font-weight:800; color:#064420; }
        .pos-product-card .product-stock { font-size:11px; color:#9ca3af; margin-top:3px; }
        .pos-product-card .product-cat { font-size:10px; text-transform:uppercase; letter-spacing:.05em;
            color:#6b7280; background:#f3f4f6; padding:2px 6px; border-radius:4px; display:inline-block; margin-bottom:6px; }

        .pos-cart-panel { background:#fff; border-radius:16px; padding:20px; box-shadow:0 2px 12px rgba(0,0,0,.08);
            position:sticky; top:20px; display:flex; flex-direction:column; max-height:calc(100vh - 120px); }
        .pos-cart-panel h4 { font-weight:800; font-size:16px; margin:0 0 14px; }
        .cart-items-list { flex:1; overflow-y:auto; min-height:100px; }
        .cart-item { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #f3f4f6; }
        .cart-item-name { flex:1; font-size:13px; font-weight:600; }
        .cart-item-qty { display:flex; align-items:center; gap:4px; }
        .cart-item-qty button { width:24px;height:24px;border-radius:6px;border:1px solid #e5e7eb;
            background:#f9fafb;font-weight:700;font-size:13px;cursor:pointer;line-height:1; }
        .cart-item-qty span { min-width:24px;text-align:center;font-weight:700; }
        .cart-item-price { font-weight:700; font-size:13px; color:#064420; min-width:70px; text-align:right; }
        .cart-total-row { display:flex; justify-content:space-between; align-items:center;
            padding:12px 0 4px; font-weight:800; font-size:18px; border-top:2px solid #e5e7eb; margin-top:8px; }
        .pos-qr-box {
            display:none; margin-top:14px; padding:14px; border-radius:14px;
            background:#f8fafc; border:1px solid #e5e7eb; text-align:center;
        }
        .pos-qr-box img { width:220px; height:220px; max-width:100%; }
        .pos-qr-box strong { display:block; margin-bottom:4px; color:#064420; }
        .pos-qr-box span { display:block; color:#6b7280; font-size:12px; }
        .pos-charge-btn { margin-top:14px; width:100%; padding:14px; font-size:16px; font-weight:800;
            background:linear-gradient(135deg,#10b981,#064420); color:#fff; border:none; border-radius:12px; cursor:pointer;
            transition:all .2s; }
        .pos-charge-btn:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(6,68,32,.25); }
        .pos-charge-btn:disabled { opacity:.6; cursor:not-allowed; transform:none; }
        .cat-filter { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
        .cat-btn { padding:4px 12px; border-radius:20px; border:1px solid #e5e7eb; background:#fff;
            font-size:12px; font-weight:600; cursor:pointer; transition:all .15s; }
        .cat-btn.active { background:#064420; color:#fff; border-color:#064420; }
        .pos-product-toolbar { display:flex; gap:10px; align-items:center; margin-bottom:12px; }
        .pos-search-input {
            width:100%; border:1px solid #d1d5db; border-radius:10px; padding:10px 12px;
            font-size:14px; background:#fff; outline:none; transition:border-color .15s, box-shadow .15s;
        }
        .pos-search-input:focus { border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.14); }
        .pos-empty-filter { display:none; text-align:center; padding:36px 20px; color:#9ca3af; font-size:13px; }

        @media (max-width: 768px) {
            .pos-layout { grid-template-columns:1fr; }
            .pos-cart-panel { position:static; max-height:none; }
        }
    </style>
</head>
<body>
<div class="merchant-layout">
    <?php require __DIR__ . '/../includes/partials/' . (gjc_is_merchant_staff() ? 'sidebar_merchant_staff.php' : 'sidebar_merchant_admin.php'); ?>

    <main class="merchant-main">
        <header class="merchant-topbar">
            <button class="merchant-menu-btn" onclick="document.getElementById('merchantSidebar').classList.toggle('collapsed')">&#9776;</button>
            <div><h1>POS Terminal</h1><p>Select items and process student wallet payments.</p></div>
            <div class="merchant-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="merchant-avatar"><i class="fa-solid fa-store"></i></div>
            </div>
        </header>

        <div class="pos-layout">
            <!-- Products Panel -->
            <div>
                <div class="pos-product-toolbar">
                    <input type="search" class="pos-search-input" id="posProductSearch"
                        placeholder="Search products by name, SKU, or category" autocomplete="off">
                </div>

                <!-- Category filter -->
                <div class="cat-filter" id="catFilter">
                    <button class="cat-btn active" onclick="filterCat('all', this)">All</button>
                    <?php
                    $cats = array_unique(array_column($products, 'category'));
                    foreach ($cats as $cat):
                    ?>
                    <button class="cat-btn" onclick="filterCat('<?= gjc_e($cat) ?>', this)"><?= gjc_e(ucwords($cat)) ?></button>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($products)): ?>
                <div style="text-align:center;padding:60px 20px;color:#9ca3af;">
                    <p style="font-size:15px">No products available in inventory.</p>
                    <p style="font-size:13px">Ask your Merchant Admin to add items first.</p>
                </div>
                <?php else: ?>
                <div class="pos-products-grid" id="productsGrid">
                    <?php foreach ($products as $p): ?>
                    <div class="pos-product-card"
                        data-id="<?= (int) $p['id'] ?>"
                        data-name="<?= gjc_e($p['product_name']) ?>"
                        data-price="<?= (float) $p['price'] ?>"
                        data-stock="<?= (int) $p['stock_qty'] ?>"
                        data-cat="<?= gjc_e($p['category']) ?>"
                        data-sku="<?= gjc_e($p['sku'] ?? '') ?>"
                        onclick="addToCart(this)">
                        <span class="product-cat"><?= gjc_e($p['category']) ?></span>
                        <div class="product-name"><?= gjc_e($p['product_name']) ?></div>
                        <div class="product-price">&#8369;<?= number_format((float) $p['price'], 2) ?></div>
                        <div class="product-stock">Stock: <?= (int) $p['stock_qty'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="pos-empty-filter" id="posEmptyFilter">No products match your search.</div>
                <?php endif; ?>
            </div>

            <!-- Cart Panel -->
            <div class="pos-cart-panel">
                <h4>&#128722; Current Order</h4>

                <div class="cart-items-list" id="cartList">
                    <div id="cartEmpty" style="text-align:center;color:#9ca3af;padding:30px 0;font-size:13px">
                        Tap products to add them here
                    </div>
                </div>

                <div class="cart-total-row">
                    <span>Total</span>
                    <span id="cartTotal">&#8369;0.00</span>
                </div>

                <button class="pos-charge-btn" id="chargeBtn" onclick="generatePaymentQr()" disabled>
                    Generate Payment QR
                </button>

                <div class="pos-qr-box" id="posQrBox">
                    <strong>Ready for student scan</strong>
                    <span>Ask the student to open Scan &amp; Pay and scan this QR.</span>
                    <img id="posQrImage" alt="Payment QR">
                    <span id="posQrSummary"></span>
                </div>

                <button class="btn btn-outline-secondary btn-sm mt-3 w-100" onclick="clearCart()">
                    &#10005; Clear Order
                </button>
            </div>
        </div>
    </main>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
const cart    = {};
const merchantName = <?= json_encode($currentUser['name'], JSON_UNESCAPED_SLASHES) ?>;
const merchantWalletId = <?= (int) $wallet['id'] ?>;
const merchantUserId = <?= (int) $ownerMerchId ?>;
let activeCategory = 'all';

// \u2500\u2500 Category filter \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
function filterCat(cat, btn) {
    activeCategory = cat;
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyProductFilters();
}

function applyProductFilters() {
    const query = (document.getElementById('posProductSearch')?.value || '').trim().toLowerCase();
    let visibleCount = 0;

    document.querySelectorAll('.pos-product-card').forEach(card => {
        const matchesCategory = activeCategory === 'all' || card.dataset.cat === activeCategory;
        const haystack = [
            card.dataset.name || '',
            card.dataset.sku || '',
            card.dataset.cat || ''
        ].join(' ').toLowerCase();
        const matchesSearch = query === '' || haystack.includes(query);
        const shouldShow = matchesCategory && matchesSearch;

        card.style.display = shouldShow ? '' : 'none';
        if (shouldShow) visibleCount++;
    });

    const emptyFilter = document.getElementById('posEmptyFilter');
    if (emptyFilter) {
        emptyFilter.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

document.getElementById('posProductSearch')?.addEventListener('input', applyProductFilters);

// \u2500\u2500 Cart management \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
function addToCart(card) {
    const id    = card.dataset.id;
    const name  = card.dataset.name;
    const price = parseFloat(card.dataset.price);
    const stock = parseInt(card.dataset.stock);

    if (!cart[id]) {
        cart[id] = { id, name, price, qty: 0, maxStock: stock };
    }
    if (cart[id].qty >= stock) {
        alert('Maximum stock reached for this item.');
        return;
    }
    cart[id].qty++;
    card.classList.add('in-cart');
    renderCart();
}

function changeQty(id, delta) {
    if (!cart[id]) return;
    cart[id].qty += delta;
    if (cart[id].qty <= 0) {
        delete cart[id];
        const card = document.querySelector(`.pos-product-card[data-id="${id}"]`);
        if (card) card.classList.remove('in-cart');
    }
    renderCart();
}

function renderCart() {
    const list  = document.getElementById('cartList');
    const empty = document.getElementById('cartEmpty');
    const keys  = Object.keys(cart);

    if (keys.length === 0) {
        list.innerHTML = '<div id="cartEmpty" style="text-align:center;color:#9ca3af;padding:30px 0;font-size:13px">Tap products to add them here</div>';
        document.getElementById('cartTotal').textContent = '\u20b10.00';
        document.getElementById('chargeBtn').disabled = true;
        document.getElementById('posQrBox').style.display = 'none';
        return;
    }

    let html = '';
    let total = 0;
    keys.forEach(id => {
        const item = cart[id];
        const subtotal = item.price * item.qty;
        total += subtotal;
        html += `
        <div class="cart-item">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-qty">
                <button onclick="changeQty('${id}', -1)">-</button>
                <span>${item.qty}</span>
                <button onclick="changeQty('${id}', 1)">+</button>
            </div>
            <div class="cart-item-price">\u20b1${subtotal.toFixed(2)}</div>
        </div>`;
    });
    list.innerHTML = html;
    document.getElementById('cartTotal').textContent = '\u20b1' + total.toFixed(2);
    document.getElementById('chargeBtn').disabled = !(merchantWalletId && total > 0);
    document.getElementById('posQrBox').style.display = 'none';
}

function clearCart() {
    Object.keys(cart).forEach(k => delete cart[k]);
    document.querySelectorAll('.pos-product-card').forEach(c => c.classList.remove('in-cart'));
    document.getElementById('posQrBox').style.display = 'none';
    document.getElementById('posQrImage').removeAttribute('src');
    document.getElementById('posQrSummary').textContent = '';
    renderCart();
}

function orderTotals() {
    const items = Object.values(cart).map(i => ({ id: i.id, qty: i.qty, price: i.price }));
    const total = items.reduce((s, i) => s + i.price * i.qty, 0);
    return { items, total };
}

function generateLegacyPaymentQr() {
    const { items, total } = orderTotals();
    if (!items.length || total <= 0) {
        return;
    }

    const desc = Object.values(cart)
        .map(i => `${i.qty}x ${i.name}`)
        .join(', ');

    const payload = {
        type: 'payment',
        source: 'pos',
        merchant: merchantName,
        merchant_wallet_id: merchantWalletId,
        merchant_user_id: merchantUserId,
        price: total.toFixed(2),
        amount: total.toFixed(2),
        desc,
        items,
        issued_at: new Date().toISOString()
    };

    const qrData = JSON.stringify(payload);
    document.getElementById('posQrImage').src =
        'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' + encodeURIComponent(qrData);
    document.getElementById('posQrSummary').textContent =
        `Total: \u20b1${total.toFixed(2)} · ${items.length} item type(s)`;
    document.getElementById('posQrBox').style.display = 'block';
}

async function generatePaymentQr() {
    const { items, total } = orderTotals();
    if (!items.length || total <= 0) {
        return;
    }

    const chargeBtn = document.getElementById('chargeBtn');
    chargeBtn.disabled = true;
    chargeBtn.textContent = 'Preparing QR...';

    try {
        const form = new FormData();
        form.append('action', 'create_qr_order');
        form.append('total', total.toFixed(2));
        form.append('items', JSON.stringify(items));

        const response = await fetch('api/pos.php', {
            method: 'POST',
            body: form
        });
        const result = await response.json();

        if (!result.success) {
            alert(result.message || 'Unable to create payment QR.');
            return;
        }

        document.getElementById('posQrImage').src =
            'https://api.qrserver.com/v1/create-qr-code/?size=320x320&ecc=H&margin=20&data=' + encodeURIComponent(result.qr_payload);
        document.getElementById('posQrSummary').textContent = result.summary || `Total: \u20b1${total.toFixed(2)}`;
        document.getElementById('posQrBox').style.display = 'block';
    } catch (error) {
        alert('Unable to create payment QR. Please try again.');
    } finally {
        chargeBtn.disabled = false;
        chargeBtn.textContent = 'Generate Payment QR';
    }
}
</script>
</body>
</html>
