<?php

/**
 * Login Page
 *
 * Handles user authentication and redirects to appropriate dashboard
 * based on user role.
 *
 * Features:
 * - Username or email login
 * - Password verification
 * - CSRF protection (timing-safe via hash_equals in csrf.php)
 * - Login attempt tracking (IP-based, stored in login_attempts table)
 * - Account lockout after failed attempts
 * - Role-based redirection
 * - Session security
 */

// Include required files
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/session.php';
require_once 'includes/csrf.php';
require_once 'includes/audit.php';

// Start secure session
start_secure_session();

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {
    switch ($_SESSION['role_id']) {
        case ROLE_ADMIN:     header("Location: admin/index.php");     exit();
        case ROLE_HOD:       header("Location: hod/index.php");       exit();
        case ROLE_SECRETARY: header("Location: secretary/index.php"); exit();
        case ROLE_ADVISOR:   header("Location: advisor/index.php");   exit();
        case ROLE_STUDENT:   header("Location: student/index.php");   exit();
        case ROLE_QUALITY:   header("Location: quality/index.php");   exit();
        default:             session_destroy(); break;
    }
}

// Initialize variables
$error = '';
$username_email = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validate CSRF token (timing-safe hash_equals comparison via csrf.php)
    if (!validate_csrf_token()) {
        $error = MSG_ERROR_INVALID_CSRF;
    } else {

        // Get and sanitize inputs
        $username_email = trim($_POST['username_email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate required fields
        if (empty($username_email) || empty($password)) {
            $error = MSG_ERROR_REQUIRED_FIELDS;
        } else {

            // IP-based login rate limiting
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $lockout_time = LOGIN_LOCKOUT_TIME; // bind_param requires variables, not constants
            $stmt_check = mysqli_prepare($conn,
                "SELECT COUNT(*) AS attempt_count FROM login_attempts
                 WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
            mysqli_stmt_bind_param($stmt_check, 'si', $ip, $lockout_time);
            mysqli_stmt_execute($stmt_check);
            $attempt_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
            mysqli_stmt_close($stmt_check);

            if ($attempt_row && (int)$attempt_row['attempt_count'] >= MAX_LOGIN_ATTEMPTS) {
                $error = MSG_ERROR_ACCOUNT_LOCKED;
                log_audit($conn, null, AUDIT_LOGIN_FAILED, null, null,
                    ['reason' => 'rate_limited', 'ip' => $ip, 'username' => $username_email], null);
            } else {

                // Prepare query to find user by username or email
                $query = "SELECT
                            user_id, username, email, password, role_id,
                            department_id, class_id, level_id, f_name, l_name, is_active
                          FROM " . TABLE_USER_DETAILS . "
                          WHERE (username = ? OR email = ?)
                          LIMIT 1";

                $stmt = mysqli_prepare($conn, $query);

                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ss", $username_email, $username_email);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);

                    if ($user = mysqli_fetch_assoc($result)) {

                        if ($user['is_active'] != 1) {
                            $error = "Your account has been deactivated. Please contact the administrator.";
                        } else {

                            if (password_verify($password, $user['password'])) {

                                // Clear failed attempts for this IP on successful login
                                $stmt_clear = mysqli_prepare($conn,
                                    "DELETE FROM login_attempts WHERE ip_address = ?");
                                mysqli_stmt_bind_param($stmt_clear, 's', $ip);
                                mysqli_stmt_execute($stmt_clear);
                                mysqli_stmt_close($stmt_clear);

                                // Set session variables
                                $_SESSION['user_id']       = (int)$user['user_id'];
                                $_SESSION['role_id']       = (int)$user['role_id'];
                                $_SESSION['department_id'] = $user['department_id'] ? (int)$user['department_id'] : null;
                                $_SESSION['class_id']      = $user['class_id']      ? (int)$user['class_id']      : null;
                                $_SESSION['level_id']      = $user['level_id']      ? (int)$user['level_id']      : null;
                                $_SESSION['username']      = $user['username'];
                                $_SESSION['email']         = $user['email'];
                                $_SESSION['full_name']     = $user['f_name'] . ' ' . $user['l_name'];
                                $_SESSION['last_activity'] = time();
                                $_SESSION['login_time']    = time();

                                // Regenerate session ID for security (prevents session fixation)
                                session_regenerate_id(true);

                                // Log successful login
                                log_audit($conn, $user['user_id'], AUDIT_LOGIN,
                                    TABLE_USER_DETAILS, $user['user_id'], null, null);

                                // Redirect to the page the user originally tried to access
                                if (!empty($_SESSION['redirect_after_login'])) {
                                    $redirect_url = $_SESSION['redirect_after_login'];
                                    unset($_SESSION['redirect_after_login']);
                                    header("Location: " . $redirect_url);
                                    exit();
                                }
                                unset($_SESSION['redirect_after_login']);

                                // Default: redirect based on role
                                switch ($user['role_id']) {
                                    case ROLE_ADMIN:     header("Location: admin/index.php");     exit();
                                    case ROLE_HOD:       header("Location: hod/index.php");       exit();
                                    case ROLE_SECRETARY: header("Location: secretary/index.php"); exit();
                                    case ROLE_ADVISOR:   header("Location: advisor/index.php");   exit();
                                    case ROLE_STUDENT:   header("Location: student/index.php");   exit();
                                    case ROLE_QUALITY:   header("Location: quality/index.php");   exit();
                                    default:             $error = "Invalid user role. Please contact administrator.";
                                }

                            } else {
                                // Record failed attempt
                                $uname_attempted = substr($username_email, 0, 100);
                                $stmt_fail = mysqli_prepare($conn,
                                    "INSERT INTO login_attempts (ip_address, username_attempted) VALUES (?, ?)");
                                mysqli_stmt_bind_param($stmt_fail, 'ss', $ip, $uname_attempted);
                                mysqli_stmt_execute($stmt_fail);
                                mysqli_stmt_close($stmt_fail);

                                log_audit($conn, null, AUDIT_LOGIN_FAILED,
                                    TABLE_USER_DETAILS, $user['user_id'],
                                    ['username' => $username_email, 'reason' => 'wrong_password'], null);

                                $error = MSG_ERROR_INVALID_LOGIN;
                            }
                        }
                    } else {
                        // User not found — record failed attempt to limit enumeration
                        $uname_attempted = substr($username_email, 0, 100);
                        $stmt_fail = mysqli_prepare($conn,
                            "INSERT INTO login_attempts (ip_address, username_attempted) VALUES (?, ?)");
                        mysqli_stmt_bind_param($stmt_fail, 'ss', $ip, $uname_attempted);
                        mysqli_stmt_execute($stmt_fail);
                        mysqli_stmt_close($stmt_fail);

                        log_audit($conn, null, AUDIT_LOGIN_FAILED, null, null,
                            ['username' => $username_email, 'reason' => 'user_not_found'], null);

                        $error = MSG_ERROR_INVALID_LOGIN;
                    }

                    mysqli_stmt_close($stmt);
                } else {
                    $error = MSG_ERROR_DATABASE;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>Sign In &mdash; <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        /* ── Reset & Base ─────────────────────────────────────── */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --color-primary:        #4f46e5;
            --color-primary-dark:   #3730a3;
            --color-primary-light:  #6366f1;
            --color-primary-subtle: #eef2ff;

            --color-text-base:    #111827;
            --color-text-muted:   #6b7280;
            --color-text-inverse: #ffffff;

            --color-border:       #d1d5db;
            --color-border-focus: #4f46e5;
            --color-bg-page:      #f3f4f6;
            --color-bg-card:      #ffffff;

            --color-error-bg:     #fef2f2;
            --color-error-border: #fca5a5;
            --color-error-text:   #b91c1c;

            --radius-sm:  4px;
            --radius-md:  8px;
            --radius-lg:  12px;

            --shadow-card: 0 4px 6px -1px rgba(0,0,0,.07),
                           0 2px 4px -2px rgba(0,0,0,.05);
            --shadow-focus: 0 0 0 3px rgba(79,70,229,.25);

            --font-sans: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont,
                         'Helvetica Neue', Arial, sans-serif;

            --transition-fast: 150ms ease;
        }

        html {
            height: 100%;
        }

        body {
            min-height: 100%;
            font-family: var(--font-sans);
            font-size: 16px;
            line-height: 1.5;
            color: var(--color-text-base);
            background-color: var(--color-bg-page);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        /* ── Brand bar above card ─────────────────────────────── */
        .brand {
            text-align: center;
            margin-bottom: 20px;
        }

        .brand__logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary-light), var(--color-primary-dark));
            margin-bottom: 10px;
        }

        .brand__logo svg {
            width: 28px;
            height: 28px;
            fill: var(--color-text-inverse);
        }

        .brand__name {
            font-size: 15px;
            font-weight: 600;
            color: var(--color-text-base);
            letter-spacing: .01em;
        }

        .brand__institution {
            font-size: 13px;
            color: var(--color-text-muted);
        }

        /* ── Card ─────────────────────────────────────────────── */
        .card {
            width: 100%;
            max-width: 420px;
            background: var(--color-bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .card__header {
            padding: 28px 32px 20px;
            border-bottom: 1px solid var(--color-border);
        }

        .card__title {
            font-size: 20px;
            font-weight: 700;
            color: var(--color-text-base);
            line-height: 1.3;
        }

        .card__subtitle {
            margin-top: 4px;
            font-size: 14px;
            color: var(--color-text-muted);
        }

        .card__body {
            padding: 24px 32px 28px;
        }

        /* ── Alert ────────────────────────────────────────────── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.45;
        }

        .alert--error {
            background: var(--color-error-bg);
            border: 1px solid var(--color-error-border);
            color: var(--color-error-text);
        }

        .alert__icon {
            flex-shrink: 0;
            margin-top: 1px;
        }

        .alert__icon svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        /* ── Form ─────────────────────────────────────────────── */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group:last-of-type {
            margin-bottom: 22px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--color-text-base);
            margin-bottom: 6px;
        }

        .form-label .required-mark {
            color: var(--color-error-text);
            margin-left: 2px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--color-text-muted);
            display: flex;
            align-items: center;
        }

        .input-icon svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px 10px 38px;
            font-family: inherit;
            font-size: 15px;
            line-height: 1.5;
            color: var(--color-text-base);
            background: var(--color-bg-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            transition: border-color var(--transition-fast),
                        box-shadow var(--transition-fast);
            -webkit-appearance: none;
            appearance: none;
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-border-focus);
            box-shadow: var(--shadow-focus);
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: var(--color-text-muted);
            display: flex;
            align-items: center;
            border-radius: var(--radius-sm);
            transition: color var(--transition-fast);
        }

        .password-toggle:hover {
            color: var(--color-primary);
        }

        .password-toggle:focus-visible {
            outline: 2px solid var(--color-border-focus);
            outline-offset: 1px;
        }

        .password-toggle svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        /* password field has icon on both sides — adjust right padding */
        .form-control--password {
            padding-right: 40px;
        }

        /* ── Submit button ────────────────────────────────────── */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 11px 20px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            color: var(--color-text-inverse);
            background: linear-gradient(135deg, var(--color-primary-light), var(--color-primary-dark));
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: opacity var(--transition-fast), box-shadow var(--transition-fast);
            -webkit-appearance: none;
            appearance: none;
        }

        .btn-primary:hover {
            opacity: .92;
            box-shadow: 0 4px 12px rgba(79,70,229,.35);
        }

        .btn-primary:active {
            opacity: 1;
            box-shadow: none;
        }

        .btn-primary:focus-visible {
            outline: none;
            box-shadow: var(--shadow-focus);
        }

        .btn-primary svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        /* ── Footer ───────────────────────────────────────────── */
        .card__footer {
            padding: 14px 32px;
            border-top: 1px solid var(--color-border);
            background: #fafafa;
            text-align: center;
            font-size: 12px;
            color: var(--color-text-muted);
        }

        /* ── Responsive ───────────────────────────────────────── */
        @media (max-width: 480px) {
            .card__header,
            .card__body {
                padding-left: 20px;
                padding-right: 20px;
            }
            .card__footer {
                padding-left: 20px;
                padding-right: 20px;
            }
        }

        /* ── Reduced motion ───────────────────────────────────── */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition-duration: 0ms !important;
            }
        }
    </style>
</head>

<body>

    <!-- Brand / Logo -->
    <div class="brand" aria-hidden="true">
        <div class="brand__logo">
            <!-- Academic cap icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zm7 13.09L12 19 5 15.09V12l7 3.82L19 12v4.09z"/>
            </svg>
        </div>
        <div class="brand__name"><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="brand__institution"><?php echo htmlspecialchars(INSTITUTION_NAME, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <!-- Login Card -->
    <main>
        <div class="card" role="main">

            <div class="card__header">
                <h1 class="card__title">Sign in to your account</h1>
                <p class="card__subtitle">Enter your credentials to continue</p>
            </div>

            <div class="card__body">

                <!-- Error Alert -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert--error" role="alert" aria-live="assertive">
                        <span class="alert__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" focusable="false">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form
                    method="POST"
                    action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>"
                    novalidate
                    aria-label="Sign in form"
                >
                    <!-- CSRF Token -->
                    <?php csrf_token_input(); ?>

                    <!-- Username or Email -->
                    <div class="form-group">
                        <label class="form-label" for="username_email">
                            Username or Email
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>
                        <div class="input-wrapper">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" focusable="false">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            <input
                                type="text"
                                id="username_email"
                                name="username_email"
                                class="form-control"
                                value="<?php echo htmlspecialchars($username_email, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="you@example.com or username"
                                autocomplete="username"
                                autocapitalize="none"
                                autocorrect="off"
                                spellcheck="false"
                                required
                                autofocus
                                aria-required="true"
                                aria-describedby="<?php echo !empty($error) ? 'login-error' : ''; ?>"
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label class="form-label" for="password">
                            Password
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>
                        <div class="input-wrapper">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" focusable="false">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control form-control--password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                                aria-required="true"
                            >
                            <button
                                type="button"
                                class="password-toggle"
                                id="toggle-password"
                                aria-label="Show password"
                                aria-controls="password"
                                aria-pressed="false"
                            >
                                <!-- Eye icon (show) -->
                                <svg id="icon-eye" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                </svg>
                                <!-- Eye-off icon (hide) — hidden by default -->
                                <svg id="icon-eye-off" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true" style="display:none">
                                    <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/>
                                    <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.064 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn-primary">
                        <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Sign In
                    </button>

                </form>
            </div>

            <div class="card__footer">
                &copy; <?php echo date('Y'); ?>
                <?php echo htmlspecialchars(INSTITUTION_NAME, ENT_QUOTES, 'UTF-8'); ?>.
                All rights reserved.
            </div>

        </div><!-- /.card -->
    </main>

    <script>
        // Password visibility toggle — no dependencies
        (function () {
            'use strict';

            var btn    = document.getElementById('toggle-password');
            var field  = document.getElementById('password');
            var iconOn = document.getElementById('icon-eye');
            var iconOff= document.getElementById('icon-eye-off');

            if (!btn || !field) return;

            btn.addEventListener('click', function () {
                var show = field.type === 'password';
                field.type          = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed',  show ? 'true' : 'false');
                btn.setAttribute('aria-label',    show ? 'Hide password' : 'Show password');
                iconOn.style.display  = show ? 'none'  : '';
                iconOff.style.display = show ? ''      : 'none';
            });
        }());
    </script>

</body>

</html>
