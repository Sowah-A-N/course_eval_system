<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_HOD){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$page_title='Course Performance Report';
$query="SELECT c.course_code,c.name,COUNT(DISTINCT et.token_id)as total_evals,
AVG(CASE WHEN et.is_used=1 THEN CAST(r.response_value AS DECIMAL(10,2))END)as avg_rating
FROM courses c
LEFT JOIN evaluation_tokens et ON c.id=et.course_id AND et.is_used=1
LEFT JOIN evaluations e ON et.token=e.token
LEFT JOIN responses r ON e.evaluation_id=r.evaluation_id
WHERE c.department_id=?
GROUP BY c.id
HAVING total_evals>=?
ORDER BY avg_rating DESC";
$stmt=mysqli_prepare($conn,$query);
$min=MIN_RESPONSE_COUNT;
mysqli_stmt_bind_param($stmt,"ii",$department_id,$min);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$courses=[];
while($row=mysqli_fetch_assoc($result)){
$row['avg_rating']=round($row['avg_rating'],2);
$courses[]=$row;
}
require_once '../../includes/header.php';
?>
<style>
.course-table{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.course-table table{width:100%;border-collapse:collapse}
.course-table th{background:#f8f9fa;padding:15px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0}
.course-table td{padding:15px;border-bottom:1px solid #f0f0f0}
.course-table tr:hover{background:#f8f9fa}
.rating-high{color:#28a745;font-weight:bold}
.rating-medium{color:#ffc107;font-weight:bold}
.rating-low{color:#dc3545;font-weight:bold}
</style>
<div class="page-header"><h1>Course Performance Report</h1><p>Individual course evaluation ratings</p></div>
<div class="course-table">
<table>
<thead><tr><th scope="col">Rank</th><th scope="col">Course Code</th><th scope="col">Course Name</th><th scope="col">Evaluations</th><th scope="col">Average Rating</th></tr></thead>
<tbody>
<?php $rank=1;foreach($courses as $course):
$class=$course['avg_rating']>=4.0?'rating-high':($course['avg_rating']>=3.0?'rating-medium':'rating-low');?>
<tr>
<td><strong>#<?php echo $rank++;?></strong></td>
<td><strong><?php echo htmlspecialchars($course['course_code']);?></strong></td>
<td><?php echo htmlspecialchars($course['name']);?></td>
<td><?php echo $course['total_evals'];?></td>
<td><span class="<?php echo $class;?>"><?php echo $course['avg_rating'];?>/5.0</span></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
<?php require_once '../../includes/footer.php';?>
