<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){header("Location:../../login.php");exit();}
$page_title='Add New Academic Year';
$errors=[];
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid security token.';
$start_year=intval($_POST['start_year']??0);
if($start_year<2000||$start_year>2100)$errors[]='Please enter a valid 4-digit start year (e.g. 2024).';
if(empty($errors)){
// end_year and year_label are GENERATED columns — insert start_year only
$stmt_check=mysqli_prepare($conn,"SELECT academic_year_id FROM academic_year WHERE start_year=?");
mysqli_stmt_bind_param($stmt_check,"i",$start_year);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='An academic year starting in '.$start_year.' already exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$stmt=mysqli_prepare($conn,"INSERT INTO academic_year (start_year) VALUES (?)");
mysqli_stmt_bind_param($stmt,"i",$start_year);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Academic year '.$start_year.'/'.($start_year+1).' created successfully!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}else{$errors[]='Error creating academic year.';}
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
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Add New Academic Year</h1>
<p>Create a new academic year period</p>
</div>
<div class="info-box">
<strong>💡 Note:</strong> Enter the starting year only. The system will automatically generate the year label (e.g. entering <strong>2024</strong> creates academic year <strong>2024/2025</strong>).
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
<label class="form-label required">Start Year</label>
<input type="number" name="start_year" class="form-input" value="<?php echo htmlspecialchars($_POST['start_year']??date('Y'));?>" min="2000" max="2100" placeholder="e.g., 2024" required>
<small style="color:#666">The end year (<?php echo date('Y')+1;?>) and label are generated automatically.</small>
</div>
<button type="submit" class="btn btn-primary">Create Academic Year</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
