<?php
/**
 * Admin Create User
 * Passwords and usernames are auto-generated; admin sees them once.
 */
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
require_once '../../includes/user_helpers.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_ADMIN) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type']    = 'error';
    header("Location: ../../login.php");
    exit();
}

$page_title = 'Add New User';
$errors     = [];

// Dropdown data
$departments = [];
$result_depts = mysqli_query($conn, "SELECT * FROM department ORDER BY dep_name");
while ($row = mysqli_fetch_assoc($result_depts)) $departments[] = $row;

$levels = [];
$result_levels = mysqli_query($conn, "SELECT * FROM level ORDER BY level_number");
while ($row = mysqli_fetch_assoc($result_levels)) $levels[] = $row;

$classes = [];
$result_cls = mysqli_query($conn,
    "SELECT c.*,d.dep_name FROM classes c LEFT JOIN department d ON c.department_id=d.t_id ORDER BY d.dep_name,c.class_name");
while ($row = mysqli_fetch_assoc($result_cls)) $classes[] = $row;

// D2: one-time credential display after PRG redirect
$show_creds = null;
if (isset($_GET['created']) && isset($_SESSION['new_user_creds'])) {
    $show_creds = $_SESSION['new_user_creds'];
    unset($_SESSION['new_user_creds']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) $errors[] = 'Invalid security token.';

    $f_name       = trim($_POST['f_name']       ?? '');
    $l_name       = trim($_POST['l_name']       ?? '');
    $email        = trim($_POST['email']        ?? '');
    $role_id      = intval($_POST['role_id']      ?? 0);
    $department_id = intval($_POST['department_id'] ?? 0);
    $level_id     = intval($_POST['level_id']     ?? 0);
    $class_id     = intval($_POST['class_id']     ?? 0);
    $unique_id    = trim($_POST['unique_id']    ?? '');
    $is_active    = isset($_POST['is_active'])  ? 1 : 0;
    $action       = $_POST['action'] ?? 'create';

    if (empty($f_name))  $errors[] = 'First name required.';
    if (empty($l_name))  $errors[] = 'Last name required.';
    if (empty($email))   $errors[] = 'Email required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
    if ($role_id === 0)  $errors[] = 'Please select a role.';
    if (in_array($role_id, [ROLE_STUDENT, ROLE_ADVISOR, ROLE_HOD, ROLE_SECRETARY, ROLE_QUALITY]) && $department_id === 0)
        $errors[] = 'Department required for this role.';
    if ($role_id === ROLE_STUDENT && $level_id === 0)  $errors[] = 'Level required for students.';
    if ($role_id === ROLE_STUDENT && $class_id === 0)  $errors[] = 'Class required for students.';

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

        $dept_id_val   = $department_id > 0 ? $department_id : null;
        $level_id_val  = $level_id > 0      ? $level_id      : null;
        $class_id_val  = $class_id > 0      ? $class_id      : null;
        $uid_val       = !empty($unique_id)  ? $unique_id     : null;

        $stmt = mysqli_prepare($conn,
            "INSERT INTO user_details
             (username, password, email, f_name, l_name, unique_id, role_id,
              department_id, level_id, class_id, is_active, force_password_change)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,1)");
        mysqli_stmt_bind_param($stmt, "ssssssiiiii",
            $username, $password_hash, $email, $f_name, $l_name, $uid_val,
            $role_id, $dept_id_val, $level_id_val, $class_id_val, $is_active);

        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            log_audit($conn, $_SESSION['user_id'], AUDIT_USER_CREATE, 'user_details', $new_id, null,
                ['username' => $username, 'email' => $email, 'role_id' => $role_id]);
            $_SESSION['new_user_creds'] = [
                'name'     => "$f_name $l_name",
                'username' => $username,
                'password' => $temp_password,
                'role'     => ROLE_NAMES[$role_id] ?? 'User',
                'continue' => ($action === 'create_another'),
            ];
            header("Location: create.php?created=1");
            exit();
        } else {
            $errors[] = 'Error creating user.';
        }
        mysqli_stmt_close($stmt);
    }
}

require_once '../../includes/header.php';
?>
<style>
.form-container{max-width:900px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px}
.form-label.required::after{content:' *';color:#dc3545}
.form-input,.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
.form-checkbox{margin-right:8px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-success{background:#28a745;color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
.conditional-field{display:none}
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
<h1>Add New User</h1>
<p>Username and temporary password are generated automatically</p>
</div>

<?php if ($show_creds): ?>
<div class="creds-card">
    <h2>✅ <?php echo htmlspecialchars($show_creds['role']); ?> Account Created</h2>
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
        <p style="margin:0;color:#166534;font-size:14px">✔ Create another user below ↓</p>
    <?php else: ?>
        <a href="create.php" class="btn btn-primary" style="margin-right:8px">Create Another User</a>
        <a href="list.php"   class="btn btn-secondary">View All Users</a>
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
<form method="POST" id="userForm">
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
<label class="form-label required">Role</label>
<select name="role_id" id="role_id" class="form-select" required>
<option value="0">-- Select Role --</option>
<?php foreach (ROLE_NAMES as $rid => $rname): ?>
<option value="<?php echo $rid; ?>"
    <?php echo (isset($_POST['role_id']) && $_POST['role_id'] == $rid) ? 'selected' : ''; ?>>
    <?php echo htmlspecialchars($rname); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group conditional-field" id="dept-field">
<label class="form-label">Department</label>
<select name="department_id" class="form-select">
<option value="0">-- Select Department --</option>
<?php foreach ($departments as $dept): ?>
<option value="<?php echo $dept['t_id']; ?>"><?php echo htmlspecialchars($dept['dep_name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group conditional-field" id="student-id-field">
<label class="form-label">Student ID</label>
<input type="text" name="unique_id" class="form-input" value="<?php echo htmlspecialchars($_POST['unique_id'] ?? ''); ?>">
</div>
<div class="form-group conditional-field" id="level-field">
<label class="form-label">Level</label>
<select name="level_id" class="form-select">
<option value="0">-- Select Level --</option>
<?php foreach ($levels as $level): ?>
<option value="<?php echo $level['t_id']; ?>"><?php echo htmlspecialchars($level['level_name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group conditional-field" id="class-field">
<label class="form-label">Class</label>
<select name="class_id" class="form-select">
<option value="0">-- Select Class --</option>
<?php foreach ($classes as $class): ?>
<option value="<?php echo $class['t_id']; ?>"><?php echo htmlspecialchars($class['class_name'] . ' (' . $class['dep_name'] . ')'); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label>
<input type="checkbox" id="is_active" name="is_active" class="form-checkbox"
    <?php echo (isset($_POST['is_active']) || !isset($_POST['f_name'])) ? 'checked' : ''; ?>>
<span class="form-label" style="display:inline">Active</span>
</label>
</div>
<button type="submit" name="action" value="create" class="btn btn-primary">Create User</button>
<button type="submit" name="action" value="create_another" class="btn btn-success">Save &amp; Create Another</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php endif; ?>

<script>
const ROLES_WITH_DEPT=[<?php echo implode(',', [ROLE_HOD, ROLE_SECRETARY, ROLE_ADVISOR, ROLE_STUDENT, ROLE_QUALITY]); ?>];
const ROLE_STUDENT_ID=<?php echo ROLE_STUDENT; ?>;
document.getElementById('role_id').addEventListener('change',function(){
    var roleId=parseInt(this.value);
    document.getElementById('dept-field').style.display=ROLES_WITH_DEPT.includes(roleId)?'block':'none';
    document.getElementById('student-id-field').style.display=(roleId===ROLE_STUDENT_ID)?'block':'none';
    document.getElementById('level-field').style.display=(roleId===ROLE_STUDENT_ID)?'block':'none';
    document.getElementById('class-field').style.display=(roleId===ROLE_STUDENT_ID)?'block':'none';
});
document.getElementById('role_id').dispatchEvent(new Event('change'));

// Live username preview
(function(){
    var fn=document.getElementById('f_name');
    var ln=document.getElementById('l_name');
    var pr=document.getElementById('username-preview');
    if(!fn||!ln||!pr)return;
    function u(){var f=fn.value.replace(/[^a-zA-Z]/g,'').toLowerCase();var l=ln.value.replace(/[^a-zA-Z]/g,'').toLowerCase();pr.value=(f&&l)?f+'.'+l:'';}
    fn.addEventListener('input',u);ln.addEventListener('input',u);u();
}());

function copyText(id,btn){
    var el=document.getElementById(id);if(!el)return;
    navigator.clipboard.writeText(el.textContent).then(function(){var o=btn.textContent;btn.textContent='Copied!';setTimeout(function(){btn.textContent=o;},2000);});
}
</script>
<?php require_once '../../includes/footer.php'; ?>
