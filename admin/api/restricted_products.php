<?php
session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/audit_logger.php';

header('Content-Type: application/json');
gjc_require_role(['finance']);

$action  = trim((string) ($_POST['action'] ?? ''));
$adminId = gjc_user_id();

try {
    switch ($action) {
        case 'flag_product': {
            $name      = trim((string) ($_POST['product_name'] ?? ''));
            $category  = trim((string) ($_POST['category'] ?? 'general'));
            $matchType = in_array(($_POST['match_type'] ?? ''), ['exact', 'contains'])
                ? $_POST['match_type'] : 'contains';
            $reason    = trim((string) ($_POST['reason'] ?? ''));

            if (!$name || !$reason) {
                echo json_encode(['success' => false, 'message' => 'Product name and reason are required.']);
                exit;
            }

            $stmt = $db->prepare(
                "INSERT INTO restricted_products (product_name, category, reason, match_type, flagged_by)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $category, $reason, $matchType, $adminId]);
            $newProductId = (int) $db->lastInsertId();

            logAudit(
                $db,
                $adminId,
                gjc_current_role(),
                'PRODUCT_RESTRICTION',
                'restricted_products',
                null,
                [
                    'event' => 'flagged',
                    'id' => $newProductId,
                    'product_name' => $name,
                    'category' => $category,
                    'match_type' => $matchType,
                    'reason' => $reason,
                ]
            );

            echo json_encode(['success' => true, 'message' => 'Product flagged successfully.']);
            break;
        }

        case 'toggle_restriction': {
            $id       = (int) ($_POST['id'] ?? 0);
            $isActive = (int) ($_POST['is_active'] ?? 0);

            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
                exit;
            }

            $prevStmt = $db->prepare("SELECT is_active FROM restricted_products WHERE id = ?");
            $prevStmt->execute([$id]);
            $prevActive = $prevStmt->fetchColumn();

            $db->prepare("UPDATE restricted_products SET is_active = ? WHERE id = ?")
               ->execute([$isActive, $id]);

            logAudit(
                $db,
                $adminId,
                gjc_current_role(),
                'PRODUCT_RESTRICTION',
                'restricted_products',
                $prevActive === false ? null : ['id' => $id, 'is_active' => (int) $prevActive],
                ['event' => 'status_changed', 'id' => $id, 'is_active' => $isActive]
            );

            echo json_encode(['success' => true, 'message' => 'Status updated.']);
            break;
        }

        case 'delete_restriction': {
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
                exit;
            }
            $prevStmt = $db->prepare("SELECT product_name FROM restricted_products WHERE id = ?");
            $prevStmt->execute([$id]);
            $prevName = (string) $prevStmt->fetchColumn();

            $db->prepare("DELETE FROM restricted_products WHERE id = ?")->execute([$id]);

            logAudit($db, $adminId, gjc_current_role(), 'PRODUCT_RESTRICTION', 'restricted_products',
                ['id' => $id, 'product_name' => $prevName], ['event' => 'deleted', 'id' => $id]);

            echo json_encode(['success' => true, 'message' => 'Restriction removed.']);
            break;
        }

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
