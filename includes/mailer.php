<?php
/**
 * Lightweight transactional mailer for account notifications.
 *
 * Uses PHP's built-in mail(). It is best-effort: every function here returns a
 * boolean and never throws, so a mail failure (e.g. no MTA configured on a dev
 * box) never blocks account creation. Callers show the credentials on screen as
 * a fallback regardless of whether the email goes out.
 */

/**
 * Absolute base URL for links placed inside emails.
 *
 * Emails need a real scheme+host (unlike the on-site relative redirects that
 * get_base_url() returns). Resolution order:
 *   1. APP_URL environment variable, if set (best for production / proxies).
 *   2. Otherwise build it from the current request: scheme + host + the app's
 *      root-relative base path.
 */
function ces_absolute_base_url(): string {
    $env = getenv('APP_URL');
    if ($env !== false && trim($env) !== '') {
        return rtrim(trim($env), '/');
    }

    $scheme = 'http';
    if ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)) {
        $scheme = 'https';
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = function_exists('get_base_url') ? get_base_url() : '';
    // When APP_URL env is set, get_base_url() already returns an absolute URL
    // (handled above); here it is a root-relative path like "/course_evaluation".
    if (preg_match('#^https?://#i', $path)) {
        return rtrim($path, '/');
    }
    return $scheme . '://' . $host . $path;
}

/**
 * Email a newly created user their login details.
 *
 * @param string $to_email  recipient email
 * @param string $full_name recipient full name
 * @param string $username  their username (they can sign in with username OR email)
 * @param string $password  the initial password they were given
 * @return bool  true if mail() accepted the message, false otherwise
 */
function ces_send_login_details(string $to_email, string $full_name, string $username, string $password): bool {
    $to_email = trim($to_email);
    if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $app       = defined('APP_NAME') ? APP_NAME : 'Course Evaluation System';
    $inst      = defined('INSTITUTION_NAME') ? INSTITUTION_NAME : $app;
    $login_url = ces_absolute_base_url() . '/login.php';

    $subject = 'Your ' . $app . ' account';

    $body  = 'Hello ' . $full_name . ",\n\n";
    $body .= 'An account has been created for you on the ' . $app . ".\n\n";
    $body .= "Sign in here:\n" . $login_url . "\n\n";
    $body .= "Your login details:\n";
    $body .= '  Username: ' . ($username !== '' ? $username : $to_email) . "\n";
    $body .= '  Email:    ' . $to_email . "   (you can sign in with either)\n";
    $body .= '  Password: ' . $password . "\n\n";
    $body .= "For your security you will be asked to change this password the first time you sign in.\n\n";
    $body .= "If you did not expect this email, please contact your administrator.\n\n";
    $body .= '— ' . $inst;

    $from_name = defined('SYSTEM_EMAIL_NAME') ? SYSTEM_EMAIL_NAME : $app;
    $from_addr = defined('SYSTEM_EMAIL_FROM') ? SYSTEM_EMAIL_FROM : ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

    $headers  = 'From: ' . $from_name . ' <' . $from_addr . ">\r\n";
    $headers .= 'Reply-To: ' . $from_addr . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    return @mail($to_email, $subject, $body, $headers);
}
