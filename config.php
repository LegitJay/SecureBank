<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SecureBank Configuration
|--------------------------------------------------------------------------
| Database connection, session security, CSRF, OTP, email, and Telerivet
| configuration.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| DATABASE CONFIGURATION
|--------------------------------------------------------------------------
*/

define('DB_HOST', 'localhost');
define('DB_NAME', 'securebank_db');
define('DB_USER', 'root');
define('DB_PASS', '');


/*
|--------------------------------------------------------------------------
| APPLICATION CONFIGURATION
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'SecureBank');


/*
|--------------------------------------------------------------------------
| SECURITY CONFIGURATION
|--------------------------------------------------------------------------
*/

define('CSRF_EXPIRATION', 1800); // 30 minutes

define('OTP_EXPIRATION', 300);   // 5 minutes

define('OTP_MAX_ATTEMPTS', 3);


/*
|--------------------------------------------------------------------------
| EMAIL CONFIGURATION
|--------------------------------------------------------------------------
|
| These are only needed if you use email MFA.
|
*/

define('MAIL_HOST', 'smtp.gmail.com');

define(
    'MAIL_USERNAME',
    'your-email@gmail.com'
);

define(
    'MAIL_PASSWORD',
    'your-app-password'
);

define('MAIL_PORT', 587);

define(
    'MAIL_FROM_ADDRESS',
    'noreply@securebank.com'
);

define(
    'MAIL_FROM_NAME',
    'SecureBank'
);


/*
|--------------------------------------------------------------------------
| TELERIVET CONFIGURATION
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Generate a NEW API key in Telerivet because the previous key was
| exposed. Paste the NEW key below.
|
*/

define(
    'TELERIVET_PROJECT_ID',
    'PJ32a18fde3ffab188'
);

define(
    'TELERIVET_API_KEY',
    '3fbbn_61wCgMFRcOzDuX6gARxTdZTYEtOsxM'
);


/*
|--------------------------------------------------------------------------
| SECURE SESSION CONFIGURATION
|--------------------------------------------------------------------------
*/

$isHttps = (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off' &&
    $_SERVER['HTTPS'] !== ''
);

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

try {

    $dsn =
        'mysql:host=' . DB_HOST .
        ';dbname=' . DB_NAME .
        ';charset=utf8mb4';

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false
        ]
    );

} catch (PDOException $e) {

    error_log(
        'Database connection error: ' .
        $e->getMessage()
    );

    die(
        'Unable to connect to the database.'
    );
}