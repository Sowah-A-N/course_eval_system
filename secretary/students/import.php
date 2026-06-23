<?php
/**
 * Secretary — Bulk Student Import via CSV
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

$department_id = (int) $_SESSION['department_id'];

/* -----------------------------------------------------------------------
 * Step 0 — Template download
 * --------------------------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'template') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['first_name', 'last_name', 'email', 'level', 'class']);
    fputcsv($out, ['Jane', 'Doe', 'jane.doe@example.com', '100', 'BIT28']);
    fclose($out);
    exit();
}

/* -----------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */

/** Map level_number (100,200,...) → ['id'=>t_id,'name'=>level_name] */
function get_levels_by_number(mysqli $conn): array {
    $res = mysqli_query($conn, "SELECT t_id, level_name, level_number FROM level ORDER BY level_number");
    $map = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $map[(int)$row['level_number']] = ['id' => (int)$row['t_id'], 'name' => $row['level_name']];
    }
    return $map;
}

/** Map UPPERCASE class_code → ['id'=>t_id,'name'=>class_name] for this department */
function get_classes_by_code(mysqli $conn, int $dept_id): array {
    $stmt = mysqli_prepare($conn,
        "SELECT t_id, class_name, class_code FROM classes WHERE department_id = ? ORDER BY class_name");
    mysqli_stmt_bind_param($stmt, "i", $dept_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $map = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $map[strtoupper($row['class_code'])] = ['id' => (int)$row['t_id'], 'name' => $row['class_name']];
    }
    mysqli_stmt_close($stmt);
    return $map;
}

function email_exists(mysqli $conn, string $email): bool {
    $stmt = mysqli_prepare($conn, "SELECT user_id FROM user_details WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $found = mysqli_stmt_get_result($stmt)->num_rows > 0;
    mysqli_stmt_close($stmt);
    return $found;
}

/** Generate a random unique_id that doesn't already exist in user_details */
function generate_unique_student_id(mysqli $conn): string {
    do {
        $uid = strtoupper(bin2hex(random_bytes(5))); // 10-char hex, e.g. A3F2C91B4E
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM user_details WHERE unique_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $uid);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->num_rows > 0;
        mysqli_stmt_close($stmt);
    } while ($exists);
    return $uid;
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

    $preview = $_SESSION['import_preview'] ?? [];
    if (empty($preview)) {
        $_SESSION['flash_message'] = 'No import data found. Please upload a CSV first.';
        $_SESSION['flash_type']    = 'error';
        header("Location: import.php");
        exit();
    }

    $role_student = ROLE_STUDENT;
    $credentials  = [];

    mysqli_begin_transaction($conn);
    try {
        $stmt_ins = mysqli_prepare($conn,
            "INSERT INTO user_details
             (username, password, email, f_name, l_name, unique_id, role_id,
              department_id, level_id, class_id, is_active, force_password_change, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,1,1,NOW())"
        );

        foreach ($preview as $row) {
            $unique_id     = generate_unique_student_id($conn);
            $username      = ces_derive_username($conn, $row['f_name'], $row['l_name']);
            $temp_password = ces_generate_temp_password();
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

            mysqli_stmt_bind_param($stmt_ins, "ssssssiiii",
                $username,
                $password_hash,
                $row['email'],
                $row['f_name'],
                $row['l_name'],
                $unique_id,
                $role_student,
                $department_id,
                $row['level_id'],
                $row['class_id']
            );

            if (!mysqli_stmt_execute($stmt_ins)) {
                throw new RuntimeException("DB insert failed for {$row['email']}: " . mysqli_stmt_error($stmt_ins));
            }

            $credentials[] = [
                'name'     => $row['f_name'] . ' ' . $row['l_name'],
                'email'    => $row['email'],
                'username' => $username,
                'password' => $temp_password,
            ];
        }

        mysqli_stmt_close($stmt_ins);
        mysqli_commit($conn);
        unset($_SESSION['import_preview']);
        $_SESSION['import_credentials'] = $credentials;
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
 * Step 2 — Parse & preview CSV
 * --------------------------------------------------------------------- */
$preview_rows = [];
$valid_count  = 0;
$error_count  = 0;
$parse_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'confirm') {
    if (!validate_csrf_token()) {
        $parse_errors[] = 'Invalid security token.';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $parse_errors[] = 'Please select a valid CSV file to upload.';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $ext  = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file);
        $allowed_mime = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($mime, $allowed_mime, true) && $ext !== 'csv') {
            $parse_errors[] = 'File must be a CSV file.';
        } else {
            $level_map = get_levels_by_number($conn);
            $class_map = get_classes_by_code($conn, $department_id);

            $handle = fopen($file, 'r');
            $header = fgetcsv($handle);
            if ($header !== false) {
                $header_lower = array_map('strtolower', array_map('trim', $header));
                $missing = array_diff(['first_name', 'last_name', 'email', 'level', 'class'], $header_lower);
                if (!empty($missing)) $parse_errors[] = 'CSV is missing required columns: ' . implode(', ', $missing);
            } else {
                $parse_errors[] = 'Could not read the CSV file.';
            }

            if (empty($parse_errors)) {
                $seen_emails = [];
                $row_num     = 1;

                while (($csv_row = fgetcsv($handle)) !== false) {
                    $row_num++;
                    if (count(array_filter(array_map('trim', $csv_row))) === 0) continue;

                    [$f_name, $l_name, $email, $level_raw, $class_raw] =
                        array_pad(array_map('trim', $csv_row), 5, '');

                    $row_errors = [];

                    if ($f_name === '') $row_errors[] = 'First name is required';
                    if ($l_name === '') $row_errors[] = 'Last name is required';

                    $email = strtolower($email);
                    if ($email === '') {
                        $row_errors[] = 'Email is required';
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $row_errors[] = 'Invalid email format';
                    } elseif (isset($seen_emails[$email])) {
                        $row_errors[] = 'Duplicate email in this file';
                    } elseif (email_exists($conn, $email)) {
                        $row_errors[] = 'Email already registered in the system';
                    }

                    // Level: expect 100, 200, 300, 400 etc.
                    $level_num = filter_var($level_raw, FILTER_VALIDATE_INT);
                    $level_id  = null;
                    $level_name = '';
                    if ($level_num === false || $level_num <= 0) {
                        $row_errors[] = 'Level must be a number like 100, 200, 300 or 400';
                    } elseif (!isset($level_map[$level_num])) {
                        $known = implode(', ', array_keys($level_map));
                        $row_errors[] = "Level $level_num not recognised. Valid levels: $known";
                    } else {
                        $level_id   = $level_map[$level_num]['id'];
                        $level_name = $level_map[$level_num]['name'];
                    }

                    // Class: expect code like BIT28
                    $class_code_upper = strtoupper($class_raw);
                    $class_id   = null;
                    $class_name = '';
                    if ($class_raw === '') {
                        $row_errors[] = 'Class is required (e.g. BIT28)';
                    } elseif (!isset($class_map[$class_code_upper])) {
                        $row_errors[] = "Class \"$class_raw\" not found in your department";
                    } else {
                        $class_id   = $class_map[$class_code_upper]['id'];
                        $class_name = $class_map[$class_code_upper]['name'];
                    }

                    $is_valid = empty($row_errors);
                    if ($is_valid) { $valid_count++; $seen_emails[$email] = true; }
                    else $error_count++;

                    $preview_rows[] = [
                        'row'        => $row_num,
                        'f_name'     => $f_name,
                        'l_name'     => $l_name,
                        'email'      => $email,
                        'level_raw'  => $level_raw,
                        'level_name' => $level_name ?: $level_raw,
                        'class_raw'  => $class_raw,
                        'class_name' => $class_name ?: $class_raw,
                        'level_id'   => $level_id,
                        'class_id'   => $class_id,
                        'valid'      => $is_valid,
                        'errors'     => $row_errors,
                    ];
                }
                fclose($handle);

                if (empty($preview_rows)) {
                    $parse_errors[] = 'The CSV file contains no data rows.';
                } else {
                    $_SESSION['import_preview'] = array_values(array_filter(
                        array_map(fn($r) => $r['valid'] ? [
                            'f_name'   => $r['f_name'],
                            'l_name'   => $r['l_name'],
                            'email'    => $r['email'],
                            'level_id' => $r['level_id'],
                            'class_id' => $r['class_id'],
                        ] : null, $preview_rows)
                    ));
                }
            } else {
                fclose($handle);
            }
        }
    }
}

/* -----------------------------------------------------------------------
 * Step 4 — Results (?done=1)
 * --------------------------------------------------------------------- */
$import_credentials = null;
if (isset($_GET['done']) && !empty($_SESSION['import_credentials'])) {
    $import_credentials = $_SESSION['import_credentials'];
    unset($_SESSION['import_credentials']);
}

$page_title = 'Bulk Student Import';
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
.btn-outline{background:#fff;border:2px solid #667eea;color:#667eea}
.btn-sm{padding:6px 14px;font-size:13px}
.btn:hover{opacity:.9}
.form-group{margin-bottom:18px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px;color:#444}
.form-input{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px;box-sizing:border-box}
.form-input:focus{outline:none;border-color:#667eea}
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
.creds-card{background:#f0fdf4;border:2px solid #86efac;border-radius:10px;padding:26px;margin-bottom:24px}
.creds-card h2{color:#166534;margin:0 0 6px;font-size:20px}
.creds-card p{color:#166534;margin:0 0 16px;font-size:14px}
.warn-note{font-size:13px;color:#92400e;background:#fef3c7;border:1px solid #fcd34d;padding:10px 14px;border-radius:6px;margin-bottom:16px}
</style>

<div class="page-header">
    <h1>Bulk Student Import</h1>
    <p>Upload a CSV file to enroll multiple students at once</p>
</div>

<div class="import-container">

<?php if (!empty($_SESSION['flash_message'])): ?>
<div class="alert alert-<?php echo $_SESSION['flash_type'] === 'error' ? 'error' : 'info'; ?>">
    <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
</div>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

<?php if ($import_credentials !== null): ?>

<div class="creds-card">
    <h2>Import Successful</h2>
    <p><?php echo count($import_credentials); ?> student account(s) created. Share credentials below. Students must change their password on first login.</p>
    <p class="warn-note">These credentials are shown <strong>once only</strong>. Download or copy them now.</p>
    <div class="table-wrap" id="creds-table-wrap">
        <table id="creds-table">
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Username</th><th>Temp Password</th></tr>
            </thead>
            <tbody>
                <?php foreach ($import_credentials as $i => $c): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
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
    <button class="btn btn-success" onclick="downloadCredsCsv()">Download Credentials CSV</button>
    <a href="import.php" class="btn btn-primary">Import More</a>
    <a href="list.php"   class="btn btn-secondary">View All Students</a>
</div>

<script>
(function(){
    var data = <?php echo json_encode(array_map(fn($c) => [
        htmlspecialchars($c['name'],     ENT_QUOTES),
        htmlspecialchars($c['email'],    ENT_QUOTES),
        htmlspecialchars($c['username'], ENT_QUOTES),
        htmlspecialchars($c['password'], ENT_QUOTES),
    ], $import_credentials), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
    window.downloadCredsCsv = function(){
        var rows = [['Name','Email','Username','Temp Password']].concat(data);
        var csv  = rows.map(function(r){
            return r.map(function(v){ return '"'+String(v).replace(/"/g,'""')+'"'; }).join(',');
        }).join('\r\n');
        var blob = new Blob([csv],{type:'text/csv'});
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href=url; a.download='imported_student_credentials.csv';
        document.body.appendChild(a); a.click();
        setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); },1000);
    };
}());
</script>

<?php elseif (!empty($preview_rows)): ?>

<?php if (!empty($parse_errors)): ?>
<div class="alert alert-error"><strong>File Error:</strong>
<ul><?php foreach ($parse_errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
    <p class="card-title">Import Preview</p>
    <p class="card-subtitle">Review before confirming. Only valid rows will be imported. Student IDs are assigned automatically.</p>
    <div class="stats-bar">
        <span class="stat-chip stat-total">Total: <?php echo count($preview_rows); ?></span>
        <span class="stat-chip stat-valid">Valid: <?php echo $valid_count; ?></span>
        <span class="stat-chip stat-error">Errors: <?php echo $error_count; ?></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Row</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Level</th><th>Class</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($preview_rows as $pr): ?>
                <tr class="<?php echo $pr['valid'] ? 'row-valid' : 'row-error'; ?>">
                    <td><?php echo (int)$pr['row']; ?></td>
                    <td><?php echo htmlspecialchars($pr['f_name']); ?></td>
                    <td><?php echo htmlspecialchars($pr['l_name']); ?></td>
                    <td><?php echo htmlspecialchars($pr['email']); ?></td>
                    <td><?php echo htmlspecialchars($pr['level_name']); ?></td>
                    <td><?php echo htmlspecialchars($pr['class_name']); ?></td>
                    <td>
                        <?php if ($pr['valid']): ?>
                            <span class="badge-valid">Valid</span>
                        <?php else: ?>
                            <span class="badge-error">Error</span>
                            <ul class="err-list">
                                <?php foreach ($pr['errors'] as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
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
        <button type="submit" class="btn btn-success">
            Confirm Import (<?php echo $valid_count; ?> student<?php echo $valid_count !== 1 ? 's' : ''; ?>)
        </button>
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
    <p class="card-title">Upload CSV File</p>
    <p class="card-subtitle">Import multiple students by uploading a correctly formatted CSV file. Student IDs, usernames, and passwords are assigned automatically.</p>

    <div class="template-hint">
        <strong>Required columns (in order):</strong>
        <code>first_name</code>, <code>last_name</code>, <code>email</code>,
        <code>level</code>, <code>class</code>
        <br><br>
        <strong>Notes:</strong>
        <ul style="margin:6px 0 0 18px;padding:0;font-size:13px">
            <li><code>level</code> — enter the level as a number: <strong>100, 200, 300</strong> or <strong>400</strong></li>
            <li><code>class</code> — enter the class code exactly as shown, e.g. <strong>BIT28</strong></li>
            <li>Each email must be unique and not already registered.</li>
            <li>Student IDs, usernames, and temporary passwords are assigned automatically.</li>
        </ul>
        <br>
        <a href="import.php?action=template" class="btn btn-outline btn-sm">Download CSV Template</a>
    </div>

    <!-- Class reference -->
    <?php
    $class_map_display = get_classes_by_code($conn, $department_id);
    if (!empty($class_map_display)):
    ?>
    <details style="margin-bottom:18px">
        <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#667eea">View available classes in your department</summary>
        <table style="margin-top:8px;width:auto;min-width:260px">
            <thead><tr><th>Class Code</th><th>Class Name</th></tr></thead>
            <tbody>
            <?php foreach ($class_map_display as $code => $info): ?>
            <tr><td><?php echo htmlspecialchars($code); ?></td><td><?php echo htmlspecialchars($info['name']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </details>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?php csrf_token_input(); ?>
        <div class="form-group">
            <label class="form-label" for="csv_file">Select CSV File</label>
            <input type="file" name="csv_file" id="csv_file" class="form-input" accept=".csv,text/csv" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload &amp; Preview</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php endif; ?>

</div>

<?php require_once '../../includes/footer.php'; ?>
