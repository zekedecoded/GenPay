<?php
session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';

header('Content-Type: application/json');
gjc_require_role(['admin']);

$action  = trim((string) ($_POST['action'] ?? ''));
$adminId = gjc_user_id();

// ── Ensure tables exist before any operation ───────────────────────────────
if (!gjc_table_exists($db, 'merchant_leases')) {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS merchant_leases (
            id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            merchant_user_id  INT UNSIGNED NOT NULL,
            stall_number      VARCHAR(30)  NOT NULL,
            stall_name        VARCHAR(120) NOT NULL,
            monthly_rent      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            deposit_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            lease_start       DATE         NOT NULL,
            lease_end         DATE         NOT NULL,
            next_due_date     DATE         NOT NULL,
            status            VARCHAR(20)  NOT NULL DEFAULT 'pending',
            contract_notes    TEXT         NULL,
            created_by        INT UNSIGNED NULL,
            created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ml_merchant (merchant_user_id),
            INDEX idx_ml_status   (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

if (!gjc_table_exists($db, 'merchant_rent_payments')) {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS merchant_rent_payments (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            lease_id        INT UNSIGNED NOT NULL,
            amount_paid     DECIMAL(12,2) NOT NULL,
            period_covered  VARCHAR(7)   NOT NULL COMMENT 'YYYY-MM',
            payment_date    DATE         NOT NULL,
            received_by     INT UNSIGNED NULL,
            reference_no    VARCHAR(60)  NOT NULL,
            notes           TEXT         NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mrp_lease (lease_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

try {
    switch ($action) {

        /* ── CREATE LEASE ───────────────────────────────────────────────────── */
        case 'create_lease': {
            $merchantUserId = (int)    ($_POST['merchant_user_id'] ?? 0);
            $stallNumber    = trim((string) ($_POST['stall_number']    ?? ''));
            $stallName      = trim((string) ($_POST['stall_name']      ?? ''));
            $monthlyRent    = (float)  ($_POST['monthly_rent']    ?? 0);
            $depositAmount  = (float)  ($_POST['deposit_amount']  ?? 0);
            $leaseStart     = trim((string) ($_POST['lease_start']     ?? ''));
            $leaseEnd       = trim((string) ($_POST['lease_end']       ?? ''));
            $status         = trim((string) ($_POST['status']          ?? 'pending'));
            $notes          = trim((string) ($_POST['contract_notes']  ?? ''));

            // Validate allowed statuses
            $allowedStatuses = ['pending', 'active', 'expired', 'terminated'];
            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'pending';
            }

            if (!$merchantUserId || !$stallNumber || !$stallName || $monthlyRent <= 0 || !$leaseStart || !$leaseEnd) {
                echo json_encode(['success' => false, 'message' => 'All required fields must be filled correctly.']);
                exit;
            }

            // Validate dates
            if (!strtotime($leaseStart) || !strtotime($leaseEnd)) {
                echo json_encode(['success' => false, 'message' => 'Invalid lease start or end date.']);
                exit;
            }

            if ($leaseEnd <= $leaseStart) {
                echo json_encode(['success' => false, 'message' => 'Lease end date must be after lease start date.']);
                exit;
            }

            // Validate merchant user exists
            $check = $db->prepare("SELECT userID FROM users WHERE userID = ? LIMIT 1");
            $check->execute([$merchantUserId]);
            if (!$check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Merchant user ID not found in the system.']);
                exit;
            }

            $stmt = $db->prepare(
                "INSERT INTO merchant_leases
                    (merchant_user_id, stall_number, stall_name, monthly_rent, deposit_amount,
                     lease_start, lease_end, next_due_date, status, contract_notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $merchantUserId,
                $stallNumber,
                $stallName,
                $monthlyRent,
                $depositAmount,
                $leaseStart,
                $leaseEnd,
                $leaseStart,    // next_due_date initialised to lease start
                $status,
                $notes ?: null,
                $adminId,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Lease contract created successfully.',
                'id'      => (int) $db->lastInsertId(),
            ]);
            break;
        }

        /* ── UPDATE LEASE ───────────────────────────────────────────────────── */
        case 'update_lease': {
            $leaseId        = (int)    ($_POST['lease_id']        ?? 0);
            $merchantUserId = (int)    ($_POST['merchant_user_id'] ?? 0);
            $stallNumber    = trim((string) ($_POST['stall_number']    ?? ''));
            $stallName      = trim((string) ($_POST['stall_name']      ?? ''));
            $monthlyRent    = (float)  ($_POST['monthly_rent']    ?? 0);
            $depositAmount  = (float)  ($_POST['deposit_amount']  ?? 0);
            $leaseStart     = trim((string) ($_POST['lease_start']     ?? ''));
            $leaseEnd       = trim((string) ($_POST['lease_end']       ?? ''));
            $status         = trim((string) ($_POST['status']          ?? 'active'));
            $notes          = trim((string) ($_POST['contract_notes']  ?? ''));

            $allowedStatuses = ['pending', 'active', 'expired', 'terminated'];
            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'active';
            }

            if (!$leaseId) {
                echo json_encode(['success' => false, 'message' => 'Invalid lease ID.']);
                exit;
            }

            if (!$stallNumber || !$stallName || $monthlyRent <= 0 || !$leaseStart || !$leaseEnd) {
                echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
                exit;
            }

            // Verify lease exists
            $chk = $db->prepare("SELECT id FROM merchant_leases WHERE id = ? LIMIT 1");
            $chk->execute([$leaseId]);
            if (!$chk->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Lease record not found.']);
                exit;
            }

            $stmt = $db->prepare(
                "UPDATE merchant_leases
                    SET merchant_user_id = ?,
                        stall_number     = ?,
                        stall_name       = ?,
                        monthly_rent     = ?,
                        deposit_amount   = ?,
                        lease_start      = ?,
                        lease_end        = ?,
                        status           = ?,
                        contract_notes   = ?
                  WHERE id = ?"
            );
            $stmt->execute([
                $merchantUserId,
                $stallNumber,
                $stallName,
                $monthlyRent,
                $depositAmount,
                $leaseStart,
                $leaseEnd,
                $status,
                $notes ?: null,
                $leaseId,
            ]);

            echo json_encode(['success' => true, 'message' => 'Lease updated successfully.']);
            break;
        }

        /* ── RECORD PAYMENT ─────────────────────────────────────────────────── */
        case 'record_payment': {
            $leaseId    = (int)    ($_POST['lease_id']      ?? 0);
            $amountPaid = (float)  ($_POST['amount_paid']   ?? 0);
            $period     = trim((string) ($_POST['period_covered'] ?? ''));
            $payDate    = trim((string) ($_POST['payment_date']   ?? ''));
            $notes      = trim((string) ($_POST['notes']          ?? ''));

            if (!$leaseId || $amountPaid <= 0 || !$period || !$payDate) {
                echo json_encode(['success' => false, 'message' => 'Missing required payment fields.']);
                exit;
            }

            // Validate lease exists
            $chk = $db->prepare("SELECT id, status FROM merchant_leases WHERE id = ? LIMIT 1");
            $chk->execute([$leaseId]);
            $lease = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$lease) {
                echo json_encode(['success' => false, 'message' => 'Lease not found.']);
                exit;
            }

            $refNo = 'RENT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $db->beginTransaction();
            try {
                // Insert payment record
                $ins = $db->prepare(
                    "INSERT INTO merchant_rent_payments
                        (lease_id, amount_paid, period_covered, payment_date, received_by, reference_no, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $ins->execute([
                    $leaseId,
                    $amountPaid,
                    $period,
                    $payDate,
                    $adminId,
                    $refNo,
                    $notes ?: null,
                ]);

                // Advance next_due_date by 1 month only for active leases
                if ($lease['status'] === 'active') {
                    $db->prepare(
                        "UPDATE merchant_leases
                            SET next_due_date = DATE_ADD(next_due_date, INTERVAL 1 MONTH)
                          WHERE id = ?"
                    )->execute([$leaseId]);
                }

                $db->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Payment recorded. Reference: ' . $refNo,
                    'ref'     => $refNo,
                ]);
            } catch (\Throwable $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
            }
            break;
        }

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
