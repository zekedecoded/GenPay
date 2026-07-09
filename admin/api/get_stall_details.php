<?php
session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/MerchantTenantDirectory.php';

header('Content-Type: application/json');

if (gjc_sub_role() !== 'super_admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Super Admin privileges are required.',
    ]);
    exit;
}

$directory = new MerchantTenantDirectory($db);
$action = trim((string) ($_REQUEST['action'] ?? 'details'));
$adminId = gjc_user_id();

function gjc_json_fail(string $message): void
{
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function gjc_valid_ymd(string $date): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function gjc_valid_period(string $period): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}$/', $period);
}

try {
    switch ($action) {
        case 'details': {
            $merchantId = (int) ($_GET['merchant_id'] ?? $_POST['merchant_id'] ?? 0);
            if ($merchantId <= 0) {
                gjc_json_fail('Invalid stall ID.');
            }

            $summary = $directory->stallSummary($merchantId);
            if (!$summary) {
                gjc_json_fail('Stall not found.');
            }

            // Opening the detail view marks the stall's merchant activity as
            // checked — shared stamp, so the dashboard badge clears for every
            // finance admin.
            gjc_ensure_merchant_card_views_schema($db);
            $db->prepare(
                "INSERT INTO merchant_card_views (merchant_id, last_viewed_at, viewed_by)
                 VALUES (?, NOW(), ?)
                 ON DUPLICATE KEY UPDATE last_viewed_at = NOW(), viewed_by = VALUES(viewed_by)"
            )->execute([$merchantId, $adminId]);

            $lease = $directory->activeLease((int) $summary['merchant_user_id']);
            $leaseId = (int) ($lease['id'] ?? 0);

            echo json_encode([
                'success' => true,
                'summary' => $summary,
                'lease' => $lease,
                'payments' => $leaseId > 0
                    ? $directory->pagedRentPayments(
                        $leaseId,
                        trim((string) ($_GET['payments_from'] ?? '')),
                        trim((string) ($_GET['payments_to'] ?? '')),
                        (int) ($_GET['payments_page'] ?? 1),
                        (int) ($_GET['per_page'] ?? 10)
                    )
                    : ['rows' => [], 'page' => 1, 'per_page' => 10, 'total' => 0, 'total_pages' => 1],
                'inventory' => $directory->pagedInventory(
                    (int) $summary['merchant_user_id'],
                    trim((string) ($_GET['inventory_search'] ?? '')),
                    trim((string) ($_GET['inventory_category'] ?? '')),
                    trim((string) ($_GET['inventory_restriction'] ?? '')),
                    (int) ($_GET['inventory_page'] ?? 1),
                    (int) ($_GET['per_page'] ?? 10)
                ),
                'activity' => $directory->pagedActivity(
                    (int) $summary['merchant_user_id'],
                    (int) ($_GET['activity_page'] ?? 1),
                    (int) ($_GET['per_page'] ?? 10)
                ),
                'privacy_notice' => 'Admin stall details intentionally exclude merchant sales revenue and transaction history.',
            ]);
            break;
        }

        case 'payments': {
            $leaseId = (int) ($_GET['lease_id'] ?? 0);
            if ($leaseId <= 0) {
                gjc_json_fail('Invalid lease ID.');
            }

            echo json_encode([
                'success' => true,
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

        case 'inventory': {
            $merchantUserId = (int) ($_GET['merchant_user_id'] ?? 0);
            if ($merchantUserId <= 0) {
                gjc_json_fail('Invalid merchant owner ID.');
            }

            echo json_encode([
                'success' => true,
                'inventory' => $directory->pagedInventory(
                    $merchantUserId,
                    trim((string) ($_GET['search'] ?? '')),
                    trim((string) ($_GET['category'] ?? '')),
                    trim((string) ($_GET['restriction'] ?? '')),
                    (int) ($_GET['page'] ?? 1),
                    (int) ($_GET['per_page'] ?? 10)
                ),
            ]);
            break;
        }

        case 'activity': {
            $merchantUserId = (int) ($_GET['merchant_user_id'] ?? 0);
            if ($merchantUserId <= 0) {
                gjc_json_fail('Invalid merchant owner ID.');
            }

            echo json_encode([
                'success' => true,
                'activity' => $directory->pagedActivity(
                    $merchantUserId,
                    (int) ($_GET['page'] ?? 1),
                    (int) ($_GET['per_page'] ?? 10)
                ),
            ]);
            break;
        }

        case 'toggle_product_restriction': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                gjc_json_fail('POST is required for compliance updates.');
            }

            $itemId = (int) ($_POST['item_id'] ?? 0);
            $restricted = (int) ($_POST['restricted'] ?? 0) === 1;
            $note = trim((string) ($_POST['note'] ?? ''));

            if ($itemId <= 0) {
                gjc_json_fail('Invalid inventory item.');
            }

            $updated = $directory->toggleProductRestriction($itemId, $restricted, $adminId, $note);
            echo json_encode([
                'success' => $updated,
                'message' => $updated
                    ? ($restricted ? 'Product restricted and disabled from POS.' : 'Product restriction cleared.')
                    : 'Inventory item not found.',
            ]);
            break;
        }

        case 'update_lease': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                gjc_json_fail('POST is required for lease updates.');
            }

            $leaseId = (int) ($_POST['lease_id'] ?? 0);
            $monthlyRent = (float) ($_POST['monthly_rent'] ?? 0);
            $depositAmount = (float) ($_POST['deposit_amount'] ?? 0);
            $leaseStart = trim((string) ($_POST['lease_start'] ?? ''));
            $leaseEnd = trim((string) ($_POST['lease_end'] ?? ''));
            $nextDueDate = trim((string) ($_POST['next_due_date'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'active'));
            $notes = trim((string) ($_POST['contract_notes'] ?? ''));
            $statuses = ['pending', 'active', 'expired', 'terminated'];

            if ($leaseId <= 0 || $monthlyRent < 0 || $depositAmount < 0) {
                gjc_json_fail('Invalid lease amounts or ID.');
            }

            if (!gjc_valid_ymd($leaseStart) || !gjc_valid_ymd($leaseEnd) || !gjc_valid_ymd($nextDueDate)) {
                gjc_json_fail('Lease dates must be valid YYYY-MM-DD values.');
            }

            if ($leaseEnd <= $leaseStart) {
                gjc_json_fail('Lease end date must be after lease start date.');
            }

            if (!in_array($status, $statuses, true)) {
                gjc_json_fail('Invalid lease status.');
            }

            $directory->updateLease([
                'lease_id' => $leaseId,
                'monthly_rent' => $monthlyRent,
                'deposit_amount' => $depositAmount,
                'lease_start' => $leaseStart,
                'lease_end' => $leaseEnd,
                'next_due_date' => $nextDueDate,
                'status' => $status,
                'contract_notes' => $notes,
            ]);

            echo json_encode(['success' => true, 'message' => 'Lease contract updated.']);
            break;
        }

        case 'record_rent_payment': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                gjc_json_fail('POST is required for rent collections.');
            }

            $leaseId = (int) ($_POST['lease_id'] ?? 0);
            $amount = (float) ($_POST['amount_paid'] ?? 0);
            $period = trim((string) ($_POST['period_covered'] ?? ''));
            $paymentDate = trim((string) ($_POST['payment_date'] ?? ''));
            $method = trim((string) ($_POST['payment_method'] ?? 'cash'));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $allowedMethods = ['cash', 'bank_transfer', 'check', 'other'];

            if ($leaseId <= 0 || $amount <= 0) {
                gjc_json_fail('A valid lease and positive rent amount are required.');
            }

            if (!gjc_valid_period($period)) {
                gjc_json_fail('Period covered must use YYYY-MM format.');
            }

            if (!gjc_valid_ymd($paymentDate)) {
                gjc_json_fail('Payment date must be a valid YYYY-MM-DD value.');
            }

            if (!in_array($method, $allowedMethods, true)) {
                $method = 'other';
            }

            $reference = $directory->recordRentPayment($leaseId, $amount, $period, $paymentDate, $method, $notes, $adminId);

            echo json_encode([
                'success' => true,
                'message' => 'Rent payment recorded.',
                'reference_no' => $reference,
            ]);
            break;
        }

        default:
            gjc_json_fail('Unknown action.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ]);
}
