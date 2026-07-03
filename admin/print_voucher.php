<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);

$voucherId = (int) ($_GET['id'] ?? 0);
$voucherCode = trim((string) ($_GET['code'] ?? ''));

if ($voucherId > 0) {
    $stmt = $db->prepare('SELECT * FROM vouchers WHERE id = ? LIMIT 1');
    $stmt->execute([$voucherId]);
} elseif ($voucherCode !== '') {
    $stmt = $db->prepare('SELECT * FROM vouchers WHERE voucher_code = ? LIMIT 1');
    $stmt->execute([$voucherCode]);
} else {
    http_response_code(400);
    exit('Voucher id or code is required.');
}

$voucher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$voucher) {
    http_response_code(404);
    exit('Voucher not found.');
}

$qrPayload = json_encode([
    'type' => 'VISITOR_VOUCHER',
    'hash' => $voucher['qr_code_hash'],
    'code' => $voucher['voucher_code'],
    'exp' => $voucher['expires_at'],
    'issuer' => 'GJC-EDUPAY',
], JSON_UNESCAPED_SLASHES);

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . rawurlencode((string) $qrPayload);
$status = strtolower((string) ($voucher['status'] ?? 'active'));
$isExpiredByTime = strtotime((string) $voucher['expires_at']) < time();
$displayStatus = $status === 'active' && $isExpiredByTime ? 'expired pending' : $status;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher <?= gjc_e($voucher['voucher_code']) ?> | GenPay</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/print_voucher.css?v=1">
</head>
<body>
    <div class="print-actions">
        <a class="secondary" href="<?= ADMIN_URL ?>/visitors.php">Back</a>
        <button type="button" onclick="downloadVoucherSvg()">Download SVG</button>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <main class="voucher" id="voucherCard">
        <section class="voucher-top">
            <div class="brand">
                <h1>GenPay Visitor Voucher</h1>
                <p>Present this QR code to participating campus merchants.</p>
            </div>
            <div class="amount">
                <p>Loaded Value</p>
                <strong><?= gjc_money($voucher['initial_value']) ?></strong>
            </div>
        </section>

        <section class="voucher-body">
            <div>
                <div class="detail-grid">
                    <div class="detail">
                        <span>Visitor</span>
                        <strong><?= gjc_e($voucher['visitor_name']) ?></strong>
                    </div>
                    <div class="detail">
                        <span>Contact / ID</span>
                        <strong><?= gjc_e($voucher['visitor_contact'] ?: 'Not provided') ?></strong>
                    </div>
                    <div class="detail">
                        <span>Voucher Code</span>
                        <strong><?= gjc_e($voucher['voucher_code']) ?></strong>
                    </div>
                    <div class="detail">
                        <span>Status</span>
                        <strong><?= gjc_e(ucwords($displayStatus)) ?></strong>
                    </div>
                    <div class="detail">
                        <span>Remaining Balance</span>
                        <strong><?= gjc_money($voucher['remaining_balance']) ?></strong>
                    </div>
                    <div class="detail">
                        <span>Expires</span>
                        <strong><?= date('M d, Y h:i A', strtotime((string) $voucher['expires_at'])) ?></strong>
                    </div>
                </div>

                <p class="voucher-note">
                    This voucher is valid until the expiry date above or until the balance is fully used.
                    Unused non-refundable balance returns to the school vault on expiry.
                </p>

                <div class="raw-payload" id="voucherPayload"><?= gjc_e((string) $qrPayload) ?></div>
            </div>

            <aside class="qr-panel">
                <img src="<?= gjc_e($qrUrl) ?>" alt="Voucher QR code" id="voucherQr">
                <strong><?= gjc_e($voucher['voucher_code']) ?></strong>
            </aside>
        </section>
    </main>

    <script>
    const voucherData = {
        code: <?= json_encode((string) $voucher['voucher_code']) ?>,
        visitor: <?= json_encode((string) $voucher['visitor_name']) ?>,
        contact: <?= json_encode((string) ($voucher['visitor_contact'] ?: 'Not provided')) ?>,
        amount: <?= json_encode('PHP ' . number_format((float) $voucher['initial_value'], 2)) ?>,
        balance: <?= json_encode('PHP ' . number_format((float) $voucher['remaining_balance'], 2)) ?>,
        expires: <?= json_encode(date('M d, Y h:i A', strtotime((string) $voucher['expires_at']))) ?>,
        status: <?= json_encode(ucwords($displayStatus)) ?>,
        qrUrl: <?= json_encode($qrUrl) ?>
    };

    function escapeSvg(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[char];
        });
    }

    function downloadVoucherSvg() {
        const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="900" height="560" viewBox="0 0 900 560">
  <rect width="900" height="560" rx="18" fill="#ffffff"/>
  <rect width="900" height="140" rx="18" fill="#064420"/>
  <rect y="120" width="900" height="30" fill="#064420"/>
  <text x="42" y="58" fill="#ffffff" font-family="Arial" font-size="31" font-weight="700">GenPay Visitor Voucher</text>
  <text x="42" y="92" fill="#d9eadf" font-family="Arial" font-size="16">Present this QR code to participating campus merchants.</text>
  <text x="858" y="54" text-anchor="end" fill="#d9eadf" font-family="Arial" font-size="15">Loaded Value</text>
  <text x="858" y="102" text-anchor="end" fill="#ffe39a" font-family="Arial" font-size="44" font-weight="700">${escapeSvg(voucherData.amount)}</text>
  <text x="42" y="195" fill="#647067" font-family="Arial" font-size="13" font-weight="700">VISITOR</text>
  <text x="42" y="225" fill="#111827" font-family="Arial" font-size="23" font-weight="700">${escapeSvg(voucherData.visitor)}</text>
  <text x="42" y="278" fill="#647067" font-family="Arial" font-size="13" font-weight="700">CONTACT / ID</text>
  <text x="42" y="307" fill="#111827" font-family="Arial" font-size="20">${escapeSvg(voucherData.contact)}</text>
  <text x="42" y="360" fill="#647067" font-family="Arial" font-size="13" font-weight="700">VOUCHER CODE</text>
  <text x="42" y="390" fill="#111827" font-family="Arial" font-size="24" font-weight="700">${escapeSvg(voucherData.code)}</text>
  <text x="42" y="443" fill="#647067" font-family="Arial" font-size="13" font-weight="700">REMAINING / STATUS</text>
  <text x="42" y="472" fill="#111827" font-family="Arial" font-size="20">${escapeSvg(voucherData.balance)} - ${escapeSvg(voucherData.status)}</text>
  <text x="42" y="516" fill="#647067" font-family="Arial" font-size="16">Expires ${escapeSvg(voucherData.expires)}</text>
  <rect x="604" y="176" width="244" height="290" rx="12" fill="#f8faf9" stroke="#b9c8bf" stroke-dasharray="8 8"/>
  <image href="${escapeSvg(voucherData.qrUrl)}" x="626" y="198" width="200" height="200"/>
  <text x="726" y="435" text-anchor="middle" fill="#111827" font-family="Arial" font-size="21" font-weight="700">${escapeSvg(voucherData.code)}</text>
</svg>`;
        const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'voucher-' + voucherData.code + '.svg';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () {
            URL.revokeObjectURL(link.href);
        }, 1000);
    }
    </script>
</body>
</html>
