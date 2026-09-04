<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mfa.php';


/*
|--------------------------------------------------------------------------
| Make sure there is a pending MFA session
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['mfa_pending_user_id'])) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get pending MFA user
|--------------------------------------------------------------------------
*/

$user = get_pending_mfa_user();

if (!$user) {

    unset(
        $_SESSION['mfa_pending_user_id'],
        $_SESSION['mfa_verified'],
        $_SESSION['otp_attempts'],
        $_SESSION['otp_locked_until'],
        $_SESSION['otp_sent_at']
    );

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';

$userId = (int) $_SESSION['mfa_pending_user_id'];


/*
|--------------------------------------------------------------------------
| Process OTP verification
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF validation
    |--------------------------------------------------------------------------
    */

    require_valid_csrf_token();


    /*
    |--------------------------------------------------------------------------
    | Get submitted OTP
    |--------------------------------------------------------------------------
    */

    $submittedOtp = trim(
        (string) ($_POST['otp'] ?? '')
    );


    /*
    |--------------------------------------------------------------------------
    | Verify OTP
    |--------------------------------------------------------------------------
    */

    $result = verify_otp(
        $userId,
        $submittedOtp
    );


    /*
    |--------------------------------------------------------------------------
    | Check result
    |--------------------------------------------------------------------------
    */

    if ($result['success']) {

        header('Location: dashboard.php');
        exit;

    } else {

        $message = $result['message'];
        $messageType = 'error';
    }
}


/*
|--------------------------------------------------------------------------
| Generate CSRF token
|--------------------------------------------------------------------------
*/

$csrfToken = generate_csrf_token();


/*
|--------------------------------------------------------------------------
| Determine MFA method
|--------------------------------------------------------------------------
*/

$mfaMethod = $user['mfa_method'] ?? '';


/*
|--------------------------------------------------------------------------
| Mask destination
|--------------------------------------------------------------------------
*/

$destination = '';

if ($mfaMethod === 'sms') {

    $phone = (string) ($user['phone_number'] ?? '');

    if (strlen($phone) >= 4) {

        $destination =
            str_repeat(
                '*',
                max(0, strlen($phone) - 4)
            ) .
            substr($phone, -4);

    } else {

        $destination = 'your registered phone number';
    }

} elseif ($mfaMethod === 'email') {

    $email = (string) ($user['email'] ?? '');

    $atPosition = strpos($email, '@');

    if (
        $atPosition !== false &&
        $atPosition > 1
    ) {

        $usernamePart =
            substr(
                $email,
                0,
                $atPosition
            );

        $domainPart =
            substr(
                $email,
                $atPosition
            );

        $destination =
            substr($usernamePart, 0, 1) .
            str_repeat(
                '*',
                max(
                    1,
                    strlen($usernamePart) - 2
                )
            ) .
            substr(
                $usernamePart,
                -1
            ) .
            $domainPart;

    } else {

        $destination = 'your registered email address';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        SecureBank - Verify OTP
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #f3f6f9;

            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }

        .container {
            width: 100%;
            max-width: 500px;

            padding: 20px;
        }

        .card {
            background: #ffffff;

            border-radius: 10px;

            padding: 40px;

            box-shadow:
                0 4px 20px
                rgba(0, 0, 0, 0.10);
        }

        .logo {
            text-align: center;

            margin-bottom: 10px;
        }

        .logo h1 {
            margin: 0;

            color: #1f4f7a;

            font-size: 32px;
        }

        .subtitle {
            text-align: center;

            color: #666666;

            margin-bottom: 30px;
        }

        h2 {
            margin-top: 0;

            color: #222222;
        }

        .info {
            background: #eef5fb;

            border-left: 4px solid #1f4f7a;

            padding: 15px;

            margin-bottom: 25px;

            line-height: 1.6;

            color: #333333;
        }

        .message {
            padding: 14px;

            border-radius: 6px;

            margin-bottom: 20px;

            line-height: 1.5;
        }

        .error {
            background: #fde8e8;

            color: #a61b1b;
        }

        .success {
            background: #e8f7ed;

            color: #176b35;
        }

        label {
            display: block;

            margin-bottom: 8px;

            font-weight: bold;

            color: #222222;
        }

        input[type="text"] {
            width: 100%;

            padding: 14px;

            border: 1px solid #cccccc;

            border-radius: 6px;

            font-size: 22px;

            text-align: center;

            letter-spacing: 8px;

            outline: none;
        }

        input[type="text"]:focus {
            border-color: #1f4f7a;

            box-shadow:
                0 0 0 2px
                rgba(31, 79, 122, 0.12);
        }

        button {
            width: 100%;

            margin-top: 20px;

            padding: 14px;

            border: none;

            border-radius: 6px;

            background: #1f4f7a;

            color: white;

            font-size: 16px;

            cursor: pointer;
        }

        button:hover {
            background: #163c5d;
        }

        .back {
            display: block;

            text-align: center;

            margin-top: 20px;

            color: #1f4f7a;

            text-decoration: none;
        }

        .back:hover {
            text-decoration: underline;
        }

        .security-note {
            margin-top: 25px;

            text-align: center;

            font-size: 13px;

            color: #777777;

            line-height: 1.5;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <div class="logo">

            <h1>
                SecureBank
            </h1>

        </div>

        <div class="subtitle">

            Secure Customer Banking Portal

        </div>


        <h2>
            Verify Your Identity
        </h2>


        <?php if ($message !== ''): ?>

            <div class="message <?= $messageType === 'error' ? 'error' : 'success' ?>">

                <?= encode_output($message) ?>

            </div>

        <?php endif; ?>


        <div class="info">

            <?php if ($mfaMethod === 'sms'): ?>

                A verification code was sent to your
                registered mobile number ending in
                <strong>
                    <?= encode_output($destination) ?>
                </strong>.

            <?php elseif ($mfaMethod === 'email'): ?>

                A verification code was sent to your
                registered email address:
                <strong>
                    <?= encode_output($destination) ?>
                </strong>.

            <?php else: ?>

                A verification code was requested
                for your account.

            <?php endif; ?>

            <br><br>

            The OTP is valid for
            <strong>5 minutes</strong>.

        </div>


        <form
            method="POST"
            action="verify_otp.php"
            autocomplete="off"
        >

            <!-- CSRF TOKEN -->

            <input
                type="hidden"
                name="csrf_token"
                value="<?= encode_output($csrfToken) ?>"
            >


            <label for="otp">
                Enter 6-Digit OTP
            </label>


            <input
                type="text"
                id="otp"
                name="otp"
                maxlength="6"
                minlength="6"
                pattern="[0-9]{6}"
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="000000"
                required
                autofocus
            >


            <button type="submit">
                Verify OTP
            </button>

        </form>


        <a
            href="index.php"
            class="back"
        >
            Cancel and return to Login
        </a>


        <div class="security-note">

            For your security, never share your
            verification code with anyone.

        </div>

    </div>

</div>

</body>

</html>