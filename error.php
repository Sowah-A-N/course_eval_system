<?php
/**
 * Custom error page (404 / 403 / 500).
 *
 * Wired up via .htaccess ErrorDocument directives. Replaces the default,
 * unbranded Apache error page (which also leaked the server/PHP version) with a
 * branded page that gives the user a clear message and a way back into the app.
 *
 * Served at whatever (bad) URL the user requested, so it uses absolute links.
 */

// Branding, if available (kept optional so the error page never itself errors).
$app_name = 'Course Evaluation System';
$institution = 'Regional Maritime University';
$constants = __DIR__ . '/config/constants.php';
if (is_readable($constants)) {
    require_once $constants;
    if (defined('APP_NAME')) {
        $app_name = APP_NAME;
    }
    if (defined('INSTITUTION_NAME')) {
        $institution = INSTITUTION_NAME;
    }
}

// Apache passes the original status in REDIRECT_STATUS when serving an
// ErrorDocument. Default to 404 for direct hits / anything unmapped.
$status = isset($_SERVER['REDIRECT_STATUS']) ? (int) $_SERVER['REDIRECT_STATUS'] : 404;

$messages = array(
    403 => array('Access denied', "You don't have permission to view this page."),
    404 => array('Page not found', "The page you're looking for doesn't exist or may have moved."),
    500 => array('Something went wrong', 'An unexpected error occurred on our end. Please try again in a moment.'),
);
if (!isset($messages[$status])) {
    $status = 404;
}
http_response_code($status);

list($heading, $detail) = $messages[$status];

// Work out the application's base URL path (e.g. /course_evaluation) from this
// script's own location, so "Return to the application" works regardless of the
// requested URL.
$app_base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$home_url = ($app_base === '' ? '' : $app_base) . '/index.php';

$e = function ($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($status . ' — ' . $heading); ?> · <?php echo $e($app_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            width: 100%;
            max-width: 460px;
            border-radius: 14px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            padding: 44px 36px;
            text-align: center;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 22px;
        }
        h1 { font-size: 22px; margin-bottom: 10px; }
        p.detail { color: #6b7280; line-height: 1.6; margin-bottom: 28px; }
        a.btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            text-decoration: none;
            padding: 12px 26px;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        a.btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(102, 126, 234, 0.4); }
        .foot { margin-top: 26px; font-size: 12px; color: #9ca3af; }
    </style>
</head>

<body>
    <main class="card">
        <div class="badge"><?php echo $e((string) $status); ?></div>
        <h1><?php echo $e($heading); ?></h1>
        <p class="detail"><?php echo $e($detail); ?></p>
        <a class="btn" href="<?php echo $e($home_url); ?>">Return to the application</a>
        <div class="foot"><?php echo $e($app_name); ?> · <?php echo $e($institution); ?></div>
    </main>
</body>

</html>
