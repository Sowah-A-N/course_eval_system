<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Evaluation Reports';
$query_active="SELECT * FROM view_active_period LIMIT 1";
$result_active=mysqli_query($conn,$query_active);
$active_period=mysqli_fetch_assoc($result_active);
$query_stats="SELECT COUNT(DISTINCT student_user_id)as total_students,COUNT(DISTINCT course_id)as total_courses,COUNT(*)as total_tokens,SUM(is_used)as used_tokens FROM evaluation_tokens";
$result_stats=mysqli_query($conn,$query_stats);
$stats=mysqli_fetch_assoc($result_stats);
$query_evals="SELECT COUNT(*)as total_evaluations FROM evaluations";
$result_evals=mysqli_query($conn,$query_evals);
$eval_stats=mysqli_fetch_assoc($result_evals);
require_once '../../includes/header.php';
?>
<style>
.reports-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-bottom:30px}
.report-card{background:white;border-radius:10px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,0.1);transition:all 0.3s;border-left:5px solid #667eea;text-decoration:none;display:block;color:inherit}
.report-card:hover{transform:translateY(-5px);box-shadow:0 6px 20px rgba(0,0,0,0.15);text-decoration:none}
.report-icon{font-size:48px;margin-bottom:15px}
.report-title{font-size:20px;font-weight:700;color:#333;margin-bottom:8px}
.report-desc{font-size:14px;color:#666;line-height:1.6}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:30px}
.stat-card{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center}
.stat-value{font-size:36px;font-weight:bold;color:#667eea}
.stat-label{font-size:14px;color:#666;margin-top:5px}
.active-period-box{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:25px;border-radius:10px;margin-bottom:20px}
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>📊 Evaluation Reports</h1>
<p>Generate and view comprehensive evaluation reports</p>
</div>
<?php if($active_period): ?>
<div class="active-period-box">
<h3 style="margin:0 0 5px 0">Current Active Period</h3>
<div style="font-size:20px;font-weight:600"><?php echo htmlspecialchars($active_period['academic_year'].' - '.$active_period['semester_name']);?></div>
</div>
<?php endif;?>
<div class="stats-grid">
<div class="stat-card">
<div class="stat-value"><?php echo number_format($eval_stats['total_evaluations']);?></div>
<div class="stat-label">Total Evaluations</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo number_format($stats['used_tokens']);?></div>
<div class="stat-label">Tokens Used</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo number_format($stats['total_students']);?></div>
<div class="stat-label">Students</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo number_format($stats['total_courses']);?></div>
<div class="stat-label">Courses</div>
</div>
</div>
<div class="info-box">
<strong>ℹ️ About Reports:</strong> Select a report type below to generate detailed analytics. Reports show aggregated data to protect individual student privacy.
</div>
<h2 style="margin:30px 0 20px 0">Available Reports</h2>
<div class="reports-grid">
<a href="by_course.php" class="report-card">
<div class="report-icon">📚</div>
<div class="report-title">Course Evaluation Report</div>
<div class="report-desc">View evaluation results for specific courses, including average ratings and response rates.</div>
</a>
<a href="by_lecturer.php" class="report-card">
<div class="report-icon">👨‍🏫</div>
<div class="report-title">Lecturer Performance Report</div>
<div class="report-desc">Analyze lecturer ratings across all courses they teach, with detailed breakdowns.</div>
</a>
<a href="by_department.php" class="report-card">
<div class="report-icon">🏢</div>
<div class="report-title">Department Report</div>
<div class="report-desc">Department-wide evaluation statistics and trends across all courses.</div>
</a>
<a href="completion_status.php" class="report-card">
<div class="report-icon">✅</div>
<div class="report-title">Completion Status</div>
<div class="report-desc">Track which students have completed evaluations and overall completion rates.</div>
</a>
<a href="response_summary.php" class="report-card">
<div class="report-icon">📊</div>
<div class="report-title">Response Summary</div>
<div class="report-desc">Overall response statistics and participation rates by level and department.</div>
</a>
<a href="export_data.php" class="report-card">
<div class="report-icon">📥</div>
<div class="report-title">Export Data</div>
<div class="report-desc">Export evaluation data in CSV format for external analysis and record-keeping.</div>
</a>
</div>
<?php require_once '../../includes/footer.php';?>
