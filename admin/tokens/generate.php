<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Generate Evaluation Tokens';
$errors=[];
$success=false;
$generated_count=0;
$query_period="SELECT * FROM view_active_period LIMIT 1";
$result_period=mysqli_query($conn,$query_period);
$active_period=mysqli_fetch_assoc($result_period);
if(!$active_period){
$errors[]='No active academic period set. Please configure an active academic year and semester first.';
}
$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;
$levels=[];
$result_levels=mysqli_query($conn,"SELECT * FROM level ORDER BY level_number");
while($row=mysqli_fetch_assoc($result_levels))$levels[]=$row;
// $dry_run_count > 0 means we've completed a dry-run and are waiting for confirmation.
$dry_run_count = 0;
$dry_run_dept  = 0;
$dry_run_level = 0;

if($_SERVER['REQUEST_METHOD']=='POST'&&empty($errors)){
if(!validate_csrf_token())$errors[]='Invalid security token.';
$department_id=intval($_POST['department_id']??0);
$level_id=intval($_POST['level_id']??0);
$regenerate=isset($_POST['regenerate'])?1:0;
$confirmed=isset($_POST['confirmed'])&&$_POST['confirmed']==='1';
if($department_id==0)$errors[]='Please select a department.';
if($level_id==0)$errors[]='Please select a level.';
if(empty($errors)){
// ── Dry-run: count what would be generated ───────────────────────────────
// This query counts student × course pairs that don't already have a token
// for the active period (respecting the skip-existing logic in the real run).
$role=ROLE_STUDENT;
$stmt_dr=mysqli_prepare($conn,
"SELECT COUNT(*) AS projected
 FROM user_details u
 JOIN courses c ON c.department_id=u.department_id AND c.level_id=u.level_id
 WHERE u.department_id=? AND u.level_id=? AND u.role_id=? AND u.is_active=1
   AND NOT EXISTS (
       SELECT 1 FROM evaluation_tokens et
       WHERE et.student_user_id=u.user_id
         AND et.course_id=c.id
         AND et.academic_year_id=?
         AND et.semester_id=?
   )");
mysqli_stmt_bind_param($stmt_dr,"iiiii",
    $department_id,$level_id,$role,
    $active_period['academic_year_id'],$active_period['semester_id']);
mysqli_stmt_execute($stmt_dr);
$dr_row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dr));
mysqli_stmt_close($stmt_dr);
$projected=(int)($dr_row['projected']??0);

// ── Hard cap check ───────────────────────────────────────────────────────
if($projected>MAX_BULK_TOKEN_GENERATION){
$errors[]='This operation would generate '.$projected.' tokens, which exceeds the single-operation limit of '.MAX_BULK_TOKEN_GENERATION.'. Narrow the selection (choose a smaller level or run department by department) or raise MAX_BULK_TOKEN_GENERATION in config/constants.php.';
}elseif(!$confirmed){
// ── First pass: show the count and ask for confirmation ──────────────────
$dry_run_count=$projected;
$dry_run_dept=$department_id;
$dry_run_level=$level_id;
}else{
// ── Confirmed: generate the tokens ──────────────────────────────────────
mysqli_begin_transaction($conn);
try{
$query_students="SELECT DISTINCT u.user_id FROM user_details u WHERE u.department_id=? AND u.level_id=? AND u.role_id=? AND u.is_active=1";
$stmt_students=mysqli_prepare($conn,$query_students);
mysqli_stmt_bind_param($stmt_students,"iii",$department_id,$level_id,$role);
mysqli_stmt_execute($stmt_students);
$result_students=mysqli_stmt_get_result($stmt_students);
$students=[];
while($row=mysqli_fetch_assoc($result_students))$students[]=$row['user_id'];
mysqli_stmt_close($stmt_students);
if(empty($students)){
$errors[]='No active students found for selected department and level.';
}else{
$query_courses="SELECT id FROM courses WHERE department_id=? AND level_id=?";
$stmt_courses=mysqli_prepare($conn,$query_courses);
mysqli_stmt_bind_param($stmt_courses,"ii",$department_id,$level_id);
mysqli_stmt_execute($stmt_courses);
$result_courses=mysqli_stmt_get_result($stmt_courses);
$courses=[];
while($row=mysqli_fetch_assoc($result_courses))$courses[]=$row['id'];
mysqli_stmt_close($stmt_courses);
if(empty($courses)){
$errors[]='No courses found for selected department and level.';
}else{
foreach($students as $student_id){
foreach($courses as $course_id){
if($regenerate){
$query_check="SELECT token_id FROM evaluation_tokens WHERE student_user_id=? AND course_id=? AND academic_year_id=? AND semester_id=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"iiii",$student_id,$course_id,$active_period['academic_year_id'],$active_period['semester_id']);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0){mysqli_stmt_close($stmt_check);continue;}
mysqli_stmt_close($stmt_check);
}
$token=bin2hex(random_bytes(TOKEN_LENGTH));
$query_insert="INSERT INTO evaluation_tokens (token,student_user_id,course_id,academic_year_id,semester_id,is_used) VALUES (?,?,?,?,?,0)";
$stmt_insert=mysqli_prepare($conn,$query_insert);
mysqli_stmt_bind_param($stmt_insert,"siiii",$token,$student_id,$course_id,$active_period['academic_year_id'],$active_period['semester_id']);
if(mysqli_stmt_execute($stmt_insert)){$generated_count++;}
mysqli_stmt_close($stmt_insert);
}
}
mysqli_commit($conn);
$success=true;
log_audit($conn,$_SESSION['user_id'],AUDIT_TOKEN_GENERATE,'evaluation_tokens',null,null,['count'=>$generated_count,'department_id'=>$department_id,'level_id'=>$level_id,'academic_year_id'=>$active_period['academic_year_id'],'semester_id'=>$active_period['semester_id']]);
$_SESSION['flash_message']="Successfully generated $generated_count evaluation tokens!";
$_SESSION['flash_type']='success';
}
}
}catch(Exception $e){
mysqli_rollback($conn);
error_log('[CES] Token generation failed: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
$errors[]='Token generation failed due to a system error. Please check the server error log or contact the system administrator.';
}
}
}
}
require_once '../../includes/header.php';
?>
<style>
.generate-container{max-width:900px;margin:0 auto}
.info-box{background:white;padding:25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:20px;border-left:4px solid #667eea}
.info-box h3{margin:0 0 10px 0;color:#667eea}
.form-container{background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px}
.form-label.required::after{content:' *';color:#dc3545}
.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px}
.form-checkbox{margin-right:8px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert{padding:15px;border-radius:8px;margin-bottom:20px}
.alert-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24}
.alert-warning{background:#fff3cd;border:1px solid #ffeaa7;color:#856404}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0}
.stat-item{background:#f8f9fa;padding:15px;border-radius:5px;text-align:center}
.stat-value{font-size:32px;font-weight:bold;color:#667eea}
.stat-label{font-size:14px;color:#666;margin-top:5px}
</style>
<div class="generate-container">
<div class="page-header">
<h1>Generate Evaluation Tokens</h1>
<p>Create evaluation tokens for students to access course evaluations</p>
</div>
<?php if(!$active_period): ?>
<div class="alert alert-error">
<strong>⚠️ No Active Period</strong><br>
You must configure an active academic year and semester before generating tokens.
<a href="../academic_years/list.php">Manage Academic Years →</a>
</div>
<?php else: ?>
<div class="info-box">
<h3>📅 Active Period</h3>
<p style="margin:0">
<strong>Academic Year:</strong> <?php echo htmlspecialchars($active_period['academic_year']);?><br>
<strong>Semester:</strong> <?php echo htmlspecialchars($active_period['semester_name']);?>
</p>
</div>
<?php endif;?>
<?php if($success): ?>
<div class="alert alert-success">
<strong>✓ Success!</strong> Generated <?php echo $generated_count;?> evaluation token(s).<br>
<a href="view.php">View All Tokens →</a>
</div>
<?php endif;?>
<?php if(!empty($errors)): ?>
<div class="alert alert-error">
<strong>⚠️ Errors:</strong>
<ul style="margin:10px 0 0 20px;padding:0">
<?php foreach($errors as $error): ?>
<li><?php echo htmlspecialchars($error);?></li>
<?php endforeach;?>
</ul>
</div>
<?php endif;?>
<?php if($active_period): ?>
<div class="form-container">
<form method="POST">
<?php csrf_token_input();?>
<div class="form-group">
<label class="form-label required">Department</label>
<select name="department_id" class="form-select" required>
<option value="0">-- Select Department --</option>
<?php foreach($departments as $dept): ?>
<option value="<?php echo $dept['t_id'];?>">
<?php echo htmlspecialchars($dept['dep_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label class="form-label required">Level</label>
<select name="level_id" class="form-select" required>
<option value="0">-- Select Level --</option>
<?php foreach($levels as $level): ?>
<option value="<?php echo $level['t_id'];?>">
<?php echo htmlspecialchars($level['level_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label>
<input type="checkbox" name="regenerate" class="form-checkbox">
<span>Skip if tokens already exist (don't create duplicates)</span>
</label>
</div>
<?php if($dry_run_count>0): ?>
<input type="hidden" name="confirmed" value="1">
<input type="hidden" name="department_id" value="<?php echo $dry_run_dept; ?>">
<input type="hidden" name="level_id" value="<?php echo $dry_run_level; ?>">
<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;border-radius:8px;margin-bottom:15px">
<strong>⚠️ Confirmation required</strong><br>
This operation will generate <strong><?php echo $dry_run_count; ?></strong> evaluation token<?php echo $dry_run_count!==1?'s':''; ?>.
This cannot be undone. Are you sure you want to continue?
</div>
<button type="submit" class="btn btn-primary">Yes, Generate <?php echo $dry_run_count; ?> Token<?php echo $dry_run_count!==1?'s':''; ?></button>
<a href="generate.php" class="btn btn-secondary">Cancel</a>
<?php else: ?>
<button type="submit" class="btn btn-primary">Preview &amp; Generate Tokens</button>
<a href="../index.php" class="btn btn-secondary">Cancel</a>
<?php endif; ?>
</form>
</div>
<div class="info-box" style="margin-top:20px">
<h3>ℹ️ How Token Generation Works</h3>
<ul style="margin:10px 0;padding-left:20px;line-height:1.8">
<li>Tokens are generated for <strong>each student + course combination</strong></li>
<li>Only <strong>active students</strong> in the selected department and level</li>
<li>Only <strong>courses matching</strong> the department and level</li>
<li>Tokens are valid for <strong>90 days</strong> from generation</li>
<li>Each token can be used <strong>once only</strong></li>
<li>Tokens are tied to the <strong>active academic period</strong></li>
</ul>
</div>
<?php endif;?>
</div>
<?php require_once '../../includes/footer.php';?>
