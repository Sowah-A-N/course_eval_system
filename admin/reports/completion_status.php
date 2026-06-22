<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Completion Status';
$filter_dept=isset($_GET['department_id'])?intval($_GET['department_id']):0;
$filter_level=isset($_GET['level_id'])?intval($_GET['level_id']):0;
// D4: academic year + semester filter
$filter_year=isset($_GET['year_id'])?intval($_GET['year_id']):0;
$filter_sem=isset($_GET['semester_id'])?intval($_GET['semester_id']):0;
$departments=[];
$result_depts=mysqli_query($conn,"SELECT * FROM department ORDER BY dep_name");
while($row=mysqli_fetch_assoc($result_depts))$departments[]=$row;
$levels=[];
$result_levels=mysqli_query($conn,"SELECT * FROM level ORDER BY level_number");
while($row=mysqli_fetch_assoc($result_levels))$levels[]=$row;
$academic_years=[];
$result_years=mysqli_query($conn,"SELECT academic_year_id,academic_year FROM academic_years ORDER BY academic_year DESC");
while($row=mysqli_fetch_assoc($result_years))$academic_years[]=$row;
$semesters=[];
$result_sems=mysqli_query($conn,"SELECT semester_id,semester_name FROM semesters ORDER BY semester_value");
while($row=mysqli_fetch_assoc($result_sems))$semesters[]=$row;
$where=["1=1"];
$params=[];
$types='';
if($filter_dept>0){$where[]="u.department_id=?";$params[]=$filter_dept;$types.='i';}
if($filter_level>0){$where[]="u.level_id=?";$params[]=$filter_level;$types.='i';}
if($filter_year>0){$where[]="et.academic_year_id=?";$params[]=$filter_year;$types.='i';}
if($filter_sem>0){$where[]="et.semester_id=?";$params[]=$filter_sem;$types.='i';}
$where_clause=implode(' AND ',$where);
$query="SELECT u.user_id,u.unique_id,u.f_name,u.l_name,d.dep_name,l.level_name,COUNT(DISTINCT et.token_id)as total_tokens,COUNT(DISTINCT CASE WHEN et.is_used=1 THEN et.token_id END)as completed_tokens FROM user_details u LEFT JOIN department d ON u.department_id=d.t_id LEFT JOIN level l ON u.level_id=l.t_id LEFT JOIN evaluation_tokens et ON u.user_id=et.student_user_id WHERE u.role_id=? AND $where_clause GROUP BY u.user_id ORDER BY u.f_name,u.l_name";
$stmt=mysqli_prepare($conn,$query);
$role_student=ROLE_STUDENT;
$all_params=array_merge([$role_student],$params);
$all_types='i'.$types;
mysqli_stmt_bind_param($stmt,$all_types,...$all_params);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$students=[];
while($row=mysqli_fetch_assoc($result))$students[]=$row;
mysqli_stmt_close($stmt);
require_once '../../includes/header.php';
?>
<style>
.filter-section{background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:15px}
.filter-group label{font-size:14px;font-weight:500;margin-bottom:5px;display:block}
.filter-group select{width:100%;padding:10px;border:1px solid #ddd;border-radius:5px}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.students-table{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.students-table table{width:100%;border-collapse:collapse}
.students-table th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0;font-size:13px}
.students-table td{padding:12px;border-bottom:1px solid #f0f0f0;font-size:14px}
.students-table tr:hover{background:#f8f9fa}
.progress-bar{background:#e9ecef;height:20px;border-radius:10px;overflow:hidden;position:relative}
.progress-fill{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);height:100%;display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:11px}
.status-complete{color:#28a745;font-weight:600}
.status-incomplete{color:#dc3545;font-weight:600}
</style>
<div class="page-header">
<h1>✅ Completion Status</h1>
<p>Track student evaluation completion rates</p>
</div>
<div class="filter-section">
<form method="GET">
<div class="filter-grid">
<div class="filter-group">
<label>Department</label>
<select name="department_id">
<option value="0">All Departments</option>
<?php foreach($departments as $dept): ?>
<option value="<?php echo $dept['t_id'];?>" <?php echo $filter_dept==$dept['t_id']?'selected':'';?>><?php echo htmlspecialchars($dept['dep_name']);?></option>
<?php endforeach;?>
</select>
</div>
<div class="filter-group">
<label>Level</label>
<select name="level_id">
<option value="0">All Levels</option>
<?php foreach($levels as $level): ?>
<option value="<?php echo $level['t_id'];?>" <?php echo $filter_level==$level['t_id']?'selected':'';?>><?php echo htmlspecialchars($level['level_name']);?></option>
<?php endforeach;?>
</select>
</div>
<div class="filter-group">
<label>Academic Year</label>
<select name="year_id">
<option value="0">All Years</option>
<?php foreach($academic_years as $yr): ?>
<option value="<?php echo $yr['academic_year_id'];?>" <?php echo $filter_year==$yr['academic_year_id']?'selected':'';?>><?php echo htmlspecialchars($yr['academic_year']);?></option>
<?php endforeach;?>
</select>
</div>
<div class="filter-group">
<label>Semester</label>
<select name="semester_id">
<option value="0">All Semesters</option>
<?php foreach($semesters as $sem): ?>
<option value="<?php echo $sem['semester_id'];?>" <?php echo $filter_sem==$sem['semester_id']?'selected':'';?>><?php echo htmlspecialchars($sem['semester_name']);?></option>
<?php endforeach;?>
</select>
</div>
</div>
<button type="submit" class="btn btn-primary">Apply Filters</button>
<a href="completion_status.php" class="btn btn-secondary">Reset</a>
</form>
</div>
<div class="students-table">
<table>
<thead>
<tr>
<th scope="col">Student ID</th>
<th scope="col">Name</th>
<th scope="col">Department</th>
<th scope="col">Level</th>
<th scope="col">Completed</th>
<th scope="col">Total</th>
<th scope="col">Progress</th>
<th scope="col">Status</th>
</tr>
</thead>
<tbody>
<?php foreach($students as $student): 
$completion_rate=$student['total_tokens']>0?($student['completed_tokens']/$student['total_tokens'])*100:0;
$is_complete=$student['total_tokens']>0&&$student['completed_tokens']==$student['total_tokens'];
?>
<tr>
<td><?php echo htmlspecialchars($student['unique_id']);?></td>
<td><?php echo htmlspecialchars($student['f_name'].' '.$student['l_name']);?></td>
<td><?php echo htmlspecialchars($student['dep_name']);?></td>
<td><?php echo htmlspecialchars($student['level_name']);?></td>
<td><?php echo $student['completed_tokens'];?></td>
<td><?php echo $student['total_tokens'];?></td>
<td>
<div class="progress-bar">
<div class="progress-fill" style="width:<?php echo $completion_rate;?>%"><?php echo round($completion_rate);?>%</div>
</div>
</td>
<td class="<?php echo $is_complete?'status-complete':'status-incomplete';?>"><?php echo $is_complete?'✓ Complete':'⚠ Incomplete';?></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
<?php require_once '../../includes/footer.php';?>
