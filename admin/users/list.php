<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Manage Users';
$filter_role=isset($_GET['role_id'])?intval($_GET['role_id']):0;
$filter_dept=isset($_GET['department_id'])?intval($_GET['department_id']):0;
$filter_status=isset($_GET['status'])?$_GET['status']:'all';
$search=isset($_GET['search'])?trim($_GET['search']):'';
$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;
$where=["1=1"];
$params=[];
$types='';
if($filter_role>0){
$where[]="u.role_id=?";
$params[]=$filter_role;
$types.='i';
}
if($filter_dept>0){
$where[]="u.department_id=?";
$params[]=$filter_dept;
$types.='i';
}
if($filter_status!='all'){
$status_val=$filter_status=='active'?1:0;
$where[]="u.is_active=?";
$params[]=$status_val;
$types.='i';
}
if(!empty($search)){
$where[]="(u.f_name LIKE ? OR u.l_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
$search_param="%$search%";
$params[]=$search_param;
$params[]=$search_param;
$params[]=$search_param;
$params[]=$search_param;
$types.='ssss';
}
$where_clause=implode(' AND ',$where);
$query="SELECT u.user_id,u.f_name,u.l_name,u.email,u.username,u.role_id,u.department_id,u.is_active,u.created_at,d.dep_name FROM user_details u LEFT JOIN department d ON u.department_id=d.t_id WHERE $where_clause ORDER BY u.created_at DESC";
$stmt=mysqli_prepare($conn,$query);
if(!empty($params)){
mysqli_stmt_bind_param($stmt,$types,...$params);
}
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$users=[];
while($row=mysqli_fetch_assoc($result))$users[]=$row;
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
.users-table{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.users-table table{width:100%;border-collapse:collapse}
.users-table th{background:#f8f9fa;padding:15px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0;font-size:13px}
.users-table td{padding:15px;border-bottom:1px solid #f0f0f0;font-size:14px}
.users-table tr:hover{background:#f8f9fa}
.status-badge{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;text-transform:uppercase}
.status-active{background:#d4edda;color:#155724}
.status-inactive{background:#f8d7da;color:#721c24}
.role-badge{padding:4px 10px;border-radius:15px;font-size:11px;font-weight:600;text-transform:uppercase}
.role-admin{background:#dc3545;color:white}
.role-hod{background:#17a2b8;color:white}
.role-advisor{background:#ffc107;color:#333}
.role-student{background:#28a745;color:white}
.role-lecturer{background:#007bff;color:white}
.role-quality{background:#6f42c1;color:white}
.role-secretary{background:#fd7e14;color:white}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:8px}
</style>
<div class="page-header">
<h1>Manage Users</h1>
<p>View and manage all system users</p>
</div>
<div class="top-actions">
<div>
<a href="create.php" class="btn btn-primary">+ Add New User</a>
<a href="import_students.php" class="btn btn-secondary">+ Import Students</a>
<a href="import_lecturers.php" class="btn btn-secondary">+ Import Lecturers</a>
</div>
<div style="color:#666;font-size:14px">
Total: <strong><?php echo count($users);?></strong> user(s)
</div>
</div>
<div class="filters-section">
<form method="GET">
<div class="filters-grid">
<div class="filter-group">
<label>Role</label>
<select name="role_id">
<option value="0">All Roles</option>
<?php foreach(ROLE_NAMES as $rid=>$rname): ?>
<option value="<?php echo $rid;?>" <?php echo $filter_role==$rid?'selected':'';?>>
<?php echo htmlspecialchars($rname);?>
</option>
<?php endforeach;?>
</select>
</div>
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
<label>Status</label>
<select name="status">
<option value="all" <?php echo $filter_status=='all'?'selected':'';?>>All</option>
<option value="active" <?php echo $filter_status=='active'?'selected':'';?>>Active</option>
<option value="inactive" <?php echo $filter_status=='inactive'?'selected':'';?>>Inactive</option>
</select>
</div>
<div class="filter-group">
<label>Search</label>
<input type="text" name="search" placeholder="Name, email, or username..." value="<?php echo htmlspecialchars($search);?>" maxlength="100">
</div>
</div>
<button type="submit" class="btn btn-primary">Apply Filters</button>
<a href="list.php" class="btn btn-secondary">Reset</a>
<button type="button" onclick="exportTableToCSV('users-table','users.csv')" class="btn btn-success">Export CSV</button>
</form>
</div>
<?php if(empty($users)): ?>
<div class="empty-state">
<div style="font-size:80px;opacity:0.3">👤</div>
<h3>No Users Found</h3>
<p style="color:#666">No users match your search criteria.</p>
<a href="create.php" class="btn btn-primary">Add First User</a>
</div>
<?php else: ?>
<div class="users-table">
<table id="users-table">
<thead>
<tr>
<th scope="col">Name</th>
<th scope="col">Email</th>
<th scope="col">Username</th>
<th scope="col">Role</th>
<th scope="col">Department</th>
<th scope="col">Status</th>
<th scope="col">Created</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($users as $user):
$role_class='role-'.strtolower(str_replace(' ','-',ROLE_NAMES[$user['role_id']]??'unknown'));
?>
<tr>
<td><strong><?php echo htmlspecialchars($user['f_name'].' '.$user['l_name']);?></strong></td>
<td><?php echo htmlspecialchars($user['email']);?></td>
<td><?php echo htmlspecialchars($user['username'] ?? 'N/A');?></td>
<td><span class="role-badge <?php echo $role_class;?>"><?php echo htmlspecialchars(ROLE_NAMES[$user['role_id']]??'Unknown');?></span></td>
<td><?php echo htmlspecialchars($user['dep_name']??'N/A');?></td>
<td>
<?php if($user['is_active']==1): ?>
<span class="status-badge status-active">Active</span>
<?php else: ?>
<span class="status-badge status-inactive">Inactive</span>
<?php endif;?>
</td>
<td><?php echo date('M d, Y',strtotime($user['created_at']));?></td>
<td>
<a href="edit.php?id=<?php echo $user['user_id'];?>" class="btn btn-primary btn-sm">Edit</a>
<form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this user?')">
    <input type="hidden" name="id" value="<?php echo $user['user_id'];?>">
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
