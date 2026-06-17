<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Add New Question';
$errors=[];
$query_max="SELECT MAX(display_order) AS max_order FROM evaluation_questions";
$result_max=mysqli_query($conn,$query_max);
$max_order=mysqli_fetch_assoc($result_max)['max_order']??0;
$next_order=$max_order+1;
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid security token.';
$question_text=trim($_POST['question_text']??'');
$question_order=intval($_POST['question_order']??$next_order);
$is_active=isset($_POST['is_active'])?1:0;
if(empty($question_text))$errors[]='Question text required.';
if($question_order<=0)$errors[]='Question order must be positive.';
if(empty($errors)){
$query="INSERT INTO evaluation_questions (question_text,display_order,is_active) VALUES (?,?,?)";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"sii",$question_text,$question_order,$is_active);
if(mysqli_stmt_execute($stmt)){
$new_question_id=mysqli_insert_id($conn);
log_audit($conn,$_SESSION['user_id'],'QUESTION_CREATE','evaluation_questions',$new_question_id,null,['question_text'=>$question_text,'display_order'=>$question_order]);
$_SESSION['flash_message']='Question created successfully!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}else{$errors[]='Error creating question.';}
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
.form-input,.form-textarea{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
.form-textarea{min-height:120px;font-family:inherit;resize:vertical}
.form-checkbox{margin-right:8px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Add New Question</h1>
<p>Create a new evaluation question</p>
</div>
<div class="info-box">
<strong>💡 Tips for Writing Good Questions:</strong><br>
• Be specific and clear<br>
• Use simple language<br>
• Ask one thing per question<br>
• Avoid leading or biased questions<br>
• Example: "The instructor explained concepts clearly" (not "Was the instructor good?")
</div>
<?php if(!empty($errors)): ?>
<div class="alert-error">
<strong>⚠️ Errors:</strong>
<ul style="margin:10px 0 0 20px;padding:0">
<?php foreach($errors as $error): ?>
<li><?php echo htmlspecialchars($error);?></li>
<?php endforeach;?>
</ul>
</div>
<?php endif;?>
<div class="form-container">
<form method="POST">
<?php csrf_token_input();?>
<div class="form-group">
<label class="form-label required">Question Text</label>
<textarea name="question_text" class="form-textarea" required placeholder="e.g., The instructor presented course material in an organized manner."><?php echo htmlspecialchars($_POST['question_text']??'');?></textarea>
<small style="color:#666">This is what students will see during evaluation</small>
</div>
<div class="form-group">
<label class="form-label required">Display Order</label>
<input type="number" name="question_order" class="form-input" value="<?php echo htmlspecialchars($_POST['question_order']??$next_order);?>" min="1" required>
<small style="color:#666">Questions are displayed in ascending order (1, 2, 3...)</small>
</div>
<div class="form-group">
<label for="is_active">
<input type="checkbox" id="is_active" name="is_active" class="form-checkbox" <?php echo(!isset($_POST['question_text'])||isset($_POST['is_active']))?'checked':'';?>>
<span class="form-label" style="display:inline">Active (visible in evaluations)</span>
</label>
</div>
<button type="submit" class="btn btn-primary">Create Question</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
