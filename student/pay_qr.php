<?php
// ============================================================
//  student/pay_qr.php
//  Charges the logged-in student's wallet for a single-use POS
//  payment order (merchant_qr_orders). Token-only: the client
//  sends the QR token or the typed short code — never an amount
//  or merchant id. Everything money-related is re-read and
//  re-checked here under row locks inside one DB transaction.
// ============================================================
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/audit_logger.php';

header('Content-Type: application/json');

/** RuntimeException with a machine-readable code the scanner UI can branch on. */
class PaymentError extends RuntimeException
{
    public string $errorCode;

    public function __construct(string $errorCode, string $message)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }
}

try {
    if (!gjc_user_id() || gjc_current_role() !== 'student') {
        http_response_code(401);
        echo json_encode(['success' => false, 'code' => 'unauthorized', 'message' => 'Your session expired. Please log in again before paying.']);
        exit;
    }

    if (!empty($_SESSION['force_change'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'code' => 'force_change', 'message' => 'Please change your password before making payments.']);
        exit;
    }

    if (!gjc_csrf_verify()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'code' => 'csrf', 'message' => 'Security check failed. Please refresh the page and try again.']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    gjc_ensure_merchant_qr_orders_schema($db);
    gjc_ensure_parent_schema($db);

    // Token (32-hex, from the QR) or short code (typed fallback). Amounts and
    // merchant ids from the client are ignored by design.
    $input = trim((string) ($payload['token'] ?? $payload['code'] ?? ''));
    $token = '';
    $shortCode = '';
    if (preg_match('/^[0-9a-f]{32,64}$/i', $input)) {
        $token = strtolower($input);
    } else {
        $shortCode = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $input));
    }

    if ($token === '' && ($shortCode === '' || strlen($shortCode) < 6 || strlen($shortCode) > 12)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'code' => 'invalid_code', 'message' => 'Invalid QR payment details. Ask the merchant to generate a new QR.']);
        exit;
    }

    $currentUser = gjc_current_user($db);
    $wallet = gjc_student_wallet($db, $currentUser['id']);
    if ($wallet['id'] <= 0 || $wallet['source'] !== 'student_wallets') {
        http_response_code(422);
        echo json_encode(['success' => false, 'code' => 'no_wallet', 'message' => 'Student wallet not found.']);
        exit;
    }

    $db->beginTransaction();
    try {
        // Lock the order row first, then the wallet row — same order on every
        // concurrent payment, so a double-scan serializes instead of deadlocking.
        $orderStmt = $db->prepare(
            "SELECT *
               FROM merchant_qr_orders
              WHERE token = ?
                 OR (short_code IS NOT NULL AND short_code = ?)
              LIMIT 1
              FOR UPDATE"
        );
        $orderStmt->execute([$token, $shortCode]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new PaymentError('invalid_code', 'Invalid or unknown payment code. Ask the merchant to generate a new QR.');
        }
        if ($order['status'] === 'paid') {
            throw new PaymentError('already_paid', 'This payment QR has already been paid.');
        }
        if ($order['status'] !== 'pending') {
            throw new PaymentError('expired', 'This payment QR is no longer valid. Ask the merchant to generate a new one.');
        }
        if (strtotime((string) $order['expires_at']) < time()) {
            $db->prepare("UPDATE merchant_qr_orders SET status = 'expired' WHERE id = ?")
                ->execute([(int) $order['id']]);
            throw new PaymentError('expired', 'This payment QR has expired. Ask the merchant to generate a new one.');
        }

        $items = json_decode((string) $order['items_json'], true);
        if (!is_array($items) || empty($items)) {
            throw new PaymentError('invalid_code', 'This payment QR has no items recorded.');
        }

        $orderAmount = round((float) $order['amount'], 2);
        $merchantWalletId = (int) $order['merchant_wallet_id'];
        $merchantUserId = (int) $order['merchant_user_id'];

        // ── Wallet row lock + parent controls + balance re-check ───────
        $wcStmt = $db->prepare(
            "SELECT balance, is_frozen, daily_spend_limit
               FROM student_wallets
              WHERE id = ?
              FOR UPDATE"
        );
        $wcStmt->execute([$wallet['id']]);
        $wc = $wcStmt->fetch(PDO::FETCH_ASSOC);
        if (!$wc) {
            throw new PaymentError('no_wallet', 'Student wallet not found.');
        }
        if (gjc_student_graduated($db, (int) $currentUser['id'])) {
            throw new PaymentError('graduated', 'Account locked: graduated.');
        }
        if ((int) $wc['is_frozen'] === 1) {
            throw new PaymentError('wallet_frozen', 'This wallet is frozen by a parent or guardian.');
        }
        if ((float) $wc['daily_spend_limit'] > 0) {
            $spentStmt = $db->prepare(
                "SELECT COALESCE(SUM(amount),0) FROM transactions
                  WHERE student_wallet_id = ? AND transaction_type IN ('payment','p2p_transfer')
                    AND DATE(created_at) = CURDATE() AND status = 'completed'"
            );
            $spentStmt->execute([$wallet['id']]);
            $todaySpent = (float) $spentStmt->fetchColumn();
            if ($todaySpent + $orderAmount > (float) $wc['daily_spend_limit']) {
                throw new PaymentError('limit_reached', 'Daily spending limit of ₱' . number_format((float) $wc['daily_spend_limit'], 2) . ' has been reached.');
            }
        }
        if ((float) $wc['balance'] < $orderAmount) {
            throw new PaymentError('insufficient_balance', 'Insufficient wallet balance. This purchase needs ₱' . number_format($orderAmount, 2) . '.');
        }
        // ────────────────────────────────────────────────────────────────

        $debitStmt = $db->prepare(
            "UPDATE student_wallets
                SET balance = balance - ?
              WHERE id = ?
                AND balance >= ?"
        );
        $debitStmt->execute([$orderAmount, $wallet['id'], $orderAmount]);
        if ($debitStmt->rowCount() === 0) {
            throw new PaymentError('insufficient_balance', 'Insufficient wallet balance.');
        }

        $db->prepare(
            "UPDATE merchant_wallets SET balance = balance + ? WHERE id = ?"
        )->execute([$orderAmount, $merchantWalletId]);

        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            if ($itemId <= 0 || $qty <= 0) {
                throw new PaymentError('invalid_code', 'Invalid item in payment QR.');
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
                throw new PaymentError('out_of_stock', 'One or more products are out of stock. Payment was not completed.');
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
                 amount, vault_before, vault_after, total_in_circulation, status, notes, school_year_id)
             VALUES (?, 'payment', ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?)"
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
            gjc_active_school_year_id($db),
        ]);

        $db->prepare(
            "UPDATE merchant_qr_orders
                SET status = 'paid',
                    paid_by = ?,
                    paid_ref = ?,
                    paid_at = NOW()
              WHERE id = ?"
        )->execute([(int) $currentUser['id'], $refNo, (int) $order['id']]);

        $newBalStmt = $db->prepare("SELECT balance FROM student_wallets WHERE id = ?");
        $newBalStmt->execute([$wallet['id']]);
        $newBalance = (float) $newBalStmt->fetchColumn();

        $db->commit();
        gjc_check_parent_balance_alert($db, (int) $wallet['id']);

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

        gjc_notify(
            $db,
            $merchantUserId,
            'sale',
            'Payment Received',
            sprintf('%s paid %s at your stall.', gjc_user_label($db, (int) $currentUser['id']), gjc_money_plain($orderAmount)),
            'cart-shopping',
            MERCHANT_URL . '/history.php'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Payment completed.',
            'reference' => $refNo,
            'amount' => $orderAmount,
            'merchant' => gjc_merchant_display_name($db, $merchantUserId) ?: 'Merchant',
            'balance' => $newBalance,
        ]);
        exit;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
} catch (PaymentError $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'code' => $e->errorCode, 'message' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'code' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'server_error', 'message' => 'A server error occurred while processing payment.']);
}
