<?php
declare(strict_types=1);
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/CirculationEngine.php';
require_once __DIR__ . '/../../connection/audit_logger.php';

gjc_require_role(['finance']);
gjc_ensure_parent_wallet_schema($db);

$sessionUserId = gjc_user_id();
$sessionRole   = gjc_current_role();

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$body = str_contains($contentType, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : array_merge($_GET, $_POST);

$action = strtolower(trim((string) ($body['action'] ?? '')));

try {
    switch ($action) {

        case 'approve': {
            $requestId = (int) ($body['id'] ?? 0);
            if (!$requestId) {
                throw new \InvalidArgumentException('Request ID required.');
            }

            // Atomic claim FIRST, engine call second — the shape that avoids
            // admin/approve_topup.php's known double-credit race (that file
            // calls the engine before any pending-status guard at all).
            $claim = $db->prepare(
                "UPDATE parent_topup_requests
                    SET status = 'approved', processed_by = ?, processed_at = NOW()
                  WHERE id = ? AND status = 'pending'"
            );
            $claim->execute([$sessionUserId, $requestId]);

            if ($claim->rowCount() === 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'This request is no longer pending.']);
                exit;
            }

            $reqStmt = $db->prepare("SELECT * FROM parent_topup_requests WHERE id = ?");
            $reqStmt->execute([$requestId]);
            $request = $reqStmt->fetch(PDO::FETCH_ASSOC);

            try {
                $parentWallet = gjc_parent_wallet($db, (int) $request['parent_id']);
                $engine = new CirculationEngine($db);
                $result = $engine->cashInParent(
                    $parentWallet['id'],
                    (float) $request['amount'],
                    (string) $request['source'],
                    $sessionUserId,
                    (int) ($request['merchant_id'] ?? 0)
                );
            } catch (\Throwable $engineError) {
                // Engine failed — revert the claim so the row returns to the queue.
                $db->prepare(
                    "UPDATE parent_topup_requests
                        SET status = 'pending', processed_by = NULL, processed_at = NULL
                      WHERE id = ?"
                )->execute([$requestId]);
                throw $engineError;
            }

            $db->prepare(
                "UPDATE parent_topup_requests
                    SET reference_no = ?, fee_amount = ?, credited_amount = ?
                  WHERE id = ?"
            )->execute([$result['reference'], $result['fee_amount'], $result['credited_amount'], $requestId]);

            logAudit(
                $db,
                $sessionUserId,
                $sessionRole,
                'TRANSACTION',
                'parent_topup_requests',
                ['id' => $requestId, 'status' => 'pending'],
                [
                    'event' => 'parent_topup_approve',
                    'id' => $requestId,
                    'status' => 'approved',
                    'approved_by' => $sessionUserId,
                    'parent_wallet_id' => $parentWallet['id'],
                    'cash_amount' => (float) $request['amount'],
                    'fee_amount' => $result['fee_amount'],
                    'credited_amount' => $result['credited_amount'],
                    'reference_no' => $result['reference'],
                ]
            );

            echo json_encode([
                'success' => true,
                'message' => '₱' . number_format($result['credited_amount'], 2) . ' credited to the parent wallet.',
                'reference' => $result['reference'],
            ]);
            break;
        }

        case 'reject': {
            $requestId = (int) ($body['id'] ?? 0);
            if (!$requestId) {
                throw new \InvalidArgumentException('Request ID required.');
            }

            $stmt = $db->prepare(
                "UPDATE parent_topup_requests
                    SET status = 'rejected', processed_by = ?, processed_at = NOW()
                  WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$sessionUserId, $requestId]);

            if ($stmt->rowCount() === 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'This request is no longer pending.']);
                exit;
            }

            logAudit(
                $db,
                $sessionUserId,
                $sessionRole,
                'TRANSACTION',
                'parent_topup_requests',
                ['id' => $requestId, 'status' => 'pending'],
                ['event' => 'parent_topup_reject', 'id' => $requestId, 'status' => 'rejected', 'rejected_by' => $sessionUserId]
            );

            echo json_encode(['success' => true, 'message' => 'Top-up request rejected.']);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Unknown action: '{$action}'"]);
    }

} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[admin/api/parent_topups.php] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
}
