<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Manage Courses';
$filter_dept=isset($_GET['department_id'])?intval($_GET['department_id']):0;
$filter_level=isset($_GET['level_id'])?intval($_GET['level_id']):0;
$filter_semester=isset($_GET['semester_id'])?intval($_GET['semester_id']):0;
$search=isset($_GET['search'])?trim($_GET['search']):'';
$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;
$levels=[];
$result_levels=mysqli_query($conn,"SELECT * FROM level ORDER BY level_number");
while($row=mysqli_fetch_assoc($result_levels))$levels[]=$row;
$semesters=[];
$result_sems=mysqli_query($conn,"SELECT * FROM semesters ORDER BY semester_value");
while($row=mysqli_fetch_assoc($result_sems))$semesters[]=$row;
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
if($filter_semester>0){
$where[]="c.semester_id=?";
$params[]=$filter_semester;
$types.='i';
}
if(!empty($search)){
$where[]="(c.course_code LIKE ? OR c.name LIKE ?)";
$search_param="%$search%";
$params[]=$search_param;
$params[]=$search_param;
$types.='ss';
}
$where_clause=implode(' AND ',$where);
$query="SELECT c.id,c.course_code,c.name, /* c.credit_hours, */ d.dep_name,l.level_name,s.semester_name,COUNT(DISTINCT et.token_id)as token_count FROM courses c LEFT JOIN department d ON c.department_id=d.t_id LEFT JOIN level l ON c.level_id=l.t_id LEFT JOIN semesters s ON c.semester_id=s.semester_id LEFT JOIN evaluation_tokens et ON c.id=et.course_id WHERE $where_clause GROUP BY c.id ORDER BY d.dep_name,c.course_code";
$stmt=mysqli_prepare($conn,$query);
if(!empty($params)){
mysqli_stmt_bind_param($stmt,$types,...$params);
}
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$courses=[];
while($row=mysqli_fetch_assoc($result))$courses[]=$row;
mysqli_stmt_close($stmt);
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
.btn-danger{background:#dc3545;color:white}
.btn-sm{padding:6px 12px;font-size:12px}
.courses-table{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.courses-table table{width:100%;border-collapse:collapse}
.courses-table th{background:#f8f9fa;padding:15px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0;font-size:13px}
.courses-table td{padding:15px;border-bottom:1px solid #f0f0f0;font-size:14px}
.courses-table tr:hover{background:#f8f9fa}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:8px}
</style>
<div class="page-header">
<h1>Manage Courses</h1>
<p>System-wide course management across all departments</p>
</div>
<div class="top-actions">
<div>
<a href="create.php" class="btn btn-primary">+ Add New Course</a>
</div>
<div style="color:#666;font-size:14px">
Total: <strong><?php echo count($courses);?></strong> course(s)
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
<label>Semester</label>
<select name="semester_id">
<option value="0">All Semesters</option>
<?php foreach($semesters as $sem): ?>
<option value="<?php echo $sem['semester_id'];?>" <?php echo $filter_semester==$sem['semester_id']?'selected':'';?>>
<?php echo htmlspecialchars($sem['semester_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="filter-group">
<label>Search</label>
<input type="text" name="search" placeholder="Course code or name..." value="<?php echo htmlspecialchars($search);? maxlength="100">">
</div>
</div>
<button type="submit" class="btn btn-primary">Apply Filters</button>
<a href="list.php" class="btn btn-secondary">Reset</a>
<button type="button" onclick="exportTableToCSV('courses-table','courses.csv')" class="btn btn-success">Export CSV</button>
</form>
</div>
<?php if(empty($courses)): ?>
<div class="empty-state">
<div style="font-size:80px;opacity:0.3">📚</div>
<h3>No Courses Found</h3>
<p style="color:#666">No courses match your search criteria.</p>
<a href="create.php" class="btn btn-primary">Add First Course</a>
</div>
<?php else: ?>
<div class="courses-table">
<table id="courses-table">
<thead>
<tr>
<th scope="col">Course Code</th>
<th scope="col">Course Name</th>
<th scope="col">Department</th>
<th scope="col">Level</th>
<th scope="col">Semester</th>
<!--th>Credits</th-->
<th scope="col">Tokens</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($courses as $course): ?>
<tr>
<td><strong><?php echo htmlspecialchars($course['course_code']);?></strong></td>
<td><?php echo htmlspecialchars($course['name']);?></td>
<td><?php echo htmlspecialchars($course['dep_name']);?></td>
<td><?php echo htmlspecialchars($course['level_name']);?></td>
<td><?php echo htmlspecialchars($course['semester_name']);?></td>
<!--td><?php echo $course['credit_hours'];?></td-->
<td><?php echo $course['token_count'];?></td>
<td>
<a href="edit.php?id=<?php echo $course['id'];?>" class="btn btn-primary btn-sm">Edit</a>
<form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this course?')">
    <input type="hidden" name="id" value="<?php echo $course['id'];?>">
    <?php csrf_token_input(); ?>
    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
</form>
</td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
<?php endif;?>
<script>
function exportTableToCSV(tableId,filename){
const table=document.getElementById(tableId);
let csv=[];
for(let row of table.rows){
let csvRow=[];
for(let cell of row.cells){
if(row.cells.indexOf(cell)!==row.cells.length-1){
csvRow.push('"'+cell.innerText.replace(/"/g,'""')+'"');
}
}
csv.push(csvRow.join(','));
}
const csvContent=csv.join('\n');
const blob=new Blob([csvContent],{type:'text/csv'});
const url=URL.createObjectURL(blob);
const a=document.createElement('a');
a.href=url;
a.download=filename;
a.click();
}
</script>
<?php require_once '../../includes/footer.php';?>
