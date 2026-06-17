<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Assign Advisor';
$errors=[];
$class_id=isset($_GET['class_id'])?intval($_GET['class_id']):0;
$query_class="SELECT c.*,d.dep_name FROM classes c LEFT JOIN department d ON c.department_id=d.t_id WHERE c.t_id=?";
$stmt_class=mysqli_prepare($conn,$query_class);
mysqli_stmt_bind_param($stmt_class,"i",$class_id);
mysqli_stmt_execute($stmt_class);
$class=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_class));
mysqli_stmt_close($stmt_class);
if(!$class){$_SESSION['flash_message']='Class not found.';header("Location:list.php");exit();}
$query_advisors="SELECT user_id,f_name,l_name,email,department_id FROM user_details WHERE role_id=? AND is_active=1 ORDER BY f_name,l_name";
$stmt_advisors=mysqli_prepare($conn,$query_advisors);
$role_advisor=ROLE_ADVISOR;
mysqli_stmt_bind_param($stmt_advisors,"i",$role_advisor);
mysqli_stmt_execute($stmt_advisors);
$result_advisors=mysqli_stmt_get_result($stmt_advisors);
$advisors=[];
while($row=mysqli_fetch_assoc($result_advisors))$advisors[]=$row;
mysqli_stmt_close($stmt_advisors);
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$advisor_id=intval($_POST['advisor_id']??0);
if($advisor_id==0)$errors[]='Please select an advisor.';
if(empty($errors)){
$query="UPDATE classes SET advisor_user_id=? WHERE t_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"ii",$advisor_id,$class_id);
if(mysqli_stmt_execute($stmt)){
$_SESSION['flash_message']='Advisor assigned successfully!';
$_SESSION['flash_type']='success';
header("Location:list.php");
exit();
}else{$errors[]='Assignment failed.';}
mysqli_stmt_close($stmt);
}
}
require_once '../../includes/header.php';
?>
<style>
.form-container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.class-info{background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px;border-left:4px solid #667eea}
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
<h1>Assign Advisor</h1>
<p>Assign an advisor to monitor this class</p>
</div>
<div class="class-info">
<h3 style="margin:0 0 10px 0">Class Information</h3>
<p style="margin:5px 0"><strong>Class:</strong> <?php echo htmlspecialchars($class['class_name']);?></p>
<p style="margin:5px 0"><strong>Code:</strong> <?php echo htmlspecialchars($class['class_code']);?></p>
<p style="margin:5px 0"><strong>Department:</strong> <?php echo htmlspecialchars($class['dep_name']);?></p>
</div>
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
<label class="form-label required">Select Advisor</label>
<select name="advisor_id" class="form-select" required>
<option value="0">-- Select Advisor --</option>
<?php foreach($advisors as $advisor): 
$dept_match=$advisor['department_id']==$class['department_id'];
?>
<option value="<?php echo $advisor['user_id'];?>" <?php echo $dept_match?'style="font-weight:bold"':'';?>>
<?php echo htmlspecialchars($advisor['f_name'].' '.$advisor['l_name']);?>
<?php if($dept_match)echo ' ★ (Same Dept)';?>
</option>
<?php endforeach;?>
</select>
<small style="color:#666">★ Advisors from the same department are highlighted</small>
</div>
<button type="submit" class="btn btn-primary">Assign Advisor</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
