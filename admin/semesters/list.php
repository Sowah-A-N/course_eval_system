<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Manage Semesters';
$query="SELECT * FROM semesters ORDER BY semester_value";
$result=mysqli_query($conn,$query);
$semesters=[];
while($row=mysqli_fetch_assoc($result))$semesters[]=$row;
require_once '../../includes/header.php';
?>
<style>
.top-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.3s}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-danger{background:#dc3545;color:white}
.btn-sm{padding:6px 12px;font-size:12px}
.semesters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px}
.semester-card{background:white;border-radius:10px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,0.1);transition:all 0.3s;border-left:5px solid #667eea}
.semester-card:hover{transform:translateY(-5px);box-shadow:0 6px 20px rgba(0,0,0,0.15)}
.semester-name{font-size:24px;font-weight:700;color:#333;margin-bottom:10px}
.semester-value{font-size:14px;color:#667eea;font-weight:600;background:#f0f3ff;padding:4px 12px;border-radius:15px;display:inline-block;margin-bottom:15px}
.semester-actions{display:flex;gap:8px;margin-top:15px}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:8px}
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Manage Semesters</h1>
<p>Configure academic semesters</p>
</div>
<div class="info-box">
<strong>ℹ️ Note:</strong> Semesters are typically pre-configured in the system. You can edit their names but be careful when deleting as they may be referenced by evaluations.
</div>
<div class="top-actions">
<div>
<a href="create.php" class="btn btn-primary">+ Add New Semester</a>
</div>
<div style="color:#666;font-size:14px">
Total: <strong><?php echo count($semesters);?></strong> semester(s)
</div>
</div>
<?php if(empty($semesters)): ?>
<div class="empty-state">
<div style="font-size:80px;opacity:0.3">📅</div>
<h3>No Semesters Found</h3>
<p style="color:#666">No semesters configured in the system.</p>
<a href="create.php" class="btn btn-primary">Add First Semester</a>
</div>
<?php else: ?>
<div class="semesters-grid">
<?php foreach($semesters as $semester): ?>
<div class="semester-card">
<div class="semester-name"><?php echo htmlspecialchars($semester['semester_name']);?></div>
<span class="semester-value">Value: <?php echo $semester['semester_value'];?></span>
<div class="semester-actions">
<a href="edit.php?id=<?php echo $semester['semester_id'];?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center">Edit</a>
<form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this semester?')">
    <input type="hidden" name="id" value="<?php echo $semester['semester_id'];?>">
    <?php csrf_token_input(); ?>
    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
</form>
</div>
</div>
<?php endforeach;?>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php';?>
