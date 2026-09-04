<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

$message = '';
$messageType = '';

$activeForm = 'login';

if (isset($_GET['register'])) {
    $activeForm = 'register';
}


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['login'])
) {

    $activeForm = 'login';

    /*
    |----------------------------------------------------------------------
    | CSRF VALIDATION
    |----------------------------------------------------------------------
    */

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!validate_csrf_token($csrfToken)) {

        $message = 'Invalid or expired CSRF token.';
        $messageType = 'error';

    } else {

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = login_user(
            $username,
            $password
        );

        if (!$result['success']) {

            $message = $result['message'];
            $messageType = 'error';

        } elseif ($result['mfa_required']) {

            /*
            |--------------------------------------------------------------
            | MFA is required.
            |--------------------------------------------------------------
            */

            header('Location: verify_otp.php');
            exit;

        } else {

            /*
            |--------------------------------------------------------------
            | Login successful.
            |--------------------------------------------------------------
            */

            header('Location: dashboard.php');
            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| REGISTRATION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['register'])
) {

    $activeForm = 'register';

    /*
    |----------------------------------------------------------------------
    | CSRF VALIDATION
    |----------------------------------------------------------------------
    */

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!validate_csrf_token($csrfToken)) {

        $message = 'Invalid or expired CSRF token.';
        $messageType = 'error';

    } else {

        $username = $_POST['reg_username'] ?? '';
        $email = $_POST['reg_email'] ?? '';
        $phone = $_POST['reg_phone'] ?? '';
        $password = $_POST['reg_password'] ?? '';

        $mfaEnabled = isset($_POST['mfa_enabled']);

        $mfaMethod = $_POST['mfa_method'] ?? null;

        $result = register_user(
            $username,
            $email,
            $phone,
            $password,
            $mfaEnabled,
            $mfaMethod
        );

        if ($result['success']) {

            $message =
                'Registration successful! Your account number is ' .
                encode_output($result['account_number']) .
                '. You can now log in.';

            $messageType = 'success';
            $activeForm = 'login';

        } else {

            $message = $result['message'];
            $messageType = 'error';
        }
    }
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

$csrfToken = generate_csrf_token();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>SecureBank - Login</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body>

<div class="container">

    <div class="card">

        <h1>SecureBank</h1>

        <p class="subtitle">
            Secure Customer Banking Portal
        </p>


        <?php if ($message !== ''): ?>

            <div class="message <?= encode_output($messageType) ?>">

                <?= $message ?>

            </div>

        <?php endif; ?>


        <!-- ==========================================================
             LOGIN FORM
        =========================================================== -->

        <?php if ($activeForm === 'login'): ?>

            <form method="POST" action="index.php">

                <h2>Login</h2>

                <!-- CSRF TOKEN -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= encode_output($csrfToken) ?>"
                >

                <div class="form-group">

                    <label for="username">
                        Username
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        maxlength="50"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                    >

                </div>


                <button
                    type="submit"
                    name="login"
                    class="btn"
                >
                    Login
                </button>

            </form>


            <p class="switch-form">

                Don't have an account?

                <a href="index.php?register=1">
                    Create an account
                </a>

            </p>


        <!-- ==========================================================
             REGISTRATION FORM
        =========================================================== -->

        <?php else: ?>

            <form method="POST" action="index.php">

                <h2>Create Account</h2>

                <!-- CSRF TOKEN -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= encode_output($csrfToken) ?>"
                >


                <div class="form-group">

                    <label for="reg_username">
                        Username
                    </label>

                    <input
                        type="text"
                        id="reg_username"
                        name="reg_username"
                        maxlength="50"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="reg_email">
                        Email
                    </label>

                    <input
                        type="email"
                        id="reg_email"
                        name="reg_email"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="reg_phone">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        id="reg_phone"
                        name="reg_phone"
                        maxlength="20"
                        placeholder="09XXXXXXXXX"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="reg_password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="reg_password"
                        name="reg_password"
                        minlength="8"
                        required
                    >

                    <small>
                        Password must contain at least 8 characters.
                    </small>

                </div>


                <div class="form-group">

                    <label>
                        <input
                            type="checkbox"
                            name="mfa_enabled"
                            value="1"
                            checked
                        >

                        Enable Multi-Factor Authentication
                    </label>

                </div>


                <div class="form-group">

                    <label for="mfa_method">
                        MFA Method
                    </label>

                    <select
                        id="mfa_method"
                        name="mfa_method"
                    >

                        <option value="email">
                            Email
                        </option>

                        <option value="sms">
                            SMS
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    name="register"
                    class="btn"
                >
                    Register
                </button>

            </form>


            <p class="switch-form">

                Already have an account?

                <a href="index.php">
                    Login
                </a>

            </p>

        <?php endif; ?>

    </div>

</div>

</body>

</html>