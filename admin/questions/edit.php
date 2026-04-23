<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$question_id=intval($_GET['id']??0);
$page_title='Edit Question';
$errors=[];
$query="SELECT * FROM evaluation_questions WHERE question_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$question_id);
mysqli_stmt_execute($stmt);
$question=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$question){$_SESSION['flash_message']='Question not found.';header("Location:list.php");exit();}
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$question_text=trim($_POST['question_text']??'');
$question_order=intval($_POST['question_order']??1);
$is_active=isset($_POST['is_active'])?1:0;
if(empty($question_text))$errors[]='Question text required.';
if($question_order<=0)$errors[]='Question order must be positive.';
if(empty($errors)){
$query="UPDATE evaluation_questions SET question_text=?,display_order=?,is_active=? WHERE question_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"siii",$question_text,$question_order,$is_active,$question_id);
if(mysqli_stmt_execute($stmt)){
log_audit($conn,$_SESSION['user_id'],'QUESTION_UPDATE','evaluation_questions',$question_id,['question_text'=>$question['question_text'],'display_order'=>$question['display_order']],['question_text'=>$question_text,'display_order'=>$question_order]);
$_SESSION['flash_message']='Question updated!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}else{$errors[]='Update failed.';}
mysqli_stmt_close($stmt);
}
}
require_once '../../includes/header.php';
?>
<style>
.form-container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px}
.form-label.required::after{content:' *';color:#dc3545}
.form-input,.form-textarea{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px}
.form-textarea{min-height:120px;font-family:inherit;resize:vertical}
.form-checkbox{margin-right:8px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header"><h1>Edit Question</h1></div>
<?php if(!empty($errors)): ?>
<div class="alert-error">
<strong>⚠️ Errors:</strong>
<ul style="margin:10px 0 0 20px"><?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e);?></li><?php endforeach;?></ul>
</div>
<?php endif;?>
<div class="form-container">
<form method="POST">
<?php csrf_token_input();?>
<div class="form-group">
<label class="form-label required">Question Text</label>
<textarea name="question_text" class="form-textarea" required><?php echo htmlspecialchars($question['question_text']);?></textarea>
</div>
<div class="form-group">
<label class="form-label required">Display Order</label>
<input type="number" name="question_order" class="form-input" value="<?php echo $question['display_order'];?>" min="1" required>
</div>
<div class="form-group">
<label for="is_active">
<input type="checkbox" id="is_active" name="is_active" class="form-checkbox" <?php echo $question['is_active']?'checked':'';?>>
<span class="form-label" style="display:inline">Active</span>
</label>
</div>
<button type="submit" class="btn btn-primary">Update Question</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
