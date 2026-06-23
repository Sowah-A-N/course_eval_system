<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Generate Evaluation Tokens';
$errors=[];
$success=false;
$generated_count=0;

$result_period=mysqli_query($conn,"SELECT * FROM view_active_period LIMIT 1");
$active_period=mysqli_fetch_assoc($result_period);
if(!$active_period)$errors[]='No active academic period. Configure an active academic year and semester first.';

$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;

$levels=[];
$result_levels=mysqli_query($conn,"SELECT * FROM level ORDER BY level_number");
while($row=mysqli_fetch_assoc($result_levels))$levels[]=$row;

$dry_run_count = 0;
$dry_run_dept  = 0;
$dry_run_level = 0;
$dry_run_regen = 0;
$dry_run_bulk  = false; // true = "generate for all"

// ── Helper: count projected tokens for one dept × level pair ─────────────────
function count_projected(mysqli $conn, int $dept_id, int $level_id, int $year_id, int $sem_id): int {
    $role = ROLE_STUDENT;
    $stmt = mysqli_prepare($conn,
        "SELECT COUNT(*) AS n
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
    mysqli_stmt_bind_param($stmt,"iiiii",$dept_id,$level_id,$role,$year_id,$sem_id);
    mysqli_stmt_execute($stmt);
    $n = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'] ?? 0);
    mysqli_stmt_close($stmt);
    return $n;
}

// ── Helper: generate tokens for one dept × level pair ────────────────────────
function generate_tokens(mysqli $conn, int $dept_id, int $level_id, int $year_id, int $sem_id, bool $regenerate): int {
    $role = ROLE_STUDENT;
    $stmt_s = mysqli_prepare($conn,"SELECT DISTINCT u.user_id FROM user_details u WHERE u.department_id=? AND u.level_id=? AND u.role_id=? AND u.is_active=1");
    mysqli_stmt_bind_param($stmt_s,"iii",$dept_id,$level_id,$role);
    mysqli_stmt_execute($stmt_s);
    $res_s=mysqli_stmt_get_result($stmt_s);
    $students=[];
    while($r=mysqli_fetch_assoc($res_s))$students[]=$r['user_id'];
    mysqli_stmt_close($stmt_s);
    if(empty($students))return 0;

    $stmt_c = mysqli_prepare($conn,"SELECT id FROM courses WHERE department_id=? AND level_id=?");
    mysqli_stmt_bind_param($stmt_c,"ii",$dept_id,$level_id);
    mysqli_stmt_execute($stmt_c);
    $res_c=mysqli_stmt_get_result($stmt_c);
    $courses=[];
    while($r=mysqli_fetch_assoc($res_c))$courses[]=$r['id'];
    mysqli_stmt_close($stmt_c);
    if(empty($courses))return 0;

    $count=0;
    foreach($students as $sid){
        foreach($courses as $cid){
            if(!$regenerate){
                $stmt_chk=mysqli_prepare($conn,"SELECT token_id FROM evaluation_tokens WHERE student_user_id=? AND course_id=? AND academic_year_id=? AND semester_id=?");
                mysqli_stmt_bind_param($stmt_chk,"iiii",$sid,$cid,$year_id,$sem_id);
                mysqli_stmt_execute($stmt_chk);
                if(mysqli_stmt_get_result($stmt_chk)->num_rows>0){mysqli_stmt_close($stmt_chk);continue;}
                mysqli_stmt_close($stmt_chk);
            }
            $token=bin2hex(random_bytes(TOKEN_LENGTH));
            $stmt_i=mysqli_prepare($conn,"INSERT INTO evaluation_tokens (token,student_user_id,course_id,academic_year_id,semester_id,is_used) VALUES (?,?,?,?,?,0)");
            mysqli_stmt_bind_param($stmt_i,"siiii",$token,$sid,$cid,$year_id,$sem_id);
            if(mysqli_stmt_execute($stmt_i))$count++;
            mysqli_stmt_close($stmt_i);
        }
    }
    return $count;
}

if($_SERVER['REQUEST_METHOD']=='POST' && empty($errors)){
    if(!validate_csrf_token())$errors[]='Invalid security token.';

    $bulk      = isset($_POST['bulk_all']) && $_POST['bulk_all']==='1';
    $dept_post = $bulk ? 0 : intval($_POST['department_id']??0);
    $level_post= $bulk ? 0 : intval($_POST['level_id']??0);
    $regenerate= isset($_POST['regenerate']) ? 1 : 0;
    $confirmed = isset($_POST['confirmed']) && $_POST['confirmed']==='1';

    if(!$bulk){
        if($dept_post==0)$errors[]='Please select a department.';
        if($level_post==0)$errors[]='Please select a level.';
    }

    if(empty($errors)){
        // Build the list of dept×level pairs to process
        $pairs=[];
        if($bulk){
            foreach($departments as $d){
                foreach($levels as $l){
                    $pairs[]=[$d['t_id'], $l['t_id']];
                }
            }
        }else{
            $pairs=[[$dept_post, $level_post]];
        }

        // Count projected tokens
        $projected=0;
        foreach($pairs as [$did,$lid]){
            $projected+=count_projected($conn,$did,$lid,$active_period['academic_year_id'],$active_period['semester_id']);
        }

        if($projected > MAX_BULK_TOKEN_GENERATION && !$bulk){
            $errors[]='This would generate '.$projected.' tokens, exceeding the limit of '.MAX_BULK_TOKEN_GENERATION.'. Select a smaller scope or raise MAX_BULK_TOKEN_GENERATION.';
        }elseif(!$confirmed){
            $dry_run_count = $projected;
            $dry_run_dept  = $dept_post;
            $dry_run_level = $level_post;
            $dry_run_regen = $regenerate;
            $dry_run_bulk  = $bulk;
        }else{
            // Generate
            mysqli_begin_transaction($conn);
            try{
                foreach($pairs as [$did,$lid]){
                    $generated_count += generate_tokens($conn,$did,$lid,
                        $active_period['academic_year_id'],$active_period['semester_id'],
                        (bool)$regenerate);
                }
                mysqli_commit($conn);
                $success=true;
                log_audit($conn,$_SESSION['user_id'],AUDIT_TOKEN_GENERATE,'evaluation_tokens',null,null,
                    ['count'=>$generated_count,'bulk'=>$bulk,'department_id'=>$dept_post,'level_id'=>$level_post,
                     'academic_year_id'=>$active_period['academic_year_id'],'semester_id'=>$active_period['semester_id']]);
                $_SESSION['flash_message']="Generated $generated_count evaluation token(s) successfully.";
                $_SESSION['flash_type']='success';
            }catch(Exception $e){
                mysqli_rollback($conn);
                error_log('[CES] Token generation failed: '.$e->getMessage());
                $errors[]='Token generation failed. Please check the error log.';
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
.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
.form-checkbox{margin-right:8px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-success{background:#28a745;color:white}
.btn-secondary{background:#6c757d;color:white}
.alert{padding:15px;border-radius:8px;margin-bottom:20px}
.alert-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24}
.alert-warning{background:#fff3cd;border:1px solid #ffeaa7;color:#856404}
.bulk-option{background:#f0fdf4;border:2px solid #86efac;border-radius:8px;padding:16px;margin-bottom:16px}
.bulk-option h4{color:#166534;margin:0 0 6px 0;font-size:14px}
.bulk-option p{color:#15803d;font-size:13px;margin:0}
.or-divider{text-align:center;margin:16px 0;font-size:13px;color:#888;position:relative}
.or-divider::before,.or-divider::after{content:'';position:absolute;top:50%;width:45%;height:1px;background:#e0e0e0}
.or-divider::before{left:0}.or-divider::after{right:0}
</style>
<div class="generate-container">
<div class="page-header">
<h1>Generate Evaluation Tokens</h1>
<p>Create tokens for students to access course evaluations</p>
</div>

<?php if(!$active_period): ?>
<div class="alert alert-error">
<strong>⚠️ No Active Period</strong><br>
Configure an active academic year and semester before generating tokens.
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
<?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e);?></li><?php endforeach;?>
</ul>
</div>
<?php endif;?>

<div class="form-container">
<form method="POST" id="gen-form">
<?php csrf_token_input();?>

<?php if($dry_run_count > 0): ?>
<!-- Confirmation step -->
<input type="hidden" name="confirmed" value="1">
<input type="hidden" name="department_id" value="<?php echo $dry_run_dept;?>">
<input type="hidden" name="level_id" value="<?php echo $dry_run_level;?>">
<?php if($dry_run_regen): ?><input type="hidden" name="regenerate" value="1"><?php endif;?>
<?php if($dry_run_bulk): ?><input type="hidden" name="bulk_all" value="1"><?php endif;?>
<div class="alert-warning alert" style="margin-bottom:20px">
<strong>⚠️ Confirmation required</strong><br>
This will generate <strong><?php echo $dry_run_bulk ? 'up to ' : '';?><?php echo number_format($dry_run_count);?></strong> evaluation token<?php echo $dry_run_count!==1?'s':'';?><?php echo $dry_run_bulk?' across all departments and levels':'';?>.
This cannot be undone.
</div>
<button type="submit" class="btn btn-primary">
    Yes, Generate <?php echo number_format($dry_run_count);?> Token<?php echo $dry_run_count!==1?'s':'';?>
</button>
<a href="generate.php" class="btn btn-secondary">Cancel</a>

<?php else: ?>
<!-- A3: Bulk option -->
<div class="bulk-option">
    <h4>⚡ Generate for All Departments &amp; Levels</h4>
    <p>One click to generate tokens for every active student across all departments and levels in the current period.</p>
    <div style="margin-top:12px">
        <label style="font-size:13px;cursor:pointer">
            <input type="checkbox" name="regenerate" class="form-checkbox" id="regen-bulk"> Skip if token already exists (recommended)
        </label>
    </div>
    <button type="submit" name="bulk_all" value="1" class="btn btn-success" style="margin-top:12px"
            onclick="syncRegen()">Preview &amp; Generate All</button>
</div>

<div class="or-divider">or generate for a specific group</div>

<div class="form-group">
<label class="form-label required">Department</label>
<select name="department_id" class="form-select" required>
<option value="0">-- Select Department --</option>
<?php foreach($departments as $dept): ?>
<option value="<?php echo $dept['t_id'];?>"><?php echo htmlspecialchars($dept['dep_name']);?></option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label class="form-label required">Level</label>
<select name="level_id" class="form-select" required>
<option value="0">-- Select Level --</option>
<?php foreach($levels as $level): ?>
<option value="<?php echo $level['t_id'];?>"><?php echo htmlspecialchars($level['level_name']);?></option>
<?php endforeach;?>
</select>
</div>
<div class="form-group">
<label>
<input type="checkbox" name="regenerate" class="form-checkbox" id="regen-single">
<span>Skip if token already exists (don't create duplicates)</span>
</label>
</div>
<button type="submit" class="btn btn-primary" onclick="document.getElementById('gen-form').removeAttribute('data-bulk')">
    Preview &amp; Generate Tokens
</button>
<a href="../index.php" class="btn btn-secondary">Cancel</a>
<?php endif;?>

</form>
</div>

<div class="info-box" style="margin-top:20px">
<h3>ℹ️ How Token Generation Works</h3>
<ul style="margin:10px 0;padding-left:20px;line-height:1.8">
<li>Tokens are generated for <strong>each active student × course</strong> combination</li>
<li>Only <strong>active students</strong> matching the selected department and level</li>
<li>Each token can be used <strong>once only</strong></li>
<li>Tokens are tied to the <strong>active academic period</strong></li>
</ul>
</div>
<?php endif;?>
</div>

<script>
function syncRegen(){
    // Mirror the "skip existing" checkbox state from the bulk option to the single select
    var bulk=document.getElementById('regen-bulk');
    var single=document.getElementById('regen-single');
    if(bulk&&single)single.checked=bulk.checked;
}
</script>
<?php require_once '../../includes/footer.php';?>
