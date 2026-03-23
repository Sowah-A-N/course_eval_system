<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){header("Location:../../login.php");exit();}
$page_title='Course Evaluation Report';
$filter_dept=isset($_GET['department_id'])?intval($_GET['department_id']):0;
$filter_course=isset($_GET['course_id'])?intval($_GET['course_id']):0;
$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;
$courses=[];
if($filter_dept>0){
$query_courses="SELECT id,course_code,name FROM courses WHERE department_id=? ORDER BY course_code";
$stmt_courses=mysqli_prepare($conn,$query_courses);
mysqli_stmt_bind_param($stmt_courses,"i",$filter_dept);
mysqli_stmt_execute($stmt_courses);
$result_courses=mysqli_stmt_get_result($stmt_courses);
while($row=mysqli_fetch_assoc($result_courses))$courses[]=$row;
mysqli_stmt_close($stmt_courses);
}
$report_data=null;
if($filter_course>0){
$min_responses=MIN_RESPONSE_COUNT;
$query="SELECT c.course_code,c.name as course_name,d.dep_name,COUNT(DISTINCT e.evaluation_id)as response_count,COUNT(DISTINCT et.token_id)as total_tokens FROM courses c LEFT JOIN department d ON c.department_id=d.t_id LEFT JOIN evaluation_tokens et ON c.id=et.course_id LEFT JOIN evaluations e ON et.token=e.token WHERE c.id=? GROUP BY c.id";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$filter_course);
mysqli_stmt_execute($stmt);
$report_data=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if($report_data&&$report_data['response_count']>=$min_responses){
$query_ratings="SELECT eq.question_text,AVG(CAST(r.response_value AS DECIMAL(10,2)))as avg_rating,COUNT(r.id)as rating_count FROM responses r JOIN evaluation_questions eq ON r.question_id=eq.question_id JOIN evaluations e ON r.evaluation_id=e.evaluation_id JOIN evaluation_tokens et ON e.token=et.token WHERE et.course_id=? GROUP BY r.question_id,eq.question_text ORDER BY eq.display_order";
$stmt_ratings=mysqli_prepare($conn,$query_ratings);
mysqli_stmt_bind_param($stmt_ratings,"i",$filter_course);
mysqli_stmt_execute($stmt_ratings);
$result_ratings=mysqli_stmt_get_result($stmt_ratings);
$ratings=[];
while($row=mysqli_fetch_assoc($result_ratings))$ratings[]=$row;
mysqli_stmt_close($stmt_ratings);
$report_data['ratings']=$ratings;
}
}
require_once '../../includes/header.php';
?>
<style>
.filter-section{background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:15px}
.filter-group label{font-size:14px;font-weight:500;margin-bottom:5px;display:block}
.filter-group select{width:100%;padding:10px;border:1px solid #ddd;border-radius:5px}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.report-container{background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.report-header{border-bottom:2px solid #667eea;padding-bottom:15px;margin-bottom:20px}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin:20px 0}
.stat-box{background:#f8f9fa;padding:15px;border-radius:8px;text-align:center}
.stat-box-value{font-size:28px;font-weight:bold;color:#667eea}
.stat-box-label{font-size:13px;color:#666;margin-top:5px}
.question-item{padding:20px;background:#f8f9fa;border-radius:8px;margin-bottom:15px}
.question-text{font-size:15px;font-weight:600;margin-bottom:10px}
.rating-bar{background:#e9ecef;height:30px;border-radius:15px;overflow:hidden;position:relative}
.rating-fill{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);height:100%;display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:14px}
.warning-box{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:15px;border-radius:8px;margin:20px 0}
</style>
<div class="page-header">
<h1>📚 Course Evaluation Report</h1>
<p>View detailed evaluation results for specific courses</p>
</div>
<div class="filter-section">
<form method="GET">
<div class="filter-grid">
<div class="filter-group">
<label>Department</label>
<select name="department_id" onchange="this.form.submit()">
<option value="0">-- Select Department --</option>
<?php foreach($departments as $dept): ?>
<option value="<?php echo $dept['t_id'];?>" <?php echo $filter_dept==$dept['t_id']?'selected':'';?>><?php echo htmlspecialchars($dept['dep_name']);?></option>
<?php endforeach;?>
</select>
</div>
<?php if($filter_dept>0): ?>
<div class="filter-group">
<label>Course</label>
<select name="course_id" onchange="this.form.submit()">
<option value="0">-- Select Course --</option>
<?php foreach($courses as $course): ?>
<option value="<?php echo $course['id'];?>" <?php echo $filter_course==$course['id']?'selected':'';?>><?php echo htmlspecialchars($course['course_code'].' - '.$course['name']);?></option>
<?php endforeach;?>
</select>
</div>
<?php endif;?>
</div>
</form>
</div>
<?php if($report_data): ?>
<?php if($report_data['response_count']<MIN_RESPONSE_COUNT): ?>
<div class="warning-box">
<strong>⚠️ Insufficient Responses</strong><br>
This course has only <?php echo $report_data['response_count'];?> response(s). Minimum <?php echo MIN_RESPONSE_COUNT;?> responses required to display results (anonymity protection).
</div>
<?php else: ?>
<div class="report-container">
<div class="report-header">
<h2><?php echo htmlspecialchars($report_data['course_code'].' - '.$report_data['course_name']);?></h2>
<p style="color:#666;margin:5px 0 0 0"><?php echo htmlspecialchars($report_data['dep_name']);?></p>
</div>
<div class="stats-row">
<div class="stat-box">
<div class="stat-box-value"><?php echo $report_data['response_count'];?></div>
<div class="stat-box-label">Responses</div>
</div>
<div class="stat-box">
<div class="stat-box-value"><?php echo $report_data['total_tokens'];?></div>
<div class="stat-box-label">Total Students</div>
</div>
<div class="stat-box">
<div class="stat-box-value"><?php echo $report_data['total_tokens']>0?round(($report_data['response_count']/$report_data['total_tokens'])*100,1):0;?>%</div>
<div class="stat-box-label">Response Rate</div>
</div>
</div>
<h3 style="margin:30px 0 15px 0">Question Ratings</h3>
<?php foreach($report_data['ratings']as $rating): ?>
<div class="question-item">
<div class="question-text"><?php echo htmlspecialchars($rating['question_text']);?></div>
<div class="rating-bar">
<div class="rating-fill" style="width:<?php echo ($rating['avg_rating']/5)*100;?>%">
<?php echo number_format($rating['avg_rating'],2);?> / 5.00
</div>
</div>
<div style="font-size:12px;color:#999;margin-top:5px"><?php echo $rating['rating_count'];?> response(s)</div>
</div>
<?php endforeach;?>
</div>
<?php endif;?>
<?php elseif($filter_course>0): ?>
<div style="text-align:center;padding:60px 20px;background:white;border-radius:8px">
<div style="font-size:80px;opacity:0.3">📊</div>
<h3>No Data Available</h3>
<p style="color:#666">No evaluation data found for this course.</p>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php';?>
