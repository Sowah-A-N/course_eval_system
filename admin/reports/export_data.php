<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){header("Location:../../login.php");exit();}
$page_title='Export Data';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&$_POST['action']==='download'){
if(!validate_csrf_token()){http_response_code(403);die('Invalid security token.');}
$type=$_POST['type']??'';
header('Content-Type:text/csv');
header('Content-Disposition:attachment;filename="evaluation_export_'.date('Y-m-d').'.csv"');
$output=fopen('php://output','w');
if($type=='evaluations'){
fputcsv($output,['Evaluation ID','Student ID','Course Code','Course Name','Lecturer','Department','Date','Status']);
$query="SELECT e.evaluation_id,u.unique_id,c.course_code,c.name,l.f_name,l.l_name,d.dep_name,e.date_submitted,et.is_used FROM evaluations e JOIN evaluation_tokens et ON e.token_id=et.token_id JOIN user_details u ON et.student_user_id=u.user_id JOIN courses c ON et.course_id=c.id LEFT JOIN user_details l ON et.lecturer_user_id=l.user_id LEFT JOIN department d ON c.department_id=d.t_id ORDER BY e.date_submitted DESC";
$result=mysqli_query($conn,$query);
while($row=mysqli_fetch_assoc($result)){
fputcsv($output,[$row['evaluation_id'],$row['unique_id'],$row['course_code'],$row['name'],$row['f_name'].' '.$row['l_name'],$row['dep_name'],$row['date_submitted'],$row['is_used']?'Used':'Unused']);
}
}elseif($type=='tokens'){
fputcsv($output,['Token ID','Student ID','Course Code','Course Name','Department','Generated Date','Used','Expires']);
$query="SELECT et.token_id,u.unique_id,c.course_code,c.name,d.dep_name,et.generated_at,et.is_used,et.expires_at FROM evaluation_tokens et JOIN user_details u ON et.student_user_id=u.user_id JOIN courses c ON et.course_id=c.id LEFT JOIN department d ON c.department_id=d.t_id ORDER BY et.generated_at DESC";
$result=mysqli_query($conn,$query);
while($row=mysqli_fetch_assoc($result)){
fputcsv($output,[$row['token_id'],$row['unique_id'],$row['course_code'],$row['name'],$row['dep_name'],$row['generated_at'],$row['is_used']?'Yes':'No',$row['expires_at']]);
}
}elseif($type=='responses'){
fputcsv($output,['Response ID','Evaluation ID','Question','Rating']);
$query="SELECT er.response_id,er.evaluation_id,eq.question_text,er.rating FROM evaluation_responses er JOIN evaluation_questions eq ON er.question_id=eq.question_id ORDER BY er.evaluation_id,eq.question_order";
$result=mysqli_query($conn,$query);
while($row=mysqli_fetch_assoc($result)){
fputcsv($output,[$row['response_id'],$row['evaluation_id'],$row['question_text'],$row['rating']]);
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
<form method="POST"><?php csrf_token_input();?><input type="hidden" name="action" value="download"><input type="hidden" name="type" value="evaluations"><button type="submit" class="btn btn-primary">Download CSV</button></form>
</div>
<div class="export-card">
<div class="export-icon">🎫</div>
<div class="export-title">Tokens Export</div>
<div class="export-desc">Export all evaluation tokens including usage status and expiry dates.</div>
<form method="POST"><?php csrf_token_input();?><input type="hidden" name="action" value="download"><input type="hidden" name="type" value="tokens"><button type="submit" class="btn btn-primary">Download CSV</button></form>
</div>
<div class="export-card">
<div class="export-icon">💬</div>
<div class="export-title">Responses Export</div>
<div class="export-desc">Download detailed response data with questions and ratings.</div>
<form method="POST"><?php csrf_token_input();?><input type="hidden" name="action" value="download"><input type="hidden" name="type" value="responses"><button type="submit" class="btn btn-primary">Download CSV</button></form>
</div>
</div>
</div>
<?php require_once '../../includes/footer.php';?>
