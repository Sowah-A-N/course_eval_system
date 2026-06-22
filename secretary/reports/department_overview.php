<?php
/**
 * Secretary Department Overview Report
 *
 * Comprehensive overview of department data including counts, lists, and statistics.
 *
 * Features:
 * - Student, lecturer, course, class counts
 * - Recent additions
 * - Status breakdown
 * - Printable format
 *
 * Role Required: ROLE_SECRETARY
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_SECRETARY) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$department_id = $_SESSION['department_id'];
$page_title = 'Department Overview Report';

// Get department info
$query_dept = "SELECT * FROM department WHERE t_id = ?";
$stmt_dept = mysqli_prepare($conn, $query_dept);
mysqli_stmt_bind_param($stmt_dept, "i", $department_id);
mysqli_stmt_execute($stmt_dept);
$department = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dept));
mysqli_stmt_close($stmt_dept);

// Get statistics
$stats = [];

// Students
$query = "SELECT COUNT(*) as total, SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) as active FROM user_details WHERE department_id=? AND role_id=?";
$stmt = mysqli_prepare($conn, $query);
$role = ROLE_STUDENT;
mysqli_stmt_bind_param($stmt, "ii", $department_id, $role);
mysqli_stmt_execute($stmt);
$stats['students'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Lecturers
$stmt = mysqli_prepare($conn, $query);
$role = ROLE_ADVISOR;
mysqli_stmt_bind_param($stmt, "ii", $department_id, $role);
mysqli_stmt_execute($stmt);
$stats['lecturers'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Courses
$query = "SELECT COUNT(*) as total FROM courses WHERE department_id=?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $department_id);
mysqli_stmt_execute($stmt);
$stats['courses'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Classes
$query = "SELECT COUNT(*) as total FROM classes WHERE department_id=?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $department_id);
mysqli_stmt_execute($stmt);
$stats['classes'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Students by level
$query = "SELECT l.level_name, COUNT(*) as count FROM user_details u JOIN level l ON u.level_id=l.t_id WHERE u.department_id=? AND u.role_id=? GROUP BY l.t_id ORDER BY l.level_number";
$stmt = mysqli_prepare($conn, $query);
$role = ROLE_STUDENT;
mysqli_stmt_bind_param($stmt, "ii", $department_id, $role);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$students_by_level = [];
while ($row = mysqli_fetch_assoc($result)) $students_by_level[] = $row;
mysqli_stmt_close($stmt);

// Students by class
$query = "SELECT c.class_name, COUNT(*) as count FROM user_details u JOIN classes c ON u.class_id=c.t_id WHERE u.department_id=? AND u.role_id=? GROUP BY c.t_id ORDER BY c.class_name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $department_id, $role);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$students_by_class = [];
while ($row = mysqli_fetch_assoc($result)) $students_by_class[] = $row;
mysqli_stmt_close($stmt);

// Recent students (last 10)
$query = "SELECT f_name, l_name, unique_id, date_created FROM user_details WHERE department_id=? AND role_id=? ORDER BY date_created DESC LIMIT 10";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $department_id, $role);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$recent_students = [];
while ($row = mysqli_fetch_assoc($result)) $recent_students[] = $row;
mysqli_stmt_close($stmt);

require_once '../../includes/header.php';
?>

<style>
    @media print {
        .no-print {display: none !important;}
        body {background: white;}
    }
    .report-header {background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px;}
    .report-header h1 {margin: 0 0 10px 0; font-size: 28px;}
    .report-header p {margin: 0; opacity: 0.9;}
    .stats-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;}
    .stat-card {background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;}
    .stat-value {font-size: 42px; font-weight: bold; color: #667eea;}
    .stat-label {font-size: 14px; color: #666; margin-top: 5px;}
    .stat-sub {font-size: 12px; color: #999; margin-top: 5px;}
    .section-title {font-size: 20px; font-weight: 600; color: #333; margin: 30px 0 15px; border-bottom: 2px solid #667eea; padding-bottom: 10px;}
    .data-table {background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;}
    .data-row {display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0;}
    .data-row:last-child {border-bottom: none;}
    .data-label {font-weight: 500; color: #333;}
    .data-value {color: #667eea; font-weight: 600;}
    .btn {padding: 10px 20px; border: none; border-radius: 5px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 10px;}
    .btn-primary {background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;}
    .btn-secondary {background: #6c757d; color: white;}
</style>

<div class="report-header">
    <h1>Department Overview Report</h1>
    <p><?php echo htmlspecialchars($department['dep_name']); ?> (<?php echo htmlspecialchars($department['dep_code']); ?>)</p>
    <p style="font-size: 14px; margin-top: 10px;">Generated on <?php echo date('F d, Y h:i A'); ?></p>
</div>

<div class="no-print" style="margin-bottom: 20px;">
    <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
    <a href="../index.php" class="btn btn-secondary">← Back to Dashboard</a>
</div>

<!-- Summary Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['students']['total']; ?></div>
        <div class="stat-label">Total Students</div>
        <div class="stat-sub"><?php echo $stats['students']['active']; ?> active</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['lecturers']['total']; ?></div>
        <div class="stat-label">Total Lecturers</div>
        <div class="stat-sub"><?php echo $stats['lecturers']['active']; ?> active</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['courses']['total']; ?></div>
        <div class="stat-label">Total Courses</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['classes']['total']; ?></div>
        <div class="stat-label">Total Classes</div>
    </div>
</div>

<!-- Students by Level -->
<?php if (!empty($students_by_level)): ?>
    <h2 class="section-title">Students by Level</h2>
    <div class="data-table">
        <?php foreach ($students_by_level as $item): ?>
            <div class="data-row">
                <span class="data-label"><?php echo htmlspecialchars($item['level_name']); ?></span>
                <span class="data-value"><?php echo $item['count']; ?> student(s)</span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Students by Class -->
<?php if (!empty($students_by_class)): ?>
    <h2 class="section-title">Students by Class</h2>
    <div class="data-table">
        <?php foreach ($students_by_class as $item): ?>
            <div class="data-row">
                <span class="data-label"><?php echo htmlspecialchars($item['class_name']); ?></span>
                <span class="data-value"><?php echo $item['count']; ?> student(s)</span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Recent Students -->
<?php if (!empty($recent_students)): ?>
    <h2 class="section-title">Recent Student Additions</h2>
    <div class="data-table">
        <?php foreach ($recent_students as $student): ?>
            <div class="data-row">
                <span class="data-label">
                    <?php echo htmlspecialchars($student['f_name'] . ' ' . $student['l_name']); ?>
                </span>
                <span class="data-value"><?php echo date('M d, Y', strtotime($student['date_created'])); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
    <h3 style="margin: 0 0 10px 0; font-size: 16px;">📋 Report Summary</h3>
    <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">
        This report provides a comprehensive overview of <?php echo htmlspecialchars($department['dep_name']); ?> department data.
        All statistics are current as of the report generation date. For detailed information or to manage records,
        please use the respective management sections from the dashboard.
    </p>
</div>

<?php require_once '../../includes/footer.php'; ?>
