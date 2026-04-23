<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$prog_id=intval($_GET['id']??0);
$page_title='Edit Programme';
$errors=[];
$query="SELECT * FROM programme WHERE t_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$prog_id);
mysqli_stmt_execute($stmt);
$programme=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$programme){$_SESSION['flash_message']='Programme not found.';header("Location:list.php");exit();}
$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$prog_name=trim($_POST['prog_name']??'');
$prog_code=trim($_POST['prog_code']??'');
$department_id=intval($_POST['department_id']??0);
if(empty($prog_name))$errors[]='Programme name required.';
if(empty($prog_code))$errors[]='Programme code required.';
if($department_id==0)$errors[]='Select department.';
if(empty($errors)){
$query_check="SELECT t_id FROM programme WHERE prog_code=? AND t_id!=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"si",$prog_code,$prog_id);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='Programme code exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$query="UPDATE programme SET prog_name=?,prog_code=?,department_id=? WHERE t_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ssii",$prog_name,$prog_code,$department_id,$prog_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Programme updated!';
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
.form-input,.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header"><h1>Edit Programme</h1></div>
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
<label class="form-label required">Programme Name</label>
<input type="text" name="prog_name" class="form-input" value="<?php echo htmlspecialchars($programme['prog_name']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Programme Code</label>
<input type="text" name="prog_code" class="form-input" value="<?php echo htmlspecialchars($programme['prog_code']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Department</label>
<select name="department_id" class="form-select" required>
<?php foreach($departments as $dept): ?>
<option value="<?php echo $dept['t_id'];?>" <?php echo $programme['department_id']==$dept['t_id']?'selected':'';?>>
<?php echo htmlspecialchars($dept['dep_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<button type="submit" class="btn btn-primary">Update Programme</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
