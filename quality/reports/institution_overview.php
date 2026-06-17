<?php
/**
 * Institution Overview Report
 *
 * Comprehensive institution-wide evaluation statistics and performance metrics.
 *
 * Features:
 * - Overall completion rates
 * - Response rates by level
 * - Category performance
 * - Filter by academic year/semester
 * - Anonymity protection
 * - Export functionality
 *
 * Role Required: ROLE_QUALITY or ROLE_ADMIN
 */

// Include required files
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';

// Start session and check login
start_secure_session();
check_login();

// Check role
if (!defined('ROLE_QUALITY')) {
    define('ROLE_QUALITY', 6);
}

if ($_SESSION['role_id'] !== ROLE_ADMIN && $_SESSION['role_id'] !== ROLE_QUALITY) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

// Set page title
$page_title = 'Institution Overview Report';

// Get filter parameters
$filter_academic_year = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$filter_semester = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;

// Get all academic years
$query_years = "SELECT * FROM academic_year ORDER BY start_year DESC";
$result_years = mysqli_query($conn, $query_years);
$academic_years = [];
while ($row = mysqli_fetch_assoc($result_years)) {
    $academic_years[] = $row;
}

// Get all semesters
$query_semesters = "SELECT * FROM semesters ORDER BY semester_value";
$result_semesters = mysqli_query($conn, $query_semesters);
$semesters = [];
while ($row = mysqli_fetch_assoc($result_semesters)) {
    $semesters[] = $row;
}

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$types = '';

if ($filter_academic_year > 0) {
    $where_conditions[] = "et.academic_year_id = ?";
    $params[] = $filter_academic_year;
    $types .= 'i';
}

if ($filter_semester > 0) {
    $where_conditions[] = "et.semester_id = ?";
    $params[] = $filter_semester;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get overall statistics
$query_overall = "
    SELECT
        COUNT(DISTINCT et.token_id) as total_tokens,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as completed_tokens,
        COUNT(DISTINCT et.student_user_id) as total_students,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.student_user_id END) as active_students,
        COUNT(DISTINCT et.course_id) as total_courses,
        COUNT(DISTINCT c.department_id) as total_departments
    FROM evaluation_tokens et
    JOIN courses c ON et.course_id = c.id
    $where_clause
";

if (!empty($params)) {
    $stmt_overall = mysqli_prepare($conn, $query_overall);
    mysqli_stmt_bind_param($stmt_overall, $types, ...$params);
    mysqli_stmt_execute($stmt_overall);
    $result_overall = mysqli_stmt_get_result($stmt_overall);
    $overall_stats = mysqli_fetch_assoc($result_overall);
    mysqli_stmt_close($stmt_overall);
} else {
    $result_overall = mysqli_query($conn, $query_overall);
    $overall_stats = mysqli_fetch_assoc($result_overall);
}

$total_tokens = $overall_stats['total_tokens'];
$completed_tokens = $overall_stats['completed_tokens'];
$completion_rate = $total_tokens > 0 ? round(($completed_tokens / $total_tokens) * 100, 1) : 0;
$sufficient_data = $completed_tokens >= MIN_RESPONSE_COUNT;

// Get level-wise statistics (only if sufficient data)
$level_stats = [];
if ($sufficient_data) {
    $query_levels = "
        SELECT
            l.level_name,
            l.level_number,
            COUNT(DISTINCT et.token_id) as level_tokens,
            COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as level_completed
        FROM evaluation_tokens et
        JOIN courses c ON et.course_id = c.id
        JOIN level l ON c.level_id = l.t_id
        $where_clause
        GROUP BY l.t_id
        HAVING level_completed >= ?
        ORDER BY l.level_number
    ";

    $level_params = $params;
    $level_params[] = MIN_RESPONSE_COUNT;
    $level_types = $types . 'i';

    $stmt_levels = mysqli_prepare($conn, $query_levels);
    mysqli_stmt_bind_param($stmt_levels, $level_types, ...$level_params);
    mysqli_stmt_execute($stmt_levels);
    $result_levels = mysqli_stmt_get_result($stmt_levels);

    while ($row = mysqli_fetch_assoc($result_levels)) {
        $row['completion_rate'] = $row['level_tokens'] > 0 ?
            round(($row['level_completed'] / $row['level_tokens']) * 100, 1) : 0;
        $level_stats[] = $row;
    }
    mysqli_stmt_close($stmt_levels);

    // Get category performance
    $query_categories = "
        SELECT
            eq.category,
            COUNT(r.id) as response_count,
            AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating,
            MIN(CAST(r.response_value AS DECIMAL(10,2))) as min_rating,
            MAX(CAST(r.response_value AS DECIMAL(10,2))) as max_rating
        FROM evaluation_questions eq
        JOIN responses r ON eq.question_id = r.question_id
        JOIN evaluations e ON r.evaluation_id = e.evaluation_id
        JOIN evaluation_tokens et ON e.token = et.token
        $where_clause
        GROUP BY eq.category
        HAVING response_count >= ?
        ORDER BY avg_rating DESC
    ";

    $cat_params = $params;
    $cat_params[] = MIN_RESPONSE_COUNT;
    $cat_types = $types . 'i';

    $stmt_categories = mysqli_prepare($conn, $query_categories);
    mysqli_stmt_bind_param($stmt_categories, $cat_types, ...$cat_params);
    mysqli_stmt_execute($stmt_categories);
    $result_categories = mysqli_stmt_get_result($stmt_categories);

    $category_stats = [];
    while ($row = mysqli_fetch_assoc($result_categories)) {
        $row['avg_rating'] = round($row['avg_rating'], 2);
        $category_stats[] = $row;
    }
    mysqli_stmt_close($stmt_categories);
}

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'Reports' => 'index.php',
    'Institution Overview' => ''
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
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        text-align: center;
    }

    .stat-value {
        font-size: 36px;
        font-weight: bold;
        color: #667eea;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 14px;
        color: #666;
    }

    .section-title {
        font-size: 20px;
        font-weight: 600;
        color: #333;
        margin: 30px 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }

    .level-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 15px;
    }

    .level-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .level-name {
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }

    .level-completion {
        font-size: 24px;
        font-weight: bold;
        color: #667eea;
    }

    .progress-container {
        background: #e0e0e0;
        height: 10px;
        border-radius: 5px;
        overflow: hidden;
        margin-top: 10px;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s;
    }

    .category-grid {
        display: grid;
        gap: 15px;
    }

    .category-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-left: 4px solid #667eea;
    }

    .category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .category-name {
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }

    .category-rating {
        font-size: 24px;
        font-weight: bold;
        color: #667eea;
    }

    .category-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e0e0e0;
    }

    .category-stat-item {
        text-align: center;
    }

    .category-stat-value {
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }

    .category-stat-label {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }

    .alert-warning-custom {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        text-align: center;
    }

    .alert-info-custom {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1>Institution Overview Report</h1>
    <p>Comprehensive evaluation statistics across the institution</p>
</div>

<!-- Filters -->
<div class="filters-section">
    <form method="GET" action="">
        <div class="filters-grid">
            <div class="filter-group">
                <label for="academic_year_id">Academic Year</label>
                <select name="academic_year_id" id="academic_year_id">
                    <option value="0">All Years</option>
                    <?php foreach ($academic_years as $year): ?>
                        <option value="<?php echo $year['academic_year_id']; ?>"
                                <?php echo $filter_academic_year == $year['academic_year_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year['year_label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="semester_id">Semester</label>
                <select name="semester_id" id="semester_id">
                    <option value="0">All Semesters</option>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?php echo $semester['semester_id']; ?>"
                                <?php echo $filter_semester == $semester['semester_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($semester['semester_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Generate Report</button>
            <a href="institution_overview.php" class="btn btn-secondary">Reset</a>
            <?php if ($sufficient_data): ?>
                <button type="button" onclick="window.print()" class="btn btn-secondary">Print Report</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!$sufficient_data): ?>
    <!-- Insufficient Data Warning -->
    <div class="alert-warning-custom">
        <h3 style="margin: 0 0 10px 0;">📊 Insufficient Data</h3>
        <p style="margin: 0;">
            Only <strong><?php echo $completed_tokens; ?></strong> evaluation(s) found.<br>
            At least <strong><?php echo MIN_RESPONSE_COUNT; ?></strong> evaluations required to display statistics.
        </p>
    </div>
<?php else: ?>

    <!-- Overall Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $completion_rate; ?>%</div>
            <div class="stat-label">Completion Rate</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $completed_tokens; ?></div>
            <div class="stat-label">Total Submissions</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $overall_stats['active_students']; ?></div>
            <div class="stat-label">Active Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $overall_stats['total_courses']; ?></div>
            <div class="stat-label">Courses Evaluated</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $overall_stats['total_departments']; ?></div>
            <div class="stat-label">Departments</div>
        </div>
    </div>

    <!-- Completion by Level -->
    <?php if (!empty($level_stats)): ?>
        <h2 class="section-title">Completion Rates by Level</h2>
        <?php foreach ($level_stats as $level): ?>
            <div class="level-card">
                <div class="level-header">
                    <span class="level-name"><?php echo htmlspecialchars($level['level_name']); ?></span>
                    <span class="level-completion"><?php echo $level['completion_rate']; ?>%</span>
                </div>
                <div>
                    <?php echo $level['level_completed']; ?> / <?php echo $level['level_tokens']; ?> evaluations completed
                </div>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?php echo $level['completion_rate']; ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Category Performance -->
    <?php if (!empty($category_stats)): ?>
        <h2 class="section-title">Performance by Category</h2>
        <div class="category-grid">
            <?php foreach ($category_stats as $cat): ?>
                <div class="category-card">
                    <div class="category-header">
                        <span class="category-name"><?php echo htmlspecialchars($cat['category']); ?></span>
                        <span class="category-rating"><?php echo $cat['avg_rating']; ?>/5.0</span>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo ($cat['avg_rating'] / 5) * 100; ?>%;"></div>
                    </div>
                    <div class="category-stats">
                        <div class="category-stat-item">
                            <div class="category-stat-value"><?php echo $cat['response_count']; ?></div>
                            <div class="category-stat-label">Responses</div>
                        </div>
                        <div class="category-stat-item">
                            <div class="category-stat-value"><?php echo $cat['min_rating']; ?></div>
                            <div class="category-stat-label">Minimum</div>
                        </div>
                        <div class="category-stat-item">
                            <div class="category-stat-value"><?php echo $cat['max_rating']; ?></div>
                            <div class="category-stat-label">Maximum</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Report Summary -->
    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h3 style="margin: 0 0 10px 0;">Report Summary</h3>
        <p style="margin: 0; color: #666; font-size: 14px;">
            Generated on <?php echo date('F d, Y h:i A'); ?>
            <?php if ($filter_academic_year > 0 || $filter_semester > 0): ?>
                <br><strong>Filters Applied:</strong>
                <?php
                $filters = [];
                if ($filter_academic_year > 0) {
                    foreach ($academic_years as $y) {
                        if ($y['academic_year_id'] == $filter_academic_year) {
                            $filters[] = "Academic Year: " . $y['year_label'];
                            break;
                        }
                    }
                }
                if ($filter_semester > 0) {
                    foreach ($semesters as $s) {
                        if ($s['semester_id'] == $filter_semester) {
                            $filters[] = "Semester: " . $s['semester_name'];
                            break;
                        }
                    }
                }
                echo implode(' | ', $filters);
                ?>
            <?php endif; ?>
        </p>
    </div>

<?php endif; ?>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
