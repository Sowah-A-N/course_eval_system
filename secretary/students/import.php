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

/** Default password given to every imported student (they must change it on first login). */
const DEFAULT_STUDENT_PASSWORD = 'password246';

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
 * File readers — CSV and Excel (.xlsx) both produce the same shape:
 * an array of rows, each row a 0-indexed array of trimmed cell strings.
 * --------------------------------------------------------------------- */

/** Read a CSV file into an array of rows. */
function parse_csv_file(string $path): array {
    $rows = [];
    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException('Could not open the CSV file.');
    }
    while (($r = fgetcsv($handle)) !== false) {
        $rows[] = array_map(function ($v) { return trim((string) $v); }, $r);
    }
    fclose($handle);
    return $rows;
}

/** Convert an Excel column reference ("A", "B", "AA") to a 0-based index. */
function xlsx_col_index(string $ref): int {
    if (!preg_match('/^([A-Za-z]+)/', $ref, $m)) return -1;
    $letters = strtoupper($m[1]);
    $n = 0;
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return $n - 1;
}

/** Extract text from a sharedStrings <si> element (handles rich-text runs). */
function xlsx_si_text(SimpleXMLElement $si): string {
    if (isset($si->t)) return (string) $si->t;
    $text = '';
    if (isset($si->r)) {
        foreach ($si->r as $r) $text .= (string) $r->t;
    }
    return $text;
}

/**
 * Read an .xlsx file into an array of rows using only ZipArchive + SimpleXML
 * (an .xlsx file is a ZIP of XML parts — no external library needed).
 */
function parse_xlsx_file(string $path): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Excel import is not available on this server. Please save your file as CSV and upload that instead.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Could not open the Excel file — it may be corrupted.');
    }

    // Shared strings table (text cells reference entries here).
    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $sx = simplexml_load_string($ssXml);
        if ($sx !== false) {
            foreach ($sx->si as $si) $shared[] = xlsx_si_text($si);
        }
    }

    // Worksheet XML (first sheet).
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetXml = $zip->getFromName($name);
                break;
            }
        }
    }
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('Could not find a worksheet inside the Excel file.');
    }
    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        throw new RuntimeException('Could not read the Excel worksheet.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells   = [];
        $max_col = -1;
        $auto    = 0; // fallback position when a cell omits its "r" reference
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $col = $ref !== '' ? xlsx_col_index($ref) : $auto;
            if ($col < 0) $col = $auto;
            $auto = $col + 1;

            $type = (string) $c['t'];
            if ($type === 's') {
                $val = $shared[(int) $c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $val = (string) $c->is->t;
            } else {
                $val = (string) $c->v; // number, boolean, or literal string
            }
            $cells[$col] = $val;
            if ($col > $max_col) $max_col = $col;
        }
        $dense = [];
        for ($i = 0; $i <= $max_col; $i++) {
            $dense[$i] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
        }
        $rows[] = $dense;
    }
    return $rows;
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

    // Every imported student gets the same default password. It is hashed once
    // (bcrypt is deliberately slow, so we avoid re-hashing per row) and each
    // student is flagged to change it on first login.
    $password_hash = password_hash(DEFAULT_STUDENT_PASSWORD, PASSWORD_DEFAULT);

    mysqli_begin_transaction($conn);
    try {
        $stmt_ins = mysqli_prepare($conn,
            "INSERT INTO user_details
             (username, password, email, f_name, l_name, unique_id, role_id,
              department_id, level_id, class_id, is_active, force_password_change, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,1,1,NOW())"
        );

        foreach ($preview as $row) {
            $unique_id = generate_unique_student_id($conn);
            $username  = ces_derive_username($conn, $row['f_name'], $row['l_name']);

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
                'password' => DEFAULT_STUDENT_PASSWORD,
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
 * Step 2 — Parse & preview (CSV or Excel .xlsx)
 * --------------------------------------------------------------------- */
$preview_rows = [];
$valid_count  = 0;
$error_count  = 0;
$parse_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'confirm') {
    if (!validate_csrf_token()) {
        $parse_errors[] = 'Invalid security token.';
    } elseif (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $parse_errors[] = 'Please select a valid CSV or Excel file to upload.';
    } else {
        $file = $_FILES['import_file']['tmp_name'];
        $ext  = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));

        // Read the whole file into a uniform array of rows, whatever the format.
        $rows = [];
        try {
            if ($ext === 'csv') {
                $rows = parse_csv_file($file);
            } elseif ($ext === 'xlsx') {
                $rows = parse_xlsx_file($file);
            } elseif ($ext === 'xls') {
                $parse_errors[] = 'The old Excel .xls format is not supported. Please save the file as .xlsx or CSV and try again.';
            } else {
                $parse_errors[] = 'File must be a CSV (.csv) or Excel (.xlsx) file.';
            }
        } catch (Throwable $e) {
            $parse_errors[] = $e->getMessage();
        }

        if (empty($parse_errors) && empty($rows)) {
            $parse_errors[] = 'The file appears to be empty.';
        }

        if (empty($parse_errors)) {
            $level_map = get_levels_by_number($conn);
            $class_map = get_classes_by_code($conn, $department_id);

            // Map header names to column positions so column ORDER doesn't matter.
            $header_lower = array_map('strtolower', array_map('trim', $rows[0]));
            $required = ['first_name', 'last_name', 'email', 'level', 'class'];
            $missing  = array_diff($required, $header_lower);
            if (!empty($missing)) {
                $parse_errors[] = 'File is missing required columns: ' . implode(', ', $missing);
            } else {
                $col = array_flip($header_lower);
                $seen_emails = [];
                $total = count($rows);

                for ($i = 1; $i < $total; $i++) {
                    $cells = $rows[$i];
                    if (count(array_filter(array_map('trim', $cells))) === 0) continue; // skip blank rows

                    $get = function ($name) use ($cells, $col) {
                        return (isset($col[$name]) && isset($cells[$col[$name]])) ? trim((string) $cells[$col[$name]]) : '';
                    };

                    $f_name    = $get('first_name');
                    $l_name    = $get('last_name');
                    $email     = strtolower($get('email'));
                    $level_raw = $get('level');
                    $class_raw = $get('class');

                    $row_errors = [];

                    if ($f_name === '') $row_errors[] = 'First name is required';
                    if ($l_name === '') $row_errors[] = 'Last name is required';

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
                    $level_num  = filter_var($level_raw, FILTER_VALIDATE_INT);
                    $level_id   = null;
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
                        'row'        => $i + 1,
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

                if (empty($preview_rows)) {
                    $parse_errors[] = 'The file contains no data rows.';
                } else {
                    $_SESSION['import_preview'] = array_values(array_filter(
                        array_map(function($r) { return $r['valid'] ? [
                            'f_name'   => $r['f_name'],
                            'l_name'   => $r['l_name'],
                            'email'    => $r['email'],
                            'level_id' => $r['level_id'],
                            'class_id' => $r['class_id'],
                        ] : null; }, $preview_rows)
                    ));
                }
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
    <p>Upload a CSV or Excel file to enroll multiple students at once</p>
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
    <p><?php echo count($import_credentials); ?> student account(s) created. Every student was given the default password <code><?php echo htmlspecialchars(DEFAULT_STUDENT_PASSWORD); ?></code> and must change it on first login.</p>
    <p class="warn-note">Share each student's <strong>username</strong> along with the default password above so they can sign in.</p>
    <div class="table-wrap" id="creds-table-wrap">
        <table id="creds-table">
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Username</th><th>Default Password</th></tr>
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
    var data = <?php echo json_encode(array_map(function($c) { return [
        htmlspecialchars($c['name'],     ENT_QUOTES),
        htmlspecialchars($c['email'],    ENT_QUOTES),
        htmlspecialchars($c['username'], ENT_QUOTES),
        htmlspecialchars($c['password'], ENT_QUOTES),
    ]; }, $import_credentials), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
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
    <p class="card-title">Upload CSV or Excel File</p>
    <p class="card-subtitle">Import multiple students by uploading a correctly formatted CSV (.csv) or Excel (.xlsx) file. Student IDs and usernames are assigned automatically.</p>

    <div class="template-hint">
        <strong>Required columns:</strong>
        <code>first_name</code>, <code>last_name</code>, <code>email</code>,
        <code>level</code>, <code>class</code>
        <span style="color:#888">(any order — matched by column heading)</span>
        <br><br>
        <strong>Notes:</strong>
        <ul style="margin:6px 0 0 18px;padding:0;font-size:13px">
            <li><code>level</code> — enter the level as a number: <strong>100, 200, 300</strong> or <strong>400</strong></li>
            <li><code>class</code> — enter the class code exactly as shown, e.g. <strong>BIT28</strong></li>
            <li>Each email must be unique and not already registered.</li>
            <li>Every student is given the default password <code><?php echo htmlspecialchars(DEFAULT_STUDENT_PASSWORD); ?></code> and must change it on first login.</li>
            <li>Accepted files: <strong>.csv</strong> and <strong>.xlsx</strong> (save old <code>.xls</code> files as <code>.xlsx</code> first).</li>
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
            <label class="form-label" for="import_file">Select CSV or Excel File</label>
            <input type="file" name="import_file" id="import_file" class="form-input" accept=".csv,.xlsx" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload &amp; Preview</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php endif; ?>

</div>

<?php require_once '../../includes/footer.php'; ?>
