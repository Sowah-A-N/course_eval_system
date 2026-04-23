<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Response Summary';
$query_overall="SELECT COUNT(DISTINCT et.token_id)as total_tokens,COUNT(DISTINCT CASE WHEN et.is_used=1 THEN et.token_id END)as used_tokens,COUNT(DISTINCT e.evaluation_id)as total_evaluations FROM evaluation_tokens et LEFT JOIN evaluations e ON et.token=e.token";
$overall=mysqli_fetch_assoc(mysqli_query($conn,$query_overall));
$query_by_dept="SELECT d.dep_name,COUNT(DISTINCT et.token_id)as total_tokens,COUNT(DISTINCT CASE WHEN et.is_used=1 THEN et.token_id END)as used_tokens FROM department d LEFT JOIN courses c ON d.t_id=c.department_id LEFT JOIN evaluation_tokens et ON c.id=et.course_id GROUP BY d.t_id ORDER BY d.dep_name";
$result_dept=mysqli_query($conn,$query_by_dept);
$by_dept=[];
while($row=mysqli_fetch_assoc($result_dept))$by_dept[]=$row;
$query_by_level="SELECT l.level_name,COUNT(DISTINCT et.token_id)as total_tokens,COUNT(DISTINCT CASE WHEN et.is_used=1 THEN et.token_id END)as used_tokens FROM level l LEFT JOIN user_details u ON l.t_id=u.level_id LEFT JOIN evaluation_tokens et ON u.user_id=et.student_user_id GROUP BY l.t_id ORDER BY l.level_number";
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
<div class="stat-label">Total Tokens</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo number_format($overall['used_tokens']);?></div>
<div class="stat-label">Used Tokens</div>
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
