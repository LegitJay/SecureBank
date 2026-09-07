<?php
require_once 'config.php';
require_once 'security.php';

// Create a user account.
function register_user($username, $email, $phone, $password) {
    global $pdo;

    // Clean user input.
    $username = sanitize_input($username);
    $email    = sanitize_input($email);
    $phone    = sanitize_input($phone);

    // Validate required fields.
    if (empty($username) || empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'All fields are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    // Prevent duplicate accounts.
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already taken.'];
    }

    // Hash the password.
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Generate the account number.
    $account_number = generate_account_number();

    // Save the user.
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, phone_number, password_hash, account_number, account_balance)
        VALUES (?, ?, ?, ?, ?, 0.00)
    ");
    $stmt->execute([$username, $email, $phone, $password_hash, $account_number]);

    return ['success' => true, 'message' => 'Account created successfully. Please log in.'];
}

// Generate a unique account number.
function generate_account_number() {
    global $pdo;
    do {
        $number = 'ACC-' . str_pad(random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        $stmt   = $pdo->prepare("SELECT id FROM users WHERE account_number = ?");
        $stmt->execute([$number]);
    } while ($stmt->fetch());
    return $number;
}

// Authenticate a user.
function login_user($username, $password) {
    global $pdo;

    // Clean the username.
    $username = sanitize_input($username);

    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username and password are required.'];
    }

    // Find the user.
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Verify the password.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    // Prevent session fixation.
    session_regenerate_id(true);

    // Store login state.
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['logged_in'] = true;

    // Start MFA if enabled.
    if ($user['is_mfa_enabled']) {
        $_SESSION['mfa_pending']    = true;
        $_SESSION['mfa_method']     = $user['mfa_method'];
        $_SESSION['mfa_user_email'] = $user['email'];
        $_SESSION['mfa_user_phone'] = $user['phone_number'];
        return ['success' => true, 'message' => 'mfa_required', 'user' => $user];
    }

    return ['success' => true, 'message' => 'Login successful.', 'user' => $user];
}

// End the session.
function logout_user() {
    // Clear session data.
    $_SESSION = [];

    // Remove the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
    header("Location: index.php?message=logged_out");
    exit();
}

// Get the current user.
function get_logged_in_user() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}