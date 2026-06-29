<?php
session_start();
require_once __DIR__ . "/../connection/config.php";
require_once __DIR__ . "/../connection/pdo.php";
require_once __DIR__ . "/../connection/app.php";

gjc_require_role(["merchant"]);
$currentUser = gjc_current_user($db);
$merchantUserId = $currentUser["id"];
$isStaff = gjc_is_merchant_staff();
$ownerMerchId = gjc_merchant_owner_id($db, $merchantUserId);
$currentPage = "pos";

// Fetch inventory for POS (only available, non-restricted items)
$products = [];
if (gjc_table_exists($db, "merchant_inventory")) {
    $stmt = $db->prepare(
        "SELECT id, sku, product_name, category, unit, price, stock_qty
           FROM merchant_inventory
          WHERE merchant_user_id = ? AND is_available = 1 AND is_restricted = 0 AND stock_qty > 0
          ORDER BY category ASC, product_name ASC",
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
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=18">
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
    <?php require __DIR__ .
        "/../includes/partials/" .
        (gjc_is_merchant_staff()
            ? "sidebar_merchant_staff.php"
            : "sidebar_merchant_admin.php"); ?>

    <main class="merchant-main">
        <header class="merchant-topbar">
            <button class="merchant-menu-btn" onclick="document.getElementById('merchantSidebar').classList.toggle('collapsed')">&#9776;</button>
            <div><h1>POS Terminal</h1><p>Select items and process student wallet payments.</p></div>
            <div class="merchant-topbar-actions">
                <button type="button" class="merchant-loadwallet-btn" onclick="lwOpen()">
                    <i class="fa-solid fa-coins"></i> <span class="merchant-loadwallet-label">Send GenCoin</span>
                </button>
                <div class="merchant-user">
                    <span><?= gjc_e($currentUser["name"]) ?></span>
                    <div class="merchant-avatar"><i class="fa-solid fa-store"></i></div>
                </div>
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
                    $cats = array_unique(array_column($products, "category"));
                    foreach ($cats as $cat): ?>
                    <button class="cat-btn" onclick="filterCat('<?= gjc_e(
                        $cat,
                    ) ?>', this)"><?= gjc_e(ucwords($cat)) ?></button>
                    <?php endforeach;
                    ?>
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
                        data-id="<?= (int) $p["id"] ?>"
                        data-name="<?= gjc_e($p["product_name"]) ?>"
                        data-price="<?= (float) $p["price"] ?>"
                        data-stock="<?= (int) $p["stock_qty"] ?>"
                        data-cat="<?= gjc_e($p["category"]) ?>"
                        data-sku="<?= gjc_e($p["sku"] ?? "") ?>"
                        onclick="addToCart(this)">
                        <span class="product-cat"><?= gjc_e(
                            $p["category"],
                        ) ?></span>
                        <div class="product-name"><?= gjc_e(
                            $p["product_name"],
                        ) ?></div>
                        <div class="product-price">&#8369;<?= number_format(
                            (float) $p["price"],
                            2,
                        ) ?></div>
                        <div class="product-stock">Stock: <?= (int) $p[
                            "stock_qty"
                        ] ?></div>
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
const merchantName = <?= json_encode(
    $currentUser["name"],
    JSON_UNESCAPED_SLASHES,
) ?>;
const merchantWalletId = <?= (int) $wallet["id"] ?>;
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

<!-- ── Load Wallet Modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="lwModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
        <div class="modal-content" style="border-radius:20px;overflow:hidden;border:none">

            <div class="modal-header border-0 pb-0" style="background:#f0fdf4;padding:20px 24px 12px">
                <div style="flex:1">
                    <h5 class="modal-title fw-bold" style="color:var(--gjc-green-600);font-size:18px">
                        <i class="fa-solid fa-coins me-2"></i>Send GenCoin
                    </h5>
                    <div style="display:flex;gap:6px;margin-top:10px;align-items:center" id="lw-steps">
                        <div class="lw-dot lw-dot--active" data-step="1"></div>
                        <div style="flex:1;height:2px;background:#d1fae5;max-width:40px"></div>
                        <div class="lw-dot" data-step="2"></div>
                        <div style="flex:1;height:2px;background:#d1fae5;max-width:40px"></div>
                        <div class="lw-dot" data-step="3"></div>
                        <span id="lw-step-label" style="margin-left:8px;font-size:12px;color:#6b7280;font-weight:600">Step 1 of 3</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="margin-top:-16px"></button>
            </div>

            <div class="modal-body" style="background:#f0fdf4;padding:20px 24px 24px">

                <!-- STEP 1: Student ID -->
                <div id="lw-step-1">
                    <p style="font-size:13px;color:#374151;margin-bottom:14px">Enter the Student ID of the wallet to load.</p>
                    <div style="position:relative">
                        <input type="text" id="lw-school-id" class="form-control" placeholder="e.g. GJC2026-0001"
                               autocomplete="off"
                               style="border-radius:12px;padding:12px 44px 12px 14px;font-size:14px;border:1.5px solid #d1fae5">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none"></i>
                    </div>
                    <div id="lw-lookup-result" style="margin-top:10px;min-height:36px"></div>
                    <div style="display:flex;justify-content:flex-end;margin-top:16px">
                        <button type="button" id="lw-next-1" class="btn btn-success" disabled
                                style="border-radius:12px;padding:10px 28px;font-weight:600"
                                onclick="lwGoStep(2)">
                            Next <i class="fa-solid fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Cash amount -->
                <div id="lw-step-2" style="display:none">
                    <div style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:12px;padding:10px 14px;margin-bottom:16px">
                        <div style="width:36px;height:36px;border-radius:50%;background:#bbf7d0;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--gjc-green-600);font-size:15px" id="lw-avatar"></div>
                        <div>
                            <div style="font-weight:700;font-size:14px;color:#111" id="lw-name-2"></div>
                            <div style="font-size:11px;color:#6b7280" id="lw-id-2"></div>
                        </div>
                    </div>

                    <label style="font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;display:block">Amount (GenCoins)</label>
                    <div style="position:relative;margin-bottom:6px">
                        <input type="number" id="lw-gc" class="form-control" min="1" step="1"
                               placeholder="e.g. 5"
                               style="border-radius:12px;padding:12px 60px 12px 14px;font-size:20px;font-weight:700;border:1.5px solid #d1fae5">
                        <span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:13px;font-weight:600;color:#9ca3af">GC</span>
                    </div>
                    <div id="lw-gc-equiv" style="font-size:12px;color:var(--gjc-green-600);font-weight:600;margin-bottom:12px;padding-left:4px;min-height:18px"></div>

                    <!-- Fee breakdown -->
                    <div id="lw-fee-preview" style="display:none;background:#fff;border-radius:10px;padding:12px 14px;font-size:12px;border:1px solid #d1fae5;margin-bottom:14px">
                        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                            <span style="color:#6b7280">Cash value (GC × ₱10)</span>
                            <span id="lw-fp-cash" style="font-weight:600;color:#111"></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                            <span style="color:var(--gjc-alert)">Service fee (3%)</span>
                            <span id="lw-fp-fee" style="font-weight:600;color:var(--gjc-alert)"></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:5px;padding-left:12px">
                            <span style="color:#6b7280;font-size:11px">↳ Your cut (1%)</span>
                            <span id="lw-fp-mcut" style="font-size:11px;font-weight:600;color:#059669"></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:5px;padding-left:12px">
                            <span style="color:#6b7280;font-size:11px">↳ System (2%)</span>
                            <span id="lw-fp-sfee" style="font-size:11px;font-weight:600;color:#6b7280"></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;border-top:1px solid #d1fae5;padding-top:8px;margin-top:4px">
                            <span style="color:var(--gjc-green-600);font-weight:700">Credited to student</span>
                            <span id="lw-fp-credited" style="font-weight:800;color:var(--gjc-green-600)"></span>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:space-between;margin-top:4px">
                        <button type="button" class="btn btn-outline-secondary" style="border-radius:12px;padding:10px 20px" onclick="lwGoStep(1)">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back
                        </button>
                        <button type="button" id="lw-next-2" class="btn btn-success" disabled
                                style="border-radius:12px;padding:10px 28px;font-weight:600"
                                onclick="lwGoStep(3)">
                            Next <i class="fa-solid fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Confirm -->
                <div id="lw-step-3" style="display:none">
                    <p style="font-size:13px;color:#374151;font-weight:600;margin-bottom:14px">Review before loading.</p>
                    <div style="background:#fff;border-radius:16px;padding:18px 20px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:18px;font-size:13px">
                        <div style="display:flex;flex-direction:column;gap:7px">
                            <div style="display:flex;justify-content:space-between"><span style="color:#6b7280">Student</span><strong id="lw-prev-name"></strong></div>
                            <div style="display:flex;justify-content:space-between"><span style="color:#6b7280">Student ID</span><span id="lw-prev-id" style="font-family:monospace"></span></div>
                        </div>
                        <div style="border-top:1px dashed #d1fae5;margin:12px 0;padding-top:12px;display:flex;flex-direction:column;gap:6px;font-size:12px">
                            <div style="display:flex;justify-content:space-between"><span style="color:#6b7280">GenCoins</span><span id="lw-prev-gc-count" style="font-weight:600;color:var(--gjc-green-600)"></span></div>
                            <div style="display:flex;justify-content:space-between"><span style="color:#6b7280">Cash value</span><span id="lw-prev-cash" style="font-weight:600"></span></div>
                            <div style="display:flex;justify-content:space-between"><span style="color:var(--gjc-alert)">Service fee (3%)</span><span id="lw-prev-fee" style="font-weight:600;color:var(--gjc-alert)"></span></div>
                            <div style="display:flex;justify-content:space-between;padding-left:10px"><span style="color:#6b7280;font-size:11px">↳ Your cut (1%)</span><span id="lw-prev-mcut" style="font-size:11px;font-weight:600;color:#059669"></span></div>
                            <div style="display:flex;justify-content:space-between;font-size:13px;border-top:1px solid #d1fae5;padding-top:8px;margin-top:2px">
                                <span style="color:var(--gjc-green-600);font-weight:700">Credited to student</span>
                                <span id="lw-prev-credited" style="font-weight:800;color:var(--gjc-green-600)"></span>
                            </div>
                        </div>
                    </div>

                    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;background:#fff;border-radius:12px;padding:12px 14px;border:1.5px solid #d1fae5">
                        <input type="checkbox" id="lw-confirm-check" style="width:18px;height:18px;margin-top:1px;accent-color:var(--gjc-success);cursor:pointer">
                        <span style="font-size:13px;color:#374151;line-height:1.5">I confirm I have received the cash and want to load <strong id="lw-confirm-credited"></strong> to <strong id="lw-confirm-name"></strong>'s wallet.</span>
                    </label>

                    <div id="lw-send-error" style="margin-top:10px;font-size:13px;color:var(--gjc-alert);min-height:18px"></div>

                    <div style="display:flex;justify-content:space-between;margin-top:16px">
                        <button type="button" class="btn btn-outline-secondary" style="border-radius:12px;padding:10px 20px" onclick="lwGoStep(2)">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back
                        </button>
                        <button type="button" id="lw-load-btn" class="btn btn-success" disabled
                                style="border-radius:12px;padding:10px 28px;font-weight:700;font-size:15px"
                                onclick="lwLoad()">
                            <i class="fa-solid fa-paper-plane me-1"></i> Send
                        </button>
                    </div>
                </div>

                <!-- SUCCESS -->
                <div id="lw-success" style="display:none;text-align:center;padding:16px 0">
                    <div style="width:64px;height:64px;background:var(--gjc-success-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
                        <i class="fa-solid fa-circle-check" style="font-size:32px;color:var(--gjc-success)"></i>
                    </div>
                    <div style="font-size:18px;font-weight:700;color:var(--gjc-green-600);margin-bottom:4px">Sent!</div>
                    <div style="font-size:13px;color:#6b7280" id="lw-success-msg"></div>
                    <div style="margin-top:10px;display:inline-block;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:6px 14px">
                        <span style="font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Reference No.</span><br>
                        <span id="lw-success-ref" style="font-size:13px;font-weight:700;color:var(--gjc-green-600);font-family:monospace;letter-spacing:.5px"></span>
                    </div>
                    <button type="button" class="btn btn-success mt-4 d-block mx-auto" data-bs-dismiss="modal"
                            style="border-radius:12px;padding:10px 32px;font-weight:600">Done</button>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
.lw-dot{width:10px;height:10px;border-radius:50%;background:#d1fae5;transition:background .2s;flex-shrink:0}
.lw-dot--active{background:var(--gjc-success)}
.lw-dot--done{background:var(--gjc-success-border)}
</style>

<script>
const LW_API = '<?= MERCHANT_URL ?>/api/topup.php';
let lwWalletId = 0, lwStudentName = '', lwSchoolId = '';

function lwFmt(n) {
    return '₱' + (+n).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function lwCalcFee(cash) {
    const systemFee   = Math.round(cash * 0.02 * 100) / 100;
    const merchantFee = Math.round(cash * 0.01 * 100) / 100;
    const credited    = Math.round((cash - systemFee - merchantFee) * 100) / 100;
    return { systemFee, merchantFee, totalFee: systemFee + merchantFee, credited };
}

function lwOpen() {
    lwReset();
    new bootstrap.Modal(document.getElementById('lwModal')).show();
}

function lwReset() {
    lwWalletId = 0; lwStudentName = ''; lwSchoolId = '';
    ['lw-school-id','lw-gc'].forEach(id => { const el = document.getElementById(id); if(el) el.value=''; });
    document.getElementById('lw-gc-equiv').textContent = '';
    document.getElementById('lw-lookup-result').innerHTML = '';
    document.getElementById('lw-next-1').disabled = true;
    document.getElementById('lw-next-2').disabled = true;
    document.getElementById('lw-fee-preview').style.display = 'none';
    document.getElementById('lw-confirm-check').checked = false;
    document.getElementById('lw-load-btn').disabled = true;
    document.getElementById('lw-send-error').textContent = '';
    ['lw-step-1','lw-step-2','lw-step-3','lw-success'].forEach(id => {
        document.getElementById(id).style.display = 'none';
    });
    document.getElementById('lw-step-1').style.display = '';
    lwUpdateStepUI(1);
}

function lwUpdateStepUI(step) {
    document.getElementById('lw-step-label').textContent = `Step ${step} of 3`;
    document.querySelectorAll('.lw-dot').forEach(d => {
        const s = parseInt(d.dataset.step);
        d.classList.toggle('lw-dot--active', s === step);
        d.classList.toggle('lw-dot--done', s < step);
    });
}

function lwGoStep(step) {
    ['lw-step-1','lw-step-2','lw-step-3'].forEach((id, i) => {
        document.getElementById(id).style.display = (i+1 === step) ? '' : 'none';
    });
    lwUpdateStepUI(step);
    if (step === 3) lwBuildPreview();
}

function lwBuildPreview() {
    const gc   = parseInt(document.getElementById('lw-gc').value, 10) || 0;
    const cash = gc * 10;
    const { systemFee, merchantFee, totalFee, credited } = lwCalcFee(cash);

    document.getElementById('lw-prev-name').textContent       = lwStudentName;
    document.getElementById('lw-prev-id').textContent         = lwSchoolId;
    document.getElementById('lw-prev-gc-count').textContent   = gc + ' GC';
    document.getElementById('lw-prev-cash').textContent       = lwFmt(cash);
    document.getElementById('lw-prev-fee').textContent        = '− ' + lwFmt(totalFee);
    document.getElementById('lw-prev-mcut').textContent       = '+ ' + lwFmt(merchantFee);
    document.getElementById('lw-prev-credited').textContent   = lwFmt(credited);
    document.getElementById('lw-confirm-credited').textContent = lwFmt(credited);
    document.getElementById('lw-confirm-name').textContent    = lwStudentName;

    document.getElementById('lw-confirm-check').checked = false;
    document.getElementById('lw-load-btn').disabled = true;
}

async function lwLookup() {
    const schoolId = document.getElementById('lw-school-id').value.trim();
    const resultEl = document.getElementById('lw-lookup-result');
    const nextBtn  = document.getElementById('lw-next-1');
    if (!schoolId) return;

    resultEl.innerHTML = '<span style="font-size:12px;color:#6b7280">Looking up…</span>';
    nextBtn.disabled = true;
    lwWalletId = 0;

    try {
        const res  = await fetch(LW_API, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'lookup_student', school_id: schoolId}),
        });
        const data = await res.json();
        if (data.success) {
            lwWalletId    = data.wallet_id;
            lwStudentName = data.name;
            lwSchoolId    = schoolId;
            resultEl.innerHTML = `
                <div style="display:flex;align-items:center;gap:8px;background:var(--gjc-info-bg);border-radius:10px;padding:8px 12px">
                    <i class="fa-solid fa-circle-check" style="color:#0ea5e9"></i>
                    <div>
                        <strong style="font-size:13px;color:#0369a1">${data.name}</strong>
                        <div style="font-size:11px;color:#6b7280">${schoolId}</div>
                    </div>
                </div>`;
            nextBtn.disabled = false;
            document.getElementById('lw-avatar').textContent  = data.name.charAt(0).toUpperCase();
            document.getElementById('lw-name-2').textContent  = data.name;
            document.getElementById('lw-id-2').textContent    = schoolId;
        } else {
            resultEl.innerHTML = `<div style="font-size:12px;color:var(--gjc-alert);padding:4px 2px"><i class="fa-solid fa-triangle-exclamation me-1"></i>${data.error||'Student not found.'}</div>`;
        }
    } catch {
        resultEl.innerHTML = '<div style="font-size:12px;color:var(--gjc-alert)">Network error. Try again.</div>';
    }
}

async function lwLoad() {
    const loadBtn = document.getElementById('lw-load-btn');
    const errorEl = document.getElementById('lw-send-error');
    const gc      = parseInt(document.getElementById('lw-gc').value, 10) || 0;
    const cash    = gc * 10;
    const { credited, totalFee, merchantFee } = lwCalcFee(cash);

    loadBtn.disabled = true;
    loadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading…';
    errorEl.textContent = '';

    try {
        const res  = await fetch(LW_API, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'load_wallet', student_wallet_id: lwWalletId, cash_amount: cash}),
        });
        const data = await res.json();
        if (data.success) {
            const actualCredited = data.credited_amount ?? credited;
            const actualFee      = data.fee_amount      ?? totalFee;
            const actualMFee     = data.merchant_fee    ?? merchantFee;
            document.getElementById('lw-step-3').style.display  = 'none';
            document.getElementById('lw-success').style.display = '';
            document.getElementById('lw-step-label').textContent = 'Complete';
            document.getElementById('lw-success-msg').textContent =
                lwFmt(actualCredited) + ' credited to ' + lwStudentName +
                '. Your cut: ' + lwFmt(actualMFee) + ' (1%).';
            document.getElementById('lw-success-ref').textContent = data.reference || '—';
        } else {
            errorEl.textContent = data.error || 'Failed. Please try again.';
            loadBtn.disabled = false;
            loadBtn.innerHTML = '<i class="fa-solid fa-wallet me-1"></i> Load';
        }
    } catch {
        errorEl.textContent = 'Network error. Please try again.';
        loadBtn.disabled = false;
        loadBtn.innerHTML = '<i class="fa-solid fa-wallet me-1"></i> Load';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const idInput = document.getElementById('lw-school-id');
    idInput.addEventListener('keydown', e => { if(e.key==='Enter'){e.preventDefault();lwLookup();} });
    idInput.addEventListener('blur', lwLookup);

    document.getElementById('lw-gc').addEventListener('input', function () {
        const gc   = parseInt(this.value, 10) || 0;
        const cash = gc * 10;
        const next = document.getElementById('lw-next-2');
        const prev = document.getElementById('lw-fee-preview');
        if (gc > 0) {
            const { systemFee, merchantFee, totalFee, credited } = lwCalcFee(cash);
            document.getElementById('lw-gc-equiv').textContent   = '≈ ' + lwFmt(cash) + ' cash value (1 GC = ₱10)';
            document.getElementById('lw-fp-cash').textContent    = lwFmt(cash);
            document.getElementById('lw-fp-fee').textContent     = '− ' + lwFmt(totalFee);
            document.getElementById('lw-fp-mcut').textContent    = lwFmt(merchantFee);
            document.getElementById('lw-fp-sfee').textContent    = lwFmt(systemFee);
            document.getElementById('lw-fp-credited').textContent= lwFmt(credited);
            prev.style.display = '';
            next.disabled = false;
        } else {
            document.getElementById('lw-gc-equiv').textContent = '';
            prev.style.display = 'none';
            next.disabled = true;
        }
    });

    document.getElementById('lw-confirm-check').addEventListener('change', function () {
        document.getElementById('lw-load-btn').disabled = !this.checked;
    });

    document.getElementById('lwModal').addEventListener('hidden.bs.modal', lwReset);
});
</script>
</body>
</html>
