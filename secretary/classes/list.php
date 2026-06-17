<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_SECRETARY){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$department_id=$_SESSION['department_id'];
$page_title='Manage Classes';
$search=isset($_GET['search'])?trim($_GET['search']):'';
//$where=["department_id=?"];
$where[] = "1=1";
$params=[$department_id];
$types='i';
if(!empty($search)){
$where[]="class_name LIKE ?";
$search_param="%$search%";
$params[]=$search_param;
$types.='s';
}
$where_clause=implode(' AND ',$where);
$query="SELECT c.t_id,c.class_name, /* c.class_code, */ COUNT(DISTINCT u.user_id)as student_count FROM classes c LEFT JOIN user_details u ON c.t_id=u.class_id AND u.role_id=? WHERE $where_clause GROUP BY c.t_id ORDER BY c.class_name";
$stmt=mysqli_prepare($conn,$query);
$role=ROLE_STUDENT;
$all_params=array_merge([$role],$params);
$all_types='i'.$types;
mysqli_stmt_bind_param($stmt,$all_types,...$all_params);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$classes=[];
while($row=mysqli_fetch_assoc($result))$classes[]=$row;
mysqli_stmt_close($stmt);
require_once '../../includes/header.php';
?>
<style>
.top-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px}
.filters-section{background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.filter-group label{font-size:14px;font-weight:500;margin-bottom:5px;display:block}
.filter-group input{width:100%;padding:10px;border:1px solid #ddd;border-radius:5px}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.3s}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.btn-success{background:#28a745;color:white}
.btn-danger{background:#dc3545;color:white}
.btn-sm{padding:6px 12px;font-size:12px}
.classes-table{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.classes-table table{width:100%;border-collapse:collapse}
.classes-table th{background:#f8f9fa;padding:15px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0}
.classes-table td{padding:15px;border-bottom:1px solid #f0f0f0}
.classes-table tr:hover{background:#f8f9fa}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:8px}
</style>
<div class="page-header">
<h1>Manage Classes</h1>
<p>View and manage classes in your department</p>
</div>
<div class="top-actions">
<div>
<a href="create.php" class="btn btn-primary">+ Add New Class</a>
</div>
<div style="color:#666;font-size:14px">
Total: <strong><?php echo count($classes);?></strong> class(es)
</div>
</div>
<div class="filters-section">
<form method="GET" style="display:flex;gap:10px;align-items:flex-end">
<div class="filter-group" style="flex:1">
<label>Search</label>
<input type="text" name="search" placeholder="Class name or code..." value="<?php echo htmlspecialchars($search);? maxlength="100">">
</div>
<button type="submit" class="btn btn-primary">Search</button>
<a href="list.php" class="btn btn-secondary">Reset</a>
<button type="button" onclick="exportTableToCSV('classes-table','classes.csv')" class="btn btn-success">Export CSV</button>
</form>
</div>
<?php if(empty($classes)): ?>
<div class="empty-state">
<div style="font-size:80px;opacity:0.3">🏫</div>
<h3>No Classes Found</h3>
<p style="color:#666">No classes match your search criteria.</p>
<a href="create.php" class="btn btn-primary">Add First Class</a>
</div>
<?php else: ?>
<div class="classes-table">
<table id="classes-table">
<thead>
<tr>
<th scope="col">Class Code</th>
<th scope="col">Class Name</th>
<th scope="col">Students Enrolled</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($classes as $class): ?>
<tr>
<td><strong><?php echo htmlspecialchars($class['class_code']);?></strong></td>
<td><?php echo htmlspecialchars($class['class_name']);?></td>
<td><?php echo $class['student_count'];?> student(s)</td>
<td>
<a href="edit.php?id=<?php echo $class['t_id'];?>" class="btn btn-primary btn-sm">Edit</a>
<form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this class?')">
    <input type="hidden" name="id" value="<?php echo $class['t_id'];?>">
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
