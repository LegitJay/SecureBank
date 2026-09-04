<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

/*
|--------------------------------------------------------------------------
| LOAD PHPMailer
|--------------------------------------------------------------------------
*/

$composerAutoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($composerAutoload)) {

    require_once $composerAutoload;

} else {

    $phpMailerPath = __DIR__ . '/includes/PHPMailer/src/';

    if (
        file_exists($phpMailerPath . 'Exception.php') &&
        file_exists($phpMailerPath . 'PHPMailer.php') &&
        file_exists($phpMailerPath . 'SMTP.php')
    ) {
        require_once $phpMailerPath . 'Exception.php';
        require_once $phpMailerPath . 'PHPMailer.php';
        require_once $phpMailerPath . 'SMTP.php';
    }
}

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;


/*
|--------------------------------------------------------------------------
| GENERATE OTP
|--------------------------------------------------------------------------
|
| Generates a cryptographically secure six-digit OTP.
|
*/

function generate_otp(): string
{
    return (string) random_int(100000, 999999);
}


/*
|--------------------------------------------------------------------------
| STORE OTP
|--------------------------------------------------------------------------
|
| Invalidates previous unused OTPs before storing the new one.
|
*/

function store_otp(
    int $userId,
    string $otp
): bool {

    global $pdo;

    /*
    |--------------------------------------------------------------------------
    | Invalidate previous unused OTPs
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'UPDATE mfa_codes
         SET is_used = TRUE
         WHERE user_id = ?
         AND is_used = FALSE'
    );

    $stmt->execute([
        $userId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Calculate expiration time
    |--------------------------------------------------------------------------
    */

    $expiresAt = date(
        'Y-m-d H:i:s',
        time() + OTP_EXPIRATION
    );


    /*
    |--------------------------------------------------------------------------
    | Insert new OTP
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'INSERT INTO mfa_codes
        (
            user_id,
            otp_code,
            expires_at,
            is_used
        )
        VALUES (?, ?, ?, FALSE)'
    );

    return $stmt->execute([
        $userId,
        $otp,
        $expiresAt
    ]);
}


/*
|--------------------------------------------------------------------------
| GET PENDING MFA USER
|--------------------------------------------------------------------------
|
| Retrieves the user who is currently waiting for MFA verification.
|
*/

function get_pending_mfa_user(): ?array
{
    global $pdo;

    if (!isset($_SESSION['mfa_pending_user_id'])) {
        return null;
    }

    $userId = (int) $_SESSION['mfa_pending_user_id'];

    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT
            id,
            username,
            email,
            phone_number,
            is_mfa_enabled,
            mfa_method
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $userId
    ]);

    $user = $stmt->fetch();

    return $user ?: null;
}


/*
|--------------------------------------------------------------------------
| SEND OTP BY EMAIL
|--------------------------------------------------------------------------
|
*/

function send_otp_email(
    string $email,
    string $username,
    string $otp
): bool {

    /*
    |--------------------------------------------------------------------------
    | Check required configuration
    |--------------------------------------------------------------------------
    */

    if (
        !defined('MAIL_HOST') ||
        !defined('MAIL_PORT') ||
        !defined('MAIL_USERNAME') ||
        !defined('MAIL_PASSWORD') ||
        !defined('MAIL_FROM_ADDRESS') ||
        !defined('MAIL_FROM_NAME')
    ) {

        error_log(
            'MFA email error: Mail configuration constants are missing.'
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Check PHPMailer availability
    |--------------------------------------------------------------------------
    */

    if (!class_exists(PHPMailer::class)) {

        error_log(
            'MFA email error: PHPMailer is not available.'
        );

        return false;
    }


    $mail = new PHPMailer(true);

    try {

        /*
        |--------------------------------------------------------------------------
        | SMTP configuration
        |--------------------------------------------------------------------------
        */

        $mail->isSMTP();

        $mail->Host = MAIL_HOST;

        $mail->SMTPAuth = true;

        $mail->Username = MAIL_USERNAME;

        $mail->Password = MAIL_PASSWORD;

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = MAIL_PORT;


        /*
        |--------------------------------------------------------------------------
        | Sender
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            MAIL_FROM_ADDRESS,
            MAIL_FROM_NAME
        );


        /*
        |--------------------------------------------------------------------------
        | Recipient
        |--------------------------------------------------------------------------
        */

        $mail->addAddress(
            $email,
            $username
        );


        /*
        |--------------------------------------------------------------------------
        | Email content
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);

        $mail->Subject =
            'SecureBank - Your One-Time Password';

        $safeUsername = encode_output($username);
        $safeOtp = encode_output($otp);

        $mail->Body = '
            <html>
            <body>

                <h2>SecureBank</h2>

                <p>
                    Hello ' . $safeUsername . ',
                </p>

                <p>
                    Your SecureBank verification code is:
                </p>

                <h1 style="letter-spacing: 5px;">
                    ' . $safeOtp . '
                </h1>

                <p>
                    This OTP will expire in
                    <strong>5 minutes</strong>.
                </p>

                <p>
                    Do not share this code with anyone.
                </p>

                <p>
                    If you did not attempt to log in,
                    please secure your account immediately.
                </p>

                <p>
                    SecureBank Security Team
                </p>

            </body>
            </html>
        ';

        $mail->AltBody =
            'Your SecureBank OTP is: ' .
            $otp .
            '. It expires in 5 minutes.';


        /*
        |--------------------------------------------------------------------------
        | Send email
        |--------------------------------------------------------------------------
        */

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log(
            'PHPMailer error: ' .
            $mail->ErrorInfo
        );

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| SEND OTP BY SMS USING TELERIVET
|--------------------------------------------------------------------------
|
*/

function send_otp_sms(
    string $phoneNumber,
    string $otp
): bool {

    /*
    |--------------------------------------------------------------------------
    | Check Telerivet configuration
    |--------------------------------------------------------------------------
    */

    if (
        !defined('TELERIVET_API_KEY') ||
        !defined('TELERIVET_PROJECT_ID') ||
        TELERIVET_API_KEY === '' ||
        TELERIVET_PROJECT_ID === ''
    ) {

        error_log(
            'Telerivet error: API key or project ID is not configured.'
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize Philippine phone number
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | 09123456789
    |
    | becomes:
    |
    | +639123456789
    |
    */

    $phoneNumber = trim($phoneNumber);

    if ($phoneNumber === '') {

        error_log(
            'Telerivet error: Phone number is empty.'
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Remove spaces, dashes and parentheses
    |--------------------------------------------------------------------------
    */

    $phoneNumber = preg_replace(
        '/[\s\-\(\)]/',
        '',
        $phoneNumber
    );


    /*
    |--------------------------------------------------------------------------
    | Convert Philippine local format
    |--------------------------------------------------------------------------
    */

    if (
        strlen($phoneNumber) === 11 &&
        substr($phoneNumber, 0, 1) === '0'
    ) {

        $phoneNumber =
            '+63' .
            substr($phoneNumber, 1);

    } elseif (
        strlen($phoneNumber) === 10 &&
        substr($phoneNumber, 0, 1) === '9'
    ) {

        $phoneNumber =
            '+63' .
            $phoneNumber;

    } elseif (
        substr($phoneNumber, 0, 2) === '63'
    ) {

        $phoneNumber =
            '+' .
            $phoneNumber;
    }


    /*
    |--------------------------------------------------------------------------
    | Telerivet API URL
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | This must be a normal URL.
    | Do NOT put Markdown links inside this string.
    |
    */

    $url =
        'https://api.telerivet.com/v1/projects/' .
        rawurlencode(TELERIVET_PROJECT_ID) .
        '/messages/send';


    /*
    |--------------------------------------------------------------------------
    | SMS payload
    |--------------------------------------------------------------------------
    */

    $payload = [
        'content' =>
            'Your SecureBank OTP: ' . $otp .
            '. It expires in 5 minutes.',

        'to_number' =>
            $phoneNumber
    ];


    /*
    |--------------------------------------------------------------------------
    | Initialize CURL
    |--------------------------------------------------------------------------
    */

    $ch = curl_init($url);

    if ($ch === false) {

        error_log(
            'Telerivet error: Unable to initialize CURL.'
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | CURL configuration
    |--------------------------------------------------------------------------
    */

    curl_setopt_array(
        $ch,
        [
            CURLOPT_POST => true,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],

            CURLOPT_USERPWD =>
                TELERIVET_API_KEY . ':',

            CURLOPT_POSTFIELDS =>
                json_encode($payload),

            CURLOPT_TIMEOUT => 30,

            CURLOPT_CONNECTTIMEOUT => 10
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Execute request
    |--------------------------------------------------------------------------
    */

    $response = curl_exec($ch);

    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    $curlError = curl_error($ch);

    curl_close($ch);


    /*
    |--------------------------------------------------------------------------
    | CURL failure
    |--------------------------------------------------------------------------
    */

    if ($response === false) {

        error_log(
            'Telerivet CURL error: ' .
            $curlError
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | HTTP failure
    |--------------------------------------------------------------------------
    */

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {

        error_log(
            'Telerivet HTTP error: ' .
            $httpCode .
            ' Response: ' .
            $response
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | SMS successfully submitted
    |--------------------------------------------------------------------------
    */

    return true;
}


/*
|--------------------------------------------------------------------------
| VERIFY OTP
|--------------------------------------------------------------------------
|
*/

function verify_otp(
    int $userId,
    string $submittedOtp
): array {

    global $pdo;


    /*
    |--------------------------------------------------------------------------
    | Verify MFA session belongs to the same user
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_SESSION['mfa_pending_user_id']) ||
        (int) $_SESSION['mfa_pending_user_id'] !== $userId
    ) {

        return [
            'success' => false,
            'message' => 'Invalid MFA session.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Make sure user ID is valid
    |--------------------------------------------------------------------------
    */

    if ($userId <= 0) {

        return [
            'success' => false,
            'message' => 'Invalid user account.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Check lockout
    |--------------------------------------------------------------------------
    */

    $lockedUntil =
        (int) (
            $_SESSION['otp_locked_until'] ?? 0
        );

    if ($lockedUntil > time()) {

        $remaining =
            $lockedUntil - time();

        return [
            'success' => false,
            'message' =>
                'Too many failed attempts. ' .
                'Please wait ' .
                $remaining .
                ' seconds before trying again.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Validate OTP format
    |--------------------------------------------------------------------------
    */

    $submittedOtp = trim($submittedOtp);

    if (
        !preg_match(
            '/^[0-9]{6}$/',
            $submittedOtp
        )
    ) {

        return [
            'success' => false,
            'message' =>
                'OTP must contain exactly 6 digits.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Find latest unused OTP
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'SELECT *
         FROM mfa_codes
         WHERE user_id = ?
         AND is_used = FALSE
         ORDER BY id DESC
         LIMIT 1'
    );

    $stmt->execute([
        $userId
    ]);

    $otpRecord = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | No OTP found
    |--------------------------------------------------------------------------
    */

    if (!$otpRecord) {

        return [
            'success' => false,
            'message' =>
                'No active OTP was found. ' .
                'Please request a new OTP.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Check OTP expiration
    |--------------------------------------------------------------------------
    */

    $expiresAt =
        strtotime(
            (string) $otpRecord['expires_at']
        );

    if (
        $expiresAt === false ||
        $expiresAt < time()
    ) {

        /*
        |--------------------------------------------------------------------------
        | Mark expired OTP as used
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            'UPDATE mfa_codes
             SET is_used = TRUE
             WHERE id = ?'
        );

        $stmt->execute([
            (int) $otpRecord['id']
        ]);

        return [
            'success' => false,
            'message' =>
                'This OTP has expired. ' .
                'Please request a new OTP.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Compare OTP securely
    |--------------------------------------------------------------------------
    */

    if (
        !hash_equals(
            (string) $otpRecord['otp_code'],
            $submittedOtp
        )
    ) {

        /*
        |--------------------------------------------------------------------------
        | Initialize attempt counter
        |--------------------------------------------------------------------------
        */

        if (!isset($_SESSION['otp_attempts'])) {

            $_SESSION['otp_attempts'] = 0;
        }


        /*
        |--------------------------------------------------------------------------
        | Increase failed attempts
        |--------------------------------------------------------------------------
        */

        $_SESSION['otp_attempts']++;


        /*
        |--------------------------------------------------------------------------
        | Check maximum attempts
        |--------------------------------------------------------------------------
        */

        if (
            $_SESSION['otp_attempts'] >=
            OTP_MAX_ATTEMPTS
        ) {

            $_SESSION['otp_locked_until'] =
                time() + 300;

            $_SESSION['otp_attempts'] = 0;

            return [
                'success' => false,
                'message' =>
                    'Maximum OTP attempts reached. ' .
                    'Your verification is temporarily locked ' .
                    'for 5 minutes.'
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Calculate remaining attempts
        |--------------------------------------------------------------------------
        */

        $remainingAttempts =
            OTP_MAX_ATTEMPTS -
            $_SESSION['otp_attempts'];

        return [
            'success' => false,
            'message' =>
                'Incorrect OTP. You have ' .
                $remainingAttempts .
                ' attempt(s) remaining.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | OTP is correct
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'UPDATE mfa_codes
         SET is_used = TRUE
         WHERE id = ?'
    );

    $stmt->execute([
        (int) $otpRecord['id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Reset OTP attempts
    |--------------------------------------------------------------------------
    */

    $_SESSION['otp_attempts'] = 0;

    $_SESSION['otp_locked_until'] = 0;


    /*
    |--------------------------------------------------------------------------
    | Complete authentication
    |--------------------------------------------------------------------------
    */

    require_once __DIR__ . '/auth.php';

    if (!complete_mfa_login($userId)) {

        return [
            'success' => false,
            'message' =>
                'Unable to complete login.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | MFA successful
    |--------------------------------------------------------------------------
    */

    return [
        'success' => true,
        'message' =>
            'MFA verification successful.'
    ];
}


/*
|--------------------------------------------------------------------------
| CREATE AND SEND OTP
|--------------------------------------------------------------------------
|
*/

function create_and_send_otp(
    int $userId
): array {

    /*
    |--------------------------------------------------------------------------
    | Get pending MFA user
    |--------------------------------------------------------------------------
    */

    $user = get_pending_mfa_user();

    if (
        !$user ||
        (int) $user['id'] !== $userId
    ) {

        return [
            'success' => false,
            'message' =>
                'Invalid MFA session.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Check MFA is enabled
    |--------------------------------------------------------------------------
    */

    if (!(bool) $user['is_mfa_enabled']) {

        return [
            'success' => false,
            'message' =>
                'MFA is not enabled for this account.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Check MFA method
    |--------------------------------------------------------------------------
    */

    $method = $user['mfa_method'];

    if (
        !in_array(
            $method,
            ['email', 'sms'],
            true
        )
    ) {

        return [
            'success' => false,
            'message' =>
                'No valid MFA method is configured.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Generate OTP
    |--------------------------------------------------------------------------
    */

    $otp = generate_otp();


    /*
    |--------------------------------------------------------------------------
    | Store OTP
    |--------------------------------------------------------------------------
    */

    if (!store_otp($userId, $otp)) {

        return [
            'success' => false,
            'message' =>
                'Unable to create OTP.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Send OTP
    |--------------------------------------------------------------------------
    */

    $sent = false;


    /*
    |--------------------------------------------------------------------------
    | EMAIL
    |--------------------------------------------------------------------------
    */

    if ($method === 'email') {

        if (
            empty($user['email']) ||
            !filter_var(
                $user['email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {

            return [
                'success' => false,
                'message' =>
                    'The account does not have a valid email address.'
            ];
        }

        $sent = send_otp_email(
            $user['email'],
            $user['username'],
            $otp
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    */

    elseif ($method === 'sms') {

        if (
            empty($user['phone_number'])
        ) {

            return [
                'success' => false,
                'message' =>
                    'The account does not have a phone number.'
            ];
        }

        $sent = send_otp_sms(
            $user['phone_number'],
            $otp
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Sending failed
    |--------------------------------------------------------------------------
    */

    if (!$sent) {

        return [
            'success' => false,
            'message' =>
                'The OTP could not be sent. ' .
                'Check your MFA configuration.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Record OTP send time
    |--------------------------------------------------------------------------
    */

    $_SESSION['otp_sent_at'] = time();


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    return [
        'success' => true,
        'message' =>
            'A verification code has been sent.'
    ];
}