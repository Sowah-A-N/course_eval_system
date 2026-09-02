<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADMIN){$_SESSION['flash_message']='Access denied. You do not have permission to view this page.';$_SESSION['flash_type']='error';header("Location:../../login.php");exit();}
$page_title='System Settings';
require_once '../../includes/header.php';
?>
<style>
.settings-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:25px;margin-bottom:30px}
.setting-card{background:white;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);transition:all 0.3s;border-left:5px solid #667eea}
.setting-card:hover{transform:translateY(-5px);box-shadow:0 6px 20px rgba(0,0,0,0.15)}
.setting-card.unavailable{border-left-color:#adb5bd;opacity:0.7}
.setting-icon{font-size:48px;margin-bottom:15px}
.setting-title{font-size:20px;font-weight:700;color:#333;margin-bottom:10px}
.setting-desc{font-size:14px;color:#666;margin-bottom:20px;line-height:1.6}
.btn{display:block;padding:12px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;text-decoration:none;text-align:center;border-radius:8px;font-weight:600;transition:all 0.3s}
.btn:hover{transform:scale(1.05);box-shadow:0 4px 12px rgba(102,126,234,0.4)}
.btn-muted{background:#adb5bd;cursor:default}
.btn-muted:hover{transform:none;box-shadow:none}
.info-box{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:20px;border-radius:8px;margin-bottom:30px}
</style>
<div class="page-header">
<h1>⚙️ System Settings</h1>
<p>Configure system parameters and application settings</p>
</div>
<div class="info-box">
<strong>ℹ️ Note:</strong> Some settings require server access or database changes. Always backup before making configuration changes.
</div>
<div class="settings-grid">
<div class="setting-card">
<div class="setting-icon">📊</div>
<div class="setting-title">System Information</div>
<div class="setting-desc">View the application version, PHP and database details, and live record counts.</div>
<a href="system_info.php" class="btn">View System Info</a>
</div>
<div class="setting-card">
<div class="setting-icon">📋</div>
<div class="setting-title">System Constants</div>
<div class="setting-desc">Review the configured constants — session timeout, password policy, anonymity threshold, and more.</div>
<a href="constants.php" class="btn">View Constants</a>
</div>
<div class="setting-card">
<div class="setting-icon">🔧</div>
<div class="setting-title">System Maintenance</div>
<div class="setting-desc">Enable maintenance mode, back up the database, and view system logs.</div>
<a href="maintenance.php" class="btn">Maintenance Tools</a>
</div>
</div>
<div style="background:white;padding:30px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1)">
<h2 style="margin:0 0 20px 0;color:#667eea">Current Configuration</h2>
<table style="width:100%;border-collapse:collapse">
<tr style="border-bottom:1px solid #f0f0f0">
<td style="padding:15px;font-weight:600">Application Name</td>
<td style="padding:15px"><?php echo APP_NAME;?></td>
</tr>
<tr style="border-bottom:1px solid #f0f0f0">
<td style="padding:15px;font-weight:600">Institution</td>
<td style="padding:15px"><?php echo INSTITUTION_NAME;?></td>
</tr>
<tr style="border-bottom:1px solid #f0f0f0">
<td style="padding:15px;font-weight:600">Session Timeout</td>
<td style="padding:15px"><?php echo SESSION_TIMEOUT/60;?> minutes</td>
</tr>
<tr style="border-bottom:1px solid #f0f0f0">
<td style="padding:15px;font-weight:600">Min Password Length</td>
<td style="padding:15px"><?php echo PASSWORD_MIN_LENGTH;?> characters</td>
</tr>
<tr style="border-bottom:1px solid #f0f0f0">
<td style="padding:15px;font-weight:600">Anonymity Threshold</td>
<td style="padding:15px"><?php echo MIN_RESPONSE_COUNT;?> responses</td>
</tr>
<tr style="border-bottom:1px solid #f0f0f0">
<td style="padding:15px;font-weight:600">Maintenance Mode</td>
<td style="padding:15px"><?php echo MAINTENANCE_MODE?'<span style="color:#dc3545;font-weight:600">ENABLED</span>':'<span style="color:#28a745">Disabled</span>';?></td>
</tr>
<tr style="border-bottom:1px solid #f0f0f0">
<td style="padding:15px;font-weight:600">Database</td>
<td style="padding:15px"><?php echo DB_NAME;?></td>
</tr>
<tr>
<td style="padding:15px;font-weight:600">App Version</td>
<td style="padding:15px"><?php echo APP_VERSION;?></td>
</tr>
</table>
</div>
<?php require_once '../../includes/footer.php';?>
