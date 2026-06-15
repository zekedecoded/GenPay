<?php
// ============================================================
//  admin/stall_verify.php
//  Phase 3 + 4: Contract review, payment verification,
//               and Final Approval cascade
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);
$currentUser = gjc_current_user($db);
$currentPage = 'stall_applications';
$adminId     = gjc_user_id();

$appId = (int) ($_GET['app_id'] ?? 0);
if (!$appId) { header('Location: ' . ADMIN_URL . '/stall_applications.php'); exit; }

// Fetch application + stall info
$stmt = $db->prepare(
    "SELECT sa.*,
            s.label AS stall_label, s.monthly_rate,
            s.row_label, s.col_number, s.area_sqm,
            CONCAT(u.first_name,' ',u.last_name) AS approver_name
     FROM stall_applications sa
     LEFT JOIN stalls s ON s.stall_id = sa.stall_id
     LEFT JOIN users  u ON u.userID   = sa.initially_approved_by
     WHERE sa.id = ?
     LIMIT 1"
);
$stmt->execute([$appId]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app || !in_array($app['status'], ['initially_approved', 'active'], true)) {
    header('Location: ' . ADMIN_URL . '/stall_applications.php');
    exit;
}

// Check if payment already verified
$payment = $db->prepare(
    "SELECT pv.*, CONCAT(u.first_name,' ',u.last_name) AS verifier_name
     FROM payment_verifications pv
     LEFT JOIN users u ON u.userID = pv.verified_by
     WHERE pv.application_id = ?
     LIMIT 1"
);
$payment->execute([$appId]);
$payment = $payment->fetch(PDO::FETCH_ASSOC);

// Check if merchant_accounts record already exists (fully approved)
$merchantAcct = $db->prepare(
    "SELECT ma.*, u.email AS merchant_email, u.first_name, u.last_name
     FROM merchant_accounts ma
     LEFT JOIN users u ON u.userID = ma.user_id
     WHERE ma.application_id = ?
     LIMIT 1"
);
$merchantAcct->execute([$appId]);
$merchantAcct = $merchantAcct->fetch(PDO::FETCH_ASSOC);

$isFullyApproved = $app['status'] === 'active';
$paymentDone     = (bool) $payment;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Application <?= htmlspecialchars($app['contract_ref'] ?? "#$appId") ?> | GJC EduPay Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* â”€â”€ layout â”€â”€ */
        .verify-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 900px) { .verify-grid { grid-template-columns: 1fr; } }

        /* â”€â”€ cards â”€â”€ */
        .vcard {
            background: #fff; border-radius: 18px; border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0,0,0,.05); overflow: hidden;
            margin-bottom: 20px;
        }
        .vcard-head {
            padding: 18px 22px 14px; border-bottom: 1px solid #f3f4f6;
            display: flex; align-items: center; justify-content: space-between;
        }
        .vcard-title {
            font-size: 13px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .07em; color: #374151;
            display: flex; align-items: center; gap: 8px;
        }
        .vcard-body { padding: 20px 22px; }

        /* â”€â”€ contract â”€â”€ */
        .contract-body {
            background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 22px; max-height: 420px; overflow-y: auto;
            font-size: 13px; line-height: 1.75; color: #374151;
        }
        .contract-body h3 { font-size: 15px; font-weight: 800; color: #064420; margin: 16px 0 6px; }
        .contract-body ol { padding-left: 20px; }
        .contract-body li { margin-bottom: 6px; }

        /* â”€â”€ detail grid â”€â”€ */
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .detail-field label {
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; color: #9ca3af; display: block; margin-bottom: 3px;
        }
        .detail-field p { font-size: 14px; font-weight: 700; color: #111827; margin: 0; }
        .detail-field p.mono { font-family: monospace; color: #15803d; font-size: 16px; }

        .doc-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
        .doc-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 50px; font-size: 11px; font-weight: 700;
            background: #f3f4f6; border: 1px solid #e5e7eb;
            text-decoration: none; color: #374151; transition: background .15s;
        }
        .doc-chip:hover { background: #e5e7eb; }

        /* â”€â”€ Accenture-style step progress bar â”€â”€ */
        .step-bar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
            margin-bottom: 36px;
        }
        /* grey connecting line */
        .step-bar::before {
            content: '';
            position: absolute;
            top: 14px;
            left: 14px;
            right: 14px;
            height: 2px;
            background: #d1d5db;
            z-index: 0;
        }
        /* green animated fill */
        .step-bar-fill {
            position: absolute;
            top: 14px;
            left: 14px;
            height: 2px;
            background: linear-gradient(90deg, #22c55e, #16a34a);
            z-index: 1;
            transition: width 0.5s cubic-bezier(.4,0,.2,1);
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            z-index: 2;
            flex: 1;
        }
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #d1d5db;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0;
            position: relative;
            transition: border-color .3s, background .3s, box-shadow .3s;
        }
        /* active step â€” hollow ring with green dot */
        .step-circle.active {
            border-color: #22c55e;
            box-shadow: 0 0 0 4px rgba(34,197,94,.15);
        }
        .step-circle.active::after {
            content: '';
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: #22c55e;
        }
        /* done step â€” solid green with checkmark */
        .step-circle.done {
            background: #22c55e;
            border-color: #22c55e;
        }
        .step-circle.done::after {
            content: '\2713';
            font-size: 13px;
            font-weight: 800;
            color: #fff;
        }
        .step-label {
            font-size: 11px;
            color: #9ca3af;
            text-align: center;
            font-weight: 600;
            line-height: 1.35;
        }
        .step-sublabel {
            font-size: 10px;
            color: #b0bac9;
            text-align: center;
            margin-top: -4px;
        }
        .step-item.s-active .step-label { color: #15803d; }
        .step-item.s-done   .step-label { color: #15803d; }
        @media (max-width:600px) { .step-label, .step-sublabel { display: none; } }

        /* â”€â”€ payment form â”€â”€ */
        .payment-form-group { margin-bottom: 16px; }
        .payment-form-group label {
            display: block; font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .07em; color: #374151; margin-bottom: 6px;
        }
        .payment-input {
            width: 100%; padding: 12px 16px; border-radius: 12px;
            border: 2px solid #e5e7eb; font-size: 14px; font-weight: 600;
            font-family: inherit; color: #111; outline: none; transition: border .18s;
        }
        .payment-input:focus { border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.1); }
        .payment-input:read-only { background: #f9fafb; color: #6b7280; }
        .payment-input.mono { font-family: monospace; font-size: 16px; letter-spacing: .04em; }

        .amount-badge {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 18px; background: linear-gradient(135deg,#f0fdf4,#dcfce7);
            border: 1px solid #86efac; border-radius: 12px; margin-bottom: 16px;
        }
        .amount-label { font-size: 12px; font-weight: 700; color: #15803d; }
        .amount-value { font-size: 24px; font-weight: 900; color: #064420; }

        /* â”€â”€ buttons â”€â”€ */
        .btn-verify-pay {
            width: 100%; padding: 15px; border: none; border-radius: 50px;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: #052e16; font-size: 14px; font-weight: 900;
            cursor: pointer; font-family: inherit; transition: all .18s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-verify-pay:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(34,197,94,.35); }
        .btn-verify-pay:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        .btn-final-approve {
            width: 100%; padding: 16px; border: none; border-radius: 50px;
            background: linear-gradient(135deg, #1d4ed8, #7c3aed);
            color: #fff; font-size: 15px; font-weight: 900;
            cursor: pointer; font-family: inherit; transition: all .2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 6px;
        }
        .btn-final-approve:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(124,58,237,.4); }
        .btn-final-approve:disabled { opacity: .4; cursor: not-allowed; transform: none; box-shadow: none; }

        /* â”€â”€ payment done indicator â”€â”€ */
        .payment-done-banner {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1.5px solid #86efac; border-radius: 14px; padding: 18px 20px;
            display: flex; align-items: center; gap: 14px; margin-bottom: 16px;
        }
        .payment-done-icon { font-size: 32px; }
        .payment-done-ref { font-size: 18px; font-weight: 900; font-family: monospace; color: #064420; }
        .payment-done-label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #15803d; font-weight: 700; }

        /* â”€â”€ success state â”€â”€ */
        .fully-approved-banner {
            background: linear-gradient(135deg, #ede9fe, #ddd6fe);
            border: 2px solid #a78bfa; border-radius: 16px; padding: 24px;
            text-align: center;
        }
        .credential-box {
            background: #1e1b4b; border-radius: 12px; padding: 16px 20px;
            margin: 14px 0; text-align: left;
            font-family: monospace; font-size: 13px; color: #c4b5fd;
        }
        .credential-box strong { color: #a78bfa; }

        /* â”€â”€ toast â”€â”€ */
        .toast-wrap { position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px; }
        .toast { padding:12px 20px; border-radius:12px; font-size:13px; font-weight:700; box-shadow:0 4px 16px rgba(0,0,0,.15); animation:fadeIn .2s ease; max-width:340px; }
        .toast--success { background:#064420; color:#4ade80; }
        .toast--error   { background:#7f1d1d; color:#fca5a5; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

    <main class="admin-main">
        <header class="topbar">
            <button class="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">&#9776;</button>
            <div>
                <h1>Contract &amp; Payment Verification</h1>
                <p>Step 2.2 - Review contract, confirm payment, then grant Final Approval.</p>
            </div>
            <div class="admin-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="avatar"><img src="<?= ICONS_URL ?>/admin.png" alt="Admin"></div>
            </div>
        </header>

        <!-- Back link -->
        <a href="<?= ADMIN_URL ?>/stall_applications.php"
           style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:#6b7280;text-decoration:none;margin-bottom:20px">
            &larr; Back to Applications
        </a>

        <!-- Accenture-style step progress bar -->
        <?php
            // step index: 1=initial done, 2=payment, 3=final
            $currentStep = $isFullyApproved ? 3 : ($paymentDone ? 3 : 2);
            // fill width: 0% between step1-2 = 0%, done step2 = 50%, done step3 = 100%
            $fillPct = $isFullyApproved ? '100%' : ($paymentDone ? '50%' : '0%');
        ?>
        <div class="step-bar" id="stepBar">
            <div class="step-bar-fill" id="stepFill" style="width:<?= $fillPct ?>"></div>

            <!-- Step 1: Initial Approval (always done on this page) -->
            <div class="step-item s-done" id="step-nav-1">
                <div class="step-circle done" id="circle-1"></div>
                <span class="step-label">Initial Approval</span>
                <span class="step-sublabel">Email sent</span>
            </div>

            <!-- Step 2: Contract & Payment -->
            <div class="step-item <?= $paymentDone ? 's-done' : 's-active' ?>" id="step-nav-2">
                <div class="step-circle <?= $paymentDone ? 'done' : 'active' ?>" id="circle-2"></div>
                <span class="step-label">Contract &amp; Payment</span>
                <span class="step-sublabel">&#8369;150 GCash verification</span>
            </div>

            <!-- Step 3: Final Approval -->
            <div class="step-item <?= $isFullyApproved ? 's-done' : '' ?>" id="step-nav-3">
                <div class="step-circle <?= $isFullyApproved ? 'done' : '' ?>" id="circle-3"></div>
                <span class="step-label">Final Approval</span>
                <span class="step-sublabel">Account created</span>
            </div>
        </div>

        <div class="verify-grid">

            <!-- LEFT: Application info + contract terms -->
            <div>
                <!-- Application summary -->
                <div class="vcard">
                    <div class="vcard-head">
                        <div class="vcard-title">Application Summary</div>
                        <span style="font-size:12px;font-weight:800;font-family:monospace;color:#15803d;background:#f0fdf4;padding:4px 12px;border-radius:50px">
                            <?= htmlspecialchars($app['contract_ref'] ?? "SA-{$appId}") ?>
                        </span>
                    </div>
                    <div class="vcard-body">
                        <div class="detail-grid">
                            <div class="detail-field">
                                <label>Business Name</label>
                                <p><?= htmlspecialchars($app['business_name']) ?></p>
                            </div>
                            <div class="detail-field">
                                <label>Stall Assigned</label>
                                <p><?= htmlspecialchars($app['stall_id']) ?> - <?= htmlspecialchars($app['stall_label'] ?? '') ?></p>
                            </div>
                            <div class="detail-field">
                                <label>Proprietor</label>
                                <p><?= htmlspecialchars($app['proprietor_name']) ?></p>
                            </div>
                            <div class="detail-field">
                                <label>Monthly Rate</label>
                                <p>&#8369;<?= number_format($app['monthly_rate'] ?? 2500, 2) ?></p>
                            </div>
                            <div class="detail-field">
                                <label>Email</label>
                                <p><?= htmlspecialchars($app['email']) ?></p>
                            </div>
                            <div class="detail-field">
                                <label>Contact</label>
                                <p><?= htmlspecialchars($app['contact_number']) ?></p>
                            </div>
                            <?php if ($app['initially_approved_at']): ?>
                            <div class="detail-field">
                                <label>Approved By</label>
                                <p><?= htmlspecialchars($app['approver_name'] ?? 'Admin') ?></p>
                            </div>
                            <div class="detail-field">
                                <label>Approved At</label>
                                <p><?= date('M j, Y g:i A', strtotime($app['initially_approved_at'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:14px">
                            <label style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;display:block;margin-bottom:8px">Documents</label>
                            <div class="doc-chips">
                                <?php
                                $docs = [
                                    'Profile Photo'    => $app['profile_picture'],
                                    'Business Permit'  => $app['business_permit'],
                                    'Sanitary Permit'  => $app['sanitary_permit'],
                                    'GJC Requirements' => $app['gjc_requirements'],
                                    'Clearance'        => $app['clearance'],
                                ];
                                foreach ($docs as $label => $path):
                                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                    $icon = $ext === 'pdf' ? 'PDF' : 'IMG';
                                ?>
                                <a href="<?= ADMIN_URL . '/doc.php?f=' . urlencode(ltrim($path, '/')) ?>"
                                   target="_blank" class="doc-chip">
                                    <?= $icon ?> <?= $label ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contract Terms -->
                <div class="vcard">
                    <div class="vcard-head">
                        <div class="vcard-title">Stall Lease Contract Terms</div>
                    </div>
                    <div class="vcard-body">
                        <div class="contract-body" id="contractBody">
                            <p style="font-weight:800;text-align:center;color:#064420;font-size:15px">GENERAL DE JESUS COLLEGE<br>GJC EduPay Stall Lease Agreement</p>
                            <p style="text-align:center;font-size:12px;color:#6b7280">Contract Ref: <strong style="font-family:monospace"><?= htmlspecialchars($app['contract_ref'] ?? '') ?></strong></p>

                            <h3>1. Parties</h3>
                            <p>This lease agreement is entered into between <strong>General de Jesus College (GJC)</strong>, hereinafter referred to as the "Lessor," and <strong><?= htmlspecialchars($app['proprietor_name']) ?></strong>, operating as <strong><?= htmlspecialchars($app['business_name']) ?></strong>, hereinafter referred to as the "Lessee."</p>

                            <h3>2. Leased Premises</h3>
                            <p>The Lessor agrees to lease Stall <strong><?= htmlspecialchars($app['stall_id']) ?></strong> (<?= htmlspecialchars($app['stall_label'] ?? $app['stall_id']) ?>) located within the GJC Campus commercial area. The stall is provided as-is for the purpose of conducting lawful commercial activities.</p>

                            <h3>3. Lease Term &amp; Renewal</h3>
                            <ol>
                                <li>The initial lease term is <strong>one (1) academic semester</strong>, commencing upon final approval of this agreement.</li>
                                <li>Renewal is subject to satisfactory performance review and timely payment of all dues.</li>
                                <li>Either party may terminate this agreement with <strong>fifteen (15) days</strong> written notice.</li>
                            </ol>

                            <h3>4. Monthly Rental</h3>
                            <ol>
                                <li>The monthly rental rate is <strong>&#8369;<?= number_format($app['monthly_rate'] ?? 2500, 2) ?></strong>, payable on or before the <strong>5th day</strong> of each month via GJC EduPay.</li>
                                <li>A penalty of <strong>2% per month</strong> shall be imposed on unpaid balances past the due date.</li>
                                <li>Three (3) consecutive months of default shall be grounds for immediate termination of this agreement.</li>
                            </ol>

                            <h3>5. Use of Premises</h3>
                            <ol>
                                <li>The Lessee shall use the stall exclusively for the business described in the approved application.</li>
                                <li>Subleasing or assignment of stall rights to any third party is strictly prohibited.</li>
                                <li>The Lessee shall keep the stall and its immediate surroundings clean and orderly at all times.</li>
                                <li>Operating hours shall conform to GJC campus regulations. Overnight stays are not permitted.</li>
                            </ol>

                            <h3>6. Permitted Products &amp; Conduct</h3>
                            <ol>
                                <li>Sale of regulated, prohibited, or age-restricted items is strictly forbidden.</li>
                                <li>Pricing must comply with GJC canteen and commerce committee guidelines.</li>
                                <li>All transactions must be processed through the <strong>GJC EduPay</strong> digital wallet system where applicable.</li>
                            </ol>

                            <h3>7. Processing Fee</h3>
                            <p>A one-time, non-refundable processing fee of <strong>&#8369;150.00</strong> is due upon signing this agreement, payable via GCash to the GJC Finance Office. This fee covers administrative and onboarding costs.</p>

                            <h3>8. Liability &amp; Compliance</h3>
                            <ol>
                                <li>The Lessee is solely responsible for securing all required business, health, and sanitary permits and keeping them current for the duration of the lease.</li>
                                <li>GJC shall not be liable for any loss, damage, or injury resulting from the Lessee's operations.</li>
                                <li>The Lessee agrees to comply with all GJC policies, rules, and regulations as amended from time to time.</li>
                            </ol>

                            <h3>9. Termination</h3>
                            <p>GJC reserves the right to immediately terminate this agreement without prior notice in cases of: violation of campus policies; non-payment of three (3) or more months; sale of prohibited items; or conduct unbecoming within the campus premises.</p>

                            <h3>10. Agreement &amp; Governing Law</h3>
                            <p>By proceeding with Final Approval, the administrator confirms that the Lessee has reviewed and agreed to all terms herein. This agreement shall be governed by the applicable laws of the Republic of the Philippines.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Payment + Final Approval panel -->
            <div>

                <?php if ($isFullyApproved && $merchantAcct): ?>
                <!-- â”€â”€ Fully Approved State â”€â”€ -->
                <div class="vcard">
                    <div class="vcard-head">
                        <div class="vcard-title" style="color:#7c3aed">Fully Approved</div>
                    </div>
                    <div class="vcard-body">
                        <div class="fully-approved-banner">
                            <div style="font-size:18px;font-weight:900;color:#4c1d95;margin-bottom:8px">Done</div>
                            <div style="font-size:15px;font-weight:800;color:#4c1d95;margin-bottom:4px">Merchant Account Created</div>
                            <div style="font-size:12px;color:#6d28d9">Final approval completed. Account is active.</div>
                        </div>
                        <div style="margin-top:16px">
                            <label style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;display:block;margin-bottom:8px">Account Credentials</label>
                            <div class="credential-box">
                                <div><strong>Email:</strong> <?= htmlspecialchars($merchantAcct['merchant_email'] ?? '') ?></div>
                                <?php if ($merchantAcct['temp_password_plain']): ?>
                                <div style="margin-top:6px"><strong>Temp Password:</strong> <?= htmlspecialchars($merchantAcct['temp_password_plain']) ?></div>
                                <div style="margin-top:4px;font-size:11px;color:#818cf8">Merchant must change this on first login.</div>
                                <?php else: ?>
                                <div style="margin-top:6px;font-size:11px;color:#818cf8">Password cleared (merchant has logged in).</div>
                                <?php endif; ?>
                            </div>
                            <a href="<?= ADMIN_URL ?>/stall_applications.php"
                               style="display:block;margin-top:12px;text-align:center;padding:12px;background:#f0fdf4;color:#15803d;border-radius:50px;font-size:13px;font-weight:800;text-decoration:none">
                                &larr; Back to Applications
                            </a>
                        </div>
                    </div>
                </div>

                <?php else: ?>

                <!-- â”€â”€ Step 2: Payment Verification â”€â”€ -->
                <div class="vcard" id="paymentCard">
                    <div class="vcard-head">
                        <div class="vcard-title">Payment Verification</div>
                        <?php if ($paymentDone): ?>
                        <span style="font-size:11px;font-weight:800;background:#f0fdf4;color:#15803d;padding:4px 12px;border-radius:50px">Verified</span>
                        <?php endif; ?>
                    </div>
                    <div class="vcard-body">

                        <?php if ($paymentDone): ?>
                        <!-- Payment already recorded -->
                        <div class="payment-done-banner">
                            <div class="payment-done-icon">OK</div>
                            <div>
                                <div class="payment-done-label">GCash Reference</div>
                                <div class="payment-done-ref"><?= htmlspecialchars($payment['gcash_ref_number']) ?></div>
                                <div style="font-size:11px;color:#15803d;margin-top:2px">
                                    &#8369;<?= number_format($payment['amount'], 2) ?> verified by <?= htmlspecialchars($payment['verifier_name'] ?? 'Admin') ?>
                                    on <?= date('M j, Y g:i A', strtotime($payment['verified_at'])) ?>
                                </div>
                            </div>
                        </div>

                        <?php else: ?>
                        <!-- Payment entry form -->
                        <div class="amount-badge">
                            <span class="amount-label">Processing Fee</span>
                            <span class="amount-value">&#8369;150.00</span>
                        </div>

                        <div class="payment-form-group">
                            <label for="gcashRef">GCash Reference Number *</label>
                            <input type="text" id="gcashRef" class="payment-input mono"
                                   placeholder="e.g. 1234567890"
                                   maxlength="60"
                                   autocomplete="off">
                        </div>

                        <div class="payment-form-group">
                            <label for="paymentNotes">Notes (optional)</label>
                            <input type="text" id="paymentNotes" class="payment-input"
                                   placeholder="Any additional remarks..." maxlength="255">
                        </div>

                        <button class="btn-verify-pay" id="btnVerifyPay" onclick="submitPayment()">
                            Record GCash Payment
                        </button>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- â”€â”€ Step 3: Final Approval â”€â”€ -->
                <div class="vcard">
                    <div class="vcard-head">
                        <div class="vcard-title">Final Approval</div>
                    </div>
                    <div class="vcard-body">

                        <?php if (!$paymentDone): ?>
                        <div style="background:#fefce8;border:1px solid #fde68a;border-radius:12px;padding:14px;margin-bottom:16px;font-size:13px;color:#92400e;font-weight:600">
                            Record GCash payment above before granting Final Approval.
                        </div>
                        <?php else: ?>
                        <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:14px;margin-bottom:16px;font-size:13px;color:#15803d;font-weight:600">
                            Payment confirmed. You may now grant Final Approval.
                        </div>
                        <?php endif; ?>

                        <div style="font-size:12px;color:#6b7280;line-height:1.7;margin-bottom:16px">
                            Final Approval will:
                            <ul style="margin:6px 0 0;padding-left:18px">
                                <li>Set stall <strong><?= htmlspecialchars($app['stall_id']) ?></strong> to <strong>Occupied</strong></li>
                                <li>Create merchant account (temp password)</li>
                                <li>Send credentials to <strong><?= htmlspecialchars($app['email']) ?></strong></li>
                                <li>Mark application as <strong>Active</strong></li>
                            </ul>
                        </div>

                        <button class="btn-final-approve" id="btnFinalApprove"
                                <?= !$paymentDone ? 'disabled' : '' ?>
                                onclick="submitFinalApproval()">
                            Grant Final Approval &amp; Create Account
                        </button>

                    </div>
                </div>

                <?php endif; ?>

            </div><!-- end right panel -->
        </div><!-- end verify-grid -->
    </main>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const API_URL = '<?= ADMIN_URL ?>/api/stall_applications';
const APP_ID  = <?= $appId ?>;

function submitPayment() {
    const ref = document.getElementById('gcashRef')?.value.trim();
    if (!ref) { toast('Please enter the GCash reference number.', 'error'); return; }
    if (ref.length < 6) { toast('Reference number seems too short.', 'error'); return; }

    const btn = document.getElementById('btnVerifyPay');
    btn.disabled = true;
    btn.textContent = 'Recording...';

    const fd = new FormData();
    fd.append('action', 'verify_payment');
    fd.append('app_id', APP_ID);
    fd.append('gcash_ref_number', ref);
    fd.append('notes', document.getElementById('paymentNotes')?.value.trim() || '');

    stallApiPost(fd)
        .then(res => {
            if (res.success) {
                toast(res.message, 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                toast(res.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Record GCash Payment';
            }
        })
        .catch(() => {
            toast('Network error. Please try again.', 'error');
            btn.disabled = false;
            btn.textContent = 'Record GCash Payment';
        });
}

function submitFinalApproval() {
    if (!confirm('This will create the merchant account and mark the stall as Occupied.\n\nProceed with Final Approval?')) return;

    const btn = document.getElementById('btnFinalApprove');
    btn.disabled = true;
    btn.textContent = 'Processing...';

    const fd = new FormData();
    fd.append('action', 'final_approval');
    fd.append('app_id', APP_ID);

    stallApiPost(fd)
        .then(res => {
            if (res.success) {
                toast(res.message, 'success');
                setTimeout(() => location.reload(), 1600);
            } else {
                toast(res.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Grant Final Approval & Create Account';
            }
        })
        .catch(() => {
            toast('Network error. Please try again.', 'error');
            btn.disabled = false;
            btn.textContent = 'Grant Final Approval & Create Account';
        });
}

function stallApiPost(fd) {
    return fetch(API_URL, { method:'POST', body:fd }).then(async r => {
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch (err) {
            console.error('Invalid stall application API response:', text);
            return {
                success: false,
                message: 'The server returned an invalid response. Check the PHP error log for details.'
            };
        }
    });
}

function toast(msg, type='success') {
    const t = document.createElement('div');
    t.className = `toast toast--${type}`;
    t.textContent = msg;
    document.getElementById('toastWrap').appendChild(t);
    setTimeout(() => t.remove(), 4500);
}

// â”€â”€ Entrance animation: fill bar sweeps in on load â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
(function() {
    const fill = document.getElementById('stepFill');
    if (!fill) return;
    const target = fill.style.width;   // e.g. "50%" set by PHP
    fill.style.width = '0%';           // reset to 0 before paint
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            fill.style.width = target; // CSS transition fires smoothly
        });
    });
})();
</script>
</body>
</html>
