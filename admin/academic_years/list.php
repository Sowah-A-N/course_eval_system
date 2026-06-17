<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Manage Academic Years';
$query="SELECT * FROM academic_year ORDER BY start_year DESC";
$result=mysqli_query($conn,$query);
$years=[];
while($row=mysqli_fetch_assoc($result))$years[]=$row;
$query_active="SELECT * FROM view_active_period LIMIT 1";
$result_active=mysqli_query($conn,$query_active);
$active_period=mysqli_fetch_assoc($result_active);
require_once '../../includes/header.php';
?>
<style>
.top-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.3s}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-success{background:#28a745;color:white}
.btn-warning{background:#ffc107;color:#333}
.btn-danger{background:#dc3545;color:white}
.btn-sm{padding:6px 12px;font-size:12px}
.active-period-box{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:25px;border-radius:10px;margin-bottom:20px;box-shadow:0 4px 12px rgba(102,126,234,0.3)}
.active-period-box h2{margin:0 0 10px 0;font-size:24px}
.active-period-box p{margin:0;font-size:16px;opacity:0.9}
.years-list{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.year-item{padding:20px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;transition:all 0.3s}
.year-item:hover{background:#f8f9fa}
.year-item:last-child{border-bottom:none}
.year-info{flex:1}
.year-label{font-size:20px;font-weight:700;color:#333;margin-bottom:5px}
.year-dates{font-size:14px;color:#666}
.year-status{display:flex;gap:10px;align-items:center}
.status-badge{padding:5px 15px;border-radius:20px;font-size:12px;font-weight:600;text-transform:uppercase}
.status-active{background:#d4edda;color:#155724}
.status-inactive{background:#f8f9fa;color:#6c757d}
.year-actions{display:flex;gap:5px;margin-left:15px}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:8px}
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:8px;margin-bottom:20px}
.warning-box{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Manage Academic Years</h1>
<p>Configure academic years and set active evaluation period</p>
</div>
<?php if($active_period): ?>
<div class="active-period-box">
<h2>📅 Active Evaluation Period</h2>
<p><strong>Year:</strong> <?php echo htmlspecialchars($active_period['academic_year']);?> | <strong>Semester:</strong> <?php echo htmlspecialchars($active_period['semester_name']);?></p>
</div>
<?php else: ?>
<div class="warning-box">
<strong>⚠️ No Active Period Set!</strong><br>
You must set an active academic year and semester before students can access evaluations. Use "Set Active" to configure.
</div>
<?php endif;?>
<div class="info-box">
<strong>ℹ️ About Academic Years:</strong> Academic years define the time periods for evaluations. Set one year and semester as "active" - this determines which evaluations students can access.
</div>
<div class="top-actions">
<div>
<a href="create.php" class="btn btn-primary">+ Add New Academic Year</a>
</div>
<div style="color:#666;font-size:14px">
Total: <strong><?php echo count($years);?></strong> year(s)
</div>
</div>
<?php if(empty($years)): ?>
<div class="empty-state">
<div style="font-size:80px;opacity:0.3">📅</div>
<h3>No Academic Years Found</h3>
<p style="color:#666">No academic years configured in the system.</p>
<a href="create.php" class="btn btn-primary">Add First Year</a>
</div>
<?php else: ?>
<div class="years-list">
<?php foreach($years as $year):
$is_active=$active_period&&$active_period['academic_year_id']==$year['academic_year_id'];
?>
<div class="year-item">
<div class="year-info">
<div class="year-label"><?php echo htmlspecialchars($year['year_label']);?></div>
<div class="year-dates">
<?php echo $year['start_year'];?> &ndash; <?php echo $year['end_year'];?>
</div>
</div>
<div class="year-status">
<span class="status-badge <?php echo $is_active?'status-active':'status-inactive';?>">
<?php echo $is_active?'Active':'Inactive';?>
</span>
</div>
<div class="year-actions">
<a href="set_active.php?id=<?php echo $year['academic_year_id'];?>" class="btn btn-success btn-sm">Set Active</a>
<a href="edit.php?id=<?php echo $year['academic_year_id'];?>" class="btn btn-primary btn-sm">Edit</a>
<form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this year?')">
    <input type="hidden" name="id" value="<?php echo $year['academic_year_id'];?>">
    <?php csrf_token_input(); ?>
    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
</form>
</div>
</div>
<?php endforeach;?>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php';?>
