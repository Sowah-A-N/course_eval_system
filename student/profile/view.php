<?php

/**
 * Student Profile View
 *
 * Displays student profile information including personal details,
 * academic information, and account status.
 *
 * Features:
 * - View personal information (name, email, student ID)
 * - View academic information (level, class, department, programme)
 * - View account status and creation date
 * - Link to change password
 * - Display evaluation statistics
 *
 * Role Required: ROLE_STUDENT
 */

// Include required files
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';

// Start session and check login
start_secure_session();
check_login();

// Check if user is a student
if ($_SESSION['role_id'] !== ROLE_STUDENT) {
    $_SESSION['flash_message'] = 'Access denied. This page is only for students.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

// Get student information
$student_id = $_SESSION['user_id'];

// Set page title
$page_title = 'My Profile';

// Get complete student information
$query = "
    SELECT
        u.user_id,
        u.username,
        u.email,
        u.f_name,
        u.l_name,
        u.unique_id,
        u.is_active,
        /* u.date_created, */
        u.level_id,
        u.class_id,
        u.department_id,
        l.level_name,
        l.level_number,
        c.class_name,
        c.year_of_completion,
        d.dep_name,
        d.dep_code
       /* p.programme_name,
        p.programme_code */
    FROM user_details u
    LEFT JOIN level l ON u.level_id = l.t_id
    LEFT JOIN classes c ON u.class_id = c.t_id
    LEFT JOIN department d ON u.department_id = d.t_id
    LEFT JOIN programme p ON c.programme_id = p.t_id
    WHERE u.user_id = ?
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$student) {
    $_SESSION['flash_message'] = 'Profile information not found.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../index.php");
    exit();
}

// Get evaluation statistics
$query_stats = "
    SELECT
        COUNT(*) as total_evaluations,
        SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as completed_evaluations,
        SUM(CASE WHEN is_used = 0 THEN 1 ELSE 0 END) as pending_evaluations
    FROM evaluation_tokens
    WHERE student_user_id = ?
";

$stmt_stats = mysqli_prepare($conn, $query_stats);
mysqli_stmt_bind_param($stmt_stats, "i", $student_id);
mysqli_stmt_execute($stmt_stats);
$result_stats = mysqli_stmt_get_result($stmt_stats);
$stats = mysqli_fetch_assoc($result_stats);
mysqli_stmt_close($stmt_stats);

$total_evaluations = $stats['total_evaluations'];
$completed_evaluations = $stats['completed_evaluations'];
$pending_evaluations = $stats['pending_evaluations'];
$completion_rate = $total_evaluations > 0 ? round(($completed_evaluations / $total_evaluations) * 100, 1) : 0;

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'My Profile' => ''
];

// Include header
require_once '../../includes/header.php';
?>

<style>
    .profile-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        display: flex;
        align-items: center;
        gap: 30px;
    }

    .profile-avatar {
        width: 120px;
        height: 120px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        border: 4px solid rgba(255, 255, 255, 0.3);
    }

    .profile-header-info {
        flex: 1;
    }

    .profile-name {
        font-size: 32px;
        font-weight: bold;
        margin-bottom: 10px;
    }

    .profile-subtitle {
        font-size: 16px;
        opacity: 0.9;
    }

    .profile-meta {
        display: flex;
        gap: 25px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    .profile-meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .profile-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .info-card {
        background: white;
        border-radius: 8px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .info-card-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: 500;
        color: #666;
        font-size: 14px;
    }

    .info-value {
        font-weight: 600;
        color: #333;
        font-size: 14px;
        text-align: right;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-active {
        background: #d4edda;
        color: #155724;
    }

    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }

    .stats-grid {
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

    .stat-icon {
        font-size: 32px;
        margin-bottom: 10px;
    }

    .stat-value {
        font-size: 32px;
        font-weight: bold;
        color: #667eea;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 14px;
        color: #666;
    }

    .action-buttons {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 5px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }

    .btn-secondary:hover {
        background: #f8f9ff;
    }

    .alert-info-custom {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 15px 20px;
        border-radius: 8px;
        margin-top: 20px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            text-align: center;
        }

        .profile-meta {
            justify-content: center;
        }

        .profile-grid {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .action-buttons {
            flex-direction: column;
        }

        .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="profile-container">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-avatar">
            👤
        </div>
        <div class="profile-header-info">
            <div class="profile-name">
                <?php echo htmlspecialchars($student['f_name'] . ' ' . $student['l_name']); ?>
            </div>
            <div class="profile-subtitle">
                Student at <?php echo htmlspecialchars($student['dep_name'] ?? 'N/A'); ?>
            </div>
            <div class="profile-meta">
                <div class="profile-meta-item">
                    <span>🆔</span>
                    <span><?php echo htmlspecialchars($student['unique_id'] ?? 'N/A'); ?></span>
                </div>
                <div class="profile-meta-item">
                    <span>📚</span>
                    <span><?php echo htmlspecialchars($student['level_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="profile-meta-item">
                    <span>👥</span>
                    <span><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="profile-meta-item">
                    <span>📅</span>
                    <span>Member since <?php echo date('M Y', strtotime($student['created_at'])); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Evaluation Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div class="stat-value"><?php echo $total_evaluations; ?></div>
            <div class="stat-label">Total Evaluations</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value" style="color: #28a745;"><?php echo $completed_evaluations; ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-value" style="color: #ffc107;"><?php echo $pending_evaluations; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?php echo $completion_rate; ?>%</div>
            <div class="stat-label">Completion Rate</div>
        </div>
    </div>

    <!-- Profile Information Grid -->
    <div class="profile-grid">
        <!-- Personal Information -->
        <div class="info-card">
            <div class="info-card-title">
                <span>👤</span>
                Personal Information
            </div>
            <div class="info-row">
                <span class="info-label">First Name</span>
                <span class="info-value"><?php echo htmlspecialchars($student['f_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Last Name</span>
                <span class="info-value"><?php echo htmlspecialchars($student['l_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email Address</span>
                <span class="info-value"><?php echo htmlspecialchars($student['email']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Username</span>
                <span class="info-value"><?php echo htmlspecialchars($student['username']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Student ID</span>
                <span class="info-value"><?php echo htmlspecialchars($student['unique_id'] ?? 'N/A'); ?></span>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="info-card">
            <div class="info-card-title">
                <span>🎓</span>
                Academic Information
            </div>
            <div class="info-row">
                <span class="info-label">Department</span>
                <span class="info-value"><?php echo htmlspecialchars($student['dep_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Department Code</span>
                <span class="info-value"><?php echo htmlspecialchars($student['dep_code'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Programme</span>
                <span class="info-value"><?php echo htmlspecialchars($student['programme_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Level</span>
                <span class="info-value"><?php echo htmlspecialchars($student['level_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Class</span>
                <span class="info-value"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></span>
            </div>
            <?php if ($student['year_of_completion']): ?>
                <div class="info-row">
                    <span class="info-label">Expected Completion</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['year_of_completion']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Account Information -->
        <div class="info-card">
            <div class="info-card-title">
                <span>🔐</span>
                Account Information
            </div>
            <div class="info-row">
                <span class="info-label">Account Status</span>
                <span class="info-value">
                    <?php if ($student['is_active'] == 1): ?>
                        <span class="status-badge status-active">Active</span>
                    <?php else: ?>
                        <span class="status-badge status-inactive">Inactive</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Account Created</span>
                <span class="info-value"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">User ID</span>
                <span class="info-value"><?php echo htmlspecialchars($student['user_id']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Role</span>
                <span class="info-value">Student</span>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="change_password.php" class="btn btn-primary">
            <span>🔒</span>
            Change Password
        </a>
        <a href="../evaluate/available_courses.php" class="btn btn-secondary">
            <span>📝</span>
            Evaluate Courses
        </a>
        <a href="../evaluate/history.php" class="btn btn-secondary">
            <span>📜</span>
            Evaluation History
        </a>
        <a href="../index.php" class="btn btn-secondary">
            <span>🏠</span>
            Back to Dashboard
        </a>
    </div>

    <!-- Information Notice -->
    <div class="alert-info-custom">
        <span style="font-size: 24px;">ℹ️</span>
        <div>
            <strong>Profile Information</strong><br>
            If you notice any incorrect information in your profile (name, student ID, level, class, department),
            please contact your department secretary or the administration office to update your records.
            For security reasons, students cannot directly edit their academic information.
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
