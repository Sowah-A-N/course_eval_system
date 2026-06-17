<?php

/**
 * Student Dashboard
 *
 * Main dashboard for students showing evaluation status and quick access to features.
 *
 * Features:
 * - Welcome message with student information
 * - Pending evaluations count and list
 * - Completed evaluations count
 * - Evaluation completion progress
 * - Active academic period display
 * - Quick action buttons
 * - Recent activity
 *
 * Role Required: ROLE_STUDENT
 */

// Include required files
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';

// Start session and check login
start_secure_session();

check_login();

// Check if user is a student
if ($_SESSION['role_id'] !== ROLE_STUDENT) {
    $_SESSION['flash_message'] = 'Access denied. This page is only for students.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../index.php");
    exit();
}

// Get student information
$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];
$student_email = $_SESSION['email'];
$student_unique_id = isset($_SESSION['unique_id']) ? $_SESSION['unique_id'] : 'N/A';

// Set page title
$page_title = 'Student Dashboard';

// Get active academic period
$query_period = "SELECT * FROM view_active_period LIMIT 1";
$result_period = mysqli_query($conn, $query_period);
$active_period = mysqli_fetch_assoc($result_period);

if (!$active_period) {
    $active_year = "No Active Period";
    $active_semester = "";
    $no_active_period = true;
} else {
    $active_year = $active_period['academic_year'];
    $active_semester = $active_period['semester_name'];
    $no_active_period = false;
}

// Get student's level and class information
$query_student_info = "
    SELECT
        u.level_id,
        u.class_id,
        u.department_id,
        l.level_name,
        c.class_name,
        d.dep_name
    FROM user_details u
    LEFT JOIN level l ON u.level_id = l.t_id
    LEFT JOIN classes c ON u.class_id = c.t_id
    LEFT JOIN department d ON u.department_id = d.t_id
    WHERE u.user_id = ?
";

$stmt_info = mysqli_prepare($conn, $query_student_info);
mysqli_stmt_bind_param($stmt_info, "i", $student_id);
mysqli_stmt_execute($stmt_info);
$result_info = mysqli_stmt_get_result($stmt_info);
$student_info = mysqli_fetch_assoc($result_info);
mysqli_stmt_close($stmt_info);

$level_name = $student_info['level_name'] ?? 'Not Assigned';
$class_name = $student_info['class_name'] ?? 'Not Assigned';
$department_name = $student_info['dep_name'] ?? 'Not Assigned';

// Get total evaluation tokens
$query_total = "
    SELECT COUNT(*) as total_tokens
    FROM evaluation_tokens
    WHERE student_user_id = ?
";

$stmt_total = mysqli_prepare($conn, $query_total);
mysqli_stmt_bind_param($stmt_total, "i", $student_id);
mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$total_data = mysqli_fetch_assoc($result_total);
$total_tokens = $total_data['total_tokens'];
mysqli_stmt_close($stmt_total);

// Get completed evaluations count
$query_completed = "
    SELECT COUNT(*) as completed_tokens
    FROM evaluation_tokens
    WHERE student_user_id = ?
    AND is_used = 1
";

$stmt_completed = mysqli_prepare($conn, $query_completed);
mysqli_stmt_bind_param($stmt_completed, "i", $student_id);
mysqli_stmt_execute($stmt_completed);
$result_completed = mysqli_stmt_get_result($stmt_completed);
$completed_data = mysqli_fetch_assoc($result_completed);
$completed_tokens = $completed_data['completed_tokens'];
mysqli_stmt_close($stmt_completed);

// Calculate pending evaluations
$pending_tokens = $total_tokens - $completed_tokens;

// Calculate completion percentage
$completion_percentage = $total_tokens > 0 ? round(($completed_tokens / $total_tokens) * 100, 1) : 0;

// Get pending evaluations with course details (limited to 5 for display)
$query_pending = "
    SELECT
        et.token_id,
        et.token,
        c.course_code,
        c.name as course_name,
        l.level_name,
        s.semester_name,
        et.created_at
    FROM evaluation_tokens et
    JOIN courses c ON et.course_id = c.id
    LEFT JOIN level l ON c.level_id = l.t_id
    LEFT JOIN semesters s ON et.semester_id = s.semester_id
    WHERE et.student_user_id = ?
    AND et.is_used = 0
    ORDER BY et.created_at DESC
    LIMIT 5
";

$stmt_pending = mysqli_prepare($conn, $query_pending);
mysqli_stmt_bind_param($stmt_pending, "i", $student_id);
mysqli_stmt_execute($stmt_pending);
$result_pending = mysqli_stmt_get_result($stmt_pending);
$pending_evaluations = [];
while ($row = mysqli_fetch_assoc($result_pending)) {
    $pending_evaluations[] = $row;
}
mysqli_stmt_close($stmt_pending);

// Get recent completed evaluations (last 5)
$query_recent = "
    SELECT
        et.used_at,
        c.course_code,
        c.name as course_name,
        l.level_name
    FROM evaluation_tokens et
    JOIN courses c ON et.course_id = c.id
    LEFT JOIN level l ON c.level_id = l.t_id
    WHERE et.student_user_id = ?
    AND et.is_used = 1
    ORDER BY et.used_at DESC
    LIMIT 5
";

$stmt_recent = mysqli_prepare($conn, $query_recent);
mysqli_stmt_bind_param($stmt_recent, "i", $student_id);
mysqli_stmt_execute($stmt_recent);
$result_recent = mysqli_stmt_get_result($stmt_recent);
$recent_evaluations = [];
while ($row = mysqli_fetch_assoc($result_recent)) {
    $recent_evaluations[] = $row;
}
mysqli_stmt_close($stmt_recent);

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

    .welcome-meta {
        display: flex;
        gap: 30px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .welcome-meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        opacity: 0.95;
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
        margin-bottom: 15px;
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

    .section-title {
        font-size: 20px;
        font-weight: 600;
        color: #333;
        margin: 30px 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }

    .action-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

    .evaluation-list {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .evaluation-item {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s;
    }

    .evaluation-item:last-child {
        border-bottom: none;
    }

    .evaluation-item:hover {
        background: #f8f9fa;
    }

    .evaluation-info {
        flex: 1;
    }

    .evaluation-course {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }

    .evaluation-meta {
        font-size: 13px;
        color: #666;
    }

    .evaluation-action {
        margin-left: 15px;
    }

    .btn-evaluate {
        padding: 8px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 5px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: transform 0.2s;
        display: inline-block;
    }

    .btn-evaluate:hover {
        transform: translateY(-2px);
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
    }

    .empty-state-icon {
        font-size: 64px;
        margin-bottom: 15px;
        opacity: 0.3;
    }

    .alert-info-custom {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 15px 20px;
        border-radius: 5px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-warning-custom {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        padding: 15px 20px;
        border-radius: 5px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    @media (max-width: 768px) {
        .welcome-meta {
            flex-direction: column;
            gap: 10px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h1>Welcome back, <?php echo htmlspecialchars($student_name); ?>! 👋</h1>
    <p>Student Dashboard - <?php echo htmlspecialchars($active_year); ?>
        <?php if ($active_semester): ?>
            (<?php echo htmlspecialchars($active_semester); ?>)
        <?php endif; ?>
    </p>
    <div class="welcome-meta">
        <div class="welcome-meta-item">
            <span>🆔</span>
            <span><strong>Student ID:</strong> <?php echo htmlspecialchars($student_unique_id); ?></span>
        </div>
        <div class="welcome-meta-item">
            <span>📚</span>
            <span><strong>Level:</strong> <?php echo htmlspecialchars($level_name); ?></span>
        </div>
        <div class="welcome-meta-item">
            <span>👥</span>
            <span><strong>Class:</strong> <?php echo htmlspecialchars($class_name); ?></span>
        </div>
        <div class="welcome-meta-item">
            <span>🏢</span>
            <span><strong>Department:</strong> <?php echo htmlspecialchars($department_name); ?></span>
        </div>
    </div>
</div>

<?php if ($no_active_period): ?>
    <!-- No Active Period Warning -->
    <div class="alert-warning-custom">
        <span style="font-size: 24px;">⚠️</span>
        <div>
            <strong>No Active Evaluation Period</strong><br>
            There is currently no active academic year or semester.
            Evaluations will be available when the administration activates an evaluation period.
        </div>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <!-- Total Evaluations -->
    <div class="stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-value"><?php echo $total_tokens; ?></div>
        <div class="stat-label">Total Evaluations</div>
    </div>

    <!-- Pending Evaluations -->
    <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-value" style="color: #ffc107;"><?php echo $pending_tokens; ?></div>
        <div class="stat-label">Pending Evaluations</div>
        <?php if ($pending_tokens > 0): ?>
            <a href="evaluate/available_courses.php" style="color: #667eea; text-decoration: none; font-size: 13px; font-weight: 500;">
                → Start evaluating
            </a>
        <?php endif; ?>
    </div>

    <!-- Completed Evaluations -->
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value" style="color: #28a745;"><?php echo $completed_tokens; ?></div>
        <div class="stat-label">Completed Evaluations</div>
        <?php if ($completed_tokens > 0): ?>
            <a href="evaluate/history.php" style="color: #667eea; text-decoration: none; font-size: 13px; font-weight: 500;">
                → View history
            </a>
        <?php endif; ?>
    </div>

    <!-- Completion Progress -->
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?php echo $completion_percentage; ?>%</div>
        <div class="stat-label">Completion Progress</div>
        <div class="progress-container">
            <div class="progress-bar" style="width: <?php echo $completion_percentage; ?>%;"></div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<h2 class="section-title">Quick Actions</h2>
<div class="action-buttons">
    <a href="evaluate/available_courses.php" class="action-btn">
        <div class="action-btn-icon">📝</div>
        <div class="action-btn-title">Evaluate Courses</div>
        <div class="action-btn-desc">Submit course evaluations</div>
    </a>

    <a href="evaluate/history.php" class="action-btn">
        <div class="action-btn-icon">📜</div>
        <div class="action-btn-title">View History</div>
        <div class="action-btn-desc">See your past submissions</div>
    </a>

    <a href="profile/view.php" class="action-btn">
        <div class="action-btn-icon">👤</div>
        <div class="action-btn-title">My Profile</div>
        <div class="action-btn-desc">View and edit your profile</div>
    </a>
</div>

<!-- Pending Evaluations -->
<?php if ($pending_tokens > 0): ?>
    <h2 class="section-title">Pending Evaluations (<?php echo $pending_tokens; ?>)</h2>

    <?php if (!empty($pending_evaluations)): ?>
        <div class="evaluation-list">
            <?php foreach ($pending_evaluations as $eval): ?>
                <div class="evaluation-item">
                    <div class="evaluation-info">
                        <div class="evaluation-course">
                            <?php echo htmlspecialchars($eval['course_code']); ?> -
                            <?php echo htmlspecialchars($eval['course_name']); ?>
                        </div>
                        <div class="evaluation-meta">
                            <?php echo htmlspecialchars($eval['level_name']); ?> •
                            <?php echo htmlspecialchars($eval['semester_name']); ?>
                            <?php if ($eval['created_at']): ?>
                                • Available since <?php echo date('M d, Y', strtotime($eval['created_at'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="evaluation-action">
                        <a href="evaluate/submit.php?token=<?php echo urlencode($eval['token']); ?>" class="btn-evaluate">
                            Evaluate Now
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($pending_tokens > 5): ?>
                <div style="text-align: center; padding: 15px;">
                    <a href="evaluate/available_courses.php" style="color: #667eea; text-decoration: none; font-weight: 500;">
                        View all <?php echo $pending_tokens; ?> pending evaluations →
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <!-- All Evaluations Complete -->
    <?php if ($total_tokens > 0): ?>
        <div class="alert-info-custom">
            <span style="font-size: 24px;">🎉</span>
            <div>
                <strong>All Evaluations Complete!</strong><br>
                You have successfully completed all <?php echo $completed_tokens; ?> course evaluations.
                Thank you for your valuable feedback!
            </div>
        </div>
    <?php else: ?>
        <!-- No Evaluations Assigned -->
        <div class="alert-info-custom">
            <span style="font-size: 24px;">ℹ️</span>
            <div>
                <strong>No Evaluations Available Yet</strong><br>
                You don't have any course evaluations assigned at the moment.
                Evaluations will appear here when they become available.
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Recent Submissions -->
<?php if (!empty($recent_evaluations)): ?>
    <h2 class="section-title">Recent Submissions</h2>
    <div class="evaluation-list">
        <?php foreach ($recent_evaluations as $eval): ?>
            <div class="evaluation-item">
                <div class="evaluation-info">
                    <div class="evaluation-course">
                        <?php echo htmlspecialchars($eval['course_code']); ?> -
                        <?php echo htmlspecialchars($eval['course_name']); ?>
                    </div>
                    <div class="evaluation-meta">
                        <?php echo htmlspecialchars($eval['level_name']); ?> •
                        Submitted on <?php echo date('M d, Y h:i A', strtotime($eval['used_at'])); ?>
                    </div>
                </div>
                <div class="evaluation-action">
                    <span style="color: #28a745; font-weight: 600; font-size: 14px;">✓ Completed</span>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($completed_tokens > 5): ?>
            <div style="text-align: center; padding: 15px;">
                <a href="evaluate/history.php" style="color: #667eea; text-decoration: none; font-weight: 500;">
                    View all <?php echo $completed_tokens; ?> completed evaluations →
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Helpful Tips -->
<div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
    <h3 style="margin: 0 0 10px 0; color: #333; font-size: 16px;">💡 Helpful Tips</h3>
    <ul style="margin: 0; padding-left: 20px; color: #666; font-size: 14px; line-height: 1.8;">
        <li>Your feedback is <strong>completely anonymous</strong> and helps improve course quality</li>
        <li>Please provide honest and constructive feedback</li>
        <li>Evaluations cannot be edited after submission, so review carefully before submitting</li>
        <li>Complete your evaluations before the deadline to ensure your voice is heard</li>
        <?php if ($pending_tokens > 0): ?>
            <li style="color: #856404;"><strong>You have <?php echo $pending_tokens; ?> pending evaluation(s)</strong> - please complete them soon!</li>
        <?php endif; ?>
    </ul>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>
