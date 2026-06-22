<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$semester_id=intval($_GET['id']??0);
$page_title='Edit Semester';
$errors=[];
$query="SELECT * FROM semesters WHERE semester_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$semester_id);
mysqli_stmt_execute($stmt);
$semester=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if(!$semester){$_SESSION['flash_message']='Semester not found.';header("Location:list.php");exit();}
if($_SERVER['REQUEST_METHOD']=='POST'){
if(!validate_csrf_token())$errors[]='Invalid token.';
$semester_name=trim($_POST['semester_name']??'');
$is_active=isset($_POST['is_active'])?1:0;
if(!in_array($semester_name,['First','Second']))$errors[]='Semester name must be First or Second.';
// A6: derive semester_value automatically — no separate field
$semester_value=($semester_name==='First')?1:2;
if(empty($errors)){
$query_check="SELECT semester_id FROM semesters WHERE academic_year_id=? AND semester_name=? AND semester_id!=?";
$stmt_check=mysqli_prepare($conn,$query_check);
mysqli_stmt_bind_param($stmt_check,"isi",$semester['academic_year_id'],$semester_name,$semester_id);
mysqli_stmt_execute($stmt_check);
if(mysqli_stmt_get_result($stmt_check)->num_rows>0)$errors[]='That semester name already exists for this academic year.';
mysqli_stmt_close($stmt_check);
}
if(empty($errors)){
$query="UPDATE semesters SET semester_name=?,semester_value=?,is_active=? WHERE semester_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"siii",$semester_name,$semester_value,$is_active,$semester_id);
if(mysqli_stmt_execute($stmt)){
log_audit($conn,$_SESSION['user_id'],'SEMESTER_UPDATE','semesters',$semester_id,['semester_name'=>$semester['semester_name'],'semester_value'=>$semester['semester_value'],'is_active'=>$semester['is_active']],['semester_name'=>$semester_name,'semester_value'=>$semester_value,'is_active'=>$is_active]);

// B1: when a semester is activated (0→1), auto-generate tokens for all dept×level combos
if($semester['is_active']==0 && $is_active==1){
    $result_ap=mysqli_query($conn,"SELECT academic_year_id FROM semesters WHERE semester_id=$semester_id LIMIT 1");
    $ap_row=mysqli_fetch_assoc($result_ap);
    $year_id=$ap_row['academic_year_id'];
    $role=ROLE_STUDENT;
    $stmt_pairs=mysqli_prepare($conn,
        "SELECT DISTINCT u.department_id, u.level_id
         FROM user_details u WHERE u.role_id=? AND u.is_active=1");
    mysqli_stmt_bind_param($stmt_pairs,"i",$role);
    mysqli_stmt_execute($stmt_pairs);
    $pairs_res=mysqli_stmt_get_result($stmt_pairs);
    $auto_gen=0;
    while($pair=mysqli_fetch_assoc($pairs_res)){
        $did=$pair['department_id'];
        $lid=$pair['level_id'];
        $stmt_s=mysqli_prepare($conn,
            "SELECT DISTINCT user_id FROM user_details WHERE department_id=? AND level_id=? AND role_id=? AND is_active=1");
        mysqli_stmt_bind_param($stmt_s,"iii",$did,$lid,$role);
        mysqli_stmt_execute($stmt_s);
        $students=[];
        while($r=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_s)))$students[]=$r['user_id'];
        mysqli_stmt_close($stmt_s);
        $stmt_c=mysqli_prepare($conn,"SELECT id FROM courses WHERE department_id=? AND level_id=?");
        mysqli_stmt_bind_param($stmt_c,"ii",$did,$lid);
        mysqli_stmt_execute($stmt_c);
        $courses=[];
        while($r=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c)))$courses[]=$r['id'];
        mysqli_stmt_close($stmt_c);
        foreach($students as $sid){
            foreach($courses as $cid){
                $stmt_chk=mysqli_prepare($conn,
                    "SELECT token_id FROM evaluation_tokens WHERE student_user_id=? AND course_id=? AND academic_year_id=? AND semester_id=?");
                mysqli_stmt_bind_param($stmt_chk,"iiii",$sid,$cid,$year_id,$semester_id);
                mysqli_stmt_execute($stmt_chk);
                if(mysqli_stmt_get_result($stmt_chk)->num_rows>0){mysqli_stmt_close($stmt_chk);continue;}
                mysqli_stmt_close($stmt_chk);
                $token=bin2hex(random_bytes(TOKEN_LENGTH));
                $stmt_i=mysqli_prepare($conn,
                    "INSERT INTO evaluation_tokens (token,student_user_id,course_id,academic_year_id,semester_id,is_used) VALUES (?,?,?,?,?,0)");
                mysqli_stmt_bind_param($stmt_i,"siiii",$token,$sid,$cid,$year_id,$semester_id);
                if(mysqli_stmt_execute($stmt_i))$auto_gen++;
                mysqli_stmt_close($stmt_i);
            }
        }
    }
    mysqli_stmt_close($stmt_pairs);
    if($auto_gen>0){
        log_audit($conn,$_SESSION['user_id'],AUDIT_TOKEN_GENERATE,'evaluation_tokens',null,null,
            ['auto'=>true,'count'=>$auto_gen,'semester_id'=>$semester_id,'academic_year_id'=>$year_id]);
        $_SESSION['flash_message']="Semester updated and $auto_gen evaluation token(s) generated automatically.";
        $_SESSION['flash_type']='success';
        header("Location:list.php");
        exit();
    }
}

// B7: when a semester transitions from active → inactive, expire all unused tokens
// so students can no longer access them. Completed tokens (is_used=1) are kept.
if($semester['is_active']==1 && $is_active==0){
    $stmt_expire=mysqli_prepare($conn,
        "UPDATE evaluation_tokens SET is_used=1, used_at=NOW() WHERE semester_id=? AND academic_year_id=? AND is_used=0");
    mysqli_stmt_bind_param($stmt_expire,"ii",$semester_id,$semester['academic_year_id']);
    mysqli_stmt_execute($stmt_expire);
    $expired_count=mysqli_affected_rows($conn);
    mysqli_stmt_close($stmt_expire);
    if($expired_count>0){
        log_audit($conn,$_SESSION['user_id'],'TOKENS_EXPIRED','evaluation_tokens',null,null,['semester_id'=>$semester_id,'count'=>$expired_count]);
    }
}

$_SESSION['flash_message']='Semester updated!';
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
.warn-box{background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:12px 15px;border-radius:8px;margin-bottom:20px;font-size:13px}
</style>
<div class="page-header"><h1>Edit Semester</h1></div>
<?php if($semester['is_active']): ?>
<div class="warn-box">
⚠️ <strong>Deactivating this semester will expire all unused evaluation tokens</strong> — students will no longer be able to submit evaluations for this period.
</div>
<?php endif;?>
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
<label class="form-label required">Semester</label>
<select name="semester_name" class="form-input" required>
<option value="First" <?php echo $semester['semester_name']==='First'?'selected':'';?>>First (value: 1)</option>
<option value="Second" <?php echo $semester['semester_name']==='Second'?'selected':'';?>>Second (value: 2)</option>
</select>
<small style="color:#666">The numeric value (1 or 2) is derived automatically.</small>
</div>
<div class="form-group">
<label><input type="checkbox" name="is_active" <?php echo $semester['is_active']?'checked':'';?>> Active</label>
</div>
<button type="submit" class="btn btn-primary">Update Semester</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php';?>
