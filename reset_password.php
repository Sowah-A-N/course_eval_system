<?php
/**
 * Reset Password
 *
 * Validates the one-time reset token from the URL, then allows the user
 * to set a new password.
 *
 * Security properties:
 *  - Token is validated as: raw_token → SHA-256 → matches stored hash
 *  - Token must be unused (used_at IS NULL) and not expired
 *  - Token is consumed immediately on first valid page load (not on submit)
 *    to prevent replay after a form error — a new token must be requested
 *  - Actually: token is consumed on SUCCESSFUL password change only, so the
 *    user can correct form errors without requesting a new link
 *  - Session is regenerated after the password change to prevent fixation
 *  - CSRF: form protected by csrf_token_input()
 *  - Password policy: same rules as create.php
 */

require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/session.php';
require_once 'includes/csrf.php';

start_secure_session();

if (isset($_SESSION['user_id'])) {
    header("Location: " . get_base_url() . "/login.php");
    exit();
}

$error       = '';
$success     = false;
$token_valid = false;
$user_id     = null;
$token_hash  = '';

// ── Validate token from URL ──────────────────────────────────────────────────
$raw_token = trim($_GET['token'] ?? '');

if (strlen($raw_token) !== 64 || !ctype_xdigit($raw_token)) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
} else {
    $token_hash = hash('sha256', $raw_token);

    $stmt = mysqli_prepare($conn,
        "SELECT id, user_id, expires_at, used_at
         FROM password_reset_tokens
         WHERE token_hash = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $token_hash);
    mysqli_stmt_execute($stmt);
    $tok = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$tok) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif ($tok['used_at'] !== null) {
        $error = 'This reset link has already been used. Please request a new one.';
    } elseif (strtotime($tok['expires_at']) < time()) {
        $error = 'This reset link has expired. Please request a new one.';
    } else {
        $token_valid = true;
        $user_id     = (int)$tok['user_id'];
    }
}

// ── Handle form submission ───────────────────────────────────────────────────
if ($token_valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $error       = 'Invalid security token. Please try again.';
        $token_valid = false;
    } else {
        $new_password  = $_POST['new_password']  ?? '';
        $conf_password = $_POST['conf_password'] ?? '';

        if (empty($new_password)) {
            $error = 'Please enter a new password.';
        } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $error = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $error = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $error = 'Password must contain at least one number.';
        } elseif ($new_password !== $conf_password) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);

            mysqli_begin_transaction($conn);
            try {
                // Update password
                $stmt_pw = mysqli_prepare($conn,
                    "UPDATE user_details SET password = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt_pw, 'si', $hash, $user_id);
                mysqli_stmt_execute($stmt_pw);
                mysqli_stmt_close($stmt_pw);

                // Mark token as used — single-use enforcement
                $stmt_use = mysqli_prepare($conn,
                    "UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?");
                mysqli_stmt_bind_param($stmt_use, 's', $token_hash);
                mysqli_stmt_execute($stmt_use);
                mysqli_stmt_close($stmt_use);

                mysqli_commit($conn);

                // Regenerate session to prevent fixation
                session_regenerate_id(true);

                $success     = true;
                $token_valid = false; // hide the form
            } catch (Exception $e) {
                mysqli_rollback($conn);
                error_log('[CES] Password reset failed for user_id=' . $user_id . ': ' . $e->getMessage());
                $error = 'A system error occurred. Please try again or contact the administrator.';
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
    <title>Set New Password &mdash; <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f3f4f6;
               display: flex; flex-direction: column; align-items: center;
               justify-content: center; min-height: 100vh; padding: 24px 16px; }
        .card { width: 100%; max-width: 420px; background: #fff; border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,.07); overflow: hidden; }
        .card__header { padding: 28px 32px 20px; border-bottom: 1px solid #e5e7eb; }
        .card__title  { font-size: 20px; font-weight: 700; }
        .card__sub    { font-size: 14px; color: #6b7280; margin-top: 4px; }
        .card__body   { padding: 24px 32px 28px; }
        .alert        { padding: 12px 14px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert--error   { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }
        .alert--success { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
        .form-group   { margin-bottom: 18px; }
        .form-label   { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 12px; font-size: 15px;
                        border: 1px solid #d1d5db; border-radius: 8px; }
        .form-control:focus { outline: none; border-color: #4f46e5;
                              box-shadow: 0 0 0 3px rgba(79,70,229,.25); }
        .hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .btn-primary { display: block; width: 100%; padding: 11px; font-size: 15px; font-weight: 600;
                       color: #fff; background: linear-gradient(135deg, #6366f1, #3730a3);
                       border: none; border-radius: 8px; cursor: pointer; }
        .back-link   { display: block; text-align: center; margin-top: 16px; font-size: 14px; color: #4f46e5; }
        .card__footer { padding: 14px 32px; border-top: 1px solid #e5e7eb; background: #fafafa;
                        text-align: center; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
<main>
<div class="card">
    <div class="card__header">
        <h1 class="card__title">Set a new password</h1>
        <p class="card__sub">Choose a strong password for your account.</p>
    </div>
    <div class="card__body">

        <?php if ($error): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert--success">
                Your password has been updated successfully. You can now sign in with your new password.
            </div>
            <a href="login.php" class="btn-primary" style="text-align:center;text-decoration:none;display:block;padding:11px">
                Sign In
            </a>

        <?php elseif ($token_valid): ?>
            <form method="POST" novalidate>
                <?php csrf_token_input(); ?>
                <div class="form-group">
                    <label class="form-label" for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password"
                           class="form-control" autocomplete="new-password"
                           required autofocus>
                    <p class="hint">
                        Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters —
                        must include an uppercase letter, a lowercase letter, and a number.
                    </p>
                </div>
                <div class="form-group">
                    <label class="form-label" for="conf_password">Confirm new password</label>
                    <input type="password" id="conf_password" name="conf_password"
                           class="form-control" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn-primary">Set New Password</button>
            </form>

        <?php elseif (!$success): ?>
            <a href="forgot_password.php" class="btn-primary" style="text-align:center;text-decoration:none;display:block;padding:11px">
                Request a New Reset Link
            </a>
        <?php endif; ?>

        <a href="login.php" class="back-link">← Back to Sign In</a>
    </div>
    <div class="card__footer">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(INSTITUTION_NAME, ENT_QUOTES, 'UTF-8'); ?>
    </div>
</div>
</main>
</body>
</html>
