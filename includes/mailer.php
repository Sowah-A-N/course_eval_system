<?php
/**
 * Transactional mailer for account notifications, built on PHPMailer.
 *
 * Delivery path:
 *   - If SMTP is configured (SMTP_HOST environment variable set) the message is
 *     sent over authenticated SMTP with STARTTLS/SSL — reliable on hosts where
 *     PHP's mail() is disabled or unconfigured.
 *   - Otherwise PHPMailer uses PHP's mail().
 *   - If the PHPMailer library is somehow missing, it falls back to raw mail().
 *
 * Best-effort: every function returns a boolean and never throws, so a mail
 * failure never blocks account creation (credentials are also shown on screen).
 *
 * SMTP is configured with environment variables (set on the server, no code
 * change needed) — mirroring how DB and APP_URL are configured:
 *   SMTP_HOST     smtp.yourhost.com      (empty/unset = use PHP mail())
 *   SMTP_PORT     587                    (default 587)
 *   SMTP_USER     login@yourhost.com     (empty = no authentication)
 *   SMTP_PASS     ********
 *   SMTP_SECURE   tls | ssl | none       (default tls; use ssl for port 465)
 *   SMTP_FROM     noreply@yourhost.com   (sender address; defaults to SYSTEM_EMAIL_FROM)
 */

use PHPMailer\PHPMailer\PHPMailer;

$__ces_autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($__ces_autoload)) {
    require_once $__ces_autoload;
}
unset($__ces_autoload);

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
    if (preg_match('#^https?://#i', $path)) {
        return rtrim($path, '/');
    }
    return $scheme . '://' . $host . $path;
}

/**
 * Email a newly created user their login details.
 *
 * @return bool true if the message was accepted for delivery, false otherwise
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

    return ces_deliver_mail($to_email, $to_email, $subject, $body);
}

/**
 * Resolve the sender address/name from constants and the optional SMTP_FROM
 * environment override.
 *
 * @return array{0:string,1:string} [address, name]
 */
function ces_mail_from(): array {
    $from_name = defined('SYSTEM_EMAIL_NAME') ? SYSTEM_EMAIL_NAME : 'Course Evaluation System';
    $from_addr = defined('SYSTEM_EMAIL_FROM') ? SYSTEM_EMAIL_FROM : ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $env_from  = getenv('SMTP_FROM');
    if ($env_from !== false && trim($env_from) !== '') {
        $from_addr = trim($env_from);
    }
    return [$from_addr, $from_name];
}

/**
 * Deliver a plain-text email via PHPMailer (SMTP if configured, else PHP mail()).
 * Returns true only if the message was accepted for delivery.
 */
function ces_deliver_mail(string $to, string $to_name, string $subject, string $body): bool {
    list($from_addr, $from_name) = ces_mail_from();

    // Fallback if the PHPMailer library is not present (e.g. vendor/ not deployed).
    if (!class_exists(PHPMailer::class)) {
        $headers  = 'From: ' . str_replace(["\r", "\n"], '', $from_name) . ' <' . str_replace(["\r", "\n"], '', $from_addr) . ">\r\n";
        $headers .= 'Reply-To: ' . str_replace(["\r", "\n"], '', $from_addr) . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= 'X-Mailer: PHP/' . phpversion();
        return @mail(str_replace(["\r", "\n"], '', $to), str_replace(["\r", "\n"], '', $subject), $body, $headers);
    }

    $mail = new PHPMailer(false); // exceptions off — we check the boolean return
    try {
        $mail->CharSet = 'UTF-8';

        $smtp_host = getenv('SMTP_HOST');
        if ($smtp_host !== false && trim($smtp_host) !== '') {
            $mail->isSMTP();
            $mail->Host    = trim($smtp_host);
            $mail->Port    = (int) (getenv('SMTP_PORT') ?: 587);
            $mail->Timeout = 15;

            $user = getenv('SMTP_USER');
            if ($user !== false && trim($user) !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = trim($user);
                $mail->Password = (string) getenv('SMTP_PASS');
            }

            $secure = strtolower((string) (getenv('SMTP_SECURE') ?: 'tls'));
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'none') {
                $mail->SMTPSecure  = '';
                $mail->SMTPAutoTLS = false;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        } else {
            $mail->isMail(); // PHP mail()
        }

        $mail->setFrom($from_addr, $from_name);
        $mail->addReplyTo($from_addr);
        $mail->addAddress($to, $to_name);
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        return $mail->send();
    } catch (\Throwable $e) {
        error_log('[CES][mail] ' . $e->getMessage()
            . (isset($mail->ErrorInfo) && $mail->ErrorInfo !== '' ? ' | ' . $mail->ErrorInfo : ''));
        return false;
    }
}
