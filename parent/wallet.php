<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['parent']);
gjc_ensure_parent_schema($db);
gjc_ensure_parent_wallet_schema($db);

$parentUserId = gjc_user_id();
$currentUser  = gjc_current_user($db);
$parentName   = $currentUser['name'];

$parentId = gjc_parent_id_for_user($db, $parentUserId);
$wallet   = gjc_parent_wallet($db, $parentId);
$balance  = (float) $wallet['balance'];

$linkedStmt = $db->prepare("SELECT COUNT(*) FROM parent_student_links WHERE parent_id = ?");
$linkedStmt->execute([$parentId]);
$linkedCount = (int) $linkedStmt->fetchColumn();

$reqStmt = $db->prepare(
    "SELECT id, amount, source, status, reference_no, fee_amount, credited_amount, requested_at
       FROM parent_topup_requests
      WHERE parent_id = ?
      ORDER BY requested_at DESC
      LIMIT 20"
);
$reqStmt->execute([$parentId]);
$requests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

function gjc_parent_topup_pill_tone(string $status): string
{
    $status = strtolower($status);
    if ($status === 'approved') return 'green';
    if (in_array($status, ['rejected', 'cancelled'], true)) return 'red';
    if ($status === 'pending') return 'gold';
    return 'gray';
}

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrfToken = gjc_csrf_token();
$currentPage = 'wallet';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wallet | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/parent_shell.css?v=2">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student_dashboard.css?v=13">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student_profile.css?v=7">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student_topup.css?v=3">
    <link rel="stylesheet" href="<?= CSS_URL ?>/parent_wallet.css?v=2">
</head>

<body>
<div class="parent-layout">

    <?php require __DIR__ . '/../includes/partials/sidebar_parent.php'; ?>

    <main class="parent-main">

        <?php
        $topbarTitle = 'My Wallet';
        $topbarSubtitle = 'Top up your parent wallet, then send allowance to your linked students.';
        require __DIR__ . '/../includes/partials/topbar_parent.php';
        ?>

        <div class="parent-content" style="max-width:none;">

            <!-- Balance card -->
            <section class="sd-balance">
                <div>
                    <span class="sd-balance-label">Wallet Balance</span>
                    <div class="sd-balance-amount">
                        <span id="sdBalance"><?= gjc_gc_amount($balance) ?></span><span class="sd-unit">GC</span>
                    </div>
                    <div class="sd-gc-row">
                        <span class="sd-gc-badge">&#8776; &#8369;<span id="sdBalancePhp"><?= number_format($balance, 2) ?></span></span>
                        <span class="sd-gc-rate">&#8369;10 = 1 GC</span>
                    </div>
                    <div class="sd-balance-actions">
                        <a class="sd-btn-ghost" href="<?= PARENT_URL ?>/allowance.php"><i class="fa-solid fa-paper-plane"></i>Send Allowance</a>
                    </div>
                </div>
                <div class="sd-balance-holder">
                    <strong><?= $e($parentName) ?></strong>
                    <span class="sd-holder-id"><?= $linkedCount ?> linked student<?= $linkedCount === 1 ? '' : 's' ?></span>
                    <span class="sd-role-badge">PARENT</span>
                </div>
            </section>

            <!-- Request form + guide -->
            <section class="tu-grid">

                <div class="sd-panel">
                    <div class="sd-panel-head">
                        <div>
                            <h3>Request Top-Up</h3>
                            <p>Enter your desired amount and choose how you'll pay.</p>
                        </div>
                    </div>

                    <div id="pwFlash"></div>

                    <form id="pwTopupForm" class="pf-form">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">

                        <div class="pf-field">
                            <label>Amount (&#8369;)</label>
                            <div class="tu-money-input">
                                <span>&#8369;</span>
                                <input type="number" name="amount" id="pwAmount" placeholder="0.00" min="1" step="0.01" required>
                            </div>
                            <div class="tu-equiv" id="pwEquiv"></div>
                        </div>

                        <div class="tu-quick">
                            <button type="button" onclick="setPwAmount(100)">&#8369;100</button>
                            <button type="button" onclick="setPwAmount(200)">&#8369;200</button>
                            <button type="button" onclick="setPwAmount(500)">&#8369;500</button>
                            <button type="button" onclick="setPwAmount(1000)">&#8369;1,000</button>
                            <button type="button" onclick="setPwAmount(2000)">&#8369;2,000</button>
                        </div>

                        <div class="pf-field">
                            <label>Source</label>
                            <div class="tu-methods">

                                <label class="tu-method selected">
                                    <input type="radio" name="source" value="finance" checked>
                                    <div class="tu-method-icon">
                                        <i class="fa-solid fa-building-columns" style="font-size:18px;color:var(--sd-forest-700)"></i>
                                    </div>
                                    <div>
                                        <strong>Cash at Finance</strong>
                                        <span>Bring cash to the Accountancy Office</span>
                                    </div>
                                </label>

                                <label class="tu-method">
                                    <input type="radio" name="source" value="merchant">
                                    <div class="tu-method-icon">
                                        <i class="fa-solid fa-store" style="font-size:18px;color:var(--sd-forest-700)"></i>
                                    </div>
                                    <div>
                                        <strong>Via a Merchant</strong>
                                        <span>Pay a merchant cash — adds a small +1% fee</span>
                                    </div>
                                </label>

                            </div>
                        </div>

                        <div class="sg-error" id="pwError"></div>

                        <button type="submit" class="pf-btn">
                            <i class="fa-solid fa-circle-plus me-1"></i> Submit Top-Up Request
                        </button>
                    </form>
                </div>

                <div class="sd-panel">
                    <div class="sd-panel-head">
                        <div>
                            <h3>Top-Up Guide</h3>
                            <p>How your wallet top-up request works.</p>
                        </div>
                    </div>

                    <div class="tu-steps">
                        <div class="tu-step">
                            <strong>1</strong>
                            <span>Enter the amount you want to add.</span>
                        </div>
                        <div class="tu-step">
                            <strong>2</strong>
                            <span>Choose Finance or a merchant as your cash source.</span>
                        </div>
                        <div class="tu-step">
                            <strong>3</strong>
                            <span>Submit your request for confirmation.</span>
                        </div>
                        <div class="tu-step">
                            <strong>4</strong>
                            <span>Your wallet balance updates once approved.</span>
                        </div>
                    </div>

                    <div class="tu-limit">
                        <span>Merchant Route Fee</span>
                        <strong>+1%</strong>
                        <p>On top of the standard 2% service fee, same as a student top-up.</p>
                    </div>
                </div>

            </section>

            <!-- Recent requests -->
            <section class="sd-panel">
                <div class="sd-panel-head">
                    <div>
                        <h3>Recent Top-Up Requests</h3>
                        <p>Track your latest wallet top-up submissions.</p>
                    </div>
                </div>

                <?php if (empty($requests)): ?>
                <div class="sd-empty">
                    <i class="fa-regular fa-folder-open"></i>
                    No top-up requests yet. Submit your first top-up request to add funds to your wallet.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle js-datatable" id="parentTopupRequestsTable" data-page-length="8">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r): ?>
                            <tr>
                                <td class="sg-ref"><?= $e($r['reference_no']) ?></td>
                                <td><?= gjc_gc_price((float) $r['amount']) ?></td>
                                <td><span class="pf-pill gray"><?= $e(ucfirst($r['source'])) ?></span></td>
                                <td><span class="pf-pill <?= gjc_parent_topup_pill_tone((string) $r['status']) ?>"><?= $e(ucfirst($r['status'])) ?></span></td>
                                <td><?= $e(date('M d, Y h:i A', strtotime($r['requested_at']))) ?></td>
                                <td>
                                    <?php if ($r['status'] === 'pending'): ?>
                                    <button class="pf-pill red pw-cancel-btn" data-id="<?= (int) $r['id'] ?>" style="border:none;cursor:pointer;">Cancel</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

        </div>

    </main>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<?php require __DIR__ . '/../includes/partials/datatables_assets.php'; ?>
<script>
function toggleParentSidebar() {
    document.getElementById('parentSidebar').classList.toggle('collapsed');
}

const WALLET_API = '<?= PARENT_URL ?>/api/wallet.php';
const PESOS_PER_GC = <?= GJC_PESOS_PER_GC ?>;
const pwAmountEl = document.getElementById('pwAmount');
const pwEquiv    = document.getElementById('pwEquiv');

function pwRenderEquiv() {
    const php = parseFloat(pwAmountEl.value) || 0;
    pwEquiv.textContent = php > 0
        ? 'You will receive ≈ ' + (+((php / PESOS_PER_GC).toFixed(2))).toLocaleString('en-PH', { maximumFractionDigits: 2 }) + ' GC'
        : '';
}
pwAmountEl.addEventListener('input', pwRenderEquiv);

function setPwAmount(amount) {
    pwAmountEl.value = amount;
    pwRenderEquiv();
}

document.querySelectorAll('.tu-method').forEach(function (card) {
    card.addEventListener('click', function () {
        document.querySelectorAll('.tu-method').forEach(function (item) {
            item.classList.remove('selected');
        });
        card.classList.add('selected');
        card.querySelector('input').checked = true;
    });
});

function pwFlash(msg, isError) {
    document.getElementById('pwFlash').innerHTML =
        '<div class="pf-alert' + (isError ? ' is-error' : '') + '" style="margin-bottom:14px">' +
        '<i class="fa-solid ' + (isError ? 'fa-circle-exclamation' : 'fa-circle-check') + '"></i> ' + msg + '</div>';
}

document.getElementById('pwTopupForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const source = fd.get('source') || document.querySelector('input[name="source"]:checked').value;
    try {
        const res = await fetch(WALLET_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'submit_topup',
                amount: fd.get('amount'),
                source: source,
                csrf_token: fd.get('csrf_token'),
            }),
        });
        const data = await res.json();
        if (data.success) {
            pwFlash(data.message, false);
            setTimeout(() => location.reload(), 1200);
        } else {
            pwFlash(data.error || 'Request failed.', true);
        }
    } catch (err) {
        pwFlash('Network error. Please try again.', true);
    }
});

document.querySelectorAll('.pw-cancel-btn').forEach(function (btn) {
    btn.addEventListener('click', async function (e) {
        e.preventDefault();
        if (!confirm('Cancel this top-up request?')) return;
        try {
            const res = await fetch(WALLET_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'cancel_topup',
                    id: btn.dataset.id,
                    csrf_token: '<?= $e($csrfToken) ?>',
                }),
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                pwFlash(data.error || 'Could not cancel.', true);
            }
        } catch (err) {
            pwFlash('Network error. Please try again.', true);
        }
    });
});
</script>
</body>
</html>
