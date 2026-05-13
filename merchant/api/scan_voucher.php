<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/VoucherEngine.php';

gjc_require_role(['merchant']);

$currentUser = gjc_current_user($db);
$merchantUserId = (int) ($currentUser['id'] ?? 0);
$wallet = gjc_merchant_wallet($db, $merchantUserId);

if ($merchantUserId <= 0 || $wallet['id'] <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Merchant wallet access is required.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$body = str_contains($contentType, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : $_POST;

$action = strtolower(trim((string) ($body['action'] ?? '')));
$qrHash = trim((string) ($body['qr_hash'] ?? ''));
$merchantWalletId = (int) ($body['merchant_wallet_id'] ?? 0);
$amount = (float) ($body['amount'] ?? 0);

$ve = new VoucherEngine($db);

try {
    switch ($action) {
        case 'validate':
            if ($qrHash === '') {
                throw new InvalidArgumentException('qr_hash is required.');
            }

            $result = $ve->scanValidate($qrHash, $merchantUserId);
            echo json_encode(array_merge(['success' => true], $result));
            break;

        case 'pay':
            if ($qrHash === '') {
                throw new InvalidArgumentException('qr_hash is required.');
            }
            if ($amount <= 0) {
                throw new InvalidArgumentException('Amount must be greater than zero.');
            }

            if ($merchantWalletId <= 0) {
                $merchantWalletId = (int) $wallet['id'];
            }

            if ($merchantWalletId !== (int) $wallet['id']) {
                throw new RuntimeException('The selected merchant wallet does not match your account.');
            }

            $result = $ve->voucherPay($qrHash, $merchantWalletId, $amount, $merchantUserId);
            echo json_encode($result);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Unknown action: '{$action}'"]);
    }
} catch (RuntimeException | InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[scan_voucher] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
}
