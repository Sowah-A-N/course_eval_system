<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_SECRETARY){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$lecturer_id=intval($_GET['id']??0);
$page_title='Edit Lecturer';
$errors=[];
$query="SELECT * FROM user_details WHERE user_id=? AND department_id=? AND role_id=?";
$stmt=mysqli_prepare($conn,$query);
$role=ROLE_LECTURER;
mysqli_stmt_bind_param($stmt,"iii",$lecturer_id,$department_id,$role);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$lecturer=mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
if(!$lecturer){$_SESSION['flash_message']='Lecturer not found.';header("Location:list.php");exit();}
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$f_name=trim($_POST['f_name']??'');
$l_name=trim($_POST['l_name']??'');
$email=trim($_POST['email']??'');
$is_active=isset($_POST['is_active'])?1:0;
if(empty($f_name))$errors[]='First name required.';
if(empty($l_name))$errors[]='Last name required.';
if(empty($email))$errors[]='Email required.';
if(empty($errors)){
$query_check="SELECT user_id FROM user_details WHERE email=? AND user_id!=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"si",$email,$lecturer_id);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='Email exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$query="UPDATE user_details SET f_name=?,l_name=?,email=?,is_active=? WHERE user_id=? AND department_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"sssiii",$f_name,$l_name,$email,$is_active,$lecturer_id,$department_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Lecturer updated!';
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
<div class="page-header"><h1>Edit Lecturer</h1></div>
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
<label class="form-label required">First Name</label>
<input type="text" name="f_name" class="form-input" value="<?php echo htmlspecialchars($lecturer['f_name']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Last Name</label>
<input type="text" name="l_name" class="form-input" value="<?php echo htmlspecialchars($lecturer['l_name']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Email</label>
<input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($lecturer['email']);?>" required>
</div>
<div class="form-group">
<label><input type="checkbox" name="is_active" <?php echo $lecturer['is_active']?'checked':'';?>> Active</label>
</div>
<button type="submit" class="btn btn-primary">Update Lecturer</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
