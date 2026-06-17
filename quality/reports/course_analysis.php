<?php
/**
 * Course Analysis Report
 *
 * Analyze individual course performance across the institution.
 *
 * Features:
 * - Top-rated courses
 * - Lowest-rated courses
 * - Filter by department/level/semester
 * - Course statistics (min 5 responses)
 * - Category breakdown per course
 *
 * Role Required: ROLE_QUALITY or ROLE_ADMIN
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_ADMIN && $_SESSION['role_id'] !== ROLE_QUALITY) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$page_title = 'Course Analysis Report';

// Get filters
$filter_department = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$filter_level = isset($_GET['level_id']) ? intval($_GET['level_id']) : 0;
$filter_semester = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;

// Get departments
$departments = [];
$result_depts = mysqli_query($conn, "SELECT * FROM department ORDER BY dep_name");
while ($row = mysqli_fetch_assoc($result_depts)) $departments[] = $row;

// Get levels
$levels = [];
$result_levels = mysqli_query($conn, "SELECT * FROM level ORDER BY level_number");
while ($row = mysqli_fetch_assoc($result_levels)) $levels[] = $row;

// Get semesters
$semesters = [];
$result_sems = mysqli_query($conn, "SELECT * FROM semesters ORDER BY semester_value");
while ($row = mysqli_fetch_assoc($result_sems)) $semesters[] = $row;

// Build query
$where = ["1=1"];
$params = [];
$types = '';

if ($filter_department > 0) {
    $where[] = "c.department_id = ?";
    $params[] = $filter_department;
    $types .= 'i';
}
if ($filter_level > 0) {
    $where[] = "c.level_id = ?";
    $params[] = $filter_level;
    $types .= 'i';
}
if ($filter_semester > 0) {
    $where[] = "et.semester_id = ?";
    $params[] = $filter_semester;
    $types .= 'i';
}

$where_clause = implode(' AND ', $where);

$query = "
    SELECT
        c.id,
        c.course_code,
        c.name as course_name,
        d.dep_name,
        l.level_name,
        COUNT(DISTINCT et.token_id) as total_evaluations,
        AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating,
        MIN(CAST(r.response_value AS DECIMAL(10,2))) as min_rating,
        MAX(CAST(r.response_value AS DECIMAL(10,2))) as max_rating
    FROM courses c
    LEFT JOIN evaluation_tokens et ON c.id = et.course_id AND et.is_used = 1
    LEFT JOIN evaluations e ON et.token = e.token
    LEFT JOIN responses r ON e.evaluation_id = r.evaluation_id
    LEFT JOIN department d ON c.department_id = d.t_id
    LEFT JOIN level l ON c.level_id = l.t_id
    WHERE $where_clause
    GROUP BY c.id
    HAVING total_evaluations >= ?
    ORDER BY avg_rating DESC
";

$params[] = MIN_RESPONSE_COUNT;
$types .= 'i';

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$courses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['avg_rating'] = round($row['avg_rating'], 2);
    $courses[] = $row;
}
mysqli_stmt_close($stmt);

// $breadcrumb = ['Dashboard' => '../index.php', 'Reports' => 'index.php', 'Course Analysis' => ''];
require_once '../../includes/header.php';
?>

<style>
    .filters-section {background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
    .filters-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;}
    .filter-group label {font-size: 14px; font-weight: 500; margin-bottom: 5px; display: block;}
    .filter-group select {width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;}
    .btn {padding: 10px 20px; border: none; border-radius: 5px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-block;}
    .btn-primary {background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;}
    .btn-secondary {background: #6c757d; color: white;}
    .course-table {background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
    .course-table table {width: 100%; border-collapse: collapse;}
    .course-table th {background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #e0e0e0;}
    .course-table td {padding: 15px; border-bottom: 1px solid #f0f0f0;}
    .course-table tr:hover {background: #f8f9fa;}
    .rating-high {color: #28a745; font-weight: bold;}
    .rating-medium {color: #ffc107; font-weight: bold;}
    .rating-low {color: #dc3545; font-weight: bold;}
</style>

<div class="page-header">
    <h1>Course Analysis Report</h1>
    <p>Analyze individual course performance</p>
</div>

<div class="filters-section">
    <form method="GET">
        <div class="filters-grid">
            <div class="filter-group">
                <label for="department_id">Department</label>
                <select name="department_id" id="department_id">
                    <option value="0">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['t_id']; ?>" <?php echo $filter_department == $dept['t_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['dep_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="level_id">Level</label>
                <select name="level_id" id="level_id">
                    <option value="0">All Levels</option>
                    <?php foreach ($levels as $level): ?>
                        <option value="<?php echo $level['t_id']; ?>" <?php echo $filter_level == $level['t_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($level['level_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="semester_id">Semester</label>
                <select name="semester_id" id="semester_id">
                    <option value="0">All Semesters</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?php echo $sem['semester_id']; ?>" <?php echo $filter_semester == $sem['semester_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sem['semester_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="course_analysis.php" class="btn btn-secondary">Reset</a>
    </form>
</div>

<?php if (empty($courses)): ?>
    <div style="text-align: center; padding: 60px; background: white; border-radius: 8px;">
        <div style="font-size: 80px; opacity: 0.3;">📚</div>
        <h3>No Course Data Available</h3>
        <p style="color: #666;">No courses with sufficient evaluation data (min <?php echo MIN_RESPONSE_COUNT; ?> evaluations).</p>
    </div>
<?php else: ?>
    <div class="course-table">
        <table>
            <thead>
                <tr>
                    <th scope="col">Rank</th>
                    <th scope="col">Course Code</th>
                    <th scope="col">Course Name</th>
                    <th scope="col">Department</th>
                    <th scope="col">Level</th>
                    <th scope="col">Evaluations</th>
                    <th scope="col">Avg Rating</th>
                    <th scope="col">Range</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rank = 1;
                foreach ($courses as $course):
                    $rating_class = $course['avg_rating'] >= 4.0 ? 'rating-high' :
                                   ($course['avg_rating'] >= 3.0 ? 'rating-medium' : 'rating-low');
                ?>
                    <tr>
                        <td><strong>#<?php echo $rank++; ?></strong></td>
                        <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($course['dep_name']); ?></td>
                        <td><?php echo htmlspecialchars($course['level_name']); ?></td>
                        <td><?php echo $course['total_evaluations']; ?></td>
                        <td class="<?php echo $rating_class; ?>"><?php echo $course['avg_rating']; ?> / 5.0</td>
                        <td><?php echo $course['min_rating']; ?> - <?php echo $course['max_rating']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
        <p style="margin: 0; color: #666;">Showing <?php echo count($courses); ?> course(s) with sufficient evaluation data</p>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
