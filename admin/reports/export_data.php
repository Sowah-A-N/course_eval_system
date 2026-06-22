<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Export Data';
// C7: available periods for filter dropdowns (used on GET render)
$export_years=[];
$res_ey=mysqli_query($conn,"SELECT academic_year_id,academic_year FROM academic_years ORDER BY academic_year DESC");
while($r=mysqli_fetch_assoc($res_ey))$export_years[]=$r;
$export_sems=[];
$res_es=mysqli_query($conn,"SELECT semester_id,semester_name FROM semesters ORDER BY semester_value");
while($r=mysqli_fetch_assoc($res_es))$export_sems[]=$r;

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&$_POST['action']==='download'){
if(!validate_csrf_token()){http_response_code(403);die('Invalid security token.');}
$type=$_POST['type']??'';
// C7: period filter values from the form
$exp_year=intval($_POST['year_id']??0);
$exp_sem=intval($_POST['semester_id']??0);
// Prevent CSV formula injection (=, +, -, @ prefixes trigger formulas in spreadsheet apps)
function csv_safe(array $fields): array {
    return array_map(function($v) {
        $v = (string)$v;
        if ($v !== '' && in_array($v[0], ['=','+','-','@',"\t","\r"], true)) {
            $v = "'" . $v;
        }
        return $v;
    }, $fields);
}
header('Content-Type:text/csv');
header('Content-Disposition:attachment;filename="evaluation_export_'.date('Y-m-d').'.csv"');
$output=fopen('php://output','w');
// C7: build period WHERE clauses
$per_et_where=''; $per_et_params=[]; $per_et_types='';
if($exp_year>0){$per_et_where.=" AND et.academic_year_id=?";$per_et_params[]=$exp_year;$per_et_types.='i';}
if($exp_sem>0){$per_et_where.=" AND et.semester_id=?";$per_et_params[]=$exp_sem;$per_et_types.='i';}
if($type=='evaluations'){
// ANONYMITY: Student identity (unique_id) is deliberately excluded from this
// export.  The evaluations table stores only an opaque token — not student_user_id.
// Including u.unique_id by joining evaluation_tokens → user_details would
// de-anonymise every evaluation (any reader could correlate evaluation_id → student
// and then pull their exact ratings from the responses export).
// The export therefore contains only aggregate/course data, not the submitting student.
fputcsv($output,['Evaluation ID','Course Code','Course Name','Lecturer(s)','Department','Date']);
$q_where="1=1".$per_et_where;
$query="SELECT e.evaluation_id,c.course_code,c.name,
    GROUP_CONCAT(DISTINCT CONCAT(l.f_name,' ',l.l_name) SEPARATOR '; ') AS lecturer_name,
    d.dep_name,e.evaluation_date
    FROM evaluations e
    JOIN evaluation_tokens et ON e.token=et.token
    JOIN courses c ON et.course_id=c.id
    LEFT JOIN course_lecturers cl ON et.course_id=cl.course_id AND cl.is_active=1
    LEFT JOIN user_details l ON cl.lecturer_user_id=l.user_id
    LEFT JOIN department d ON c.department_id=d.t_id
    WHERE $q_where
    GROUP BY e.evaluation_id,c.course_code,c.name,d.dep_name,e.evaluation_date
    ORDER BY e.evaluation_date DESC";
if(empty($per_et_params)){
    $result=mysqli_query($conn,$query);
}else{
    $stmt=mysqli_prepare($conn,$query);
    mysqli_stmt_bind_param($stmt,$per_et_types,...$per_et_params);
    mysqli_stmt_execute($stmt);$result=mysqli_stmt_get_result($stmt);
}
while($row=mysqli_fetch_assoc($result)){
fputcsv($output,csv_safe([$row['evaluation_id'],$row['course_code'],$row['name'],$row['lecturer_name'],$row['dep_name'],$row['evaluation_date']]));
}
}elseif($type=='tokens'){
// Token export shows which students have been assigned tokens and whether each
// was used — this is operational data (not evaluation content) and is appropriate
// for admin review.  student_user_id is included here because this is pre/post
// submission housekeeping, not the evaluation data itself.
fputcsv($output,['Token ID','Student ID','Course Code','Course Name','Department','Created Date','Used']);
$q_where="1=1".$per_et_where;
$query="SELECT et.token_id,COALESCE(u.unique_id,'[anonymised]') AS unique_id,c.course_code,c.name,d.dep_name,et.created_at,et.is_used
    FROM evaluation_tokens et
    LEFT JOIN user_details u ON et.student_user_id=u.user_id
    JOIN courses c ON et.course_id=c.id
    LEFT JOIN department d ON c.department_id=d.t_id
    WHERE $q_where
    ORDER BY et.created_at DESC";
if(empty($per_et_params)){
    $result=mysqli_query($conn,$query);
}else{
    $stmt=mysqli_prepare($conn,$query);
    mysqli_stmt_bind_param($stmt,$per_et_types,...$per_et_params);
    mysqli_stmt_execute($stmt);$result=mysqli_stmt_get_result($stmt);
}
while($row=mysqli_fetch_assoc($result)){
fputcsv($output,csv_safe([$row['token_id'],$row['unique_id'],$row['course_code'],$row['name'],$row['dep_name'],$row['created_at'],$row['is_used']?'Yes':'No']));
}
}elseif($type=='responses'){
fputcsv($output,['Response ID','Evaluation ID','Question','Rating']);
$q_where="1=1".$per_et_where;
$query="SELECT r.id as response_id,r.evaluation_id,eq.question_text,r.response_value as rating
    FROM responses r
    JOIN evaluation_questions eq ON r.question_id=eq.question_id
    JOIN evaluations e ON r.evaluation_id=e.evaluation_id
    JOIN evaluation_tokens et ON e.token=et.token
    WHERE $q_where
    ORDER BY r.evaluation_id,eq.display_order";
if(empty($per_et_params)){
    $result=mysqli_query($conn,$query);
}else{
    $stmt=mysqli_prepare($conn,$query);
    mysqli_stmt_bind_param($stmt,$per_et_types,...$per_et_params);
    mysqli_stmt_execute($stmt);$result=mysqli_stmt_get_result($stmt);
}
while($row=mysqli_fetch_assoc($result)){
fputcsv($output,csv_safe([$row['response_id'],$row['evaluation_id'],$row['question_text'],$row['rating']]));
}
}
fclose($output);
exit();
}
require_once '../../includes/header.php';
?>
<style>
.export-container{max-width:900px;margin:0 auto}
.export-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px}
.export-card{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center;transition:all 0.3s;border:2px solid transparent}
.export-card:hover{border-color:#667eea;transform:translateY(-5px);box-shadow:0 6px 20px rgba(0,0,0,0.15)}
.export-icon{font-size:64px;margin-bottom:15px}
.export-title{font-size:20px;font-weight:700;margin-bottom:10px;color:#333}
.export-desc{font-size:14px;color:#666;margin-bottom:20px;line-height:1.6}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:8px;margin-bottom:30px}
</style>
<div class="export-container">
<div class="page-header">
<h1>📥 Export Data</h1>
<p>Download evaluation data for external analysis</p>
</div>
<div class="info-box">
<strong>ℹ️ Data Privacy:</strong> Exported data contains personally identifiable information. Handle with care and follow your institution's data protection policies.
</div>
<div class="export-grid">
<div class="export-card">
<div class="export-icon">📋</div>
<div class="export-title">Evaluations Export</div>
<div class="export-desc">Download all completed evaluations with student and course information.</div>
<?php
// C7: shared period filter snippet (reusable inline)
$period_inputs = function($type) use ($export_years,$export_sems) {
    echo '<div style="margin:10px 0 12px;text-align:left">';
    echo '<label style="font-size:12px;color:#666;display:block;margin-bottom:4px">Academic Year</label>';
    echo '<select name="year_id" style="width:100%;padding:6px;font-size:13px;border:1px solid #ddd;border-radius:4px;margin-bottom:8px"><option value="0">All Years</option>';
    foreach($export_years as $yr) echo '<option value="'.$yr['academic_year_id'].'">'.htmlspecialchars($yr['academic_year']).'</option>';
    echo '</select>';
    echo '<label style="font-size:12px;color:#666;display:block;margin-bottom:4px">Semester</label>';
    echo '<select name="semester_id" style="width:100%;padding:6px;font-size:13px;border:1px solid #ddd;border-radius:4px;margin-bottom:8px"><option value="0">All Semesters</option>';
    foreach($export_sems as $sem) echo '<option value="'.$sem['semester_id'].'">'.htmlspecialchars($sem['semester_name']).'</option>';
    echo '</select>';
    echo '</div>';
};
?>
<form method="POST"><?php csrf_token_input();?><input type="hidden" name="action" value="download"><input type="hidden" name="type" value="evaluations"><?php $period_inputs('evaluations');?><button type="submit" class="btn btn-primary">Download CSV</button></form>
</div>
<div class="export-card">
<div class="export-icon">🎫</div>
<div class="export-title">Tokens Export</div>
<div class="export-desc">Export all evaluation tokens including usage status and expiry dates.</div>
<form method="POST"><?php csrf_token_input();?><input type="hidden" name="action" value="download"><input type="hidden" name="type" value="tokens"><?php $period_inputs('tokens');?><button type="submit" class="btn btn-primary">Download CSV</button></form>
</div>
<div class="export-card">
<div class="export-icon">💬</div>
<div class="export-title">Responses Export</div>
<div class="export-desc">Download detailed response data with questions and ratings.</div>
<form method="POST"><?php csrf_token_input();?><input type="hidden" name="action" value="download"><input type="hidden" name="type" value="responses"><?php $period_inputs('responses');?><button type="submit" class="btn btn-primary">Download CSV</button></form>
</div>
</div>
</div>
<?php require_once '../../includes/footer.php';?>
