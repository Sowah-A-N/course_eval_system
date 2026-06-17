<?php

/**
 * Evaluation Success Page
 *
 * Confirmation page displayed after successful evaluation submission.
 * Shows success message and provides links to next actions.
 *
 * Features:
 * - Success confirmation message
 * - Display evaluated course name
 * - Submission timestamp
 * - Links to evaluate another course
 * - Link to view history
 * - Link back to dashboard
 *
 * Role Required: ROLE_STUDENT
 */

// Include required files
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

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
$student_name = $_SESSION['full_name'];

// Get evaluated course name from session (set in submit.php)
$evaluated_course = isset($_SESSION['evaluated_course']) ? $_SESSION['evaluated_course'] : 'the course';
unset($_SESSION['evaluated_course']); // Clear after retrieving

// Get remaining pending evaluations count
$query_pending = "
    SELECT COUNT(*) as pending_count
    FROM evaluation_tokens
    WHERE student_user_id = ?
    AND is_used = 0
";

$stmt_pending = mysqli_prepare($conn, $query_pending);
mysqli_stmt_bind_param($stmt_pending, "i", $student_id);
mysqli_stmt_execute($stmt_pending);
$result_pending = mysqli_stmt_get_result($stmt_pending);
$pending_data = mysqli_fetch_assoc($result_pending);
$pending_count = $pending_data['pending_count'];
mysqli_stmt_close($stmt_pending);

// Get total completed evaluations
$query_completed = "
    SELECT COUNT(*) as completed_count
    FROM evaluation_tokens
    WHERE student_user_id = ?
    AND is_used = 1
";

$stmt_completed = mysqli_prepare($conn, $query_completed);
mysqli_stmt_bind_param($stmt_completed, "i", $student_id);
mysqli_stmt_execute($stmt_completed);
$result_completed = mysqli_stmt_get_result($stmt_completed);
$completed_data = mysqli_fetch_assoc($result_completed);
$completed_count = $completed_data['completed_count'];
mysqli_stmt_close($stmt_completed);

// Set page title
$page_title = 'Evaluation Submitted Successfully';

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'Available Courses' => 'available_courses.php',
    'Success' => ''
];

// Include header
require_once '../../includes/header.php';
?>

<style>
    .success-container {
        max-width: 700px;
        margin: 50px auto;
        text-align: center;
    }

    .success-card {
        background: white;
        border-radius: 16px;
        padding: 50px 40px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    }

    .success-icon {
        width: 120px;
        height: 120px;
        margin: 0 auto 30px;
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 60px;
        color: white;
        box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
        animation: scaleIn 0.5s ease-out;
    }

    @keyframes scaleIn {
        0% {
            transform: scale(0);
            opacity: 0;
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .success-title {
        font-size: 32px;
        font-weight: bold;
        color: #28a745;
        margin-bottom: 15px;
    }

    .success-message {
        font-size: 18px;
        color: #666;
        line-height: 1.6;
        margin-bottom: 30px;
    }

    .course-name {
        font-size: 20px;
        font-weight: 600;
        color: #333;
        padding: 15px 20px;
        background: #f8f9fa;
        border-radius: 8px;
        margin: 20px 0;
    }

    .submission-info {
        font-size: 14px;
        color: #999;
        margin-bottom: 30px;
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
        margin: 30px 0;
        padding: 20px 0;
        border-top: 1px solid #e0e0e0;
        border-bottom: 1px solid #e0e0e0;
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        font-size: 36px;
        font-weight: bold;
        color: #667eea;
    }

    .stat-value.pending {
        color: #ffc107;
    }

    .stat-value.completed {
        color: #28a745;
    }

    .stat-label {
        font-size: 14px;
        color: #666;
        margin-top: 5px;
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-top: 30px;
    }

    .btn {
        padding: 15px 30px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.3s;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }

    .btn-secondary:hover {
        background: #f8f9ff;
    }

    .btn-outline {
        background: white;
        color: #6c757d;
        border: 2px solid #e0e0e0;
    }

    .btn-outline:hover {
        border-color: #667eea;
        color: #667eea;
    }

    .thank-you-note {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #667eea;
        margin-top: 30px;
        text-align: left;
    }

    .thank-you-note h3 {
        color: #333;
        font-size: 16px;
        margin-bottom: 10px;
    }

    .thank-you-note p {
        color: #666;
        font-size: 14px;
        line-height: 1.6;
        margin: 0;
    }

    @media (max-width: 768px) {
        .success-card {
            padding: 40px 20px;
        }

        .success-title {
            font-size: 24px;
        }

        .success-message {
            font-size: 16px;
        }

        .stats-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="success-container">
    <div class="success-card">
        <!-- Success Icon -->
        <div class="success-icon">
            ✓
        </div>

        <!-- Success Title -->
        <h1 class="success-title">Evaluation Submitted Successfully!</h1>

        <!-- Success Message -->
        <p class="success-message">
            Thank you for taking the time to provide your valuable feedback.
            Your responses have been recorded and will help improve the quality of education.
        </p>

        <!-- Evaluated Course -->
        <div class="course-name">
            <?php echo htmlspecialchars($evaluated_course); ?>
        </div>

        <!-- Submission Info -->
        <div class="submission-info">
            Submitted on <?php echo date('F d, Y \a\t h:i A'); ?>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-value completed"><?php echo $completed_count; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-item">
                <div class="stat-value pending"><?php echo $pending_count; ?></div>
                <div class="stat-label">Remaining</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if ($pending_count > 0): ?>
                <a href="available_courses.php" class="btn btn-primary">
                    <span>📝</span>
                    Evaluate Another Course (<?php echo $pending_count; ?> remaining)
                </a>
            <?php else: ?>
                <div style="padding: 20px; background: #d4edda; border-radius: 8px; color: #155724; margin-bottom: 15px;">
                    <strong>🎉 Congratulations!</strong><br>
                    You have completed all your course evaluations!
                </div>
            <?php endif; ?>

            <a href="history.php" class="btn btn-secondary">
                <span>📜</span>
                View Submission History
            </a>

            <a href="../index.php" class="btn btn-outline">
                <span>🏠</span>
                Back to Dashboard
            </a>
        </div>

        <!-- Thank You Note -->
        <div class="thank-you-note">
            <h3>💡 Why Your Feedback Matters</h3>
            <p>
                Your honest feedback helps lecturers understand what's working well and what can be improved.
                All responses are completely anonymous and are aggregated with other students' feedback to
                provide meaningful insights. Thank you for contributing to the continuous improvement of
                <?php echo INSTITUTION_NAME; ?>!
            </p>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
