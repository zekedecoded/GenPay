<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/CirculationEngine.php';
require_once __DIR__ . '/../connection/audit_logger.php';

header('Content-Type: application/json');

try {
    if (!gjc_user_id() || gjc_current_role() !== 'student') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Your session expired. Please log in again before paying.']);
        exit;
    }

    if (!empty($_SESSION['force_change'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Please change your password before making payments.']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    gjc_ensure_merchant_qr_orders_schema($db);

    $token = trim((string) ($payload['token'] ?? ''));
    $merchantWalletId = (int) ($payload['merchant_wallet_id'] ?? 0);
    $amount = (float) ($payload['amount'] ?? $payload['price'] ?? 0);

    if ($token === '' && ($merchantWalletId <= 0 || $amount <= 0)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid QR payment details.']);
        exit;
    }

    $currentUser = gjc_current_user($db);
    $wallet = gjc_student_wallet($db, $currentUser['id']);
    if ($wallet['id'] <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Student wallet not found.']);
        exit;
    }

    if ($token !== '') {
        $db->beginTransaction();
        try {
            $orderStmt = $db->prepare(
                "SELECT *
                   FROM merchant_qr_orders
                  WHERE token = ?
                  LIMIT 1
                  FOR UPDATE"
            );
            $orderStmt->execute([$token]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new RuntimeException('Invalid or unknown product QR. Ask the merchant to generate a new one.');
            }
            if ($order['status'] !== 'pending') {
                throw new RuntimeException('This product QR has already been used.');
            }
            if (strtotime((string) $order['expires_at']) < time()) {
                $db->prepare("UPDATE merchant_qr_orders SET status = 'expired' WHERE id = ?")
                    ->execute([(int) $order['id']]);
                throw new RuntimeException('This product QR has expired. Ask the merchant to generate a new one.');
            }

            $items = json_decode((string) $order['items_json'], true);
            if (!is_array($items) || empty($items)) {
                throw new RuntimeException('This product QR has no items recorded.');
            }

            $orderAmount = round((float) $order['amount'], 2);
            $merchantWalletId = (int) $order['merchant_wallet_id'];
            $merchantUserId = (int) $order['merchant_user_id'];

            $debitStmt = $db->prepare(
                "UPDATE student_wallets
                    SET balance = balance - ?
                  WHERE id = ?
                    AND balance >= ?"
            );
            $debitStmt->execute([$orderAmount, $wallet['id'], $orderAmount]);
            if ($debitStmt->rowCount() === 0) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $db->prepare(
                "UPDATE merchant_wallets SET balance = balance + ? WHERE id = ?"
            )->execute([$orderAmount, $merchantWalletId]);

            foreach ($items as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                $qty = (int) ($item['qty'] ?? 0);
                if ($itemId <= 0 || $qty <= 0) {
                    throw new RuntimeException('Invalid item in product QR.');
                }

                $stockStmt = $db->prepare(
                    "UPDATE merchant_inventory
                        SET stock_qty = stock_qty - ?
                      WHERE id = ?
                        AND merchant_user_id = ?
                        AND stock_qty >= ?
                        AND is_available = 1
                        AND is_restricted = 0"
                );
                $stockStmt->execute([$qty, $itemId, $merchantUserId, $qty]);
                if ($stockStmt->rowCount() === 0) {
                    throw new RuntimeException('One or more products are out of stock. Payment was not completed.');
                }
            }

            $vaultBefore = (float) $db->query(
                "SELECT cashier_vault_points FROM system_settings WHERE id = 1"
            )->fetchColumn();

            $totalCirc = (float) $db->query(
                "SELECT (cashier_vault_points
                        + (SELECT COALESCE(SUM(balance),0) FROM student_wallets)
                        + (SELECT COALESCE(SUM(balance),0) FROM merchant_wallets)
                        + (SELECT COALESCE(SUM(remaining_balance),0) FROM vouchers WHERE status='active'))
                   FROM system_settings WHERE id = 1"
            )->fetchColumn();

            $refNo = gjc_reference('POS');
            $db->prepare(
                "INSERT INTO transactions
                    (reference_no, transaction_type, initiated_by, student_wallet_id, merchant_wallet_id,
                     amount, vault_before, vault_after, total_in_circulation, status, notes)
                 VALUES (?, 'payment', ?, ?, ?, ?, ?, ?, ?, 'completed', ?)"
            )->execute([
                $refNo,
                (int) $currentUser['id'],
                (int) $wallet['id'],
                $merchantWalletId,
                $orderAmount,
                $vaultBefore,
                $vaultBefore,
                $totalCirc,
                'POS QR Sale: ' . (string) $order['description'],
            ]);

            $db->prepare(
                "UPDATE merchant_qr_orders
                    SET status = 'paid',
                        paid_by = ?,
                        paid_ref = ?,
                        paid_at = NOW()
                  WHERE id = ?"
            )->execute([(int) $currentUser['id'], $refNo, (int) $order['id']]);

            $db->commit();

            logAudit(
                $db,
                (int) $currentUser['id'],
                gjc_current_role(),
                'TRANSACTION',
                'e_wallet_transactions',
                null,
                [
                    'reference_no' => $refNo,
                    'transaction_type' => 'payment',
                    'amount' => $orderAmount,
                    'student_wallet_id' => (int) $wallet['id'],
                    'merchant_wallet_id' => $merchantWalletId,
                    'items' => $items,
                    'status' => 'completed',
                ]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Payment completed.',
                'reference' => $refNo,
            ]);
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    $engine = new CirculationEngine($db);
    $result = $engine->studentPay($wallet['id'], $merchantWalletId, $amount, $currentUser['id']);

    echo json_encode([
        'success' => true,
        'message' => 'Payment completed.',
        'reference' => $result['reference'],
    ]);
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred while processing payment.']);
}
