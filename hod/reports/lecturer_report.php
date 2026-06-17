<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_HOD){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$page_title='Lecturer Performance Report';
$query="SELECT u.user_id,u.f_name,u.l_name,COUNT(DISTINCT et.token_id)as total_evals,
AVG(CASE WHEN et.is_used=1 THEN CAST(r.response_value AS DECIMAL(10,2))END)as avg_rating
FROM user_details u
LEFT JOIN course_lecturers cl ON u.user_id=cl.lecturer_user_id
LEFT JOIN evaluation_tokens et ON cl.course_id=et.course_id AND et.is_used=1
LEFT JOIN evaluations e ON et.token=e.token
LEFT JOIN responses r ON e.evaluation_id=r.evaluation_id
WHERE u.department_id=? AND u.role_id=?
GROUP BY u.user_id
ORDER BY avg_rating DESC";
$stmt=mysqli_prepare($conn,$query);
$role=ROLE_LECTURER;
mysqli_stmt_bind_param($stmt,"ii",$department_id,$role);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$lecturers=[];
while($row=mysqli_fetch_assoc($result)){
$row['avg_rating']=$row['total_evals']>=MIN_RESPONSE_COUNT&&$row['avg_rating']?round($row['avg_rating'],2):null;
$lecturers[]=$row;
}
require_once '../../includes/header.php';
?>
<style>
.lecturer-table{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.lecturer-table table{width:100%;border-collapse:collapse}
.lecturer-table th{background:#f8f9fa;padding:15px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0}
.lecturer-table td{padding:15px;border-bottom:1px solid #f0f0f0}
.lecturer-table tr:hover{background:#f8f9fa}
.rating-high{color:#28a745;font-weight:bold}
.rating-medium{color:#ffc107;font-weight:bold}
.rating-low{color:#dc3545;font-weight:bold}
</style>
<div class="page-header"><h1>Lecturer Performance Report</h1><p>Individual lecturer evaluation ratings</p></div>
<div class="lecturer-table">
<table>
<thead><tr><th scope="col">Lecturer Name</th><th scope="col">Evaluations</th><th scope="col">Average Rating</th></tr></thead>
<tbody>
<?php foreach($lecturers as $lec): ?>
<tr>
<td><strong><?php echo htmlspecialchars($lec['f_name'].' '.$lec['l_name']);?></strong></td>
<td><?php echo $lec['total_evals'];?></td>
<td><?php if($lec['avg_rating']):
$class=$lec['avg_rating']>=4.0?'rating-high':($lec['avg_rating']>=3.0?'rating-medium':'rating-low');?>
<span class="<?php echo $class;?>"><?php echo $lec['avg_rating'];?>/5.0</span>
<?php else: ?>
<span style="color:#999;font-style:italic"><?php echo $lec['total_evals']>0?'Insufficient data':'No data';?></span>
<?php endif;?></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
<?php require_once '../../includes/footer.php';?>
