<?php
// PHPMailer configuration
// Install: composer require phpmailer/phpmailer
// Or download from: https://github.com/PHPMailer/PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEnrollmentEmail($to_email, $to_name, $subject, $body_html) {
  // Check if PHPMailer is available
  $autoload = __DIR__ . '/../vendor/autoload.php';
  if (!file_exists($autoload)) {
    // PHPMailer not installed — log and skip silently
    error_log("PHPMailer not installed. Email to $to_email skipped.");
    return false;
  }

  require_once $autoload;

  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = getenv('MAIL_HOST')     ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('MAIL_USER')     ?: '';
    $mail->Password   = getenv('MAIL_PASS')     ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = intval(getenv('MAIL_PORT') ?: 587);

    $mail->setFrom(
      getenv('MAIL_FROM') ?: 'noreply@coj.edu.ph',
      'COJ Enrollment System'
    );
    $mail->addAddress($to_email, $to_name);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = emailTemplate($subject, $body_html);
    $mail->AltBody = strip_tags($body_html);

    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log("Email failed to $to_email: " . $mail->ErrorInfo);
    return false;
  }
}

function emailTemplate($title, $content) {
  return '<!DOCTYPE html><html><head><meta charset="UTF-8">
  <style>
    body { font-family: Inter, sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
    .wrap { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
    .header { background: #494C8A; padding: 28px 32px; text-align: center; }
    .header img { width: 52px; height: 52px; }
    .header h1 { color: #fff; font-size: 16px; margin: 10px 0 0; }
    .body { padding: 32px; color: #374151; font-size: 14px; line-height: 1.7; }
    .footer { background: #f9fafb; padding: 16px 32px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
  </style></head><body>
  <div class="wrap">
    <div class="header">
      <h1>COJ Catholic Progressive School</h1>
      <div style="color:rgba(255,255,255,.7);font-size:12px;margin-top:4px;">Enrollment System</div>
    </div>
    <div class="body">' . $content . '</div>
    <div class="footer">COJ Catholic Progressive School · Enrollment System<br>This is an automated message. Please do not reply.</div>
  </div></body></html>';
}
