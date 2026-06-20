<?php
// ============================================================
//  admin/api/stall_applications.php
//  JSON API for the unified 4-step stall application pipeline.
//  Source requirement: adviser feedback session (SIR EMMAN 4.mp3)
//
//  Pipeline: review -> meeting -> down_payment -> approval -> active
//  Actions:
//    accept_review   Step 1 -> Step 2   (docs accepted)
//    decline         Step 1 or 2 only   (archives to archived_rejections)
//    save_meeting    Step 2 -> Step 3   (saves meeting details)
//    save_down_payment Step 3 -> Step 4 (saves down payment record)
//    award_stall     Step 4 -> active   (assigns stall, creates merchant)
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
gjc_ensure_archived_rejections_schema($db);
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

/** Fetch one application row by id, or null. */
function stall_app_fetch(PDO $db, int $appId): ?array
{
    $stmt = $db->prepare("SELECT * FROM stall_applications WHERE id = ? LIMIT 1");
    $stmt->execute([$appId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    switch ($action) {

        // ── Step 1: Review Requirements — Accept ──────────────
        case 'accept_review': {
            $appId = (int) ($_POST['app_id'] ?? 0);
            $app = $appId ? stall_app_fetch($db, $appId) : null;
            if (!$app || $app['status'] !== 'review') {
                stall_app_json(['success' => false, 'message' => 'Application not found or not at the Review step.']);
            }

            $db->prepare(
                "UPDATE stall_applications
                    SET status = 'meeting', current_step = 2,
                        reviewed_by = ?, reviewed_at = NOW()
                  WHERE id = ?"
            )->execute([$adminId, $appId]);

            stall_app_json([
                'success' => true,
                'message' => 'Documents accepted. Moved to Step 2 - Meeting Schedule.',
                'status' => 'meeting', 'current_step' => 2,
            ]);
        }

        // ── Step 1 or 2: Decline -> archive ────────────────────
        case 'decline': {
            $appId  = (int) ($_POST['app_id'] ?? 0);
            $reason = trim((string) ($_POST['rejection_reason'] ?? ''));
            $app = $appId ? stall_app_fetch($db, $appId) : null;

            if (!$app || !in_array($app['status'], ['review', 'meeting'], true)) {
                stall_app_json(['success' => false, 'message' => 'Decline is only available during Step 1 (Review) or Step 2 (Meeting).']);
            }
            if (!$reason) {
                stall_app_json(['success' => false, 'message' => 'A decline reason is required.']);
            }

            $db->beginTransaction();
            try {
                $db->prepare(
                    "INSERT INTO archived_rejections
                        (original_application_id, rejected_at_step, business_name, proprietor_name,
                         contact_number, email, profile_picture, business_permit, sanitary_permit,
                         gjc_requirements, clearance, rejection_reason, rejected_by, rejected_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())"
                )->execute([
                    $app['id'], $app['current_step'], $app['business_name'], $app['proprietor_name'],
                    $app['contact_number'], $app['email'], $app['profile_picture'], $app['business_permit'],
                    $app['sanitary_permit'], $app['gjc_requirements'], $app['clearance'], $reason, $adminId,
                ]);
                $db->prepare("DELETE FROM stall_applications WHERE id = ?")->execute([$appId]);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                stall_app_json(['success' => false, 'message' => 'Decline failed: ' . $e->getMessage()]);
            }

            try {
                $mail = gjc_mailer();
                $mail->addAddress($app['email'], $app['proprietor_name']);
                $mail->Subject = 'GenPay - Stall Application Update';
                $mail->Body = '
                    <div style="font-family:Arial,sans-serif;max-width:540px;margin:0 auto;padding:28px;background:#fff1f2;border-radius:14px">
                        <h3 style="color:#b91c1c">Dear ' . htmlspecialchars($app['proprietor_name']) . ',</h3>
                        <p style="color:#374151;line-height:1.7">After review, your stall application has not been approved at this time.</p>
                        <div style="background:#fff;border:1px solid #fca5a5;border-radius:10px;padding:14px;margin:14px 0">
                            <p style="margin:0 0 4px;font-size:11px;font-weight:700;color:#b91c1c;text-transform:uppercase">Reason</p>
                            <p style="margin:0;color:#374151">' . htmlspecialchars($reason) . '</p>
                        </div>
                        <p style="font-size:12px;color:#9ca3af">GenPay Team</p>
                    </div>';
                $mail->AltBody = "Dear {$app['proprietor_name']},\n\nYour stall application was declined.\n\nReason: {$reason}\n\nGenPay Team";
                $mail->send();
            } catch (Throwable $ignored) {
            }

            stall_app_json([
                'success' => true,
                'message' => 'Application declined and archived.',
            ]);
        }

        // ── Step 2: Meeting Schedule — save & advance ──────────
        case 'save_meeting': {
            $appId = (int) ($_POST['app_id'] ?? 0);
            $date  = trim((string) ($_POST['meetup_date'] ?? ''));
            $time  = trim((string) ($_POST['meetup_time'] ?? ''));
            $place = trim((string) ($_POST['meetup_location'] ?? ''));
            $notes = trim((string) ($_POST['meetup_notes'] ?? ''));

            $app = $appId ? stall_app_fetch($db, $appId) : null;
            if (!$app || $app['status'] !== 'meeting') {
                stall_app_json(['success' => false, 'message' => 'Application not found or not at the Meeting step.']);
            }
            if (!$date || !$time || !$place) {
                stall_app_json(['success' => false, 'message' => 'Date, time, and location are required.']);
            }

            $scheduledAt = $date . ' ' . $time . ':00';
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt);
            if (!$dt || $dt->format('Y-m-d H:i:s') !== $scheduledAt) {
                stall_app_json(['success' => false, 'message' => 'Invalid meeting date or time.']);
            }

            $db->prepare(
                "UPDATE stall_applications
                    SET status = 'down_payment', current_step = 3,
                        meetup_scheduled_at = ?, meetup_location = ?, meetup_notes = ?,
                        meetup_scheduled_by = ?, meetup_scheduled_email_sent_at = NOW()
                  WHERE id = ?"
            )->execute([$scheduledAt, $place, $notes ?: null, $adminId, $appId]);

            $mailSent = false;
            $mailError = '';
            try {
                $mail = gjc_mailer();
                $mail->addAddress($app['email'], $app['proprietor_name']);
                $mail->Subject = 'GenPay - Stall Application Meeting Schedule';
                $prettyDate = $dt->format('F j, Y');
                $prettyTime = $dt->format('g:i A');
                $mail->Body = '
                    <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f0fdf4;padding:28px;border-radius:14px">
                        <h2 style="color:#064420;margin-top:0">Meeting Scheduled</h2>
                        <p style="color:#374151;line-height:1.7">Dear <strong>' . htmlspecialchars($app['proprietor_name']) . '</strong>,</p>
                        <p style="color:#374151;line-height:1.7">Your stall application for <strong>' . htmlspecialchars($app['business_name']) . '</strong> has passed the document review stage.</p>
                        <div style="background:#fff;border:1px solid #86efac;border-radius:10px;padding:16px;margin:16px 0">
                            <p style="margin:0 0 8px;color:#15803d;font-weight:700;text-transform:uppercase;font-size:12px">Meeting Details</p>
                            <p style="margin:0;color:#111827"><strong>Date:</strong> ' . htmlspecialchars($prettyDate) . '</p>
                            <p style="margin:4px 0 0;color:#111827"><strong>Time:</strong> ' . htmlspecialchars($prettyTime) . '</p>
                            <p style="margin:4px 0 0;color:#111827"><strong>Location:</strong> ' . htmlspecialchars($place) . '</p>
                        </div>
                        ' . ($notes !== '' ? '<p style="color:#374151;line-height:1.7"><strong>Notes:</strong> ' . nl2br(htmlspecialchars($notes)) . '</p>' : '') . '
                        <p style="font-size:12px;color:#6b7280">GenPay Team</p>
                    </div>';
                $mail->AltBody = "Dear {$app['proprietor_name']},\n\nYour stall application has passed review.\n\nMeeting schedule:\nDate: {$prettyDate}\nTime: {$prettyTime}\nLocation: {$place}" . ($notes !== '' ? "\nNotes: {$notes}" : '') . "\n\nGenPay Team";
                $mail->send();
                $mailSent = true;
            } catch (Throwable $mailEx) {
                $mailError = $mailEx->getMessage();
            }

            stall_app_json([
                'success' => true,
                'message' => 'Meeting saved. Moved to Step 3 - Down Payment.' . ($mailSent ? ' Email sent.' : ' Note: email failed - ' . $mailError),
                'status' => 'down_payment', 'current_step' => 3,
            ]);
        }

        // ── Step 3: Down Payment — save & advance ──────────────
        case 'save_down_payment': {
            $appId  = (int) ($_POST['app_id'] ?? 0);
            $amount = (float) ($_POST['down_payment_amount'] ?? 0);
            $ref    = trim((string) ($_POST['down_payment_reference'] ?? ''));
            $notes  = trim((string) ($_POST['down_payment_notes'] ?? ''));

            $app = $appId ? stall_app_fetch($db, $appId) : null;
            if (!$app || $app['status'] !== 'down_payment') {
                stall_app_json(['success' => false, 'message' => 'Application not found or not at the Down Payment step.']);
            }
            if ($amount <= 0) {
                stall_app_json(['success' => false, 'message' => 'A valid down payment amount is required.']);
            }

            $db->prepare(
                "UPDATE stall_applications
                    SET status = 'approval', current_step = 4,
                        down_payment_amount = ?, down_payment_reference = ?, down_payment_notes = ?,
                        down_payment_recorded_by = ?, down_payment_recorded_at = NOW()
                  WHERE id = ?"
            )->execute([$amount, $ref ?: null, $notes ?: null, $adminId, $appId]);

            stall_app_json([
                'success' => true,
                'message' => 'Down payment recorded. Moved to Step 4 - Approval / Award.',
                'status' => 'approval', 'current_step' => 4,
            ]);
        }

        // ── Step 4: Approval / Award — assign stall & finalize ─
        case 'award_stall': {
            $appId   = (int) ($_POST['app_id'] ?? 0);
            $stallId = strtoupper(trim((string) ($_POST['stall_id'] ?? '')));

            $app = $appId ? stall_app_fetch($db, $appId) : null;
            if (!$app || $app['status'] !== 'approval') {
                stall_app_json(['success' => false, 'message' => 'Application not found or not at the Approval step.']);
            }
            if (!preg_match('/^[A-Z]\d+$/', $stallId)) {
                stall_app_json(['success' => false, 'message' => 'Please select a stall to award.']);
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
                // Lock and re-validate the chosen stall is still vacant.
                $stallStmt = $db->prepare("SELECT * FROM stalls WHERE stall_id = ? FOR UPDATE");
                $stallStmt->execute([$stallId]);
                $oldStall = $stallStmt->fetch(PDO::FETCH_ASSOC);
                if (!$oldStall) {
                    throw new RuntimeException('STALL_NOT_FOUND');
                }
                if ($oldStall['status'] !== 'vacant') {
                    throw new RuntimeException('STALL_TAKEN');
                }

                $db->prepare(
                    "INSERT INTO users
                         (last_name, first_name, email, contact_number,
                          roleID, sub_role, password, profile_img,
                          force_password_change, is_first_login, password_changed, temp_password)
                     VALUES (?, ?, ?, ?, 2, 'merchant_admin', ?, '', 1, 1, 0, ?)"
                )->execute([
                    $lastName,
                    $firstName,
                    $app['email'],
                    $app['contact_number'],
                    $hashedPw,
                    $tempPassword,
                ]);
                $newUserId = (int) $db->lastInsertId();

                $db->prepare(
                    "INSERT INTO merchant (userID, stall_name, stall_id, operational_status)
                     VALUES (?, ?, ?, 'active')"
                )->execute([$newUserId, $app['business_name'], $stallId]);
                $newMerchantId = (int) $db->lastInsertId();

                // The submitted profile picture lives under /uploads, which is blocked
                // from direct public access (.htaccess: "served through PHP only").
                // Copy it into /assets/merchant_logos so it can render on the public
                // stall directory as the tenant's company logo.
                if ($app['profile_picture'] && $app['profile_picture'] !== 'pending_path') {
                    $srcPath = BASE_PATH . '/' . $app['profile_picture'];
                    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION)) ?: 'jpg';
                    $logoDir = BASE_PATH . '/assets/merchant_logos';
                    if (!is_dir($logoDir)) {
                        mkdir($logoDir, 0755, true);
                    }
                    $logoRelPath = 'assets/merchant_logos/' . $newMerchantId . '.' . $ext;
                    if (is_file($srcPath) && copy($srcPath, BASE_PATH . '/' . $logoRelPath)) {
                        $db->prepare("UPDATE users SET profile_img = ? WHERE userID = ?")
                           ->execute([$logoRelPath, $newUserId]);
                    }
                }

                $db->prepare(
                    "INSERT IGNORE INTO merchant_wallets (user_id, balance) VALUES (?, 0.00)"
                )->execute([$newUserId]);

                $db->prepare(
                    "UPDATE stalls
                        SET status = 'occupied', merchant_id = ?, pending_expires_at = NULL
                      WHERE stall_id = ?"
                )->execute([$newMerchantId, $stallId]);
                $newStall = $oldStall;
                $newStall['status'] = 'occupied';
                $newStall['merchant_id'] = $newMerchantId;
                $newStall['pending_expires_at'] = null;

                $db->prepare(
                    "UPDATE stall_applications
                        SET stall_id = ?, status = 'active',
                            reviewed_by = ?, reviewed_at = NOW(),
                            merchant_user_id = ?, temp_password_plain = ?
                      WHERE id = ?"
                )->execute([$stallId, $adminId, $newUserId, $tempPassword, $appId]);

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
                $msg = match ($e->getMessage()) {
                    'STALL_NOT_FOUND' => 'The selected stall does not exist.',
                    'STALL_TAKEN' => 'The selected stall was just taken. Please choose a different stall.',
                    default => 'Award failed: ' . $e->getMessage(),
                };
                stall_app_json(['success' => false, 'message' => $msg]);
            }

            logAudit($db, $adminId, gjc_current_role(), 'STALL_UPDATE', 'stalls', $oldStall, $newStall, $stallId);

            $mailSent = false;
            $mailError = '';
            try {
                $mail = gjc_mailer();
                $mail->addAddress($app['email'], $app['proprietor_name']);
                $mail->Subject = 'GenPay - Merchant Account Credentials';
                $mail->Body = '
                    <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f0fdf4;padding:28px;border-radius:14px">
                        <h2 style="color:#064420;margin-top:0">Your Merchant Account Is Approved</h2>
                        <p style="color:#374151;line-height:1.7">Dear <strong>' . htmlspecialchars($app['proprietor_name']) . '</strong>,</p>
                        <p style="color:#374151;line-height:1.7">Your stall application has been approved and awarded <strong>Stall ' . htmlspecialchars($stallId) . '</strong>.</p>
                        <div style="background:#052e16;border-radius:10px;padding:16px;margin:16px 0;color:#dcfce7">
                            <p style="margin:0 0 8px;font-size:12px;font-weight:700;color:#86efac;text-transform:uppercase">Login Credentials</p>
                            <p style="margin:0"><strong>Email:</strong> ' . htmlspecialchars($app['email']) . '</p>
                            <p style="margin:6px 0 0"><strong>Temporary Password:</strong> ' . htmlspecialchars($tempPassword) . '</p>
                        </div>
                        <p style="color:#b91c1c;font-weight:700">You must change this password on first login before accessing your dashboard.</p>
                        <p style="color:#374151">Login page: <a href="' . BASE_URL . '/login" style="color:#15803d">' . BASE_URL . '/login</a></p>
                    </div>';
                $mail->AltBody = "Dear {$app['proprietor_name']},\n\nYour merchant account is approved for Stall {$stallId}.\n\nEmail: {$app['email']}\nTemporary Password: {$tempPassword}\n\nLog in at " . BASE_URL . "/login. You must change your password on first login.\n\nGenPay Team";
                $mail->send();
                $mailSent = true;
            } catch (Throwable $mailEx) {
                $mailError = $mailEx->getMessage();
            }

            stall_app_json([
                'success' => true,
                'message' => "Application awarded Stall {$stallId}. Merchant account created for {$app['proprietor_name']}."
                    . ($mailSent ? ' Credentials emailed.' : ' Note: email failed - ' . $mailError),
                'status' => 'active', 'current_step' => 4,
                'user_id' => $newUserId,
                'temp_password' => $tempPassword,
                'mail_sent' => $mailSent,
            ]);
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
