<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){header("Location:../../login.php");exit();}
$user_id=intval($_GET['id']??0);
$page_title='Delete User';
$query="SELECT * FROM user_details WHERE user_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);
$user=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$user){$_SESSION['flash_message']='User not found.';header("Location:list.php");exit();}
if($user['user_id']==$_SESSION['user_id']){
$_SESSION['flash_message']='Cannot delete your own account.';
$_SESSION['flash_type']='error';
header("Location:list.php");
exit();
}
// Count dependent records
$stmt_tk=mysqli_prepare($conn,"SELECT COUNT(*) AS cnt FROM evaluation_tokens WHERE student_user_id=?");
mysqli_stmt_bind_param($stmt_tk,"i",$user_id);
mysqli_stmt_execute($stmt_tk);
$token_count=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tk))['cnt'];
mysqli_stmt_close($stmt_tk);
$stmt_al=mysqli_prepare($conn,"SELECT COUNT(*) AS cnt FROM advisor_levels WHERE advisor_id=?");
mysqli_stmt_bind_param($stmt_al,"i",$user_id);
mysqli_stmt_execute($stmt_al);
$advisor_count=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_al))['cnt'];
mysqli_stmt_close($stmt_al);
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token()){$_SESSION['flash_message']='Invalid token.';header("Location:list.php");exit();}
if($token_count>0){
$_SESSION['flash_message']='Cannot delete: user has '.$token_count.' evaluation token(s). Deactivate the account instead.';
$_SESSION['flash_type']='error';
header("Location:list.php");
exit();
}
if($advisor_count>0){
// Remove advisor-level assignments before deleting
$stmt_del=mysqli_prepare($conn,"DELETE FROM advisor_levels WHERE advisor_id=?");
mysqli_stmt_bind_param($stmt_del,"i",$user_id);
mysqli_stmt_execute($stmt_del);
mysqli_stmt_close($stmt_del);
}
$query="DELETE FROM user_details WHERE user_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$user_id);
if(mysqli_stmt_execute($stmt)){
log_audit($conn,$_SESSION['user_id'],'USER_DELETE','user_details',$user_id,['username'=>$user['username'],'email'=>$user['email'],'role_id'=>$user['role_id']],null);
$_SESSION['flash_message']='User deleted successfully!';
$_SESSION['flash_type']='success';
}else{
$_SESSION['flash_message']='Error deleting user.';
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
.btn-danger{background:#dc3545;color:white}
.btn-secondary{background:#6c757d;color:white}
</style>
<div class="delete-container">
<div class="delete-icon">⚠️</div>
<h2>Confirm Deletion</h2>
<p>Are you sure you want to delete this user?</p>
<div style="padding:20px;background:#f8f9fa;border-radius:8px;margin:20px 0;text-align:left">
<strong>Name:</strong> <?php echo htmlspecialchars($user['f_name'].' '.$user['l_name']);?><br>
<strong>Email:</strong> <?php echo htmlspecialchars($user['email']);?><br>
<strong>Role:</strong> <?php echo htmlspecialchars(ROLE_NAMES[$user['role_id']]??'Unknown');?>
</div>
<?php if($token_count>0): ?>
<p style="color:#dc3545;font-weight:600">Cannot delete: this user has <?php echo $token_count;?> evaluation token(s). Deactivate the account instead.</p>
<a href="list.php" class="btn btn-secondary">Back to List</a>
<?php else: ?>
<p style="color:#dc3545;font-weight:600">This action cannot be undone!</p>
<?php if($advisor_count>0): ?>
<p style="color:#856404">Note: <?php echo $advisor_count;?> advisor-level assignment(s) will also be removed.</p>
<?php endif;?>
<form method="POST">
<?php csrf_token_input();?>
<button type="submit" class="btn btn-danger">Yes, Delete User</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
<?php endif;?>
</div>
<?php require_once '../../includes/footer.php';?>
