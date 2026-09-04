<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

require_login();

$user = get_current_user_data();

$username = $user['username'] ?? 'User';
$email = $user['email'] ?? '';
$phone = $user['phone_number'] ?? '';
$accountNumber = $user['account_number'] ?? '';

$csrfToken = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>SecureBank Dashboard</title>

    <link rel="stylesheet"
          href="css/style.css">
</head>

<body>

<div class="container">

    <div class="dashboard-header">

        <div>
            <h1>SecureBank</h1>

            <p>
                Welcome,
                <strong>
                    <?= encode_output($username) ?>
                </strong>
            </p>
        </div>

        <div>
            <a href="logout.php"
               class="btn">
                Logout
            </a>
        </div>

    </div>


    <hr>


    <section class="dashboard-section">

        <h2>Account Information</h2>

        <div class="account-card">

            <p>
                <strong>Username:</strong>
                <?= encode_output($username) ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?= encode_output($email) ?>
            </p>

            <p>
                <strong>Phone Number:</strong>
                <?= encode_output($phone) ?>
            </p>

            <p>
                <strong>Account Number:</strong>
                <?= encode_output($accountNumber) ?>
            </p>

        </div>

    </section>


    <section class="dashboard-section">

        <h2>Banking Services</h2>

        <div class="dashboard-links">

            <a href="transfer.php"
               class="dashboard-button">
                Transfer Money
            </a>

            <a href="transaction_history.php"
               class="dashboard-button">
                Transaction History
            </a>

            <a href="profile.php"
               class="dashboard-button">
                Update Profile
            </a>

        </div>

    </section>


    <section class="dashboard-section">

        <h2>Security</h2>

        <div class="security-card">

            <p>
                Your SecureBank account is protected by
                security controls including:
            </p>

            <ul>
                <li>XSS protection</li>
                <li>CSRF protection</li>
                <li>Secure session cookies</li>
                <li>Multi-factor authentication</li>
                <li>IDOR access control</li>
            </ul>

        </div>

    </section>


    <!--
    ------------------------------------------------------------
    CSRF TOKEN
    ------------------------------------------------------------
    This hidden token can be used by future
    state-changing forms on the dashboard.
    ------------------------------------------------------------
    -->

    <input type="hidden"
           name="csrf_token"
           value="<?= encode_output($csrfToken) ?>">


</div>

</body>
</html>