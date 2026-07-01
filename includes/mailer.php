<?php
/**
 * Lightweight transactional mailer for account notifications.
 *
 * Delivery path:
 *   - If SMTP is configured (SMTP_HOST environment variable set) the message is
 *     sent over authenticated SMTP with STARTTLS/SSL — reliable on hosts where
 *     PHP's mail() is disabled or unconfigured.
 *   - Otherwise it falls back to PHP mail().
 *
 * It is best-effort: every function returns a boolean and never throws, so a
 * mail failure never blocks account creation (the credentials are also shown
 * on screen as a fallback).
 *
 * SMTP is configured with environment variables (set on the server, no code
 * change needed) — mirroring how DB and APP_URL are configured:
 *   SMTP_HOST       smtp.yourhost.com      (empty/unset = use mail())
 *   SMTP_PORT       587                    (default 587)
 *   SMTP_USER       login@yourhost.com     (empty = no AUTH)
 *   SMTP_PASS       ********
 *   SMTP_SECURE     tls | ssl | none       (default tls; use ssl for port 465)
 *   SMTP_FROM       noreply@yourhost.com   (envelope sender; defaults to SYSTEM_EMAIL_FROM)
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

    return ces_deliver_mail($to_email, $subject, $body);
}

/**
 * Deliver a plain-text email via SMTP (if configured) or PHP mail().
 * Header/subject values are stripped of CR/LF to prevent header injection.
 */
function ces_deliver_mail(string $to, string $subject, string $body): bool {
    $to      = str_replace(["\r", "\n"], '', $to);
    $subject = str_replace(["\r", "\n"], '', $subject);

    $from_name = defined('SYSTEM_EMAIL_NAME') ? SYSTEM_EMAIL_NAME : 'Course Evaluation System';
    $from_addr = defined('SYSTEM_EMAIL_FROM') ? SYSTEM_EMAIL_FROM : ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $env_from  = getenv('SMTP_FROM');
    if ($env_from !== false && trim($env_from) !== '') {
        $from_addr = trim($env_from);
    }
    $from_name = str_replace(["\r", "\n"], '', $from_name);
    $from_addr = str_replace(["\r", "\n"], '', $from_addr);

    $headers = [
        'From'         => $from_name . ' <' . $from_addr . '>',
        'Reply-To'     => $from_addr,
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer'     => 'PHP/' . phpversion(),
    ];

    $smtp_host = getenv('SMTP_HOST');
    if ($smtp_host !== false && trim($smtp_host) !== '') {
        return ces_smtp_send(trim($smtp_host), $to, $subject, $body, $headers, $from_addr);
    }

    // Fallback: PHP mail() — headers as a CRLF-joined string (no To/Subject here).
    $header_str = '';
    foreach ($headers as $k => $v) {
        $header_str .= $k . ': ' . $v . "\r\n";
    }
    return @mail($to, $subject, $body, rtrim($header_str));
}

/**
 * Send one message over SMTP. Returns true only if the server accepted the
 * message (250 after end-of-data). Any protocol/connection failure returns
 * false and is logged.
 */
function ces_smtp_send(string $host, string $to, string $subject, string $body, array $headers, string $envelope_from): bool {
    $port    = (int) (getenv('SMTP_PORT') ?: 587);
    $user    = (string) (getenv('SMTP_USER') ?: '');
    $pass    = (string) (getenv('SMTP_PASS') ?: '');
    $secure  = strtolower((string) (getenv('SMTP_SECURE') ?: 'tls')); // tls | ssl | none
    $timeout = 15;

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'SNI_enabled'       => true,
    ]]);

    $conn = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$conn) {
        error_log("[CES][SMTP] connect to $remote failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($conn, $timeout);

    // Read a (possibly multi-line) SMTP reply and return its numeric code.
    $read = function () use ($conn) {
        $data = '';
        while (($line = fgets($conn, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // last line of reply
        }
        return $data;
    };
    $code = function ($resp) { return (int) substr((string) $resp, 0, 3); };
    $send = function ($line) use ($conn) { fwrite($conn, $line . "\r\n"); };

    $fail = function ($msg) use ($conn) {
        error_log('[CES][SMTP] ' . $msg);
        @fwrite($conn, "QUIT\r\n");
        @fclose($conn);
        return false;
    };

    if ($code($read()) !== 220) return $fail('no 220 greeting');

    $ehlo = $_SERVER['SERVER_NAME'] ?? (gethostname() ?: 'localhost');
    $send('EHLO ' . $ehlo);
    if ($code($read()) !== 250) {
        $send('HELO ' . $ehlo);
        if ($code($read()) !== 250) return $fail('EHLO/HELO rejected');
    }

    if ($secure === 'tls') {
        $send('STARTTLS');
        if ($code($read()) !== 220) return $fail('STARTTLS refused');
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        if (!@stream_socket_enable_crypto($conn, true, $crypto)) return $fail('TLS handshake failed');
        $send('EHLO ' . $ehlo);
        if ($code($read()) !== 250) return $fail('EHLO after STARTTLS rejected');
    }

    if ($user !== '') {
        $send('AUTH LOGIN');
        if ($code($read()) !== 334) return $fail('AUTH LOGIN not accepted');
        $send(base64_encode($user));
        if ($code($read()) !== 334) return $fail('username not accepted');
        $send(base64_encode($pass));
        if ($code($read()) !== 235) return $fail('authentication failed');
    }

    $send('MAIL FROM:<' . $envelope_from . '>');
    if ($code($read()) !== 250) return $fail('MAIL FROM rejected');

    $send('RCPT TO:<' . $to . '>');
    $rcpt = $code($read());
    if ($rcpt !== 250 && $rcpt !== 251) return $fail('RCPT TO rejected');

    $send('DATA');
    if ($code($read()) !== 354) return $fail('DATA not accepted');

    $eol = "\r\n";
    $message = '';
    foreach ($headers as $k => $v) {
        $message .= $k . ': ' . $v . $eol;
    }
    $message .= 'To: ' . $to . $eol;
    $message .= 'Subject: ' . $subject . $eol;
    $message .= 'Date: ' . date('r') . $eol;
    $message .= 'MIME-Version: 1.0' . $eol;
    $message .= $eol;

    // Normalise line endings and dot-stuff lines beginning with '.'
    $normalised = preg_replace('/\r\n|\r|\n/', $eol, $body);
    $normalised = preg_replace('/^\./m', '..', $normalised);
    $message .= $normalised . $eol;

    fwrite($conn, $message . '.' . $eol);
    if ($code($read()) !== 250) return $fail('message not accepted after DATA');

    $send('QUIT');
    @fclose($conn);
    return true;
}
