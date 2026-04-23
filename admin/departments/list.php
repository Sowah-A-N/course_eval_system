<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Manage Departments';
$search=isset($_GET['search'])?trim($_GET['search']):'';
$where=["1=1"];
$params=[];
$types='';
if(!empty($search)){
$where[]="(d.dep_name LIKE ? OR d.dep_code LIKE ?)";
$search_param="%$search%";
$params[]=$search_param;
$params[]=$search_param;
$types.='ss';
}
$where_clause=implode(' AND ',$where);
$query="
SELECT
d.t_id,
d.dep_name,
d.dep_code,
COUNT(DISTINCT u.user_id) as total_users,
COUNT(DISTINCT CASE WHEN u.role_id=? THEN u.user_id END) as student_count,
COUNT(DISTINCT CASE WHEN u.role_id=? THEN u.user_id END) as lecturer_count,
COUNT(DISTINCT c.id) as course_count,
COUNT(DISTINCT cl.t_id) as class_count
FROM department d
LEFT JOIN user_details u ON d.t_id=u.department_id
LEFT JOIN courses c ON d.t_id=c.department_id
LEFT JOIN classes cl ON d.t_id=cl.department_id
WHERE $where_clause
GROUP BY d.t_id
ORDER BY d.dep_name
";
$stmt=mysqli_prepare($conn,$query);
$role_student=ROLE_STUDENT;
$role_lecturer=ROLE_ADVISOR;
$all_params=array_merge([$role_student,$role_lecturer],$params);
$all_types='ii'.$types;
if(!empty($all_params)){
mysqli_stmt_bind_param($stmt,$all_types,...$all_params);
}
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$departments=[];
while($row=mysqli_fetch_assoc($result))$departments[]=$row;
mysqli_stmt_close($stmt);
require_once '../../includes/header.php';
?>
<style>
.top-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px}
.filters-section{background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.3s}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.btn-success{background:#28a745;color:white}
.btn-danger{background:#dc3545;color:white}
.btn-sm{padding:6px 12px;font-size:12px}
.departments-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px}
.dept-card{background:white;border-radius:10px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,0.1);transition:all 0.3s;border-left:5px solid #667eea}
.dept-card:hover{transform:translateY(-5px);box-shadow:0 6px 20px rgba(0,0,0,0.15)}
.dept-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:15px}
.dept-name{font-size:20px;font-weight:700;color:#333}
.dept-code{font-size:14px;color:#667eea;font-weight:600;background:#f0f3ff;padding:4px 12px;border-radius:15px;margin-top:5px;display:inline-block}
.dept-stats{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:15px 0}
.stat-item{background:#f8f9fa;padding:12px;border-radius:6px;text-align:center}
.stat-value{font-size:24px;font-weight:bold;color:#667eea}
.stat-label{font-size:12px;color:#666;margin-top:3px}
.dept-actions{display:flex;gap:8px;margin-top:15px}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:8px}
</style>
<div class="page-header">
<h1>Manage Departments</h1>
<p>Configure academic departments and their resources</p>
</div>
<div class="top-actions">
<div>
<a href="create.php" class="btn btn-primary">+ Add New Department</a>
</div>
<div style="color:#666;font-size:14px">
Total: <strong><?php echo count($departments);?></strong> department(s)
</div>
</div>
<div class="filters-section">
<form method="GET" style="display:flex;gap:10px;align-items:center">
<input type="text" name="search" placeholder="Search by name or code..." value="<?php echo htmlspecialchars($search);? maxlength="100">" style="flex:1;padding:10px;border:1px solid #ddd;border-radius:5px">
<button type="submit" class="btn btn-primary">Search</button>
<a href="list.php" class="btn btn-secondary">Reset</a>
<button type="button" onclick="exportData()" class="btn btn-success">Export CSV</button>
</form>
</div>
<?php if(empty($departments)): ?>
<div class="empty-state">
<div style="font-size:80px;opacity:0.3">🏢</div>
<h3>No Departments Found</h3>
<p style="color:#666">No departments match your search criteria.</p>
<a href="create.php" class="btn btn-primary">Add First Department</a>
</div>
<?php else: ?>
<div class="departments-grid">
<?php foreach($departments as $dept): ?>
<div class="dept-card">
<div class="dept-header">
<div>
<div class="dept-name"><?php echo htmlspecialchars($dept['dep_name']);?></div>
<span class="dept-code"><?php echo htmlspecialchars($dept['dep_code']);?></span>
</div>
</div>
<div class="dept-stats">
<div class="stat-item">
<div class="stat-value"><?php echo $dept['student_count'];?></div>
<div class="stat-label">Students</div>
</div>
<div class="stat-item">
<div class="stat-value"><?php echo $dept['lecturer_count'];?></div>
<div class="stat-label">Lecturers</div>
</div>
<div class="stat-item">
<div class="stat-value"><?php echo $dept['course_count'];?></div>
<div class="stat-label">Courses</div>
</div>
<div class="stat-item">
<div class="stat-value"><?php echo $dept['class_count'];?></div>
<div class="stat-label">Classes</div>
</div>
</div>
<div style="padding-top:15px;border-top:1px solid #f0f0f0;margin-top:15px">
<div style="font-size:13px;color:#666;margin-bottom:10px">
<strong>Total Users:</strong> <?php echo $dept['total_users'];?>
</div>
</div>
<div class="dept-actions">
<a href="edit.php?id=<?php echo $dept['t_id'];?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center">Edit</a>
<form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this department?')">
    <input type="hidden" name="id" value="<?php echo $dept['t_id'];?>">
    <?php csrf_token_input(); ?>
    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
</form>
</div>
</div>
<?php endforeach;?>
</div>
<?php endif;?>
<script>
function exportData(){
let csv='Department Name,Department Code,Total Users,Students,Lecturers,Courses,Classes\n';
<?php foreach($departments as $dept): ?>
csv+='"<?php echo addslashes($dept['dep_name']);?>","<?php echo addslashes($dept['dep_code']);?>",<?php echo $dept['total_users'];?>,<?php echo $dept['student_count'];?>,<?php echo $dept['lecturer_count'];?>,<?php echo $dept['course_count'];?>,<?php echo $dept['class_count'];?>\n';
<?php endforeach;?>
const blob=new Blob([csv],{type:'text/csv'});
const url=URL.createObjectURL(blob);
const a=document.createElement('a');
a.href=url;
a.download='departments.csv';
a.click();
}
</script>
<?php require_once '../../includes/footer.php';?>
