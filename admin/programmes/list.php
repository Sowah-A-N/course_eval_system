<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Manage Programmes';
$filter_dept=isset($_GET['department_id'])?intval($_GET['department_id']):0;
$search=isset($_GET['search'])?trim($_GET['search']):'';
$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;
$where=["1=1"];
$params=[];
$types='';
if($filter_dept>0){
$where[]="p.department_id=?";
$params[]=$filter_dept;
$types.='i';
}
if(!empty($search)){
$where[]="(p.prog_name LIKE ? OR p.prog_code LIKE ?)";
$search_param="%$search%";
$params[]=$search_param;
$params[]=$search_param;
$types.='ss';
}
$where_clause=implode(' AND ',$where);
$query="SELECT p.t_id,p.prog_name,p.prog_code,d.dep_name FROM programme p LEFT JOIN department d ON p.department_id=d.t_id WHERE $where_clause ORDER BY d.dep_name,p.prog_name";
$stmt=mysqli_prepare($conn,$query);
if(!empty($params)){
mysqli_stmt_bind_param($stmt,$types,...$params);
}
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$programmes=[];
while($row=mysqli_fetch_assoc($result))$programmes[]=$row;
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
.programmes-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px}
.programme-card{background:white;border-radius:10px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,0.1);transition:all 0.3s;border-left:5px solid #667eea}
.programme-card:hover{transform:translateY(-5px);box-shadow:0 6px 20px rgba(0,0,0,0.15)}
.programme-name{font-size:20px;font-weight:700;color:#333;margin-bottom:8px}
.programme-code{font-size:14px;color:#667eea;font-weight:600;background:#f0f3ff;padding:4px 12px;border-radius:15px;display:inline-block;margin-bottom:15px}
.programme-dept{background:#f8f9fa;padding:12px;border-radius:8px;margin:10px 0;font-size:14px}
.programme-actions{display:flex;gap:8px;margin-top:15px}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:8px}
</style>
<div class="page-header">
<h1>Manage Programmes</h1>
<p>Academic programme management across all departments</p>
</div>
<div class="top-actions">
<div>
<a href="create.php" class="btn btn-primary">+ Add New Programme</a>
</div>
<div style="color:#666;font-size:14px">
Total: <strong><?php echo count($programmes);?></strong> programme(s)
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
<input type="text" name="search" placeholder="Programme name or code..." value="<?php echo htmlspecialchars($search);? maxlength="100">">
</div>
</div>
<button type="submit" class="btn btn-primary">Apply Filters</button>
<a href="list.php" class="btn btn-secondary">Reset</a>
<button type="button" onclick="exportData()" class="btn btn-success">Export CSV</button>
</form>
</div>
<?php if(empty($programmes)): ?>
<div class="empty-state">
<div style="font-size:80px;opacity:0.3">🎓</div>
<h3>No Programmes Found</h3>
<p style="color:#666">No programmes match your search criteria.</p>
<a href="create.php" class="btn btn-primary">Add First Programme</a>
</div>
<?php else: ?>
<div class="programmes-grid">
<?php foreach($programmes as $programme): ?>
<div class="programme-card">
<div class="programme-name"><?php echo htmlspecialchars($programme['prog_name']);?></div>
<span class="programme-code"><?php echo htmlspecialchars($programme['prog_code']);?></span>
<div class="programme-dept">
<strong>Department:</strong> <?php echo htmlspecialchars($programme['dep_name']);?>
</div>
<div class="programme-actions">
<a href="edit.php?id=<?php echo $programme['t_id'];?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center">Edit</a>
<form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this programme?')">
    <input type="hidden" name="id" value="<?php echo $programme['t_id'];?>">
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
let csv='Programme Name,Programme Code,Department\n';
<?php foreach($programmes as $prog): ?>
csv+='"<?php echo addslashes($prog['prog_name']);?>","<?php echo addslashes($prog['prog_code']);?>","<?php echo addslashes($prog['dep_name']);?>"\n';
<?php endforeach;?>
const blob=new Blob([csv],{type:'text/csv'});
const url=URL.createObjectURL(blob);
const a=document.createElement('a');
a.href=url;
a.download='programmes.csv';
a.click();
}
</script>
<?php require_once '../../includes/footer.php';?>
