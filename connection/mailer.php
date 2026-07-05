<?php
/**
 * GenPay — Mailer Helper
 * Wraps PHPMailer with project SMTP credentials.
 * Usage:
 *   $mail = gjc_mailer();
 *   $mail->addAddress($email, $name);
 *   $mail->Subject = '...';
 *   $mail->Body    = '...';
 *   $mail->send();
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';

function gjc_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'daitodump@gmail.com';
    $mail->Password   = 'ogfj biau oeyr ntab';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 10;
    $mail->setFrom('daitodump@gmail.com', 'GenPay');
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    return $mail;
}

/**
 * Sends the one-stop stall application confirmation: the application was
 * received and a verification meeting has been auto-scheduled. Mirrors the
 * existing "Meeting Scheduled" template and adds the mandatory reminder to
 * bring the ORIGINAL documents to the meeting.
 *
 * Shared by the public submission form (apply.php). Never throws — returns a
 * ['sent' => bool, 'error' => string] result so the caller can record whether
 * the confirmation email went out.
 *
 * @return array{sent:bool,error:string}
 */
function gjc_send_stall_meeting_email(
    string $toEmail,
    string $toName,
    string $businessName,
    DateTime $meetingAt,
    string $location,
    string $notes = ''
): array {
    $prettyDate = $meetingAt->format('l, F j, Y');
    $prettyTime = $meetingAt->format('g:i A');
    $safeName   = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeBiz    = htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8');
    $safePlace  = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');

    try {
        $mail = gjc_mailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'GenPay - Stall Application Received & Meeting Scheduled';
        $mail->Body = '
            <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f0fdf4;padding:28px;border-radius:14px">
                <h2 style="color:#064420;margin-top:0">Application Received - Meeting Scheduled</h2>
                <p style="color:#374151;line-height:1.7">Dear <strong>' . $safeName . '</strong>,</p>
                <p style="color:#374151;line-height:1.7">Your stall application for <strong>' . $safeBiz . '</strong> has been received. A verification meeting has been scheduled for you. Everything - document verification, contract signing, and payment - will be handled in this one meeting.</p>
                <div style="background:#fff;border:1px solid #bbf7d0;border-radius:10px;padding:16px;margin:16px 0">
                    <p style="margin:0 0 8px;color:#059669;font-weight:700;text-transform:uppercase;font-size:12px">Meeting Details</p>
                    <p style="margin:0;color:#111827"><strong>Date:</strong> ' . htmlspecialchars($prettyDate, ENT_QUOTES, 'UTF-8') . '</p>
                    <p style="margin:4px 0 0;color:#111827"><strong>Time:</strong> ' . htmlspecialchars($prettyTime, ENT_QUOTES, 'UTF-8') . '</p>
                    <p style="margin:4px 0 0;color:#111827"><strong>Location:</strong> ' . $safePlace . '</p>
                </div>
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px;margin:16px 0">
                    <p style="margin:0;color:#92400e;line-height:1.6"><strong>Important:</strong> Please bring the <strong>original copies</strong> of all documents you uploaded (business permit, sanitary permit, GJC requirements, and clearance) so they can be verified against your submission.</p>
                </div>
                ' . ($notes !== '' ? '<p style="color:#374151;line-height:1.7"><strong>Notes:</strong> ' . nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')) . '</p>' : '') . '
                <p style="color:#374151;line-height:1.7">If you cannot attend, your application will be cancelled and you are welcome to submit a new one.</p>
                <p style="font-size:12px;color:#6b7280">GenPay Team</p>
            </div>';
        $mail->AltBody = "Dear {$toName},\n\n"
            . "Your stall application for {$businessName} has been received and a verification meeting has been scheduled.\n\n"
            . "Meeting schedule:\nDate: {$prettyDate}\nTime: {$prettyTime}\nLocation: {$location}\n\n"
            . "IMPORTANT: Please bring the ORIGINAL copies of all uploaded documents (business permit, sanitary permit, GJC requirements, and clearance) for verification."
            . ($notes !== '' ? "\n\nNotes: {$notes}" : '')
            . "\n\nIf you cannot attend, your application will be cancelled and you may submit a new one.\n\nGenPay Team";
        $mail->send();
        return ['sent' => true, 'error' => ''];
    } catch (Throwable $e) {
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}
