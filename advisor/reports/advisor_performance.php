<?php

/**
 * Advisor Performance Report
 *
 * Shows how students have rated the advisor across all course evaluations.
 * Displays aggregate feedback on advisor performance with anonymity protection.
 *
 * Features:
 * - Overall advisor rating
 * - Rating distribution (how many gave 1, 2, 3, 4, 5)
 * - Trend over time (by academic year/semester)
 * - Filter by level and period
 * - Anonymity protection (min 5 responses)
 * - Performance insights
 *
 * Note: Advisor rating question is included in all course evaluations
 * Question: "How would you rate the performance of your class advisor?"
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
$page_title = 'My Advisor Performance';

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
$no_assignment = empty($level_ids);
$sufficient_data = false;
$overall_rating = 0;
$total_responses = 0;
$rating_distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$performance_by_period = [];

if (!$no_assignment) {
    // Build WHERE conditions
    $where_conditions = ["u.level_id IN (" . implode(',', array_fill(0, count($level_ids), '?')) . ")"];
    $where_conditions[] = "u.department_id = ?";
    $where_conditions[] = "eq.question_text LIKE '%advisor%'"; // Advisor performance question

    if ($filter_level > 0 && in_array($filter_level, $level_ids)) {
        $where_conditions[] = "u.level_id = ?";
    }
    if ($filter_academic_year > 0) {
        $where_conditions[] = "e.academic_year_id = ?";
    }
    if ($filter_semester > 0) {
        $where_conditions[] = "e.semester_id = ?";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Build parameters
    $types = str_repeat('i', count($level_ids)) . 'i';
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

    // Get overall rating statistics
    $query_overall = "
        SELECT
            AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating,
            COUNT(r.id) as response_count,
            MIN(CAST(r.response_value AS DECIMAL(10,2))) as min_rating,
            MAX(CAST(r.response_value AS DECIMAL(10,2))) as max_rating,
            STDDEV(CAST(r.response_value AS DECIMAL(10,2))) as std_rating
        FROM responses r
        JOIN evaluations e ON r.evaluation_id = e.evaluation_id
        JOIN evaluation_questions eq ON r.question_id = eq.question_id
        JOIN evaluation_tokens et ON e.token = et.token
        JOIN user_details u ON et.student_user_id = u.user_id
        WHERE $where_clause
    ";

    $stmt_overall = mysqli_prepare($conn, $query_overall);
    mysqli_stmt_bind_param($stmt_overall, $types, ...$params);
    mysqli_stmt_execute($stmt_overall);
    $result_overall = mysqli_stmt_get_result($stmt_overall);
    $overall_data = mysqli_fetch_assoc($result_overall);

    $total_responses = $overall_data['response_count'];
    $sufficient_data = $total_responses >= MIN_RESPONSE_COUNT;

    if ($sufficient_data) {
        $overall_rating = round($overall_data['avg_rating'], 2);
        $min_rating = $overall_data['min_rating'];
        $max_rating = $overall_data['max_rating'];
        $std_rating = round($overall_data['std_rating'], 2);

        // Get rating distribution
        $query_distribution = "
            SELECT
                r.response_value,
                COUNT(*) as count
            FROM responses r
            JOIN evaluations e ON r.evaluation_id = e.evaluation_id
            JOIN evaluation_questions eq ON r.question_id = eq.question_id
            JOIN evaluation_tokens et ON e.token = et.token
            JOIN user_details u ON et.student_user_id = u.user_id
            WHERE $where_clause
            GROUP BY r.response_value
            ORDER BY r.response_value
        ";

        $stmt_dist = mysqli_prepare($conn, $query_distribution);
        mysqli_stmt_bind_param($stmt_dist, $types, ...$params);
        mysqli_stmt_execute($stmt_dist);
        $result_dist = mysqli_stmt_get_result($stmt_dist);

        while ($row = mysqli_fetch_assoc($result_dist)) {
            $rating = intval($row['response_value']);
            if ($rating >= 1 && $rating <= 5) {
                $rating_distribution[$rating] = $row['count'];
            }
        }
        mysqli_stmt_close($stmt_dist);

        // Get performance by period (academic year and semester)
        $query_period = "
            SELECT
                ay.year_label,
                s.semester_name,
                e.academic_year_id,
                e.semester_id,
                AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating,
                COUNT(r.id) as response_count
            FROM responses r
            JOIN evaluations e ON r.evaluation_id = e.evaluation_id
            JOIN evaluation_questions eq ON r.question_id = eq.question_id
            JOIN evaluation_tokens et ON e.token = et.token
            JOIN user_details u ON et.student_user_id = u.user_id
            JOIN academic_year ay ON e.academic_year_id = ay.academic_year_id
            JOIN semesters s ON e.semester_id = s.semester_id
            WHERE $where_clause
            GROUP BY e.academic_year_id, e.semester_id
            HAVING response_count >= ?
            ORDER BY ay.start_year DESC, s.semester_value
        ";

        $types_period = $types . 'i';
        $params_period = array_merge($params, [MIN_RESPONSE_COUNT]);

        $stmt_period = mysqli_prepare($conn, $query_period);
        mysqli_stmt_bind_param($stmt_period, $types_period, ...$params_period);
        mysqli_stmt_execute($stmt_period);
        $result_period = mysqli_stmt_get_result($stmt_period);

        while ($row = mysqli_fetch_assoc($result_period)) {
            $performance_by_period[] = [
                'period' => $row['year_label'] . ' - ' . $row['semester_name'],
                'avg_rating' => round($row['avg_rating'], 2),
                'response_count' => $row['response_count']
            ];
        }
        mysqli_stmt_close($stmt_period);
    }

    mysqli_stmt_close($stmt_overall);
}

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'Reports' => 'index.php',
    'My Performance' => ''
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

    .rating-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 60px 40px;
        border-radius: 12px;
        text-align: center;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .rating-value {
        font-size: 96px;
        font-weight: bold;
        margin: 20px 0;
        text-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .rating-label {
        font-size: 24px;
        opacity: 0.9;
        margin-bottom: 10px;
    }

    .rating-stars {
        font-size: 48px;
        margin: 15px 0;
    }

    .rating-meta {
        font-size: 16px;
        opacity: 0.8;
        margin-top: 20px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 25px;
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

    .distribution-chart {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    .bar-chart {
        margin-top: 20px;
    }

    .bar-item {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .bar-label {
        width: 80px;
        font-weight: 600;
        color: #333;
    }

    .bar-container {
        flex: 1;
        height: 40px;
        background: #e0e0e0;
        border-radius: 5px;
        overflow: hidden;
        position: relative;
    }

    .bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.5s ease;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 10px;
        color: white;
        font-weight: 600;
    }

    .trend-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .trend-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .trend-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #e0e0e0;
    }

    .trend-table td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
    }

    .trend-table tr:hover {
        background: #f8f9fa;
    }

    .alert-warning-custom {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        padding: 30px;
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

    .insight-box {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #667eea;
        margin-top: 20px;
    }

    .insight-box h3 {
        margin: 0 0 10px 0;
        color: #333;
        font-size: 18px;
    }

    .insight-box p {
        margin: 0;
        color: #666;
        line-height: 1.6;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1>My Advisor Performance</h1>
    <p>Student feedback on your performance as class advisor</p>
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
                <a href="advisor_performance.php" class="btn btn-secondary">Reset Filters</a>
            </div>
        </form>
    </div>

    <?php if (!$sufficient_data && $total_responses > 0): ?>
        <!-- Insufficient Data Warning -->
        <div class="alert-warning-custom">
            <h3 style="margin: 0 0 10px 0;">📊 Insufficient Data for Display</h3>
            <p style="margin: 0;">
                Only <strong><?php echo $total_responses; ?></strong> rating(s) found for the selected filters.<br>
                At least <strong><?php echo MIN_RESPONSE_COUNT; ?></strong> ratings are required to display performance data
                (anonymity protection).
            </p>
            <p style="margin: 10px 0 0 0; font-size: 14px;">
                Try removing some filters or wait for more students to complete evaluations.
            </p>
        </div>
    <?php elseif ($sufficient_data): ?>

        <!-- Overall Rating Hero Section -->
        <div class="rating-hero">
            <div class="rating-label">Your Overall Advisor Rating</div>
            <div class="rating-value"><?php echo $overall_rating; ?><span style="font-size: 48px;">/5.0</span></div>
            <div class="rating-stars">
                <?php
                $full_stars = floor($overall_rating);
                $half_star = ($overall_rating - $full_stars) >= 0.5;
                for ($i = 0; $i < $full_stars; $i++) echo '⭐';
                if ($half_star) echo '⭐';
                ?>
            </div>
            <div class="rating-meta">
                Based on <?php echo $total_responses; ?> student rating(s) from your assigned classes
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_responses; ?></div>
                <div class="stat-label">Total Ratings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $min_rating; ?></div>
                <div class="stat-label">Lowest Rating</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $max_rating; ?></div>
                <div class="stat-label">Highest Rating</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $std_rating; ?></div>
                <div class="stat-label">Standard Deviation</div>
            </div>
        </div>

        <!-- Rating Distribution -->
        <h2 class="section-title">Rating Distribution</h2>
        <div class="distribution-chart">
            <p style="color: #666; margin-bottom: 20px;">
                How students rated your performance (1 = Very Poor, 5 = Excellent)
            </p>
            <div class="bar-chart">
                <?php
                $max_count = max($rating_distribution);
                foreach ([5, 4, 3, 2, 1] as $rating):
                    $count = $rating_distribution[$rating];
                    $percentage = $max_count > 0 ? ($count / $max_count) * 100 : 0;
                ?>
                    <div class="bar-item">
                        <div class="bar-label"><?php echo $rating; ?> Star<?php echo $rating != 1 ? 's' : ''; ?></div>
                        <div class="bar-container">
                            <div class="bar-fill" style="width: <?php echo $percentage; ?>%;">
                                <?php if ($count > 0): ?>
                                    <?php echo $count; ?> (<?php echo round(($count / $total_responses) * 100, 1); ?>%)
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Performance Trend by Period -->
        <?php if (!empty($performance_by_period)): ?>
            <h2 class="section-title">Performance Trend Over Time</h2>
            <div class="trend-table">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Period</th>
                            <th scope="col">Average Rating</th>
                            <th scope="col">Number of Ratings</th>
                            <th scope="col">Visual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($performance_by_period as $period): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($period['period']); ?></strong></td>
                                <td><?php echo $period['avg_rating']; ?> / 5.0</td>
                                <td><?php echo $period['response_count']; ?> rating(s)</td>
                                <td style="width: 200px;">
                                    <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); width: <?php echo ($period['avg_rating'] / 5) * 100; ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Performance Insights -->
        <div class="insight-box">
            <h3>💡 Performance Insight</h3>
            <p>
                <?php if ($overall_rating >= 4.5): ?>
                    Excellent work! Your students consistently rate you very highly as their class advisor.
                    Keep up the great support and mentorship.
                <?php elseif ($overall_rating >= 4.0): ?>
                    Great performance! Students appreciate your work as their advisor.
                    Continue providing strong support to your classes.
                <?php elseif ($overall_rating >= 3.5): ?>
                    Good performance overall. Consider engaging more with students to understand
                    their needs and provide additional support where possible.
                <?php elseif ($overall_rating >= 3.0): ?>
                    Satisfactory performance. There may be areas where students need more support.
                    Consider meeting with your classes to discuss how you can better assist them.
                <?php else: ?>
                    Your rating suggests students may need more support from their advisor.
                    Consider scheduling regular meetings with your assigned classes to understand
                    their concerns and provide better guidance.
                <?php endif; ?>
            </p>
            <?php if ($std_rating > 1.0): ?>
                <p style="margin-top: 10px;">
                    <strong>Note:</strong> The relatively high standard deviation (<?php echo $std_rating; ?>)
                    indicates varied opinions among students. Some students may have very different
                    experiences or expectations. Consider reaching out to understand these differences.
                </p>
            <?php endif; ?>
        </div>

        <!-- Report Footer -->
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; font-size: 14px; color: #666;">
            <p style="margin: 0;">
                <strong>Note:</strong> This rating is based on the advisor performance question included in all course evaluations.
                Students rate their class advisor each time they evaluate a course. All ratings are completely anonymous
                and cannot be traced back to individual students.
            </p>
            <p style="margin: 10px 0 0 0;">
                Report generated on <?php echo date('F d, Y h:i A'); ?> for <?php echo htmlspecialchars($advisor_name); ?>
            </p>
        </div>

    <?php elseif ($total_responses == 0): ?>
        <!-- No Data Message -->
        <div class="alert-info-custom">
            <strong>No Ratings Yet</strong><br>
            No students have rated your advisor performance yet.
            Ratings will appear here once students complete course evaluations that include
            the advisor performance question.
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
