<?php


/**
 * Main Landing Page (index.php)
 *
 * This is the entry point of the application.
 *
 * Behavior:
 * - If user is logged in: Redirect to appropriate dashboard
 * - If user is not logged in: Redirect to login page
 * - If maintenance mode is enabled: Show maintenance message
 */

// Include required files
require_once 'config/constants.php';
require_once 'includes/session.php';

// Start secure session
start_secure_session();

// Check if maintenance mode is enabled
if (MAINTENANCE_MODE) {
    // Only allow admins during maintenance
    if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== ROLE_ADMIN) {
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Maintenance - <?php echo APP_NAME; ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .maintenance-box {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                    text-align: center;
                    max-width: 500px;
                }

                .maintenance-box h1 {
                    color: #667eea;
                    margin-bottom: 20px;
                    font-size: 32px;
                }

                .maintenance-box p {
                    color: #666;
                    line-height: 1.6;
                    margin-bottom: 15px;
                }

                .icon {
                    font-size: 64px;
                    margin-bottom: 20px;
                }
            </style>
        </head>

        <body>
            <div class="maintenance-box">
                <div class="icon">🔧</div>
                <h1>System Maintenance</h1>
                <p><?php echo MSG_WARNING_MAINTENANCE; ?></p>
                <p>We apologize for any inconvenience.</p>
                <p><small>If you need urgent assistance, please contact the system administrator.</small></p>
            </div>
        </body>

        </html>
<?php
        exit();
    }
}

// Check if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {
    // User is logged in - redirect to appropriate dashboard based on role
    switch ($_SESSION['role_id']) {
        case ROLE_ADMIN:
            header("Location: admin/index.php");
            exit();

        case ROLE_HOD:
            header("Location: hod/index.php");
            exit();

        case ROLE_SECRETARY:
            header("Location: secretary/index.php");
            exit();

        case ROLE_ADVISOR:
            header("Location: advisor/index.php");
            exit();

        case ROLE_STUDENT:
            header("Location: student/index.php");
            exit();

        case ROLE_QUALITY:
            header("Location: quality/index.php");
            exit();

        default:
            // Unknown role - logout and redirect to login
            session_destroy();
            header("Location: login.php");
            exit();
    }
} else {
    // User is not logged in - redirect to login page
    header("Location: login.php");
    exit();
}
?>
