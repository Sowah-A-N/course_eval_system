<?php
/**
 * Admin — Faculties (feature 3.1). Create faculties, assign a Dean, and group
 * departments under them. A Dean's reports are scoped to their faculty.
 */
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
$page_title = 'Faculties';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $_SESSION['flash_message'] = 'Invalid security token.';
        $_SESSION['flash_type'] = 'error';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $name = trim($_POST['faculty_name'] ?? '');
        $code = trim($_POST['faculty_code'] ?? '');
        $dean = intval($_POST['dean_user_id'] ?? 0) ?: null;
        if ($name === '' || $code === '') {
            $_SESSION['flash_message'] = 'Faculty name and code are required.';
            $_SESSION['flash_type'] = 'error';
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO faculties (faculty_name, faculty_code, dean_user_id) VALUES (?,?,?)");
            mysqli_stmt_bind_param($stmt, "ssi", $name, $code, $dean);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['flash_message'] = $ok ? 'Faculty created.' : 'Could not create faculty (name or code may already exist).';
            $_SESSION['flash_type'] = $ok ? 'success' : 'error';
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $fid = intval($_POST['faculty_id'] ?? 0);
        // Detach its departments, then delete.
        $u = mysqli_prepare($conn, "UPDATE department SET faculty_id = NULL WHERE faculty_id = ?");
        mysqli_stmt_bind_param($u, "i", $fid);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);
        $d = mysqli_prepare($conn, "DELETE FROM faculties WHERE t_id = ?");
        mysqli_stmt_bind_param($d, "i", $fid);
        mysqli_stmt_execute($d);
        mysqli_stmt_close($d);
        $_SESSION['flash_message'] = 'Faculty deleted.';
        $_SESSION['flash_type'] = 'success';
    }
    header("Location: index.php");
    exit();
}

$deans = [];
$rd = mysqli_query($conn, "SELECT user_id, f_name, l_name FROM user_details WHERE role_id = " . ROLE_DEAN . " AND is_active = 1 ORDER BY l_name, f_name");
while ($row = mysqli_fetch_assoc($rd)) $deans[] = $row;

$faculties = [];
$rf = mysqli_query($conn, "SELECT f.t_id, f.faculty_name, f.faculty_code,
        TRIM(CONCAT(COALESCE(u.f_name,''),' ',COALESCE(u.l_name,''))) AS dean_name,
        (SELECT COUNT(*) FROM department d WHERE d.faculty_id = f.t_id) AS dept_count
    FROM faculties f LEFT JOIN user_details u ON u.user_id = f.dean_user_id
    ORDER BY f.faculty_name");
while ($row = mysqli_fetch_assoc($rf)) $faculties[] = $row;

require_once '../../includes/header.php';
?>
<style>
.card{max-width:1000px;margin:0 auto 22px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:26px}
.btn{padding:9px 18px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
.btn-secondary{background:#6c757d;color:#fff}
.btn-danger{background:#dc3545;color:#fff}
.btn-sm{padding:5px 12px;font-size:13px}
.form-input,.form-select{padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
table{width:100%;border-collapse:collapse;font-size:14px}
th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0}
td{padding:11px 12px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.muted{color:#888;font-size:13px}
</style>
<div class="page-header"><h1>Faculties</h1><p>Group departments under faculties and assign a Dean to each.</p></div>
<div class="card">
    <h3 style="margin-top:0">Add Faculty</h3>
    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <?php csrf_token_input(); ?>
        <input type="hidden" name="action" value="create">
        <input type="text" name="faculty_name" class="form-input" style="flex:2;min-width:220px" placeholder="Faculty name (e.g. Maritime Studies)" required>
        <input type="text" name="faculty_code" class="form-input" style="flex:1;min-width:120px" placeholder="Code (e.g. FMS)" required>
        <select name="dean_user_id" class="form-select" style="flex:1;min-width:180px">
            <option value="0">— Dean (optional) —</option>
            <?php foreach ($deans as $d): ?>
                <option value="<?php echo (int)$d['user_id']; ?>"><?php echo htmlspecialchars(trim($d['f_name'].' '.$d['l_name'])); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Add</button>
    </form>
    <?php if (empty($deans)): ?><p class="muted" style="margin-top:10px">Tip: create users with the <strong>Dean</strong> role first, then assign them here.</p><?php endif; ?>
</div>
<div class="card">
    <?php if (empty($faculties)): ?>
        <p class="muted">No faculties yet.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Faculty</th><th>Code</th><th>Dean</th><th>Departments</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($faculties as $f): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($f['faculty_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($f['faculty_code']); ?></td>
                    <td><?php echo $f['dean_name'] !== '' ? htmlspecialchars($f['dean_name']) : '<span class="muted">— none —</span>'; ?></td>
                    <td><?php echo (int)$f['dept_count']; ?></td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="edit.php?id=<?php echo (int)$f['t_id']; ?>" class="btn btn-secondary btn-sm">Edit / Departments</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this faculty? Its departments will be unlinked.');">
                            <?php csrf_token_input(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="faculty_id" value="<?php echo (int)$f['t_id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once '../../includes/footer.php'; ?>
