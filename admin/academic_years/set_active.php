<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){header("Location:../../login.php");exit();}
$year_id=intval($_GET['id']??0);
$page_title='Set Active Period';
$errors=[];
$query="SELECT * FROM academic_year WHERE academic_year_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$year_id);
mysqli_stmt_execute($stmt);
$year=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$year){$_SESSION['flash_message']='Academic year not found.';$_SESSION['flash_type']='error';header("Location:list.php");exit();}
$semesters=[];
$stmt_sems=mysqli_prepare($conn,"SELECT * FROM semesters WHERE academic_year_id=? ORDER BY semester_value");
mysqli_stmt_bind_param($stmt_sems,"i",$year_id);
mysqli_stmt_execute($stmt_sems);
$result_sems=mysqli_stmt_get_result($stmt_sems);
while($row=mysqli_fetch_assoc($result_sems))$semesters[]=$row;
mysqli_stmt_close($stmt_sems);
$query_current="SELECT * FROM view_active_period LIMIT 1";
$result_current=mysqli_query($conn,$query_current);
$current_period=mysqli_fetch_assoc($result_current);
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$semester_id=intval($_POST['semester_id']??0);
if($semester_id==0)$errors[]='Please select a semester.';
if(empty($errors)){
// Validate that the semester belongs to the selected academic year
$stmt_v=mysqli_prepare($conn,"SELECT semester_id FROM semesters WHERE semester_id=? AND academic_year_id=?");
mysqli_stmt_bind_param($stmt_v,"ii",$semester_id,$year_id);
mysqli_stmt_execute($stmt_v);
if(mysqli_stmt_get_result($stmt_v)->num_rows==0)$errors[]='Selected semester does not belong to this academic year.';
mysqli_stmt_close($stmt_v);
}
if(empty($errors)){
mysqli_begin_transaction($conn);
try{
$query_clear="UPDATE academic_year SET is_active=0";
mysqli_query($conn,$query_clear);
$query_clear_sem="UPDATE semesters SET is_active=0";
mysqli_query($conn,$query_clear_sem);
$query_set_year="UPDATE academic_year SET is_active=1 WHERE academic_year_id=?";
$stmt_year=mysqli_prepare($conn,$query_set_year);
mysqli_stmt_bind_param($stmt_year,"i",$year_id);
mysqli_stmt_execute($stmt_year);
mysqli_stmt_close($stmt_year);
$query_set_sem="UPDATE semesters SET is_active=1 WHERE semester_id=?";
$stmt_sem=mysqli_prepare($conn,$query_set_sem);
mysqli_stmt_bind_param($stmt_sem,"i",$semester_id);
mysqli_stmt_execute($stmt_sem);
mysqli_stmt_close($stmt_sem);
mysqli_commit($conn);
$_SESSION['flash_message']='Active period set successfully!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}catch(Exception $e){
mysqli_rollback($conn);
$errors[]='Error setting active period.';
}
}
}
require_once '../../includes/header.php';
?>
<style>
.form-container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.current-active{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:15px;border-radius:8px;margin-bottom:20px}
.year-box{background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px;border-left:4px solid #667eea}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px}
.form-label.required::after{content:' *';color:#dc3545}
.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Set Active Evaluation Period</h1>
<p>Configure which academic year and semester are active for evaluations</p>
</div>
<?php if($current_period): ?>
<div class="current-active">
<strong>⚠️ Current Active Period:</strong> <?php echo htmlspecialchars($current_period['academic_year'].' - '.$current_period['semester_name']);?><br>
<small>Setting a new active period will replace the current one.</small>
</div>
<?php endif;?>
<?php if(!empty($errors)): ?>
<div class="alert-error">
<strong>⚠️ Errors:</strong>
<ul style="margin:10px 0 0 20px"><?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e);?></li><?php endforeach;?></ul>
</div>
<?php endif;?>
<div class="form-container">
<div class="year-box">
<h3 style="margin:0 0 10px 0">Selected Academic Year</h3>
<div style="font-size:18px;font-weight:600;color:#667eea"><?php echo htmlspecialchars($year['year_label']);?></div>
<div style="font-size:14px;color:#666;margin-top:5px">
<?php echo $year['start_year'];?> &ndash; <?php echo $year['end_year'];?>
</div>
</div>
<form method="POST">
<?php csrf_token_input();?>
<div class="form-group">
<label class="form-label required">Select Semester</label>
<select name="semester_id" class="form-select" required>
<option value="0">-- Select Semester --</option>
<?php foreach($semesters as $sem): ?>
<option value="<?php echo $sem['semester_id'];?>"><?php echo htmlspecialchars($sem['semester_name']);?></option>
<?php endforeach;?>
</select>
</div>
<button type="submit" class="btn btn-primary">Set as Active Period</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
