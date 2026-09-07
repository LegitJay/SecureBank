<?php
require_once 'config.php';
require_once 'security.php';
require_once 'auth.php';
require_once 'mfa.php';

// Require login.
require_login();

// Skip verification when MFA is complete.
if (!isset($_SESSION['mfa_pending']) || $_SESSION['mfa_pending'] !== true) {
    header("Location: dashboard.php");
    exit();
}

$error   = '';
$success = '';

// Resend the OTP.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend') {

    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid form token. Please try again.';
    } else {
        unset($_SESSION['otp_attempts']);

        $result = send_otp(
            $_SESSION['user_id'],
            $_SESSION['mfa_method'],
            $_SESSION['mfa_user_email'],
            $_SESSION['mfa_user_phone']
        );
        $success = $result['success']
            ? 'A new OTP has been sent to your ' . $_SESSION['mfa_method'] . '.'
            : $result['message'];
    }
}

// Verify the OTP.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {

    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid form token. Please try again.';
    } else {
        $submitted_otp = sanitize_input($_POST['otp'] ?? '');
        $result        = verify_otp($_SESSION['user_id'], $submitted_otp);

        if ($result['success']) {
            // Complete MFA.
            unset($_SESSION['mfa_pending']);
            header("Location: dashboard.php");
            exit();

        } elseif (isset($result['lockout']) && $result['lockout']) {
            // End the session after lockout.
            logout_user();

        } else {
            $error = $result['message'];
        }
    }
}

// Mask the delivery target.
$method  = $_SESSION['mfa_method'] ?? 'email';
$target  = $method === 'email' ? $_SESSION['mfa_user_email'] : $_SESSION['mfa_user_phone'];
$masked  = mask_target($target, $method);

function mask_target($value, $method) {
    if ($method === 'email') {
        [$local, $domain] = explode('@', $value);
        return substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 2)) . substr($local, -1) . '@' . $domain;
    } else {
        return substr($value, 0, 4) . str_repeat('*', strlen($value) - 7) . substr($value, -3);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureBank — Verify OTP</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">

<div class="auth-container">
    <div class="auth-logo">
        <div class="logo-icon">🔐</div>
        <h1>SecureBank</h1>
        <p>Two-Factor Verification</p>
    </div>

    <div class="otp-info">
        <p>A 6-digit OTP has been sent to your
            <strong><?= $method === 'email' ? 'email' : 'phone' ?></strong>:
            <span class="masked-target"><?= encode_output($masked) ?></span>
        </p>
        <p class="otp-expiry">⏱ Code expires in <strong><?= OTP_EXPIRY_MINUTES ?> minutes</strong></p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= encode_output($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= encode_output($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="verify_otp.php" class="auth-form">
        <input type="hidden" name="action" value="verify">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="otp">Enter OTP Code</label>
            <input type="text" id="otp" name="otp"
                   placeholder="_ _ _ _ _ _"
                   maxlength="6"
                   pattern="\d{6}"
                   inputmode="numeric"
                   autocomplete="one-time-code"
                   class="otp-input"
                   required>
        </div>

        <button type="submit" class="btn btn-primary btn-full">Verify OTP</button>
    </form>

    <form method="POST" action="verify_otp.php" class="resend-form">
        <input type="hidden" name="action" value="resend">
        <?= csrf_field() ?>
        <p>Didn't receive the code?
            <button type="submit" class="btn-link">Resend OTP</button>
        </p>
    </form>

    <div class="auth-footer">
        <a href="logout.php">← Cancel and Logout</a>
    </div>
</div>

</body>
</html>