<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$question_id=intval($_GET['id']??0);
$query="UPDATE evaluation_questions SET is_active=1 WHERE question_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$question_id);
if(mysqli_stmt_execute($stmt)){
log_audit($conn,$_SESSION['user_id'],'QUESTION_ACTIVATE','evaluation_questions',$question_id,['is_active'=>0],['is_active'=>1]);
$_SESSION['flash_message']='Question activated successfully!';
$_SESSION['flash_type']='success';
}else{
$_SESSION['flash_message']='Error activating question.';
$_SESSION['flash_type']='error';
}
mysqli_stmt_close($stmt);
header("Location:list.php");
exit();
?>
