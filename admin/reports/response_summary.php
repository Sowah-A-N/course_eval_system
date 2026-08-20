<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Response Summary';
// Tokenless: total_tokens -> eligible active students; used_tokens -> distinct
// students who recorded a completion; total_evaluations -> course-scope answer
// containers.
$query_overall="SELECT (SELECT COUNT(*) FROM user_details WHERE role_id=".ROLE_STUDENT." AND is_active=1)as total_tokens,(SELECT COUNT(DISTINCT student_user_id) FROM evaluation_completions)as used_tokens,(SELECT COUNT(*) FROM evaluations WHERE scope='course')as total_evaluations";
$overall=mysqli_fetch_assoc(mysqli_query($conn,$query_overall));
// Per department: eligible students vs distinct students who completed a
// course evaluation for a course in that department.
$query_by_dept="SELECT d.dep_name,(SELECT COUNT(*) FROM user_details u WHERE u.role_id=".ROLE_STUDENT." AND u.is_active=1 AND u.department_id=d.t_id)as total_tokens,(SELECT COUNT(DISTINCT ec.student_user_id) FROM evaluation_completions ec JOIN courses cc ON ec.course_id=cc.id WHERE cc.department_id=d.t_id AND ec.scope='course')as used_tokens FROM department d GROUP BY d.t_id ORDER BY d.dep_name";
$result_dept=mysqli_query($conn,$query_by_dept);
$by_dept=[];
while($row=mysqli_fetch_assoc($result_dept))$by_dept[]=$row;
// Per level: eligible students at the level vs those who recorded any completion.
$query_by_level="SELECT l.level_name,COUNT(DISTINCT CASE WHEN u.role_id=".ROLE_STUDENT." AND u.is_active=1 THEN u.user_id END)as total_tokens,COUNT(DISTINCT ec.student_user_id)as used_tokens FROM level l LEFT JOIN user_details u ON l.t_id=u.level_id LEFT JOIN evaluation_completions ec ON ec.student_user_id=u.user_id GROUP BY l.t_id ORDER BY l.level_number";
$result_level=mysqli_query($conn,$query_by_level);
$by_level=[];
while($row=mysqli_fetch_assoc($result_level))$by_level[]=$row;
require_once '../../includes/header.php';
?>
<style>
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:30px}
.stat-card{background:white;padding:25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center}
.stat-value{font-size:42px;font-weight:bold;color:#667eea}
.stat-label{font-size:14px;color:#666;margin-top:10px}
.report-section{background:white;padding:25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:20px}
.section-title{font-size:20px;font-weight:700;margin-bottom:20px;color:#333;border-bottom:2px solid #667eea;padding-bottom:10px}
.data-row{display:flex;justify-content:space-between;align-items:center;padding:15px;background:#f8f9fa;border-radius:8px;margin-bottom:10px}
.data-label{font-weight:600;color:#333}
.data-stats{display:flex;gap:20px;align-items:center}
.data-value{font-size:16px;color:#667eea;font-weight:600}
.progress-mini{background:#e9ecef;height:8px;width:100px;border-radius:4px;overflow:hidden}
.progress-mini-fill{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);height:100%}
</style>
<div class="page-header">
<h1>📊 Response Summary</h1>
<p>Overall participation and response statistics</p>
</div>
<div class="stats-grid">
<div class="stat-card">
<div class="stat-value"><?php echo number_format($overall['total_tokens']);?></div>
<div class="stat-label">Eligible Students</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo number_format($overall['used_tokens']);?></div>
<div class="stat-label">Students Responded</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo number_format($overall['total_evaluations']);?></div>
<div class="stat-label">Total Evaluations</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $overall['total_tokens']>0?round(($overall['used_tokens']/$overall['total_tokens'])*100,1):0;?>%</div>
<div class="stat-label">Response Rate</div>
</div>
</div>
<div class="report-section">
<div class="section-title">Response Rate by Department</div>
<?php foreach($by_dept as $dept): 
$rate=$dept['total_tokens']>0?($dept['used_tokens']/$dept['total_tokens'])*100:0;
?>
<div class="data-row">
<div class="data-label"><?php echo htmlspecialchars($dept['dep_name']);?></div>
<div class="data-stats">
<div class="data-value"><?php echo $dept['used_tokens'];?>/<?php echo $dept['total_tokens'];?></div>
<div class="progress-mini">
<div class="progress-mini-fill" style="width:<?php echo $rate;?>%"></div>
</div>
<div class="data-value"><?php echo round($rate,1);?>%</div>
</div>
</div>
<?php endforeach;?>
</div>
<div class="report-section">
<div class="section-title">Response Rate by Level</div>
<?php foreach($by_level as $level): 
$rate=$level['total_tokens']>0?($level['used_tokens']/$level['total_tokens'])*100:0;
?>
<div class="data-row">
<div class="data-label"><?php echo htmlspecialchars($level['level_name']);?></div>
<div class="data-stats">
<div class="data-value"><?php echo $level['used_tokens'];?>/<?php echo $level['total_tokens'];?></div>
<div class="progress-mini">
<div class="progress-mini-fill" style="width:<?php echo $rate;?>%"></div>
</div>
<div class="data-value"><?php echo round($rate,1);?>%</div>
</div>
</div>
<?php endforeach;?>
</div>
<?php require_once '../../includes/footer.php';?>
