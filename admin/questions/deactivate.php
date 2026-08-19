<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
// State-changing action: require POST + a valid CSRF token so it cannot be
// triggered by a crafted link/image that a logged-in admin merely opens.
if($_SERVER['REQUEST_METHOD']!=='POST' || !validate_csrf_token()){$_SESSION['flash_message']='Invalid request.';$_SESSION['flash_type']='error';header("Location:list.php");exit();}
$question_id=intval($_POST['id']??0);
$query="UPDATE evaluation_questions SET is_active=0 WHERE question_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$question_id);
if(mysqli_stmt_execute($stmt)){
log_audit($conn,$_SESSION['user_id'],'QUESTION_DEACTIVATE','evaluation_questions',$question_id,['is_active'=>1],['is_active'=>0]);
$_SESSION['flash_message']='Question deactivated successfully!';
$_SESSION['flash_type']='success';
}else{
$_SESSION['flash_message']='Error deactivating question.';
$_SESSION['flash_type']='error';
}
mysqli_stmt_close($stmt);
header("Location:list.php");
exit();
?>
