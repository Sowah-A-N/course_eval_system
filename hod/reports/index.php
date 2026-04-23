<?php
/**
 * HOD Reports Index
 * 
 * Redirects to department report page.
 * 
 * Role Required: ROLE_HOD
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] != ROLE_HOD) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

header("Location: department_report.php");
exit();
?>
