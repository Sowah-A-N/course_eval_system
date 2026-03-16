<?php

/**
 * Quality Assurance Dashboard
 *
 * Main dashboard for Quality Assurance officers showing institution-wide
 * evaluation statistics and performance metrics.
 *
 * Features:
 * - Overall evaluation completion statistics
 * - Department performance summary
 * - Category-wise performance overview
 * - Recent evaluation activity
 * - Quick links to detailed reports
 * - Anonymity protection (min 5 responses)
 *
 * Role Required: ROLE_QUALITY (assuming role exists)
 * Note: If ROLE_QUALITY doesn't exist, this would be accessible by ROLE_ADMIN
 */

// Include required files
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';

// Start session and check login
start_secure_session();

check_login();

// Allow both admin and quality roles
if ($_SESSION['role_id'] != ROLE_ADMIN && $_SESSION['role_id'] != ROLE_QUALITY) {
    $_SESSION['flash_message'] = 'Access denied. This page is only for quality assurance officers.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../index.php");
    exit();
}

// Get user information
$user_name = $_SESSION['full_name'];

// Set page title
$page_title = 'Quality Assurance Dashboard';

// Get active academic period
$query_period = "SELECT * FROM view_active_period LIMIT 1";
$result_period = mysqli_query($conn, $query_period);
$active_period = mysqli_fetch_assoc($result_period);

if (!$active_period) {
    $active_year = "No Active Period";
    $active_semester = "";
} else {
    $active_year = $active_period['academic_year'];
    $active_semester = $active_period['semester_name'];
}

// Get overall statistics
$query_overall = "
    SELECT
        COUNT(DISTINCT et.token_id) as total_tokens,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as completed_tokens,
        COUNT(DISTINCT et.student_user_id) as total_students,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.student_user_id END) as active_students,
        COUNT(DISTINCT et.course_id) as total_courses
    FROM evaluation_tokens et
";

$result_overall = mysqli_query($conn, $query_overall);
$overall_stats = mysqli_fetch_assoc($result_overall);

$total_tokens = $overall_stats['total_tokens'];
$completed_tokens = $overall_stats['completed_tokens'];
$completion_rate = $total_tokens > 0 ? round(($completed_tokens / $total_tokens) * 100, 1) : 0;
$total_students = $overall_stats['total_students'];
$active_students = $overall_stats['active_students'];
$total_courses = $overall_stats['total_courses'];

// Get department statistics
$query_departments = "
    SELECT
        d.t_id,
        d.dep_name,
        d.dep_code,
        COUNT(DISTINCT et.token_id) as dept_tokens,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as dept_completed
    FROM department d
    LEFT JOIN courses c ON d.t_id = c.department_id
    LEFT JOIN evaluation_tokens et ON c.id = et.course_id
    GROUP BY d.t_id
    HAVING dept_completed >= ?
    ORDER BY d.dep_name
";

$min_responses = MIN_RESPONSE_COUNT;
$stmt_departments = mysqli_prepare($conn, $query_departments);
mysqli_stmt_bind_param($stmt_departments, "i", $min_responses);
mysqli_stmt_execute($stmt_departments);
$result_departments = mysqli_stmt_get_result($stmt_departments);

$departments = [];
while ($row = mysqli_fetch_assoc($result_departments)) {
    if ($row['dept_tokens'] > 0) {
        $row['completion_rate'] = round(($row['dept_completed'] / $row['dept_tokens']) * 100, 1);
    } else {
        $row['completion_rate'] = 0;
    }
    $departments[] = $row;
}
mysqli_stmt_close($stmt_departments);

// Get category performance (only if sufficient responses)
$query_categories = "
    SELECT
        eq.category,
        COUNT(r.id) as response_count,
        AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating
    FROM evaluation_questions eq
    LEFT JOIN responses r ON eq.question_id = r.question_id
    WHERE eq.is_active = 1
    GROUP BY eq.category
    HAVING response_count >= ?
    ORDER BY avg_rating DESC
";

$stmt_categories = mysqli_prepare($conn, $query_categories);
mysqli_stmt_bind_param($stmt_categories, "i", $min_responses);
mysqli_stmt_execute($stmt_categories);
$result_categories = mysqli_stmt_get_result($stmt_categories);

$categories = [];
while ($row = mysqli_fetch_assoc($result_categories)) {
    $row['avg_rating'] = round($row['avg_rating'], 2);
    $categories[] = $row;
}
mysqli_stmt_close($stmt_categories);

// Get recent evaluation activity (last 10 submissions)
$query_recent = "
    SELECT
        et.used_at,
        c.course_code,
        c.name as course_name,
        d.dep_name
    FROM evaluation_tokens et
    JOIN courses c ON et.course_id = c.id
    JOIN department d ON c.department_id = d.t_id
    WHERE et.is_used = 1
    ORDER BY et.used_at DESC
    LIMIT 10
";

$result_recent = mysqli_query($conn, $query_recent);
$recent_activity = [];
while ($row = mysqli_fetch_assoc($result_recent)) {
    $recent_activity[] = $row;
}

// Include header
require_once '../includes/header.php';
?>

<style>
    .welcome-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .welcome-banner h1 {
        margin: 0 0 10px 0;
        font-size: 32px;
    }

    .welcome-banner p {
        margin: 0;
        font-size: 16px;
        opacity: 0.9;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        font-size: 40px;
        margin-bottom: 15px;
    }

    .stat-value {
        font-size: 42px;
        font-weight: bold;
        color: #667eea;
        margin-bottom: 5px;
    }

    .stat-label {
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
    }

    .stat-sublabel {
        font-size: 12px;
        color: #999;
    }

    .progress-container {
        background: #e0e0e0;
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 10px;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s;
    }

    .section-title {
        font-size: 20px;
        font-weight: 600;
        color: #333;
        margin: 30px 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }

    .report-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .report-card {
        background: white;
        border-radius: 8px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .report-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 15px rgba(102, 126, 234, 0.3);
    }

    .report-icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .report-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
    }

    .report-description {
        font-size: 14px;
        color: #666;
        line-height: 1.5;
    }

    .department-list {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .department-item {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .department-item:last-child {
        border-bottom: none;
    }

    .department-info {
        flex: 1;
    }

    .department-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }

    .department-meta {
        font-size: 13px;
        color: #999;
    }

    .department-stats {
        text-align: right;
    }

    .department-completion {
        font-size: 24px;
        font-weight: bold;
        color: #667eea;
    }

    .category-list {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .category-item {
        padding: 15px;
        margin-bottom: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }

    .category-item:last-child {
        margin-bottom: 0;
    }

    .category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .category-name {
        font-weight: 600;
        color: #333;
    }

    .category-rating {
        font-size: 20px;
        font-weight: bold;
        color: #667eea;
    }

    .category-bar {
        height: 8px;
        background: #e0e0e0;
        border-radius: 4px;
        overflow: hidden;
    }

    .category-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }

    .activity-list {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .activity-item {
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-course {
        font-size: 14px;
        color: #333;
    }

    .activity-time {
        font-size: 12px;
        color: #999;
    }

    .alert-info-custom {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .report-cards {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h1>Quality Assurance Dashboard</h1>
    <p>Institution-Wide Evaluation Performance Overview - <?php echo htmlspecialchars($active_year); ?>
        <?php if ($active_semester): ?>
            (<?php echo htmlspecialchars($active_semester); ?>)
        <?php endif; ?>
    </p>
</div>

<!-- Overall Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?php echo $completion_rate; ?>%</div>
        <div class="stat-label">Overall Completion Rate</div>
        <div class="progress-container">
            <div class="progress-bar" style="width: <?php echo $completion_rate; ?>%;"></div>
        </div>
        <div class="stat-sublabel"><?php echo $completed_tokens; ?> / <?php echo $total_tokens; ?> evaluations</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">📝</div>
        <div class="stat-value"><?php echo $completed_tokens; ?></div>
        <div class="stat-label">Total Submissions</div>
        <div class="stat-sublabel">Across all departments</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?php echo $active_students; ?></div>
        <div class="stat-label">Active Students</div>
        <div class="stat-sublabel">Out of <?php echo $total_students; ?> total students</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-value"><?php echo $total_courses; ?></div>
        <div class="stat-label">Courses Evaluated</div>
        <div class="stat-sublabel">Institution-wide</div>
    </div>
</div>

<!-- Quick Access Reports -->
<h2 class="section-title">Quick Access Reports</h2>
<div class="report-cards">
    <a href="reports/institution_overview.php" class="report-card">
        <div class="report-icon">🏛️</div>
        <div class="report-title">Institution Overview</div>
        <div class="report-description">
            View comprehensive institution-wide statistics and performance metrics
        </div>
    </a>

    <a href="reports/department_comparison.php" class="report-card">
        <div class="report-icon">📊</div>
        <div class="report-title">Department Comparison</div>
        <div class="report-description">
            Compare performance across different departments
        </div>
    </a>

    <a href="reports/course_analysis.php" class="report-card">
        <div class="report-icon">📖</div>
        <div class="report-title">Course Analysis</div>
        <div class="report-description">
            Analyze individual course performance and ratings
        </div>
    </a>

    <a href="reports/question_analysis.php" class="report-card">
        <div class="report-icon">❓</div>
        <div class="report-title">Question Analysis</div>
        <div class="report-description">
            Evaluate effectiveness of evaluation questions
        </div>
    </a>

    <a href="reports/trend_analysis.php" class="report-card">
        <div class="report-icon">📈</div>
        <div class="report-title">Trend Analysis</div>
        <div class="report-description">
            Track performance trends over time
        </div>
    </a>
</div>

<!-- Department Performance -->
<?php if (!empty($departments)): ?>
    <h2 class="section-title">Department Performance Overview</h2>
    <div class="department-list">
        <?php foreach ($departments as $dept): ?>
            <div class="department-item">
                <div class="department-info">
                    <div class="department-name"><?php echo htmlspecialchars($dept['dep_name']); ?></div>
                    <div class="department-meta">
                        <?php echo htmlspecialchars($dept['dep_code']); ?> •
                        <?php echo $dept['dept_completed']; ?> / <?php echo $dept['dept_tokens']; ?> evaluations
                    </div>
                </div>
                <div class="department-stats">
                    <div class="department-completion"><?php echo $dept['completion_rate']; ?>%</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Category Performance -->
<?php if (!empty($categories)): ?>
    <h2 class="section-title">Performance by Category</h2>
    <div class="category-list">
        <?php foreach ($categories as $cat): ?>
            <div class="category-item">
                <div class="category-header">
                    <span class="category-name"><?php echo htmlspecialchars($cat['category']); ?></span>
                    <span class="category-rating"><?php echo $cat['avg_rating']; ?> / 5.0</span>
                </div>
                <div class="category-bar">
                    <div class="category-bar-fill" style="width: <?php echo ($cat['avg_rating'] / 5) * 100; ?>%;"></div>
                </div>
                <div style="font-size: 12px; color: #999; margin-top: 5px;">
                    Based on <?php echo $cat['response_count']; ?> responses
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Recent Activity -->
<?php if (!empty($recent_activity)): ?>
    <h2 class="section-title">Recent Evaluation Activity</h2>
    <div class="activity-list">
        <?php foreach ($recent_activity as $activity): ?>
            <div class="activity-item">
                <div>
                    <div class="activity-course">
                        <strong><?php echo htmlspecialchars($activity['course_code']); ?></strong> -
                        <?php echo htmlspecialchars($activity['course_name']); ?>
                    </div>
                    <div class="activity-time">
                        <?php echo htmlspecialchars($activity['dep_name']); ?>
                    </div>
                </div>
                <div class="activity-time">
                    <?php echo date('M d, h:i A', strtotime($activity['used_at'])); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Information Notice -->
<div style="margin-top: 30px; padding: 20px; background: white; border-radius: 8px; border-left: 4px solid #667eea;">
    <h3 style="margin: 0 0 10px 0; color: #333; font-size: 16px;">🔒 Data Privacy & Anonymity</h3>
    <ul style="margin: 0; padding-left: 20px; color: #666; font-size: 14px; line-height: 1.8;">
        <li>All displayed data is <strong>aggregated and anonymized</strong></li>
        <li>Individual student responses are never shown</li>
        <li>Statistics only displayed when at least <strong><?php echo MIN_RESPONSE_COUNT; ?> responses</strong> are available</li>
        <li>Department and course data protected by minimum response thresholds</li>
        <li>This ensures student anonymity while providing meaningful insights</li>
    </ul>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>
