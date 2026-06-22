<?php
/**
 * HOD — Lecturer × Course Assignment Matrix
 * Full-department bulk assignment view.
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_HOD) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type']    = 'error';
    header('Location: ../../login.php');
    exit();
}

$hod_id        = $_SESSION['user_id'];
$department_id = (int) $_SESSION['department_id'];
$page_title    = 'Assignment Matrix';

// ------------------------------------------------------------------
// Load active period (required — abort form if missing)
// ------------------------------------------------------------------
$result_period  = mysqli_query($conn, 'SELECT * FROM view_active_period LIMIT 1');
$active_period  = mysqli_fetch_assoc($result_period);

if (!$active_period) {
    $_SESSION['flash_message'] = 'No active academic period is configured. Please ask an administrator to activate a period before managing assignments.';
    $_SESSION['flash_type']    = 'error';
    // Still render the page so the flash is visible; flag for template
    $no_period = true;
} else {
    $no_period         = false;
    $academic_year_id  = (int) $active_period['academic_year_id'];
    $semester_id       = (int) $active_period['semester_id'];
}

// ------------------------------------------------------------------
// POST — save bulk assignments
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$no_period) {

    if (!validate_csrf_token()) {
        $_SESSION['flash_message'] = 'Invalid security token. Please try again.';
        $_SESSION['flash_type']    = 'error';
        header('Location: assign_matrix.php');
        exit();
    }

    // Submitted checkbox map: [lecturer_id][course_id] = "1"
    $submitted = isset($_POST['assignments']) && is_array($_POST['assignments'])
        ? $_POST['assignments']
        : [];

    // ------------------------------------------------------------------
    // Fetch all dept lecturers and courses for boundary validation
    // ------------------------------------------------------------------
    $dept_lecturer_ids = [];
    $stmt_lec = mysqli_prepare($conn,
        'SELECT user_id FROM user_details
          WHERE department_id = ? AND role_id = ? AND is_active = 1');
    mysqli_stmt_bind_param($stmt_lec, 'ii', $department_id, ROLE_ADVISOR);
    mysqli_stmt_execute($stmt_lec);
    $res_lec = mysqli_stmt_get_result($stmt_lec);
    while ($row = mysqli_fetch_assoc($res_lec)) {
        $dept_lecturer_ids[$row['user_id']] = true;
    }
    mysqli_stmt_close($stmt_lec);

    $dept_course_ids = [];
    $stmt_crs = mysqli_prepare($conn,
        'SELECT id FROM courses WHERE department_id = ?');
    mysqli_stmt_bind_param($stmt_crs, 'i', $department_id);
    mysqli_stmt_execute($stmt_crs);
    $res_crs = mysqli_stmt_get_result($stmt_crs);
    while ($row = mysqli_fetch_assoc($res_crs)) {
        $dept_course_ids[$row['id']] = true;
    }
    mysqli_stmt_close($stmt_crs);

    // ------------------------------------------------------------------
    // Fetch current assignments for this dept + period
    // ------------------------------------------------------------------
    $current = [];   // [lecturer_user_id][course_id] = assignment_id
    $stmt_cur = mysqli_prepare($conn,
        'SELECT cl.id, cl.lecturer_user_id, cl.course_id
           FROM course_lecturers cl
           JOIN courses c ON c.id = cl.course_id
          WHERE c.department_id = ?
            AND cl.academic_year_id = ?
            AND cl.semester_id = ?');
    mysqli_stmt_bind_param($stmt_cur, 'iii', $department_id, $academic_year_id, $semester_id);
    mysqli_stmt_execute($stmt_cur);
    $res_cur = mysqli_stmt_get_result($stmt_cur);
    while ($row = mysqli_fetch_assoc($res_cur)) {
        $current[$row['lecturer_user_id']][$row['course_id']] = (int) $row['id'];
    }
    mysqli_stmt_close($stmt_cur);

    // ------------------------------------------------------------------
    // Prepare INSERT and DELETE statements
    // ------------------------------------------------------------------
    $stmt_ins = mysqli_prepare($conn,
        'INSERT INTO course_lecturers
            (course_id, lecturer_user_id, academic_year_id, semester_id, assigned_at, is_active)
         VALUES (?, ?, ?, ?, NOW(), 1)');

    $stmt_del = mysqli_prepare($conn,
        'DELETE FROM course_lecturers WHERE id = ?');

    $inserts = 0;
    $deletes = 0;

    foreach ($dept_lecturer_ids as $lid => $_l) {
        foreach ($dept_course_ids as $cid => $_c) {
            $is_checked  = isset($submitted[$lid][$cid]) && $submitted[$lid][$cid] === '1';
            $exists      = isset($current[$lid][$cid]);
            $existing_id = $exists ? $current[$lid][$cid] : null;

            if ($is_checked && !$exists) {
                // INSERT
                mysqli_stmt_bind_param($stmt_ins, 'iiii',
                    $cid, $lid, $academic_year_id, $semester_id);
                mysqli_stmt_execute($stmt_ins);
                $inserts++;

            } elseif (!$is_checked && $exists) {
                // DELETE
                mysqli_stmt_bind_param($stmt_del, 'i', $existing_id);
                mysqli_stmt_execute($stmt_del);
                $deletes++;
            }
        }
    }

    mysqli_stmt_close($stmt_ins);
    mysqli_stmt_close($stmt_del);

    // ------------------------------------------------------------------
    // Audit + flash
    // ------------------------------------------------------------------
    log_audit($conn, $hod_id, 'COURSE_ASSIGN', 'course_lecturers', null, null, [
        'department_id'    => $department_id,
        'academic_year_id' => $academic_year_id,
        'semester_id'      => $semester_id,
        'assignments_added'   => $inserts,
        'assignments_removed' => $deletes,
    ]);

    $_SESSION['flash_message'] = $inserts . ' assignment' . ($inserts !== 1 ? 's' : '') . ' added, '
        . $deletes . ' removed.';
    $_SESSION['flash_type'] = ($inserts > 0 || $deletes > 0) ? 'success' : 'info';

    header('Location: assign_matrix.php');
    exit();
}

// ------------------------------------------------------------------
// GET — load data for matrix rendering
// ------------------------------------------------------------------
$lecturers = [];
if (!$no_period) {
    $stmt_lec2 = mysqli_prepare($conn,
        'SELECT user_id, f_name, l_name
           FROM user_details
          WHERE department_id = ? AND role_id = ? AND is_active = 1
          ORDER BY l_name, f_name');
    mysqli_stmt_bind_param($stmt_lec2, 'ii', $department_id, ROLE_ADVISOR);
    mysqli_stmt_execute($stmt_lec2);
    $res_lec2 = mysqli_stmt_get_result($stmt_lec2);
    while ($row = mysqli_fetch_assoc($res_lec2)) {
        $lecturers[] = $row;
    }
    mysqli_stmt_close($stmt_lec2);
}

$courses = [];
if (!$no_period) {
    $stmt_crs2 = mysqli_prepare($conn,
        'SELECT id, course_code, name
           FROM courses
          WHERE department_id = ?
          ORDER BY course_code');
    mysqli_stmt_bind_param($stmt_crs2, 'i', $department_id);
    mysqli_stmt_execute($stmt_crs2);
    $res_crs2 = mysqli_stmt_get_result($stmt_crs2);
    while ($row = mysqli_fetch_assoc($res_crs2)) {
        $courses[] = $row;
    }
    mysqli_stmt_close($stmt_crs2);
}

// Build $assigned[lecturer_user_id][course_id] = true
$assigned = [];
if (!$no_period) {
    $stmt_asgn = mysqli_prepare($conn,
        'SELECT cl.lecturer_user_id, cl.course_id
           FROM course_lecturers cl
           JOIN courses c ON c.id = cl.course_id
          WHERE c.department_id = ?
            AND cl.academic_year_id = ?
            AND cl.semester_id = ?');
    mysqli_stmt_bind_param($stmt_asgn, 'iii', $department_id, $academic_year_id, $semester_id);
    mysqli_stmt_execute($stmt_asgn);
    $res_asgn = mysqli_stmt_get_result($stmt_asgn);
    while ($row = mysqli_fetch_assoc($res_asgn)) {
        $assigned[$row['lecturer_user_id']][$row['course_id']] = true;
    }
    mysqli_stmt_close($stmt_asgn);
}

require_once '../../includes/header.php';
?>

<style>
/* ---- Matrix page styles ---- */
.matrix-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
    padding: 28px 30px;
    margin-bottom: 24px;
}

.matrix-card h2 {
    margin: 0 0 6px;
    font-size: 1.25rem;
    color: #2d3748;
}

.matrix-card .subtitle {
    color: #718096;
    font-size: .9rem;
    margin: 0 0 20px;
}

/* Scrollable wrapper */
.matrix-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

/* The matrix table */
.matrix-table {
    border-collapse: collapse;
    white-space: nowrap;
    font-size: .85rem;
}

.matrix-table th,
.matrix-table td {
    border: 1px solid #e2e8f0;
    padding: 0;
}

/* Rotating column headers */
.matrix-table thead th {
    background: #f7f8fc;
    vertical-align: bottom;
    text-align: center;
    height: 130px;
    min-width: 44px;
    max-width: 44px;
    width: 44px;
    position: relative;
}

.matrix-table thead th.col-header-rotated {
    padding-bottom: 8px;
}

.col-header-rotated .col-label {
    display: block;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    white-space: nowrap;
    font-size: .78rem;
    font-weight: 600;
    color: #4a5568;
    max-height: 118px;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 4px 0;
    cursor: default;
}

/* First column — lecturer name */
.matrix-table th.name-col,
.matrix-table td.name-col {
    min-width: 180px;
    max-width: 220px;
    padding: 8px 12px;
    text-align: left;
    position: sticky;
    left: 0;
    background: #fff;
    z-index: 2;
    box-shadow: 2px 0 4px rgba(0,0,0,.06);
}

.matrix-table thead th.name-col {
    background: #f7f8fc;
    z-index: 3;
    font-weight: 700;
    color: #2d3748;
}

.lecturer-name-cell {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
}

.lecturer-name-cell span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
    display: inline-block;
    color: #2d3748;
    font-weight: 500;
}

/* Checkbox cell */
.matrix-table td.check-cell {
    text-align: center;
    vertical-align: middle;
    padding: 0;
    transition: background .15s;
}

/* Highlight cell when checked */
.matrix-table td.check-cell:has(input:checked) {
    background: #c6f6d5;
}

.matrix-table td.check-cell input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #48bb78;
    margin: 10px;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

/* Row hover */
.matrix-table tbody tr:hover td {
    background: #ebf8ff;
}

.matrix-table tbody tr:hover td.check-cell:has(input:checked) {
    background: #9ae6b4;
}

.matrix-table tbody tr:hover td.name-col {
    background: #ebf8ff;
}

/* Select All button per row */
.btn-select-row {
    font-size: .7rem;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid #667eea;
    background: transparent;
    color: #667eea;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background .15s, color .15s;
}

.btn-select-row:hover {
    background: #667eea;
    color: #fff;
}

/* Form actions bar */
.matrix-actions {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.btn-save-matrix {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border: none;
    padding: 10px 28px;
    border-radius: 8px;
    font-size: .95rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .2s;
}

.btn-save-matrix:hover {
    opacity: .88;
}

.matrix-legend {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: .82rem;
    color: #718096;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.legend-swatch {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    border: 1px solid #cbd5e0;
    display: inline-block;
}

.legend-swatch.assigned {
    background: #c6f6d5;
    border-color: #48bb78;
}

.legend-swatch.unassigned {
    background: #fff;
}

/* Mobile hint */
.scroll-hint {
    font-size: .78rem;
    color: #a0aec0;
    margin-bottom: 6px;
    display: none;
}

@media (max-width: 768px) {
    .scroll-hint { display: block; }
    .matrix-card { padding: 18px 14px; }
}

/* Info banner */
.period-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 8px;
    padding: 12px 20px;
    margin-bottom: 20px;
    font-size: .9rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.period-banner strong { font-weight: 700; }

/* No data states */
.empty-matrix {
    text-align: center;
    padding: 48px 20px;
    color: #a0aec0;
}

.empty-matrix .icon { font-size: 3rem; margin-bottom: 12px; }
.empty-matrix h3 { color: #4a5568; margin: 0 0 8px; }
.empty-matrix p  { margin: 0; font-size: .9rem; }
</style>

<div class="page-header">
    <h1>Assignment Matrix</h1>
    <p>Bulk assign lecturers to courses for your department.</p>
    <nav class="breadcrumb" aria-label="breadcrumb">
        <a href="../dashboard.php">Dashboard</a> &rsaquo;
        <a href="list.php">Lecturers</a> &rsaquo;
        <span>Assignment Matrix</span>
    </nav>
</div>

<?php if ($no_period): ?>
    <!-- No active period — flash is shown by header.php; nothing more to render -->
    <div class="matrix-card">
        <div class="empty-matrix">
            <div class="icon">&#128197;</div>
            <h3>No Active Period</h3>
            <p>An active academic period must be configured before assignments can be managed.</p>
        </div>
    </div>

<?php else: ?>

    <div class="period-banner" role="status">
        <span>&#128197;</span>
        <span>Active period:
            <strong><?php echo htmlspecialchars($active_period['academic_year'] ?? ''); ?></strong>
            &mdash;
            <strong><?php echo htmlspecialchars($active_period['semester_name'] ?? ''); ?></strong>
        </span>
    </div>

    <?php if (empty($lecturers)): ?>
        <div class="matrix-card">
            <div class="empty-matrix">
                <div class="icon">&#128100;</div>
                <h3>No Lecturers Found</h3>
                <p>There are no active lecturers in your department.</p>
            </div>
        </div>

    <?php elseif (empty($courses)): ?>
        <div class="matrix-card">
            <div class="empty-matrix">
                <div class="icon">&#128218;</div>
                <h3>No Courses Found</h3>
                <p>There are no courses registered for your department.</p>
            </div>
        </div>

    <?php else: ?>

        <form method="POST" action="assign_matrix.php" id="matrixForm">
            <?php csrf_token_input(); ?>

            <div class="matrix-card">
                <h2>Lecturer &times; Course Matrix</h2>
                <p class="subtitle">
                    Check a cell to assign a lecturer to that course. Uncheck to remove the assignment.
                    &nbsp;|&nbsp;
                    <?php echo count($lecturers); ?> lecturer<?php echo count($lecturers) !== 1 ? 's' : ''; ?>,
                    <?php echo count($courses); ?> course<?php echo count($courses) !== 1 ? 's' : ''; ?>
                </p>

                <p class="scroll-hint">&#8592; Scroll horizontally to see all courses</p>

                <div class="matrix-scroll" role="region" aria-label="Assignment matrix" tabindex="0">
                    <table class="matrix-table" id="assignMatrix">
                        <thead>
                            <tr>
                                <th class="name-col" scope="col">Lecturer</th>
                                <?php foreach ($courses as $course): ?>
                                    <th class="col-header-rotated" scope="col">
                                        <span class="col-label"
                                              title="<?php echo htmlspecialchars($course['name']); ?>">
                                            <?php echo htmlspecialchars($course['course_code']); ?>
                                        </span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lecturers as $lecturer): ?>
                                <?php
                                $lid      = (int) $lecturer['user_id'];
                                $fullname = htmlspecialchars(trim($lecturer['f_name'] . ' ' . $lecturer['l_name']));
                                ?>
                                <tr>
                                    <td class="name-col">
                                        <div class="lecturer-name-cell">
                                            <span title="<?php echo $fullname; ?>">
                                                <?php echo $fullname; ?>
                                            </span>
                                            <button type="button"
                                                    class="btn-select-row"
                                                    data-row="row-<?php echo $lid; ?>"
                                                    title="Toggle all for this lecturer">
                                                All
                                            </button>
                                        </div>
                                    </td>
                                    <?php foreach ($courses as $course): ?>
                                        <?php
                                        $cid       = (int) $course['id'];
                                        $is_assign = isset($assigned[$lid][$cid]);
                                        $cb_id     = 'cb_' . $lid . '_' . $cid;
                                        ?>
                                        <td class="check-cell" data-row="row-<?php echo $lid; ?>">
                                            <input
                                                type="checkbox"
                                                id="<?php echo $cb_id; ?>"
                                                name="assignments[<?php echo $lid; ?>][<?php echo $cid; ?>]"
                                                value="1"
                                                <?php echo $is_assign ? 'checked' : ''; ?>
                                                aria-label="Assign <?php echo $fullname; ?> to <?php echo htmlspecialchars($course['course_code']); ?>"
                                            >
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div><!-- .matrix-scroll -->

                <div class="matrix-actions">
                    <button type="submit" class="btn-save-matrix">
                        Save All Assignments
                    </button>
                    <a href="list.php" class="btn btn-secondary">Cancel</a>
                    <div class="matrix-legend" aria-label="Legend">
                        <span class="legend-item">
                            <span class="legend-swatch assigned"></span> Assigned
                        </span>
                        <span class="legend-item">
                            <span class="legend-swatch unassigned"></span> Unassigned
                        </span>
                    </div>
                </div>
            </div><!-- .matrix-card -->
        </form>

    <?php endif; // lecturers && courses ?>

<?php endif; // active period ?>

<script>
(function () {
    'use strict';

    // Per-row "All" toggle
    document.querySelectorAll('.btn-select-row').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var rowKey   = btn.getAttribute('data-row');
            var cells    = document.querySelectorAll('[data-row="' + rowKey + '"] input[type="checkbox"]');
            var allChk   = Array.from(cells).every(function (cb) { return cb.checked; });
            cells.forEach(function (cb) { cb.checked = !allChk; });
            btn.textContent = !allChk ? 'None' : 'All';
        });
    });

    // Restore button label when individual boxes are toggled
    document.querySelectorAll('.matrix-table tbody input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var td      = cb.closest('td');
            var rowKey  = td ? td.getAttribute('data-row') : null;
            if (!rowKey) return;
            var cells   = document.querySelectorAll('[data-row="' + rowKey + '"] input[type="checkbox"]');
            var allChk  = Array.from(cells).every(function (c) { return c.checked; });
            var btn     = document.querySelector('.btn-select-row[data-row="' + rowKey + '"]');
            if (btn) btn.textContent = allChk ? 'None' : 'All';
        });
    });

    // Warn before leaving with unsaved changes
    var form     = document.getElementById('matrixForm');
    var pristine = form ? form.innerHTML : null;
    var saved    = false;

    if (form) {
        form.addEventListener('submit', function () { saved = true; });
        window.addEventListener('beforeunload', function (e) {
            if (!saved && form.innerHTML !== pristine) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }
}());
</script>

<?php require_once '../../includes/footer.php'; ?>
