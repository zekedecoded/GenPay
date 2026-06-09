<?php
session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';

header('Content-Type: application/json');
gjc_require_role(['merchant']);

$action         = trim((string) ($_POST['action'] ?? ''));
$merchantUserId = gjc_user_id();
$ownerMerchId   = gjc_merchant_owner_id($db, $merchantUserId);

try {
    // ── LOOKUP STUDENT ──────────────────────────────────────────────────────────
    if ($action === 'lookup_student') {
        $studentIdInput = trim((string) ($_POST['student_id'] ?? ''));
        if (!$studentIdInput) {
            echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
            exit;
        }

        // Support both student_info.studentID and users.userID search
        $stmt = $db->prepare(
            "SELECT u.userID, u.first_name, u.last_name, sw.id AS wallet_id, sw.balance
               FROM users u
               JOIN student_info si ON si.userID = u.userID
               JOIN student_wallets sw ON sw.user_id = u.userID
              WHERE si.studentID = ? AND u.roleID = 1
              LIMIT 1"
        );
        $stmt->execute([$studentIdInput]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$found) {
            echo json_encode(['success' => false, 'message' => 'Student not found. Check the Student ID.']);
            exit;
        }

        echo json_encode([
            'success'   => true,
            'name'      => trim($found['first_name'] . ' ' . $found['last_name']),
            'user_id'   => (int) $found['userID'],
            'wallet_id' => (int) $found['wallet_id'],
            'balance'   => (float) $found['balance'],
        ]);
        exit;
    }

    // ── PROCESS POS TRANSACTION ─────────────────────────────────────────────────
    if ($action === 'process_pos') {
        $walletId  = (int)   ($_POST['wallet_id'] ?? 0);
        $totalAmt  = round((float) ($_POST['total'] ?? 0), 2);
        $itemsJson = (string) ($_POST['items'] ?? '[]');
        $items     = json_decode($itemsJson, true);

        if (!$walletId || $totalAmt <= 0 || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Invalid transaction data.']);
            exit;
        }

        // Validate each item belongs to this merchant's inventory
        foreach ($items as &$item) {
            $item['id']  = (int)   ($item['id']  ?? 0);
            $item['qty'] = (int)   ($item['qty'] ?? 0);
            $item['price'] = (float) ($item['price'] ?? 0);

            if (!$item['id'] || $item['qty'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid item in order.']);
                exit;
            }

            // Verify item is still available and has enough stock
            $invCheck = $db->prepare(
                "SELECT id, product_name, stock_qty, price FROM merchant_inventory
                  WHERE id = ? AND merchant_user_id = ? AND is_available = 1 AND is_restricted = 0"
            );
            $invCheck->execute([$item['id'], $ownerMerchId]);
            $invItem = $invCheck->fetch(PDO::FETCH_ASSOC);

            if (!$invItem) {
                echo json_encode(['success' => false, 'message' => "Item #{$item['id']} is unavailable or not in your inventory."]);
                exit;
            }
            if ($invItem['stock_qty'] < $item['qty']) {
                echo json_encode(['success' => false, 'message' => "Insufficient stock for '{$invItem['product_name']}'. Available: {$invItem['stock_qty']}."]);
                exit;
            }
            $item['name'] = $invItem['product_name'];
        }
        unset($item);

        // Get merchant wallet
        $merchantWallet = gjc_merchant_wallet($db, $ownerMerchId);
        if ($merchantWallet['id'] === 0) {
            echo json_encode(['success' => false, 'message' => 'Merchant wallet not found.']);
            exit;
        }

        $refNo = gjc_reference('POS');

        // ── BEGIN TRANSACTION ───────────────────────────────────────────────────
        $db->beginTransaction();
        try {
            // 1. Debit student wallet
            $debitStmt = $db->prepare(
                "UPDATE student_wallets SET balance = balance - ? WHERE id = ? AND balance >= ?"
            );
            $debitStmt->execute([$totalAmt, $walletId, $totalAmt]);
            if ($debitStmt->rowCount() === 0) {
                throw new \RuntimeException('Insufficient balance or concurrent modification.');
            }

            // 2. Credit merchant wallet
            $db->prepare(
                "UPDATE merchant_wallets SET balance = balance + ? WHERE id = ?"
            )->execute([$totalAmt, $merchantWallet['id']]);

            // 3. Deduct stock for each item
            foreach ($items as $item) {
                $db->prepare(
                    "UPDATE merchant_inventory SET stock_qty = stock_qty - ?
                      WHERE id = ? AND merchant_user_id = ? AND stock_qty >= ?"
                )->execute([$item['qty'], $item['id'], $ownerMerchId, $item['qty']]);
            }

            // 4. Snapshot vault
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

            // 5. Log to transactions ledger
            $itemsSummary = implode(', ', array_map(function ($i) {
                return "{$i['qty']}x {$i['name']}";
            }, $items));
            $db->prepare(
                "INSERT INTO transactions
                    (reference_no, transaction_type, initiated_by, student_wallet_id, merchant_wallet_id,
                     amount, vault_before, vault_after, total_in_circulation, status, notes)
                 VALUES (?, 'payment', ?, ?, ?, ?, ?, ?, ?, 'completed', ?)"
            )->execute([
                $refNo,
                $merchantUserId,
                $walletId,
                $merchantWallet['id'],
                $totalAmt,
                $vaultBefore,
                $vaultBefore, // vault unchanged in a payment
                $totalCirc,
                'POS Sale: ' . $itemsSummary,
            ]);

            $db->commit();
            // ── COMMIT ─────────────────────────────────────────────────────────

            echo json_encode([
                'success'   => true,
                'reference' => $refNo,
                'message'   => 'Payment processed successfully.',
                'total'     => $totalAmt,
            ]);
        } catch (\Throwable $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
