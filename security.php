<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| INPUT SANITIZATION
|--------------------------------------------------------------------------
| Removes HTML tags, control characters, and encodes special characters.
|--------------------------------------------------------------------------
*/

function sanitize_input($data): string
{
    $data = (string) $data;

    // Remove unnecessary whitespace
    $data = trim($data);

    // Remove backslashes
    $data = stripslashes($data);

    // Remove control characters
    $data = preg_replace('/[\x00-\x1F\x7F]/u', '', $data);

    // Remove HTML and PHP tags
    $data = strip_tags($data);

    // Encode special HTML characters
    $data = htmlspecialchars(
        $data,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    return $data;
}


/*
|--------------------------------------------------------------------------
| OUTPUT ENCODING
|--------------------------------------------------------------------------
| Encodes user-controlled data before displaying it in HTML.
|--------------------------------------------------------------------------
*/

function encode_output($data): string
{
    return htmlspecialchars(
        (string) $data,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN GENERATION
|--------------------------------------------------------------------------
| Generates a cryptographically secure 32-byte token.
| The token is stored in the session and returned for use
| inside hidden form fields.
|--------------------------------------------------------------------------
*/

function generate_csrf_token(): string
{
    /*
     * Generate a new token if:
     * 1. No token currently exists
     * 2. No token timestamp exists
     * 3. Existing token has expired
     */

    if (
        empty($_SESSION['csrf_token']) ||
        empty($_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time']) > CSRF_EXPIRATION
    ) {
        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );

        $_SESSION['csrf_token_time'] = time();
    }

    return $_SESSION['csrf_token'];
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN VALIDATION
|--------------------------------------------------------------------------
| Checks:
| 1. Session exists
| 2. Submitted token exists
| 3. Session token exists
| 4. Token has not expired
| 5. Submitted token matches session token
|--------------------------------------------------------------------------
*/

function validate_csrf_token(?string $token): bool
{
    /*
     * Make sure a token was submitted.
     */

    if (empty($token)) {
        return false;
    }


    /*
     * Make sure the session contains a CSRF token.
     */

    if (
        empty($_SESSION['csrf_token']) ||
        empty($_SESSION['csrf_token_time'])
    ) {
        return false;
    }


    /*
     * Check whether the token has expired.
     *
     * CSRF_EXPIRATION is set to 1800 seconds,
     * which is 30 minutes.
     */

    if (
        (time() - $_SESSION['csrf_token_time']) >
        CSRF_EXPIRATION
    ) {
        return false;
    }


    /*
     * Compare the submitted token with the session token
     * using hash_equals() to prevent timing attacks.
     */

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


/*
|--------------------------------------------------------------------------
| REQUIRE VALID CSRF TOKEN
|--------------------------------------------------------------------------
| This helper can be used by state-changing PHP pages.
|--------------------------------------------------------------------------
*/

function require_valid_csrf_token(): void
{
    $token = $_POST['csrf_token'] ?? null;

    if (!validate_csrf_token($token)) {

        http_response_code(403);

        die(
            'CSRF validation failed. ' .
            'The request was rejected for security reasons.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| IDOR ACCESS CONTROL
|--------------------------------------------------------------------------
| Ensures that the logged-in user can access only their own data.
|--------------------------------------------------------------------------
*/

function verify_user_access($target_user_id): bool
{
    /*
     * User must be logged in.
     */

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    /*
     * Compare the currently logged-in user ID
     * with the requested user ID.
     */

    return (int) $_SESSION['user_id'] ===
           (int) $target_user_id;
}


/*
|--------------------------------------------------------------------------
| INDIRECT REFERENCE GENERATION
|--------------------------------------------------------------------------
| Creates a random reference instead of exposing the actual
| user ID in the URL.
|--------------------------------------------------------------------------
*/

function generate_reference(int $user_id): string
{
    if (!isset($_SESSION['reference_map'])) {
        $_SESSION['reference_map'] = [];
    }

    /*
     * Generate a cryptographically secure random reference.
     */

    $reference = bin2hex(
        random_bytes(16)
    );

    /*
     * Store the relationship between the random reference
     * and the actual user ID.
     */

    $_SESSION['reference_map'][$reference] = $user_id;

    return $reference;
}


/*
|--------------------------------------------------------------------------
| GET USER ID FROM INDIRECT REFERENCE
|--------------------------------------------------------------------------
*/

function get_user_from_reference(
    ?string $reference
): ?int {

    if (
        empty($reference) ||
        !isset($_SESSION['reference_map'][$reference])
    ) {
        return null;
    }

    return (int) $_SESSION['reference_map'][$reference];
}