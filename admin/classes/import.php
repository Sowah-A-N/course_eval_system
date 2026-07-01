<?php
/**
 * Admin — Bulk Class Import via CSV / Excel
 *
 * Unlike the secretary, the admin is not tied to a single department, so the
 * target department is chosen on the upload form and carried through the
 * preview → confirm steps via the session.
 */
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
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
    header('Content-Disposition: attachment; filename="class_import_template.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['class_name', 'class_code', 'level_id']);
    fputcsv($out, ['Year 1 Group A', 'Y1A', '1']);
    fclose($out);
    exit();
}

/* -----------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */
function get_levels_map(mysqli $conn): array {
    $res = mysqli_query($conn, "SELECT t_id, level_name FROM level ORDER BY level_number");
    $map = [];
    while ($row = mysqli_fetch_assoc($res)) $map[(int)$row['t_id']] = $row['level_name'];
    return $map;
}

function get_departments(mysqli $conn): array {
    $res = mysqli_query($conn, "SELECT t_id, dep_name FROM department ORDER BY dep_name");
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    return $rows;
}

function department_exists(mysqli $conn, int $dept_id): bool {
    $stmt = mysqli_prepare($conn, "SELECT t_id FROM department WHERE t_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $dept_id);
    mysqli_stmt_execute($stmt);
    $found = mysqli_stmt_get_result($stmt)->num_rows > 0;
    mysqli_stmt_close($stmt);
    return $found;
}

function department_name(mysqli $conn, int $dept_id): string {
    $stmt = mysqli_prepare($conn, "SELECT dep_name FROM department WHERE t_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $dept_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ? $row['dep_name'] : '';
}

function class_code_exists(mysqli $conn, string $code, int $dept_id): bool {
    $stmt = mysqli_prepare($conn, "SELECT t_id FROM classes WHERE class_code=? AND department_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "si", $code, $dept_id);
    mysqli_stmt_execute($stmt);
    $found = mysqli_stmt_get_result($stmt)->num_rows > 0;
    mysqli_stmt_close($stmt);
    return $found;
}

/* -----------------------------------------------------------------------
 * Step 3 — Confirm import
 * --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    if (!validate_csrf_token()) {
        $_SESSION['flash_message'] = 'Invalid security token.';
        $_SESSION['flash_type']    = 'error';
        header("Location: import.php");
        exit();
    }

    $preview       = $_SESSION['admin_import_preview_classes'] ?? [];
    $department_id = (int) ($_SESSION['admin_import_dept_classes'] ?? 0);
    if (empty($preview) || $department_id <= 0) {
        $_SESSION['flash_message'] = 'No import data found. Please upload a file first.';
        $_SESSION['flash_type']    = 'error';
        header("Location: import.php");
        exit();
    }

    mysqli_begin_transaction($conn);
    try {
        $stmt_ins = mysqli_prepare($conn,
            "INSERT INTO classes (class_name, class_code, department_id, level_id, created_at)
             VALUES (?, ?, ?, ?, NOW())");

        $inserted = 0;
        foreach ($preview as $row) {
            mysqli_stmt_bind_param($stmt_ins, "ssii",
                $row['class_name'], $row['class_code'], $department_id, $row['level_id']);
            if (!mysqli_stmt_execute($stmt_ins)) {
                throw new RuntimeException("DB insert failed for {$row['class_code']}: " . mysqli_stmt_error($stmt_ins));
            }
            $inserted++;
        }

        mysqli_stmt_close($stmt_ins);
        mysqli_commit($conn);
        unset($_SESSION['admin_import_preview_classes'], $_SESSION['admin_import_dept_classes']);
        $_SESSION['flash_message'] = "$inserted class(es) imported successfully.";
        $_SESSION['flash_type']    = 'success';
        header("Location: import.php?done=1");
        exit();

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $_SESSION['flash_message'] = 'Import failed: ' . htmlspecialchars($e->getMessage());
        $_SESSION['flash_type']    = 'error';
        header("Location: import.php");
        exit();
    }
}

/* -----------------------------------------------------------------------
 * Step 2 — Parse & preview file
 * --------------------------------------------------------------------- */
$preview_rows      = [];
$valid_count       = 0;
$error_count       = 0;
$parse_errors      = [];
$selected_dept_id  = 0;
$selected_dept_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'confirm') {
    $rows = [];
    if (!validate_csrf_token()) {
        $parse_errors[] = 'Invalid security token.';
    } else {
        $selected_dept_id = (int) ($_POST['department_id'] ?? 0);
        if ($selected_dept_id <= 0 || !department_exists($conn, $selected_dept_id)) {
            $parse_errors[] = 'Please select a valid department.';
        }
        try {
            $rows = import_read_upload('import_file');
        } catch (RuntimeException $e) {
            $parse_errors[] = $e->getMessage();
        } catch (Throwable $e) {
            $parse_errors[] = $e->getMessage();
        }
    }

    if (empty($parse_errors)) {
        $selected_dept_name = department_name($conn, $selected_dept_id);
        $levels = get_levels_map($conn);
        $col = import_header_map($rows[0]);
        $missing = array_diff(['class_name', 'class_code', 'level_id'], array_keys($col));
        if (!empty($missing)) {
            $parse_errors[] = 'File is missing required columns: ' . implode(', ', $missing);
        } else {
            $seen_codes = [];
            $total = count($rows);
            for ($i = 1; $i < $total; $i++) {
                if (count(array_filter(array_map('trim', $rows[$i]))) === 0) continue;

                $class_name   = import_cell($rows[$i], $col, 'class_name');
                $class_code   = import_cell($rows[$i], $col, 'class_code');
                $level_id_raw = import_cell($rows[$i], $col, 'level_id');

                $row_errors = [];
                if ($class_name === '') $row_errors[] = 'Class name is required';
                if ($class_code === '') {
                    $row_errors[] = 'Class code is required';
                } elseif (isset($seen_codes[strtolower($class_code)])) {
                    $row_errors[] = 'Duplicate class code in this file';
                } elseif (class_code_exists($conn, $class_code, $selected_dept_id)) {
                    $row_errors[] = 'Class code already exists in this department';
                }

                $level_id = filter_var($level_id_raw, FILTER_VALIDATE_INT);
                if ($level_id === false || $level_id <= 0) {
                    $row_errors[] = 'level_id must be a positive integer';
                } elseif (!isset($levels[$level_id])) {
                    $row_errors[] = "level_id $level_id does not exist";
                }

                $is_valid = empty($row_errors);
                if ($is_valid) { $valid_count++; $seen_codes[strtolower($class_code)] = true; }
                else $error_count++;

                $preview_rows[] = [
                    'row'        => $i + 1,
                    'class_name' => $class_name,
                    'class_code' => $class_code,
                    'level_id'   => $level_id !== false ? $level_id : 0,
                    'level_name' => ($level_id && isset($levels[$level_id])) ? $levels[$level_id] : $level_id_raw,
                    'valid'      => $is_valid,
                    'errors'     => $row_errors,
                ];
            }

            if (empty($preview_rows)) {
                $parse_errors[] = 'The file contains no data rows.';
            } else {
                $_SESSION['admin_import_preview_classes'] = array_values(array_filter(
                    array_map(function($r) { return $r['valid'] ? [
                        'class_name' => $r['class_name'],
                        'class_code' => $r['class_code'],
                        'level_id'   => $r['level_id'],
                    ] : null; }, $preview_rows)
                ));
                $_SESSION['admin_import_dept_classes'] = $selected_dept_id;
            }
        }
    }
}

$page_title = 'Bulk Class Import';
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
</style>

<div class="page-header">
    <h1>Bulk Class Import</h1>
    <p>Upload a CSV or Excel file to add multiple classes at once</p>
</div>

<div class="import-container">

<?php if (!empty($_SESSION['flash_message'])): ?>
<div class="alert alert-<?php echo $_SESSION['flash_type'] === 'error' ? 'error' : 'info'; ?>">
    <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
    <?php if (isset($_GET['done'])): ?>
    <br><a href="list.php" style="color:inherit;font-weight:600">View All Classes →</a>
    <?php endif; ?>
</div>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

<?php if (!empty($preview_rows)): ?>

<?php if (!empty($parse_errors)): ?>
<div class="alert alert-error"><strong>File Error:</strong>
<ul><?php foreach ($parse_errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
    <p class="card-title">Import Preview</p>
    <p class="card-subtitle">Review before confirming. Only valid rows will be imported.</p>
    <div class="alert alert-info" style="margin-bottom:18px">
        Classes will be added to department: <strong><?php echo htmlspecialchars($selected_dept_name); ?></strong>
    </div>
    <div class="stats-bar">
        <span class="stat-chip stat-total">Total: <?php echo count($preview_rows); ?></span>
        <span class="stat-chip stat-valid">Valid: <?php echo $valid_count; ?></span>
        <span class="stat-chip stat-error">Errors: <?php echo $error_count; ?></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Row</th><th>Class Name</th><th>Class Code</th><th>Level</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($preview_rows as $pr): ?>
            <tr class="<?php echo $pr['valid'] ? 'row-valid' : 'row-error'; ?>">
                <td><?php echo (int)$pr['row']; ?></td>
                <td><?php echo htmlspecialchars($pr['class_name']); ?></td>
                <td><?php echo htmlspecialchars($pr['class_code']); ?></td>
                <td><?php echo htmlspecialchars($pr['level_name']); ?></td>
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
        <button type="submit" class="btn btn-success">Confirm Import (<?php echo $valid_count; ?> class<?php echo $valid_count !== 1 ? 'es' : ''; ?>)</button>
        <a href="import.php" class="btn btn-secondary">Cancel</a>
        <?php if ($error_count > 0): ?>
        <p style="margin-top:10px;font-size:13px;color:#856404;background:#fff3cd;padding:8px 12px;border-radius:5px;display:inline-block">
            <?php echo $error_count; ?> row(s) with errors will be skipped.
        </p>
        <?php endif; ?>
    </form>
    <?php else: ?>
    <div class="alert alert-error" style="margin-top:16px">No valid rows found. Please fix the errors and try again.</div>
    <a href="import.php" class="btn btn-primary" style="margin-top:8px">Upload Again</a>
    <?php endif; ?>
</div>

<?php else: ?>

<?php if (!empty($parse_errors)): ?>
<div class="alert alert-error"><strong>Error:</strong>
<ul><?php foreach ($parse_errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
    <p class="card-title">Upload CSV or Excel File</p>
    <p class="card-subtitle">Import multiple classes by uploading a CSV (.csv) or Excel (.xlsx) file.</p>

    <div class="template-hint">
        <strong>Required columns:</strong>
        <code>class_name</code>, <code>class_code</code>, <code>level_id</code>
        <span style="color:#888">(any order — matched by column heading)</span>
        <br><br>
        <strong>Notes:</strong>
        <ul style="margin:6px 0 0 18px;padding:0;font-size:13px">
            <li><code>class_code</code> must be unique within the selected department.</li>
            <li><code>level_id</code> must match a valid Level ID — see the reference table below.</li>
        </ul>
        <br>
        <a href="import.php?action=template" class="btn btn-outline" style="background:#fff;border:2px solid #667eea;color:#667eea;padding:6px 14px;border-radius:5px;text-decoration:none;font-size:13px;font-weight:500">
            Download Template CSV
        </a>
    </div>

    <!-- Level reference -->
    <details style="margin-bottom:18px">
        <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#667eea">View Level ID Reference</summary>
        <table style="margin-top:8px;width:auto;min-width:260px">
            <thead><tr><th>Level ID</th><th>Level Name</th></tr></thead>
            <tbody>
            <?php
            $lvls = get_levels_map($conn);
            foreach ($lvls as $id => $name): ?>
            <tr><td><?php echo $id; ?></td><td><?php echo htmlspecialchars($name); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </details>

    <form method="POST" enctype="multipart/form-data">
        <?php csrf_token_input(); ?>
        <div class="form-group">
            <label class="form-label" for="department_id">Target Department</label>
            <select name="department_id" id="department_id" class="form-input" required>
                <option value="">— Select a department —</option>
                <?php foreach (get_departments($conn) as $dept): ?>
                <option value="<?php echo (int)$dept['t_id']; ?>"<?php echo ($selected_dept_id === (int)$dept['t_id']) ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($dept['dep_name']); ?>
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
