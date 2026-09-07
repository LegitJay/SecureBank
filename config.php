<?php
// Secure session cookies.
session_set_cookie_params([
    'lifetime' => 3600,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

$env = parse_ini_file(__DIR__ . '/.env');

// Database settings
define('DB_HOST', $env['DB_HOST']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASS']);
define('DB_NAME', $env['DB_NAME']);

// Open the database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Keep database errors out of responses
    error_log("DB Connection Error: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// Application settings
define('APP_NAME', 'SecureBank Inc.');
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_MAX_ATTEMPTS', 3);
define('CSRF_EXPIRY_SECONDS', 1800);