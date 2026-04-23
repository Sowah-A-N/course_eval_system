<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_SECRETARY){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$student_id=intval($_REQUEST['id']??0);
$page_title='Delete Student';
// Get student (department scope)
$query="SELECT * FROM user_details WHERE user_id=? AND department_id=? AND role_id=?";
$stmt=mysqli_prepare($conn,$query);
$role=ROLE_STUDENT;
mysqli_stmt_bind_param($stmt,"iii",$student_id,$department_id,$role);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$student=mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
if(!$student){$_SESSION['flash_message']='Student not found.';header("Location:list.php");exit();}
// Handle deletion
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token()){$_SESSION['flash_message']='Invalid token.';header("Location:list.php");exit();}
$query="DELETE FROM user_details WHERE user_id=? AND department_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ii",$student_id,$department_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Student deleted successfully!';
$_SESSION['flash_type']='success';
}else{
$_SESSION['flash_message']='Error deleting student.';
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
<p>Are you sure you want to delete this student?</p>
<div style="padding:20px;background:#f8f9fa;border-radius:8px;margin:20px 0;text-align:left">
<strong>Name:</strong> <?php echo htmlspecialchars($student['f_name'].' '.$student['l_name']);?><br>
<strong>Email:</strong> <?php echo htmlspecialchars($student['email']);?><br>
<strong>Student ID:</strong> <?php echo htmlspecialchars($student['unique_id']);?>
</div>
<p style="color:#dc3545;font-weight:600">This action cannot be undone!</p>
<form method="POST">
<input type="hidden" name="id" value="<?php echo $student_id; ?>">
<?php csrf_token_input();?>
<button type="submit" class="btn btn-danger">Yes, Delete Student</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
