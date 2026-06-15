<?php


session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/CirculationEngine.php';

header('Content-Type: application/json');

$allowedRoles = ['cashier', 'sub-admin', 'admin', 'super-admin', 'finance'];
if (!isset($_SESSION['user_id'], $_SESSION['role'])
    || !in_array($_SESSION['role'], $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$visitorName    = trim(filter_input(INPUT_POST, 'visitor_name',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$visitorContact = trim(filter_input(INPUT_POST, 'visitor_contact', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$amount         = filter_input(INPUT_POST, 'amount',       FILTER_VALIDATE_FLOAT);
$expiryHours    = filter_input(INPUT_POST, 'expiry_hours', FILTER_VALIDATE_INT);

if (!$visitorName || !$amount || $amount <= 0 || !$expiryHours || $expiryHours < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Visitor name, valid amount, and expiry hours are required.']);
    exit;
}

try {
    $engine = new CirculationEngine($db);
    $result = $engine->createVoucher(
        $amount,
        $visitorName,
        $visitorContact,
        $expiryHours,
        $_SESSION['user_id']
    );

    echo json_encode([
        'success'              => true,
        'message'              => "Voucher issued successfully.",
        'voucher_code'         => $result['voucher_code'],
        'voucher_id'           => $result['voucher_id'],
        'amount'               => $amount,
        'expires_at'           => $result['expires_at'],
        'reference'            => $result['reference'],
        'non_refundable_notice'=> $result['non_refundable_notice'],
        
        'qr_url' => BASE_URL . '/admin/print_voucher.php?code=' .
                    urlencode($result['voucher_code']),
    ]);

} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}

