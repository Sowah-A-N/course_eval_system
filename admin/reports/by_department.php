<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){header("Location:../../login.php");exit();}
$page_title='Department Report';
$query="SELECT d.t_id,d.dep_name,COUNT(DISTINCT c.id)as course_count,COUNT(DISTINCT et.token_id)as token_count,COUNT(DISTINCT CASE WHEN et.is_used=1 THEN et.token_id END)as used_tokens,AVG(CAST(r.response_value AS DECIMAL(10,2)))as avg_rating FROM department d LEFT JOIN courses c ON d.t_id=c.department_id LEFT JOIN evaluation_tokens et ON c.id=et.course_id LEFT JOIN evaluations e ON et.token=e.token LEFT JOIN responses r ON e.evaluation_id=r.evaluation_id GROUP BY d.t_id ORDER BY d.dep_name";
$result=mysqli_query($conn,$query);
$departments=[];
while($row=mysqli_fetch_assoc($result))$departments[]=$row;
require_once '../../includes/header.php';
?>
<style>
.report-container{background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.dept-item{padding:25px;background:#f8f9fa;border-radius:10px;margin-bottom:20px;border-left:5px solid #667eea}
.dept-name{font-size:22px;font-weight:700;color:#333;margin-bottom:15px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px}
.stat-mini{background:white;padding:15px;border-radius:8px;text-align:center}
.stat-mini-value{font-size:24px;font-weight:bold;color:#667eea}
.stat-mini-label{font-size:12px;color:#666;margin-top:5px}
</style>
<div class="page-header">
<h1>🏢 Department Report</h1>
<p>Overview of evaluation statistics by department</p>
</div>
<div class="report-container">
<?php foreach($departments as $dept): ?>
<div class="dept-item">
<div class="dept-name"><?php echo htmlspecialchars($dept['dep_name']);?></div>
<div class="stats-grid">
<div class="stat-mini">
<div class="stat-mini-value"><?php echo $dept['course_count'];?></div>
<div class="stat-mini-label">Courses</div>
</div>
<div class="stat-mini">
<div class="stat-mini-value"><?php echo $dept['token_count'];?></div>
<div class="stat-mini-label">Total Tokens</div>
</div>
<div class="stat-mini">
<div class="stat-mini-value"><?php echo $dept['used_tokens'];?></div>
<div class="stat-mini-label">Used Tokens</div>
</div>
<div class="stat-mini">
<div class="stat-mini-value"><?php echo $dept['token_count']>0?round(($dept['used_tokens']/$dept['token_count'])*100,1):0;?>%</div>
<div class="stat-mini-label">Usage Rate</div>
</div>
<div class="stat-mini">
<div class="stat-mini-value"><?php echo $dept['avg_rating']?number_format($dept['avg_rating'],2):'N/A';?></div>
<div class="stat-mini-label">Avg Rating</div>
</div>
</div>
</div>
<?php endforeach;?>
</div>
<?php require_once '../../includes/footer.php';?>
