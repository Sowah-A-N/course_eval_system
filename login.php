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
            $stmt_check = mysqli_prepare($conn,
                "SELECT COUNT(*) AS attempt_count FROM login_attempts
                 WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
            mysqli_stmt_bind_param($stmt_check, 'si', $ip, LOGIN_LOCKOUT_TIME);
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
    <title>Login - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .login-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            text-align: center;
            font-size: 13px;
            color: #666;
        }

        @media (max-width: 480px) {
            .login-header h1 {
                font-size: 20px;
            }

            .login-body {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <h1><?php echo APP_NAME; ?></h1>
            <p><?php echo INSTITUTION_NAME; ?></p>
        </div>

        <!-- Body -->
        <div class="login-body">
            <!-- Display error message if exists -->
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <!-- CSRF Token (generated via csrf.php) -->
                <?php csrf_token_input(); ?>

                <!-- Username or Email -->
                <div class="form-group">
                    <label for="username_email">Username or Email</label>
                    <input
                        type="text"
                        id="username_email"
                        name="username_email"
                        value="<?php echo htmlspecialchars($username_email); ?>"
                        required
                        autofocus
                        placeholder="Enter your username or email">
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        placeholder="Enter your password">
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-login">Login</button>
            </form>
        </div>

        <!-- Footer -->
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> <?php echo INSTITUTION_NAME; ?>. All rights reserved.
        </div>
    </div>
</body>

</html>
