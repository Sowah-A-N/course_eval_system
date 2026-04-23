<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_SECRETARY){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$course_id=intval($_GET['id']??0);
$page_title='Edit Course';
$errors=[];
$query="SELECT * FROM courses WHERE id=? AND department_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ii",$course_id,$department_id);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$course=mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
if(!$course){$_SESSION['flash_message']='Course not found.';header("Location:list.php");exit();}
$levels=[];
$result_levels=mysqli_query($conn,"SELECT * FROM level ORDER BY level_number");
while($row=mysqli_fetch_assoc($result_levels))$levels[]=$row;
$semesters=[];
$result_sems=mysqli_query($conn,"SELECT * FROM semesters ORDER BY semester_value");
while($row=mysqli_fetch_assoc($result_sems))$semesters[]=$row;
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$course_code=trim($_POST['course_code']??'');
$name=trim($_POST['name']??'');
$credit_hours=intval($_POST['credit_hours']??0);
$level_id=intval($_POST['level_id']??0);
$semester_id=intval($_POST['semester_id']??0);
if(empty($course_code))$errors[]='Course code required.';
if(empty($name))$errors[]='Course name required.';
if($credit_hours<=0)$errors[]='Credit hours must be positive.';
if($level_id==0)$errors[]='Select level.';
if($semester_id==0)$errors[]='Select semester.';
if(empty($errors)){
$query_check="SELECT id FROM courses WHERE course_code=? AND department_id=? AND id!=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"sii",$course_code,$department_id,$course_id);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='Course code exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$query="UPDATE courses SET course_code=?,name=?,credit_hours=?,level_id=?,semester_id=? WHERE id=? AND department_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ssiiiii",$course_code,$name,$credit_hours,$level_id,$semester_id,$course_id,$department_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Course updated!';
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
<div class="page-header"><h1>Edit Course</h1></div>
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
<label class="form-label required">Course Code</label>
<input type="text" name="course_code" class="form-input" value="<?php echo htmlspecialchars($course['course_code']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Course Name</label>
<input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($course['name']);?>" required>
</div>
<div class="form-group">
<label class="form-label required">Credit Hours</label>
<input type="number" name="credit_hours" class="form-input" value="<?php echo $course['credit_hours'];?>" min="1" max="10" required>
</div>
<div class="form-group">
<label class="form-label required">Level</label>
<select name="level_id" class="form-select" required>
<?php foreach($levels as $level): ?>
<option value="<?php echo $level['t_id'];?>" <?php echo $course['level_id']==$level['t_id']?'selected':'';?>>
<?php echo htmlspecialchars($level['level_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label class="form-label required">Semester</label>
<select name="semester_id" class="form-select" required>
<?php foreach($semesters as $sem): ?>
<option value="<?php echo $sem['semester_id'];?>" <?php echo $course['semester_id']==$sem['semester_id']?'selected':'';?>>
<?php echo htmlspecialchars($sem['semester_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<button type="submit" class="btn btn-primary">Update Course</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
