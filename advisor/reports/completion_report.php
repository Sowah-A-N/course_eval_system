<?php

/**
 * Advisor Completion Report
 *
 * Shows evaluation completion status for all students in advisor's assigned classes.
 * Helps advisors track which students have completed their evaluations and which haven't.
 *
 * Features:
 * - List all students with completion status
 * - Filter by level, class, and completion status
 * - Show number of completed vs total evaluations per student
 * - Highlight students who haven't started
 * - Export to CSV for follow-up
 * - Sort by completion percentage
 * - Summary statistics
 *
 * Role Required: ROLE_ADVISOR
 */

// Include required files
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';

// Start session and check login
start_secure_session();
check_login();

// Check if user is an advisor
if ($_SESSION['role_id'] !== ROLE_ADVISOR) {
    $_SESSION['flash_message'] = 'Access denied. This page is only for advisors.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

// Get advisor information
$advisor_id = $_SESSION['user_id'];
$advisor_name = $_SESSION['full_name'];
$department_id = $_SESSION['department_id'];

// Set page title
$page_title = 'Evaluation Completion Report';

// Get filter parameters
$filter_level = isset($_GET['level_id']) ? intval($_GET['level_id']) : 0;
$filter_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Get advisor's assigned levels
$query_levels = "
    SELECT
        al.level_id,
        l.level_name,
        l.level_number
    FROM advisor_levels al
    JOIN level l ON al.level_id = l.t_id
    WHERE al.advisor_id = ?
    ORDER BY l.level_number
";

$stmt_levels = mysqli_prepare($conn, $query_levels);
mysqli_stmt_bind_param($stmt_levels, "i", $advisor_id);
mysqli_stmt_execute($stmt_levels);
$result_levels = mysqli_stmt_get_result($stmt_levels);
$assigned_levels = [];
$level_ids = [];
while ($row = mysqli_fetch_assoc($result_levels)) {
    $assigned_levels[] = $row;
    $level_ids[] = $row['level_id'];
}
mysqli_stmt_close($stmt_levels);

// Get classes in advisor's department
$query_classes = "
    SELECT DISTINCT
        c.t_id,
        c.class_name,
        c.level_id
    FROM classes c
    WHERE c.department_id = ?
    AND c.level_id IN (" . implode(',', array_fill(0, count($level_ids), '?')) . ")
    ORDER BY c.class_name
";

$stmt_classes = mysqli_prepare($conn, $query_classes);
$types = 'i' . str_repeat('i', count($level_ids));
$params = array_merge([$department_id], $level_ids);
mysqli_stmt_bind_param($stmt_classes, $types, ...$params);
mysqli_stmt_execute($stmt_classes);
$result_classes = mysqli_stmt_get_result($stmt_classes);
$classes = [];
while ($row = mysqli_fetch_assoc($result_classes)) {
    $classes[] = $row;
}
mysqli_stmt_close($stmt_classes);

// Initialize variables
$level_rows = [];
$no_assignment = empty($level_ids);
$total_students = 0;
$students_complete = 0;
$students_incomplete = 0;
$students_not_started = 0;
$overall_completion = 0;

if (!$no_assignment) {
    // Aggregate query: one row per level, counts by completion status
    $level_placeholders = implode(',', array_fill(0, count($level_ids), '?'));
    $query = "
        SELECT
            l.t_id AS level_id,
            l.level_name,
            l.level_number,
            COUNT(DISTINCT u.user_id) AS total_students,
            SUM(CASE WHEN
                (SELECT COUNT(*) FROM evaluation_tokens et WHERE et.student_user_id = u.user_id) > 0
                AND (SELECT COUNT(*) FROM evaluation_tokens et WHERE et.student_user_id = u.user_id AND et.is_used = 1)
                    = (SELECT COUNT(*) FROM evaluation_tokens et WHERE et.student_user_id = u.user_id)
                THEN 1 ELSE 0 END) AS complete_students,
            SUM(CASE WHEN
                (SELECT COUNT(*) FROM evaluation_tokens et WHERE et.student_user_id = u.user_id) > 0
                AND (SELECT COUNT(*) FROM evaluation_tokens et WHERE et.student_user_id = u.user_id AND et.is_used = 1) > 0
                AND (SELECT COUNT(*) FROM evaluation_tokens et WHERE et.student_user_id = u.user_id AND et.is_used = 1)
                    < (SELECT COUNT(*) FROM evaluation_tokens et WHERE et.student_user_id = u.user_id)
                THEN 1 ELSE 0 END) AS inprog_students,
            SUM(CASE WHEN
                (SELECT COUNT(*) FROM evaluation_tokens et WHERE et.student_user_id = u.user_id AND et.is_used = 1) = 0
                THEN 1 ELSE 0 END) AS notstarted_students
        FROM user_details u
        JOIN level l ON u.level_id = l.t_id
        WHERE u.role_id = ?
        AND u.level_id IN ($level_placeholders)
        AND u.department_id = ?
    ";

    $types = 'i' . str_repeat('i', count($level_ids)) . 'i';
    $params = array_merge([ROLE_STUDENT], $level_ids, [$department_id]);

    if ($filter_level > 0 && in_array($filter_level, $level_ids)) {
        $query .= " AND u.level_id = ?";
        $types .= 'i';
        $params[] = $filter_level;
    }
    if ($filter_class > 0) {
        $query .= " AND u.class_id = ?";
        $types .= 'i';
        $params[] = $filter_class;
    }

    $query .= " GROUP BY l.t_id, l.level_name, l.level_number ORDER BY l.level_number";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $row['pct_complete'] = $row['total_students'] > 0
            ? round(($row['complete_students'] / $row['total_students']) * 100, 1) : 0;
        $level_rows[] = $row;
        $total_students      += $row['total_students'];
        $students_complete   += $row['complete_students'];
        $students_incomplete += $row['inprog_students'];
        $students_not_started += $row['notstarted_students'];
    }
    mysqli_stmt_close($stmt);

    $overall_completion = $total_students > 0 ? round(($students_complete / $total_students) * 100, 1) : 0;
}

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'Reports' => 'index.php',
    'Completion Report' => ''
];

// Include header
require_once '../../includes/header.php';
?>

<style>
    .filters-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }

    .filter-group label {
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 5px;
        display: block;
        color: #333;
    }

    .filter-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .stat-value {
        font-size: 32px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .stat-value.complete {
        color: #28a745;
    }

    .stat-value.incomplete {
        color: #ffc107;
    }

    .stat-value.not-started {
        color: #dc3545;
    }

    .stat-value.total {
        color: #667eea;
    }

    .stat-label {
        font-size: 14px;
        color: #666;
    }

    .completion-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .completion-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .completion-table th {
        background: #f8f9fa;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #333;
        font-size: 13px;
        border-bottom: 2px solid #e0e0e0;
        white-space: nowrap;
    }

    .completion-table th a {
        color: #333;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .completion-table td {
        padding: 12px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
    }

    .completion-table tr:hover {
        background: #f8f9fa;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
        text-transform: uppercase;
    }

    .status-success {
        background: #d4edda;
        color: #155724;
    }

    .status-warning {
        background: #fff3cd;
        color: #856404;
    }

    .status-danger {
        background: #f8d7da;
        color: #721c24;
    }

    .status-gray {
        background: #e9ecef;
        color: #6c757d;
    }

    .progress-bar-container {
        width: 100%;
        height: 8px;
        background: #e0e0e0;
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s;
    }

    .alert-info-custom {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 20px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .no-data {
        text-align: center;
        padding: 40px;
        color: #666;
        font-style: italic;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1>Evaluation Completion Report</h1>
    <p>Track student evaluation completion status</p>
</div>

<?php if ($no_assignment): ?>
    <!-- No Assignment Message -->
    <div class="alert-info-custom">
        <strong>No Class Assignments</strong><br>
        You have not been assigned to any classes yet. Please contact your department head.
    </div>
<?php else: ?>

    <!-- Summary Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value total"><?php echo $total_students; ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-value complete"><?php echo $students_complete; ?></div>
            <div class="stat-label">Completed (100%)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value incomplete"><?php echo $students_incomplete; ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card">
            <div class="stat-value not-started"><?php echo $students_not_started; ?></div>
            <div class="stat-label">Not Started</div>
        </div>
        <div class="stat-card">
            <div class="stat-value total"><?php echo $overall_completion; ?>%</div>
            <div class="stat-label">Overall Completion Rate</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" action="">
            <div class="filters-grid">
                <!-- Level Filter -->
                <div class="filter-group">
                    <label for="level_id">Filter by Level</label>
                    <select name="level_id" id="level_id">
                        <option value="0">All Levels</option>
                        <?php foreach ($assigned_levels as $level): ?>
                            <option value="<?php echo $level['level_id']; ?>"
                                <?php echo $filter_level == $level['level_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($level['level_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Class Filter -->
                <div class="filter-group">
                    <label for="class_id">Filter by Class</label>
                    <select name="class_id" id="class_id">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['t_id']; ?>"
                                <?php echo $filter_class == $class['t_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="completion_report.php" class="btn btn-secondary">Reset</a>
                <button type="button" onclick="exportTableToCSV('completion-table', 'completion_report.csv')" class="btn btn-success">
                    Export to CSV
                </button>
            </div>
        </form>
    </div>

    <!-- Aggregated Completion by Level -->
    <?php if (empty($level_rows)): ?>
        <div class="completion-table">
            <div class="no-data">No students found matching your criteria.</div>
        </div>
    <?php else: ?>
        <div class="completion-table">
            <table id="completion-table">
                <thead>
                    <tr>
                        <th scope="col">Level</th>
                        <th scope="col">Total Students</th>
                        <th scope="col">Complete</th>
                        <th scope="col">In Progress</th>
                        <th scope="col">Not Started</th>
                        <th scope="col">% Complete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($level_rows as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['level_name']); ?></strong></td>
                            <td><?php echo $row['total_students']; ?></td>
                            <td><?php echo $row['complete_students']; ?></td>
                            <td><?php echo $row['inprog_students']; ?></td>
                            <td><?php echo $row['notstarted_students']; ?></td>
                            <td style="min-width: 150px;">
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $row['pct_complete']; ?>%;"></div>
                                </div>
                                <small><?php echo $row['pct_complete']; ?>%</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
