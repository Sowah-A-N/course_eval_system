<?php
/** Admin — edit a faculty: name, code, Dean, and which departments belong to it. */
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if ($_SESSION['role_id'] !== ROLE_ADMIN) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}
$faculty_id = intval($_GET['id'] ?? $_POST['faculty_id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM faculties WHERE t_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $faculty_id);
mysqli_stmt_execute($stmt);
$faculty = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$faculty) {
    $_SESSION['flash_message'] = 'Faculty not found.';
    $_SESSION['flash_type'] = 'error';
    header("Location: index.php");
    exit();
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $errors[] = 'Invalid security token.';
    }
    $name = trim($_POST['faculty_name'] ?? '');
    $code = trim($_POST['faculty_code'] ?? '');
    $dean = intval($_POST['dean_user_id'] ?? 0) ?: null;
    $dept_ids = isset($_POST['dept_ids']) && is_array($_POST['dept_ids']) ? array_map('intval', $_POST['dept_ids']) : [];
    if ($name === '' || $code === '') $errors[] = 'Faculty name and code are required.';
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "UPDATE faculties SET faculty_name=?, faculty_code=?, dean_user_id=? WHERE t_id=?");
        mysqli_stmt_bind_param($stmt, "ssii", $name, $code, $dean, $faculty_id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            // Reset this faculty's departments, then set the chosen ones (moving them if needed).
            $u = mysqli_prepare($conn, "UPDATE department SET faculty_id = NULL WHERE faculty_id = ?");
            mysqli_stmt_bind_param($u, "i", $faculty_id);
            mysqli_stmt_execute($u);
            mysqli_stmt_close($u);
            if (!empty($dept_ids)) {
                $set = mysqli_prepare($conn, "UPDATE department SET faculty_id = ? WHERE t_id = ?");
                foreach ($dept_ids as $did) {
                    mysqli_stmt_bind_param($set, "ii", $faculty_id, $did);
                    mysqli_stmt_execute($set);
                }
                mysqli_stmt_close($set);
            }
            log_audit($conn, $_SESSION['user_id'], 'FACULTY_UPDATE', 'faculties', $faculty_id, null, ['name' => $name, 'departments' => count($dept_ids)]);
            $_SESSION['flash_message'] = 'Faculty updated.';
            $_SESSION['flash_type'] = 'success';
            header("Location: index.php");
            exit();
        } else {
            $errors[] = 'Update failed (name or code may already exist).';
            mysqli_stmt_close($stmt);
        }
    }
}

$deans = [];
$rd = mysqli_query($conn, "SELECT user_id, f_name, l_name FROM user_details WHERE role_id = " . ROLE_DEAN . " AND is_active = 1 ORDER BY l_name, f_name");
while ($row = mysqli_fetch_assoc($rd)) $deans[] = $row;

// Departments: all, flagged with which faculty they currently belong to.
$departments = [];
$rdept = mysqli_query($conn, "SELECT t_id, dep_name, faculty_id FROM department ORDER BY dep_name");
while ($row = mysqli_fetch_assoc($rdept)) $departments[] = $row;

require_once '../../includes/header.php';
?>
<style>
.form-container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px}
.form-input,.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
.btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
.dept-item{display:block;padding:7px 0}
.muted{color:#888;font-size:13px}
</style>
<div class="page-header"><h1>Edit Faculty</h1></div>
<?php if (!empty($errors)): ?>
<div class="alert-error"><strong>⚠️ Errors:</strong><ul style="margin:10px 0 0 20px"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="form-container">
<form method="POST">
    <?php csrf_token_input(); ?>
    <input type="hidden" name="faculty_id" value="<?php echo (int)$faculty_id; ?>">
    <div class="form-group">
        <label class="form-label">Faculty Name</label>
        <input type="text" name="faculty_name" class="form-input" value="<?php echo htmlspecialchars($_POST['faculty_name'] ?? $faculty['faculty_name']); ?>" required>
    </div>
    <div class="form-group">
        <label class="form-label">Faculty Code</label>
        <input type="text" name="faculty_code" class="form-input" value="<?php echo htmlspecialchars($_POST['faculty_code'] ?? $faculty['faculty_code']); ?>" required>
    </div>
    <div class="form-group">
        <label class="form-label">Dean</label>
        <select name="dean_user_id" class="form-select">
            <option value="0">— none —</option>
            <?php foreach ($deans as $d): $cur = ($_POST['dean_user_id'] ?? $faculty['dean_user_id']); ?>
                <option value="<?php echo (int)$d['user_id']; ?>" <?php echo ((int)$cur === (int)$d['user_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars(trim($d['f_name'].' '.$d['l_name'])); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Departments in this faculty</label>
        <p class="muted">A department belongs to one faculty; ticking it here moves it here.</p>
        <?php foreach ($departments as $d): ?>
            <label class="dept-item">
                <input type="checkbox" name="dept_ids[]" value="<?php echo (int)$d['t_id']; ?>" <?php echo ((int)$d['faculty_id'] === (int)$faculty_id) ? 'checked' : ''; ?>>
                <?php echo htmlspecialchars($d['dep_name']); ?>
                <?php if ($d['faculty_id'] !== null && (int)$d['faculty_id'] !== (int)$faculty_id): ?><span class="muted">(currently in another faculty)</span><?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<?php require_once '../../includes/footer.php'; ?>
