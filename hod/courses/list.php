<?php

/**
 * HOD Courses List
 *
 * View all courses in the HOD's department with lecturer assignments
 * and evaluation statistics.
 *
 * Features:
 * - List all department courses
 * - Show assigned lecturers
 * - Evaluation statistics per course
 * - Filter by level, semester
 * - Sort options
 * - Assign/reassign lecturers
 *
 * Role Required: ROLE_HOD
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] != ROLE_HOD) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$hod_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'];
$page_title = 'Department Courses';

// Get filters
$filter_level = isset($_GET['level_id']) ? intval($_GET['level_id']) : 0;
$filter_semester = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get levels
$levels = [];
$result_levels = mysqli_query($conn, "SELECT * FROM level ORDER BY level_number");
while ($row = mysqli_fetch_assoc($result_levels)) $levels[] = $row;

// Get semesters
$semesters = [];
$result_sems = mysqli_query($conn, "SELECT * FROM semesters ORDER BY semester_value");
while ($row = mysqli_fetch_assoc($result_sems)) $semesters[] = $row;

// Build query
$where = ["c.department_id = ?"];
$params = [$department_id];
$types = 'i';

if ($filter_level > 0) {
    $where[] = "c.level_id = ?";
    $params[] = $filter_level;
    $types .= 'i';
}
if ($filter_semester > 0) {
    $where[] = "c.semester_id = ?";
    $params[] = $filter_semester;
    $types .= 'i';
}
if (!empty($search)) {
    $where[] = "(c.course_code LIKE ? OR c.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = implode(' AND ', $where);

$query = "
    SELECT
        c.id,
        c.course_code,
        c.name,
        c.semester_id,
        l.level_name,
        s.semester_name,
        COUNT(DISTINCT et.token_id) as total_evals,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as completed_evals,
        GROUP_CONCAT(DISTINCT CONCAT(u.f_name, ' ', u.l_name) SEPARATOR ', ') as lecturers
    FROM courses c
    LEFT JOIN level l ON c.level_id = l.t_id
    LEFT JOIN semesters s ON c.semester_id = s.semester_id
    LEFT JOIN evaluation_tokens et ON c.id = et.course_id
    LEFT JOIN course_lecturers cl ON c.id = cl.course_id
    LEFT JOIN user_details u ON cl.lecturer_user_id = u.user_id
    WHERE $where_clause
    GROUP BY c.id
    ORDER BY c.course_code
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$courses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['completion_rate'] = $row['total_evals'] > 0 ?
        round(($row['completed_evals'] / $row['total_evals']) * 100, 1) : 0;
    $courses[] = $row;
}
mysqli_stmt_close($stmt);

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
    }

    .filter-group select,
    .filter-group input {
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

    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }

    .stats-summary {
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
        color: #667eea;
    }

    .stat-label {
        font-size: 14px;
        color: #666;
        margin-top: 5px;
    }

    .courses-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .courses-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .courses-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #333;
        font-size: 13px;
        border-bottom: 2px solid #e0e0e0;
    }

    .courses-table td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
    }

    .courses-table tr:hover {
        background: #f8f9fa;
    }

    .lecturer-list {
        font-size: 13px;
        color: #666;
    }

    .no-lecturer {
        color: #dc3545;
        font-style: italic;
    }

    .progress-bar-mini {
        width: 80px;
        height: 6px;
        background: #e0e0e0;
        border-radius: 3px;
        overflow: hidden;
        display: inline-block;
        vertical-align: middle;
        margin-left: 5px;
    }

    .progress-fill-mini {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 8px;
    }
</style>

<div class="page-header">
    <h1>Department Courses</h1>
    <p>Manage and view courses in your department</p>
</div>

<!-- Summary Statistics -->
<div class="stats-summary">
    <div class="stat-card">
        <div class="stat-value"><?php echo count($courses); ?></div>
        <div class="stat-label">Total Courses</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">
            <?php
            $with_lecturers = count(array_filter($courses, function ($c) {
                return !empty($c['lecturers']);
            }));
            echo $with_lecturers;
            ?>
        </div>
        <div class="stat-label">With Assigned Lecturers</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">
            <?php
            $total_evals = array_sum(array_column($courses, 'completed_evals'));
            echo $total_evals;
            ?>
        </div>
        <div class="stat-label">Total Evaluations</div>
    </div>
</div>

<!-- Filters -->
<div class="filters-section">
    <form method="GET">
        <div class="filters-grid">
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
            <div class="filter-group">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" placeholder="Course code or name" value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="list.php" class="btn btn-secondary">Reset</a>
        <button type="button" onclick="exportTableToCSV('courses-table', 'courses.csv')" class="btn btn-success">Export CSV</button>
    </form>
</div>

<!-- Courses Table -->
<?php if (empty($courses)): ?>
    <div class="empty-state">
        <div style="font-size: 80px; opacity: 0.3;">📚</div>
        <h3>No Courses Found</h3>
        <p style="color: #666;">No courses match your search criteria.</p>
    </div>
<?php else: ?>
    <div class="courses-table">
        <table id="courses-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Level</th>
                    <th>Semester</th>
                    <th>Assigned Lecturers</th>
                    <th>Evaluations</th>
                    <th>Completion</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($course['name']); ?></td>
                        <td><?php echo htmlspecialchars($course['level_name']); ?></td>
                        <td><?php echo htmlspecialchars($course['semester_name']); ?></td>
                        <td>
                            <?php if (!empty($course['lecturers'])): ?>
                                <span class="lecturer-list"><?php echo htmlspecialchars($course['lecturers']); ?></span>
                            <?php else: ?>
                                <span class="no-lecturer">No lecturer assigned</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $course['completed_evals']; ?> / <?php echo $course['total_evals']; ?></td>
                        <td>
                            <?php echo $course['completion_rate']; ?>%
                            <div class="progress-bar-mini">
                                <div class="progress-fill-mini" style="width: <?php echo $course['completion_rate']; ?>%;"></div>
                            </div>
                        </td>
                        <td>
                            <a href="../lecturers/assign_courses.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                Assign Lecturer
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px; text-align: center; font-size: 14px; color: #666;">
        Showing <?php echo count($courses); ?> course(s)
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
