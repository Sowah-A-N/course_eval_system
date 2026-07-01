<?php
/**
 * Session Management Functions
 *
 * This file contains all session-related functions for the application.
 *
 * Functions:
 * - start_secure_session() - Initialize session with secure settings
 * - check_login() - Verify user is logged in
 * - check_session_timeout() - Validate session hasn't expired
 * - is_logged_in() - Check if user is authenticated
 * - get_user_id() - Get current user's ID
 * - get_user_role() - Get current user's role
 * - get_user_department() - Get current user's department
 * - get_user_name() - Get current user's full name
 * - logout_user() - Logout and destroy session
 * - regenerate_session() - Regenerate session ID for security
 * - set_session_message() - Set flash message
 * - get_session_message() - Get and clear flash message
 * - update_last_activity() - Update session activity timestamp
 *
 * USAGE:
 * Include this file at the top of pages that need session management
 * require_once 'includes/session.php';
 */

// Ensure constants are loaded
if (!defined('SESSION_TIMEOUT')) {
    require_once dirname(__DIR__) . '/config/constants.php';
}

/**
 * Start Secure Session
 *
 * Initializes a session with secure settings.
 * Call this at the beginning of every page that uses sessions.
 *
 * @return bool True if session started successfully
 */
function start_secure_session() {
    // Check if session is already started
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }

    // Set session name (makes it harder to identify the application)
    session_name(SESSION_NAME);

    // Set session cookie parameters for security
    session_set_cookie_params([
        'lifetime' => SESSION_COOKIE_LIFETIME,
        'path' => SESSION_COOKIE_PATH,
        'domain' => SESSION_COOKIE_DOMAIN,
        'secure' => SESSION_COOKIE_SECURE,      // Set to true if using HTTPS
        'httponly' => SESSION_COOKIE_HTTPONLY,  // Prevent JavaScript access
        'samesite' => SESSION_COOKIE_SAMESITE   // CSRF protection
    ]);

    // Start the session
    if (session_start()) {
        // Set last activity timestamp if not set
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }

        // Set session start time if not set
        if (!isset($_SESSION['session_start'])) {
            $_SESSION['session_start'] = time();
        }

        // Store user agent for validation (helps prevent session hijacking)
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        }

        // Store IP address for validation (optional - can cause issues with dynamic IPs)
        if (!isset($_SESSION['ip_address'])) {
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }

        return true;
    }

    return false;
}

/**
 * Check if User is Logged In
 *
 * Verifies that user is authenticated and session is valid.
 * Redirects to login page if not logged in or session expired.
 * Call this at the top of protected pages.
 *
 * @param bool $redirect Whether to redirect to login if not authenticated
 * @return bool True if logged in, false otherwise
 */
function check_login($redirect = true) {
    // Ensure session is started
    start_secure_session();

    // Check if user_id exists in session
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        if ($redirect) {
            // Store current page for redirect after login.
            // Validation rules — the URI must:
            //   1. Start with '/'        (relative, not protocol-relative or absolute)
            //   2. Not start with '//'   (prevents //evil.com off-origin redirect)
            //   3. Not contain '\'       (browsers normalise /\evil.com → //evil.com)
            //   4. Not contain '..'      (prevents path traversal)
            //   5. Not contain ':'       (prevents javascript: or data: URIs)
            // Any URI that fails these checks is silently discarded; the user
            // will land on their role dashboard instead.
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (
                strpos($uri, '/')    === 0          &&
                strpos($uri, '//')   !== 0           &&
                strpos($uri, '\\')   === false        &&
                strpos($uri, '..')   === false        &&
                strpos($uri, ':')    === false
            ) {
                $_SESSION['redirect_after_login'] = $uri;
            }

            // Redirect to login
            header("Location: " . get_base_url() . "/login.php");
            exit();
        }
        return false;
    }

    // Validate session timeout
    if (!check_session_timeout($redirect)) {
        return false;
    }

    // Validate user agent (helps prevent session hijacking)
    if (isset($_SESSION['user_agent'])) {
        $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        if ($_SESSION['user_agent'] !== $current_user_agent) {
            // User agent changed - possible session hijacking
            logout_user();
            if ($redirect) {
                header("Location: " . get_base_url() . "/login.php?error=session_invalid");
                exit();
            }
            return false;
        }
    }

    // Optional: Validate IP address (commented out - can cause issues with dynamic IPs)
    /*
    if (isset($_SESSION['ip_address'])) {
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        if ($_SESSION['ip_address'] !== $current_ip) {
            logout_user();
            if ($redirect) {
                header("Location: " . get_base_url() . "/login.php?error=session_invalid");
                exit();
            }
            return false;
        }
    }
    */

    // Update last activity
    update_last_activity();

    // C9: intercept any page load when the user has a forced password-change pending.
    // Allow the change_password pages and logout through; block everything else.
    if (!empty($_SESSION['must_change_password'])) {
        $self = $_SERVER['PHP_SELF'] ?? '';
        $allowed = strpos($self, 'change_password') !== false
                || strpos($self, 'logout')          !== false;
        if (!$allowed) {
            $role = $_SESSION['role_id'] ?? 0;
            if (defined('ROLE_STUDENT') && $role === ROLE_STUDENT) {
                header("Location: " . get_base_url() . "/student/profile/change_password.php");
            } else {
                header("Location: " . get_base_url() . "/change_password.php");
            }
            exit();
        }
    }

    return true;
}

/**
 * Check Session Timeout
 *
 * Validates that the session hasn't expired due to inactivity.
 *
 * @param bool $redirect Whether to redirect to login if expired
 * @return bool True if session is valid, false if expired
 */
function check_session_timeout($redirect = true) {
    // ── Absolute lifetime check ──────────────────────────────────────────────
    // Enforce an upper bound on how long ANY session may live, regardless of
    // how active the user is.  Without this a user (or attacker with a stolen
    // cookie) could maintain a session indefinitely by staying active.
    // SESSION_ABSOLUTE_LIFETIME is defined in config/constants.php (default 8 h).
    if (isset($_SESSION['session_start'])) {
        $absolute_age = time() - $_SESSION['session_start'];
        if ($absolute_age > SESSION_ABSOLUTE_LIFETIME) {
            logout_user();
            if ($redirect) {
                header("Location: " . get_base_url() . "/login.php?error=session_expired");
                exit();
            }
            return false;
        }
    }

    // ── Inactivity timeout check ─────────────────────────────────────────────
    if (!isset($_SESSION['last_activity'])) {
        return true; // First request in this session — allow it
    }

    $inactive_time = time() - $_SESSION['last_activity'];

    if ($inactive_time > SESSION_TIMEOUT) {
        logout_user();

        if ($redirect) {
            header("Location: " . get_base_url() . "/login.php?error=session_expired");
            exit();
        }
        return false;
    }

    return true;
}

/**
 * Check if User is Logged In (without redirect)
 *
 * Simple check to see if user is authenticated.
 * Does not redirect or validate timeout.
 *
 * @return bool True if logged in, false otherwise
 */
function is_logged_in() {
    start_secure_session();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get Current User ID
 *
 * @return int|null User ID if logged in, null otherwise
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get Current User Role ID
 *
 * @return int|null Role ID if logged in, null otherwise
 */
function get_user_role() {
    return $_SESSION['role_id'] ?? null;
}

/**
 * Get Current User Department ID
 *
 * @return int|null Department ID if set, null otherwise
 */
function get_user_department() {
    return $_SESSION['department_id'] ?? null;
}

/**
 * Get Current User Class ID
 *
 * @return int|null Class ID if set, null otherwise
 */
function get_user_class() {
    return $_SESSION['class_id'] ?? null;
}

/**
 * Get Current User Level ID
 *
 * @return int|null Level ID if set, null otherwise
 */
function get_user_level() {
    return $_SESSION['level_id'] ?? null;
}

/**
 * Get Current User's Full Name
 *
 * @return string|null Full name if set, null otherwise
 */
function get_user_name() {
    return $_SESSION['full_name'] ?? null;
}

/**
 * Get Current Username
 *
 * @return string|null Username if set, null otherwise
 */
function get_username() {
    return $_SESSION['username'] ?? null;
}

/**
 * Get Current User Email
 *
 * @return string|null Email if set, null otherwise
 */
function get_user_email() {
    return $_SESSION['email'] ?? null;
}

/**
 * Logout User
 *
 * Destroys the session and clears all session data.
 * Does NOT redirect - call header() separately if needed.
 */
function logout_user() {
    // Ensure session is started
    start_secure_session();

    // Unset all session variables
    $_SESSION = array();

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();
}

/**
 * Regenerate Session ID
 *
 * Regenerates the session ID to prevent session fixation attacks.
 * Call this after successful login or privilege escalation.
 *
 * @param bool $delete_old_session Whether to delete the old session
 * @return bool True on success, false on failure
 */
function regenerate_session($delete_old_session = true) {
    return session_regenerate_id($delete_old_session);
}

/**
 * Update Last Activity Timestamp
 *
 * Updates the session's last activity time.
 * This resets the session timeout counter.
 */
function update_last_activity() {
    $_SESSION['last_activity'] = time();
}

/**
 * Set Flash Message
 *
 * Stores a one-time message in the session.
 * Message will be displayed once and then cleared.
 *
 * @param string $message The message to display
 * @param string $type Message type: 'success', 'error', 'warning', 'info'
 */
function set_session_message($message, $type = 'info') {
    start_secure_session();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get Flash Message
 *
 * Retrieves and clears the flash message from session.
 * Returns null if no message exists.
 *
 * @return array|null Array with 'message' and 'type' keys, or null
 */
function get_session_message() {
    start_secure_session();

    if (isset($_SESSION['flash_message'])) {
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];

        // Clear the message
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);

        return $message;
    }

    return null;
}

/**
 * Display Flash Message
 *
 * Displays the flash message if one exists.
 * Automatically includes HTML styling.
 */
function display_session_message() {
    $message = get_session_message();

    if ($message !== null) {
        $type = htmlspecialchars($message['type']);
        $text = htmlspecialchars($message['message']);

        // CSS classes for different message types
        $class_map = [
            'success' => 'alert-success',
            'error' => 'alert-error',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];

        $class = $class_map[$type] ?? 'alert-info';

        // D7: unique ID so the JS auto-dismiss can target exactly this element
        $fid = 'flash-' . substr(md5(uniqid('', true)), 0, 8);
        echo '<div class="alert ' . $class . '" role="alert" id="' . $fid . '" style="position:relative;padding-right:40px">';
        echo $text;
        echo '<button onclick="document.getElementById(\'' . $fid . '\').remove()" '
           . 'style="position:absolute;top:50%;right:10px;transform:translateY(-50%);'
           . 'background:none;border:none;font-size:20px;cursor:pointer;color:inherit;'
           . 'opacity:.65;line-height:1" aria-label="Dismiss">&times;</button>';
        echo '</div>';
        // Auto-dismiss after 5 seconds
        echo '<script>setTimeout(function(){var e=document.getElementById("' . $fid . '");if(e)e.remove();},5000);</script>';
    }
}

/**
 * Get Base URL
 *
 * Helper function to get the application's base URL.
 *
 * @return string Base URL
 */
function get_base_url() {
    // Optional override: if APP_URL is set in the environment, honor it as an
    // absolute base URL. This lets a reverse-proxy or custom deployment pin the
    // public URL when it can't be inferred from the filesystem layout.
    $env_url = getenv('APP_URL');
    if ($env_url !== false && trim($env_url) !== '') {
        return rtrim(trim($env_url), '/');
    }

    // Default: return a ROOT-RELATIVE base path (e.g. "/course_evaluation" in a
    // subdirectory install, or "" when the app sits at the web root). Emitting a
    // path instead of a full scheme://host URL means:
    //   - it works unchanged on localhost and on any production domain with no
    //     configuration (the same code runs in every environment);
    //   - the browser resolves the relative Location: against the current
    //     origin, so a forged Host header cannot redirect users off-site —
    //     host-header injection is impossible because no host is ever emitted.
    //
    // The path is derived from the application's own location on disk relative
    // to the web server's document root — never from a request header. (The
    // password-reset email flow, which needs an absolute URL, uses APP_URL
    // directly and is unaffected by this function.)
    $app_dir  = realpath(defined('BASE_DIR') ? BASE_DIR : dirname(__DIR__));
    $doc_root = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

    if ($app_dir !== false && $doc_root !== false) {
        $app_dir  = str_replace('\\', '/', rtrim($app_dir, '/\\'));
        $doc_root = str_replace('\\', '/', rtrim($doc_root, '/\\'));
        // Case-insensitive prefix match (Windows paths differ only by case).
        if (stripos($app_dir, $doc_root) === 0) {
            return rtrim(substr($app_dir, strlen($doc_root)), '/');
        }
    }

    // Could not match the document root (e.g. symlinked webroot). Fall back to
    // origin-root so redirects still stay on-site.
    return '';
}

/**
 * Redirect to Login
 *
 * Helper function to redirect to login page.
 *
 * @param string $error Optional error message parameter
 */
function redirect_to_login($error = '') {
    $url = get_base_url() . '/login.php';
    if (!empty($error)) {
        $url .= '?error=' . urlencode($error);
    }
    header("Location: $url");
    exit();
}

/**
 * Redirect to Dashboard
 *
 * Redirects user to their appropriate dashboard based on role.
 */
function redirect_to_dashboard() {
    $role_id = get_user_role();

    if ($role_id === null) {
        redirect_to_login();
    }

    $base_url = get_base_url();

    switch ($role_id) {
        case ROLE_ADMIN:
            header("Location: {$base_url}/admin/index.php");
            break;
        case ROLE_HOD:
            header("Location: {$base_url}/hod/index.php");
            break;
        case ROLE_SECRETARY:
            header("Location: {$base_url}/secretary/index.php");
            break;
        case ROLE_ADVISOR:
            header("Location: {$base_url}/advisor/index.php");
            break;
        case ROLE_STUDENT:
            header("Location: {$base_url}/student/index.php");
            break;
        case ROLE_QUALITY:
            header("Location: {$base_url}/quality/index.php");
            break;
        default:
            redirect_to_login('invalid_role');
    }
    exit();
}

/**
 * Get Session Age
 *
 * Returns how long the current session has been active.
 *
 * @return int|null Session age in seconds, or null if not set
 */
function get_session_age() {
    if (isset($_SESSION['session_start'])) {
        return time() - $_SESSION['session_start'];
    }
    return null;
}

/**
 * Get Time Until Session Expires
 *
 * Returns how many seconds until the session expires.
 *
 * @return int|null Seconds until expiration, or null if not set
 */
function get_time_until_expiration() {
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        return SESSION_TIMEOUT - $inactive_time;
    }
    return null;
}

/**
 * Is Session About to Expire
 *
 * Checks if session will expire within the specified number of seconds.
 * Useful for displaying warnings to users.
 *
 * @param int $seconds Warning threshold in seconds (default: 300 = 5 minutes)
 * @return bool True if session expires soon, false otherwise
 */
function is_session_expiring_soon($seconds = 300) {
    $time_left = get_time_until_expiration();
    return $time_left !== null && $time_left <= $seconds;
}

/**
 * USAGE EXAMPLES:
 *
 * // At the top of every protected page:
 * require_once 'includes/session.php';
 * start_secure_session();
 * check_login();
 *
 * // Get current user information:
 * $user_id = get_user_id();
 * $role_id = get_user_role();
 * $name = get_user_name();
 *
 * // Set a success message after an action:
 * set_session_message('Record created successfully!', 'success');
 * header("Location: list.php");
 *
 * // Display flash message on the next page:
 * display_session_message();
 *
 * // Check if user is logged in without redirecting:
 * if (is_logged_in()) {
 *     echo "Welcome back!";
 * }
 *
 * // Logout user:
 * logout_user();
 * header("Location: login.php");
 *
 * // Regenerate session after login:
 * regenerate_session(true);
 */

/**
 * SECURITY NOTES:
 *
 * 1. Always call start_secure_session() before any output
 * 2. Call check_login() at the top of all protected pages
 * 3. Regenerate session ID after login with regenerate_session()
 * 4. Set SESSION_COOKIE_SECURE to true in production (HTTPS)
 * 5. User agent validation helps prevent session hijacking
 * 6. IP validation is optional (can cause issues with dynamic IPs)
 * 7. Regular session timeout prevents abandoned sessions
 * 8. Flash messages prevent duplicate form submissions
 * 9. Never store sensitive data directly in sessions
 * 10. Always validate session data before use
 */
?>
