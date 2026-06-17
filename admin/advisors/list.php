<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Manage Advisor Assignments';
$filter_dept=isset($_GET['department_id'])?intval($_GET['department_id']):0;
$search=isset($_GET['search'])?trim($_GET['search']):'';
$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;
$where=["1=1"];
$params=[];
$types='';
if($filter_dept>0){
$where[]="c.department_id=?";
$params[]=$filter_dept;
$types.='i';
}
if(!empty($search)){
$where[]="(u.f_name LIKE ? OR u.l_name LIKE ? OR c.class_name LIKE ?)";
$search_param="%$search%";
$params[]=$search_param;
$params[]=$search_param;
$params[]=$search_param;
$types.='sss';
}
$where_clause=implode(' AND ',$where);
$query="SELECT c.t_id as class_id,c.class_name,c.class_code,d.dep_name,u.user_id as advisor_id,u.f_name,u.l_name,u.email,COUNT(DISTINCT s.user_id)as student_count FROM classes c LEFT JOIN department d ON c.department_id=d.t_id LEFT JOIN user_details u ON c.advisor_user_id=u.user_id LEFT JOIN user_details s ON c.t_id=s.class_id AND s.role_id=? WHERE $where_clause GROUP BY c.t_id ORDER BY d.dep_name,c.class_name";
$stmt=mysqli_prepare($conn,$query);
$role_student=ROLE_STUDENT;
$all_params=array_merge([$role_student],$params);
$all_types='i'.$types;
mysqli_stmt_bind_param($stmt,$all_types,...$all_params);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$assignments=[];
while($row=mysqli_fetch_assoc($result))$assignments[]=$row;
mysqli_stmt_close($stmt);
$query_stats="SELECT COUNT(DISTINCT c.t_id)as total_classes,COUNT(DISTINCT CASE WHEN c.advisor_user_id IS NOT NULL THEN c.t_id END)as assigned_classes,COUNT(DISTINCT CASE WHEN c.advisor_user_id IS NULL THEN c.t_id END)as unassigned_classes FROM classes c";
$result_stats=mysqli_query($conn,$query_stats);
$stats=mysqli_fetch_assoc($result_stats);
require_once '../../includes/header.php';
?>
<style>
.top-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px}
.filters-section{background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:15px}
.filter-group label{font-size:14px;font-weight:500;margin-bottom:5px;display:block}
.filter-group select,.filter-group input{width:100%;padding:10px;border:1px solid #ddd;border-radius:5px}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.3s}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.btn-warning{background:#ffc107;color:#333}
.btn-sm{padding:6px 12px;font-size:12px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px}
.stat-card{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center}
.stat-value{font-size:36px;font-weight:bold;color:#667eea}
.stat-label{font-size:14px;color:#666;margin-top:5px}
.assignments-table{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.assignments-table table{width:100%;border-collapse:collapse}
.assignments-table th{background:#f8f9fa;padding:15px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0;font-size:13px}
.assignments-table td{padding:15px;border-bottom:1px solid #f0f0f0;font-size:14px}
.assignments-table tr:hover{background:#f8f9fa}
.status-badge{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;text-transform:uppercase}
.status-assigned{background:#d4edda;color:#155724}
.status-unassigned{background:#f8d7da;color:#721c24}
</style>
<div class="page-header">
<h1>Manage Advisor Assignments</h1>
<p>Assign advisors to classes for student monitoring</p>
</div>
<div class="stats-grid">
<div class="stat-card">
<div class="stat-value"><?php echo $stats['total_classes'];?></div>
<div class="stat-label">Total Classes</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $stats['assigned_classes'];?></div>
<div class="stat-label">Assigned</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $stats['unassigned_classes'];?></div>
<div class="stat-label">Unassigned</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $stats['total_classes']>0?round(($stats['assigned_classes']/$stats['total_classes'])*100,1):0;?>%</div>
<div class="stat-label">Coverage</div>
</div>
</div>
<div class="top-actions">
<div>
<a href="assign.php" class="btn btn-primary">+ Assign Advisor</a>
</div>
<div style="color:#666;font-size:14px">
Total: <strong><?php echo count($assignments);?></strong> class(es)
</div>
</div>
<div class="filters-section">
<form method="GET">
<div class="filters-grid">
<div class="filter-group">
<label>Department</label>
<select name="department_id">
<option value="0">All Departments</option>
<?php foreach($departments as $dept): ?>
<option value="<?php echo $dept['t_id'];?>" <?php echo $filter_dept==$dept['t_id']?'selected':'';?>>
<?php echo htmlspecialchars($dept['dep_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="filter-group">
<label>Search</label>
<input type="text" name="search" placeholder="Advisor or class name..." value="<?php echo htmlspecialchars($search);? maxlength="100">">
</div>
</div>
<button type="submit" class="btn btn-primary">Apply Filters</button>
<a href="list.php" class="btn btn-secondary">Reset</a>
</form>
</div>
<?php if(empty($assignments)): ?>
<div style="text-align:center;padding:60px 20px;background:white;border-radius:8px">
<div style="font-size:80px;opacity:0.3">👨‍🏫</div>
<h3>No Classes Found</h3>
<p style="color:#666">No classes match your criteria.</p>
</div>
<?php else: ?>
<div class="assignments-table">
<table>
<thead>
<tr>
<th scope="col">Class</th>
<th scope="col">Department</th>
<th scope="col">Assigned Advisor</th>
<th scope="col">Students</th>
<th scope="col">Status</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($assignments as $assignment): ?>
<tr>
<td>
<strong><?php echo htmlspecialchars($assignment['class_name']);?></strong><br>
<small style="color:#999"><?php echo htmlspecialchars($assignment['class_code']);?></small>
</td>
<td><?php echo htmlspecialchars($assignment['dep_name']);?></td>
<td>
<?php if($assignment['advisor_id']): ?>
<strong><?php echo htmlspecialchars($assignment['f_name'].' '.$assignment['l_name']);?></strong><br>
<small style="color:#999"><?php echo htmlspecialchars($assignment['email']);?></small>
<?php else: ?>
<span style="color:#999">— Not Assigned —</span>
<?php endif;?>
</td>
<td><?php echo $assignment['student_count'];?> student(s)</td>
<td>
<?php if($assignment['advisor_id']): ?>
<span class="status-badge status-assigned">Assigned</span>
<?php else: ?>
<span class="status-badge status-unassigned">Unassigned</span>
<?php endif;?>
</td>
<td>
<?php if($assignment['advisor_id']): ?>
<a href="unassign.php?class_id=<?php echo $assignment['class_id'];?>" class="btn btn-warning btn-sm" onclick="return confirm('Remove advisor assignment?')">Unassign</a>
<?php else: ?>
<a href="assign.php?class_id=<?php echo $assignment['class_id'];?>" class="btn btn-primary btn-sm">Assign</a>
<?php endif;?>
</td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php';?>
