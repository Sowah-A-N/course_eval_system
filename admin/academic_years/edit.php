<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$year_id=intval($_GET['id']??0);
$page_title='Edit Academic Year';
$errors=[];
$stmt=mysqli_prepare($conn,"SELECT * FROM academic_year WHERE academic_year_id=?");
mysqli_stmt_bind_param($stmt,"i",$year_id);
mysqli_stmt_execute($stmt);
$year=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$year){$_SESSION['flash_message']='Academic year not found.';header("Location:list.php");exit();}
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$start_year=intval($_POST['start_year']??0);
if($start_year<2000||$start_year>2100)$errors[]='Please enter a valid 4-digit start year (e.g. 2024).';
if(empty($errors)){
$stmt_check=mysqli_prepare($conn,"SELECT academic_year_id FROM academic_year WHERE start_year=? AND academic_year_id!=?");
mysqli_stmt_bind_param($stmt_check,"ii",$start_year,$year_id);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='An academic year starting in '.$start_year.' already exists.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
// Only start_year is writeable; end_year and year_label are GENERATED columns
$stmt_upd=mysqli_prepare($conn,"UPDATE academic_year SET start_year=? WHERE academic_year_id=?");
mysqli_stmt_bind_param($stmt_upd,"ii",$start_year,$year_id);
if(mysqli_stmt_execute($stmt_upd)){
$_SESSION['flash_message']='Academic year updated!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}else{$errors[]='Update failed.';}
mysqli_stmt_close($stmt_upd);
}
}
require_once '../../includes/header.php';
?>
<style>
.form-container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px}
.form-label.required::after{content:' *';color:#dc3545}
.form-input{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px}
.form-readonly{width:100%;padding:10px;border:2px solid #e9ecef;border-radius:5px;background:#f8f9fa;color:#6c757d}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header"><h1>Edit Academic Year</h1></div>
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
<label class="form-label required">Start Year</label>
<input type="number" name="start_year" class="form-input" value="<?php echo htmlspecialchars($_POST['start_year']??$year['start_year']);?>" min="2000" max="2100" required>
</div>
<div class="form-group">
<label class="form-label">Generated Label (read-only)</label>
<input type="text" class="form-readonly" value="<?php echo htmlspecialchars($year['year_label']);?>" disabled>
<small style="color:#666">Updated automatically when start year changes.</small>
</div>
<button type="submit" class="btn btn-primary">Update Academic Year</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
