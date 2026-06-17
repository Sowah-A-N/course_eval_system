<?php
/**
 * Advisor Students Index
 * 
 * This file redirects to the student list page.
 * Provides a consistent URL structure.
 * 
 * Role Required: ROLE_ADVISOR
 */

// Include required files
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

// Start session and check login
start_secure_session();
check_login();

// Check if user is an advisor
if ($_SESSION['role_id'] !== ROLE_ADVISOR) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

// Redirect to list page
header("Location: list.php");
exit();
?>