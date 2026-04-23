<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='System Information';
$php_version=phpversion();
$db_version=mysqli_get_server_info($conn);
$query_stats="
SELECT 
(SELECT COUNT(*) FROM user_details) as total_users,
(SELECT COUNT(*) FROM courses) as total_courses,
(SELECT COUNT(*) FROM evaluation_tokens) as total_tokens,
(SELECT COUNT(*) FROM evaluations) as total_evaluations,
(SELECT COUNT(*) FROM evaluation_questions) as total_questions
";
$result_stats=mysqli_query($conn,$query_stats);
$stats=mysqli_fetch_assoc($result_stats);
$query_space="
SELECT 
table_schema as db_name,
SUM(data_length + index_length) as size_bytes,
SUM(data_length + index_length) / 1024 / 1024 as size_mb
FROM information_schema.TABLES 
WHERE table_schema = DATABASE()
GROUP BY table_schema
";
$result_space=mysqli_query($conn,$query_space);
$space=mysqli_fetch_assoc($result_space);
require_once '../../includes/header.php';
?>
<style>
.info-container{max-width:1000px;margin:0 auto}
.info-section{background:white;padding:25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:20px}
.info-section h2{margin:0 0 20px 0;color:#667eea;border-bottom:2px solid #667eea;padding-bottom:10px}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px}
.info-card{background:#f8f9fa;padding:20px;border-radius:8px;text-align:center}
.info-value{font-size:32px;font-weight:bold;color:#667eea;margin-bottom:5px}
.info-label{font-size:14px;color:#666}
table{width:100%;border-collapse:collapse}
td{padding:12px;border-bottom:1px solid #f0f0f0}
td:first-child{font-weight:600;width:300px}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
</style>
<div class="info-container">
<div class="page-header">
<h1>📊 System Information</h1>
<p>Technical details and system statistics</p>
</div>
<div class="info-section">
<h2>Database Statistics</h2>
<div class="info-grid">
<div class="info-card">
<div class="info-value"><?php echo number_format($stats['total_users']);?></div>
<div class="info-label">Total Users</div>
</div>
<div class="info-card">
<div class="info-value"><?php echo number_format($stats['total_courses']);?></div>
<div class="info-label">Courses</div>
</div>
<div class="info-card">
<div class="info-value"><?php echo number_format($stats['total_tokens']);?></div>
<div class="info-label">Eval Tokens</div>
</div>
<div class="info-card">
<div class="info-value"><?php echo number_format($stats['total_evaluations']);?></div>
<div class="info-label">Evaluations</div>
</div>
<div class="info-card">
<div class="info-value"><?php echo number_format($stats['total_questions']);?></div>
<div class="info-label">Questions</div>
</div>
</div>
</div>
<div class="info-section">
<h2>Server Information</h2>
<table>
<tr>
<td>PHP Version</td>
<td><?php echo $php_version;?></td>
</tr>
<tr>
<td>Database Server</td>
<td>MySQL <?php echo $db_version;?></td>
</tr>
<tr>
<td>Database Name</td>
<td><?php echo DB_NAME;?></td>
</tr>
<tr>
<td>Database Size</td>
<td><?php echo $space?number_format($space['size_mb'],2).' MB':'Unknown';?></td>
</tr>
<tr>
<td>Server Software</td>
<td><?php echo $_SERVER['SERVER_SOFTWARE']??'Unknown';?></td>
</tr>
<tr>
<td>Server OS</td>
<td><?php echo PHP_OS;?></td>
</tr>
<tr>
<td>Max Upload Size</td>
<td><?php echo ini_get('upload_max_filesize');?></td>
</tr>
<tr>
<td>Max POST Size</td>
<td><?php echo ini_get('post_max_size');?></td>
</tr>
<tr>
<td>Memory Limit</td>
<td><?php echo ini_get('memory_limit');?></td>
</tr>
<tr>
<td>Max Execution Time</td>
<td><?php echo ini_get('max_execution_time');?> seconds</td>
</tr>
</table>
</div>
<div class="info-section">
<h2>Application Information</h2>
<table>
<tr>
<td>Application Name</td>
<td><?php echo APP_NAME;?></td>
</tr>
<tr>
<td>Institution Name</td>
<td><?php echo INSTITUTION_NAME;?></td>
</tr>
<tr>
<td>Application URL</td>
<td><?php echo APP_URL;?></td>
</tr>
<tr>
<td>Session Timeout</td>
<td><?php echo SESSION_TIMEOUT;?> seconds (<?php echo round(SESSION_TIMEOUT/60);?> minutes)</td>
</tr>
<tr>
<td>Min Response Count</td>
<td><?php echo MIN_RESPONSE_COUNT;?> responses</td>
</tr>
<tr>
<td>Maintenance Mode</td>
<td><?php echo MAINTENANCE_MODE?'<span style="color:#dc3545;font-weight:600">ENABLED</span>':'<span style="color:#28a745">Disabled</span>';?></td>
</tr>
<tr>
<td>Password Min Length</td>
<td><?php echo PASSWORD_MIN_LENGTH;?> characters</td>
</tr>
<tr>
<td>Token Length</td>
<td><?php echo TOKEN_LENGTH;?> bytes (<?php echo TOKEN_LENGTH*2;?> character hex)</td>
</tr>
</table>
</div>
<div class="info-section">
<h2>PHP Extensions</h2>
<table>
<tr>
<td>MySQLi</td>
<td><?php echo extension_loaded('mysqli')?'<span style="color:#28a745">✓ Loaded</span>':'<span style="color:#dc3545">✗ Not Loaded</span>';?></td>
</tr>
<tr>
<td>OpenSSL</td>
<td><?php echo extension_loaded('openssl')?'<span style="color:#28a745">✓ Loaded</span>':'<span style="color:#dc3545">✗ Not Loaded</span>';?></td>
</tr>
<tr>
<td>MBString</td>
<td><?php echo extension_loaded('mbstring')?'<span style="color:#28a745">✓ Loaded</span>':'<span style="color:#dc3545">✗ Not Loaded</span>';?></td>
</tr>
<tr>
<td>cURL</td>
<td><?php echo extension_loaded('curl')?'<span style="color:#28a745">✓ Loaded</span>':'<span style="color:#dc3545">✗ Not Loaded</span>';?></td>
</tr>
<tr>
<td>GD (Image)</td>
<td><?php echo extension_loaded('gd')?'<span style="color:#28a745">✓ Loaded</span>':'<span style="color:#dc3545">✗ Not Loaded</span>';?></td>
</tr>
</table>
</div>
<div style="text-align:center;padding:20px">
<a href="index.php" class="btn btn-primary">Back to Settings</a>
</div>
</div>
<?php require_once '../../includes/footer.php';?>
