<?php

/**
 * Advisor Class Report
 *
 * Displays evaluation statistics and feedback for the advisor's assigned classes.
 * Shows aggregate data by question category with anonymity protection.
 *
 * Features:
 * - Filter by level and academic period
 * - Aggregate statistics by question category
 * - Question-by-question breakdown
 * - Response rate tracking
 * - Anonymity protection (hides data if responses < 5)
 * - Export to PDF/CSV options
 * - Visual charts and graphs
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
if ($_SESSION['role_id'] != ROLE_ADVISOR) {
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
$page_title = 'Class Report';

// Get filter parameters
$filter_level = isset($_GET['level_id']) ? intval($_GET['level_id']) : 0;
$filter_academic_year = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$filter_semester = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;

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

// Initialize variables
$report_data = null;
$category_stats = [];
$question_stats = [];
$total_evaluations = 0;
$total_responses = 0;
$sufficient_data = false;

// If no levels assigned, show message
if (empty($level_ids)) {
    $no_assignment = true;
} else {
    $no_assignment = false;

    // Build base WHERE clause for filtering
    $where_conditions = ["u.level_id IN (" . implode(',', array_fill(0, count($level_ids), '?')) . ")"];
    $where_conditions[] = "u.department_id = ?";

    if ($filter_level > 0 && in_array($filter_level, $level_ids)) {
        $where_conditions[] = "e.course_id IN (SELECT id FROM courses WHERE level_id = ?)";
    }

    if ($filter_academic_year > 0) {
        $where_conditions[] = "e.academic_year_id = ?";
    }

    if ($filter_semester > 0) {
        $where_conditions[] = "e.semester_id = ?";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Get total evaluations count
    $query_count = "
        SELECT COUNT(DISTINCT e.evaluation_id) as total_evals
        FROM evaluations e
        JOIN evaluation_tokens et ON e.token = et.token
        JOIN user_details u ON et.student_user_id = u.user_id
        WHERE $where_clause
    ";

    $stmt_count = mysqli_prepare($conn, $query_count);

    // Bind parameters for count query
    $types = str_repeat('i', count($level_ids)) . 'i'; // level_ids + department_id
    $params = array_merge($level_ids, [$department_id]);

    if ($filter_level > 0 && in_array($filter_level, $level_ids)) {
        $types .= 'i';
        $params[] = $filter_level;
    }
    if ($filter_academic_year > 0) {
        $types .= 'i';
        $params[] = $filter_academic_year;
    }
    if ($filter_semester > 0) {
        $types .= 'i';
        $params[] = $filter_semester;
    }

    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    mysqli_stmt_execute($stmt_count);
    $result_count = mysqli_stmt_get_result($stmt_count);
    $count_data = mysqli_fetch_assoc($result_count);
    $total_evaluations = $count_data['total_evals'];
    mysqli_stmt_close($stmt_count);

    // Check if we have sufficient data for display
    $sufficient_data = $total_evaluations >= MIN_RESPONSE_COUNT;

    if ($sufficient_data) {
        // Get statistics by category
        $query_category = "
            SELECT
                eq.category,
                COUNT(DISTINCT r.id) as response_count,
                AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating,
                MIN(CAST(r.response_value AS DECIMAL(10,2))) as min_rating,
                MAX(CAST(r.response_value AS DECIMAL(10,2))) as max_rating,
                STDDEV(CAST(r.response_value AS DECIMAL(10,2))) as std_rating
            FROM responses r
            JOIN evaluations e ON r.evaluation_id = e.evaluation_id
            JOIN evaluation_questions eq ON r.question_id = eq.question_id
            JOIN evaluation_tokens et ON e.token = et.token
            JOIN user_details u ON et.student_user_id = u.user_id
            WHERE $where_clause
            GROUP BY eq.category
            ORDER BY avg_rating DESC
        ";

        $stmt_category = mysqli_prepare($conn, $query_category);
        mysqli_stmt_bind_param($stmt_category, $types, ...$params);
        mysqli_stmt_execute($stmt_category);
        $result_category = mysqli_stmt_get_result($stmt_category);

        while ($row = mysqli_fetch_assoc($result_category)) {
            $category_stats[] = [
                'category' => $row['category'],
                'response_count' => $row['response_count'],
                'avg_rating' => round($row['avg_rating'], 2),
                'min_rating' => $row['min_rating'],
                'max_rating' => $row['max_rating'],
                'std_rating' => round($row['std_rating'], 2)
            ];
        }
        mysqli_stmt_close($stmt_category);

        // Get statistics by question
        $query_questions = "
            SELECT
                eq.question_id,
                eq.question_text,
                eq.category,
                COUNT(r.id) as response_count,
                AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating,
                MIN(CAST(r.response_value AS DECIMAL(10,2))) as min_rating,
                MAX(CAST(r.response_value AS DECIMAL(10,2))) as max_rating,
                STDDEV(CAST(r.response_value AS DECIMAL(10,2))) as std_rating
            FROM evaluation_questions eq
            LEFT JOIN responses r ON eq.question_id = r.question_id
            LEFT JOIN evaluations e ON r.evaluation_id = e.evaluation_id
            LEFT JOIN evaluation_tokens et ON e.token = et.token
            LEFT JOIN user_details u ON et.student_user_id = u.user_id
            WHERE eq.is_active = 1
            AND ($where_clause OR r.id IS NULL)
            GROUP BY eq.question_id
            ORDER BY eq.category, eq.display_order
        ";

        $stmt_questions = mysqli_prepare($conn, $query_questions);
        mysqli_stmt_bind_param($stmt_questions, $types, ...$params);
        mysqli_stmt_execute($stmt_questions);
        $result_questions = mysqli_stmt_get_result($stmt_questions);

        while ($row = mysqli_fetch_assoc($result_questions)) {
            $question_stats[] = [
                'question_id' => $row['question_id'],
                'question_text' => $row['question_text'],
                'category' => $row['category'],
                'response_count' => $row['response_count'] ?? 0,
                'avg_rating' => $row['avg_rating'] ? round($row['avg_rating'], 2) : 0,
                'min_rating' => $row['min_rating'] ?? 0,
                'max_rating' => $row['max_rating'] ?? 0,
                'std_rating' => $row['std_rating'] ? round($row['std_rating'], 2) : 0
            ];
        }
        mysqli_stmt_close($stmt_questions);

        // Count total responses
        $total_responses = array_sum(array_column($category_stats, 'response_count'));
    }
}

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'Reports' => 'index.php',
    'Class Report' => ''
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

    .overview-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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

    .category-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 15px;
    }

    .category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .category-name {
        font-size: 18px;
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
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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

    .questions-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-top: 20px;
    }

    .questions-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .questions-table th {
        background: #f8f9fa;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #333;
        font-size: 13px;
        border-bottom: 2px solid #e0e0e0;
    }

    .questions-table td {
        padding: 12px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
    }

    .questions-table tr:hover {
        background: #f8f9fa;
    }

    .rating-bar {
        height: 8px;
        background: #e0e0e0;
        border-radius: 4px;
        overflow: hidden;
        margin: 5px 0;
    }

    .rating-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s;
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
    <h1>Class Performance Report</h1>
    <p>Evaluation statistics for your assigned classes</p>
</div>

<?php if ($no_assignment): ?>
    <!-- No Assignment Message -->
    <div class="alert-info-custom">
        <strong>No Class Assignments</strong><br>
        You have not been assigned to any classes yet. Please contact your department head.
    </div>
<?php else: ?>

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

                <!-- Academic Year Filter -->
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

                <!-- Semester Filter -->
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
                <a href="class_report.php" class="btn btn-secondary">Reset Filters</a>
                <?php if ($sufficient_data): ?>
                    <button type="button" onclick="window.print()" class="btn btn-success">Print Report</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (!$sufficient_data && $total_evaluations > 0): ?>
        <!-- Insufficient Data Warning -->
        <div class="alert-warning-custom">
            <h3 style="margin: 0 0 10px 0;">📊 Insufficient Data for Display</h3>
            <p style="margin: 0;">
                Only <strong><?php echo $total_evaluations; ?></strong> evaluation(s) found for the selected filters.<br>
                At least <strong><?php echo MIN_RESPONSE_COUNT; ?></strong> evaluations are required to display statistics
                (anonymity protection).
            </p>
            <p style="margin: 10px 0 0 0; font-size: 14px;">
                Try removing some filters or wait for more students to complete evaluations.
            </p>
        </div>
    <?php elseif ($sufficient_data): ?>

        <!-- Overview Statistics -->
        <div class="overview-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_evaluations; ?></div>
                <div class="stat-label">Total Evaluations</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_responses; ?></div>
                <div class="stat-label">Total Responses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php
                    $overall_avg = !empty($category_stats) ?
                        round(array_sum(array_column($category_stats, 'avg_rating')) / count($category_stats), 2) : 0;
                    echo $overall_avg;
                    ?>
                </div>
                <div class="stat-label">Overall Average Rating</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($category_stats); ?></div>
                <div class="stat-label">Categories Evaluated</div>
            </div>
        </div>

        <!-- Category Statistics -->
        <h2 class="section-title">Performance by Category</h2>

        <?php if (empty($category_stats)): ?>
            <div class="no-data">No category data available</div>
        <?php else: ?>
            <?php foreach ($category_stats as $cat): ?>
                <div class="category-card">
                    <div class="category-header">
                        <div class="category-name">
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </div>
                        <div class="category-rating">
                            <?php echo $cat['avg_rating']; ?>/5.0
                        </div>
                    </div>

                    <div class="rating-bar">
                        <div class="rating-bar-fill" style="width: <?php echo ($cat['avg_rating'] / 5) * 100; ?>%;"></div>
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
                        <div class="category-stat-item">
                            <div class="category-stat-value"><?php echo $cat['std_rating']; ?></div>
                            <div class="category-stat-label">Std. Deviation</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Question-by-Question Breakdown -->
        <h2 class="section-title">Question-by-Question Analysis</h2>

        <?php if (empty($question_stats)): ?>
            <div class="no-data">No question data available</div>
        <?php else: ?>
            <div class="questions-table">
                <table>
                    <thead>
                        <tr>
                            <th scope="col" style="width: 40%;">Question</th>
                            <th scope="col" style="width: 15%;">Category</th>
                            <th scope="col" style="width: 10%;">Responses</th>
                            <th scope="col" style="width: 10%;">Avg Rating</th>
                            <th scope="col" style="width: 10%;">Min</th>
                            <th scope="col" style="width: 10%;">Max</th>
                            <th scope="col" style="width: 5%;">Std Dev</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $current_category = '';
                        foreach ($question_stats as $q):
                            // Show category header if changed
                            if ($current_category != $q['category']):
                                $current_category = $q['category'];
                        ?>
                                <tr style="background: #f0f0f0;">
                                    <td colspan="7" style="font-weight: bold; padding: 10px;">
                                        <?php echo htmlspecialchars($current_category); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <tr>
                                <td><?php echo htmlspecialchars($q['question_text']); ?></td>
                                <td><?php echo htmlspecialchars($q['category']); ?></td>
                                <td><?php echo $q['response_count']; ?></td>
                                <td>
                                    <strong><?php echo $q['avg_rating']; ?></strong>
                                    <div class="rating-bar" style="width: 60px;">
                                        <div class="rating-bar-fill" style="width: <?php echo ($q['avg_rating'] / 5) * 100; ?>%;"></div>
                                    </div>
                                </td>
                                <td><?php echo $q['min_rating']; ?></td>
                                <td><?php echo $q['max_rating']; ?></td>
                                <td><?php echo $q['std_rating']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Report Summary -->
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3 style="margin: 0 0 10px 0;">Report Summary</h3>
            <p style="margin: 0; color: #666; font-size: 14px;">
                Generated on <?php echo date('F d, Y h:i A'); ?> by <?php echo htmlspecialchars($advisor_name); ?>
            </p>
            <?php if ($filter_level > 0 || $filter_academic_year > 0 || $filter_semester > 0): ?>
                <p style="margin: 10px 0 0 0; color: #666; font-size: 14px;">
                    <strong>Filters Applied:</strong>
                    <?php
                    $filters_applied = [];
                    if ($filter_level > 0) {
                        $level_name = '';
                        foreach ($assigned_levels as $l) {
                            if ($l['level_id'] == $filter_level) {
                                $level_name = $l['level_name'];
                                break;
                            }
                        }
                        $filters_applied[] = "Level: $level_name";
                    }
                    if ($filter_academic_year > 0) {
                        foreach ($academic_years as $y) {
                            if ($y['academic_year_id'] == $filter_academic_year) {
                                $filters_applied[] = "Academic Year: " . $y['year_label'];
                                break;
                            }
                        }
                    }
                    if ($filter_semester > 0) {
                        foreach ($semesters as $s) {
                            if ($s['semester_id'] == $filter_semester) {
                                $filters_applied[] = "Semester: " . $s['semester_name'];
                                break;
                            }
                        }
                    }
                    echo implode(' | ', $filters_applied);
                    ?>
                </p>
            <?php endif; ?>
        </div>

    <?php elseif ($total_evaluations == 0): ?>
        <!-- No Data Message -->
        <div class="alert-info-custom">
            <strong>No Evaluations Found</strong><br>
            No evaluations have been submitted for the selected filters yet.
            Please wait for students to complete their evaluations or try different filter options.
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
