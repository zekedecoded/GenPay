<?php
session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';

header('Content-Type: application/json');
gjc_require_role(['admin']);

$action  = trim((string) ($_POST['action'] ?? ''));
$adminId = gjc_user_id();

try {
    switch ($action) {

        // ── Submit new application ───────────────────────────────────────────
        case 'submit_application': {
            $businessName = trim((string) ($_POST['business_name'] ?? ''));
            $ownerName    = trim((string) ($_POST['owner_name']    ?? ''));
            $ownerEmail   = strtolower(trim((string) ($_POST['owner_email']   ?? '')));
            $ownerContact = trim((string) ($_POST['owner_contact'] ?? ''));
            $stallNumber  = trim((string) ($_POST['stall_number']  ?? ''));
            $productTypes = trim((string) ($_POST['product_types'] ?? ''));

            if (!$businessName || !$ownerName || !$ownerEmail || !$ownerContact || !$productTypes) {
                echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
                exit;
            }

            $stmt = $db->prepare(
                "INSERT INTO merchant_applications
                    (business_name, owner_name, owner_email, owner_contact, stall_number, product_types, stage)
                 VALUES (?, ?, ?, ?, ?, ?, 'submitted')"
            );
            $stmt->execute([
                $businessName, $ownerName, $ownerEmail, $ownerContact,
                $stallNumber ?: null, $productTypes,
            ]);
            echo json_encode(['success' => true, 'message' => 'Application submitted into the pipeline.']);
            break;
        }

        // ── Advance stage ────────────────────────────────────────────────────
        case 'advance_stage': {
            $appId     = (int) ($_POST['app_id']     ?? 0);
            $nextStage = trim((string) ($_POST['next_stage'] ?? ''));
            $allowed   = ['compliance_review', 'exec_review'];

            if (!$appId || !in_array($nextStage, $allowed, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid stage transition.']);
                exit;
            }

            $updates = ['stage = ?'];
            $params  = [$nextStage];

            if ($nextStage === 'compliance_review') {
                $updates[] = 'compliance_by = ?';
                $updates[] = 'compliance_at = NOW()';
                $params[]  = $adminId;
            } elseif ($nextStage === 'exec_review') {
                $updates[] = 'exec_by = ?';
                $updates[] = 'exec_at = NOW()';
                $params[]  = $adminId;
            }

            $params[] = $appId;
            $sql = 'UPDATE merchant_applications SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $db->prepare($sql)->execute($params);

            echo json_encode([
                'success' => true,
                'message' => 'Application advanced to ' . str_replace('_', ' ', $nextStage) . '.',
            ]);
            break;
        }

        // ── Approve & auto-create merchant account ───────────────────────────
        case 'approve_application': {
            $appId = (int) ($_POST['app_id'] ?? 0);
            if (!$appId) {
                echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
                exit;
            }

            $appStmt = $db->prepare(
                "SELECT * FROM merchant_applications WHERE id = ? AND stage = 'exec_review' LIMIT 1"
            );
            $appStmt->execute([$appId]);
            $app = $appStmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) {
                echo json_encode(['success' => false, 'message' => 'Application not found or not in Exec Review stage.']);
                exit;
            }

            $db->beginTransaction();
            try {
                $tempPassword = bin2hex(random_bytes(6));
                $hashedPw     = password_hash($tempPassword, PASSWORD_BCRYPT);
                $nameParts    = explode(' ', $app['owner_name'], 2);
                $firstName    = $nameParts[0];
                $lastName     = $nameParts[1] ?? '';

                // Create user (Merchant Admin = roleID 5)
                $db->prepare(
                    "INSERT INTO users
                        (last_name, first_name, email, contact_number, roleID, sub_role, password, profile_img)
                     VALUES (?, ?, ?, ?, 5, 'merchant_admin', ?, '')"
                )->execute([$lastName, $firstName, $app['owner_email'], $app['owner_contact'], $hashedPw]);
                $newUserId = (int) $db->lastInsertId();

                // Create merchant record
                $db->prepare("INSERT IGNORE INTO merchant (userID, stall_name) VALUES (?, ?)")
                   ->execute([$newUserId, $app['business_name']]);

                // Create merchant wallet
                $db->prepare("INSERT IGNORE INTO merchant_wallets (user_id, balance) VALUES (?, 0.00)")
                   ->execute([$newUserId]);

                // Mark application approved
                $db->prepare(
                    "UPDATE merchant_applications
                        SET stage='approved', approved_by=?, approved_at=NOW(), generated_user_id=?
                      WHERE id=?"
                )->execute([$adminId, $newUserId, $appId]);

                $db->commit();

                echo json_encode([
                    'success'       => true,
                    'message'       => "Account created for {$app['owner_name']}.\n\nEmail: {$app['owner_email']}\nTemp Password: {$tempPassword}\n\nShare these credentials securely with the vendor.",
                    'user_id'       => $newUserId,
                    'temp_password' => $tempPassword,
                ]);
            } catch (\Throwable $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Account creation failed: ' . $e->getMessage()]);
            }
            break;
        }

        // ── Reject application ───────────────────────────────────────────────
        case 'reject_application': {
            $appId  = (int) ($_POST['app_id'] ?? 0);
            $reason = trim((string) ($_POST['rejection_reason'] ?? ''));

            if (!$appId || !$reason) {
                echo json_encode(['success' => false, 'message' => 'Application ID and rejection reason are required.']);
                exit;
            }

            $db->prepare(
                "UPDATE merchant_applications
                    SET stage='rejected', rejected_by=?, rejected_at=NOW(), rejection_reason=?
                  WHERE id=?"
            )->execute([$adminId, $reason, $appId]);

            echo json_encode(['success' => true, 'message' => 'Application rejected.']);
            break;
        }

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
