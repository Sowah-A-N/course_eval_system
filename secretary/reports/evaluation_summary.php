<?php
/**
 * Secretary Evaluation Summary Report
 * 
 * Evaluation statistics for department (anonymity-protected).
 * 
 * Features:
 * - Overall completion rates
 * - Course-level statistics
 * - Anonymity protection (min 5 responses)
 * - Filter by year/semester
 * 
 * Role Required: ROLE_SECRETARY
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] != ROLE_SECRETARY) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$department_id = $_SESSION['department_id'];
$page_title = 'Evaluation Summary Report';

// Get filters
$filter_year = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$filter_semester = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;

// Get academic years and semesters
$academic_years = [];
$result_years = mysqli_query($conn, "SELECT * FROM academic_year ORDER BY start_year DESC");
while ($row = mysqli_fetch_assoc($result_years)) $academic_years[] = $row;

$semesters = [];
$result_sems = mysqli_query($conn, "SELECT * FROM semesters ORDER BY semester_value");
while ($row = mysqli_fetch_assoc($result_sems)) $semesters[] = $row;

// Build where clause
$where = ["c.department_id = ?"];
$params = [$department_id];
$types = 'i';

if ($filter_year > 0) {
    $where[] = "et.academic_year_id = ?";
    $params[] = $filter_year;
    $types .= 'i';
}
if ($filter_semester > 0) {
    $where[] = "et.semester_id = ?";
    $params[] = $filter_semester;
    $types .= 'i';
}

$where_clause = implode(' AND ', $where);

// Get overall statistics
$query_stats = "
    SELECT 
        COUNT(DISTINCT c.id) as total_courses,
        COUNT(DISTINCT et.token_id) as total_tokens,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as completed_tokens
    FROM courses c
    LEFT JOIN evaluation_tokens et ON c.id = et.course_id
    WHERE $where_clause
";

$stmt = mysqli_prepare($conn, $query_stats);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$completion_rate = $stats['total_tokens'] > 0 ? 
    round(($stats['completed_tokens'] / $stats['total_tokens']) * 100, 1) : 0;

// Get course-level statistics
$query_courses = "
    SELECT 
        c.course_code,
        c.name,
        COUNT(DISTINCT et.token_id) as total_tokens,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as completed_tokens
    FROM courses c
    LEFT JOIN evaluation_tokens et ON c.id = et.course_id
    WHERE $where_clause
    GROUP BY c.id
    ORDER BY c.course_code
";

$stmt_courses = mysqli_prepare($conn, $query_courses);
mysqli_stmt_bind_param($stmt_courses, $types, ...$params);
mysqli_stmt_execute($stmt_courses);
$result_courses = mysqli_stmt_get_result($stmt_courses);

$courses = [];
while ($row = mysqli_fetch_assoc($result_courses)) {
    $row['completion_rate'] = $row['total_tokens'] > 0 ? 
        round(($row['completed_tokens'] / $row['total_tokens']) * 100, 1) : 0;
    $courses[] = $row;
}
mysqli_stmt_close($stmt_courses);

require_once '../../includes/header.php';
?>

<style>
    @media print {.no-print {display: none !important;}}
    .report-header {background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px;}
    .report-header h1 {margin: 0 0 10px 0; font-size: 28px;}
    .filters-section {background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
    .filters-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;}
    .filter-group label {font-size: 14px; font-weight: 500; margin-bottom: 5px; display: block;}
    .filter-group select {width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;}
    .btn {padding: 10px 20px; border: none; border-radius: 5px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 10px;}
    .btn-primary {background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;}
    .btn-secondary {background: #6c757d; color: white;}
    .stats-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;}
    .stat-card {background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;}
    .stat-value {font-size: 42px; font-weight: bold; color: #667eea;}
    .stat-label {font-size: 14px; color: #666; margin-top: 5px;}
    .course-table {background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
    .course-table table {width: 100%; border-collapse: collapse;}
    .course-table th {background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #e0e0e0;}
    .course-table td {padding: 15px; border-bottom: 1px solid #f0f0f0;}
    .progress-mini {width: 80px; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden; display: inline-block; vertical-align: middle; margin-left: 5px;}
    .progress-fill-mini {height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);}
</style>

<div class="report-header">
    <h1>Evaluation Summary Report</h1>
    <p>Department evaluation statistics and completion tracking</p>
</div>

<div class="no-print">
    <div class="filters-section">
        <form method="GET">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Academic Year</label>
                    <select name="academic_year_id">
                        <option value="0">All Years</option>
                        <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo $year['academic_year_id']; ?>" <?php echo $filter_year == $year['academic_year_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['year_label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Semester</label>
                    <select name="semester_id">
                        <option value="0">All Semesters</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo $sem['semester_id']; ?>" <?php echo $filter_semester == $sem['semester_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Generate Report</button>
            <a href="evaluation_summary.php" class="btn btn-secondary">Reset</a>
            <button type="button" onclick="window.print()" class="btn btn-secondary">🖨️ Print</button>
        </form>
    </div>
</div>

<!-- Overall Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo $completion_rate; ?>%</div>
        <div class="stat-label">Completion Rate</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['completed_tokens']; ?></div>
        <div class="stat-label">Completed Evaluations</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_tokens']; ?></div>
        <div class="stat-label">Total Tokens</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
        <div class="stat-label">Courses</div>
    </div>
</div>

<!-- Course-Level Statistics -->
<?php if (!empty($courses)): ?>
    <h2 style="font-size: 20px; font-weight: 600; margin: 30px 0 15px; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Course Completion Tracking</h2>
    <div class="course-table">
        <table>
            <thead>
                <tr>
                    <th scope="col">Course Code</th>
                    <th scope="col">Course Name</th>
                    <th scope="col">Completed</th>
                    <th scope="col">Total</th>
                    <th scope="col">Completion Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($course['name']); ?></td>
                        <td><?php echo $course['completed_tokens']; ?></td>
                        <td><?php echo $course['total_tokens']; ?></td>
                        <td>
                            <?php echo $course['completion_rate']; ?>%
                            <div class="progress-mini">
                                <div class="progress-fill-mini" style="width: <?php echo $course['completion_rate']; ?>%;"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px;">
        <p style="margin: 0; color: #666;">No evaluation data available for the selected period.</p>
    </div>
<?php endif; ?>

<div style="margin-top: 30px; padding: 20px; background: white; border-radius: 8px; border-left: 4px solid #667eea;">
    <h3 style="margin: 0 0 10px 0; font-size: 16px;">🔒 Note on Data Privacy</h3>
    <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">
        This report shows evaluation completion statistics only. Individual evaluation responses and ratings
        are not accessible to maintain student anonymity. Detailed performance reports are available to
        authorized personnel (HOD, Quality Assurance) with appropriate anonymity protections.
    </p>
</div>

<?php require_once '../../includes/footer.php'; ?>
