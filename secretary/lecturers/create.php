<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_SECRETARY){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$page_title='Add New Lecturer';
$errors=[];
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid security token.';
$f_name=trim($_POST['f_name']??'');
$l_name=trim($_POST['l_name']??'');
$email=trim($_POST['email']??'');
$username=trim($_POST['username']??'');
$password=$_POST['password']??'';
$is_active=isset($_POST['is_active'])?1:0;
if(empty($f_name))$errors[]='First name required.';
if(empty($l_name))$errors[]='Last name required.';
if(empty($email))$errors[]='Email required.';
elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Invalid email.';
if(empty($username))$errors[]='Username required.';
if(empty($password))$errors[]='Password required.';
elseif(strlen($password)<PASSWORD_MIN_LENGTH)$errors[]='Password must be at least '.PASSWORD_MIN_LENGTH.' characters.';
if(empty($errors)){
$query_check="SELECT user_id FROM user_details WHERE email=? OR username=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"ss",$email,$username);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='Email or username exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$password_hash=password_hash($password,PASSWORD_DEFAULT);
$query="INSERT INTO user_details (username,password,email,f_name,l_name,role_id,department_id,is_active,date_created) VALUES (?,?,?,?,?,?,?,?,NOW())";
$stmt=mysqli_prepare($conn,$query);
$role=ROLE_LECTURER;
mysqli_stmt_bind_param($stmt,"sssssiii",$username,$password_hash,$email,$f_name,$l_name,$role,$department_id,$is_active);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Lecturer created successfully!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}else{$errors[]='Error creating lecturer.';}
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
.form-checkbox{margin-right:8px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Add New Lecturer</h1>
<p>Create a new lecturer record</p>
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
<label class="form-label required">First Name</label>
<input type="text" name="f_name" class="form-input" value="<?php echo htmlspecialchars($_POST['f_name']??'');?>" required>
</div>
<div class="form-group">
<label class="form-label required">Last Name</label>
<input type="text" name="l_name" class="form-input" value="<?php echo htmlspecialchars($_POST['l_name']??'');?>" required>
</div>
<div class="form-group">
<label class="form-label required">Email</label>
<input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($_POST['email']??'');?>" required>
</div>
<div class="form-group">
<label class="form-label required">Username</label>
<input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($_POST['username']??'');?>" required>
</div>
<div class="form-group">
<label class="form-label required">Password</label>
<input type="password" name="password" class="form-input" required>
<small style="color:#666">Minimum <?php echo PASSWORD_MIN_LENGTH;?> characters</small>
</div>
<div class="form-group">
<label>
<input type="checkbox" name="is_active" class="form-checkbox" <?php echo(isset($_POST['is_active'])||!isset($_POST['f_name']))?'checked':'';?>>
<span class="form-label" style="display:inline">Active</span>
</label>
</div>
<button type="submit" class="btn btn-primary">Create Lecturer</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
