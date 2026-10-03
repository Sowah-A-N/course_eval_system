<?php
/** Vice-Chancellor dashboard — institution-wide overview + links to the full reports. */
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';
start_secure_session();
check_login();
if ($_SESSION['role_id'] !== ROLE_VC) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../login.php");
    exit();
}
$page_title = 'Vice-Chancellor Dashboard';
$role_label = 'Vice-Chancellor';
require __DIR__ . '/../includes/governance_dashboard.php';
