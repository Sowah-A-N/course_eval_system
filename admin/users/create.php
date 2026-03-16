<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){header("Location:../../login.php");exit();}
$page_title='Add New User';
$errors=[];
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
if(!validate_csrf_token())$errors[]='Invalid security token.';
$f_name=trim($_POST['f_name']??'');
$l_name=trim($_POST['l_name']??'');
$email=trim($_POST['email']??'');
$username=trim($_POST['username']??'');
$password=$_POST['password']??'';
$role_id=intval($_POST['role_id']??0);
$department_id=intval($_POST['department_id']??0);
$level_id=intval($_POST['level_id']??0);
$class_id=intval($_POST['class_id']??0);
$unique_id=trim($_POST['unique_id']??'');
$is_active=isset($_POST['is_active'])?1:0;
if(empty($f_name))$errors[]='First name required.';
if(empty($l_name))$errors[]='Last name required.';
if(empty($email))$errors[]='Email required.';
elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Invalid email.';
if(empty($username))$errors[]='Username required.';
if(empty($password))$errors[]='Password required.';
elseif(strlen($password)<PASSWORD_MIN_LENGTH)$errors[]='Password must be at least '.PASSWORD_MIN_LENGTH.' characters.';
if($role_id==0)$errors[]='Please select a role.';
if(in_array($role_id,[ROLE_STUDENT,ROLE_ADVISOR,ROLE_HOD,ROLE_SECRETARY])&&$department_id==0)$errors[]='Department required for this role.';
if($role_id==ROLE_STUDENT&&$level_id==0)$errors[]='Level required for students.';
if($role_id==ROLE_STUDENT&&$class_id==0)$errors[]='Class required for students.';
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
$dept_id_value=$department_id>0?$department_id:null;
$level_id_value=$level_id>0?$level_id:null;
$class_id_value=$class_id>0?$class_id:null;
$unique_id_value=!empty($unique_id)?$unique_id:null;
$query="INSERT INTO user_details (username,password,email,f_name,l_name,unique_id,role_id,department_id,level_id,class_id,is_active,date_created) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ssssssiiiii",$username,$password_hash,$email,$f_name,$l_name,$unique_id_value,$role_id,$dept_id_value,$level_id_value,$class_id_value,$is_active);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='User created successfully!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}else{$errors[]='Error creating user.';}
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
.form-input,.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
.form-checkbox{margin-right:8px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
.conditional-field{display:none}
</style>
<div class="page-header">
<h1>Add New User</h1>
<p>Create a new user account</p>
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
<form method="POST" id="userForm">
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
<label class="form-label required">Role</label>
<select name="role_id" id="role_id" class="form-select" required>
<option value="0">-- Select Role --</option>
<?php foreach(ROLE_NAMES as $rid=>$rname): ?>
<option value="<?php echo $rid;?>" <?php echo(isset($_POST['role_id'])&&$_POST['role_id']==$rid)?'selected':'';?>>
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
<option value="<?php echo $dept['t_id'];?>"><?php echo htmlspecialchars($dept['dep_name']);?></option>
<?php endforeach;?>
</select>
</div>
<div class="form-group conditional-field" id="student-id-field">
<label class="form-label">Student ID</label>
<input type="text" name="unique_id" class="form-input" value="<?php echo htmlspecialchars($_POST['unique_id']??'');?>">
</div>
<div class="form-group conditional-field" id="level-field">
<label class="form-label">Level</label>
<select name="level_id" class="form-select">
<option value="0">-- Select Level --</option>
<?php foreach($levels as $level): ?>
<option value="<?php echo $level['t_id'];?>"><?php echo htmlspecialchars($level['level_name']);?></option>
<?php endforeach;?>
</select>
</div>
<div class="form-group conditional-field" id="class-field">
<label class="form-label">Class</label>
<select name="class_id" class="form-select">
<option value="0">-- Select Class --</option>
<?php foreach($classes as $class): ?>
<option value="<?php echo $class['t_id'];?>"><?php echo htmlspecialchars($class['class_name'].' ('.$class['dep_name'].')');?></option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label>
<input type="checkbox" name="is_active" class="form-checkbox" <?php echo(isset($_POST['is_active'])||!isset($_POST['f_name']))?'checked':'';?>>
<span class="form-label" style="display:inline">Active</span>
</label>
</div>
<button type="submit" class="btn btn-primary">Create User</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<script>
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
if([2,3,4,5].includes(roleId)){deptField.style.display='block';}
if(roleId===5){
studentIdField.style.display='block';
levelField.style.display='block';
classField.style.display='block';
}
});
document.getElementById('role_id').dispatchEvent(new Event('change'));
</script>
<?php require_once '../../includes/footer.php';?>
