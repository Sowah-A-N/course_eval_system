<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){header("Location:../../login.php");exit();}
$question_id=intval($_GET['id']??0);
$page_title='Delete Question';
$query="SELECT * FROM evaluation_questions WHERE question_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$question_id);
mysqli_stmt_execute($stmt);
$question=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$question){$_SESSION['flash_message']='Question not found.';header("Location:list.php");exit();}
// Check how many historical responses reference this question
$stmt_rc=mysqli_prepare($conn,"SELECT COUNT(*) AS cnt FROM responses WHERE question_id=?");
mysqli_stmt_bind_param($stmt_rc,"i",$question_id);
mysqli_stmt_execute($stmt_rc);
$response_count=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rc))['cnt'];
mysqli_stmt_close($stmt_rc);
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token()){$_SESSION['flash_message']='Invalid token.';header("Location:list.php");exit();}
if($response_count>0){
$_SESSION['flash_message']='Cannot delete: this question has '.$response_count.' recorded response(s). Deactivate it instead.';
$_SESSION['flash_type']='error';
header("Location:list.php");
exit();
}
$query="DELETE FROM evaluation_questions WHERE question_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$question_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Question deleted successfully!';
$_SESSION['flash_type']='success';
}else{
$_SESSION['flash_message']='Error deleting question.';
$_SESSION['flash_type']='error';
}
mysqli_stmt_close($stmt);
header("Location:list.php");
exit();
}
require_once '../../includes/header.php';
?>
<style>
.delete-container{max-width:700px;margin:50px auto;background:white;padding:40px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center}
.delete-icon{font-size:80px;margin-bottom:20px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin:10px}
.btn-danger{background:#dc3545;color:white}
.btn-secondary{background:#6c757d;color:white}
</style>
<div class="delete-container">
<div class="delete-icon">⚠️</div>
<h2>Confirm Deletion</h2>
<p>Are you sure you want to delete this question?</p>
<div style="padding:20px;background:#f8f9fa;border-radius:8px;margin:20px 0;text-align:left">
<strong>Question Text:</strong><br>
<div style="padding:10px;margin-top:10px;background:white;border-left:3px solid #667eea">
<?php echo htmlspecialchars($question['question_text']);?>
</div>
</div>
<?php if($response_count>0): ?>
<p style="color:#dc3545;font-weight:600">Cannot delete: <?php echo $response_count;?> recorded response(s) exist for this question. Deactivate it instead to hide it from future evaluations.</p>
<a href="list.php" class="btn btn-secondary">Back to List</a>
<?php else: ?>
<p style="color:#dc3545;font-weight:600">This action cannot be undone!</p>
<form method="POST">
<?php csrf_token_input();?>
<button type="submit" class="btn btn-danger">Yes, Delete Question</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
<?php endif;?>
</div>
<?php require_once '../../includes/footer.php';?>
