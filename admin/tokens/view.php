<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='View Evaluation Tokens';
$filter_dept=isset($_GET['department_id'])?intval($_GET['department_id']):0;
$filter_level=isset($_GET['level_id'])?intval($_GET['level_id']):0;
$filter_status=isset($_GET['status'])?$_GET['status']:'all';
$search=isset($_GET['search'])?trim($_GET['search']):'';
$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;
$levels=[];
$result_levels=mysqli_query($conn,"SELECT * FROM level ORDER BY level_number");
while($row=mysqli_fetch_assoc($result_levels))$levels[]=$row;
$where=["1=1"];
$params=[];
$types='';
if($filter_dept>0){
$where[]="c.department_id=?";
$params[]=$filter_dept;
$types.='i';
}
if($filter_level>0){
$where[]="c.level_id=?";
$params[]=$filter_level;
$types.='i';
}
if($filter_status=='used'){
$where[]="et.is_used=1";
}elseif($filter_status=='unused'){
$where[]="et.is_used=0";
}
if(!empty($search)){
$where[]="(u.f_name LIKE ? OR u.l_name LIKE ? OR c.course_code LIKE ?)";
$search_param="%$search%";
$params[]=$search_param;
$params[]=$search_param;
$params[]=$search_param;
$types.='sss';
}
$where_clause=implode(' AND ',$where);
$query="
SELECT et.token_id,et.token,et.is_used,et.created_at,et.used_at,
u.f_name,u.l_name,u.unique_id,
c.course_code,c.name as course_name,
d.dep_name,
l.level_name,
ay.year_label,
s.semester_name
FROM evaluation_tokens et
JOIN user_details u ON et.student_user_id=u.user_id
JOIN courses c ON et.course_id=c.id
LEFT JOIN department d ON c.department_id=d.t_id
LEFT JOIN level l ON c.level_id=l.t_id
LEFT JOIN academic_year ay ON et.academic_year_id=ay.academic_year_id
LEFT JOIN semesters s ON et.semester_id=s.semester_id
WHERE $where_clause
ORDER BY et.created_at DESC
LIMIT 1000
";
$stmt=mysqli_prepare($conn,$query);
if(!empty($params)){
mysqli_stmt_bind_param($stmt,$types,...$params);
}
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$tokens=[];
while($row=mysqli_fetch_assoc($result))$tokens[]=$row;
mysqli_stmt_close($stmt);
$query_stats="SELECT COUNT(*)as total,SUM(is_used)as used,SUM(CASE WHEN is_used=0 THEN 1 ELSE 0 END)as unused FROM evaluation_tokens";
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
.btn-success{background:#28a745;color:white}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px}
.stat-card{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center}
.stat-value{font-size:36px;font-weight:bold;color:#667eea}
.stat-label{font-size:14px;color:#666;margin-top:5px}
.tokens-table{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.tokens-table table{width:100%;border-collapse:collapse}
.tokens-table th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0;font-size:13px}
.tokens-table td{padding:12px;border-bottom:1px solid #f0f0f0;font-size:14px}
.tokens-table tr:hover{background:#f8f9fa}
.status-badge{padding:4px 10px;border-radius:15px;font-size:11px;font-weight:600;text-transform:uppercase}
.status-used{background:#d4edda;color:#155724}
.status-unused{background:#fff3cd;color:#856404}
.token-display{font-family:monospace;font-size:12px;color:#666}
</style>
<div class="page-header">
<h1>Evaluation Tokens</h1>
<p>View and manage generated evaluation tokens</p>
</div>
<div class="stats-grid">
<div class="stat-card">
<div class="stat-value"><?php echo $stats['total'];?></div>
<div class="stat-label">Total Tokens</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $stats['used'];?></div>
<div class="stat-label">Used</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $stats['unused'];?></div>
<div class="stat-label">Unused</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $stats['total']>0?round(($stats['used']/$stats['total'])*100,1):0;?>%</div>
<div class="stat-label">Usage Rate</div>
</div>
</div>
<div class="top-actions">
<div>
<a href="generate.php" class="btn btn-primary">+ Generate New Tokens</a>
</div>
<div style="color:#666;font-size:14px">
Showing: <strong><?php echo count($tokens);?></strong> token(s)
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
<label>Level</label>
<select name="level_id">
<option value="0">All Levels</option>
<?php foreach($levels as $level): ?>
<option value="<?php echo $level['t_id'];?>" <?php echo $filter_level==$level['t_id']?'selected':'';?>>
<?php echo htmlspecialchars($level['level_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="filter-group">
<label>Status</label>
<select name="status">
<option value="all" <?php echo $filter_status=='all'?'selected':'';?>>All</option>
<option value="used" <?php echo $filter_status=='used'?'selected':'';?>>Used</option>
<option value="unused" <?php echo $filter_status=='unused'?'selected':'';?>>Unused</option>
</select>
</div>
<div class="filter-group">
<label>Search</label>
<input type="text" name="search" placeholder="Student or course..." value="<?php echo htmlspecialchars($search);?>" maxlength="100">
</div>
</div>
<button type="submit" class="btn btn-primary">Apply Filters</button>
<a href="view.php" class="btn btn-secondary">Reset</a>
</form>
</div>
<?php if(empty($tokens)): ?>
<div style="text-align:center;padding:60px 20px;background:white;border-radius:8px">
<div style="font-size:80px;opacity:0.3">🎫</div>
<h3>No Tokens Found</h3>
<p style="color:#666">No evaluation tokens match your criteria.</p>
<a href="generate.php" class="btn btn-primary">Generate Tokens</a>
</div>
<?php else: ?>
<div class="tokens-table">
<table>
<thead>
<tr>
<th scope="col">Student</th>
<th scope="col">Course</th>
<th scope="col">Department</th>
<th scope="col">Level</th>
<th scope="col">Period</th>
<th scope="col">Status</th>
<th scope="col">Created</th>
<th scope="col">Used</th>
</tr>
</thead>
<tbody>
<?php foreach($tokens as $token): ?>
<tr>
<td>
<strong><?php echo htmlspecialchars($token['f_name'].' '.$token['l_name']);?></strong><br>
<small style="color:#999"><?php echo htmlspecialchars($token['unique_id']);?></small>
</td>
<td>
<strong><?php echo htmlspecialchars($token['course_code']);?></strong><br>
<small style="color:#999"><?php echo htmlspecialchars($token['course_name']);?></small>
</td>
<td><?php echo htmlspecialchars($token['dep_name']);?></td>
<td><?php echo htmlspecialchars($token['level_name']);?></td>
<td>
<?php echo htmlspecialchars($token['year_label']);?><br>
<small style="color:#999"><?php echo htmlspecialchars($token['semester_name']);?></small>
</td>
<td>
<?php if($token['is_used']): ?>
<span class="status-badge status-used">Used</span>
<?php else: ?>
<span class="status-badge status-unused">Unused</span>
<?php endif;?>
</td>
<td><?php echo date('M d, Y',strtotime($token['created_at']));?></td>
<td><?php echo $token['used_at']?date('M d, Y',strtotime($token['used_at'])):'—';?></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php';?>
