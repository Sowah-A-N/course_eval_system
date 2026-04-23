<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_SECRETARY){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$student_id=intval($_GET['id']??0);
if($student_id<=0){$_SESSION['flash_message']='Invalid student ID.';$_SESSION['flash_type']='error';header("Location:list.php");exit();}
$page_title='Edit Student';
$errors=[];
// Get student (department scope check)
$query="SELECT * FROM user_details WHERE user_id=? AND department_id=? AND role_id=?";
$stmt=mysqli_prepare($conn,$query);
$role=ROLE_STUDENT;
mysqli_stmt_bind_param($stmt,"iii",$student_id,$department_id,$role);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$student=mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
if(!$student){$_SESSION['flash_message']='Student not found.';$_SESSION['flash_type']='error';header("Location:list.php");exit();}
// Get levels and classes
$levels=[];$result_levels=mysqli_query($conn,"SELECT * FROM level ORDER BY level_number");
while($row=mysqli_fetch_assoc($result_levels))$levels[]=$row;
$query_classes="SELECT * FROM classes WHERE department_id=? ORDER BY class_name";
$stmt_classes=mysqli_prepare($conn,$query_classes);
mysqli_stmt_bind_param($stmt_classes,"i",$department_id);
mysqli_stmt_execute($stmt_classes);
$result_classes=mysqli_stmt_get_result($stmt_classes);
$classes=[];
while($row=mysqli_fetch_assoc($result_classes))$classes[]=$row;
mysqli_stmt_close($stmt_classes);
// Handle form submission
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$f_name=trim($_POST['f_name']??'');
$l_name=trim($_POST['l_name']??'');
$email=trim($_POST['email']??'');
$unique_id=trim($_POST['unique_id']??'');
$level_id=intval($_POST['level_id']??0);
$class_id=intval($_POST['class_id']??0);
$is_active=isset($_POST['is_active'])?1:0;
if(empty($f_name))$errors[]='First name required.';
if(empty($l_name))$errors[]='Last name required.';
if(empty($email))$errors[]='Email required.';
if($level_id==0)$errors[]='Select level.';
if($class_id==0)$errors[]='Select class.';
// Check duplicates (excluding current student)
if(empty($errors)){
$query_check="SELECT user_id FROM user_details WHERE (email=? OR unique_id=?) AND user_id!=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"ssi",$email,$unique_id,$student_id);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='Email or ID exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$query="UPDATE user_details SET f_name=?,l_name=?,email=?,unique_id=?,level_id=?,class_id=?,is_active=? WHERE user_id=? AND department_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ssssiiii",$f_name,$l_name,$email,$unique_id,$level_id,$class_id,$is_active,$student_id,$department_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Student updated!';
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
<div class="page-header"><h1>Edit Student</h1></div>
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
<input type="text" name="f_name" class="form-input" value="<?php echo htmlspecialchars($student['f_name']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Last Name</label>
<input type="text" name="l_name" class="form-input" value="<?php echo htmlspecialchars($student['l_name']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Email</label>
<input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($student['email']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Student ID</label>
<input type="text" name="unique_id" class="form-input" value="<?php echo htmlspecialchars($student['unique_id']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Level</label>
<select name="level_id" class="form-select" required>
<?php foreach($levels as $level): ?>
<option value="<?php echo $level['t_id'];?>" <?php echo $student['level_id']==$level['t_id']?'selected':'';?>>
<?php echo htmlspecialchars($level['level_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label class="form-label required">Class</label>
<select name="class_id" class="form-select" required>
<?php foreach($classes as $class): ?>
<option value="<?php echo $class['t_id'];?>" <?php echo $student['class_id']==$class['t_id']?'selected':'';?>>
<?php echo htmlspecialchars($class['class_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label for="is_active"><input type="checkbox" id="is_active" name="is_active" <?php echo $student['is_active']?'checked':'';?>> Active</label>
</div>
<button type="submit" class="btn btn-primary">Update Student</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
