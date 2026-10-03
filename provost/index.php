<?php
/** Provost dashboard — institution-wide overview + links to the full reports. */
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';
start_secure_session();
check_login();
if ($_SESSION['role_id'] !== ROLE_PROVOST) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../login.php");
    exit();
}
$page_title = 'Provost Dashboard';
$role_label = 'Provost';
require __DIR__ . '/../includes/governance_dashboard.php';
