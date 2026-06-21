<?php
session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/audit_logger.php';
require_once __DIR__ . '/../../connection/MerchantTenantDirectory.php';

header('Content-Type: application/json');
gjc_require_role(['finance']);

$action  = trim((string) ($_REQUEST['action'] ?? ''));
$adminId = gjc_user_id();
$adminRole = gjc_current_role();
$directory = new MerchantTenantDirectory($db);

// ── Ensure tables exist before any operation ──────────────────────────────
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
            payment_method  VARCHAR(20)  NOT NULL DEFAULT 'cash',
            received_by     INT UNSIGNED NULL,
            reference_no    VARCHAR(60)  NOT NULL,
            notes           TEXT         NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mrp_lease (lease_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function gjc_lease_json_fail(string $message): void
{
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    switch ($action) {

        /* ── LIST MERCHANTS (picker for new lease) ───────────────────────── */
        case 'list_merchants': {
            echo json_encode(['success' => true, 'merchants' => $directory->merchantsForPicker()]);
            break;
        }

        /* ── LEASE LEDGER (detail + paginated payment history) ──────────── */
        case 'get_ledger': {
            $leaseId = (int) ($_GET['lease_id'] ?? 0);
            if (!$leaseId) {
                gjc_lease_json_fail('Invalid lease ID.');
            }

            $lease = $directory->leaseById($leaseId);
            if (!$lease) {
                gjc_lease_json_fail('Lease record not found.');
            }

            $merchant = $db->prepare("SELECT u.first_name, u.last_name, u.email FROM users u WHERE u.userID = ? LIMIT 1");
            $merchant->execute([$lease['merchant_user_id']]);
            $merchantRow = $merchant->fetch(PDO::FETCH_ASSOC) ?: [];

            echo json_encode([
                'success' => true,
                'lease' => $lease,
                'merchant' => [
                    'name' => trim((string) (($merchantRow['first_name'] ?? '') . ' ' . ($merchantRow['last_name'] ?? ''))),
                    'email' => (string) ($merchantRow['email'] ?? ''),
                ],
                'payments' => $directory->pagedRentPayments(
                    $leaseId,
                    trim((string) ($_GET['from'] ?? '')),
                    trim((string) ($_GET['to'] ?? '')),
                    (int) ($_GET['page'] ?? 1),
                    (int) ($_GET['per_page'] ?? 10)
                ),
            ]);
            break;
        }

        /* ── CREATE LEASE ─────────────────────────────────────────────────── */
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

            $allowedStatuses = ['pending', 'active', 'expired', 'terminated'];
            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'pending';
            }

            if (!$merchantUserId || !$stallNumber || !$stallName || $monthlyRent <= 0 || !$leaseStart || !$leaseEnd) {
                gjc_lease_json_fail('All required fields must be filled correctly.');
            }

            if (!strtotime($leaseStart) || !strtotime($leaseEnd)) {
                gjc_lease_json_fail('Invalid lease start or end date.');
            }

            if ($leaseEnd <= $leaseStart) {
                gjc_lease_json_fail('Lease end date must be after lease start date.');
            }

            $check = $db->prepare("SELECT userID FROM users WHERE userID = ? LIMIT 1");
            $check->execute([$merchantUserId]);
            if (!$check->fetch()) {
                gjc_lease_json_fail('Selected merchant was not found in the system.');
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
                $leaseStart,
                $status,
                $notes ?: null,
                $adminId,
            ]);

            $newId = (int) $db->lastInsertId();
            logAudit($db, $adminId, $adminRole, 'STALL_UPDATE', 'merchant_leases', null, [
                'lease_id' => $newId, 'merchant_user_id' => $merchantUserId, 'stall_name' => $stallName,
                'monthly_rent' => $monthlyRent, 'status' => $status,
            ], $stallNumber);

            echo json_encode([
                'success' => true,
                'message' => 'Lease contract created successfully.',
                'id'      => $newId,
            ]);
            break;
        }

        /* ── UPDATE LEASE ─────────────────────────────────────────────────── */
        case 'update_lease': {
            $leaseId        = (int)    ($_POST['lease_id']        ?? 0);
            $monthlyRent    = (float)  ($_POST['monthly_rent']    ?? 0);
            $depositAmount  = (float)  ($_POST['deposit_amount']  ?? 0);
            $leaseStart     = trim((string) ($_POST['lease_start']     ?? ''));
            $leaseEnd       = trim((string) ($_POST['lease_end']       ?? ''));
            $nextDueDate    = trim((string) ($_POST['next_due_date']   ?? ''));
            $status         = trim((string) ($_POST['status']          ?? 'active'));
            $notes          = trim((string) ($_POST['contract_notes']  ?? ''));
            $stallNumber    = trim((string) ($_POST['stall_number']    ?? ''));
            $stallName      = trim((string) ($_POST['stall_name']      ?? ''));

            $allowedStatuses = ['pending', 'active', 'expired', 'terminated'];
            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'active';
            }

            if (!$leaseId) {
                gjc_lease_json_fail('Invalid lease ID.');
            }

            if ($monthlyRent <= 0 || !$leaseStart || !$leaseEnd) {
                gjc_lease_json_fail('All required fields must be filled.');
            }

            if ($leaseEnd <= $leaseStart) {
                gjc_lease_json_fail('Lease end date must be after lease start date.');
            }

            $chk = $db->prepare("SELECT * FROM merchant_leases WHERE id = ? LIMIT 1");
            $chk->execute([$leaseId]);
            $old = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                gjc_lease_json_fail('Lease record not found.');
            }

            if (!$nextDueDate || !strtotime($nextDueDate)) {
                $nextDueDate = $old['next_due_date'];
            }
            if (!$stallNumber) {
                $stallNumber = $old['stall_number'];
            }
            if (!$stallName) {
                $stallName = $old['stall_name'];
            }

            $stmt = $db->prepare(
                "UPDATE merchant_leases
                    SET stall_number     = ?,
                        stall_name       = ?,
                        monthly_rent     = ?,
                        deposit_amount   = ?,
                        lease_start      = ?,
                        lease_end        = ?,
                        next_due_date    = ?,
                        status           = ?,
                        contract_notes   = ?
                  WHERE id = ?"
            );
            $stmt->execute([
                $stallNumber,
                $stallName,
                $monthlyRent,
                $depositAmount,
                $leaseStart,
                $leaseEnd,
                $nextDueDate,
                $status,
                $notes ?: null,
                $leaseId,
            ]);

            logAudit($db, $adminId, $adminRole, 'STALL_UPDATE', 'merchant_leases', $old, [
                'lease_id' => $leaseId, 'monthly_rent' => $monthlyRent, 'status' => $status,
                'lease_start' => $leaseStart, 'lease_end' => $leaseEnd,
            ], $stallNumber);

            echo json_encode(['success' => true, 'message' => 'Lease updated successfully.']);
            break;
        }

        /* ── RECORD PAYMENT ───────────────────────────────────────────────── */
        case 'record_payment': {
            $leaseId    = (int)    ($_POST['lease_id']      ?? 0);
            $amountPaid = (float)  ($_POST['amount_paid']   ?? 0);
            $period     = trim((string) ($_POST['period_covered'] ?? ''));
            $payDate    = trim((string) ($_POST['payment_date']   ?? ''));
            $method     = trim((string) ($_POST['payment_method'] ?? 'cash'));
            $notes      = trim((string) ($_POST['notes']          ?? ''));
            $allowedMethods = ['cash', 'bank_transfer', 'check', 'other'];

            if (!$leaseId || $amountPaid <= 0 || !$period || !$payDate) {
                gjc_lease_json_fail('Missing required payment fields.');
            }

            if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
                gjc_lease_json_fail('Period covered must use YYYY-MM format.');
            }

            if (!in_array($method, $allowedMethods, true)) {
                $method = 'other';
            }

            $chk = $db->prepare("SELECT id, status FROM merchant_leases WHERE id = ? LIMIT 1");
            $chk->execute([$leaseId]);
            $lease = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$lease) {
                gjc_lease_json_fail('Lease not found.');
            }

            $db->beginTransaction();
            try {
                $refNo = $directory->recordRentPayment($leaseId, $amountPaid, $period, $payDate, $method, $notes, $adminId);

                if ($lease['status'] === 'active') {
                    $db->prepare(
                        "UPDATE merchant_leases
                            SET next_due_date = DATE_ADD(next_due_date, INTERVAL 1 MONTH)
                          WHERE id = ?"
                    )->execute([$leaseId]);
                }

                $db->commit();

                logAudit($db, $adminId, $adminRole, 'TRANSACTION', 'merchant_rent_payments', null, [
                    'lease_id' => $leaseId, 'amount_paid' => $amountPaid, 'period_covered' => $period,
                    'payment_method' => $method, 'reference_no' => $refNo,
                ], $leaseId);

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
