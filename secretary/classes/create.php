<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_SECRETARY){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$page_title='Add New Class';
$errors=[];
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid security token.';
$class_name=trim($_POST['class_name']??'');
$class_code=trim($_POST['class_code']??'');
if(empty($class_name))$errors[]='Class name required.';
if(empty($class_code))$errors[]='Class code required.';
if(empty($errors)){
$query_check="SELECT t_id FROM classes WHERE class_code=? AND department_id=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"si",$class_code,$department_id);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='Class code exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$query="INSERT INTO classes (class_name,class_code,department_id) VALUES (?,?,?)";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ssi",$class_name,$class_code,$department_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Class created successfully!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}else{$errors[]='Error creating class.';}
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
.form-input{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Add New Class</h1>
<p>Create a new class record</p>
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
<label class="form-label required">Class Name</label>
<input type="text" name="class_name" class="form-input" value="<?php echo htmlspecialchars($_POST['class_name']??'');?>" placeholder="e.g., Computer Science A" required>
</div>
<div class="form-group">
<label class="form-label required">Class Code</label>
<input type="text" name="class_code" class="form-input" value="<?php echo htmlspecialchars($_POST['class_code']??'');?>" placeholder="e.g., CS-A" required>
<small style="color:#666">Unique code for this class</small>
</div>
<button type="submit" class="btn btn-primary">Create Class</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
