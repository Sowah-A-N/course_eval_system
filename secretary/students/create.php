<?php
/**
 * Secretary Create Student
 * Passwords and usernames are auto-generated; creator sees them once.
 */
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/user_helpers.php';
require_once '../../includes/mailer.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_SECRETARY) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type']    = 'error';
    header("Location: ../../login.php");
    exit();
}

$department_id = $_SESSION['department_id'];
$page_title    = 'Add New Student';
$errors        = [];

// D2: pick up one-time credential display after PRG redirect
$show_creds = null;
if (isset($_GET['created']) && isset($_SESSION['new_student_creds'])) {
    $show_creds = $_SESSION['new_student_creds'];
    unset($_SESSION['new_student_creds']);
}

// Levels for dropdown
$levels = [];
$result_levels = mysqli_query($conn, "SELECT * FROM level ORDER BY level_number");
while ($row = mysqli_fetch_assoc($result_levels)) $levels[] = $row;

// Classes in this secretary's department
$stmt_cls = mysqli_prepare($conn, "SELECT * FROM classes WHERE department_id=? ORDER BY class_name");
mysqli_stmt_bind_param($stmt_cls, "i", $department_id);
mysqli_stmt_execute($stmt_cls);
$result_cls = mysqli_stmt_get_result($stmt_cls);
$classes = [];
while ($row = mysqli_fetch_assoc($result_cls)) $classes[] = $row;
mysqli_stmt_close($stmt_cls);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $errors[] = 'Invalid security token.';
    }

    $f_name    = trim($_POST['f_name']    ?? '');
    $l_name    = trim($_POST['l_name']    ?? '');
    $email     = trim($_POST['email']     ?? '');
    $unique_id = trim($_POST['unique_id'] ?? '');
    $level_id  = intval($_POST['level_id']  ?? 0);
    $class_id  = intval($_POST['class_id']  ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $action    = $_POST['action'] ?? 'create';

    if (empty($f_name))   $errors[] = 'First name is required.';
    if (empty($l_name))   $errors[] = 'Last name is required.';
    if (empty($email))    $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if (empty($unique_id)) $errors[] = 'Student ID is required.';
    if ($level_id === 0)  $errors[] = 'Please select a level.';
    if ($class_id === 0)  $errors[] = 'Please select a class.';

    if (empty($errors)) {
        $stmt_chk = mysqli_prepare($conn,
            "SELECT user_id FROM user_details WHERE email=? OR unique_id=?");
        mysqli_stmt_bind_param($stmt_chk, "ss", $email, $unique_id);
        mysqli_stmt_execute($stmt_chk);
        if (mysqli_stmt_get_result($stmt_chk)->num_rows > 0) {
            $errors[] = 'Email or Student ID already exists.';
        }
        mysqli_stmt_close($stmt_chk);
    }

    if (empty($errors)) {
        // A1+A2: auto-generate credentials
        $temp_password = ces_generate_temp_password();
        $username      = ces_derive_username($conn, $f_name, $l_name);
        $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
        $role          = ROLE_STUDENT;

        $stmt = mysqli_prepare($conn,
            "INSERT INTO user_details
             (username, password, email, f_name, l_name, unique_id, role_id,
              department_id, level_id, class_id, is_active, force_password_change, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW())");
        mysqli_stmt_bind_param($stmt, "ssssssiiii",
            $username, $password_hash, $email, $f_name, $l_name,
            $unique_id, $role, $department_id, $level_id, $class_id, $is_active);

        if (mysqli_stmt_execute($stmt)) {
            // Email the new student their login details (best-effort).
            $emailed = ces_send_login_details($email, "$f_name $l_name", $username, $temp_password);
            $_SESSION['new_student_creds'] = [
                'name'     => "$f_name $l_name",
                'username' => $username,
                'password' => $temp_password,
                'emailed'  => $emailed,
                'continue' => ($action === 'create_another'),
            ];
            header("Location: create.php?created=1");
            exit();
        } else {
            $errors[] = 'Error creating student.';
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
.form-input,.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
.form-input:focus,.form-select:focus{outline:none;border-color:#667eea}
.form-checkbox{margin-right:8px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-success{background:#28a745;color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
/* credentials reveal card */
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
    <h1>Add New Student</h1>
    <p>Username and temporary password are generated automatically</p>
</div>

<?php if ($show_creds): ?>
<!-- Credentials reveal — shown once after successful creation -->
<div class="creds-card">
    <h2>✅ Student Account Created</h2>
    <p>Share these login credentials with <strong><?php echo htmlspecialchars($show_creds['name']); ?></strong>. They will be required to change their password on first login.</p>
    <?php if (!empty($show_creds['emailed'])): ?>
    <p style="color:#166534;font-size:14px">📧 A copy of these login details has been emailed to the student.</p>
    <?php endif; ?>
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
        <p style="margin:0;color:#166534;font-size:14px">✔ Create another student below ↓</p>
    <?php else: ?>
        <a href="create.php" class="btn btn-primary" style="margin-right:8px">Create Another Student</a>
        <a href="list.php"   class="btn btn-secondary">View All Students</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert-error">
    <strong>⚠️ Errors:</strong>
    <ul style="margin:10px 0 0 20px;padding:0">
        <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (empty($show_creds) || !empty($show_creds['continue'])): ?>
<div class="form-container">
    <form method="POST">
        <?php csrf_token_input(); ?>

        <div class="form-group">
            <label class="form-label required">First Name</label>
            <input type="text" name="f_name" class="form-input" id="f_name"
                   value="<?php echo htmlspecialchars($_POST['f_name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label required">Last Name</label>
            <input type="text" name="l_name" class="form-input" id="l_name"
                   value="<?php echo htmlspecialchars($_POST['l_name'] ?? ''); ?>" required>
        </div>

        <!-- Auto-generated username preview (read-only hint) -->
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
            <label class="form-label required">Student ID</label>
            <input type="text" name="unique_id" class="form-input"
                   value="<?php echo htmlspecialchars($_POST['unique_id'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label required">Level</label>
            <select name="level_id" class="form-select" required>
                <option value="0">-- Select Level --</option>
                <?php foreach ($levels as $level): ?>
                    <option value="<?php echo $level['t_id']; ?>"
                        <?php echo (isset($_POST['level_id']) && $_POST['level_id'] == $level['t_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($level['level_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label required">Class</label>
            <select name="class_id" class="form-select" required>
                <option value="0">-- Select Class --</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['t_id']; ?>"
                        <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['t_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" class="form-checkbox"
                    <?php echo (isset($_POST['is_active']) || !isset($_POST['f_name'])) ? 'checked' : ''; ?>>
                <span class="form-label" style="display:inline">Active</span>
            </label>
        </div>

        <button type="submit" name="action" value="create" class="btn btn-primary">Create Student</button>
        <button type="submit" name="action" value="create_another" class="btn btn-success">Save &amp; Create Another</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php endif; ?>

<script>
// Live username preview (first.last)
(function(){
    var fn = document.getElementById('f_name');
    var ln = document.getElementById('l_name');
    var pr = document.getElementById('username-preview');
    if (!fn || !ln || !pr) return;
    function update(){
        var f = fn.value.replace(/[^a-zA-Z]/g,'').toLowerCase();
        var l = ln.value.replace(/[^a-zA-Z]/g,'').toLowerCase();
        pr.value = (f && l) ? f + '.' + l : '';
    }
    fn.addEventListener('input', update);
    ln.addEventListener('input', update);
    update();
}());

// Copy-to-clipboard helper
function copyText(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.textContent).then(function(){
        var orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function(){ btn.textContent = orig; }, 2000);
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
