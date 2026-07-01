<?php
/**
 * Admin — Bulk Lecturer Import via CSV/Excel
 *
 * Unlike the secretary importer, an admin is not tied to a single department,
 * so the admin chooses the target department on the upload form and that choice
 * is carried through preview -> confirm.
 */
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/user_helpers.php';
require_once '../../includes/import_helpers.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_ADMIN) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type']    = 'error';
    header("Location: ../../login.php");
    exit();
}

/* -----------------------------------------------------------------------
 * Step 0 — Template download
 * --------------------------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'template') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lecturer_import_template.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['first_name', 'last_name', 'email']);
    fputcsv($out, ['John', 'Doe', 'john.doe@example.com']);
    fclose($out);
    exit();
}

/* -----------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */
function email_exists_lec(mysqli $conn, string $email): bool {
    $stmt = mysqli_prepare($conn, "SELECT user_id FROM user_details WHERE email=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $found = mysqli_stmt_get_result($stmt)->num_rows > 0;
    mysqli_stmt_close($stmt);
    return $found;
}

function dept_exists_admin(mysqli $conn, int $dept_id): bool {
    $stmt = mysqli_prepare($conn, "SELECT t_id FROM department WHERE t_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $dept_id);
    mysqli_stmt_execute($stmt);
    $found = mysqli_stmt_get_result($stmt)->num_rows > 0;
    mysqli_stmt_close($stmt);
    return $found;
}

function dept_name_admin(mysqli $conn, int $dept_id): string {
    $stmt = mysqli_prepare($conn, "SELECT dep_name FROM department WHERE t_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $dept_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $name = '';
    if ($row = mysqli_fetch_assoc($res)) {
        $name = (string) $row['dep_name'];
    }
    mysqli_stmt_close($stmt);
    return $name;
}

/* Departments list for the upload form. */
$departments = [];
$dept_result = mysqli_query($conn, "SELECT t_id, dep_name FROM department ORDER BY dep_name");
if ($dept_result) {
    while ($drow = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $drow;
    }
}

/* -----------------------------------------------------------------------
 * Step 3 — Confirm import
 * --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    if (!validate_csrf_token()) {
        $_SESSION['flash_message'] = 'Invalid security token.';
        $_SESSION['flash_type']    = 'error';
        header("Location: import_lecturers.php");
        exit();
    }

    $preview = $_SESSION['admin_import_preview_lecturers'] ?? [];
    $dept_id = (int) ($_SESSION['admin_import_dept_lecturers'] ?? 0);

    if (empty($preview)) {
        $_SESSION['flash_message'] = 'No import data found. Please upload a file first.';
        $_SESSION['flash_type']    = 'error';
        header("Location: import_lecturers.php");
        exit();
    }

    if ($dept_id <= 0 || !dept_exists_admin($conn, $dept_id)) {
        $_SESSION['flash_message'] = 'The selected department is no longer valid. Please start again.';
        $_SESSION['flash_type']    = 'error';
        header("Location: import_lecturers.php");
        exit();
    }

    mysqli_begin_transaction($conn);
    try {
        $stmt_ins = mysqli_prepare($conn,
            "INSERT INTO user_details (role_id, f_name, l_name, email, username, password, department_id, is_active, force_password_change, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())");

        // Every imported lecturer gets the same default password (hashed once),
        // and is flagged to change it on first login.
        $hashed = password_hash(DEFAULT_IMPORT_PASSWORD, PASSWORD_DEFAULT);

        $credentials = [];
        foreach ($preview as $row) {
            $username = ces_derive_username($conn, $row['first_name'], $row['last_name']);
            $role_id  = ROLE_ADVISOR;

            mysqli_stmt_bind_param($stmt_ins, "issssi",
                $role_id, $row['first_name'], $row['last_name'],
                $row['email'], $username, $hashed, $dept_id);

            if (!mysqli_stmt_execute($stmt_ins)) {
                throw new RuntimeException("DB insert failed for {$row['email']}: " . mysqli_stmt_error($stmt_ins));
            }
            $credentials[] = [
                'name'     => $row['first_name'] . ' ' . $row['last_name'],
                'email'    => $row['email'],
                'username' => $username,
                'password' => DEFAULT_IMPORT_PASSWORD,
            ];
        }

        mysqli_stmt_close($stmt_ins);
        mysqli_commit($conn);
        unset($_SESSION['admin_import_preview_lecturers'], $_SESSION['admin_import_dept_lecturers']);

        $_SESSION['admin_import_lecturers_credentials'] = $credentials;
        header("Location: import_lecturers.php?done=1");
        exit();

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $_SESSION['flash_message'] = 'Import failed: ' . htmlspecialchars($e->getMessage());
        $_SESSION['flash_type']    = 'error';
        header("Location: import_lecturers.php");
        exit();
    }
}

/* -----------------------------------------------------------------------
 * Step 2 — Parse & preview file
 * --------------------------------------------------------------------- */
$preview_rows    = [];
$valid_count     = 0;
$error_count     = 0;
$parse_errors    = [];
$selected_dept   = 0;
$selected_dept_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'confirm') {
    $rows = [];
    if (!validate_csrf_token()) {
        $parse_errors[] = 'Invalid security token.';
    } else {
        $selected_dept = (int) ($_POST['department_id'] ?? 0);
        if ($selected_dept <= 0 || !dept_exists_admin($conn, $selected_dept)) {
            $parse_errors[] = 'Please choose a valid department to import lecturers into.';
        } else {
            try {
                $rows = import_read_upload('import_file');
            } catch (Throwable $e) {
                $parse_errors[] = $e->getMessage();
            }
        }
    }

    if (empty($parse_errors)) {
        $selected_dept_name = dept_name_admin($conn, $selected_dept);
        $col = import_header_map($rows[0]);
        $missing = array_diff(['first_name', 'last_name', 'email'], array_keys($col));
        if (!empty($missing)) {
            $parse_errors[] = 'File is missing required columns: ' . implode(', ', $missing);
        } else {
            $seen_emails = [];
            $total = count($rows);
            for ($i = 1; $i < $total; $i++) {
                if (count(array_filter(array_map('trim', $rows[$i]))) === 0) continue;

                $first_name = import_cell($rows[$i], $col, 'first_name');
                $last_name  = import_cell($rows[$i], $col, 'last_name');
                $email      = strtolower(import_cell($rows[$i], $col, 'email'));

                $row_errors = [];
                if ($first_name === '') $row_errors[] = 'First name is required';
                if ($last_name === '')  $row_errors[] = 'Last name is required';

                if ($email === '') {
                    $row_errors[] = 'Email is required';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $row_errors[] = 'Invalid email format';
                } elseif (isset($seen_emails[$email])) {
                    $row_errors[] = 'Duplicate email in this file';
                } elseif (email_exists_lec($conn, $email)) {
                    $row_errors[] = 'Email already registered in the system';
                }

                $is_valid = empty($row_errors);
                if ($is_valid) { $valid_count++; $seen_emails[$email] = true; }
                else $error_count++;

                $preview_rows[] = [
                    'row'        => $i + 1,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'valid'      => $is_valid,
                    'errors'     => $row_errors,
                ];
            }

            if (empty($preview_rows)) {
                $parse_errors[] = 'The file contains no data rows.';
            } else {
                $_SESSION['admin_import_preview_lecturers'] = array_values(array_filter(
                    array_map(function($r) { return $r['valid'] ? [
                        'first_name' => $r['first_name'],
                        'last_name'  => $r['last_name'],
                        'email'      => $r['email'],
                    ] : null; }, $preview_rows)
                ));
                $_SESSION['admin_import_dept_lecturers'] = $selected_dept;
            }
        }
    }
}

/* -----------------------------------------------------------------------
 * Retrieve credentials after redirect
 * --------------------------------------------------------------------- */
$imported_credentials = null;
if (isset($_GET['done']) && !empty($_SESSION['admin_import_lecturers_credentials'])) {
    $imported_credentials = $_SESSION['admin_import_lecturers_credentials'];
    unset($_SESSION['admin_import_lecturers_credentials']);
}

$page_title = 'Bulk Lecturer Import';
require_once '../../includes/header.php';
?>
<style>
.import-container{max-width:960px;margin:0 auto}
.card{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:28px;margin-bottom:24px}
.card-title{font-size:18px;font-weight:600;color:#333;margin:0 0 6px}
.card-subtitle{font-size:13px;color:#666;margin:0 0 20px}
.btn{padding:10px 22px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:8px;margin-bottom:6px;line-height:1.4}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
.btn-success{background:#28a745;color:#fff}
.btn-secondary{background:#6c757d;color:#fff}
.btn-sm{padding:6px 14px;font-size:13px}.btn:hover{opacity:.9}
.form-group{margin-bottom:18px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px;color:#444}
.form-input{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px;box-sizing:border-box}
.alert{padding:14px 18px;border-radius:7px;margin-bottom:20px;font-size:14px}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24}
.alert-info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460}
.alert-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724}
.alert ul{margin:8px 0 0 18px;padding:0}
.template-hint{background:#f8f9fa;border:1px dashed #ced4da;border-radius:6px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#555}
.template-hint code{background:#e9ecef;padding:2px 6px;border-radius:3px;font-family:monospace}
.stats-bar{display:flex;gap:16px;margin-bottom:18px;flex-wrap:wrap}
.stat-chip{padding:8px 16px;border-radius:20px;font-size:13px;font-weight:600}
.stat-valid{background:#d4edda;color:#155724}
.stat-error{background:#f8d7da;color:#721c24}
.stat-total{background:#cce5ff;color:#004085}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
thead th{background:#f1f3f5;padding:10px 12px;text-align:left;font-weight:600;color:#555;white-space:nowrap;border-bottom:2px solid #dee2e6}
tbody td{padding:9px 12px;border-bottom:1px solid #f0f0f0;vertical-align:top}
tbody tr:hover{background:#fafafa}
tr.row-valid{border-left:3px solid #28a745}
tr.row-error{border-left:3px solid #dc3545}
.badge-valid{background:#d4edda;color:#155724;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.badge-error{background:#f8d7da;color:#721c24;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.err-list{margin:4px 0 0 0;padding:0 0 0 14px;color:#dc3545;font-size:12px}
.err-list li{margin-bottom:2px}
.cred-warning{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px 18px;margin-bottom:16px;font-size:13px;color:#856404}
.dept-tag{background:#e7e3ff;color:#4b3f8f;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
</style>

<div class="page-header">
    <h1>Bulk Lecturer Import</h1>
    <p>Upload a CSV or Excel file to add multiple lecturers at once</p>
</div>

<div class="import-container">

<?php if ($imported_credentials !== null): ?>

<div class="alert alert-success">
    <strong><?php echo count($imported_credentials); ?> lecturer(s) imported successfully.</strong>
    <br><a href="list.php" style="color:inherit;font-weight:600">View All Lecturers →</a>
</div>

<div class="card">
    <p class="card-title">Generated Credentials</p>
    <div class="cred-warning">
        <strong>Important:</strong> Every lecturer was given the default password <code><?php echo htmlspecialchars(DEFAULT_IMPORT_PASSWORD); ?></code>
        and will be prompted to change it on first login. Share each lecturer's username with them.
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Username</th><th>Default Password</th></tr></thead>
            <tbody>
            <?php foreach ($imported_credentials as $c): ?>
            <tr>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td><?php echo htmlspecialchars($c['email']); ?></td>
                <td><code><?php echo htmlspecialchars($c['username']); ?></code></td>
                <td><code><?php echo htmlspecialchars($c['password']); ?></code></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <br>
    <button onclick="printCredentials()" class="btn btn-primary">Print Credentials</button>
    <a href="list.php" class="btn btn-secondary">Go to Lecturers List</a>
</div>
<script>
function printCredentials(){
    var tbl=document.querySelector('.table-wrap').innerHTML;
    var w=window.open('','_blank');
    w.document.write('<html><head><title>Lecturer Credentials</title><style>table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:8px;font-size:13px}</style></head><body>'+tbl+'</body></html>');
    w.print();w.close();
}
</script>

<?php elseif (!empty($preview_rows)): ?>

<?php if (!empty($parse_errors)): ?>
<div class="alert alert-error"><strong>File Error:</strong>
<ul><?php foreach ($parse_errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
    <p class="card-title">Import Preview</p>
    <p class="card-subtitle">
        Review before confirming. Only valid rows will be imported. Credentials will be generated automatically.
        <?php if ($selected_dept_name !== ''): ?>
        <br>Lecturers will be added to: <span class="dept-tag"><?php echo htmlspecialchars($selected_dept_name); ?></span>
        <?php endif; ?>
    </p>
    <div class="stats-bar">
        <span class="stat-chip stat-total">Total: <?php echo count($preview_rows); ?></span>
        <span class="stat-chip stat-valid">Valid: <?php echo $valid_count; ?></span>
        <span class="stat-chip stat-error">Errors: <?php echo $error_count; ?></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Row</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($preview_rows as $pr): ?>
            <tr class="<?php echo $pr['valid'] ? 'row-valid' : 'row-error'; ?>">
                <td><?php echo (int)$pr['row']; ?></td>
                <td><?php echo htmlspecialchars($pr['first_name']); ?></td>
                <td><?php echo htmlspecialchars($pr['last_name']); ?></td>
                <td><?php echo htmlspecialchars($pr['email']); ?></td>
                <td>
                    <?php if ($pr['valid']): ?>
                        <span class="badge-valid">Valid</span>
                    <?php else: ?>
                        <span class="badge-error">Error</span>
                        <ul class="err-list"><?php foreach ($pr['errors'] as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($valid_count > 0): ?>
    <br>
    <form method="POST">
        <?php csrf_token_input(); ?>
        <input type="hidden" name="action" value="confirm">
        <button type="submit" class="btn btn-success">Confirm Import (<?php echo $valid_count; ?> lecturer<?php echo $valid_count !== 1 ? 's' : ''; ?>)</button>
        <a href="import_lecturers.php" class="btn btn-secondary">Cancel</a>
        <?php if ($error_count > 0): ?>
        <p style="margin-top:10px;font-size:13px;color:#856404;background:#fff3cd;padding:8px 12px;border-radius:5px;display:inline-block">
            <?php echo $error_count; ?> row(s) with errors will be skipped.
        </p>
        <?php endif; ?>
    </form>
    <?php else: ?>
    <div class="alert alert-error" style="margin-top:16px">No valid rows found. Please fix the errors and try again.</div>
    <a href="import_lecturers.php" class="btn btn-primary" style="margin-top:8px">Upload Again</a>
    <?php endif; ?>
</div>

<?php else: ?>

<?php if (!empty($_SESSION['flash_message'])): ?>
<div class="alert alert-<?php echo $_SESSION['flash_type'] === 'error' ? 'error' : 'info'; ?>">
    <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
</div>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

<?php if (!empty($parse_errors)): ?>
<div class="alert alert-error"><strong>Error:</strong>
<ul><?php foreach ($parse_errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
    <p class="card-title">Upload CSV or Excel File</p>
    <p class="card-subtitle">Import multiple lecturers by uploading a CSV (.csv) or Excel (.xlsx) file. A username is generated for each.</p>

    <div class="template-hint">
        <strong>Required columns:</strong>
        <code>first_name</code>, <code>last_name</code>, <code>email</code>
        <span style="color:#888">(any order — matched by column heading)</span>
        <br><br>
        <strong>Notes:</strong>
        <ul style="margin:6px 0 0 18px;padding:0;font-size:13px">
            <li>Choose the department these lecturers belong to.</li>
            <li>Each email must be unique and not already registered in the system.</li>
            <li>Every lecturer is given the default password <code><?php echo htmlspecialchars(DEFAULT_IMPORT_PASSWORD); ?></code> and must change it on first login.</li>
            <li>Accepted files: <strong>.csv</strong> and <strong>.xlsx</strong>.</li>
        </ul>
        <br>
        <a href="import_lecturers.php?action=template" class="btn btn-outline" style="background:#fff;border:2px solid #667eea;color:#667eea;padding:6px 14px;border-radius:5px;text-decoration:none;font-size:13px;font-weight:500">
            Download Template CSV
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <?php csrf_token_input(); ?>
        <div class="form-group">
            <label class="form-label" for="department_id">Target Department</label>
            <select name="department_id" id="department_id" class="form-input" required>
                <option value="">— Select a department —</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?php echo (int)$d['t_id']; ?>"<?php echo ($selected_dept === (int)$d['t_id']) ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($d['dep_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="import_file">Select CSV or Excel File</label>
            <input type="file" name="import_file" id="import_file" class="form-input" accept=".csv,.xlsx" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload & Preview</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
