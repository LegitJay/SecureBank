<?php
// Clean input before storage.
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    $data = preg_replace('/[\x00-\x1F\x7F]/u', '', $data);
    return $data;
}

// Escape output for HTML.
function encode_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Create a session CSRF token.
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token']      = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

// Validate and rotate a CSRF token.
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }

    if (time() - $_SESSION['csrf_token_time'] > CSRF_EXPIRY_SECONDS) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }

    unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
    return true;
}

// Render a hidden CSRF field.
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

// Check resource ownership.
function verify_user_access($target_user_id) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    return (int)$_SESSION['user_id'] === (int)$target_user_id;
}

// Create an indirect user reference.
function generate_reference($user_id) {
    $ref = bin2hex(random_bytes(16));
    if (!isset($_SESSION['reference_map'])) {
        $_SESSION['reference_map'] = [];
    }
    $_SESSION['reference_map'][$ref] = (int)$user_id;
    return $ref;
}

// Resolve an indirect user reference.
function get_user_from_reference($ref) {
    if (!isset($_SESSION['reference_map'][$ref])) {
        return null;
    }
    return $_SESSION['reference_map'][$ref];
}

// Require an authenticated session.
function require_login() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        header("Location: index.php?error=session_expired");
        exit();
    }
}

// Require completed MFA.
function require_mfa_verified() {
    if (isset($_SESSION['mfa_pending']) && $_SESSION['mfa_pending'] === true) {
        header("Location: verify_otp.php");
        exit();
    }
}