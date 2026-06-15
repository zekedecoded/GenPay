<?php
// ============================================================
//  admin/api/stall_applications.php
//  JSON API for the stall applications finance workflow
//  Actions: schedule_meetup | record_down_payment | final_approval | reject
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/audit_logger.php';
require_once __DIR__ . '/../../connection/mailer.php';

header('Content-Type: application/json; charset=UTF-8');
gjc_require_role(['finance']);
gjc_ensure_stall_application_workflow_schema($db);
gjc_ensure_first_login_schema($db);

$action  = trim((string) ($_POST['action'] ?? ''));
$adminId = gjc_user_id();

function stall_app_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function stall_app_temp_password(int $length = 10): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

try {
    switch ($action) {
        case 'schedule_meetup': {
            $appId = (int) ($_POST['app_id'] ?? 0);
            $date  = trim((string) ($_POST['meetup_date'] ?? ''));
            $time  = trim((string) ($_POST['meetup_time'] ?? ''));
            $place = trim((string) ($_POST['meetup_location'] ?? ''));
            $notes = trim((string) ($_POST['meetup_notes'] ?? ''));

            if (!$appId || !$date || !$time || !$place) {
                stall_app_json(['success' => false, 'message' => 'Date, time, and location are required.']);
            }

            $scheduledAt = $date . ' ' . $time . ':00';
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt);
            if (!$dt || $dt->format('Y-m-d H:i:s') !== $scheduledAt) {
                stall_app_json(['success' => false, 'message' => 'Invalid meet-up date or time.']);
            }

            $stmt = $db->prepare(
                "SELECT sa.*, s.label AS stall_label
                   FROM stall_applications sa
                   LEFT JOIN stalls s ON s.stall_id = sa.stall_id
                  WHERE sa.id = ?
                    AND sa.status IN ('pending', 'initially_approved')
                  LIMIT 1"
            );
            $stmt->execute([$appId]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) {
                stall_app_json(['success' => false, 'message' => 'Application not found or not ready for meet-up scheduling.']);
            }

            $db->prepare(
                "UPDATE stall_applications
                    SET status = 'awaiting_meetup',
                        meetup_scheduled_at = ?,
                        meetup_location = ?,
                        meetup_notes = ?,
                        meetup_scheduled_by = ?,
                        meetup_scheduled_email_sent_at = NOW()
                  WHERE id = ?"
            )->execute([$scheduledAt, $place, $notes ?: null, $adminId, $appId]);

            $mailSent = false;
            $mailError = '';
            try {
                $mail = gjc_mailer();
                $mail->addAddress($app['email'], $app['proprietor_name']);
                $mail->Subject = 'GJC EduPay - Stall Application Meet-up Schedule';
                $prettyDate = $dt->format('F j, Y');
                $prettyTime = $dt->format('g:i A');
                $mail->Body = '
                    <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f0fdf4;padding:28px;border-radius:14px">
                        <h2 style="color:#064420;margin-top:0">Meet-up Scheduled</h2>
                        <p style="color:#374151;line-height:1.7">Dear <strong>' . htmlspecialchars($app['proprietor_name']) . '</strong>,</p>
                        <p style="color:#374151;line-height:1.7">Your stall application for <strong>' . htmlspecialchars($app['stall_label'] ?? $app['stall_id']) . '</strong> has passed the document review stage.</p>
                        <div style="background:#fff;border:1px solid #86efac;border-radius:10px;padding:16px;margin:16px 0">
                            <p style="margin:0 0 8px;color:#15803d;font-weight:700;text-transform:uppercase;font-size:12px">Meet-up Details</p>
                            <p style="margin:0;color:#111827"><strong>Date:</strong> ' . htmlspecialchars($prettyDate) . '</p>
                            <p style="margin:4px 0 0;color:#111827"><strong>Time:</strong> ' . htmlspecialchars($prettyTime) . '</p>
                            <p style="margin:4px 0 0;color:#111827"><strong>Location:</strong> ' . htmlspecialchars($place) . '</p>
                        </div>
                        ' . ($notes !== '' ? '<p style="color:#374151;line-height:1.7"><strong>Notes:</strong> ' . nl2br(htmlspecialchars($notes)) . '</p>' : '') . '
                        <p style="font-size:12px;color:#6b7280">GJC EduPay Team</p>
                    </div>';
                $mail->AltBody = "Dear {$app['proprietor_name']},\n\nYour stall application for " . ($app['stall_label'] ?? $app['stall_id']) . " has passed review.\n\nMeet-up schedule:\nDate: {$prettyDate}\nTime: {$prettyTime}\nLocation: {$place}" . ($notes !== '' ? "\nNotes: {$notes}" : '') . "\n\nGJC EduPay Team";
                $mail->send();
                $mailSent = true;
            } catch (Throwable $mailEx) {
                $mailError = $mailEx->getMessage();
            }

            stall_app_json([
                'success' => true,
                'message' => 'Meet-up scheduled. Application moved to Awaiting Meet-up.'
                    . ($mailSent ? ' Email sent.' : ' Note: email failed - ' . $mailError),
            ]);
        }

        case 'record_down_payment': {
            $appId = (int) ($_POST['app_id'] ?? 0);
            $amount = (float) ($_POST['down_payment_amount'] ?? 0);
            $ref = trim((string) ($_POST['down_payment_reference'] ?? ''));
            $notes = trim((string) ($_POST['down_payment_notes'] ?? ''));

            if (!$appId || $amount <= 0) {
                stall_app_json(['success' => false, 'message' => 'Application ID and down payment amount are required.']);
            }

            $stmt = $db->prepare(
                "SELECT * FROM stall_applications
                  WHERE id = ?
                    AND status IN ('awaiting_meetup', 'initially_approved')
                  LIMIT 1"
            );
            $stmt->execute([$appId]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) {
                stall_app_json(['success' => false, 'message' => 'Application not found or not awaiting meet-up.']);
            }

            $db->prepare(
                "UPDATE stall_applications
                    SET status = 'awaiting_approval',
                        down_payment_amount = ?,
                        down_payment_reference = ?,
                        down_payment_notes = ?,
                        down_payment_recorded_by = ?,
                        down_payment_recorded_at = NOW()
                  WHERE id = ?"
            )->execute([$amount, $ref ?: null, $notes ?: null, $adminId, $appId]);

            stall_app_json([
                'success' => true,
                'message' => 'Down payment recorded. Application moved to Awaiting Approval.',
            ]);
        }

        case 'final_approval': {
            $appId = (int) ($_POST['app_id'] ?? 0);
            if (!$appId) {
                stall_app_json(['success' => false, 'message' => 'Invalid application ID.']);
            }

            $stmt = $db->prepare(
                "SELECT sa.*, s.label AS stall_label
                   FROM stall_applications sa
                   LEFT JOIN stalls s ON s.stall_id = sa.stall_id
                  WHERE sa.id = ?
                    AND sa.status IN ('awaiting_approval')
                  LIMIT 1"
            );
            $stmt->execute([$appId]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) {
                stall_app_json(['success' => false, 'message' => 'Application not found or not awaiting approval.']);
            }

            $existing = $db->prepare("SELECT userID FROM users WHERE email = ? LIMIT 1");
            $existing->execute([$app['email']]);
            if ($existing->fetch()) {
                stall_app_json(['success' => false, 'message' => 'A user account already exists for this email.']);
            }

            $tempPassword = stall_app_temp_password();
            $hashedPw = password_hash($tempPassword, PASSWORD_BCRYPT);

            $nameParts = preg_split('/\s+/', trim($app['proprietor_name'])) ?: [];
            $lastName = array_pop($nameParts) ?: trim($app['proprietor_name']);
            $firstName = trim(implode(' ', $nameParts)) ?: $lastName;
            $oldStall = null;
            $newStall = null;

            $db->beginTransaction();
            try {
                $db->prepare(
                    "INSERT INTO users
                         (last_name, first_name, email, contact_number,
                          roleID, sub_role, password, profile_img,
                          force_password_change, is_first_login, password_changed, temp_password)
                     VALUES (?, ?, ?, ?, 2, 'merchant_admin', ?, ?, 1, 1, 0, ?)"
                )->execute([
                    $lastName,
                    $firstName,
                    $app['email'],
                    $app['contact_number'],
                    $hashedPw,
                    $app['profile_picture'],
                    $tempPassword,
                ]);
                $newUserId = (int) $db->lastInsertId();

                $db->prepare(
                    "INSERT INTO merchant (userID, stall_name, stall_id, operational_status)
                     VALUES (?, ?, ?, 'active')"
                )->execute([$newUserId, $app['business_name'], $app['stall_id']]);
                $newMerchantId = (int) $db->lastInsertId();

                $db->prepare(
                    "INSERT IGNORE INTO merchant_wallets (user_id, balance) VALUES (?, 0.00)"
                )->execute([$newUserId]);

                $stallStmt = $db->prepare("SELECT * FROM stalls WHERE stall_id = ? LIMIT 1");
                $stallStmt->execute([$app['stall_id']]);
                $oldStall = $stallStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                $db->prepare(
                    "UPDATE stalls
                        SET status = 'occupied',
                            merchant_id = ?,
                            pending_expires_at = NULL
                      WHERE stall_id = ?"
                )->execute([$newMerchantId, $app['stall_id']]);
                $newStall = $oldStall ?: ['stall_id' => $app['stall_id']];
                $newStall['status'] = 'occupied';
                $newStall['merchant_id'] = $newMerchantId;
                $newStall['pending_expires_at'] = null;

                $db->prepare(
                    "UPDATE stall_applications
                        SET status = 'active',
                            reviewed_by = ?,
                            reviewed_at = NOW(),
                            merchant_user_id = ?,
                            temp_password_plain = ?
                      WHERE id = ?"
                )->execute([$adminId, $newUserId, $tempPassword, $appId]);

                if (gjc_table_exists($db, 'merchant_accounts')) {
                    $db->prepare(
                        "INSERT INTO merchant_accounts
                             (application_id, user_id, temp_password_plain, created_by)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                             user_id = VALUES(user_id),
                             temp_password_plain = VALUES(temp_password_plain),
                             created_by = VALUES(created_by)"
                    )->execute([$appId, $newUserId, $tempPassword, $adminId]);
                }

                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                stall_app_json(['success' => false, 'message' => 'Final approval failed: ' . $e->getMessage()]);
            }

            logAudit(
                $db,
                $adminId,
                gjc_current_role(),
                'STALL_UPDATE',
                'stalls',
                $oldStall,
                $newStall,
                $app['stall_id']
            );

            $mailSent = false;
            $mailError = '';
            try {
                $mail = gjc_mailer();
                $mail->addAddress($app['email'], $app['proprietor_name']);
                $mail->Subject = 'GJC EduPay - Merchant Account Credentials';
                $mail->Body = '
                    <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f0fdf4;padding:28px;border-radius:14px">
                        <h2 style="color:#064420;margin-top:0">Your Merchant Account Is Approved</h2>
                        <p style="color:#374151;line-height:1.7">Dear <strong>' . htmlspecialchars($app['proprietor_name']) . '</strong>,</p>
                        <p style="color:#374151;line-height:1.7">Your stall application for <strong>' . htmlspecialchars($app['stall_label'] ?? $app['stall_id']) . '</strong> has been approved.</p>
                        <div style="background:#052e16;border-radius:10px;padding:16px;margin:16px 0;color:#dcfce7">
                            <p style="margin:0 0 8px;font-size:12px;font-weight:700;color:#86efac;text-transform:uppercase">Login Credentials</p>
                            <p style="margin:0"><strong>Email:</strong> ' . htmlspecialchars($app['email']) . '</p>
                            <p style="margin:6px 0 0"><strong>Temporary Password:</strong> ' . htmlspecialchars($tempPassword) . '</p>
                        </div>
                        <p style="color:#b91c1c;font-weight:700">You must change this password on first login before accessing your dashboard.</p>
                        <p style="color:#374151">Login page: <a href="' . BASE_URL . '/login" style="color:#15803d">' . BASE_URL . '/login</a></p>
                    </div>';
                $mail->AltBody = "Dear {$app['proprietor_name']},\n\nYour merchant account is approved.\n\nEmail: {$app['email']}\nTemporary Password: {$tempPassword}\n\nLog in at " . BASE_URL . "/login. You must change your password on first login.\n\nGJC EduPay Team";
                $mail->send();
                $mailSent = true;
            } catch (Throwable $mailEx) {
                $mailError = $mailEx->getMessage();
            }

            stall_app_json([
                'success' => true,
                'message' => "Application approved. Merchant account created for {$app['proprietor_name']}."
                    . ($mailSent ? ' Credentials emailed.' : ' Note: email failed - ' . $mailError),
                'user_id' => $newUserId,
                'temp_password' => $tempPassword,
                'mail_sent' => $mailSent,
            ]);
        }

        case 'reject': {
            $appId  = (int) ($_POST['app_id'] ?? 0);
            $reason = trim((string) ($_POST['rejection_reason'] ?? ''));

            if (!$appId || !$reason) {
                stall_app_json(['success' => false, 'message' => 'Application ID and rejection reason are required.']);
            }

            $stmt = $db->prepare("SELECT * FROM stall_applications WHERE id = ? LIMIT 1");
            $stmt->execute([$appId]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) {
                stall_app_json(['success' => false, 'message' => 'Application not found.']);
            }
            if ($app['status'] === 'active') {
                stall_app_json(['success' => false, 'message' => 'Cannot reject an active application.']);
            }

            $stallStmt = $db->prepare("SELECT * FROM stalls WHERE stall_id = ? LIMIT 1");
            $stallStmt->execute([$app['stall_id']]);
            $oldStall = $stallStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $db->prepare(
                "UPDATE stall_applications
                    SET status = 'rejected',
                        rejection_reason = ?,
                        reviewed_by = ?,
                        reviewed_at = NOW()
                  WHERE id = ?"
            )->execute([$reason, $adminId, $appId]);

            $db->prepare(
                "UPDATE stalls
                    SET status = 'vacant',
                        pending_expires_at = NULL
                  WHERE stall_id = ?
                    AND status != 'occupied'"
            )->execute([$app['stall_id']]);

            $newStall = $oldStall ?: ['stall_id' => $app['stall_id']];
            if (($oldStall['status'] ?? '') !== 'occupied') {
                $newStall['status'] = 'vacant';
                $newStall['pending_expires_at'] = null;
            }
            logAudit(
                $db,
                $adminId,
                gjc_current_role(),
                'STALL_UPDATE',
                'stalls',
                $oldStall,
                $newStall,
                $app['stall_id']
            );

            try {
                $mail = gjc_mailer();
                $mail->addAddress($app['email'], $app['proprietor_name']);
                $mail->Subject = 'GJC EduPay - Stall Application Update';
                $mail->Body = '
                    <div style="font-family:Arial,sans-serif;max-width:540px;margin:0 auto;padding:28px;background:#fff1f2;border-radius:14px">
                        <h3 style="color:#b91c1c">Dear ' . htmlspecialchars($app['proprietor_name']) . ',</h3>
                        <p style="color:#374151;line-height:1.7">After review, your stall application has not been approved at this time.</p>
                        <div style="background:#fff;border:1px solid #fca5a5;border-radius:10px;padding:14px;margin:14px 0">
                            <p style="margin:0 0 4px;font-size:11px;font-weight:700;color:#b91c1c;text-transform:uppercase">Reason</p>
                            <p style="margin:0;color:#374151">' . htmlspecialchars($reason) . '</p>
                        </div>
                        <p style="font-size:12px;color:#9ca3af">GJC EduPay Team</p>
                    </div>';
                $mail->AltBody = "Dear {$app['proprietor_name']},\n\nYour stall application was not approved.\n\nReason: {$reason}\n\nGJC EduPay Team";
                $mail->send();
            } catch (Throwable $ignored) {
            }

            stall_app_json(['success' => true, 'message' => 'Application rejected and applicant notified.']);
        }

        default:
            stall_app_json(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    stall_app_json(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
