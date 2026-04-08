<?php
/**
 * Quality Reports Index
 *
 * Redirects to institution overview report page.
 *
 * Role Required: ROLE_QUALITY
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] != ROLE_QUALITY) {
    header("Location: ../../login.php");
    exit();
}

header("Location: institution_overview.php");
exit();
?>
