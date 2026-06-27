<?php
session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/audit_logger.php';

header('Content-Type: application/json');
gjc_require_role(['merchant']);

// Only Merchant Admin (or legacy merchant) can manage staff
if (!gjc_is_merchant_admin() && (gjc_current_role() !== 'merchant' || gjc_is_merchant_staff())) {
    echo json_encode(['success' => false, 'message' => 'Only Merchant Admin can manage staff accounts.']);
    exit;
}

$action         = trim((string) ($_POST['action'] ?? ''));
$merchantUserId = gjc_user_id();

try {
    switch ($action) {
        case 'create_staff': {
            $firstName  = trim((string) ($_POST['first_name'] ?? ''));
            $lastName   = trim((string) ($_POST['last_name'] ?? ''));
            $email      = strtolower(trim((string) ($_POST['email'] ?? '')));
            $contact    = trim((string) ($_POST['contact_number'] ?? '0'));
            $password   = (string) ($_POST['password'] ?? '');

            if (!$firstName || !$lastName || !$email || !$password) {
                echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
                exit;
            }

            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
                exit;
            }

            // Check email uniqueness
            $dup = $db->prepare("SELECT userID FROM users WHERE email = ? LIMIT 1");
            $dup->execute([$email]);
            if ($dup->fetch()) {
                echo json_encode(['success' => false, 'message' => 'An account with that email already exists.']);
                exit;
            }

            $hashedPw = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare(
                "INSERT INTO users
                    (last_name, first_name, email, contact_number, roleID, sub_role, merchant_owner_id, password, profile_img)
                 VALUES (?, ?, ?, ?, 6, 'merchant_staff', ?, ?, '')"
            );
            $stmt->execute([$lastName, $firstName, $email, $contact ?: '0', $merchantUserId, $hashedPw]);
            $newStaffId = (int) $db->lastInsertId();

            logAudit(
                $db,
                $merchantUserId,
                gjc_current_role(),
                'USER_ACCOUNT',
                'users',
                null,
                [
                    'event' => 'created',
                    'user_id' => $newStaffId,
                    'name' => trim($firstName . ' ' . $lastName),
                    'email' => $email,
                    'role' => 'merchant_staff',
                    'merchant_owner_id' => $merchantUserId,
                ]
            );

            echo json_encode(['success' => true, 'message' => 'Staff account created successfully.', 'user_id' => $newStaffId]);
            break;
        }

        case 'toggle_staff_status': {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
                exit;
            }

            // Verify this staff belongs to the current merchant admin
            $verify = $db->prepare(
                "SELECT userID, status FROM users WHERE userID = ? AND merchant_owner_id = ? AND roleID = 6"
            );
            $verify->execute([$userId, $merchantUserId]);
            $staffRow = $verify->fetch();
            if (!$staffRow) {
                echo json_encode(['success' => false, 'message' => 'Staff member not found or not under your management.']);
                exit;
            }

            $currentStatus = $staffRow['status'];
            $newStatus     = $currentStatus === 'Active' ? 'Inactive' : 'Active';

            $db->prepare(
                "UPDATE users SET status = ? WHERE userID = ? AND merchant_owner_id = ?"
            )->execute([$newStatus, $userId, $merchantUserId]);

            logAudit(
                $db,
                $merchantUserId,
                gjc_current_role(),
                'USER_ACCOUNT',
                'users',
                ['user_id' => $userId, 'status' => $currentStatus],
                ['event' => $newStatus === 'Active' ? 'reactivated' : 'deactivated', 'user_id' => $userId, 'changed_by' => $merchantUserId, 'new_status' => $newStatus]
            );

            echo json_encode(['success' => true, 'new_status' => $newStatus, 'message' => "Staff account set to {$newStatus}."]);
            break;
        }

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
