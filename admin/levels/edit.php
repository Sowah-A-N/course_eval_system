<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$level_id=intval($_GET['id']??0);
$page_title='Edit Level';
$errors=[];
$query="SELECT * FROM level WHERE t_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$level_id);
mysqli_stmt_execute($stmt);
$level=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$level){$_SESSION['flash_message']='Level not found.';header("Location:list.php");exit();}
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$level_name=trim($_POST['level_name']??'');
$level_number=intval($_POST['level_number']??0);
if(empty($level_name))$errors[]='Level name required.';
if($level_number<=0)$errors[]='Level number must be positive.';
if(empty($errors)){
$query_check="SELECT t_id FROM level WHERE level_number=? AND t_id!=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"ii",$level_number,$level_id);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='Level number exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$query="UPDATE level SET level_name=?,level_number=? WHERE t_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"sii",$level_name,$level_number,$level_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Level updated!';
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
.form-input{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header"><h1>Edit Level</h1></div>
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
<label class="form-label required">Level Name</label>
<input type="text" name="level_name" class="form-input" value="<?php echo htmlspecialchars($level['level_name']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Level Number</label>
<input type="number" name="level_number" class="form-input" value="<?php echo $level['level_number'];?>" min="1" required>
</div>
<button type="submit" class="btn btn-primary">Update Level</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
