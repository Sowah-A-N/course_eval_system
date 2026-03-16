<?php

/**
 * Admin Dashboard
 *
 * Main dashboard for system administrators showing institution-wide statistics
 * and quick access to all administrative functions.
 *
 * Features:
 * - System-wide overview statistics
 * - All departments overview
 * - Recent activity across system
 * - Quick action buttons for all admin functions
 * - System health indicators
 *
 * Role Required: ROLE_ADMIN
 */

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] != ROLE_ADMIN) {
    $_SESSION['flash_message'] = 'Access denied. This page is only for system administrators.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['full_name'];
$page_title = 'Admin Dashboard';

// Get system-wide statistics
$query_stats = "
    SELECT
        (SELECT COUNT(*) FROM user_details) as total_users,
        (SELECT COUNT(*) FROM user_details WHERE role_id = ?) as total_students,
        (SELECT COUNT(*) FROM user_details WHERE role_id = ?) as total_lecturers,
        (SELECT COUNT(*) FROM department) as total_departments,
        (SELECT COUNT(*) FROM courses) as total_courses,
        (SELECT COUNT(*) FROM classes) as total_classes,
        (SELECT COUNT(*) FROM evaluation_questions WHERE is_active = 1) as active_questions,
        (SELECT COUNT(*) FROM evaluation_tokens) as total_tokens,
        (SELECT COUNT(*) FROM evaluation_tokens WHERE is_used = 1) as completed_evals,
        (SELECT COUNT(*) FROM academic_year) as total_years,
        (SELECT COUNT(*) FROM programme) as total_programmes
";

$stmt_stats = mysqli_prepare($conn, $query_stats);
$role_student = ROLE_STUDENT;
$role_lecturer = ROLE_ADVISOR;
mysqli_stmt_bind_param($stmt_stats, "ii", $role_student, $role_lecturer);
mysqli_stmt_execute($stmt_stats);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stats));
mysqli_stmt_close($stmt_stats);

$completion_rate = $stats['total_tokens'] > 0 ?
    round(($stats['completed_evals'] / $stats['total_tokens']) * 100, 1) : 0;

// Get active academic period
$query_period = "SELECT * FROM view_active_period LIMIT 1";
$result_period = mysqli_query($conn, $query_period);
$active_period = mysqli_fetch_assoc($result_period);

// Get department overview
$query_depts = "
    SELECT
        d.t_id,
        d.dep_name,
        d.dep_code,
        COUNT(DISTINCT u.user_id) as user_count,
        COUNT(DISTINCT c.id) as course_count
    FROM department d
    LEFT JOIN user_details u ON d.t_id = u.department_id
    LEFT JOIN courses c ON d.t_id = c.department_id
    GROUP BY d.t_id
    ORDER BY d.dep_name
    LIMIT 10
";
$result_depts = mysqli_query($conn, $query_depts);
$departments = [];
while ($row = mysqli_fetch_assoc($result_depts)) {
    $departments[] = $row;
}

// Get recent user registrations
$query_recent = "
    SELECT f_name, l_name, email, role_id, date_created
    FROM user_details
    ORDER BY date_created DESC
    LIMIT 10
";
$result_recent = mysqli_query($conn, $query_recent);
$recent_users = [];
while ($row = mysqli_fetch_assoc($result_recent)) {
    $recent_users[] = $row;
}

require_once '../includes/header.php';
?>

<style>
    .admin-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 50px 40px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .admin-banner h1 {
        margin: 0 0 10px 0;
        font-size: 36px;
        font-weight: 700;
    }

    .admin-banner p {
        margin: 0;
        font-size: 16px;
        opacity: 0.95;
    }

    .admin-banner .system-info {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.3);
        font-size: 14px;
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
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        font-size: 42px;
        margin-bottom: 12px;
    }

    .stat-value {
        font-size: 40px;
        font-weight: bold;
        color: #667eea;
        margin-bottom: 5px;
    }

    .stat-label {
        color: #666;
        font-size: 14px;
        font-weight: 500;
    }

    .quick-actions {
        margin-bottom: 30px;
    }

    .section-title {
        font-size: 22px;
        font-weight: 600;
        color: #333;
        margin: 30px 0 20px;
        padding-bottom: 12px;
        border-bottom: 3px solid #667eea;
    }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .action-card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s;
        border-left: 4px solid #667eea;
    }

    .action-card:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .action-icon {
        font-size: 36px;
        margin-bottom: 12px;
    }

    .action-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .action-desc {
        font-size: 13px;
        color: #666;
        line-height: 1.5;
    }

    .data-table {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .table-row {
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-row:last-child {
        border-bottom: none;
    }

    .row-label {
        font-weight: 500;
        color: #333;
    }

    .row-value {
        color: #667eea;
        font-weight: 600;
    }

    .system-health {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #28a745;
    }

    .health-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .health-item:last-child {
        border-bottom: none;
    }

    .health-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-ok {
        background: #d4edda;
        color: #155724;
    }

    .status-warning {
        background: #fff3cd;
        color: #856404;
    }
</style>

<div class="admin-banner">
    <h1>🎛️ System Administration</h1>
    <p>Welcome, <?php echo htmlspecialchars($admin_name); ?>! You have full system access.</p>
    <div class="system-info">
        <?php if ($active_period): ?>
            📅 Active Period: <strong><?php echo htmlspecialchars($active_period['academic_year'] . ' - ' . $active_period['semester_name']); ?></strong>
        <?php else: ?>
            ⚠️ No active academic period set
        <?php endif; ?>
    </div>
</div>

<!-- System Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?php echo $stats['total_users']; ?></div>
        <div class="stat-label">Total Users</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🎓</div>
        <div class="stat-value"><?php echo $stats['total_students']; ?></div>
        <div class="stat-label">Students</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👨‍🏫</div>
        <div class="stat-value"><?php echo $stats['total_lecturers']; ?></div>
        <div class="stat-label">Lecturers</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🏢</div>
        <div class="stat-value"><?php echo $stats['total_departments']; ?></div>
        <div class="stat-label">Departments</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
        <div class="stat-label">Courses</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?php echo $completion_rate; ?>%</div>
        <div class="stat-label">Eval Completion</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">❓</div>
        <div class="stat-value"><?php echo $stats['active_questions']; ?></div>
        <div class="stat-label">Active Questions</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-value"><?php echo $stats['total_programmes']; ?></div>
        <div class="stat-label">Programmes</div>
    </div>
</div>

<!-- Quick Actions -->
<h2 class="section-title">⚡ Quick Actions</h2>
<div class="actions-grid">
    <a href="users/list.php" class="action-card">
        <div class="action-icon">👤</div>
        <div class="action-title">Manage Users</div>
        <div class="action-desc">Create, edit, delete users across all roles</div>
    </a>
    <a href="departments/list.php" class="action-card">
        <div class="action-icon">🏢</div>
        <div class="action-title">Manage Departments</div>
        <div class="action-desc">Add and configure academic departments</div>
    </a>
    <a href="courses/list.php" class="action-card">
        <div class="action-icon">📚</div>
        <div class="action-title">Manage Courses</div>
        <div class="action-desc">System-wide course management</div>
    </a>
    <a href="questions/list.php" class="action-card">
        <div class="action-icon">❓</div>
        <div class="action-title">Evaluation Questions</div>
        <div class="action-desc">Create and manage evaluation questions</div>
    </a>
    <a href="academic_years/list.php" class="action-card">
        <div class="action-icon">📅</div>
        <div class="action-title">Academic Years</div>
        <div class="action-desc">Manage academic year periods</div>
    </a>
    <a href="tokens/generate.php" class="action-card">
        <div class="action-icon">🎫</div>
        <div class="action-title">Generate Tokens</div>
        <div class="action-desc">Create evaluation tokens for students</div>
    </a>
    <a href="reports/system_overview.php" class="action-card">
        <div class="action-icon">📊</div>
        <div class="action-title">System Reports</div>
        <div class="action-desc">View institution-wide analytics</div>
    </a>
    <a href="settings/index.php" class="action-card">
        <div class="action-icon">⚙️</div>
        <div class="action-title">System Settings</div>
        <div class="action-desc">Configure system parameters</div>
    </a>
</div>

<!-- Department Overview -->
<?php if (!empty($departments)): ?>
    <h2 class="section-title">🏢 Department Overview</h2>
    <div class="data-table">
        <?php foreach ($departments as $dept): ?>
            <div class="table-row">
                <div class="row-label">
                    <?php echo htmlspecialchars($dept['dep_name']); ?>
                    <small style="color: #999;">(<?php echo htmlspecialchars($dept['dep_code']); ?>)</small>
                </div>
                <div class="row-value">
                    <?php echo $dept['user_count']; ?> users • <?php echo $dept['course_count']; ?> courses
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (count($departments) >= 10): ?>
            <div style="text-align: center; margin-top: 15px;">
                <a href="departments/list.php" style="color: #667eea; text-decoration: none; font-weight: 600;">View All Departments →</a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Recent User Registrations -->
<?php if (!empty($recent_users)): ?>
    <h2 class="section-title">🆕 Recent User Registrations</h2>
    <div class="data-table">
        <?php foreach ($recent_users as $user): ?>
            <div class="table-row">
                <div class="row-label">
                    <?php echo htmlspecialchars($user['f_name'] . ' ' . $user['l_name']); ?>
                    <small style="color: #999;">(<?php echo ROLE_NAMES[$user['role_id']] ?? 'Unknown'; ?>)</small>
                </div>
                <div class="row-value">
                    <?php
                        $dateCreated = $user['date_created'] ?? null;
                        echo $dateCreated ? date('M d, Y', strtotime($dateCreated)) : 'N/A';
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- System Health -->
<h2 class="section-title">💚 System Health</h2>
<div class="system-health">
    <div class="health-item">
        <span>Database Connection</span>
        <span class="health-status status-ok">✓ Connected</span>
    </div>
    <div class="health-item">
        <span>Active Period Set</span>
        <span class="health-status <?php echo $active_period ? 'status-ok' : 'status-warning'; ?>">
            <?php echo $active_period ? '✓ Active' : '⚠ Not Set'; ?>
        </span>
    </div>
    <div class="health-item">
        <span>Evaluation Questions</span>
        <span class="health-status <?php echo $stats['active_questions'] > 0 ? 'status-ok' : 'status-warning'; ?>">
            <?php echo $stats['active_questions'] > 0 ? '✓ ' . $stats['active_questions'] . ' Active' : '⚠ None Active'; ?>
        </span>
    </div>
    <div class="health-item">
        <span>Departments Configured</span>
        <span class="health-status <?php echo $stats['total_departments'] > 0 ? 'status-ok' : 'status-warning'; ?>">
            <?php echo $stats['total_departments'] > 0 ? '✓ ' . $stats['total_departments'] . ' Departments' : '⚠ No Departments'; ?>
        </span>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
