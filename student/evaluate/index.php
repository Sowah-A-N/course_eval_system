<?php

/**
 * Student Evaluate Index
 *
 * This file redirects to the available courses page.
 * Provides a consistent URL structure.
 *
 * Role Required: ROLE_STUDENT
 */

// Include required files
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

// Start session and check login
start_secure_session();
check_login();

// Check if user is a student
if ($_SESSION['role_id'] != ROLE_STUDENT) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

// Redirect to available courses page
header("Location: available_courses.php");
exit();
