<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_HOD){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$page_title='Completion Report';
$query="SELECT c.course_code,c.name,l.level_name,
COUNT(DISTINCT et.token_id)as total_tokens,
COUNT(DISTINCT CASE WHEN et.is_used=1 THEN et.token_id END)as completed
FROM courses c
LEFT JOIN level l ON c.level_id=l.t_id
LEFT JOIN evaluation_tokens et ON c.id=et.course_id
WHERE c.department_id=?
GROUP BY c.id
ORDER BY c.course_code";
$stmt=mysqli_prepare($conn,$query);
mysqli_stmt_bind_param($stmt,"i",$department_id);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$courses=[];
while($row=mysqli_fetch_assoc($result)){
$row['completion_rate']=$row['total_tokens']>0?round(($row['completed']/$row['total_tokens'])*100,1):0;
$courses[]=$row;
}
require_once '../../includes/header.php';
?>
<style>
.completion-table{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.completion-table table{width:100%;border-collapse:collapse}
.completion-table th{background:#f8f9fa;padding:15px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0}
.completion-table td{padding:15px;border-bottom:1px solid #f0f0f0}
.completion-table tr:hover{background:#f8f9fa}
.progress-mini{width:100px;height:6px;background:#e0e0e0;border-radius:3px;overflow:hidden;display:inline-block;vertical-align:middle;margin-left:5px}
.progress-fill-mini{height:100%;background:linear-gradient(90deg,#667eea,#764ba2)}
</style>
<div class="page-header"><h1>Completion Report</h1><p>Evaluation completion status by course</p></div>
<div class="completion-table">
<table>
<thead><tr><th scope="col">Course Code</th><th scope="col">Course Name</th><th scope="col">Level</th><th scope="col">Completed</th><th scope="col">Total</th><th scope="col">Completion Rate</th></tr></thead>
<tbody>
<?php foreach($courses as $course): ?>
<tr>
<td><strong><?php echo htmlspecialchars($course['course_code']);?></strong></td>
<td><?php echo htmlspecialchars($course['name']);?></td>
<td><?php echo htmlspecialchars($course['level_name']);?></td>
<td><?php echo $course['completed'];?></td>
<td><?php echo $course['total_tokens'];?></td>
<td>
<?php echo $course['completion_rate'];?>%
<div class="progress-mini">
<div class="progress-fill-mini" style="width:<?php echo $course['completion_rate'];?>%"></div>
</div>
</td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
<?php require_once '../../includes/footer.php';?>
