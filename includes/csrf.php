<?php

/**
 * CSRF (Cross-Site Request Forgery) Protection Functions
 * 
 * This file provides CSRF token generation and validation to protect
 * forms and actions from CSRF attacks.
 * 
 * CSRF attacks trick authenticated users into submitting malicious requests.
 * This protection ensures requests come from legitimate forms in your application.
 * 
 * Functions:
 * - generate_csrf_token() - Generate and store CSRF token
 * - validate_csrf_token() - Validate submitted token
 * - csrf_token_input() - Generate hidden input field
 * - csrf_token_meta() - Generate meta tag for AJAX
 * - get_csrf_token() - Get current token value
 * - regenerate_csrf_token() - Create new token
 * - csrf_token_field() - Alias for csrf_token_input()
 * 
 * USAGE:
 * Include this file in forms and form processors
 * require_once 'includes/csrf.php';
 */

// Ensure session is started before using CSRF functions.
// Use start_secure_session() if available (session.php already included),
// so the correct session name (COURSE_EVAL_SESSION) and secure cookie
// params are applied. Falling back to a bare session_start() would open
// the default PHPSESSID session instead, which has no login data and
// causes an infinite redirect loop between login.php and the dashboards.
if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('start_secure_session')) {
        start_secure_session();
    } else {
        session_start();
    }
}

// Load constants if not already loaded
if (!defined('CSRF_TOKEN_NAME')) {
    require_once dirname(__DIR__) . '/config/constants.php';
}

/**
 * Generate CSRF Token
 * 
 * Creates a new CSRF token and stores it in the session.
 * If a token already exists, returns the existing token.
 * 
 * @param bool $force_new Force generation of new token (default: false)
 * @return string The CSRF token
 */
function generate_csrf_token($force_new = false)
{
    // Check if token already exists and we're not forcing a new one
    if (!$force_new && isset($_SESSION[CSRF_TOKEN_NAME]) && !empty($_SESSION[CSRF_TOKEN_NAME])) {
        if (!isset($_SESSION[CSRF_TOKEN_NAME . '_time'])) {
            $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    // Generate new random token
    // Using random_bytes for cryptographically secure random string
    $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));

    // Store in session
    $_SESSION[CSRF_TOKEN_NAME] = $token;

    // Optional: Store token generation time for expiration
    $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();

    return $token;
}

/**
 * Validate CSRF Token
 * 
 * Checks if the submitted token matches the token in session.
 * Uses timing-safe comparison to prevent timing attacks.
 * 
 * @param string|null $token The token to validate (if null, checks POST/GET)
 * @param string $input_name The input field name (default: CSRF_TOKEN_NAME)
 * @return bool True if valid, false otherwise
 */
function validate_csrf_token($token = null, $input_name = null)
{
    // Use default token name if not specified
    if ($input_name === null) {
        $input_name = CSRF_TOKEN_NAME;
    }

    // If token not provided, try to get it from POST or GET
    if ($token === null) {
        // Check POST first (most common)
        if (isset($_POST[$input_name])) {
            $token = $_POST[$input_name];
        }
        // Then check GET (less common, but supported)
        elseif (isset($_GET[$input_name])) {
            $token = $_GET[$input_name];
        }
        // No token found
        else {
            return false;
        }
    }

    // Check if session token exists
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || empty($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }

    // Reject expired tokens
    if (is_csrf_token_expired()) {
        return false;
    }

    // Get the stored token
    $stored_token = $_SESSION[CSRF_TOKEN_NAME];

    // Validate token using timing-safe comparison
    // hash_equals prevents timing attacks
    if (hash_equals($stored_token, $token)) {
        return true;
    }

    return false;
}

/**
 * Get Current CSRF Token
 * 
 * Returns the current CSRF token from session.
 * Generates a new one if it doesn't exist.
 * 
 * @return string The CSRF token
 */
function get_csrf_token()
{
    return generate_csrf_token();
}

/**
 * Regenerate CSRF Token
 * 
 * Forces generation of a new CSRF token.
 * Useful after sensitive operations.
 * 
 * @return string The new CSRF token
 */
function regenerate_csrf_token()
{
    return generate_csrf_token(true);
}

/**
 * CSRF Token Input Field
 * 
 * Generates a hidden input field with CSRF token.
 * Use this in all forms.
 * 
 * @param bool $echo Whether to echo or return (default: true)
 * @return string|void HTML input field
 */
function csrf_token_input($echo = true)
{
    $token = generate_csrf_token();
    $input = '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';

    if ($echo) {
        echo $input;
    } else {
        return $input;
    }
}

/**
 * Alias for csrf_token_input()
 * 
 * Shorter name for convenience.
 * 
 * @param bool $echo Whether to echo or return (default: true)
 * @return string|void HTML input field
 */
function csrf_field($echo = true)
{
    return csrf_token_input($echo);
}

/**
 * CSRF Token Meta Tag
 * 
 * Generates a meta tag with CSRF token for AJAX requests.
 * Place this in the <head> section of your HTML.
 * 
 * @param bool $echo Whether to echo or return (default: true)
 * @return string|void HTML meta tag
 */
function csrf_token_meta($echo = true)
{
    $token = generate_csrf_token();
    $meta = '<meta name="' . CSRF_TOKEN_NAME . '" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';

    if ($echo) {
        echo $meta;
    } else {
        return $meta;
    }
}

/**
 * CSRF Token for JavaScript/AJAX
 * 
 * Outputs JavaScript code to set CSRF token as a variable.
 * Use this if you need the token in JavaScript.
 * 
 * @param string $var_name JavaScript variable name (default: 'csrf_token')
 * @param bool $echo Whether to echo or return (default: true)
 * @return string|void JavaScript code
 */
function csrf_token_js($var_name = 'csrf_token', $echo = true)
{
    $token = generate_csrf_token();
    $js = '<script>var ' . $var_name . ' = "' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '";</script>';

    if ($echo) {
        echo $js;
    } else {
        return $js;
    }
}

/**
 * Check CSRF or Die
 * 
 * Validates CSRF token and terminates script with error if invalid.
 * Use this at the top of form processing scripts.
 * 
 * @param string|null $token The token to validate (if null, checks POST/GET)
 * @param string $error_message Custom error message (optional)
 */
function check_csrf_or_die($token = null, $error_message = null)
{
    if (!validate_csrf_token($token)) {
        // Use custom error message or default
        if ($error_message === null) {
            $error_message = defined('MSG_ERROR_INVALID_CSRF') ? MSG_ERROR_INVALID_CSRF : 'Invalid CSRF token. Please try again.';
        }

        // Display error and stop execution
        die('
            <div style="font-family: Arial; padding: 20px; background: #ffcccc; border: 2px solid #cc0000; margin: 20px; border-radius: 5px;">
                <h2 style="color: #cc0000; margin-top: 0;">Security Error</h2>
                <p>' . htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') . '</p>
                <p><a href="javascript:history.back()" style="color: #0066cc;">Go Back</a></p>
            </div>
        ');
    }
}

/**
 * CSRF Protected Form Start
 * 
 * Outputs a form opening tag with CSRF token included.
 * Convenience function for quick form creation.
 * 
 * @param string $action Form action URL
 * @param string $method Form method (default: 'POST')
 * @param string $extra_attributes Additional HTML attributes (optional)
 * @param bool $echo Whether to echo or return (default: true)
 * @return string|void HTML form tag with CSRF token
 */
function csrf_form_start($action = '', $method = 'POST', $extra_attributes = '', $echo = true)
{
    $html = '<form action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" method="' . strtoupper($method) . '" ' . $extra_attributes . '>';
    $html .= csrf_token_input(false);

    if ($echo) {
        echo $html;
    } else {
        return $html;
    }
}

/**
 * Get CSRF Token Array
 * 
 * Returns an associative array with token name and value.
 * Useful for AJAX requests or API calls.
 * 
 * @return array Array with 'name' and 'value' keys
 */
function get_csrf_token_array()
{
    return [
        'name' => CSRF_TOKEN_NAME,
        'value' => generate_csrf_token()
    ];
}

/**
 * Get CSRF Token JSON
 * 
 * Returns JSON string with token name and value.
 * Useful for API responses.
 * 
 * @return string JSON string
 */
function get_csrf_token_json()
{
    return json_encode(get_csrf_token_array());
}

/**
 * Validate CSRF Token from JSON/AJAX Request
 * 
 * Validates CSRF token from JSON payload or request headers.
 * Useful for AJAX/API endpoints.
 * 
 * @return bool True if valid, false otherwise
 */
function validate_csrf_ajax()
{
    // Try to get token from request header (getallheaders() not available in all SAPI environments)
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strncmp($key, 'HTTP_', 5) === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }
    }
    if (isset($headers['X-CSRF-Token'])) {
        return validate_csrf_token($headers['X-CSRF-Token']);
    }
    if (isset($headers['X-CSRF-TOKEN'])) {
        return validate_csrf_token($headers['X-CSRF-TOKEN']);
    }

    // Try to get from JSON payload
    $json = file_get_contents('php://input');
    if ($json) {
        $data = json_decode($json, true);
        if (isset($data[CSRF_TOKEN_NAME])) {
            return validate_csrf_token($data[CSRF_TOKEN_NAME]);
        }
    }

    // Fallback to regular validation (POST/GET)
    return validate_csrf_token();
}

/**
 * Check if CSRF Token is Expired
 * 
 * Checks if the CSRF token is older than the specified time.
 * Optional feature for additional security.
 * 
 * @param int $max_age Maximum age in seconds (default: 3600 = 1 hour)
 * @return bool True if expired, false if still valid
 */
function is_csrf_token_expired($max_age = 3600)
{
    if (!isset($_SESSION[CSRF_TOKEN_NAME . '_time'])) {
        return true; // No timestamp means token was not properly generated; treat as expired
    }

    $token_age = time() - $_SESSION[CSRF_TOKEN_NAME . '_time'];
    return $token_age > $max_age;
}

/**
 * Display CSRF Error Message
 * 
 * Shows a user-friendly CSRF error message.
 * Use this when CSRF validation fails.
 * 
 * @param bool $with_back_button Include back button (default: true)
 */
function display_csrf_error($with_back_button = true)
{
    $message = defined('MSG_ERROR_INVALID_CSRF') ? MSG_ERROR_INVALID_CSRF : 'Security validation failed. Please try again.';

    echo '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 30px; background: #fff; border: 2px solid #dc3545; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="font-size: 48px; color: #dc3545;">⚠️</div>
        </div>
        <h2 style="color: #dc3545; text-align: center; margin-bottom: 20px;">Security Validation Failed</h2>
        <p style="color: #333; line-height: 1.6; text-align: center;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>
        <p style="color: #666; font-size: 14px; line-height: 1.6; text-align: center; margin-top: 20px;">
            This usually happens when:<br>
            • Your session has expired<br>
            • The form was open for too long<br>
            • You opened the form in multiple browser tabs
        </p>';

    if ($with_back_button) {
        echo '
        <div style="text-align: center; margin-top: 30px;">
            <a href="javascript:history.back()" style="display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Go Back and Try Again
            </a>
        </div>';
    }

    echo '</div>';
}

/**
 * ============================================
 * USAGE EXAMPLES
 * ============================================
 */

/**
 * EXAMPLE 1: Basic Form Protection
 * 
 * In your HTML form:
 * 
 * <form method="POST" action="submit.php">
 *     <?php csrf_token_input(); ?>
 *     <input type="text" name="username">
 *     <button type="submit">Submit</button>
 * </form>
 * 
 * In submit.php:
 * 
 * <?php
 * require_once 'includes/csrf.php';
 * 
 * if ($_SERVER['REQUEST_METHOD'] == 'POST') {
 *     if (!validate_csrf_token()) {
 *         die('Invalid CSRF token');
 *     }
 *     // Process form...
 * }
 * ?>
 */

/**
 * EXAMPLE 2: Quick Form Protection
 * 
 * <?php
 * require_once 'includes/csrf.php';
 * 
 * if ($_SERVER['REQUEST_METHOD'] == 'POST') {
 *     check_csrf_or_die(); // Automatically validates and dies if invalid
 *     // Process form...
 * }
 * ?>
 * 
 * <form method="POST">
 *     <?php csrf_field(); ?>
 *     <!-- form fields -->
 * </form>
 */

/**
 * EXAMPLE 3: AJAX Request Protection
 * 
 * In your HTML <head>:
 * <?php csrf_token_meta(); ?>
 * 
 * In your JavaScript:
 * 
 * // Get CSRF token from meta tag
 * var token = document.querySelector('meta[name="csrf_token"]').getAttribute('content');
 * 
 * // Include in AJAX request
 * fetch('api/endpoint.php', {
 *     method: 'POST',
 *     headers: {
 *         'Content-Type': 'application/json',
 *         'X-CSRF-Token': token
 *     },
 *     body: JSON.stringify(data)
 * });
 * 
 * In api/endpoint.php:
 * <?php
 * require_once '../includes/csrf.php';
 * 
 * if (!validate_csrf_ajax()) {
 *     http_response_code(403);
 *     echo json_encode(['error' => 'Invalid CSRF token']);
 *     exit;
 * }
 * ?>
 */

/**
 * EXAMPLE 4: Convenience Form Function
 * 
 * <?php
 * csrf_form_start('submit.php', 'POST');
 * ?>
 *     <input type="text" name="username">
 *     <button type="submit">Submit</button>
 * </form>
 */

/**
 * EXAMPLE 5: Token Regeneration After Sensitive Action
 * 
 * <?php
 * // After password change or privilege escalation
 * regenerate_csrf_token();
 * ?>
 */

/**
 * ============================================
 * SECURITY BEST PRACTICES
 * ============================================
 * 
 * 1. ALWAYS include CSRF token in forms
 * 2. ALWAYS validate token before processing POST/PUT/DELETE requests
 * 3. Use timing-safe comparison (hash_equals) - already implemented
 * 4. Regenerate token after sensitive operations
 * 5. Use HTTPS to prevent token interception
 * 6. Don't include token in GET requests (tokens in URL can be logged)
 * 7. Token should be unpredictable (use random_bytes)
 * 8. Store token in session, not in database
 * 9. Validate token on server-side, never trust client-side validation
 * 10. Consider token expiration for additional security
 * 
 * WHEN TO USE CSRF PROTECTION:
 * - All forms that modify data (POST, PUT, DELETE)
 * - Any state-changing operations
 * - Admin actions
 * - File uploads
 * - API endpoints that modify data
 * 
 * WHEN NOT NEEDED:
 * - GET requests (should be idempotent anyway)
 * - Public read-only pages
 * - Stateless API with token-based auth (JWT, OAuth)
 */
