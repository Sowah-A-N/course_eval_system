<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Manage Academic Levels';
$query="SELECT l.*,COUNT(DISTINCT u.user_id)as student_count,COUNT(DISTINCT c.id)as course_count FROM level l LEFT JOIN user_details u ON l.t_id=u.level_id AND u.role_id=? LEFT JOIN courses c ON l.t_id=c.level_id GROUP BY l.t_id ORDER BY l.level_number";
$stmt=mysqli_prepare($conn,$query);
$role_student=ROLE_STUDENT;
mysqli_stmt_bind_param($stmt,"i",$role_student);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
$levels=[];
while($row=mysqli_fetch_assoc($result))$levels[]=$row;
mysqli_stmt_close($stmt);
require_once '../../includes/header.php';
?>
<style>
.top-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.3s}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-success{background:#28a745;color:white}
.btn-danger{background:#dc3545;color:white}
.btn-sm{padding:6px 12px;font-size:12px}
.levels-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}
.level-card{background:white;border-radius:10px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,0.1);transition:all 0.3s;border-left:5px solid #667eea}
.level-card:hover{transform:translateY(-5px);box-shadow:0 6px 20px rgba(0,0,0,0.15)}
.level-name{font-size:22px;font-weight:700;color:#333;margin-bottom:8px}
.level-number{font-size:14px;color:#667eea;font-weight:600;background:#f0f3ff;padding:4px 12px;border-radius:15px;display:inline-block;margin-bottom:15px}
.level-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:15px 0}
.stat-item{background:#f8f9fa;padding:12px;border-radius:6px;text-align:center}
.stat-value{font-size:24px;font-weight:bold;color:#667eea}
.stat-label{font-size:12px;color:#666;margin-top:3px}
.level-actions{display:flex;gap:8px;margin-top:15px}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:8px}
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Manage Academic Levels</h1>
<p>Configure academic levels (years of study)</p>
</div>
<div class="info-box">
<strong>ℹ️ About Levels:</strong> Academic levels represent years of study (e.g., 100, 200, 300, 400). Students and courses are assigned to levels. Common systems use 100-level for first year, 200-level for second year, etc.
</div>
<div class="top-actions">
<div>
<a href="create.php" class="btn btn-primary">+ Add New Level</a>
</div>
<div style="color:#666;font-size:14px">
Total: <strong><?php echo count($levels);?></strong> level(s)
</div>
</div>
<?php if(empty($levels)): ?>
<div class="empty-state">
<div style="font-size:80px;opacity:0.3">📊</div>
<h3>No Levels Found</h3>
<p style="color:#666">No academic levels configured in the system.</p>
<a href="create.php" class="btn btn-primary">Add First Level</a>
</div>
<?php else: ?>
<div class="levels-grid">
<?php foreach($levels as $level): ?>
<div class="level-card">
<div class="level-name"><?php echo htmlspecialchars($level['level_name']);?></div>
<span class="level-number">Level <?php echo $level['level_number'];?></span>
<div class="level-stats">
<div class="stat-item">
<div class="stat-value"><?php echo $level['student_count'];?></div>
<div class="stat-label">Students</div>
</div>
<div class="stat-item">
<div class="stat-value"><?php echo $level['course_count'];?></div>
<div class="stat-label">Courses</div>
</div>
</div>
<div class="level-actions">
<a href="edit.php?id=<?php echo $level['t_id'];?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center">Edit</a>
<form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this level?')">
    <input type="hidden" name="id" value="<?php echo $level['t_id'];?>">
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
let csv='Level Name,Level Number,Students,Courses\n';
<?php foreach($levels as $level): ?>
csv+='"<?php echo addslashes($level['level_name']);?>",<?php echo $level['level_number'];?>,<?php echo $level['student_count'];?>,<?php echo $level['course_count'];?>\n';
<?php endforeach;?>
const blob=new Blob([csv],{type:'text/csv'});
const url=URL.createObjectURL(blob);
const a=document.createElement('a');
a.href=url;
a.download='levels.csv';
a.click();
}
</script>
<?php require_once '../../includes/footer.php';?>
