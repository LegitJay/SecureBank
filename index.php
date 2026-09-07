<?php
require_once 'config.php';
require_once 'security.php';
require_once 'auth.php';
require_once 'mfa.php';

// Redirect active sessions.
if (isset($_SESSION['logged_in']) && !isset($_SESSION['mfa_pending'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$mode = isset($_GET['mode']) && $_GET['mode'] === 'register' ? 'register' : 'login';

// Show redirect status.
if (isset($_GET['message']) && $_GET['message'] === 'logged_out') {
    $success = 'You have been logged out successfully.';
}
if (isset($_GET['error']) && $_GET['error'] === 'session_expired') {
    $error = 'Your session has expired. Please log in again.';
}

// Handle login.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {

    // Validate the form token.
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid or expired form token. Please try again.';
    } else {
        $result = login_user($_POST['username'] ?? '', $_POST['password'] ?? '');

        if ($result['success']) {
            if ($result['message'] === 'mfa_required') {
                // Send OTP before verification.
                $user = $result['user'];
                $method = $user['mfa_method'];
                send_otp($user['id'], $method, $user['email'], $user['phone_number']);
                header("Location: verify_otp.php");
                exit();
            } else {
                header("Location: dashboard.php");
                exit();
            }
        } else {
            $error = $result['message'];
        }
    }
}

// Handle registration.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {

    // Validate the form token.
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid or expired form token. Please try again.';
    } else {
        $result = register_user(
            $_POST['username'] ?? '',
            $_POST['email'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['password'] ?? ''
        );

        if ($result['success']) {
            $success = $result['message'];
            $mode = 'login';
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureBank — <?= $mode === 'register' ? 'Create Account' : 'Login' ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>

<script>
function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const icon  = document.getElementById('icon-' + fieldId);

    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = `
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
            <line x1="1" y1="1" x2="23" y2="23"/>
        `;
    } else {
        input.type = 'password';
        icon.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        `;
    }
}
</script>

<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-panel-left">
            <div class="auth-panel-logo">
                <div class="auth-panel-logo-name">SecureBank<br>Inc.</div>
                <div class="auth-panel-logo-sub">Online Banking Portal</div>
            </div>
            <div class="auth-panel-tagline">
                Your security is our priority. All transactions are protected with bank-grade encryption.
            </div>
        </div>
        <div class="auth-panel-right">

            <?php if ($error): ?>
                <div class="alert alert-error"><?= encode_output($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= encode_output($success) ?></div>
            <?php endif; ?>

            <div class="auth-tabs">
                <a href="index.php" class="tab <?= $mode === 'login' ? 'active' : '' ?>">Login</a>
                <a href="index.php?mode=register" class="tab <?= $mode === 'register' ? 'active' : '' ?>">Register</a>
            </div>

            <?php if ($mode === 'login'): ?>
                <!-- Login form. -->
                <form method="POST" action="index.php" class="auth-form">
                    <input type="hidden" name="action" value="login">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter your username"
                            autocomplete="username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" placeholder="Enter your password"
                                autocomplete="current-password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                <svg id="icon-password" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">Login</button>
                </form>

            <?php else: ?>
                <!-- Registration form. -->
                <form method="POST" action="index.php?mode=register" class="auth-form">
                    <input type="hidden" name="action" value="register">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="reg_username">Username</label>
                        <input type="text" id="reg_username" name="username" placeholder="Choose a username"
                            autocomplete="username" required>
                    </div>

                    <div class="form-group">
                        <label for="reg_email">Email Address</label>
                        <input type="email" id="reg_email" name="email" placeholder="your@email.com" autocomplete="email"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="reg_phone">Phone Number</label>
                        <input type="tel" id="reg_phone" name="phone" placeholder="+639XXXXXXXXX" autocomplete="tel">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" placeholder="Enter your password"
                                autocomplete="current-password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                <svg id="icon-password" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">Create Account</button>
                </form>
            <?php endif; ?>
        </div>

</body>

</html>