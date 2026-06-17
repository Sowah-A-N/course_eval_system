<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Lecturer Performance Report';
$lecturers=[];
$query_lecturers="SELECT DISTINCT u.user_id,u.f_name,u.l_name,d.dep_name FROM user_details u LEFT JOIN department d ON u.department_id=d.t_id WHERE u.role_id=? ORDER BY u.f_name,u.l_name";
$stmt_lecturers=mysqli_prepare($conn,$query_lecturers);
$role_lecturer=ROLE_LECTURER;
mysqli_stmt_bind_param($stmt_lecturers,"i",$role_lecturer);
mysqli_stmt_execute($stmt_lecturers);
$result_lecturers=mysqli_stmt_get_result($stmt_lecturers);
while($row=mysqli_fetch_assoc($result_lecturers))$lecturers[]=$row;
mysqli_stmt_close($stmt_lecturers);
$filter_lecturer=isset($_GET['lecturer_id'])?intval($_GET['lecturer_id']):0;
$report_data=null;
if($filter_lecturer>0){
$min_responses=MIN_RESPONSE_COUNT;
$query="SELECT u.f_name,u.l_name,u.email,d.dep_name FROM user_details u LEFT JOIN department d ON u.department_id=d.t_id WHERE u.user_id=?";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$filter_lecturer);
mysqli_stmt_execute($stmt);
$report_data=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if($report_data){
// JOIN replaces the correlated IN(SELECT …) subquery.
// The correlated subquery was re-executed for every row in evaluation_tokens;
// the JOIN lets the optimiser pick the cheapest join order and use the index
// on course_lecturers(lecturer_user_id, course_id, is_active) directly.
$query_courses="SELECT c.course_code,c.name as course_name,COUNT(DISTINCT e.evaluation_id)as response_count
    FROM evaluation_tokens et
    JOIN course_lecturers cl ON et.course_id=cl.course_id
                             AND cl.lecturer_user_id=?
                             AND cl.is_active=1
    JOIN courses c ON et.course_id=c.id
    LEFT JOIN evaluations e ON et.token=e.token
    GROUP BY c.id
    HAVING response_count>=?
    ORDER BY c.course_code";
$stmt_courses=mysqli_prepare($conn,$query_courses);
mysqli_stmt_bind_param($stmt_courses,"ii",$filter_lecturer,$min_responses);
mysqli_stmt_execute($stmt_courses);
$result_courses=mysqli_stmt_get_result($stmt_courses);
$courses_data=[];
while($row=mysqli_fetch_assoc($result_courses))$courses_data[]=$row;
mysqli_stmt_close($stmt_courses);
$report_data['courses']=$courses_data;
$query_overall="SELECT AVG(CAST(r.response_value AS DECIMAL(10,2)))as overall_avg,COUNT(DISTINCT e.evaluation_id)as total_evaluations
    FROM responses r
    JOIN evaluations e ON r.evaluation_id=e.evaluation_id
    JOIN evaluation_tokens et ON e.token=et.token
    JOIN course_lecturers cl ON et.course_id=cl.course_id
                             AND cl.lecturer_user_id=?
                             AND cl.is_active=1";
$stmt_overall=mysqli_prepare($conn,$query_overall);
mysqli_stmt_bind_param($stmt_overall,"i",$filter_lecturer);
mysqli_stmt_execute($stmt_overall);
$overall=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_overall));
mysqli_stmt_close($stmt_overall);
$report_data['overall_avg']=$overall['overall_avg'];
$report_data['total_evaluations']=$overall['total_evaluations'];
}
}
require_once '../../includes/header.php';
?>
<style>
.filter-section{background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.report-container{background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.lecturer-info{background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0}
.stat-box{background:#f8f9fa;padding:20px;border-radius:8px;text-align:center;border-left:4px solid #667eea}
.stat-box-value{font-size:32px;font-weight:bold;color:#667eea}
.stat-box-label{font-size:13px;color:#666;margin-top:5px}
.course-item{padding:15px;background:#f8f9fa;border-radius:8px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
.info-box{background:#d1ecf1;padding:15px;border-radius:8px;margin:20px 0}
</style>
<div class="page-header">
<h1>👨‍🏫 Lecturer Performance Report</h1>
<p>View evaluation results for individual lecturers</p>
</div>
<div class="filter-section">
<form method="GET">
<label style="font-size:14px;font-weight:500;margin-bottom:10px;display:block">Select Lecturer</label>
<select name="lecturer_id" style="width:100%;max-width:400px;padding:10px;border:1px solid #ddd;border-radius:5px" onchange="this.form.submit()">
<option value="0">-- Select Lecturer --</option>
<?php foreach($lecturers as $lect): ?>
<option value="<?php echo $lect['user_id'];?>" <?php echo $filter_lecturer==$lect['user_id']?'selected':'';?>><?php echo htmlspecialchars($lect['f_name'].' '.$lect['l_name'].' ('.$lect['dep_name'].')');?></option>
<?php endforeach;?>
</select>
</form>
</div>
<?php if($report_data): ?>
<div class="report-container">
<div class="lecturer-info">
<h2 style="margin:0 0 10px 0"><?php echo htmlspecialchars($report_data['f_name'].' '.$report_data['l_name']);?></h2>
<p style="margin:5px 0;color:#666"><strong>Department:</strong> <?php echo htmlspecialchars($report_data['dep_name']);?></p>
<p style="margin:5px 0;color:#666"><strong>Email:</strong> <?php echo htmlspecialchars($report_data['email']);?></p>
</div>
<div class="stats-row">
<div class="stat-box">
<div class="stat-box-value"><?php echo $report_data['overall_avg']?number_format($report_data['overall_avg'],2):'N/A';?></div>
<div class="stat-box-label">Overall Average Rating</div>
</div>
<div class="stat-box">
<div class="stat-box-value"><?php echo $report_data['total_evaluations'];?></div>
<div class="stat-box-label">Total Evaluations</div>
</div>
<div class="stat-box">
<div class="stat-box-value"><?php echo count($report_data['courses']);?></div>
<div class="stat-box-label">Courses Taught</div>
</div>
</div>
<?php if(!empty($report_data['courses'])): ?>
<h3 style="margin:30px 0 15px 0">Courses Taught (with sufficient responses)</h3>
<?php foreach($report_data['courses']as $course): ?>
<div class="course-item">
<div>
<strong><?php echo htmlspecialchars($course['course_code']);?></strong> - <?php echo htmlspecialchars($course['course_name']);?>
</div>
<div style="color:#667eea;font-weight:600"><?php echo $course['response_count'];?> evaluations</div>
</div>
<?php endforeach;?>
<?php else: ?>
<div class="info-box">No courses with sufficient evaluation responses (minimum <?php echo MIN_RESPONSE_COUNT;?> required).</div>
<?php endif;?>
</div>
<?php elseif($filter_lecturer>0): ?>
<div style="text-align:center;padding:60px 20px;background:white;border-radius:8px">
<h3>No Data Available</h3>
<p style="color:#666">No data found for this lecturer.</p>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php';?>
