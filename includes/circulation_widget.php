<?php

require_once __DIR__ . "/../connection/CirculationEngine.php";
require_once __DIR__ . "/../connection/MintingGuard.php";

$engine = new CirculationEngine($db);
$guard = new MintingGuard($db);
$snap = $engine->getCirculationSnapshot();
$monthly = $guard->getMonthlyMintingReport();

$cap = max((float) ($snap["cap"] ?? 1), 0.01);
$vault = (float) ($snap["vault"] ?? 0);
$students = (float) ($snap["student_wallets_total"] ?? 0);
$merchants = (float) ($snap["merchant_wallets_total"] ?? 0);
$vouchers = (float) ($snap["active_vouchers_total"] ?? 0);
$circulation = (float) ($snap["total_in_circulation"] ?? 0);
$drift = abs((float) ($snap["circulation_drift"] ?? 0));
$isBalanced = $drift < 0.01;

$walletStats = gjc_wallet_user_stats($db);
$merchantWalletStats = gjc_merchant_wallet_user_stats($db);

// Combined wallet-user figures for the "Total Wallet Users" card + drill-in table.
$totalWalletUsers = $walletStats["total"] + $merchantWalletStats["total"];
$activeWalletUsers = $walletStats["active"] + $merchantWalletStats["active"];
$inactiveWalletUsers =
    $walletStats["inactive"] + $merchantWalletStats["inactive"];
$walletUsersList = gjc_wallet_users_list($db);

$vaultPct = round(($vault / $cap) * 100, 1);
$studPct = round(($students / $cap) * 100, 1);
$merchPct = round(($merchants / $cap) * 100, 1);
$vchPct = round(($vouchers / $cap) * 100, 1);
$mintUsedPct = (float) $monthly["soft_limit_used_pct"];
$minted = (float) $monthly["minted_this_month"];
$limitHit = (bool) $monthly["soft_limit_exceeded"];
?>

<section class="ce-section" id="circulation-health">

    <div class="ce-section-label">
        <span class="ce-label-pill">
            <i class="fa-solid fa-coins ce-label-icon"></i>
            GenCoin Economy
        </span>
        <div class="ce-balance-badge <?= $isBalanced
            ? "ce-badge-ok"
            : "ce-badge-err" ?>">
            <?= $isBalanced
                ? '<span class="ce-dot ce-dot-green"></span> Economy Balanced'
                : '<span class="ce-dot ce-dot-red ce-pulse"></span> Drift Detected' ?>
        </div>
    </div>

    <?php if (!$isBalanced): ?>
    <div class="ce-alert-danger">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:20px;opacity:.7"></i>
        <div>
            <strong>Balance Mismatch Detected — <?= gjc_money(
                $drift,
            ) ?> Unaccounted</strong><br>
            <small>Please stop all transactions and contact your system administrator to resolve this.</small>
        </div>
    </div>
    <?php endif; ?>

    <div class="ce-ledger-panel">

        <div class="ce-ledger-head">
            <div>
                <span class="ce-ledger-label">Total Money in System</span>
                <div class="ce-ledger-amount"><?= gjc_money($cap) ?></div>
                <p class="ce-ledger-sub">The maximum amount of money allowed in the system at any time</p>
                <p class="ce-ledger-sub" style="margin-top:2px;font-weight:700;">
                    &asymp; <?= number_format(
                        $cap / 10,
                        1,
                    ) ?> GenCoins &middot; Fixed rate: &#8369;10 = 1 GenCoin
                </p>
            </div>

            <?php if ($isBalanced): ?>
            <div class="ce-reconcile ce-reconcile--ok">
                <i class="fa-solid fa-circle-check"></i>
                <span>All money is accounted for — vault and wallets add up correctly.</span>
            </div>
            <?php else: ?>
            <div class="ce-reconcile ce-reconcile--err">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Total recorded money (<strong><?= gjc_money(
                    $circulation,
                ) ?></strong>) doesn't match what's in the accounts — <strong><?= gjc_money(
    $drift,
) ?></strong> is unaccounted for.</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="ce-ledger-bar" role="img"
             aria-label="Circulation breakdown: Cashier Vault <?= $vaultPct ?>%, Student Wallets <?= $studPct ?>%, Merchant Wallets <?= $merchPct ?>%, Active Vouchers <?= $vchPct ?>%">
            <div class="ce-ledger-seg ce-pool-vault"     style="width:<?= $vaultPct ?>%"></div>
            <div class="ce-ledger-seg ce-pool-students"  style="width:<?= $studPct ?>%"></div>
            <div class="ce-ledger-seg ce-pool-merchants" style="width:<?= $merchPct ?>%"></div>
            <div class="ce-ledger-seg ce-pool-vouchers"  style="width:<?= $vchPct ?>%"></div>
        </div>

        <ul class="ce-ledger-legend">
            <li class="ce-legend-item"><span class="ce-legend-dot ce-pool-vault"></span>Cashier Vault <b><?= $vaultPct ?>%</b></li>
            <li class="ce-legend-item"><span class="ce-legend-dot ce-pool-students"></span>Student Wallets <b><?= $studPct ?>%</b></li>
            <li class="ce-legend-item"><span class="ce-legend-dot ce-pool-merchants"></span>Merchant Wallets <b><?= $merchPct ?>%</b></li>
            <li class="ce-legend-item"><span class="ce-legend-dot ce-pool-vouchers"></span>Active Vouchers <b><?= $vchPct ?>%</b></li>
        </ul>

    </div>

    <div class="ce-pool-intro">
        <div class="ce-flow-title">Wallet Pools</div>
        <div class="ce-flow-sub">Where the cap currently sits - detail behind the bar above</div>
    </div>

    <div class="ce-pool-grid">

        <div class="ce-pool-card ce-pool-vault">
            <div class="ce-pool-icon-wrap">
                <i class="fa-solid fa-building-columns ce-pool-icon"></i>
            </div>
            <div class="ce-pool-info">
                <span class="ce-pool-label">Cashier Vault</span>
                <div class="ce-pool-amt"><?= gjc_money($vault) ?></div>
                <small class="ce-pool-share">Available to load</small>
            </div>
        </div>

        <div class="ce-pool-card ce-pool-users ce-pool-card--clickable"
             role="button" tabindex="0"
             data-bs-toggle="modal" data-bs-target="#walletUsersModal"
             data-wallet-filter="" data-wallet-title="All Wallet Users (<?= number_format(
                 $totalWalletUsers,
             ) ?>)"
             aria-label="View all wallet users in a table">
            <div class="ce-pool-icon-wrap">
                <i class="fa-solid fa-users ce-pool-icon"></i>
            </div>
            <div class="ce-pool-info" style="flex:1;min-width:0;">
                <span class="ce-pool-label">Total Wallet Users</span>
                <div class="ce-pool-amt"><?= number_format(
                    $totalWalletUsers,
                ) ?></div>

                <?php $totalActivePct =
                    $totalWalletUsers > 0
                        ? round(($activeWalletUsers / $totalWalletUsers) * 100)
                        : 0; ?>
                <div class="ce-wu-bar-wrap">
                    <div class="ce-wu-bar">
                        <div class="ce-wu-bar-fill" style="width:<?= $totalActivePct ?>%"></div>
                    </div>
                    <span class="ce-wu-pct"><?= $totalActivePct ?>%</span>
                </div>

                <div class="ce-wu-badges">
                    <span class="ce-wu-badge ce-wu-badge--active">
                        <span class="ce-wu-dot ce-wu-dot--active"></span>
                        <?= number_format($activeWalletUsers) ?> Active
                    </span>
                    <span class="ce-wu-badge ce-wu-badge--inactive">
                        <span class="ce-wu-dot ce-wu-dot--inactive"></span>
                        <?= number_format($inactiveWalletUsers) ?> Inactive
                    </span>
                </div>

                <small class="ce-pool-share">
                </small>
            </div>
        </div>

        <div class="ce-pool-card ce-pool-students ce-pool-card--wallet ce-pool-card--clickable"
             role="button" tabindex="0"
             data-bs-toggle="modal" data-bs-target="#walletUsersModal"
             data-wallet-filter="Student" data-wallet-title="Student Wallet Users (<?= number_format(
                 $walletStats["total"],
             ) ?>)"
             aria-label="View student wallet users in a table">
            <div class="ce-pool-icon-wrap">
                <i class="fa-solid fa-user-graduate ce-pool-icon"></i>
            </div>
            <div class="ce-pool-info" style="flex:1;min-width:0;">
                <span class="ce-pool-label">Student Wallet Users</span>
                <div class="ce-pool-amt"><?= number_format(
                    $walletStats["total"],
                ) ?></div>

                <?php $activePct =
                    $walletStats["total"] > 0
                        ? round(
                            ($walletStats["active"] / $walletStats["total"]) *
                                100,
                        )
                        : 0; ?>
                <div class="ce-wu-bar-wrap">
                    <div class="ce-wu-bar">
                        <div class="ce-wu-bar-fill" style="width:<?= $activePct ?>%"></div>
                    </div>
                    <span class="ce-wu-pct"><?= $activePct ?>%</span>
                </div>

                <div class="ce-wu-badges">
                    <span class="ce-wu-badge ce-wu-badge--active">
                        <span class="ce-wu-dot ce-wu-dot--active"></span>
                        <?= number_format($walletStats["active"]) ?> Active
                    </span>
                    <span class="ce-wu-badge ce-wu-badge--inactive">
                        <span class="ce-wu-dot ce-wu-dot--inactive"></span>
                        <?= number_format($walletStats["inactive"]) ?> Inactive
                    </span>
                </div>

                <small class="ce-pool-share">No activity in 30 days = inactive</small>
            </div>
        </div>

        <div class="ce-pool-card ce-pool-merchants ce-pool-card--wallet ce-pool-card--clickable"
             role="button" tabindex="0"
             data-bs-toggle="modal" data-bs-target="#walletUsersModal"
             data-wallet-filter="Merchant" data-wallet-title="Merchant Wallet Users (<?= number_format(
                 $merchantWalletStats["total"],
             ) ?>)"
             aria-label="View merchant wallet users in a table">
            <div class="ce-pool-icon-wrap">
                <i class="fa-solid fa-store ce-pool-icon"></i>
            </div>
            <div class="ce-pool-info" style="flex:1;min-width:0;">
                <span class="ce-pool-label">Merchant Wallet Users</span>
                <div class="ce-pool-amt"><?= number_format(
                    $merchantWalletStats["total"],
                ) ?></div>

                <?php $mActivePct =
                    $merchantWalletStats["total"] > 0
                        ? round(
                            ($merchantWalletStats["active"] /
                                $merchantWalletStats["total"]) *
                                100,
                        )
                        : 0; ?>
                <div class="ce-wu-bar-wrap">
                    <div class="ce-wu-bar">
                        <div class="ce-wu-bar-fill ce-wu-bar-fill--merchant" style="width:<?= $mActivePct ?>%"></div>
                    </div>
                    <span class="ce-wu-pct ce-wu-pct--merchant"><?= $mActivePct ?>%</span>
                </div>

                <div class="ce-wu-badges">
                    <span class="ce-wu-badge ce-wu-badge--merchant-active">
                        <span class="ce-wu-dot ce-wu-dot--merchant"></span>
                        <?= number_format(
                            $merchantWalletStats["active"],
                        ) ?> Active
                    </span>
                    <span class="ce-wu-badge ce-wu-badge--inactive">
                        <span class="ce-wu-dot ce-wu-dot--inactive"></span>
                        <?= number_format(
                            $merchantWalletStats["inactive"],
                        ) ?> Inactive
                    </span>
                </div>

                <small class="ce-pool-share">No sales in 30 days = inactive</small>
            </div>
        </div>

    </div>

    <div class="ce-bottom-grid">


        <div class="ce-mint-info-panel <?= $limitHit ? "ce-limit-hit" : "" ?>">
            <div class="ce-mint-info-header">
                <div class="ce-mint-info-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <div>
                    <div class="ce-mint-info-title">Monthly Top-Up Budget</div>
                    <div class="ce-mint-info-sub">
                        <?= $limitHit
                            ? "Monthly limit reached — PIN required to continue"
                            : "Within the " .
                                gjc_money(MintingGuard::SOFT_LIMIT) .
                                " monthly limit" ?>
                    </div>
                </div>
            </div>
            <div class="ce-mint-track-wrap">
                <div class="ce-mint-track">
                    <div class="ce-mint-track-fill <?= $limitHit
                        ? "ce-track-warn"
                        : "ce-track-ok" ?>"
                         style="width:<?= min(100, $mintUsedPct) ?>%">
                    </div>
                </div>
                <span class="ce-mint-pct"><?= min(100, $mintUsedPct) ?>%</span>
            </div>
            <div class="ce-mint-stats">
                <div class="ce-mint-stat-item ce-mint-stat-primary">
                    <span>Budget remaining this month</span>
                    <strong><?= gjc_money(
                        max(0, (float) $monthly["remaining_soft_limit"]),
                    ) ?></strong>
                </div>
                <div class="ce-mint-stat-item">
                    <span>Added this month</span>
                    <strong><?= gjc_money($minted) ?></strong>
                </div>
                <div class="ce-mint-stat-item">
                    <span>Times money was added</span>
                    <strong><?= $monthly["mint_events"] ?></strong>
                </div>
                <div class="ce-mint-stat-item">
                    <span>Maximum allowed limit</span>
                    <strong><?= gjc_money(
                        (float) $monthly["hard_limit"],
                    ) ?></strong>
                </div>
            </div>
        </div>


        <?php if (($_SESSION["sub_role"] ?? "") === "super_admin"): ?>
        <div class="ce-mint-form-panel">
            <div class="ce-mint-form-header">
                <span class="ce-mint-badge">Admin</span>
                <div class="ce-mint-form-title">Add Money to the System</div>
                <div class="ce-mint-form-sub">Adds new money to the system and places it in the Cashier Vault</div>
            </div>

            <div id="ce-mint-alert"></div>

            <form id="ce-mint-form">
                <div class="ce-field-row">
                    <div class="ce-field">
                        <label class="ce-label" for="ce-amount">Amount (&#8369;)</label>
                        <input type="number" id="ce-amount" class="ce-input"
                               min="1" step="0.01" placeholder="e.g. 10,000" required>
                    </div>
                    <div class="ce-field ce-field-wide">
                        <label class="ce-label" for="ce-reason">Reason for Adding Money</label>
                        <input type="text" id="ce-reason" class="ce-input"
                               placeholder="e.g. Q3 budget approved by board" required>
                    </div>
                </div>
                <div class="ce-field" id="ce-pin-wrap"
                     style="display:<?= $limitHit ? "block" : "none" ?>">
                    <label class="ce-label" for="ce-pin">
                        Security PIN
                        <span class="ce-pin-badge">Required when monthly limit is exceeded</span>
                    </label>
                    <input type="password" id="ce-pin" class="ce-input" placeholder="Enter Security PIN">
                </div>
                <button type="submit" class="ce-mint-btn" id="ce-mint-btn">
                    <span class="ce-mint-btn-content">
                        <i class="fa-solid fa-coins"></i>
                        <span>Add Money to System</span>
                    </span>
                </button>
            </form>
        </div>
        <?php else: ?>

        <div class="ce-flow-guide">
            <div class="ce-flow-guide-title">How Money Moves</div>
            <div class="ce-flow-steps">
                <div class="ce-flow-step">
                    <div class="ce-flow-step-icon" style="background:var(--gjc-green-800)">1</div>
                    <div class="ce-flow-step-info">
                        <strong>Add</strong>
                        <span>Admin adds to Vault</span>
                    </div>
                    <div class="ce-flow-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
                <div class="ce-flow-step">
                    <div class="ce-flow-step-icon" style="background:var(--gjc-green-700)">2</div>
                    <div class="ce-flow-step-info">
                        <strong>Load</strong>
                        <span>Vault to Student Wallet</span>
                    </div>
                    <div class="ce-flow-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
                <div class="ce-flow-step">
                    <div class="ce-flow-step-icon" style="background:var(--gjc-gold-600)">3</div>
                    <div class="ce-flow-step-info">
                        <strong>Pay</strong>
                        <span>Student pays Merchant</span>
                    </div>
                    <div class="ce-flow-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
                <div class="ce-flow-step">
                    <div class="ce-flow-step-icon" style="background:var(--gjc-slate)">4</div>
                    <div class="ce-flow-step-info">
                        <strong>Cash Out</strong>
                        <span>Merchant cashes out to Vault</span>
                    </div>
                    <div class="ce-flow-arrow ce-invisible"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Total circulation and balance status are already shown above
         (ledger panel + section badge) - the footer only adds the timestamp. -->
    <div class="ce-footer">
        <i class="fa-solid fa-clock-rotate-left" style="opacity:.6"></i>
        <span>Last updated: <strong><?= $snap["as_of"] ??
            "N/A" ?></strong></span>
    </div>

</section>

<!-- Total Wallet Users drill-in table -->
<div class="modal fade" id="walletUsersModal" tabindex="-1" aria-labelledby="walletUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none">
            <div class="modal-header" style="background:var(--gjc-soft)">
                <h5 class="modal-title" id="walletUsersModalLabel" style="font-weight:800;color:var(--gjc-green-800)">
                    <i class="fa-solid fa-users me-2"></i><span id="walletUsersModalText">All Wallet Users (<?= number_format(
                        $totalWalletUsers,
                    ) ?>)</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table align-middle js-datatable" id="walletUsersTable"
                       data-page-length="10" data-empty-message="No wallet users found">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($walletUsersList as $wu): ?>
                        <tr>
                            <td><?= htmlspecialchars(
                                $wu["name"],
                                ENT_QUOTES,
                            ) ?></td>
                            <td>
                                <span class="ce-type-pill ce-type-<?= strtolower(
                                    $wu["type"],
                                ) ?>">
                                    <?= htmlspecialchars(
                                        $wu["type"],
                                        ENT_QUOTES,
                                    ) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($wu["active"]): ?>
                                <span class="ce-wu-badge ce-wu-badge--active">
                                    <span class="ce-wu-dot ce-wu-dot--active"></span> Active
                                </span>
                                <?php else: ?>
                                <span class="ce-wu-badge ce-wu-badge--inactive">
                                    <span class="ce-wu-dot ce-wu-dot--inactive"></span> Inactive
                                </span>
                                <?php endif; ?>
                            </td>
                            <td data-order="<?= $wu["last_txn"]
                                ? strtotime($wu["last_txn"])
                                : 0 ?>">
                                <?= $wu["last_txn"]
                                    ? date("M j, Y", strtotime($wu["last_txn"]))
                                    : '<span style="color:var(--gjc-muted)">&mdash;</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


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
            btn.innerHTML = `<span class="ce-mint-btn-content"><i class="fa-solid fa-coins"></i><span>Add Money to System</span></span>`;
        });
    }


})();
</script>
