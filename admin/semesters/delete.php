<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$semester_id=intval($_GET["id"]??0);
$page_title='Delete Semester';
$query="SELECT * FROM semesters WHERE semester_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$semester_id);
mysqli_stmt_execute($stmt);
$semester=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$semester){$_SESSION['flash_message']='Semester not found.';header("Location:list.php");exit();}
$query_tokens="SELECT COUNT(*)as count FROM evaluation_tokens WHERE semester_id=?";
$stmt_tokens=mysqli_prepare($conn,$query_tokens);
mysqli_stmt_bind_param($stmt_tokens,"i",$semester_id);
mysqli_stmt_execute($stmt_tokens);
$token_count=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tokens))['count'];
mysqli_stmt_close($stmt_tokens);
$query_courses="SELECT COUNT(*)as count FROM courses WHERE semester_id=?";
$stmt_courses=mysqli_prepare($conn,$query_courses);
mysqli_stmt_bind_param($stmt_courses,"i",$semester_id);
mysqli_stmt_execute($stmt_courses);
$course_count=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_courses))['count'];
mysqli_stmt_close($stmt_courses);
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token()){$_SESSION['flash_message']='Invalid token.';header("Location:list.php");exit();}
if($token_count>0||$course_count>0){
$_SESSION['flash_message']='Cannot delete semester with tokens or courses.';
$_SESSION['flash_type']='error';
header("Location:list.php");
exit();
}
$query="DELETE FROM semesters WHERE semester_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$semester_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Semester deleted successfully!';
$_SESSION['flash_type']='success';
}else{
$_SESSION['flash_message']='Error deleting semester.';
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
<p>Are you sure you want to delete this semester?</p>
<div style="padding:20px;background:#f8f9fa;border-radius:8px;margin:20px 0;text-align:left">
<strong>Semester Name:</strong> <?php echo htmlspecialchars($semester['semester_name']);?><br>
<strong>Semester Value:</strong> <?php echo $semester['semester_value'];?><br>
<strong>Evaluation Tokens:</strong> <?php echo $token_count;?><br>
<strong>Courses:</strong> <?php echo $course_count;?>
</div>
<?php if($token_count>0||$course_count>0): ?>
<p style="color:#dc3545;font-weight:600">Cannot delete! This semester has <?php echo $token_count;?> token(s) and <?php echo $course_count;?> course(s).</p>
<a href="list.php" class="btn btn-secondary">Back to List</a>
<?php else: ?>
<p style="color:#dc3545;font-weight:600">This action cannot be undone!</p>
<form method="POST">
<input type="hidden" name="id" value="<?php echo $semester_id; ?>">
<?php csrf_token_input();?>
<button type="submit" class="btn btn-danger">Yes, Delete Semester</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
<?php endif;?>
</div>
<?php require_once '../../includes/footer.php';?>
