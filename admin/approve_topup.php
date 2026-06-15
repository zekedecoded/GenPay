<?php


session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/CirculationEngine.php';

header('Content-Type: application/json');


$sessionUserId = gjc_user_id();
$sessionRole = gjc_current_role();
$allowedRoles = ['cashier', 'sub-admin', 'admin', 'super-admin', 'finance'];
if (!$sessionUserId || !in_array($sessionRole, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}


$topupId         = filter_input(INPUT_POST, 'topup_id',         FILTER_VALIDATE_INT);
$studentWalletId = filter_input(INPUT_POST, 'student_wallet_id', FILTER_VALIDATE_INT);
$amount          = filter_input(INPUT_POST, 'amount',           FILTER_VALIDATE_FLOAT);

if (!$topupId || !$studentWalletId || !$amount || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters.']);
    exit;
}


try {
    $engine = new CirculationEngine($db);
    $result = $engine->cashIn($studentWalletId, $amount, $sessionUserId);

    
    $db->prepare(
        "UPDATE topup_requests
            SET status       = 'approved',
                approved_by  = ?,
                approved_at  = NOW(),
                reference_no = ?
          WHERE id = ?"
    )->execute([$sessionUserId, $result['reference'], $topupId]);

    echo json_encode([
        'success'   => true,
        'message'   => "₱" . number_format($amount, 2) . " loaded successfully.",
        'reference' => $result['reference'],
        'vault_remaining' => $result['vault_after'],
    ]);

} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}
