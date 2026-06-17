<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_SECRETARY){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$class_id=intval($_GET["id"]??0);
$page_title='Delete Class';
$query="SELECT * FROM classes WHERE t_id=? AND department_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ii",$class_id,$department_id);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$class=mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
if(!$class){$_SESSION['flash_message']='Class not found.';header("Location:list.php");exit();}
$query_count="SELECT COUNT(*)as count FROM user_details WHERE class_id=?";
$stmt_count=mysqli_prepare($conn,$query_count);
mysqli_stmt_bind_param($stmt_count,"i",$class_id);
mysqli_stmt_execute($stmt_count);
$student_count=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['count'];
mysqli_stmt_close($stmt_count);
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token()){$_SESSION['flash_message']='Invalid token.';header("Location:list.php");exit();}
if($student_count>0){
$_SESSION['flash_message']='Cannot delete class with enrolled students.';
$_SESSION['flash_type']='error';
header("Location:list.php");
exit();
}
$query="DELETE FROM classes WHERE t_id=? AND department_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ii",$class_id,$department_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Class deleted successfully!';
$_SESSION['flash_type']='success';
}else{
$_SESSION['flash_message']='Error deleting class.';
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
<p>Are you sure you want to delete this class?</p>
<div style="padding:20px;background:#f8f9fa;border-radius:8px;margin:20px 0;text-align:left">
<strong>Class Name:</strong> <?php echo htmlspecialchars($class['class_name']);?><br>
<strong>Class Code:</strong> <?php echo htmlspecialchars($class['class_code']);?><br>
<strong>Students Enrolled:</strong> <?php echo $student_count;?>
</div>
<?php if($student_count>0): ?>
<p style="color:#dc3545;font-weight:600">Cannot delete! This class has <?php echo $student_count;?> enrolled student(s).</p>
<a href="list.php" class="btn btn-secondary">Back to List</a>
<?php else: ?>
<p style="color:#dc3545;font-weight:600">This action cannot be undone!</p>
<form method="POST">
<input type="hidden" name="id" value="<?php echo $class_id; ?>">
<?php csrf_token_input();?>
<button type="submit" class="btn btn-danger">Yes, Delete Class</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
<?php endif;?>
</div>
<?php require_once '../../includes/footer.php';?>
