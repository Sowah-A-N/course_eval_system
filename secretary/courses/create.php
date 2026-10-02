<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_SECRETARY){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$page_title='Add New Course';
$errors=[];
$levels=[];
$result_levels=mysqli_query($conn,"SELECT * FROM level ORDER BY level_number");
while($row=mysqli_fetch_assoc($result_levels))$levels[]=$row;
$semesters=[];
$result_sems=mysqli_query($conn,"SELECT * FROM semesters ORDER BY semester_value");
while($row=mysqli_fetch_assoc($result_sems))$semesters[]=$row;
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid security token.';
$course_code=trim($_POST['course_code']??'');
$name=trim($_POST['name']??'');
$credit_hours=intval($_POST['credit_hours']??0);
$level_id=intval($_POST['level_id']??0);
$semester_id=intval($_POST['semester_id']??0);
$course_type=(($_POST['course_type']??'regular')==='short')?'short':'regular';
$eval_start_date=trim($_POST['eval_start_date']??'');
$eval_end_date=trim($_POST['eval_end_date']??'');
if(empty($course_code))$errors[]='Course code required.';
if(empty($name))$errors[]='Course name required.';
if($credit_hours<=0)$errors[]='Credit hours must be positive.';
if($level_id==0)$errors[]='Please select a level.';
if($semester_id==0)$errors[]='Please select a semester.';
if($course_type==='short'){
if($eval_start_date===''||$eval_end_date==='')$errors[]='Short courses require an evaluation open and close date.';
foreach([$eval_start_date,$eval_end_date] as $d){if($d!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d))$errors[]='Invalid evaluation date format.';}
if($eval_start_date!==''&&$eval_end_date!==''&&$eval_end_date<$eval_start_date)$errors[]='Evaluation close date must be on or after the open date.';
$eval_start_val=$eval_start_date;$eval_end_val=$eval_end_date;
}else{$eval_start_val=null;$eval_end_val=null;}
$course_audience=($course_type==='short'&&in_array($_POST['course_audience']??'',['cohort','department','institution'],true))?$_POST['course_audience']:'cohort';
if(empty($errors)){
$query_check="SELECT id FROM courses WHERE course_code=? AND department_id=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"si",$course_code,$department_id);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='Course code exists in this department.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$query="INSERT INTO courses (course_code,name,credit_hours,level_id,semester_id,department_id,course_type,course_audience,eval_start_date,eval_end_date) VALUES (?,?,?,?,?,?,?,?,?,?)";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ssiiiissss",$course_code,$name,$credit_hours,$level_id,$semester_id,$department_id,$course_type,$course_audience,$eval_start_val,$eval_end_val);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Course created successfully!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}else{$errors[]='Error creating course.';}
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
.form-input:focus,.form-select:focus{outline:none;border-color:#667eea}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Add New Course</h1>
<p>Create a new course record</p>
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
<label class="form-label required">Course Code</label>
<input type="text" name="course_code" class="form-input" value="<?php echo htmlspecialchars($_POST['course_code']??'');?>" placeholder="e.g., CS101" required>
</div>
<div class="form-group">
<label class="form-label required">Course Name</label>
<input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($_POST['name']??'');?>" placeholder="e.g., Introduction to Computer Science" required>
</div>
<div class="form-group">
<label class="form-label required">Credit Hours</label>
<input type="number" name="credit_hours" class="form-input" value="<?php echo htmlspecialchars($_POST['credit_hours']??'3');?>" min="1" max="10" required>
</div>
<div class="form-group">
<label class="form-label required">Level</label>
<select name="level_id" class="form-select" required>
<option value="0">-- Select Level --</option>
<?php foreach($levels as $level): ?>
<option value="<?php echo $level['t_id'];?>" <?php echo(isset($_POST['level_id'])&&$_POST['level_id']==$level['t_id'])?'selected':'';?>>
<?php echo htmlspecialchars($level['level_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label class="form-label required">Semester</label>
<select name="semester_id" class="form-select" required>
<option value="0">-- Select Semester --</option>
<?php foreach($semesters as $sem): ?>
<option value="<?php echo $sem['semester_id'];?>" <?php echo(isset($_POST['semester_id'])&&$_POST['semester_id']==$sem['semester_id'])?'selected':'';?>>
<?php echo htmlspecialchars($sem['semester_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label class="form-label required">Course Type</label>
<select name="course_type" id="course_type" class="form-select" required>
<option value="regular" <?php echo(($_POST['course_type']??'regular')==='regular')?'selected':'';?>>Regular course (uses the semester window)</option>
<option value="short" <?php echo(($_POST['course_type']??'')==='short')?'selected':'';?>>Short course / seminar (own evaluation window)</option>
</select>
</div>
<div class="form-group" id="short_window" style="display:none">
<label class="form-label">Audience (short courses)</label>
<select name="course_audience" class="form-select">
<option value="cohort" <?php echo(($_POST['course_audience']??'cohort')==='cohort')?'selected':'';?>>Department + Level (one cohort)</option>
<option value="department" <?php echo(($_POST['course_audience']??'')==='department')?'selected':'';?>>Whole Department (any level)</option>
<option value="institution" <?php echo(($_POST['course_audience']??'')==='institution')?'selected':'';?>>Institution-wide (all students)</option>
</select>
<small style="color:#666">Who may evaluate this seminar. You can also set an exact attendee list after saving.</small>
<label class="form-label" style="margin-top:14px">Evaluation Window</label>
<div style="display:flex;gap:12px;flex-wrap:wrap">
<input type="date" name="eval_start_date" class="form-input" style="flex:1;min-width:160px" value="<?php echo htmlspecialchars($_POST['eval_start_date']??'');?>">
<input type="date" name="eval_end_date" class="form-input" style="flex:1;min-width:160px" value="<?php echo htmlspecialchars($_POST['eval_end_date']??'');?>">
</div>
<small style="color:#666">Opens on the last day of the course and stays open until the close date.</small>
</div>
<button type="submit" class="btn btn-primary">Create Course</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<script>
(function(){
var sel=document.getElementById('course_type'),win=document.getElementById('short_window');
function t(){win.style.display=sel.value==='short'?'block':'none';}
sel.addEventListener('change',t);t();
}());
</script>
<?php require_once '../../includes/footer.php';?>
