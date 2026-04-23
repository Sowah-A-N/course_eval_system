<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id']!=ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='System Maintenance';
require_once '../../includes/header.php';
?>
<style>
.maintenance-container{max-width:1000px;margin:0 auto}
.maintenance-section{background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:20px}
.maintenance-section h2{margin:0 0 15px 0;color:#667eea}
.tool-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:20px}
.tool-card{background:#f8f9fa;padding:20px;border-radius:8px;border-left:4px solid #667eea}
.tool-card h3{margin:0 0 10px 0;font-size:18px}
.tool-card p{margin:0 0 15px 0;font-size:14px;color:#666;line-height:1.6}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-warning{background:#ffc107;color:#333}
.btn-danger{background:#dc3545;color:white}
.status-indicator{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px}
.status-ok{background:#28a745}
.status-warning{background:#ffc107}
.status-error{background:#dc3545}
</style>
<div class="maintenance-container">
<div class="page-header">
<h1>🔧 System Maintenance</h1>
<p>Database maintenance and system health tools</p>
</div>
<div class="maintenance-section">
<h2>System Health Status</h2>
<table style="width:100%;border-collapse:collapse">
<tr style="border-bottom:1px solid #f0f0f0">
<td style="padding:15px"><span class="status-indicator status-ok"></span>Database Connection</td>
<td style="padding:15px;text-align:right"><strong style="color:#28a745">✓ Connected</strong></td>
</tr>
<tr style="border-bottom:1px solid #f0f0f0">
<td style="padding:15px"><span class="status-indicator <?php echo MAINTENANCE_MODE?'status-warning':'status-ok';?>"></span>Maintenance Mode</td>
<td style="padding:15px;text-align:right"><strong><?php echo MAINTENANCE_MODE?'<span style="color:#ffc107">⚠ ENABLED</span>':'<span style="color:#28a745">✓ Disabled</span>';?></strong></td>
</tr>
<tr style="border-bottom:1px solid #f0f0f0">
<td style="padding:15px"><span class="status-indicator status-ok"></span>PHP Version</td>
<td style="padding:15px;text-align:right"><strong><?php echo phpversion();?></strong></td>
</tr>
<tr>
<td style="padding:15px"><span class="status-indicator status-ok"></span>MySQL Version</td>
<td style="padding:15px;text-align:right"><strong><?php echo mysqli_get_server_info($conn);?></strong></td>
</tr>
</table>
</div>
<div class="maintenance-section">
<h2>Maintenance Tools</h2>
<div class="tool-grid">
<div class="tool-card">
<h3>🗄️ Database Backup</h3>
<p>Create a backup of the entire database. Includes all tables, data, and structure.</p>
<button class="btn btn-primary" disabled>Create Backup</button>
<p style="margin-top:10px;font-size:12px;color:#999">Use your hosting panel or phpMyAdmin to create backups</p>
</div>
<div class="tool-card">
<h3>🧹 Clear Old Sessions</h3>
<p>Remove expired session data from the server to free up space and improve performance.</p>
<button class="btn btn-warning" disabled>Clear Sessions</button>
<p style="margin-top:10px;font-size:12px;color:#999">Sessions auto-expire based on SESSION_TIMEOUT</p>
</div>
<div class="tool-card">
<h3>📊 Optimize Database</h3>
<p>Optimize database tables to improve query performance and reclaim unused space.</p>
<button class="btn btn-primary" disabled>Optimize Tables</button>
<p style="margin-top:10px;font-size:12px;color:#999">Use phpMyAdmin Operations tab to optimize</p>
</div>
<div class="tool-card">
<h3>🔄 Clear Cache</h3>
<p>Clear system cache files if your application uses caching for improved performance.</p>
<button class="btn btn-warning" disabled>Clear Cache</button>
<p style="margin-top:10px;font-size:12px;color:#999">No file-based caching currently enabled</p>
</div>
<div class="tool-card">
<h3>📝 View Error Logs</h3>
<p>Check PHP error logs for debugging issues and monitoring system problems.</p>
<button class="btn btn-primary" disabled>View Logs</button>
<p style="margin-top:10px;font-size:12px;color:#999">Check server error logs via hosting panel</p>
</div>
<div class="tool-card">
<h3>🔐 Security Audit</h3>
<p>Run a security check to identify potential vulnerabilities and configuration issues.</p>
<button class="btn btn-primary" disabled>Run Audit</button>
<p style="margin-top:10px;font-size:12px;color:#999">Feature planned for future release</p>
</div>
</div>
</div>
<div class="maintenance-section">
<h2>Quick Tips</h2>
<ul style="line-height:2;color:#666">
<li><strong>Regular Backups:</strong> Create database backups weekly or before major changes</li>
<li><strong>Monitor Size:</strong> Check database size regularly (see System Information)</li>
<li><strong>Update PHP:</strong> Keep PHP version updated for security and performance</li>
<li><strong>Session Cleanup:</strong> Old sessions are cleaned automatically after timeout</li>
<li><strong>Optimize Tables:</strong> Run optimization monthly on large databases</li>
<li><strong>Security Updates:</strong> Keep system and dependencies updated</li>
</ul>
</div>
<div style="text-align:center;padding:20px">
<a href="system_info.php" class="btn btn-primary">System Information</a>
<a href="index.php" class="btn btn-primary">Back to Settings</a>
</div>
</div>
<?php require_once '../../includes/footer.php';?>
