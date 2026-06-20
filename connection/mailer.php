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
