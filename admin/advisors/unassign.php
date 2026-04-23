<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$class_id=intval($_GET['class_id']??0);
$page_title='Unassign Advisor';
$query="SELECT c.*,d.dep_name,u.f_name,u.l_name,u.email FROM classes c LEFT JOIN department d ON c.department_id=d.t_id LEFT JOIN user_details u ON c.advisor_user_id=u.user_id WHERE c.t_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$class_id);
mysqli_stmt_execute($stmt);
$class=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$class){$_SESSION['flash_message']='Class not found.';header("Location:list.php");exit();}
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token()){$_SESSION['flash_message']='Invalid token.';header("Location:list.php");exit();}
$query="UPDATE classes SET advisor_user_id=NULL WHERE t_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$class_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Advisor unassigned successfully!';
$_SESSION['flash_type']='success';
}else{
$_SESSION['flash_message']='Error unassigning advisor.';
$_SESSION['flash_type']='error';
}
mysqli_stmt_close($stmt);
header("Location:list.php");
exit();
}
require_once '../../includes/header.php';
?>
<style>
.delete-container{max-width:600px;margin:50px auto;background:white;padding:40px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center}
.delete-icon{font-size:80px;margin-bottom:20px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin:10px}
.btn-warning{background:#ffc107;color:#333}
.btn-secondary{background:#6c757d;color:white}
</style>
<div class="delete-container">
<div class="delete-icon">⚠️</div>
<h2>Unassign Advisor</h2>
<p>Are you sure you want to remove the advisor from this class?</p>
<div style="padding:20px;background:#f8f9fa;border-radius:8px;margin:20px 0;text-align:left">
<strong>Class:</strong> <?php echo htmlspecialchars($class['class_name']);?><br>
<strong>Department:</strong> <?php echo htmlspecialchars($class['dep_name']);?><br>
<strong>Current Advisor:</strong> <?php echo htmlspecialchars($class['f_name'].' '.$class['l_name']);?>
</div>
<p style="color:#856404;font-weight:600">The class will no longer have an assigned advisor.</p>
<form method="POST">
<?php csrf_token_input();?>
<button type="submit" class="btn btn-warning">Yes, Unassign Advisor</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
