<?php
/**
 * Advisor Dashboard
 *
 * This is the main dashboard for Class Advisors.
 * Displays assigned classes, student statistics, and evaluation completion rates.
 *
 * Features:
 * - Overview of assigned classes/levels
 * - Student count per class
 * - Evaluation completion statistics
 * - Recent evaluation activity
 * - Quick access to reports
 * - Advisor performance rating (how students rated the advisor)
 *
 * Role Required: ROLE_ADVISOR
 */

// Include required files
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';

// Start session and check login
start_secure_session();
check_login();

// Check if user is an advisor
if ($_SESSION['role_id'] !== ROLE_ADVISOR) {
    $_SESSION['flash_message'] = 'Access denied. This page is only for advisors.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../index.php");
    exit();
}

// Get advisor information
$advisor_id = $_SESSION['user_id'];
$advisor_name = $_SESSION['full_name'];
$department_id = $_SESSION['department_id'];

// Set page title
$page_title = 'Advisor Dashboard';

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

// Get advisor's assigned levels/classes
$query_assigned = "
    SELECT
        al.t_id,
        al.level_id,
        al.department_id,
        l.level_name,
        d.dep_name,
        d.dep_code
    FROM advisor_levels al
    JOIN level l ON al.level_id = l.t_id
    JOIN department d ON al.department_id = d.t_id
    WHERE al.advisor_id = ?
    ORDER BY l.level_number
";

$stmt_assigned = mysqli_prepare($conn, $query_assigned);
mysqli_stmt_bind_param($stmt_assigned, "i", $advisor_id);
mysqli_stmt_execute($stmt_assigned);
$result_assigned = mysqli_stmt_get_result($stmt_assigned);
$assigned_levels = [];
while ($row = mysqli_fetch_assoc($result_assigned)) {
    $assigned_levels[] = $row;
}
mysqli_stmt_close($stmt_assigned);

// Calculate statistics for each assigned level
$statistics = [];
foreach ($assigned_levels as $level) {
    $level_id = $level['level_id'];
    $dept_id = $level['department_id'];

    // Count total students in this level/department
    $query_students = "
        SELECT COUNT(*) as total_students
        FROM user_details
        WHERE role_id = ?
        AND level_id = ?
        AND department_id = ?
        AND is_active = 1
    ";
    $stmt_students = mysqli_prepare($conn, $query_students);
    mysqli_stmt_bind_param($stmt_students, "iii", $student_role, $level_id, $dept_id);
    $student_role = ROLE_STUDENT;
    mysqli_stmt_execute($stmt_students);
    $result_students = mysqli_stmt_get_result($stmt_students);
    $student_data = mysqli_fetch_assoc($result_students);
    $total_students = $student_data['total_students'];
    mysqli_stmt_close($stmt_students);

    // Count students who have completed at least one evaluation
    $query_completed = "
        SELECT COUNT(DISTINCT et.student_user_id) as completed_students
        FROM evaluation_tokens et
        JOIN user_details u ON et.student_user_id = u.user_id
        WHERE u.level_id = ?
        AND u.department_id = ?
        AND et.is_used = 1
    ";
    $stmt_completed = mysqli_prepare($conn, $query_completed);
    mysqli_stmt_bind_param($stmt_completed, "ii", $level_id, $dept_id);
    mysqli_stmt_execute($stmt_completed);
    $result_completed = mysqli_stmt_get_result($stmt_completed);
    $completed_data = mysqli_fetch_assoc($result_completed);
    $completed_students = $completed_data['completed_students'];
    mysqli_stmt_close($stmt_completed);

    // Calculate completion rate
    $completion_rate = $total_students > 0 ? round(($completed_students / $total_students) * 100, 1) : 0;

    // Count total available evaluations for this level
    $query_available = "
        SELECT COUNT(DISTINCT et.token_id) as total_evaluations
        FROM evaluation_tokens et
        JOIN user_details u ON et.student_user_id = u.user_id
        WHERE u.level_id = ?
        AND u.department_id = ?
    ";
    $stmt_available = mysqli_prepare($conn, $query_available);
    mysqli_stmt_bind_param($stmt_available, "ii", $level_id, $dept_id);
    mysqli_stmt_execute($stmt_available);
    $result_available = mysqli_stmt_get_result($stmt_available);
    $available_data = mysqli_fetch_assoc($result_available);
    $total_evaluations = $available_data['total_evaluations'];
    mysqli_stmt_close($stmt_available);

    // Count completed evaluations
    $query_eval_completed = "
        SELECT COUNT(*) as completed_evaluations
        FROM evaluation_tokens et
        JOIN user_details u ON et.student_user_id = u.user_id
        WHERE u.level_id = ?
        AND u.department_id = ?
        AND et.is_used = 1
    ";
    $stmt_eval_completed = mysqli_prepare($conn, $query_eval_completed);
    mysqli_stmt_bind_param($stmt_eval_completed, "ii", $level_id, $dept_id);
    mysqli_stmt_execute($stmt_eval_completed);
    $result_eval_completed = mysqli_stmt_get_result($stmt_eval_completed);
    $eval_completed_data = mysqli_fetch_assoc($result_eval_completed);
    $completed_evaluations = $eval_completed_data['completed_evaluations'];
    mysqli_stmt_close($stmt_eval_completed);

    // Calculate evaluation completion rate
    $eval_completion_rate = $total_evaluations > 0 ? round(($completed_evaluations / $total_evaluations) * 100, 1) : 0;

    $statistics[$level['level_id']] = [
        'total_students' => $total_students,
        'completed_students' => $completed_students,
        'completion_rate' => $completion_rate,
        'total_evaluations' => $total_evaluations,
        'completed_evaluations' => $completed_evaluations,
        'eval_completion_rate' => $eval_completion_rate
    ];
}

// Get advisor performance rating (how students rated the advisor)
// Question: "How would you rate the performance of your class advisor?"
$advisor_rating = null;
$advisor_rating_count = 0;

$query_advisor_rating = "
    SELECT
        AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating,
        COUNT(r.id) as response_count
    FROM responses r
    JOIN evaluations e ON r.evaluation_id = e.evaluation_id
    JOIN evaluation_questions eq ON r.question_id = eq.question_id
    JOIN evaluation_tokens et ON e.token = et.token
    JOIN user_details u ON et.student_user_id = u.user_id
    WHERE eq.question_text LIKE '%advisor%'
    AND u.level_id IN (
        SELECT level_id FROM advisor_levels WHERE advisor_id = ?
    )
    AND u.department_id = ?
";

$stmt_rating = mysqli_prepare($conn, $query_advisor_rating);
mysqli_stmt_bind_param($stmt_rating, "ii", $advisor_id, $department_id);
mysqli_stmt_execute($stmt_rating);
$result_rating = mysqli_stmt_get_result($stmt_rating);
$rating_data = mysqli_fetch_assoc($result_rating);

if ($rating_data && $rating_data['response_count'] >= MIN_RESPONSE_COUNT) {
    $advisor_rating = round($rating_data['avg_rating'], 2);
    $advisor_rating_count = $rating_data['response_count'];
}
mysqli_stmt_close($stmt_rating);

// Get recent evaluation activity (last 10 submissions from advisor's students)
$query_recent = "
    SELECT
        et.used_at,
        c.course_code,
        c.name as course_name,
        u.f_name,
        u.l_name,
        l.level_name
    FROM evaluation_tokens et
    JOIN user_details u ON et.student_user_id = u.user_id
    JOIN courses c ON et.course_id = c.id
    JOIN level l ON u.level_id = l.t_id
    WHERE et.is_used = 1
    AND u.level_id IN (
        SELECT level_id FROM advisor_levels WHERE advisor_id = ?
    )
    AND u.department_id = ?
    ORDER BY et.used_at DESC
    LIMIT 10
";

$stmt_recent = mysqli_prepare($conn, $query_recent);
mysqli_stmt_bind_param($stmt_recent, "ii", $advisor_id, $department_id);
mysqli_stmt_execute($stmt_recent);
$result_recent = mysqli_stmt_get_result($stmt_recent);
$recent_activities = [];
while ($row = mysqli_fetch_assoc($result_recent)) {
    $recent_activities[] = $row;
}
mysqli_stmt_close($stmt_recent);

// Include header
require_once '../includes/header.php';
?>

<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 8px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .stat-icon {
        font-size: 36px;
    }

    .stat-value {
        font-size: 36px;
        font-weight: bold;
        color: #667eea;
        margin-bottom: 5px;
    }

    .stat-label {
        color: #666;
        font-size: 14px;
    }

    .progress-bar-container {
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
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }

    .level-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
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

    .level-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .level-stat-item {
        text-align: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
    }

    .level-stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #667eea;
    }

    .level-stat-label {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }

    .activity-table {
        width: 100%;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .activity-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .activity-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #e0e0e0;
    }

    .activity-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
    }

    .activity-table tr:hover {
        background: #f8f9fa;
    }

    .btn-primary {
        display: inline-block;
        padding: 10px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 500;
        transition: transform 0.2s;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
    }

    .rating-display {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
    }

    .stars {
        color: #ffc107;
        font-size: 24px;
    }

    .no-data {
        text-align: center;
        padding: 40px;
        color: #666;
        font-style: italic;
    }

    .alert-info-custom {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1>Welcome, <?php echo htmlspecialchars($advisor_name); ?></h1>
    <p>Class Advisor Dashboard - <?php echo htmlspecialchars($active_year); ?>
       <?php if ($active_semester): ?>
           (<?php echo htmlspecialchars($active_semester); ?>)
       <?php endif; ?>
    </p>
</div>

<?php if (empty($assigned_levels)): ?>
    <!-- No Assignments Message -->
    <div class="alert-info-custom">
        <strong>No Assignments Found</strong><br>
        You have not been assigned to any classes yet. Please contact your department head.
    </div>
<?php else: ?>

    <!-- Overview Statistics -->
    <div class="dashboard-grid">
        <!-- Total Assigned Levels -->
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-value"><?php echo count($assigned_levels); ?></div>
                    <div class="stat-label">Assigned Level(s)</div>
                </div>
                <div class="stat-icon">📚</div>
            </div>
        </div>

        <!-- Total Students -->
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <?php
                    $total_all_students = array_sum(array_column($statistics, 'total_students'));
                    ?>
                    <div class="stat-value"><?php echo $total_all_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-icon">👥</div>
            </div>
        </div>

        <!-- Evaluation Progress -->
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <?php
                    $total_all_evaluations = array_sum(array_column($statistics, 'total_evaluations'));
                    $total_completed_evaluations = array_sum(array_column($statistics, 'completed_evaluations'));
                    $overall_completion = $total_all_evaluations > 0 ?
                        round(($total_completed_evaluations / $total_all_evaluations) * 100, 1) : 0;
                    ?>
                    <div class="stat-value"><?php echo $overall_completion; ?>%</div>
                    <div class="stat-label">Overall Completion Rate</div>
                </div>
                <div class="stat-icon">📊</div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo $overall_completion; ?>%;"></div>
            </div>
        </div>

        <!-- Advisor Rating -->
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <?php if ($advisor_rating !== null): ?>
                        <div class="stat-value"><?php echo $advisor_rating; ?>/5</div>
                        <div class="stat-label">Your Advisor Rating</div>
                        <div class="rating-display">
                            <div class="stars">
                                <?php
                                $full_stars = floor($advisor_rating);
                                $half_star = ($advisor_rating - $full_stars) >= 0.5;
                                for ($i = 0; $i < $full_stars; $i++) echo '⭐';
                                if ($half_star) echo '⭐';
                                ?>
                            </div>
                            <small>(<?php echo $advisor_rating_count; ?> responses)</small>
                        </div>
                    <?php else: ?>
                        <div class="stat-value">-</div>
                        <div class="stat-label">Your Advisor Rating</div>
                        <small style="color: #999;">Not enough responses (min: <?php echo MIN_RESPONSE_COUNT; ?>)</small>
                    <?php endif; ?>
                </div>
                <div class="stat-icon">⭐</div>
            </div>
        </div>
    </div>

    <!-- Assigned Levels Details -->
    <h2 class="section-title">Your Assigned Classes</h2>

    <?php foreach ($assigned_levels as $level): ?>
        <?php
        $level_id = $level['level_id'];
        $stats = $statistics[$level_id];
        ?>
        <div class="level-card">
            <div class="level-header">
                <div>
                    <div class="level-name">
                        <?php echo htmlspecialchars($level['level_name']); ?> -
                        <?php echo htmlspecialchars($level['dep_name']); ?>
                    </div>
                    <small style="color: #666;">Department Code: <?php echo htmlspecialchars($level['dep_code']); ?></small>
                </div>
                <div>
                    <a href="students/list.php?level_id=<?php echo $level_id; ?>" class="btn-primary">
                        View Students
                    </a>
                </div>
            </div>

            <div class="level-stats">
                <div class="level-stat-item">
                    <div class="level-stat-value"><?php echo $stats['total_students']; ?></div>
                    <div class="level-stat-label">Total Students</div>
                </div>

                <div class="level-stat-item">
                    <div class="level-stat-value"><?php echo $stats['completed_students']; ?></div>
                    <div class="level-stat-label">Active Students</div>
                </div>

                <div class="level-stat-item">
                    <div class="level-stat-value"><?php echo $stats['completion_rate']; ?>%</div>
                    <div class="level-stat-label">Student Participation</div>
                </div>

                <div class="level-stat-item">
                    <div class="level-stat-value">
                        <?php echo $stats['completed_evaluations']; ?>/<?php echo $stats['total_evaluations']; ?>
                    </div>
                    <div class="level-stat-label">Evaluations Completed</div>
                </div>
            </div>

            <div class="progress-bar-container" style="margin-top: 15px;">
                <div class="progress-bar" style="width: <?php echo $stats['eval_completion_rate']; ?>%;"></div>
            </div>
            <small style="color: #666; margin-top: 5px; display: block;">
                Evaluation Progress: <?php echo $stats['eval_completion_rate']; ?>%
            </small>
        </div>
    <?php endforeach; ?>

    <!-- Recent Activity -->
    <h2 class="section-title" style="margin-top: 40px;">Recent Evaluation Activity</h2>

    <?php if (empty($recent_activities)): ?>
        <div class="activity-table">
            <div class="no-data">No recent evaluation activity</div>
        </div>
    <?php else: ?>
        <div class="activity-table">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Date & Time</th>
                        <th scope="col">Student</th>
                        <th scope="col">Level</th>
                        <th scope="col">Course Evaluated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activities as $activity): ?>
                        <tr>
                            <td><?php echo date('M d, Y h:i A', strtotime($activity['used_at'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($activity['f_name'] . ' ' . $activity['l_name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($activity['level_name']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($activity['course_code']); ?></strong> -
                                <?php echo htmlspecialchars($activity['course_name']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <h2 class="section-title" style="margin-top: 40px;">Quick Actions</h2>

    <div class="dashboard-grid">
        <div class="stat-card">
            <h3 style="margin-bottom: 15px; color: #333;">📊 Reports</h3>
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                View detailed reports and analytics for your classes
            </p>
            <a href="reports/class_report.php" class="btn-primary">View Class Reports</a>
        </div>

        <div class="stat-card">
            <h3 style="margin-bottom: 15px; color: #333;">✅ Completion Status</h3>
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                Check which students have completed their evaluations
            </p>
            <a href="reports/completion_report.php" class="btn-primary">Completion Report</a>
        </div>

        <div class="stat-card">
            <h3 style="margin-bottom: 15px; color: #333;">👥 Student List</h3>
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                View all students in your assigned classes
            </p>
            <a href="students/list.php" class="btn-primary">View All Students</a>
        </div>
    </div>

<?php endif; ?>

<?php
// Include footer
require_once '../includes/footer.php';
?>
