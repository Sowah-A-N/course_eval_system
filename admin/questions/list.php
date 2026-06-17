<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='Manage Evaluation Questions';
$filter_status=isset($_GET['status'])?$_GET['status']:'active';
$where=["1=1"];
if($filter_status=='active'){
$where[]="is_active=1";
}elseif($filter_status=='inactive'){
$where[]="is_active=0";
}
$where_clause=implode(' AND ',$where);
$query="SELECT * FROM evaluation_questions WHERE $where_clause ORDER BY display_order, question_id";
$result=mysqli_query($conn,$query);
$questions=[];
while($row=mysqli_fetch_assoc($result))$questions[]=$row;
$query_counts="SELECT COUNT(*)as total,SUM(is_active)as active,SUM(CASE WHEN is_active=0 THEN 1 ELSE 0 END)as inactive FROM evaluation_questions";
$result_counts=mysqli_query($conn,$query_counts);
$counts=mysqli_fetch_assoc($result_counts);
require_once '../../includes/header.php';
?>
<style>
.top-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px}
.stat-card{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center}
.stat-value{font-size:36px;font-weight:bold;color:#667eea}
.stat-label{font-size:14px;color:#666;margin-top:5px}
.filters-section{background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.3s}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.btn-success{background:#28a745;color:white}
.btn-warning{background:#ffc107;color:#333}
.btn-danger{background:#dc3545;color:white}
.btn-sm{padding:6px 12px;font-size:12px}
.questions-list{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.question-item{padding:20px;border-bottom:1px solid #f0f0f0;transition:all 0.3s}
.question-item:hover{background:#f8f9fa}
.question-item:last-child{border-bottom:none}
.question-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:10px}
.question-order{font-size:24px;font-weight:bold;color:#667eea;margin-right:15px}
.question-text{flex:1;font-size:16px;font-weight:500;color:#333;line-height:1.6}
.question-meta{display:flex;gap:10px;align-items:center;margin-top:10px}
.status-badge{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;text-transform:uppercase}
.status-active{background:#d4edda;color:#155724}
.status-inactive{background:#f8d7da;color:#721c24}
.question-actions{display:flex;gap:5px;margin-top:10px}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:8px}
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
<div class="page-header">
<h1>Manage Evaluation Questions</h1>
<p>Configure questions used in course evaluations</p>
</div>
<div class="stats-grid">
<div class="stat-card">
<div class="stat-value"><?php echo $counts['total'];?></div>
<div class="stat-label">Total Questions</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $counts['active'];?></div>
<div class="stat-label">Active</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $counts['inactive'];?></div>
<div class="stat-label">Inactive</div>
</div>
</div>
<div class="info-box">
<strong>ℹ️ About Questions:</strong> Active questions appear in student evaluations. Inactive questions are hidden but preserved for historical data. Use the Reorder button to set display order.
</div>
<div class="top-actions">
<div>
<a href="create.php" class="btn btn-primary">+ Add New Question</a>
<a href="reorder.php" class="btn btn-success">⇅ Reorder Questions</a>
</div>
</div>
<div class="filters-section">
<form method="GET" style="display:flex;gap:10px;align-items:center">
<label style="font-weight:600">Filter:</label>
<button type="submit" name="status" value="all" class="btn <?php echo $filter_status=='all'?'btn-primary':'btn-secondary';?>">All</button>
<button type="submit" name="status" value="active" class="btn <?php echo $filter_status=='active'?'btn-primary':'btn-secondary';?>">Active</button>
<button type="submit" name="status" value="inactive" class="btn <?php echo $filter_status=='inactive'?'btn-primary':'btn-secondary';?>">Inactive</button>
</form>
</div>
<?php if(empty($questions)): ?>
<div class="empty-state">
<div style="font-size:80px;opacity:0.3">❓</div>
<h3>No Questions Found</h3>
<p style="color:#666">No evaluation questions match your criteria.</p>
<a href="create.php" class="btn btn-primary">Add First Question</a>
</div>
<?php else: ?>
<div class="questions-list">
<?php foreach($questions as $question): ?>
<div class="question-item">
<div class="question-header">
<div class="question-order">#<?php echo $question['display_order'];?></div>
<div class="question-text"><?php echo htmlspecialchars($question['question_text']);?></div>
</div>
<div class="question-meta">
<span class="status-badge <?php echo $question['is_active']?'status-active':'status-inactive';?>">
<?php echo $question['is_active']?'Active':'Inactive';?>
</span>
<span style="font-size:12px;color:#999">ID: <?php echo $question['question_id'];?></span>
</div>
<div class="question-actions">
<a href="edit.php?id=<?php echo $question['question_id'];?>" class="btn btn-primary btn-sm">Edit</a>
<?php if($question['is_active']): ?>
<a href="deactivate.php?id=<?php echo $question['question_id'];?>" class="btn btn-warning btn-sm" onclick="return confirm('Deactivate this question?')">Deactivate</a>
<?php else: ?>
<a href="activate.php?id=<?php echo $question['question_id'];?>" class="btn btn-success btn-sm">Activate</a>
<?php endif;?>
<form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this question?')">
    <input type="hidden" name="id" value="<?php echo $question['question_id'];?>">
    <?php csrf_token_input(); ?>
    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
</form>
</div>
</div>
<?php endforeach;?>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php';?>
