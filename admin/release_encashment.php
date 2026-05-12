<?php
/**
 * release_encashment.php — Cashier/Admin Action
 *
 * Processes a merchant settlement (encashment):
 *   merchant wallet  →  vault  (recycling loop)
 *
 * Usage: POST release_encashment.php
 *   { encashment_id, merchant_wallet_id, amount }
 */

session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/CirculationEngine.php';

header('Content-Type: application/json');

// ── Auth guard ──────────────────────────────────────────────
$sessionUserId = gjc_user_id();
$sessionRole = gjc_current_role();
$allowedRoles = ['cashier', 'sub-admin', 'admin', 'super-admin'];
if (!$sessionUserId || !in_array($sessionRole, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// ── Input validation ────────────────────────────────────────
$encashmentId    = filter_input(INPUT_POST, 'encashment_id',     FILTER_VALIDATE_INT);
$merchantWalletId = filter_input(INPUT_POST, 'merchant_wallet_id', FILTER_VALIDATE_INT);
$amount           = filter_input(INPUT_POST, 'amount',            FILTER_VALIDATE_FLOAT);

if (!$encashmentId || !$merchantWalletId || !$amount || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters.']);
    exit;
}

// ── Execute ─────────────────────────────────────────────────
try {
    $engine = new CirculationEngine($db);
    $result = $engine->merchantSettle($merchantWalletId, $amount, $sessionUserId);

    // Update encashment request status
    $db->prepare(
        "UPDATE encashment_requests
            SET status       = 'released',
                released_by  = ?,
                released_at  = NOW(),
                reference_no = ?
          WHERE id = ?"
    )->execute([$sessionUserId, $result['reference'], $encashmentId]);

    echo json_encode([
        'success'   => true,
        'message'   => "₱" . number_format($amount, 2) . " settled. Points returned to vault.",
        'reference' => $result['reference'],
        'vault_after' => $result['vault_after'],
    ]);

} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}
