<?php
declare(strict_types=1);

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/VoucherEngine.php';

$userId = gjc_user_id();
$role = gjc_current_role();
$allowedRoles = ['cashier', 'sub-admin', 'admin', 'super-admin', 'finance'];
if (!$userId || !in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin or cashier access required.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$body = str_contains($contentType, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : array_merge($_GET, $_POST);

$action = strtolower(trim((string) ($body['action'] ?? $_GET['action'] ?? '')));
$ve = new VoucherEngine($db);

try {
    switch ($action) {
        case 'create':
            $amount = (float) ($body['amount'] ?? 0);
            $name = trim((string) ($body['visitor_name'] ?? ''));
            $contact = trim((string) ($body['visitor_contact'] ?? ''));
            $expiry = (int) ($body['expiry_hours'] ?? VoucherEngine::DEFAULT_EXPIRY_HOURS);
            $refundable = !empty($body['is_refundable']);

            $result = $ve->createVoucher($amount, $name, $contact, $userId, $expiry, $refundable);
            echo json_encode($result);
            break;

        case 'expire':
            if (!in_array($role, ['admin', 'super-admin', 'finance'], true)) {
                throw new RuntimeException('Only admin-level users can force-expire vouchers.');
            }

            $voucherId = (int) ($body['voucher_id'] ?? 0);
            echo json_encode($ve->adminExpireVoucher($voucherId, $userId));
            break;

        case 'list':
            $status = (string) ($body['status'] ?? 'all');
            $limit = min((int) ($body['limit'] ?? 25), 100);
            $offset = (int) ($body['offset'] ?? 0);
            echo json_encode(['success' => true, 'data' => $ve->listVouchers($status, $limit, $offset)]);
            break;

        case 'stats':
            echo json_encode(array_merge(['success' => true], $ve->getSummaryStats()));
            break;

        case 'payments':
            $voucherId = (int) ($body['voucher_id'] ?? 0);
            echo json_encode(['success' => true, 'data' => $ve->getVoucherPayments($voucherId)]);
            break;

        case 'expiring_soon':
            $minutes = (int) ($body['minutes'] ?? 60);
            echo json_encode(['success' => true, 'data' => $ve->expiringSoon($minutes)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Unknown action: '{$action}'"]);
    }
} catch (RuntimeException | InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[voucher_api] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
}
