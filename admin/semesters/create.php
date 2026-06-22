<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Add New Semester';
$errors=[];
$academic_years=[];
$result_ay=mysqli_query($conn,"SELECT academic_year_id,year_label FROM academic_year ORDER BY start_year DESC");
while($row=mysqli_fetch_assoc($result_ay))$academic_years[]=$row;
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid security token.';
$academic_year_id=intval($_POST['academic_year_id']??0);
$semester_name=trim($_POST['semester_name']??'');
$is_active=isset($_POST['is_active'])?1:0;
if($academic_year_id==0)$errors[]='Academic year required.';
if(!in_array($semester_name,['First','Second']))$errors[]='Semester name must be First or Second.';
// A6: derive integer value from name — no separate field needed
$semester_value=($semester_name==='First')?1:2;
if(empty($errors)){
$query_check="SELECT semester_id FROM semesters WHERE academic_year_id=? AND semester_name=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"is",$academic_year_id,$semester_name);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='That semester already exists for the selected academic year.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$query="INSERT INTO semesters (academic_year_id,semester_name,semester_value,is_active) VALUES (?,?,?,?)";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"isii",$academic_year_id,$semester_name,$semester_value,$is_active);
if(mysqli_stmt_execute($stmt)){
$new_semester_id=mysqli_insert_id($conn);
log_audit($conn,$_SESSION['user_id'],'SEMESTER_CREATE','semesters',$new_semester_id,null,['academic_year_id'=>$academic_year_id,'semester_name'=>$semester_name,'semester_value'=>$semester_value]);
$_SESSION['flash_message']='Semester created successfully!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}else{$errors[]='Error creating semester.';}
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
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Add New Semester</h1>
<p>Create a new semester configuration</p>
</div>
<div class="info-box">
<strong>💡 Note:</strong> Each semester belongs to an academic year. The numeric value (1 or 2) is derived automatically from the name you select.
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
<label class="form-label required">Academic Year</label>
<select name="academic_year_id" class="form-select" required>
<option value="0">-- Select Academic Year --</option>
<?php foreach($academic_years as $ay): ?>
<option value="<?php echo $ay['academic_year_id'];?>" <?php echo(isset($_POST['academic_year_id'])&&$_POST['academic_year_id']==$ay['academic_year_id'])?'selected':'';?>>
<?php echo htmlspecialchars($ay['year_label']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label class="form-label required">Semester</label>
<select name="semester_name" class="form-select" required>
<option value="">-- Select --</option>
<option value="First" <?php echo(($_POST['semester_name']??'')==='First')?'selected':'';?>>First (value: 1)</option>
<option value="Second" <?php echo(($_POST['semester_name']??'')==='Second')?'selected':'';?>>Second (value: 2)</option>
</select>
<small style="color:#666">The numeric ordering value is derived automatically.</small>
</div>
<div class="form-group">
<label>
<input type="checkbox" name="is_active" <?php echo(isset($_POST['is_active'])||!isset($_POST['semester_name']))?'checked':'';?>>
Active
</label>
</div>
<button type="submit" class="btn btn-primary">Create Semester</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
