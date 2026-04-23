<?php
/**
 * Admin Departments Index
 * Redirects to department list page.
 * Role Required: ROLE_ADMIN
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] != ROLE_ADMIN) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

header("Location: list.php");
exit();
?>
