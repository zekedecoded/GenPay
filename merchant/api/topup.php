<?php
declare(strict_types=1);
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/CirculationEngine.php';
require_once __DIR__ . '/../../connection/audit_logger.php';

gjc_require_role(['merchant']);
gjc_ensure_operational_tables($db);

$initiatedBy      = gjc_user_id();
$ownerMerchId     = gjc_merchant_owner_id($db, $initiatedBy);
$merchantWallet   = gjc_merchant_wallet($db, $ownerMerchId);
$merchantWalletId = (int) $merchantWallet['id'];

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$body = str_contains($contentType, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : array_merge($_GET, $_POST);

$action = strtolower(trim($body['action'] ?? ''));

try {
    switch ($action) {

        case 'lookup_student': {
            $schoolId = trim((string)($body['school_id'] ?? ''));
            if (!$schoolId) throw new \InvalidArgumentException('Student ID is required.');

            $stmt = $db->prepare(
                "SELECT u.userID, u.first_name, u.last_name,
                        sw.id AS wallet_id
                   FROM users u
                   JOIN student_info si ON si.userID = u.userID
                   JOIN student_wallets sw ON sw.user_id = u.userID
                  WHERE si.studentID = ? AND u.roleID = 1
                  LIMIT 1"
            );
            $stmt->execute([$schoolId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) throw new \RuntimeException('Student not found. Check the ID and try again.');

            echo json_encode([
                'success'   => true,
                'name'      => trim($student['first_name'] . ' ' . $student['last_name']),
                'user_id'   => (int) $student['userID'],
                'wallet_id' => (int) $student['wallet_id'],
            ]);
            break;
        }

        case 'load_wallet': {
            $studentWalletId = (int)($body['student_wallet_id'] ?? 0);
            $cashAmount      = (float)($body['cash_amount'] ?? 0);

            if ($studentWalletId <= 0) throw new \InvalidArgumentException('Invalid student wallet.');
            if ($cashAmount <= 0)      throw new \InvalidArgumentException('Cash amount must be greater than zero.');

            $engine = new CirculationEngine($db);
            $result = $engine->cashInWithFee(
                $studentWalletId,
                $cashAmount,
                'merchant',
                $initiatedBy,
                $merchantWalletId
            );

            logAudit(
                $db,
                $initiatedBy,
                gjc_current_role(),
                'TRANSACTION',
                'transactions',
                null,
                [
                    'event'            => 'merchant_wallet_load',
                    'reference'        => $result['reference'],
                    'cash_amount'      => $cashAmount,
                    'system_fee'       => $result['system_fee'],
                    'merchant_fee'     => $result['merchant_fee'],
                    'credited_amount'  => $result['credited_amount'],
                    'merchant_wallet'  => $merchantWalletId,
                    'student_wallet'   => $studentWalletId,
                ]
            );

            echo json_encode(array_merge(['success' => true], $result));
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Unknown action: '{$action}'"]);
    }

} catch (\RuntimeException | \InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[merchant/api/topup.php] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
}
