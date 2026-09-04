<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mfa.php';

/*
|--------------------------------------------------------------------------
| CHECK LOGIN STATUS
|--------------------------------------------------------------------------
*/

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) &&
           is_numeric($_SESSION['user_id']);
}


/*
|--------------------------------------------------------------------------
| REQUIRE LOGIN
|--------------------------------------------------------------------------
*/

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| GET CURRENT USER
|--------------------------------------------------------------------------
*/

function get_current_user_data(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT id, username, email, phone_number,
                account_number, account_balance,
                created_at, is_mfa_enabled, mfa_method
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([
        (int) $_SESSION['user_id']
    ]);

    $user = $stmt->fetch();

    return $user ?: null;
}


/*
|--------------------------------------------------------------------------
| GENERATE UNIQUE ACCOUNT NUMBER
|--------------------------------------------------------------------------
*/

function generate_account_number(): string
{
    global $pdo;

    do {
        $accountNumber = str_pad(
            (string) random_int(1000000000, 9999999999),
            10,
            '0',
            STR_PAD_LEFT
        );

        $stmt = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE account_number = ?
             LIMIT 1'
        );

        $stmt->execute([$accountNumber]);

    } while ($stmt->fetch());

    return $accountNumber;
}


/*
|--------------------------------------------------------------------------
| REGISTER USER
|--------------------------------------------------------------------------
*/

function register_user(
    string $username,
    string $email,
    string $phone,
    string $password,
    bool $mfaEnabled,
    ?string $mfaMethod
): array {

    global $pdo;

    /*
    |----------------------------------------------------------------------
    | SANITIZE USER INPUT
    |----------------------------------------------------------------------
    */

    $username = sanitize_input($username);
    $email = sanitize_input($email);
    $phone = sanitize_input($phone);

    /*
    | Password is NOT HTML-encoded because it is credential data.
    | It will be securely hashed before storage.
    */

    $password = trim($password);

    /*
    |----------------------------------------------------------------------
    | VALIDATION
    |----------------------------------------------------------------------
    */

    if ($username === '') {
        return [
            'success' => false,
            'message' => 'Username is required.'
        ];
    }

    if (strlen($username) < 3 || strlen($username) > 50) {
        return [
            'success' => false,
            'message' => 'Username must be between 3 and 50 characters.'
        ];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Please enter a valid email address.'
        ];
    }

    if ($phone === '') {
        return [
            'success' => false,
            'message' => 'Phone number is required.'
        ];
    }

    if (strlen($password) < 8) {
        return [
            'success' => false,
            'message' => 'Password must be at least 8 characters.'
        ];
    }

    /*
    |----------------------------------------------------------------------
    | VALIDATE MFA METHOD
    |----------------------------------------------------------------------
    */

    if ($mfaEnabled) {

        if (!in_array($mfaMethod, ['sms', 'email'], true)) {
            return [
                'success' => false,
                'message' => 'Please select a valid MFA method.'
            ];
        }

    } else {

        $mfaMethod = null;
    }

    /*
    |----------------------------------------------------------------------
    | CHECK DUPLICATE USERNAME
    |----------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'SELECT id
         FROM users
         WHERE username = ?
         LIMIT 1'
    );

    $stmt->execute([$username]);

    if ($stmt->fetch()) {
        return [
            'success' => false,
            'message' => 'Username already exists.'
        ];
    }

    /*
    |----------------------------------------------------------------------
    | CHECK DUPLICATE EMAIL
    |----------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'SELECT id
         FROM users
         WHERE email = ?
         LIMIT 1'
    );

    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        return [
            'success' => false,
            'message' => 'Email address is already registered.'
        ];
    }

    /*
    |----------------------------------------------------------------------
    | HASH PASSWORD
    |----------------------------------------------------------------------
    */

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    /*
    |----------------------------------------------------------------------
    | GENERATE ACCOUNT NUMBER
    |----------------------------------------------------------------------
    */

    $accountNumber = generate_account_number();

    /*
    |----------------------------------------------------------------------
    | INSERT USER
    |----------------------------------------------------------------------
    */

    try {

        $stmt = $pdo->prepare(
            'INSERT INTO users
            (
                username,
                email,
                phone_number,
                password_hash,
                account_number,
                account_balance,
                is_mfa_enabled,
                mfa_method
            )
            VALUES (?, ?, ?, ?, ?, 0.00, ?, ?)'
        );

        $stmt->execute([
            $username,
            $email,
            $phone,
            $passwordHash,
            $accountNumber,
            $mfaEnabled ? 1 : 0,
            $mfaMethod
        ]);

        return [
            'success' => true,
            'message' => 'Registration successful.',
            'account_number' => $accountNumber
        ];

    } catch (PDOException $e) {

        error_log(
            'Registration error: ' . $e->getMessage()
        );

        return [
            'success' => false,
            'message' => 'Registration failed. Please try again.'
        ];
    }
}


/*
|--------------------------------------------------------------------------
| LOGIN USER
|--------------------------------------------------------------------------
*/

function login_user(
    string $username,
    string $password
): array {

    global $pdo;

    /*
    |----------------------------------------------------------------------
    | SANITIZE USERNAME
    |----------------------------------------------------------------------
    */

    $username = sanitize_input($username);

    /*
    | Password remains unchanged.
    */

    $password = trim($password);

    if ($username === '' || $password === '') {

        return [
            'success' => false,
            'message' => 'Username and password are required.'
        ];
    }

    /*
    |----------------------------------------------------------------------
    | FIND USER
    |----------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'SELECT *
         FROM users
         WHERE username = ?
         LIMIT 1'
    );

    $stmt->execute([$username]);

    $user = $stmt->fetch();

    /*
    |----------------------------------------------------------------------
    | VERIFY PASSWORD
    |----------------------------------------------------------------------
    */

    if (!$user || !password_verify(
        $password,
        $user['password_hash']
    )) {

        return [
            'success' => false,
            'message' => 'Invalid username or password.'
        ];
    }

    /*
    |----------------------------------------------------------------------
    | REGENERATE SESSION ID
    |----------------------------------------------------------------------
    */

    session_regenerate_id(true);

    /*
    |----------------------------------------------------------------------
    | MFA REQUIRED
    |----------------------------------------------------------------------
    */

    if ((bool) $user['is_mfa_enabled']) {

    $_SESSION['mfa_pending_user_id'] =
        (int) $user['id'];

    $_SESSION['mfa_verified'] = false;

    $_SESSION['otp_attempts'] = 0;

    $_SESSION['otp_locked_until'] = 0;

    $otpResult = create_and_send_otp(
        (int) $user['id']
    );

    if (!$otpResult['success']) {

        unset(
            $_SESSION['mfa_pending_user_id'],
            $_SESSION['mfa_verified']
        );

        return [
            'success' => false,
            'message' =>
                $otpResult['message']
        ];
    }

    return [
        'success' => true,
        'mfa_required' => true,
        'user_id' => (int) $user['id'],
        'mfa_method' => $user['mfa_method']
    ];
}

    /*
    |----------------------------------------------------------------------
    | COMPLETE LOGIN
    |----------------------------------------------------------------------
    */

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['mfa_verified'] = true;

    /*
    |----------------------------------------------------------------------
    | Generate an indirect reference.
    |----------------------------------------------------------------------
    */

    generate_reference(
        (int) $user['id']
    );

    return [
        'success' => true,
        'mfa_required' => false,
        'user_id' => (int) $user['id']
    ];
}


/*
|--------------------------------------------------------------------------
| COMPLETE MFA LOGIN
|--------------------------------------------------------------------------
*/

function complete_mfa_login(int $userId): bool
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT id, username
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$userId]);

    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    /*
    |----------------------------------------------------------------------
    | Regenerate session after successful MFA.
    |----------------------------------------------------------------------
    */

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['mfa_verified'] = true;

    unset(
        $_SESSION['mfa_pending_user_id'],
        $_SESSION['otp_attempts'],
        $_SESSION['otp_locked_until']
    );

    /*
    |----------------------------------------------------------------------
    | Generate secure indirect reference.
    |----------------------------------------------------------------------
    */

    generate_reference(
        (int) $user['id']
    );

    return true;
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}