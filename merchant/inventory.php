<?php
session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['merchant']);
$currentUser    = gjc_current_user($db);
$merchantUserId = $currentUser['id'];
$ownerMerchId   = gjc_merchant_owner_id($db, $merchantUserId);
$isStaff        = gjc_is_merchant_staff();
$isMerchAdmin   = gjc_is_merchant_admin() || (gjc_current_role() === 'merchant' && !$isStaff); // legacy merchant = admin
$currentPage    = 'inventory';

// Fetch inventory for this merchant
$inventory = [];
if (gjc_table_exists($db, 'merchant_inventory')) {
    $stmt = $db->prepare(
        "SELECT * FROM merchant_inventory WHERE merchant_user_id = ? ORDER BY category ASC, product_name ASC"
    );
    $stmt->execute([$ownerMerchId]);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$categories = ['food', 'beverage', 'snack', 'supplies', 'service', 'general'];
$units       = ['piece', 'pack', 'bottle', 'can', 'cup', 'kg', 'gram', 'litre', 'serving', 'set'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | GJC EduPay Merchant</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/merchant.css?v=11">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<div class="merchant-layout">
    <?php require __DIR__ . '/../includes/partials/' . (gjc_is_merchant_staff() ? 'sidebar_merchant_staff.php' : 'sidebar_merchant_admin.php'); ?>

    <main class="merchant-main">
        <header class="merchant-topbar">
            <button class="merchant-menu-btn" onclick="document.getElementById('merchantSidebar').classList.toggle('collapsed')">&#9776;</button>
            <div><h1>Product Inventory</h1><p><?= $isMerchAdmin ? 'Manage your full product catalog.' : 'Update stock levels for available items.' ?></p></div>
            <div class="merchant-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="merchant-avatar"><img src="<?= ICONS_URL ?>/store.png" alt="Merchant"></div>
            </div>
        </header>

        <section class="merchant-premium-panel">
            <div class="merchant-panel-header d-flex justify-content-between align-items-center">
                <div><h3>Product Catalog</h3><p><?= count($inventory) ?> items on file.</p></div>
                <?php if ($isMerchAdmin): ?>
                <button class="merchant-view-btn" data-bs-toggle="modal" data-bs-target="#addProductModal">+ Add Product</button>
                <?php endif; ?>
            </div>

            <?php
            $lowStock   = array_filter($inventory, function ($i) {
                return $i['stock_qty'] <= $i['min_stock_alert'] && $i['is_available'];
            });
            $restricted = array_filter($inventory, function ($i) {
                return $i['is_restricted'];
            });
            if ($lowStock): ?>
            <div class="alert alert-warning" style="margin:0 0 16px;border-radius:10px;font-size:13px">
                &#9888; <strong><?= count($lowStock) ?> item(s)</strong> are at or below minimum stock levels.
            </div>
            <?php endif; ?>
            <?php if ($restricted): ?>
            <div class="alert alert-danger" style="margin:0 0 16px;border-radius:10px;font-size:13px">
                &#128683; <strong><?= count($restricted) ?> item(s)</strong> are flagged as restricted by admin policy.
            </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table merchant-premium-table align-middle">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Min Alert</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($inventory)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-5">No products yet. <?= $isMerchAdmin ? 'Add your first item.' : 'Contact your store admin.' ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($inventory as $item): ?>
                        <?php
                            $isLow = $item['stock_qty'] <= $item['min_stock_alert'];
                        ?>
                        <tr class="<?= $item['is_restricted'] ? 'table-danger' : ($isLow ? 'table-warning' : '') ?>">
                            <td><code><?= gjc_e($item['sku'] ?: '—') ?></code></td>
                            <td>
                                <strong><?= gjc_e($item['product_name']) ?></strong>
                                <?php if ($item['is_restricted']): ?>
                                    <span class="badge bg-danger ms-1" style="font-size:10px">RESTRICTED</span>
                                <?php endif; ?>
                                <?php if ($item['description']): ?>
                                    <br><small class="text-muted"><?= gjc_e($item['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= gjc_e(ucwords($item['category'])) ?></td>
                            <td><?= gjc_e($item['unit']) ?></td>
                            <td><?= gjc_money($item['price']) ?></td>
                            <td>
                                <span class="<?= $isLow ? 'text-danger fw-bold' : '' ?>">
                                    <?= (int) $item['stock_qty'] ?>
                                    <?= $isLow ? '⚠' : '' ?>
                                </span>
                            </td>
                            <td><?= (int) $item['min_stock_alert'] ?></td>
                            <td>
                                <?php if ($item['is_available'] && !$item['is_restricted']): ?>
                                    <span class="merchant-type-pill">Available</span>
                                <?php elseif ($item['is_restricted']): ?>
                                    <span style="color:#ef4444;font-weight:700;font-size:12px">Blocked</span>
                                <?php else: ?>
                                    <span style="color:#9ca3af;font-size:12px">Unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="editStock(<?= (int)$item['id'] ?>, <?= (int)$item['stock_qty'] ?>, '<?= gjc_e($item['product_name']) ?>')">
                                    Stock
                                </button>
                                <?php if ($isMerchAdmin): ?>
                                <button class="btn btn-sm btn-outline-secondary"
                                    onclick="editProduct(<?= htmlspecialchars(json_encode($item), ENT_QUOTES) ?>)">
                                    Edit
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<!-- Add Product Modal (Merchant Admin only) -->
<?php if ($isMerchAdmin): ?>
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header"><h5 class="modal-title" id="productModalTitle">Add Product</h5></div>
            <div class="modal-body">
                <form id="productForm">
                    <input type="hidden" name="action" value="add_product" id="productAction">
                    <input type="hidden" name="item_id" id="productId" value="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SKU (optional)</label>
                            <input type="text" class="form-control" name="sku" id="pSku" placeholder="e.g. RICE-001">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Product Name *</label>
                            <input type="text" class="form-control" name="product_name" id="pName" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <input type="text" class="form-control" name="description" id="pDesc" placeholder="Short description">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category *</label>
                            <select class="form-select" name="category" id="pCategory">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c ?>"><?= ucwords($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Unit *</label>
                            <select class="form-select" name="unit" id="pUnit">
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $u ?>"><?= ucwords($u) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price (₱) *</label>
                            <input type="number" class="form-control" name="price" id="pPrice" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Initial Stock *</label>
                            <input type="number" class="form-control" name="stock_qty" id="pStock" min="0" required value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Min Stock Alert</label>
                            <input type="number" class="form-control" name="min_stock_alert" id="pMinStock" min="0" value="5">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" name="is_available" id="pAvailable" value="1" checked>
                                <label class="form-check-label" for="pAvailable">Available for sale</label>
                            </div>
                        </div>
                    </div>
                    <div id="productMsg" class="mt-3"></div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="login-btn" style="flex:1" id="productSubmitBtn">Save Product</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Stock Modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header"><h5 class="modal-title">Update Stock Level</h5></div>
            <div class="modal-body">
                <form id="stockForm">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="item_id" id="stockItemId">
                    <p>Product: <strong id="stockProductName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Stock Quantity</label>
                        <input type="number" class="form-control form-control-lg" name="stock_qty" id="stockQtyInput" min="0" required>
                    </div>
                    <div id="stockMsg" class="mb-3"></div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="login-btn" style="flex:1">Update Stock</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
const INV_API = '<?= MERCHANT_URL ?>/api/inventory.php';

function editStock(id, qty, name) {
    document.getElementById('stockItemId').value = id;
    document.getElementById('stockQtyInput').value = qty;
    document.getElementById('stockProductName').textContent = name;
    document.getElementById('stockMsg').innerHTML = '';
    new bootstrap.Modal(document.getElementById('stockModal')).show();
}

<?php if ($isMerchAdmin): ?>
function editProduct(item) {
    document.getElementById('productModalTitle').textContent = 'Edit Product';
    document.getElementById('productAction').value = 'edit_product';
    document.getElementById('productId').value = item.id;
    document.getElementById('pSku').value = item.sku || '';
    document.getElementById('pName').value = item.product_name;
    document.getElementById('pDesc').value = item.description || '';
    document.getElementById('pCategory').value = item.category;
    document.getElementById('pUnit').value = item.unit;
    document.getElementById('pPrice').value = item.price;
    document.getElementById('pStock').value = item.stock_qty;
    document.getElementById('pMinStock').value = item.min_stock_alert;
    document.getElementById('pAvailable').checked = !!parseInt(item.is_available);
    new bootstrap.Modal(document.getElementById('addProductModal')).show();
}

document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('productSubmitBtn');
    btn.disabled = true; btn.textContent = 'Saving...';
    const r = await fetch(INV_API, { method:'POST', body: new FormData(this) });
    const d = await r.json();
    const msg = document.getElementById('productMsg');
    if (d.success) {
        msg.innerHTML = `<div class="alert alert-success">${d.message} Reloading...</div>`;
        setTimeout(() => location.reload(), 1300);
    } else {
        msg.innerHTML = `<div class="alert alert-danger">${d.message}</div>`;
        btn.disabled = false; btn.textContent = 'Save Product';
    }
});
<?php endif; ?>

document.getElementById('stockForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const r = await fetch(INV_API, { method:'POST', body: new FormData(this) });
    const d = await r.json();
    const msg = document.getElementById('stockMsg');
    if (d.success) {
        msg.innerHTML = '<div class="alert alert-success">Stock updated! Reloading...</div>';
        setTimeout(() => location.reload(), 1200);
    } else {
        msg.innerHTML = `<div class="alert alert-danger">${d.message}</div>`;
    }
});
</script>
</body>
</html>
