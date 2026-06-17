<?php
/**
 * Secretary Dashboard
 *
 * Main dashboard for department secretaries showing department statistics
 * and quick access to CRUD operations.
 *
 * Features:
 * - Department overview statistics
 * - Quick action buttons for CRUD operations
 * - Recent activity
 * - Department-scoped access only
 *
 * Role Required: ROLE_SECRETARY
 */

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_SECRETARY) {
    $_SESSION['flash_message'] = 'Access denied. This page is only for department secretaries.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../index.php");
    exit();
}

$secretary_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'];
$secretary_name = $_SESSION['full_name'];
$page_title = 'Secretary Dashboard';

// Get department information
$query_dept = "SELECT * FROM department WHERE t_id = ?";
$stmt_dept = mysqli_prepare($conn, $query_dept);
mysqli_stmt_bind_param($stmt_dept, "i", $department_id);
mysqli_stmt_execute($stmt_dept);
$result_dept = mysqli_stmt_get_result($stmt_dept);
$department = mysqli_fetch_assoc($result_dept);
mysqli_stmt_close($stmt_dept);

// Get statistics
$query_stats = "
    SELECT
        (SELECT COUNT(*) FROM user_details WHERE department_id = ? AND role_id = ?) as total_students,
        (SELECT COUNT(*) FROM user_details WHERE department_id = ? AND role_id = ?) as total_lecturers,
        (SELECT COUNT(*) FROM courses WHERE department_id = ?) as total_courses,
        (SELECT COUNT(*) FROM classes WHERE department_id = ?) as total_classes
";

$stmt_stats = mysqli_prepare($conn, $query_stats);
$role_student = ROLE_STUDENT;
$role_lecturer = ROLE_ADVISOR;
mysqli_stmt_bind_param($stmt_stats, "iiiiii",
    $department_id, $role_student,
    $department_id, $role_lecturer,
    $department_id,
    $department_id
);
mysqli_stmt_execute($stmt_stats);
$result_stats = mysqli_stmt_get_result($stmt_stats);
$stats = mysqli_fetch_assoc($result_stats);
mysqli_stmt_close($stmt_stats);

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
    .welcome-banner h1 {margin: 0 0 10px 0; font-size: 32px;}
    .welcome-banner p {margin: 0; font-size: 16px; opacity: 0.9;}
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
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        text-align: center;
        transition: transform 0.2s;
    }
    .stat-card:hover {transform: translateY(-5px);}
    .stat-icon {font-size: 40px; margin-bottom: 15px;}
    .stat-value {font-size: 42px; font-weight: bold; color: #667eea; margin-bottom: 5px;}
    .stat-label {color: #666; font-size: 14px;}
    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .action-card {
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    .action-card:hover {
        border-color: #667eea;
        transform: translateY(-5px);
        box-shadow: 0 6px 15px rgba(102, 126, 234, 0.3);
    }
    .action-icon {font-size: 48px; margin-bottom: 15px; text-align: center;}
    .action-title {font-size: 18px; font-weight: 600; color: #333; margin-bottom: 8px; text-align: center;}
    .action-desc {font-size: 14px; color: #666; text-align: center;}
</style>

<div class="welcome-banner">
    <h1>Welcome, <?php echo htmlspecialchars($secretary_name); ?>!</h1>
    <p>Department Secretary - <?php echo htmlspecialchars($department['dep_name']); ?></p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?php echo $stats['total_students']; ?></div>
        <div class="stat-label">Students</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👨‍🏫</div>
        <div class="stat-value"><?php echo $stats['total_lecturers']; ?></div>
        <div class="stat-label">Lecturers</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
        <div class="stat-label">Courses</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🏫</div>
        <div class="stat-value"><?php echo $stats['total_classes']; ?></div>
        <div class="stat-label">Classes</div>
    </div>
</div>

<h2 style="font-size: 20px; font-weight: 600; margin: 30px 0 15px; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Quick Actions</h2>

<div class="action-grid">
    <a href="students/list.php" class="action-card">
        <div class="action-icon">👥</div>
        <div class="action-title">Manage Students</div>
        <div class="action-desc">View, create, edit, and delete students</div>
    </a>

    <a href="lecturers/list.php" class="action-card">
        <div class="action-icon">👨‍🏫</div>
        <div class="action-title">Manage Lecturers</div>
        <div class="action-desc">View, create, edit, and delete lecturers</div>
    </a>

    <a href="courses/list.php" class="action-card">
        <div class="action-icon">📚</div>
        <div class="action-title">Manage Courses</div>
        <div class="action-desc">View, create, edit, and delete courses</div>
    </a>

    <a href="classes/list.php" class="action-card">
        <div class="action-icon">🏫</div>
        <div class="action-title">Manage Classes</div>
        <div class="action-desc">View, create, edit, and delete classes</div>
    </a>

    <a href="reports/department_overview.php" class="action-card">
        <div class="action-icon">📊</div>
        <div class="action-title">View Reports</div>
        <div class="action-desc">Department statistics and reports</div>
    </a>

    <a href="exports/index.php" class="action-card">
        <div class="action-icon">📥</div>
        <div class="action-title">Export Data</div>
        <div class="action-desc">Export department data to CSV</div>
    </a>
</div>

<div style="padding: 20px; background: white; border-radius: 8px; border-left: 4px solid #667eea;">
    <h3 style="margin: 0 0 10px 0; font-size: 16px;">📋 Your Responsibilities</h3>
    <ul style="margin: 0; padding-left: 20px; color: #666; font-size: 14px; line-height: 1.8;">
        <li>Manage student records within your department</li>
        <li>Maintain lecturer information and assignments</li>
        <li>Update course information as needed</li>
        <li>Manage class enrollments and information</li>
        <li>Generate reports for department administration</li>
        <li>Export data for record-keeping purposes</li>
    </ul>
</div>

<?php require_once '../includes/footer.php'; ?>
