<?php
require_once 'config.php';
require_once 'security.php';
require_once __DIR__ . '/includes/phpmailer/src/Exception.php';
require_once __DIR__ . '/includes/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/includes/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$env = parse_ini_file(__DIR__ . '/.env');

if (!$env) {
    error_log("Failed to load .env file");
    die("Configuration error. Please contact support.");
}

define('TELERIVET_API_KEY', $env['TELERIVET_API_KEY']);
define('TELERIVET_PROJECT_ID', $env['TELERIVET_PROJECT_ID']);
define('TELERIVET_PHONE_ID', $env['TELERIVET_PHONE_ID']);

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USERNAME', $env['MAIL_USERNAME']);
define('MAIL_PASSWORD', $env['MAIL_PASSWORD']);
define('MAIL_PORT', 587);
define('MAIL_FROM', $env['MAIL_USERNAME']);
define('MAIL_FROM_NAME', 'SecureBank Inc.');

// Create a secure OTP.
function generate_otp()
{
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// Store the OTP and invalidate older codes.
function store_otp($user_id, $otp)
{
    global $pdo;

    $stmt = $pdo->prepare("
        UPDATE mfa_codes SET is_used = TRUE
        WHERE user_id = ? AND is_used = FALSE
    ");
    $stmt->execute([$user_id]);

    $stmt = $pdo->prepare("
        INSERT INTO mfa_codes (user_id, otp_code, expires_at, is_used)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), FALSE)
    ");
    $stmt->execute([$user_id, $otp, OTP_EXPIRY_MINUTES]);

    return true;
}

// Verify the OTP and enforce attempt limits.
function verify_otp($user_id, $submitted_otp)
{
    global $pdo;

    if (!isset($_SESSION['otp_attempts'])) {
        $_SESSION['otp_attempts'] = 0;
    }

    if ($_SESSION['otp_attempts'] >= OTP_MAX_ATTEMPTS) {
        return ['success' => false, 'message' => 'Too many failed attempts. Please log in again.', 'lockout' => true];
    }

    $stmt = $pdo->prepare("
        SELECT * FROM mfa_codes
        WHERE user_id = ?
          AND is_used = FALSE
          AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $record = $stmt->fetch();

    if (!$record) {
        $_SESSION['otp_attempts']++;
        return ['success' => false, 'message' => 'OTP has expired or is invalid. Please request a new one.'];
    }

    if ($record['otp_code'] !== $submitted_otp) {
        $_SESSION['otp_attempts']++;
        $remaining = OTP_MAX_ATTEMPTS - $_SESSION['otp_attempts'];
        return ['success' => false, 'message' => "Incorrect OTP. $remaining attempt(s) remaining."];
    }

    $stmt = $pdo->prepare("UPDATE mfa_codes SET is_used = TRUE WHERE id = ?");
    $stmt->execute([$record['id']]);

    unset($_SESSION['otp_attempts']);

    return ['success' => true, 'message' => 'OTP verified successfully.'];
}

// Send an OTP by email.
function send_otp_email($email, $otp)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'SecureBank - Your One-Time Password';
        $mail->Body = '
                <div style="font-family: Arial, sans-serif; max-width: 500px; margin: auto; border: 1px solid #ddd;">
                    <div style="background: #CC0000; padding: 20px; text-align: center;">
                        <h2 style="color: #ffffff; margin: 0; font-size: 20px;">SecureBank Inc.</h2>
                        <p style="color: rgba(255,255,255,0.8); margin: 4px 0 0; font-size: 13px;">Online Banking Portal</p>
                    </div>
                    <div style="padding: 30px; background: #fff;">
                        <p style="font-size: 14px; color: #222;">Hello,</p>
                        <p style="font-size: 14px; color: #222;">Your One-Time Password (OTP) for SecureBank login is:</p>
                        <div style="background: #f5f5f5; border-left: 4px solid #CC0000; padding: 16px; text-align: center; margin: 20px 0;">
                            <span style="font-size: 36px; font-weight: bold; letter-spacing: 10px; color: #CC0000;">' . $otp . '</span>
                        </div>
                        <p style="font-size: 13px; color: #666;">This code expires in <strong>' . OTP_EXPIRY_MINUTES . ' minutes</strong>. Do not share this with anyone.</p>
                        <p style="font-size: 13px; color: #999;">If you did not request this, please contact SecureBank support immediately.</p>
                    </div>
                    <div style="background: #f5f5f5; padding: 12px; text-align: center; font-size: 11px; color: #999;">
                        &copy; ' . date('Y') . ' SecureBank Inc. All rights reserved.
                    </div>
                </div>
            ';
        $mail->AltBody = "Your SecureBank OTP is: $otp. Valid for " . OTP_EXPIRY_MINUTES . " minutes.";

        $mail->send();
        return ['success' => true, 'message' => 'OTP sent to your email.'];

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Failed to send email OTP. Please try again.'];
    }
}

// Send an OTP by SMS.
function send_otp_sms($phone_number, $otp)
{
    $message = "Your SecureBank OTP: $otp. Valid for " . OTP_EXPIRY_MINUTES . " minutes. Do not share this code.";

    $url = "https://api.telerivet.com/v1/projects/" . TELERIVET_PROJECT_ID . "/messages/send";
    $payload = json_encode([
        'to_number' => $phone_number,
        'content' => $message,
        'phone_id' => TELERIVET_PHONE_ID,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ],
        CURLOPT_USERPWD => TELERIVET_API_KEY . ':',
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 || $http_code === 201) {
        return ['success' => true, 'message' => 'OTP sent to your phone via SMS.'];
    } else {
        error_log("Telerivet Error: HTTP $http_code — $response");
        return ['success' => false, 'message' => 'Failed to send SMS OTP. Please try again.'];
    }
}

// Generate and send an OTP.
function send_otp($user_id, $method, $email, $phone)
{
    $otp = generate_otp();
    $stored = store_otp($user_id, $otp);

    if (!$stored) {
        return ['success' => false, 'message' => 'Failed to generate OTP. Please try again.'];
    }

    if ($method === 'email') {
        return send_otp_email($email, $otp);
    } elseif ($method === 'sms') {
        return send_otp_sms($phone, $otp);
    } else {
        return ['success' => false, 'message' => 'Invalid MFA method.'];
    }
}