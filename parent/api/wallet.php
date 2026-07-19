<?php
declare(strict_types=1);
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/CirculationEngine.php';
require_once __DIR__ . '/../../connection/audit_logger.php';

gjc_require_role(['parent']);
gjc_ensure_parent_schema($db);
gjc_ensure_parent_wallet_schema($db);

$parentUserId = gjc_user_id();
$parentId = gjc_parent_id_for_user($db, $parentUserId);
if (!$parentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Parent record not found.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$body = str_contains($contentType, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : array_merge($_GET, $_POST);

$action = strtolower(trim((string) ($body['action'] ?? '')));

// State-changing actions require a valid CSRF token — this dispatcher moves
// (or queues moving) real money, unlike parent/api/link.php or controls.php.
$stateChanging = in_array($action, ['submit_topup', 'cancel_topup'], true);
if ($stateChanging && !gjc_csrf_verify($body['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security check failed. Please refresh the page and try again.']);
    exit;
}

try {
    switch ($action) {

        case 'submit_topup': {
            $amount = round((float) ($body['amount'] ?? 0), 2);
            $source = strtolower(trim((string) ($body['source'] ?? 'finance')));

            if ($amount < 1.00) {
                throw new \InvalidArgumentException('Minimum top-up amount is ₱1.00.');
            }
            if (!in_array($source, ['finance', 'merchant'], true)) {
                throw new \InvalidArgumentException('Invalid top-up source.');
            }

            $reference = gjc_reference('PTU');
            $db->prepare(
                "INSERT INTO parent_topup_requests
                    (parent_id, amount, source, status, reference_no)
                 VALUES (?, ?, ?, 'pending', ?)"
            )->execute([$parentId, $amount, $source, $reference]);

            echo json_encode([
                'success' => true,
                'message' => "Top-up request {$reference} was submitted for finance approval.",
                'reference' => $reference,
            ]);
            break;
        }

        case 'cancel_topup': {
            $requestId = (int) ($body['id'] ?? 0);
            if (!$requestId) {
                throw new \InvalidArgumentException('Request ID required.');
            }

            $stmt = $db->prepare(
                "UPDATE parent_topup_requests
                    SET status = 'cancelled'
                  WHERE id = ? AND parent_id = ? AND status = 'pending'"
            );
            $stmt->execute([$requestId, $parentId]);

            if ($stmt->rowCount() === 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'This request is no longer pending.']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Top-up request cancelled.']);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Unknown action: '{$action}'"]);
    }

} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[parent/api/wallet.php] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
}
