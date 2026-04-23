<?php

/**
 * Secretary Data Exports
 *
 * Export department data to CSV format.
 *
 * Features:
 * - Export students
 * - Export lecturers
 * - Export courses
 * - Export classes
 * - Department-scoped data only
 * - CSV format
 *
 * Role Required: ROLE_SECRETARY
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] != ROLE_SECRETARY) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$department_id = $_SESSION['department_id'];
$page_title = 'Export Data';

// Get department info
$query_dept = "SELECT * FROM department WHERE t_id = ?";
$stmt_dept = mysqli_prepare($conn, $query_dept);
mysqli_stmt_bind_param($stmt_dept, "i", $department_id);
mysqli_stmt_execute($stmt_dept);
$department = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dept));
mysqli_stmt_close($stmt_dept);

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $export_type . '_' . date('Y-m-d') . '.csv"');

    // Create output stream
    $output = fopen('php://output', 'w');

    switch ($export_type) {
        case 'students':
            // Export students
            fputcsv($output, ['Student ID', 'First Name', 'Last Name', 'Email', 'Username', 'Level', 'Class', 'Status', 'Date Created']);

            $query = "
                SELECT u.unique_id, u.f_name, u.l_name, u.email, u.username,
                       l.level_name, c.class_name, u.is_active, u.date_created
                FROM user_details u
                LEFT JOIN level l ON u.level_id = l.t_id
                LEFT JOIN classes c ON u.class_id = c.t_id
                WHERE u.department_id = ? AND u.role_id = ?
                ORDER BY u.f_name, u.l_name
            ";
            $stmt = mysqli_prepare($conn, $query);
            $role = ROLE_STUDENT;
            mysqli_stmt_bind_param($stmt, "ii", $department_id, $role);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['unique_id'],
                    $row['f_name'],
                    $row['l_name'],
                    $row['email'],
                    $row['username'],
                    $row['level_name'],
                    $row['class_name'],
                    $row['is_active'] ? 'Active' : 'Inactive',
                    date('Y-m-d', strtotime($row['date_created']))
                ]);
            }
            mysqli_stmt_close($stmt);
            break;

        case 'lecturers':
            // Export lecturers
            fputcsv($output, ['First Name', 'Last Name', 'Email', 'Username', 'Courses Assigned', 'Status', 'Date Created']);

            $query = "
                SELECT u.f_name, u.l_name, u.email, u.username, u.is_active, u.date_created,
                       COUNT(DISTINCT cl.course_id) as course_count
                FROM user_details u
                LEFT JOIN course_lecturers cl ON u.user_id = cl.lecturer_user_id
                WHERE u.department_id = ? AND u.role_id = ?
                GROUP BY u.user_id
                ORDER BY u.f_name, u.l_name
            ";
            $stmt = mysqli_prepare($conn, $query);
            $role = ROLE_ADVISOR;
            mysqli_stmt_bind_param($stmt, "ii", $department_id, $role);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['f_name'],
                    $row['l_name'],
                    $row['email'],
                    $row['username'],
                    $row['course_count'],
                    $row['is_active'] ? 'Active' : 'Inactive',
                    date('Y-m-d', strtotime($row['date_created']))
                ]);
            }
            mysqli_stmt_close($stmt);
            break;

        case 'courses':
            // Export courses
            fputcsv($output, ['Course Code', 'Course Name', 'Level', 'Semester', 'Credit Hours', 'Evaluations']);

            $query = "
                SELECT c.course_code, c.name, l.level_name, s.semester_name, c.credit_hours,
                       COUNT(DISTINCT et.token_id) as eval_count
                FROM courses c
                LEFT JOIN level l ON c.level_id = l.t_id
                LEFT JOIN semesters s ON c.semester_id = s.semester_id
                LEFT JOIN evaluation_tokens et ON c.id = et.course_id
                WHERE c.department_id = ?
                GROUP BY c.id
                ORDER BY c.course_code
            ";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $department_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['course_code'],
                    $row['name'],
                    $row['level_name'],
                    $row['semester_name'],
                    $row['credit_hours'],
                    $row['eval_count']
                ]);
            }
            mysqli_stmt_close($stmt);
            break;

        case 'classes':
            // Export classes
            fputcsv($output, ['Class Code', 'Class Name', 'Students Enrolled']);

            $query = "
                SELECT c.class_code, c.class_name, COUNT(DISTINCT u.user_id) as student_count
                FROM classes c
                LEFT JOIN user_details u ON c.t_id = u.class_id AND u.role_id = ?
                WHERE c.department_id = ?
                GROUP BY c.t_id
                ORDER BY c.class_name
            ";
            $stmt = mysqli_prepare($conn, $query);
            $role = ROLE_STUDENT;
            mysqli_stmt_bind_param($stmt, "ii", $role, $department_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['class_code'],
                    $row['class_name'],
                    $row['student_count']
                ]);
            }
            mysqli_stmt_close($stmt);
            break;
    }

    fclose($output);
    exit();
}

// Get counts for display
$query_counts = "
    SELECT
        (SELECT COUNT(*) FROM user_details WHERE department_id = ? AND role_id = ?) as students,
        (SELECT COUNT(*) FROM user_details WHERE department_id = ? AND role_id = ?) as lecturers,
        (SELECT COUNT(*) FROM courses WHERE department_id = ?) as courses,
        (SELECT COUNT(*) FROM classes WHERE department_id = ?) as classes
";
$stmt_counts = mysqli_prepare($conn, $query_counts);
$role_student = ROLE_STUDENT;
$role_lecturer = ROLE_ADVISOR;
mysqli_stmt_bind_param(
    $stmt_counts,
    "iiiiii",
    $department_id,
    $role_student,
    $department_id,
    $role_lecturer,
    $department_id,
    $department_id
);
mysqli_stmt_execute($stmt_counts);
$counts = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_counts));
mysqli_stmt_close($stmt_counts);

require_once '../../includes/header.php';
?>

<style>
    .export-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .export-header h1 {
        margin: 0 0 10px 0;
        font-size: 32px;
    }

    .export-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 16px;
    }

    .export-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .export-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s;
        border: 2px solid transparent;
    }

    .export-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 15px rgba(102, 126, 234, 0.3);
        border-color: #667eea;
    }

    .export-icon {
        font-size: 48px;
        margin-bottom: 15px;
        text-align: center;
    }

    .export-title {
        font-size: 20px;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
        text-align: center;
    }

    .export-count {
        font-size: 36px;
        font-weight: bold;
        color: #667eea;
        text-align: center;
        margin-bottom: 10px;
    }

    .export-label {
        font-size: 14px;
        color: #666;
        text-align: center;
        margin-bottom: 20px;
    }

    .btn-export {
        display: block;
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s;
    }

    .btn-export:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .info-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #667eea;
    }

    .info-section h3 {
        margin: 0 0 15px 0;
        font-size: 18px;
        color: #333;
    }

    .info-section ul {
        margin: 0;
        padding-left: 20px;
        color: #666;
        line-height: 1.8;
    }
</style>

<div class="export-header">
    <h1>📥 Export Data</h1>
    <p>Export department data to CSV format for backup or analysis</p>
    <p style="font-size: 14px; margin-top: 10px; opacity: 0.8;">
        Department: <?php echo htmlspecialchars($department['dep_name']); ?>
    </p>
</div>

<div class="export-grid">
    <!-- Students Export -->
    <div class="export-card">
        <div class="export-icon">👥</div>
        <div class="export-title">Students</div>
        <div class="export-count"><?php echo $counts['students']; ?></div>
        <div class="export-label">Total Records</div>
        <a href="?export=students" class="btn-export">📥 Export Students CSV</a>
    </div>

    <!-- Lecturers Export -->
    <div class="export-card">
        <div class="export-icon">👨‍🏫</div>
        <div class="export-title">Lecturers</div>
        <div class="export-count"><?php echo $counts['lecturers']; ?></div>
        <div class="export-label">Total Records</div>
        <a href="?export=lecturers" class="btn-export">📥 Export Lecturers CSV</a>
    </div>

    <!-- Courses Export -->
    <div class="export-card">
        <div class="export-icon">📚</div>
        <div class="export-title">Courses</div>
        <div class="export-count"><?php echo $counts['courses']; ?></div>
        <div class="export-label">Total Records</div>
        <a href="?export=courses" class="btn-export">📥 Export Courses CSV</a>
    </div>

    <!-- Classes Export -->
    <div class="export-card">
        <div class="export-icon">🏫</div>
        <div class="export-title">Classes</div>
        <div class="export-count"><?php echo $counts['classes']; ?></div>
        <div class="export-label">Total Records</div>
        <a href="?export=classes" class="btn-export">📥 Export Classes CSV</a>
    </div>
</div>

<div class="info-section">
    <h3>📋 Export Information</h3>
    <ul>
        <li><strong>Format:</strong> CSV (Comma-Separated Values) - compatible with Excel, Google Sheets, and other spreadsheet applications</li>
        <li><strong>Scope:</strong> Only data from your department (<?php echo htmlspecialchars($department['dep_name']); ?>) is included</li>
        <li><strong>File Naming:</strong> Files are named with the format: [type]_[date].csv (e.g., students_2026-02-26.csv)</li>
        <li><strong>Character Encoding:</strong> UTF-8 encoding preserves special characters and international text</li>
        <li><strong>Privacy:</strong> Exported data contains sensitive information - handle with care and store securely</li>
        <li><strong>Usage:</strong> Use exports for backup, data analysis, reporting, or migration purposes</li>
    </ul>
</div>

<div style="margin-top: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
    <h3 style="margin: 0 0 10px 0; color: #856404; font-size: 16px;">⚠️ Data Security Reminder</h3>
    <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
        Exported files contain personal and sensitive information. Please ensure you:
    </p>
    <ul style="margin: 10px 0 0 20px; padding: 0; color: #856404; font-size: 14px;">
        <li>Store files securely and restrict access</li>
        <li>Do not share files via unsecured channels</li>
        <li>Delete files when no longer needed</li>
        <li>Comply with institutional data protection policies</li>
    </ul>
</div>

<?php require_once '../../includes/footer.php'; ?>
