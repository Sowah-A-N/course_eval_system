<?php
/**
 * Forgot Password
 *
 * Accepts an email address and, if it matches an active account, sends a
 * one-time password-reset link to that address.
 *
 * Security properties:
 *  - Enumeration-safe: the same success message is shown regardless of
 *    whether the email exists in the database.
 *  - Token: 32 random bytes (bin2hex → 64-char hex string), stored as
 *    SHA-256 hash in the DB; the raw token travels only in the email.
 *  - Expiry: 15 minutes (PASSWORD_RESET_TTL seconds).
 *  - One active token per user: any previous unused token is invalidated
 *    before a new one is issued, preventing token accumulation.
 *  - Rate limiting: a user can request at most one reset per 2 minutes
 *    (checked server-side, not just client-side).
 *  - CSRF: the form is protected by the standard csrf_token_input() token.
 */

require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/session.php';
require_once 'includes/csrf.php';

start_secure_session();

// Already logged in — no need to reset
if (isset($_SESSION['user_id'])) {
    header("Location: " . get_base_url() . "/login.php");
    exit();
}

$message      = '';
$message_type = 'info';
$submitted    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $message      = 'Invalid security token. Please refresh and try again.';
        $message_type = 'error';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message      = 'Please enter a valid email address.';
            $message_type = 'error';
        } else {
            // Enumeration-safe: always show the same message after this point.
            $submitted = true;

            // Look up the account (do not reveal whether it exists)
            $stmt = mysqli_prepare($conn,
                "SELECT user_id FROM user_details
                 WHERE email = ? AND is_active = 1 LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($user) {
                $user_id = (int)$user['user_id'];
                $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

                // Rate limit: block if a token was issued in the last 2 minutes
                $stmt_rate = mysqli_prepare($conn,
                    "SELECT id FROM password_reset_tokens
                     WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                     LIMIT 1");
                mysqli_stmt_bind_param($stmt_rate, 'i', $user_id);
                mysqli_stmt_execute($stmt_rate);
                $recent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rate));
                mysqli_stmt_close($stmt_rate);

                if (!$recent) {
                    // Invalidate any existing unused tokens for this user
                    $stmt_del = mysqli_prepare($conn,
                        "DELETE FROM password_reset_tokens
                         WHERE user_id = ? AND used_at IS NULL");
                    mysqli_stmt_bind_param($stmt_del, 'i', $user_id);
                    mysqli_stmt_execute($stmt_del);
                    mysqli_stmt_close($stmt_del);

                    // Generate token: raw (sent in email) + hash (stored in DB)
                    $raw_token  = bin2hex(random_bytes(32)); // 64-char hex
                    $token_hash = hash('sha256', $raw_token);
                    $ttl        = defined('PASSWORD_RESET_TTL') ? PASSWORD_RESET_TTL : 900; // 15 min

                    $stmt_ins = mysqli_prepare($conn,
                        "INSERT INTO password_reset_tokens
                             (user_id, token_hash, expires_at, ip_address)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)");
                    mysqli_stmt_bind_param($stmt_ins, 'isis', $user_id, $token_hash, $ttl, $ip);
                    mysqli_stmt_execute($stmt_ins);
                    mysqli_stmt_close($stmt_ins);

                    // Build reset URL
                    $reset_url = rtrim(APP_URL, '/') . '/reset_password.php?token=' . urlencode($raw_token);

                    $to      = $email;
                    $subject = 'Password Reset — ' . APP_NAME;
                    $body    = "You requested a password reset for your " . APP_NAME . " account.\n\n"
                             . "Click the link below to set a new password. This link expires in "
                             . round($ttl / 60) . " minutes.\n\n"
                             . $reset_url . "\n\n"
                             . "If you did not request this, you can safely ignore this email.\n\n"
                             . "— " . INSTITUTION_NAME;
                    $headers = "From: " . SYSTEM_EMAIL_NAME . " <" . SYSTEM_EMAIL_FROM . ">\r\n"
                             . "Reply-To: " . SYSTEM_EMAIL_FROM . "\r\n"
                             . "X-Mailer: PHP/" . phpversion();

                    $mail_sent = @mail($to, $subject, $body, $headers);
                }
                // Whether rate-limited or not — same message (enumeration safe)
            }

            $message      = 'If an account exists for that email address, a reset link has been sent. Check your inbox (and spam folder).';
            $message_type = 'success';
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
    <title>Forgot Password &mdash; <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
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
        .form-control { width: 100%; padding: 10px 12px; font-size: 15px; border: 1px solid #d1d5db;
                        border-radius: 8px; }
        .form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.25); }
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
        <h1 class="card__title">Reset your password</h1>
        <p class="card__sub">Enter your email address and we'll send a reset link.</p>
    </div>
    <div class="card__body">
        <?php if ($message): ?>
            <div class="alert alert--<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!$submitted): ?>
        <form method="POST" novalidate>
            <?php csrf_token_input(); ?>
            <div class="form-group">
                <label class="form-label" for="email">Email address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="you@example.com" autocomplete="email" required autofocus>
            </div>
            <button type="submit" class="btn-primary">Send Reset Link</button>
        </form>
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
