<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$user_id=intval($_GET['id']??0);
$page_title='Edit User';
$errors=[];
$query="SELECT * FROM user_details WHERE user_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);
$user=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$user){$_SESSION['flash_message']='User not found.';header("Location:list.php");exit();}
$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;
$levels=[];
$result_levels=mysqli_query($conn,"SELECT * FROM level ORDER BY level_number");
while($row=mysqli_fetch_assoc($result_levels))$levels[]=$row;
$classes=[];
$result_classes=mysqli_query($conn,"SELECT c.*,d.dep_name FROM classes c LEFT JOIN department d ON c.department_id=d.t_id ORDER BY d.dep_name,c.class_name");
while($row=mysqli_fetch_assoc($result_classes))$classes[]=$row;
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$f_name=trim($_POST['f_name']??'');
$l_name=trim($_POST['l_name']??'');
$email=trim($_POST['email']??'');
$role_id=intval($_POST['role_id']??0);
$department_id=intval($_POST['department_id']??0);
$level_id=intval($_POST['level_id']??0);
$class_id=intval($_POST['class_id']??0);
$unique_id=trim($_POST['unique_id']??'');
$is_active=isset($_POST['is_active'])?1:0;
// Password change is optional — only applied when the admin fills in the field.
$new_password=trim($_POST['new_password']??'');
if(empty($f_name))$errors[]='First name required.';
if(empty($l_name))$errors[]='Last name required.';
if(empty($email))$errors[]='Email required.';
elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Invalid email format.';
if($role_id==0)$errors[]='Select role.';
// Enforce the same password policy as create.php when a new password is provided.
if(!empty($new_password)){
if(strlen($new_password)<PASSWORD_MIN_LENGTH)$errors[]='New password must be at least '.PASSWORD_MIN_LENGTH.' characters.';
elseif(!preg_match('/[A-Z]/',$new_password))$errors[]='New password must contain at least one uppercase letter.';
elseif(!preg_match('/[a-z]/',$new_password))$errors[]='New password must contain at least one lowercase letter.';
elseif(!preg_match('/[0-9]/',$new_password))$errors[]='New password must contain at least one number.';
}
if(empty($errors)){
$query_check="SELECT user_id FROM user_details WHERE email=? AND user_id!=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"si",$email,$user_id);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='Email exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$dept_id_value=$department_id>0?$department_id:null;
$level_id_value=$level_id>0?$level_id:null;
$class_id_value=$class_id>0?$class_id:null;
$unique_id_value=!empty($unique_id)?$unique_id:null;
$query="UPDATE user_details SET f_name=?,l_name=?,email=?,unique_id=?,role_id=?,department_id=?,level_id=?,class_id=?,is_active=? WHERE user_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ssssiiiiii",$f_name,$l_name,$email,$unique_id_value,$role_id,$dept_id_value,$level_id_value,$class_id_value,$is_active,$user_id);
if(mysqli_stmt_execute($stmt)){
// Update password separately only when the admin provided a new one.
if(!empty($new_password)){
$hash=password_hash($new_password,PASSWORD_DEFAULT);
$stmt_pw=mysqli_prepare($conn,"UPDATE user_details SET password=? WHERE user_id=?");
mysqli_stmt_bind_param($stmt_pw,"si",$hash,$user_id);
mysqli_stmt_execute($stmt_pw);
mysqli_stmt_close($stmt_pw);
}
log_audit($conn,$_SESSION['user_id'],AUDIT_USER_UPDATE,'user_details',$user_id,['f_name'=>$user['f_name'],'l_name'=>$user['l_name'],'email'=>$user['email'],'role_id'=>$user['role_id']],['f_name'=>$f_name,'l_name'=>$l_name,'email'=>$email,'role_id'=>$role_id,'password_changed'=>!empty($new_password)]);
$_SESSION['flash_message']='User updated!';
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
.form-container{max-width:900px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px}
.form-label.required::after{content:' *';color:#dc3545}
.form-input,.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;padding:15px;border-radius:8px;margin-bottom:20px}
.conditional-field{display:none}
</style>
<div class="page-header"><h1>Edit User</h1></div>
<?php if(!empty($errors)): ?>
<div class="alert-error">
<strong>⚠️ Errors:</strong>
<ul style="margin:10px 0 0 20px"><?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e);?></li><?php endforeach;?></ul>
</div>
<?php endif;?>
<div class="form-container">
<form method="POST" id="userForm">
<?php csrf_token_input();?>
<div class="form-group">
<label class="form-label required">First Name</label>
<input type="text" name="f_name" class="form-input" value="<?php echo htmlspecialchars($user['f_name']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Last Name</label>
<input type="text" name="l_name" class="form-input" value="<?php echo htmlspecialchars($user['l_name']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Email</label>
<input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Role</label>
<select name="role_id" id="role_id" class="form-select" required>
<?php foreach(ROLE_NAMES as $rid=>$rname): ?>
<option value="<?php echo $rid;?>" <?php echo $user['role_id']==$rid?'selected':'';?>>
<?php echo htmlspecialchars($rname);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group conditional-field" id="dept-field">
<label class="form-label">Department</label>
<select name="department_id" class="form-select">
<option value="0">-- Select Department --</option>
<?php foreach($departments as $dept): ?>
<option value="<?php echo $dept['t_id'];?>" <?php echo $user['department_id']==$dept['t_id']?'selected':'';?>>
<?php echo htmlspecialchars($dept['dep_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group conditional-field" id="student-id-field">
<label class="form-label">Student ID</label>
<input type="text" name="unique_id" class="form-input" value="<?php echo htmlspecialchars($user['unique_id']??'');?>">
</div>
<div class="form-group conditional-field" id="level-field">
<label class="form-label">Level</label>
<select name="level_id" class="form-select">
<option value="0">-- Select Level --</option>
<?php foreach($levels as $level): ?>
<option value="<?php echo $level['t_id'];?>" <?php echo $user['level_id']==$level['t_id']?'selected':'';?>>
<?php echo htmlspecialchars($level['level_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group conditional-field" id="class-field">
<label class="form-label">Class</label>
<select name="class_id" class="form-select">
<option value="0">-- Select Class --</option>
<?php foreach($classes as $class): ?>
<option value="<?php echo $class['t_id'];?>" <?php echo $user['class_id']==$class['t_id']?'selected':'';?>>
<?php echo htmlspecialchars($class['class_name'].' ('.$class['dep_name'].')');?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label for="is_active"><input type="checkbox" id="is_active" name="is_active" <?php echo $user['is_active']?'checked':'';?>> Active</label>
</div>
<div class="form-group" style="border-top:1px solid #e0e0e0;padding-top:20px;margin-top:20px">
<label class="form-label">New Password <small style="color:#666;font-weight:normal">(leave blank to keep current password)</small></label>
<input type="password" name="new_password" class="form-input" autocomplete="new-password"
       placeholder="Enter new password or leave blank">
<small style="color:#666;display:block;margin-top:4px">
Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters, must include uppercase, lowercase, and a number.
</small>
</div>
<button type="submit" class="btn btn-primary">Update User</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<script>
const ROLES_WITH_DEPT=[<?php echo implode(',', [ROLE_HOD, ROLE_SECRETARY, ROLE_ADVISOR, ROLE_STUDENT, ROLE_QUALITY]); ?>];
const ROLE_STUDENT_ID=<?php echo ROLE_STUDENT; ?>;
document.getElementById('role_id').addEventListener('change',function(){
const roleId=parseInt(this.value);
const deptField=document.getElementById('dept-field');
const studentIdField=document.getElementById('student-id-field');
const levelField=document.getElementById('level-field');
const classField=document.getElementById('class-field');
deptField.style.display='none';
studentIdField.style.display='none';
levelField.style.display='none';
classField.style.display='none';
if(ROLES_WITH_DEPT.includes(roleId)){deptField.style.display='block';}
if(roleId===ROLE_STUDENT_ID){
studentIdField.style.display='block';
levelField.style.display='block';
classField.style.display='block';
}
});
document.getElementById('role_id').dispatchEvent(new Event('change'));
</script>
<?php require_once '../../includes/footer.php';?>
