<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='System Constants';
$constants_file='../../config/constants.php';
$file_contents='';
$file_exists=file_exists($constants_file);
if($file_exists){
$file_contents=file_get_contents($constants_file);
}
require_once '../../includes/header.php';
?>
<style>
.constants-container{max-width:1200px;margin:0 auto}
.info-box{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:15px;border-radius:8px;margin-bottom:20px}
.code-viewer{background:#f8f9fa;padding:20px;border-radius:8px;border:2px solid #e0e0e0;overflow-x:auto}
.code-viewer pre{margin:0;font-family:monospace;font-size:13px;line-height:1.6;color:#333}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
</style>
<div class="constants-container">
<div class="page-header">
<h1>📄 System Constants</h1>
<p>View configuration constants defined in constants.php</p>
</div>
<div class="info-box">
<strong>⚠️ Warning:</strong> This file contains sensitive configuration. Do not share its contents. Edit with caution as incorrect values can break the system.
</div>
<?php if($file_exists): ?>
<div class="code-viewer">
<pre><?php echo htmlspecialchars($file_contents);?></pre>
</div>
<?php else: ?>
<div style="background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:20px;border-radius:8px">
<strong>Error:</strong> Constants file not found at <code><?php echo htmlspecialchars($constants_file);?></code>
</div>
<?php endif;?>
<div style="margin-top:20px">
<a href="index.php" class="btn btn-primary">Back to Settings</a>
<a href="../index.php" class="btn btn-secondary">Dashboard</a>
</div>
</div>
<?php require_once '../../includes/footer.php';?>
