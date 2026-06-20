<?php


require_once __DIR__ . '/../connection/CirculationEngine.php';
require_once __DIR__ . '/../connection/MintingGuard.php';

$engine  = new CirculationEngine($db);
$guard   = new MintingGuard($db);
$snap    = $engine->getCirculationSnapshot();
$monthly = $guard->getMonthlyMintingReport();

$cap         = max((float)($snap['cap'] ?? 1), 0.01);
$vault       = (float)($snap['vault']                  ?? 0);
$students    = (float)($snap['student_wallets_total']  ?? 0);
$merchants   = (float)($snap['merchant_wallets_total'] ?? 0);
$vouchers    = (float)($snap['active_vouchers_total']  ?? 0);
$circulation = (float)($snap['total_in_circulation']   ?? 0);
$drift       = abs((float)($snap['circulation_drift']  ?? 0));
$isBalanced  = $drift < 0.01;

$vaultPct    = round(($vault    / $cap) * 100, 1);
$studPct     = round(($students / $cap) * 100, 1);
$merchPct    = round(($merchants/ $cap) * 100, 1);
$vchPct      = round(($vouchers / $cap) * 100, 1);
$mintUsedPct = (float)$monthly['soft_limit_used_pct'];
$minted      = (float)$monthly['minted_this_month'];
$limitHit    = (bool)$monthly['soft_limit_exceeded'];
?>

<section class="ce-section" id="circulation-health">

    <div class="ce-section-label">
        <span class="ce-label-pill">
            <img src="<?= ICONS_URL ?>/wallet.png" alt="" class="ce-label-icon">
            Token Economy
        </span>
        <div class="ce-balance-badge <?= $isBalanced ? 'ce-badge-ok' : 'ce-badge-err' ?>">
            <?= $isBalanced
                ? '<span class="ce-dot ce-dot-green"></span> Economy Balanced'
                : '<span class="ce-dot ce-dot-red ce-pulse"></span> Drift Detected' ?>
        </div>
    </div>

    <?php if (!$isBalanced): ?>
    <div class="ce-alert-danger">
        <img src="<?= ICONS_URL ?>/pending-encashments.png" alt="" style="width:20px;opacity:.7">
        <div>
            <strong>INTEGRITY FAILURE - Economy Drift <?= gjc_money($drift) ?></strong><br>
            <small>All transactions should be halted until this is resolved by the Admin.</small>
        </div>
    </div>
    <?php endif; ?>

    <div class="ce-hero-panel">
        <div class="ce-hero-left">
            <div class="ce-hero-label">Total Circulation Cap</div>
            <div class="ce-hero-amount"><?= gjc_money($cap) ?></div>
            <div class="ce-hero-sub">Maximum authorized money supply in the closed-loop economy</div>
        </div>
        <div class="ce-hero-right">
            <div class="ce-hero-stat">
                <span>Distributed</span>
                <strong><?= gjc_money($cap - $vault) ?></strong>
            </div>
            <div class="ce-hero-divider"></div>
            <div class="ce-hero-stat">
                <span>Vault Reserve</span>
                <strong><?= gjc_money($vault) ?></strong>
            </div>
            <div class="ce-hero-divider"></div>
            <div class="ce-hero-stat">
                <span>Total in Circulation</span>
                <strong class="<?= $isBalanced ? 'ce-text-green' : 'ce-text-red' ?>">
                    <?= gjc_money($circulation) ?>
                </strong>
            </div>
        </div>
    </div>

    <div class="ce-pool-intro">
        <div class="ce-flow-title">Wallet Pools</div>
        <div class="ce-flow-sub">All pools must sum to the cap at all times</div>
    </div>

    <div class="ce-pool-grid">

        <div class="ce-pool-card ce-pool-vault">
            <div class="ce-pool-icon-wrap">
                <img src="<?= ICONS_URL ?>/pending-topups.png" alt="" class="ce-pool-icon">
            </div>
            <div class="ce-pool-info">
                <span class="ce-pool-label">Cashier Vault</span>
                <div class="ce-pool-amt"><?= gjc_money($vault) ?></div>
                <div class="ce-pool-pct-bar">
                    <div class="ce-pool-pct-fill" style="width:<?= $vaultPct ?>%"></div>
                </div>
                <small class="ce-pool-share"><?= $vaultPct ?>% of cap - Available to load</small>
            </div>
        </div>

        <div class="ce-pool-card ce-pool-students">
            <div class="ce-pool-icon-wrap">
                <img src="<?= ICONS_URL ?>/students.png" alt="" class="ce-pool-icon">
            </div>
            <div class="ce-pool-info">
                <span class="ce-pool-label">Student Wallets</span>
                <div class="ce-pool-amt"><?= gjc_money($students) ?></div>
                <div class="ce-pool-pct-bar">
                    <div class="ce-pool-pct-fill" style="width:<?= $studPct ?>%"></div>
                </div>
                <small class="ce-pool-share"><?= $studPct ?>% of cap - Spendable balance</small>
            </div>
        </div>

        <div class="ce-pool-card ce-pool-merchants">
            <div class="ce-pool-icon-wrap">
                <img src="<?= ICONS_URL ?>/merchants.png" alt="" class="ce-pool-icon">
            </div>
            <div class="ce-pool-info">
                <span class="ce-pool-label">Merchant Wallets</span>
                <div class="ce-pool-amt"><?= gjc_money($merchants) ?></div>
                <div class="ce-pool-pct-bar">
                    <div class="ce-pool-pct-fill" style="width:<?= $merchPct ?>%"></div>
                </div>
                <small class="ce-pool-share"><?= $merchPct ?>% of cap - Pending encashment</small>
            </div>
        </div>

        <div class="ce-pool-card ce-pool-vouchers">
            <div class="ce-pool-icon-wrap">
                <img src="<?= ICONS_URL ?>/visitors.png" alt="" class="ce-pool-icon">
            </div>
            <div class="ce-pool-info">
                <span class="ce-pool-label">Active Vouchers</span>
                <div class="ce-pool-amt"><?= gjc_money($vouchers) ?></div>
                <div class="ce-pool-pct-bar">
                    <div class="ce-pool-pct-fill" style="width:<?= $vchPct ?>%"></div>
                </div>
                <small class="ce-pool-share"><?= $vchPct ?>% of cap - Visitor QR balances</small>
            </div>
        </div>

    </div>

    <div class="ce-bottom-grid">

        
        <div class="ce-mint-info-panel <?= $limitHit ? 'ce-limit-hit' : '' ?>">
            <div class="ce-mint-info-header">
                <div class="ce-mint-info-icon">P</div>
                <div>
                    <div class="ce-mint-info-title">Monthly Minting Budget</div>
                    <div class="ce-mint-info-sub">
                        <?= $limitHit
                            ? 'Soft limit exceeded - Mint PIN required'
                            : 'Within the ' . gjc_money(MintingGuard::SOFT_LIMIT) . ' monthly soft limit' ?>
                    </div>
                </div>
            </div>
            <div class="ce-mint-track-wrap">
                <div class="ce-mint-track">
                    <div class="ce-mint-track-fill <?= $limitHit ? 'ce-track-warn' : 'ce-track-ok' ?>"
                         style="width:<?= min(100, $mintUsedPct) ?>%">
                    </div>
                </div>
                <span class="ce-mint-pct"><?= min(100, $mintUsedPct) ?>%</span>
            </div>
            <div class="ce-mint-stats">
                <div class="ce-mint-stat-item">
                    <span>Minted this month</span>
                    <strong><?= gjc_money($minted) ?></strong>
                </div>
                <div class="ce-mint-stat-item">
                    <span>Remaining budget</span>
                    <strong><?= gjc_money(max(0, (float)$monthly['remaining_soft_limit'])) ?></strong>
                </div>
                <div class="ce-mint-stat-item">
                    <span>Mint events</span>
                    <strong><?= $monthly['mint_events'] ?></strong>
                </div>
                <div class="ce-mint-stat-item">
                    <span>Hard limit</span>
                    <strong><?= gjc_money((float)$monthly['hard_limit']) ?></strong>
                </div>
            </div>
        </div>

        
        <?php if (($_SESSION['sub_role'] ?? '') === 'super_admin'): ?>
        <div class="ce-mint-form-panel">
            <div class="ce-mint-form-header">
                <span class="ce-mint-badge">Admin</span>
                <div class="ce-mint-form-title">Mint New Points</div>
                <div class="ce-mint-form-sub">Increases the cap and injects points into the Cashier Vault</div>
            </div>

            <div id="ce-mint-alert"></div>

            <form id="ce-mint-form">
                <div class="ce-field-row">
                    <div class="ce-field">
                        <label class="ce-label">Amount (&#8369;)</label>
                        <input type="number" id="ce-amount" class="ce-input"
                               min="1" step="0.01" placeholder="e.g. 10,000" required>
                    </div>
                    <div class="ce-field ce-field-wide">
                        <label class="ce-label">Audit Justification</label>
                        <input type="text" id="ce-reason" class="ce-input"
                               placeholder="e.g. Q3 budget approved by board" required>
                    </div>
                </div>
                <div class="ce-field" id="ce-pin-wrap"
                     style="display:<?= $limitHit ? 'block' : 'none' ?>">
                    <label class="ce-label">
                        Mint PIN
                        <span class="ce-pin-badge">Required above <?= gjc_money(MintingGuard::SOFT_LIMIT) ?>/mo</span>
                    </label>
                    <input type="password" id="ce-pin" class="ce-input" placeholder="Enter Mint PIN">
                </div>
                <button type="submit" class="ce-mint-btn" id="ce-mint-btn">
                    <span class="ce-mint-btn-content">
                        <img src="<?= ICONS_URL ?>/wallet.png" alt="">
                        <span>Mint Points into Economy</span>
                    </span>
                </button>
            </form>
        </div>
        <?php else: ?>
        
        <div class="ce-flow-guide">
            <div class="ce-flow-guide-title">Money Flow Reference</div>
            <div class="ce-flow-steps">
                <div class="ce-flow-step">
                    <div class="ce-flow-step-icon" style="background:linear-gradient(135deg,#064420,#137a3f)">1</div>
                    <div class="ce-flow-step-info">
                        <strong>Mint</strong>
                        <span>Admin to Vault</span>
                    </div>
                    <div class="ce-flow-arrow">&gt;</div>
                </div>
                <div class="ce-flow-step">
                    <div class="ce-flow-step-icon" style="background:linear-gradient(135deg,#1e3a5f,#2563eb)">2</div>
                    <div class="ce-flow-step-info">
                        <strong>Load</strong>
                        <span>Vault to Student</span>
                    </div>
                    <div class="ce-flow-arrow">&gt;</div>
                </div>
                <div class="ce-flow-step">
                    <div class="ce-flow-step-icon" style="background:linear-gradient(135deg,#713f12,#d97706)">3</div>
                    <div class="ce-flow-step-info">
                        <strong>Pay</strong>
                        <span>Student to Merchant</span>
                    </div>
                    <div class="ce-flow-arrow">&gt;</div>
                </div>
                <div class="ce-flow-step">
                    <div class="ce-flow-step-icon" style="background:linear-gradient(135deg,#4c1d95,#7c3aed)">4</div>
                    <div class="ce-flow-step-info">
                        <strong>Settle</strong>
                        <span>Merchant to Vault</span>
                    </div>
                    <div class="ce-flow-arrow ce-invisible">&gt;</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Total circulation and balance status are already shown above
         (hero panel + section badge) - the footer only adds the timestamp. -->
    <div class="ce-footer">
        <span>Snapshot: <strong><?= $snap['as_of'] ?? 'N/A' ?></strong></span>
    </div>

</section>

<script>
(function () {
    const SOFT_LIMIT   = <?= MintingGuard::SOFT_LIMIT ?>;
    const mintedSoFar  = <?= $minted ?>;

    const amtInput = document.getElementById('ce-amount');
    if (amtInput) {
        amtInput.addEventListener('input', function () {
            const pinWrap = document.getElementById('ce-pin-wrap');
            if (!pinWrap) return;
            pinWrap.style.display = ((mintedSoFar + (parseFloat(this.value) || 0)) > SOFT_LIMIT)
                ? 'block' : 'none';
        });
    }

    const form = document.getElementById('ce-mint-form');
    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn     = document.getElementById('ce-mint-btn');
            const alertEl = document.getElementById('ce-mint-alert');
            btn.disabled  = true;
            btn.innerHTML = '<span class="ce-spinner"></span> Processing...';
            alertEl.innerHTML = '';

            const payload = {
                amount: parseFloat(document.getElementById('ce-amount').value),
                reason: document.getElementById('ce-reason').value,
                pin:    document.getElementById('ce-pin')?.value || null,
            };

            try {
                const res  = await fetch('<?= ADMIN_URL ?>/api/mint.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(payload),
                });
                const data = await res.json();

                if (data.success) {
                    alertEl.innerHTML = `
                        <div class="ce-alert ce-alert-ok">
                            Minted <strong>&#8369;${payload.amount.toLocaleString('en-PH',{minimumFractionDigits:2})}</strong>.
                            New Cap: <strong>&#8369;${parseFloat(data.new_cap).toLocaleString('en-PH',{minimumFractionDigits:2})}</strong>.
                            New Vault: <strong>&#8369;${parseFloat(data.new_vault).toLocaleString('en-PH',{minimumFractionDigits:2})}</strong>.
                            <a href="" onclick="location.reload();return false">Refresh</a>
                        </div>`;
                    form.reset();
                } else {
                    alertEl.innerHTML = `<div class="ce-alert ce-alert-err">${data.error}</div>`;
                }
            } catch (err) {
                alertEl.innerHTML = `<div class="ce-alert ce-alert-err">Network error: ${err.message}</div>`;
            }

            btn.disabled  = false;
            btn.innerHTML = `<span class="ce-mint-btn-content"><img src="<?= ICONS_URL ?>/wallet.png" alt=""><span>Mint Points into Economy</span></span>`;
        });
    }
})();
</script>

