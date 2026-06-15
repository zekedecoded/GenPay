<?php
// ============================================================
//  admin/api/stall_applications.php
//  JSON API for the stall applications admin workflow
//  Actions: initial_approval | reject | verify_payment | final_approval
// ============================================================
session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/mailer.php';

header('Content-Type: application/json');
gjc_require_role(['finance']);

$action  = trim((string) ($_POST['action'] ?? ''));
$adminId = gjc_user_id();

try {
    switch ($action) {

        // â”€â”€ Step 2.1: Initial Approval â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'initial_approval': {
            $appId = (int) ($_POST['app_id'] ?? 0);
            if (!$appId) {
                echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
                exit;
            }

            // Fetch application â€” must be pending
            $app = $db->prepare(
                "SELECT sa.*, s.label AS stall_label
                 FROM stall_applications sa
                 LEFT JOIN stalls s ON s.stall_id = sa.stall_id
                 WHERE sa.id = ? AND sa.status = 'pending'
                 LIMIT 1"
            );
            $app->execute([$appId]);
            $app = $app->fetch(PDO::FETCH_ASSOC);

            if (!$app) {
                echo json_encode(['success' => false, 'message' => 'Application not found or not in Pending status.']);
                exit;
            }

            // Auto-generate contract reference: SA-{zero-padded id}-{year}
            $contractRef = 'SA-' . str_pad($appId, 5, '0', STR_PAD_LEFT) . '-' . date('Y');

            $db->beginTransaction();
            try {
                // Update application status
                $db->prepare(
                    "UPDATE stall_applications
                     SET status                = 'initially_approved',
                         contract_ref          = ?,
                         initially_approved_by = ?,
                         initially_approved_at = NOW()
                     WHERE id = ?"
                )->execute([$contractRef, $adminId, $appId]);

                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
                exit;
            }

            // â”€â”€ Send invitation email (non-blocking: failure doesn't abort) â”€â”€
            $mailSent = false;
            $mailError = '';
            try {
                $mail = gjc_mailer();
                $mail->addAddress($app['email'], $app['proprietor_name']);
                $mail->Subject = 'GJC EduPay â€” Your Stall Application Has Been Initially Approved';
                $mail->Body = '
                    <div style="font-family:\'Plus Jakarta Sans\',Arial,sans-serif;max-width:560px;margin:0 auto;background:#f0fdf4;padding:32px 24px;border-radius:16px">
                        <div style="text-align:center;margin-bottom:24px">
                            <div style="font-size:40px">ðŸª</div>
                            <h2 style="color:#064420;margin:8px 0 4px">GJC EduPay</h2>
                            <p style="color:#15803d;font-size:13px;margin:0">Campus Stall Management</p>
                        </div>
                        <div style="background:#ffffff;border-radius:12px;padding:24px;margin-bottom:20px">
                            <h3 style="color:#064420;margin-top:0">Dear ' . htmlspecialchars($app['proprietor_name']) . ',</h3>
                            <p style="color:#374151;line-height:1.7">
                                We are pleased to inform you that your application for
                                <strong>' . htmlspecialchars($app['stall_label'] ?? $app['stall_id']) . '</strong>
                                at General de Jesus College has been <strong style="color:#15803d">Initially Approved</strong>.
                            </p>
                            <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:14px;margin:16px 0">
                                <p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:.06em">Contract Reference</p>
                                <p style="margin:0;font-size:20px;font-weight:900;color:#064420;font-family:monospace">' . htmlspecialchars($contractRef) . '</p>
                            </div>
                            <p style="color:#374151;line-height:1.7"><strong>Next Steps:</strong></p>
                            <ol style="color:#374151;line-height:1.9;padding-left:20px">
                                <li>Please visit the GJC Finance Office to review and sign your stall contract.</li>
                                <li>Bring a valid ID and your contract reference number above.</li>
                                <li>Pay the processing fee of <strong>â‚±150.00</strong> via GCash or over the counter.</li>
                                <li>Once payment is confirmed, your stall account will be activated.</li>
                            </ol>
                        </div>
                        <p style="font-size:12px;color:#6b7280;text-align:center">
                            General de Jesus College &mdash; GJC EduPay &bull; Do not reply to this email.
                        </p>
                    </div>';
                $mail->AltBody = "Dear {$app['proprietor_name']},\n\nYour stall application for {$app['stall_label']} has been initially approved.\n\nContract Ref: {$contractRef}\n\nPlease visit the GJC Finance Office with a valid ID and pay the â‚±150 processing fee.\n\nGJC EduPay Team";
                $mail->send();
                $mailSent = true;
            } catch (Throwable $mailEx) {
                $mailError = $mailEx->getMessage();
            }

            echo json_encode([
                'success'     => true,
                'message'     => "Application initially approved! Contract ref: {$contractRef}."
                    . ($mailSent ? ' Invitation email sent.' : ' Note: email failed â€” ' . $mailError),
                'contract_ref' => $contractRef,
                'mail_sent'   => $mailSent,
            ]);
            break;
        }

        // â”€â”€ Reject application â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'reject': {
            $appId  = (int) ($_POST['app_id'] ?? 0);
            $reason = trim((string) ($_POST['rejection_reason'] ?? ''));

            if (!$appId || !$reason) {
                echo json_encode(['success' => false, 'message' => 'Application ID and rejection reason are required.']);
                exit;
            }

            // Fetch to get email for notification
            $app = $db->prepare("SELECT * FROM stall_applications WHERE id = ? LIMIT 1");
            $app->execute([$appId]);
            $app = $app->fetch(PDO::FETCH_ASSOC);

            if (!$app || in_array($app['status'], ['active'], true)) {
                echo json_encode(['success' => false, 'message' => 'Cannot reject an active application.']);
                exit;
            }

            $db->prepare(
                "UPDATE stall_applications
                 SET status           = 'rejected',
                     rejection_reason = ?,
                     reviewed_by      = ?,
                     reviewed_at      = NOW()
                 WHERE id = ?"
            )->execute([$reason, $adminId, $appId]);

            // Release stall back to vacant
            $db->prepare(
                "UPDATE stalls
                 SET status = 'vacant', pending_expires_at = NULL
                 WHERE stall_id = ?
                   AND status != 'occupied'"
            )->execute([$app['stall_id']]);

            // Notify applicant
            try {
                $mail = gjc_mailer();
                $mail->addAddress($app['email'], $app['proprietor_name']);
                $mail->Subject = 'GJC EduPay â€” Stall Application Update';
                $mail->Body = '
                    <div style="font-family:Arial,sans-serif;max-width:540px;margin:0 auto;padding:28px;background:#fff1f2;border-radius:14px">
                        <h3 style="color:#b91c1c">Dear ' . htmlspecialchars($app['proprietor_name']) . ',</h3>
                        <p style="color:#374151;line-height:1.7">After review, we regret to inform you that your stall application has not been approved at this time.</p>
                        <div style="background:#fff;border:1px solid #fca5a5;border-radius:10px;padding:14px;margin:14px 0">
                            <p style="margin:0 0 4px;font-size:11px;font-weight:700;color:#b91c1c;text-transform:uppercase">Reason</p>
                            <p style="margin:0;color:#374151">' . htmlspecialchars($reason) . '</p>
                        </div>
                        <p style="color:#374151">You may re-apply in the future. For queries, contact the GJC Finance Office.</p>
                        <p style="font-size:12px;color:#9ca3af">GJC EduPay Team</p>
                    </div>';
                $mail->send();
            } catch (Throwable $ignored) {}

            echo json_encode(['success' => true, 'message' => 'Application rejected and applicant notified.']);
            break;
        }

        // â”€â”€ Step 2.2: Record GCash payment â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'verify_payment': {
            $appId  = (int) ($_POST['app_id'] ?? 0);
            $gcRef  = trim((string) ($_POST['gcash_ref_number'] ?? ''));
            $notes  = trim((string) ($_POST['notes'] ?? ''));

            if (!$appId || !$gcRef) {
                echo json_encode(['success' => false, 'message' => 'Application ID and GCash reference are required.']);
                exit;
            }

            // Must be initially_approved
            $app = $db->prepare("SELECT * FROM stall_applications WHERE id = ? AND status = 'initially_approved' LIMIT 1");
            $app->execute([$appId]);
            $app = $app->fetch(PDO::FETCH_ASSOC);

            if (!$app) {
                echo json_encode(['success' => false, 'message' => 'Application not found or not in Initially Approved status.']);
                exit;
            }

            // Check duplicate
            $dup = $db->prepare("SELECT id FROM payment_verifications WHERE application_id = ? LIMIT 1");
            $dup->execute([$appId]);
            if ($dup->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Payment already recorded for this application.']);
                exit;
            }

            $db->beginTransaction();
            try {
                $db->prepare(
                    "INSERT INTO payment_verifications
                         (application_id, amount, gcash_ref_number, verified_by, notes)
                     VALUES (?, 150.00, ?, ?, ?)"
                )->execute([$appId, $gcRef, $adminId, $notes ?: null]);

                // Record signed_at on the application
                $db->prepare(
                    "UPDATE stall_applications SET signed_at = NOW() WHERE id = ?"
                )->execute([$appId]);

                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => "Payment of â‚±150.00 recorded. GCash ref: {$gcRef}. You may now grant Final Approval.",
            ]);
            break;
        }

        // â”€â”€ Step 2.3: Final Approval cascade â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        case 'final_approval': {
            $appId = (int) ($_POST['app_id'] ?? 0);
            if (!$appId) {
                echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
                exit;
            }

            // Fetch application â€” must be initially_approved
            $appStmt = $db->prepare(
                "SELECT sa.*, s.label AS stall_label
                 FROM stall_applications sa
                 LEFT JOIN stalls s ON s.stall_id = sa.stall_id
                 WHERE sa.id = ? AND sa.status = 'initially_approved'
                 LIMIT 1"
            );
            $appStmt->execute([$appId]);
            $app = $appStmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) {
                echo json_encode(['success' => false, 'message' => 'Application not found or not in Initially Approved status.']);
                exit;
            }

            // Payment must be verified first
            $pvStmt = $db->prepare("SELECT id FROM payment_verifications WHERE application_id = ? LIMIT 1");
            $pvStmt->execute([$appId]);
            if (!$pvStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Cannot finalise: no payment verification on record.']);
                exit;
            }

            // Generate temp password (8-char alphanumeric)
            $chars       = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
            $tempPassword = '';
            for ($i = 0; $i < 8; $i++) {
                $tempPassword .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $hashedPw = password_hash($tempPassword, PASSWORD_BCRYPT);

            // Parse name: last word = last_name, rest = first_name
            $nameParts = explode(' ', trim($app['proprietor_name']));
            $lastName  = array_pop($nameParts);
            $firstName = implode(' ', $nameParts) ?: $lastName;

            $db->beginTransaction();
            try {
                // 1. Create users record (roleID=2 merchant, sub_role=merchant_admin)
                $db->prepare(
                    "INSERT INTO users
                         (last_name, first_name, email, contact_number,
                          roleID, sub_role, password, profile_img, force_password_change)
                     VALUES (?, ?, ?, ?, 2, 'merchant_admin', ?, ?, 1)"
                )->execute([
                    $lastName, $firstName,
                    $app['email'], $app['contact_number'],
                    $hashedPw, $app['profile_picture'],
                ]);
                $newUserId = (int) $db->lastInsertId();

                // 2. Create merchant record and link stall_id
                $db->prepare(
                    "INSERT INTO merchant (userID, stall_name, stall_id, operational_status)
                     VALUES (?, ?, ?, 'active')"
                )->execute([$newUserId, $app['business_name'], $app['stall_id']]);
                $newMerchantId = (int) $db->lastInsertId();

                // 3. Create merchant wallet
                $db->prepare(
                    "INSERT IGNORE INTO merchant_wallets (user_id, balance) VALUES (?, 0.00)"
                )->execute([$newUserId]);

                // 4. Occupy the stall
                $db->prepare(
                    "UPDATE stalls
                     SET status = 'occupied', merchant_id = ?, pending_expires_at = NULL
                     WHERE stall_id = ?"
                )->execute([$newMerchantId, $app['stall_id']]);

                // 5. Mark application active, record reviewer
                $db->prepare(
                    "UPDATE stall_applications
                     SET status = 'active', reviewed_by = ?, reviewed_at = NOW()
                     WHERE id = ?"
                )->execute([$adminId, $appId]);

                // 6. Insert merchant_accounts bridge (stores temp plaintext for display)
                $db->prepare(
                    "INSERT INTO merchant_accounts
                         (application_id, user_id, temp_password_plain, created_by)
                     VALUES (?, ?, ?, ?)"
                )->execute([$appId, $newUserId, $tempPassword, $adminId]);

                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Final approval failed: ' . $e->getMessage()]);
                exit;
            }

            // â”€â”€ Send credentials email (non-blocking) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $mailSent  = false;
            $mailError = '';
            try {
                $mail = gjc_mailer();
                $mail->addAddress($app['email'], $app['proprietor_name']);
                $mail->Subject = 'GJC EduPay â€” Your Stall Account Is Now Active!';
                $mail->Body = '
                    <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f5f3ff;padding:32px 24px;border-radius:16px">
                        <div style="text-align:center;margin-bottom:22px">
                            <div style="font-size:44px">ðŸŽ‰</div>
                            <h2 style="color:#4c1d95;margin:8px 0 4px">Stall Account Activated!</h2>
                        </div>
                        <div style="background:#fff;border-radius:12px;padding:24px;margin-bottom:18px">
                            <p style="color:#374151;line-height:1.7">Dear <strong>' . htmlspecialchars($app['proprietor_name']) . '</strong>,</p>
                            <p style="color:#374151;line-height:1.7">
                                Your stall application for <strong>' . htmlspecialchars($app['stall_label'] ?? $app['stall_id']) . '</strong>
                                has been <strong style="color:#15803d">fully approved</strong> and your GJC EduPay merchant account is now active.
                            </p>
                            <div style="background:#1e1b4b;border-radius:10px;padding:18px 20px;margin:16px 0">
                                <p style="margin:0 0 6px;font-size:11px;font-weight:700;color:#a78bfa;text-transform:uppercase">Login Credentials</p>
                                <p style="margin:0 0 4px;color:#e0e7ff;font-family:monospace"><strong style="color:#c4b5fd">Email:</strong> ' . htmlspecialchars($app['email']) . '</p>
                                <p style="margin:0;color:#e0e7ff;font-family:monospace"><strong style="color:#c4b5fd">Temp Password:</strong> ' . htmlspecialchars($tempPassword) . '</p>
                            </div>
                            <p style="color:#dc2626;font-size:13px;font-weight:700">âš  You will be required to change your password on first login.</p>
                            <p style="color:#374151;line-height:1.7">
                                Log in at: <a href="' . BASE_URL . '/login" style="color:#7c3aed">' . BASE_URL . '/login</a>
                            </p>
                        </div>
                        <p style="font-size:12px;color:#6b7280;text-align:center">GJC EduPay &mdash; Campus Digital Wallet</p>
                    </div>';
                $mail->AltBody = "Dear {$app['proprietor_name']},\n\nYour stall account is now active!\n\nEmail: {$app['email']}\nTemp Password: {$tempPassword}\n\nPlease log in at " . BASE_URL . "/login and change your password immediately.\n\nGJC EduPay Team";
                $mail->send();
                $mailSent = true;
            } catch (\Throwable $mailEx) {
                $mailError = $mailEx->getMessage();
            }

            echo json_encode([
                'success'       => true,
                'message'       => "Final approval complete! Merchant account created for {$app['proprietor_name']}."
                    . ($mailSent ? ' Credentials emailed.' : ' Note: email failed â€” ' . $mailError),
                'user_id'       => $newUserId,
                'temp_password' => $tempPassword,
                'mail_sent'     => $mailSent,
            ]);
            break;
        }

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
