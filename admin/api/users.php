<?php
/**
 * admin/api/users.php — account-control actions behind the Manage dropdown in
 * admin/users.php. Multi-stage dispatcher shape (references/patterns.md):
 * $_POST + FormData, one `action` switch, JSON out.
 *
 * Suspend is a finance-issued lockout, distinct from the merchant-staff
 * Active/Inactive toggle and from the automatic restricted-product suspension.
 * The state itself lives on users.status = 'Suspended' plus the suspension_*
 * columns; everything that reads or reverses it lives in connection/app.php
 * (gjc_suspend_account / gjc_account_suspension / gjc_lift_account_suspension)
 * so the enforcement points and this endpoint can never disagree.
 */
session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/audit_logger.php';

header('Content-Type: application/json');
gjc_require_role(['finance']);
gjc_ensure_account_suspension_schema($db);

/** Hard ceiling on a timed suspension — anything longer should be indefinite. */
const GJC_SUSPEND_MAX_DAYS = 365;

$action  = trim((string) ($_POST['action'] ?? ''));
$adminId = gjc_user_id();

function users_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

/**
 * The target account, or null. Reads roleID/sub_role directly rather than
 * joining `role` — that table only seeds roleIDs 1-3, so a join would report
 * finance (4), merchant staff (6) and parent (7) as unknown and the guard rail
 * below would wave through the very accounts it exists to protect.
 */
function users_fetch(PDO $db, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }
    $stmt = $db->prepare(
        "SELECT userID, first_name, last_name, email, roleID, sub_role, status,
                suspended_until, suspension_reason
           FROM users WHERE userID = ? LIMIT 1"
    );
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Why this account may not be suspended, or null when it may. Keeps an admin
 * from locking out themselves — or the finance team as a whole — which nobody
 * left inside the system would be able to undo.
 */
function users_suspend_guard(array $user, int $adminId): ?string
{
    if ((int) $user['userID'] === $adminId) {
        return 'You cannot suspend your own account.';
    }
    if (in_array((int) $user['roleID'], [3, 4], true)) {
        return 'Finance accounts cannot be suspended from this page — revoking finance access is a school-administrator action.';
    }
    if ((string) ($user['sub_role'] ?? '') === 'super_admin') {
        return 'The super-admin account cannot be suspended.';
    }
    return null;
}

try {
    switch ($action) {

        case 'suspend': {
            $userId   = (int) ($_POST['user_id'] ?? 0);
            $duration = trim((string) ($_POST['duration'] ?? ''));
            $reason   = trim((string) ($_POST['reason'] ?? ''));

            $user = users_fetch($db, $userId);
            if (!$user) {
                users_json(['success' => false, 'message' => 'That user no longer exists.']);
            }
            if (($guard = users_suspend_guard($user, $adminId)) !== null) {
                users_json(['success' => false, 'message' => $guard]);
            }
            if (mb_strlen($reason) < 5) {
                users_json(['success' => false, 'message' => 'Give a reason of at least 5 characters — the user sees it, and it goes into the audit trail.']);
            }

            // NULL end date = indefinite. Anything else is a day count, which
            // has to land inside the allowed window.
            $until = null;
            if ($duration !== 'indefinite') {
                $days = filter_var($duration, FILTER_VALIDATE_INT);
                if ($days === false || $days < 1 || $days > GJC_SUSPEND_MAX_DAYS) {
                    users_json(['success' => false, 'message' => 'Pick a duration between 1 and ' . GJC_SUSPEND_MAX_DAYS . ' days, or Indefinite.']);
                }
                $until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            }

            if (!gjc_suspend_account($db, $userId, $until, $reason, $adminId)) {
                users_json(['success' => false, 'message' => 'That account is already suspended.']);
            }

            $untilLabel = $until !== null ? date('M d, Y g:i A', strtotime($until)) : 'further notice';
            $name = trim($user['first_name'] . ' ' . $user['last_name']);

            logAudit(
                $db, $adminId, gjc_current_role(),
                'USER_ACCOUNT', 'users',
                ['userID' => $userId, 'status' => (string) $user['status']],
                [
                    'event'           => 'suspended',
                    'userID'          => $userId,
                    'status'          => 'Suspended',
                    'suspended_until' => $until,
                    'indefinite'      => $until === null,
                    'reason'          => $reason,
                    'suspended_by'    => $adminId,
                ]
            );

            gjc_notify(
                $db, $userId, 'compliance', 'Your account has been suspended',
                gjc_suspension_notice(['until' => $until, 'reason' => $reason], 'Your GenPay account'),
                'triangle-exclamation'
            );

            users_json([
                'success' => true,
                'message' => "{$name} suspended until {$untilLabel}. Any signed-in session ends on their next click.",
            ]);
        }

        case 'lift_suspension': {
            $userId = (int) ($_POST['user_id'] ?? 0);

            $user = users_fetch($db, $userId);
            if (!$user) {
                users_json(['success' => false, 'message' => 'That user no longer exists.']);
            }

            // gjc_lift_account_suspension() writes the audit entry and the
            // notification itself — shared with the automatic expiry path, so a
            // suspension is always undone the same way.
            if (!gjc_lift_account_suspension($db, $userId, $adminId)) {
                users_json(['success' => false, 'message' => 'That account is not currently suspended.']);
            }

            $name = trim($user['first_name'] . ' ' . $user['last_name']);
            users_json(['success' => true, 'message' => "Suspension lifted. {$name} can sign in again."]);
        }

        default:
            users_json(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (RuntimeException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    users_json(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[admin/api/users.php] ' . $e->getMessage());
    users_json(['success' => false, 'message' => 'A server error occurred.']);
}
