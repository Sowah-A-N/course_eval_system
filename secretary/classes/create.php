<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_SECRETARY){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$page_title='Add New Class';
$errors=[];
// Programmes are limited to the secretary's own department; levels are global.
$programmes=[];
$stmt_progs=mysqli_prepare($conn,"SELECT t_id,prog_name,prog_code FROM programme WHERE department_id=? ORDER BY prog_name");
mysqli_stmt_bind_param($stmt_progs,"i",$department_id);
mysqli_stmt_execute($stmt_progs);
$res_progs=mysqli_stmt_get_result($stmt_progs);
while($row=mysqli_fetch_assoc($res_progs))$programmes[]=$row;
mysqli_stmt_close($stmt_progs);
$levels=[];
$result_levels=mysqli_query($conn,"SELECT t_id,level_name,level_number FROM level ORDER BY level_number");
while($row=mysqli_fetch_assoc($result_levels))$levels[]=$row;
$current_year=(int)date('Y');
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid security token.';
$class_name=trim($_POST['class_name']??'');
$programme_id=intval($_POST['programme_id']??0);
$level_id=intval($_POST['level_id']??0);
$year_of_completion=intval($_POST['year_of_completion']??0);
if(empty($class_name))$errors[]='Class name required.';
if($programme_id==0)$errors[]='Please select a programme.';
if($level_id==0)$errors[]='Please select a level.';
if($year_of_completion<2000||$year_of_completion>$current_year+10)$errors[]='Please enter a valid year of completion.';
// Programme must belong to this secretary's department.
if(empty($errors)){
$query_prog="SELECT t_id FROM programme WHERE t_id=? AND department_id=?";
$stmt_prog=mysqli_prepare($conn,$query_prog);
mysqli_stmt_bind_param($stmt_prog,"ii",$programme_id,$department_id);
mysqli_stmt_execute($stmt_prog);
if(mysqli_stmt_get_result($stmt_prog)->num_rows==0)$errors[]='Invalid programme selection.';
mysqli_stmt_close($stmt_prog);
}
if(empty($errors)){
$query_check="SELECT t_id FROM classes WHERE class_name=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"s",$class_name);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='Class name already exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$class_code='';
$query="INSERT INTO classes (class_name,class_code,department_id,programme_id,level_id,year_of_completion) VALUES (?,?,?,?,?,?)";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ssiiii",$class_name,$class_code,$department_id,$programme_id,$level_id,$year_of_completion);
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
.form-input,.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
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
<small style="color:#666">Must be unique</small>
</div>
<div class="form-group">
<label class="form-label required">Programme</label>
<select name="programme_id" class="form-select" required>
<option value="0">-- Select Programme --</option>
<?php foreach($programmes as $prog): ?>
<option value="<?php echo $prog['t_id'];?>" <?php echo(isset($_POST['programme_id'])&&$_POST['programme_id']==$prog['t_id'])?'selected':'';?>>
<?php echo htmlspecialchars($prog['prog_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label class="form-label required">Level</label>
<select name="level_id" class="form-select" required>
<option value="0">-- Select Level --</option>
<?php foreach($levels as $lvl): ?>
<option value="<?php echo $lvl['t_id'];?>" <?php echo(isset($_POST['level_id'])&&$_POST['level_id']==$lvl['t_id'])?'selected':'';?>>
<?php echo htmlspecialchars($lvl['level_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label class="form-label required">Year of Completion</label>
<input type="number" name="year_of_completion" class="form-input" value="<?php echo htmlspecialchars($_POST['year_of_completion']??($current_year+1));?>" min="2000" max="<?php echo $current_year+10;?>" placeholder="e.g., <?php echo $current_year+1;?>" required>
<small style="color:#666">The year this class is expected to graduate</small>
</div>
<button type="submit" class="btn btn-primary">Create Class</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
