<?php
/**
 * Secretary Create Lecturer
 * Passwords and usernames are auto-generated; creator sees them once.
 */
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/user_helpers.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_SECRETARY) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type']    = 'error';
    header("Location: ../../login.php");
    exit();
}

$department_id = $_SESSION['department_id'];
$page_title    = 'Add New Lecturer';
$errors        = [];

// D2: one-time credential display after PRG redirect
$show_creds = null;
if (isset($_GET['created']) && isset($_SESSION['new_lecturer_creds'])) {
    $show_creds = $_SESSION['new_lecturer_creds'];
    unset($_SESSION['new_lecturer_creds']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) $errors[] = 'Invalid security token.';

    $f_name    = trim($_POST['f_name']    ?? '');
    $l_name    = trim($_POST['l_name']    ?? '');
    $email     = trim($_POST['email']     ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $action    = $_POST['action'] ?? 'create';

    if (empty($f_name))  $errors[] = 'First name required.';
    if (empty($l_name))  $errors[] = 'Last name required.';
    if (empty($email))   $errors[] = 'Email required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

    if (empty($errors)) {
        $stmt_chk = mysqli_prepare($conn, "SELECT user_id FROM user_details WHERE email=?");
        mysqli_stmt_bind_param($stmt_chk, "s", $email);
        mysqli_stmt_execute($stmt_chk);
        if (mysqli_stmt_get_result($stmt_chk)->num_rows > 0) $errors[] = 'Email already exists.';
        mysqli_stmt_close($stmt_chk);
    }

    if (empty($errors)) {
        // A1+A2: auto-generate credentials
        $temp_password = ces_generate_temp_password();
        $username      = ces_derive_username($conn, $f_name, $l_name);
        $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
        $role          = ROLE_LECTURER;

        $stmt = mysqli_prepare($conn,
            "INSERT INTO user_details
             (username, password, email, f_name, l_name, role_id, department_id,
              is_active, force_password_change, date_created)
             VALUES (?,?,?,?,?,?,?,?,1,NOW())");
        mysqli_stmt_bind_param($stmt, "sssssiii",
            $username, $password_hash, $email, $f_name, $l_name,
            $role, $department_id, $is_active);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['new_lecturer_creds'] = [
                'name'     => "$f_name $l_name",
                'username' => $username,
                'password' => $temp_password,
                'continue' => ($action === 'create_another'),
            ];
            header("Location: create.php?created=1");
            exit();
        } else {
            $errors[] = 'Error creating lecturer.';
        }
        mysqli_stmt_close($stmt);
    }
}

require_once '../../includes/header.php';
?>
<style>
.form-container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px}
.form-label.required::after{content:' *';color:#dc3545}
.form-input{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
.form-checkbox{margin-right:8px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-success{background:#28a745;color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
.creds-card{background:#f0fdf4;border:2px solid #86efac;border-radius:10px;padding:24px;margin-bottom:28px}
.creds-card h2{color:#166534;margin-bottom:8px;font-size:20px}
.creds-card p{color:#166534;margin-bottom:16px;font-size:14px}
.creds-table{width:100%;border-collapse:collapse;font-size:14px;margin-bottom:16px}
.creds-table td{padding:8px 12px;border-bottom:1px solid #bbf7d0}
.creds-table td:first-child{font-weight:600;color:#15803d;width:160px}
.creds-table code{background:#dcfce7;padding:3px 8px;border-radius:4px;font-size:13px;font-family:monospace;user-select:all}
.copy-btn{background:none;border:1px solid #86efac;color:#166534;padding:3px 10px;border-radius:4px;cursor:pointer;font-size:12px}
.copy-btn:hover{background:#dcfce7}
.warn-note{font-size:13px;color:#92400e;background:#fef3c7;border:1px solid #fcd34d;padding:10px 14px;border-radius:6px;margin-bottom:16px}
</style>

<div class="page-header">
<h1>Add New Lecturer</h1>
<p>Username and temporary password are generated automatically</p>
</div>

<?php if ($show_creds): ?>
<div class="creds-card">
    <h2>✅ Lecturer Account Created</h2>
    <p>Share these login credentials with <strong><?php echo htmlspecialchars($show_creds['name']); ?></strong>. They will be required to change their password on first login.</p>
    <table class="creds-table">
        <tr>
            <td>Username</td>
            <td>
                <code id="cred-user"><?php echo htmlspecialchars($show_creds['username']); ?></code>
                <button class="copy-btn" onclick="copyText('cred-user',this)">Copy</button>
            </td>
        </tr>
        <tr>
            <td>Temporary Password</td>
            <td>
                <code id="cred-pass"><?php echo htmlspecialchars($show_creds['password']); ?></code>
                <button class="copy-btn" onclick="copyText('cred-pass',this)">Copy</button>
            </td>
        </tr>
    </table>
    <p class="warn-note">⚠️ This password is displayed <strong>once only</strong>. Copy it now — it will not be shown again.</p>
    <?php if (!empty($show_creds['continue'])): ?>
        <p style="margin:0;color:#166534;font-size:14px">✔ Create another lecturer below ↓</p>
    <?php else: ?>
        <a href="create.php" class="btn btn-primary" style="margin-right:8px">Create Another Lecturer</a>
        <a href="list.php"   class="btn btn-secondary">View All Lecturers</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert-error">
<strong>⚠️ Errors:</strong>
<ul style="margin:10px 0 0 20px;padding:0">
<?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<?php if (empty($show_creds) || !empty($show_creds['continue'])): ?>
<div class="form-container">
<form method="POST">
<?php csrf_token_input(); ?>
<div class="form-group">
<label class="form-label required">First Name</label>
<input type="text" name="f_name" id="f_name" class="form-input"
       value="<?php echo htmlspecialchars($_POST['f_name'] ?? ''); ?>" required>
</div>
<div class="form-group">
<label class="form-label required">Last Name</label>
<input type="text" name="l_name" id="l_name" class="form-input"
       value="<?php echo htmlspecialchars($_POST['l_name'] ?? ''); ?>" required>
</div>
<div class="form-group">
<label class="form-label">Username <span style="color:#888;font-weight:400">(auto-generated)</span></label>
<input type="text" id="username-preview" class="form-input" readonly
       style="background:#f8f9fa;color:#666" placeholder="Will be generated from name…">
</div>
<div class="form-group">
<label class="form-label required">Email</label>
<input type="email" name="email" class="form-input"
       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
</div>
<div class="form-group">
<label>
<input type="checkbox" name="is_active" class="form-checkbox"
    <?php echo (isset($_POST['is_active']) || !isset($_POST['f_name'])) ? 'checked' : ''; ?>>
<span class="form-label" style="display:inline">Active</span>
</label>
</div>
<button type="submit" name="action" value="create" class="btn btn-primary">Create Lecturer</button>
<button type="submit" name="action" value="create_another" class="btn btn-success">Save &amp; Create Another</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php endif; ?>

<script>
(function(){
    var fn=document.getElementById('f_name');
    var ln=document.getElementById('l_name');
    var pr=document.getElementById('username-preview');
    if(!fn||!ln||!pr)return;
    function u(){
        var f=fn.value.replace(/[^a-zA-Z]/g,'').toLowerCase();
        var l=ln.value.replace(/[^a-zA-Z]/g,'').toLowerCase();
        pr.value=(f&&l)?f+'.'+l:'';
    }
    fn.addEventListener('input',u);ln.addEventListener('input',u);u();
}());
function copyText(id,btn){
    var el=document.getElementById(id);if(!el)return;
    navigator.clipboard.writeText(el.textContent).then(function(){
        var o=btn.textContent;btn.textContent='Copied!';
        setTimeout(function(){btn.textContent=o;},2000);
    });
}
</script>
<?php require_once '../../includes/footer.php'; ?>
