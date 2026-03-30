<?php

/**
 * HOD (Head of Department) Dashboard
 *
 * Main dashboard for HODs showing department-specific statistics and quick access.
 *
 * Features:
 * - Department overview statistics
 * - Lecturer performance summary
 * - Course evaluation status
 * - Student completion rates
 * - Quick action buttons
 * - Recent activity
 *
 * Role Required: ROLE_HOD
 */

// Include required files
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';

// Start session and check login
start_secure_session();
check_login();

// Check if user is HOD
if ($_SESSION['role_id'] != ROLE_HOD) {
    $_SESSION['flash_message'] = 'Access denied. This page is only for Heads of Department.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../index.php");
    exit();
}

// Get HOD information
$hod_id = $_SESSION['user_id'];
$hod_name = $_SESSION['full_name'];
$department_id = $_SESSION['department_id'];

// Set page title
$page_title = 'HOD Dashboard';

// Get department information
$query_dept = "SELECT * FROM department WHERE t_id = ?";
$stmt_dept = mysqli_prepare($conn, $query_dept);
mysqli_stmt_bind_param($stmt_dept, "i", $department_id);
mysqli_stmt_execute($stmt_dept);
$result_dept = mysqli_stmt_get_result($stmt_dept);
$department = mysqli_fetch_assoc($result_dept);
mysqli_stmt_close($stmt_dept);

if (!$department) {
    // Log the user out before redirecting to login.php.
    // Without logout, login.php would redirect the HOD straight back here,
    // creating an infinite redirect loop.
    logout_user();
    set_session_message('Your department could not be found. Please contact the system administrator.', 'error');
    header("Location: ../login.php");
    exit();
}

// Get active academic period
$query_period = "SELECT * FROM view_active_period LIMIT 1";
$result_period = mysqli_query($conn, $query_period);
$active_period = mysqli_fetch_assoc($result_period);

// Get department statistics
$query_stats = "
    SELECT
        COUNT(DISTINCT c.id) as total_courses,
        COUNT(DISTINCT et.token_id) as total_tokens,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as completed_tokens,
        COUNT(DISTINCT u.user_id) as total_students,
        COUNT(DISTINCT CASE WHEN u.role_id = 2 THEN u.user_id END) as total_lecturers
    FROM courses c
    LEFT JOIN evaluation_tokens et ON c.id = et.course_id
    LEFT JOIN user_details u ON (u.department_id = ? AND (u.role_id = 5 OR u.role_id = 2))
    WHERE c.department_id = ?
";

$stmt_stats = mysqli_prepare($conn, $query_stats);
mysqli_stmt_bind_param($stmt_stats, "ii", $department_id, $department_id);
mysqli_stmt_execute($stmt_stats);
$result_stats = mysqli_stmt_get_result($stmt_stats);
$stats = mysqli_fetch_assoc($result_stats);
mysqli_stmt_close($stmt_stats);

$completion_rate = $stats['total_tokens'] > 0 ?
    round(($stats['completed_tokens'] / $stats['total_tokens']) * 100, 1) : 0;

// Get top-rated courses (min 5 responses)
$query_top_courses = "
    SELECT
        c.course_code,
        c.name,
        AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating,
        COUNT(DISTINCT et.token_id) as eval_count
    FROM courses c
    JOIN evaluation_tokens et ON c.id = et.course_id AND et.is_used = 1
    JOIN evaluations e ON et.token = e.token
    JOIN responses r ON e.evaluation_id = r.evaluation_id
    WHERE c.department_id = ?
    GROUP BY c.id
    HAVING eval_count >= ?
    ORDER BY avg_rating DESC
    LIMIT 5
";

$stmt_top = mysqli_prepare($conn, $query_top_courses);
$min_count = MIN_RESPONSE_COUNT;
mysqli_stmt_bind_param($stmt_top, "ii", $department_id, $min_count);
mysqli_stmt_execute($stmt_top);
$result_top = mysqli_stmt_get_result($stmt_top);
$top_courses = [];
while ($row = mysqli_fetch_assoc($result_top)) {
    $row['avg_rating'] = round($row['avg_rating'], 2);
    $top_courses[] = $row;
}
mysqli_stmt_close($stmt_top);

// Get recent evaluations
$query_recent = "
    SELECT
        et.used_at,
        c.course_code,
        c.name as course_name
    FROM evaluation_tokens et
    JOIN courses c ON et.course_id = c.id
    WHERE c.department_id = ?
    AND et.is_used = 1
    ORDER BY et.used_at DESC
    LIMIT 10
";

$stmt_recent = mysqli_prepare($conn, $query_recent);
mysqli_stmt_bind_param($stmt_recent, "i", $department_id);
mysqli_stmt_execute($stmt_recent);
$result_recent = mysqli_stmt_get_result($stmt_recent);
$recent_activity = [];
while ($row = mysqli_fetch_assoc($result_recent)) {
    $recent_activity[] = $row;
}
mysqli_stmt_close($stmt_recent);

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
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
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

    .action-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .action-btn {
        display: block;
        padding: 20px;
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s;
        color: #333;
    }

    .action-btn:hover {
        border-color: #667eea;
        background: #f8f9ff;
        transform: translateY(-3px);
    }

    .action-btn-icon {
        font-size: 36px;
        margin-bottom: 10px;
    }

    .action-btn-title {
        font-weight: 600;
        font-size: 16px;
        margin-bottom: 5px;
    }

    .action-btn-desc {
        font-size: 13px;
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

    .course-list,
    .activity-list {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .course-item,
    .activity-item {
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .course-item:last-child,
    .activity-item:last-child {
        border-bottom: none;
    }

    .course-name {
        font-size: 14px;
        color: #333;
    }

    .course-rating {
        font-size: 16px;
        font-weight: bold;
        color: #667eea;
    }

    .activity-time {
        font-size: 12px;
        color: #999;
    }
</style>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h1>Welcome, <?php echo htmlspecialchars($hod_name); ?>!</h1>
    <p>Head of Department - <?php echo htmlspecialchars($department['dep_name']); ?>
        <?php if ($active_period): ?>
            (<?php echo htmlspecialchars($active_period['academic_year'] . ' - ' . $active_period['semester_name']); ?>)
        <?php endif; ?>
    </p>
</div>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?php echo $completion_rate; ?>%</div>
        <div class="stat-label">Completion Rate</div>
        <div class="progress-container">
            <div class="progress-bar" style="width: <?php echo $completion_rate; ?>%;"></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
        <div class="stat-label">Total Courses</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">👨‍🏫</div>
        <div class="stat-value"><?php echo $stats['total_lecturers']; ?></div>
        <div class="stat-label">Lecturers</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?php echo $stats['total_students']; ?></div>
        <div class="stat-label">Students</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?php echo $stats['completed_tokens']; ?></div>
        <div class="stat-label">Evaluations Submitted</div>
    </div>
</div>

<!-- Quick Actions -->
<h2 class="section-title">Quick Actions</h2>
<div class="action-buttons">
    <a href="lecturers/list.php" class="action-btn">
        <div class="action-btn-icon">👨‍🏫</div>
        <div class="action-btn-title">Manage Lecturers</div>
        <div class="action-btn-desc">View and assign lecturers</div>
    </a>

    <a href="courses/list.php" class="action-btn">
        <div class="action-btn-icon">📖</div>
        <div class="action-btn-title">Manage Courses</div>
        <div class="action-btn-desc">View department courses</div>
    </a>

    <a href="reports/department_report.php" class="action-btn">
        <div class="action-btn-icon">📊</div>
        <div class="action-btn-title">Department Report</div>
        <div class="action-btn-desc">View performance statistics</div>
    </a>

    <a href="reports/lecturer_report.php" class="action-btn">
        <div class="action-btn-icon">📈</div>
        <div class="action-btn-title">Lecturer Reports</div>
        <div class="action-btn-desc">Individual lecturer performance</div>
    </a>
</div>

<!-- Top Rated Courses -->
<?php if (!empty($top_courses)): ?>
    <h2 class="section-title">Top Rated Courses</h2>
    <div class="course-list">
        <?php foreach ($top_courses as $course): ?>
            <div class="course-item">
                <div>
                    <strong><?php echo htmlspecialchars($course['course_code']); ?></strong> -
                    <span class="course-name"><?php echo htmlspecialchars($course['name']); ?></span>
                    <div class="activity-time"><?php echo $course['eval_count']; ?> evaluations</div>
                </div>
                <div class="course-rating"><?php echo $course['avg_rating']; ?>/5.0</div>
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
                    <strong><?php echo htmlspecialchars($activity['course_code']); ?></strong> -
                    <?php echo htmlspecialchars($activity['course_name']); ?>
                </div>
                <div class="activity-time">
                    <?php echo htmlspecialchars(date('M d, h:i A', strtotime($activity['used_at'])), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
