<?php

/**
 * Logout Handler
 *
 * This script handles user logout by:
 * - Logging the logout action (optional audit)
 * - Destroying the session
 * - Clearing session cookies
 * - Redirecting to login page
 *
 * Security: This page should not require authentication check
 * since its purpose is to logout users.
 */

// Include required files
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/session.php';
require_once 'includes/csrf.php';
require_once 'includes/audit.php';

// Start secure session
start_secure_session();

// Require POST + valid CSRF token so that a third-party page cannot log out
// the user by embedding <img src="logout.php"> or similar.
// GET-based logouts are silently redirected to login without destroying the session.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token()) {
    header("Location: login.php");
    exit();
}

// Capture user_id BEFORE the session is destroyed so the audit row has a subject.
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Audit the logout using the shared helper (consistent schema, all fields populated).
// Must happen before logout_user() destroys the session.
if ($user_id !== null) {
    log_audit($conn, $user_id, AUDIT_LOGOUT, null, null, null, null);
}

// Destroy the session and clear the cookie.
logout_user();

// Close database connection (optional)
mysqli_close($conn);

// Redirect to login page with logout message
header("Location: login.php?logout=success");
exit();
