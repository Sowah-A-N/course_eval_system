<?php

/**
 * Available Courses for Evaluation
 *
 * Displays all courses that the student can evaluate.
 * Shows pending (not yet evaluated) and completed evaluations.
 *
 * Features:
 * - List all evaluation tokens for the student
 * - Show pending evaluations with "Evaluate Now" button
 * - Show completed evaluations with submission date
 * - Filter by status (all, pending, completed)
 * - Display course information
 * - Direct links to evaluation form
 *
 * Note: When we implement lecturer selection, this page will show
 * the assigned lecturer for each course evaluation.
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
$student_name = $_SESSION['full_name'];

// Set page title
$page_title = 'Available Course Evaluations';

// Get filter parameter
$allowed_statuses = ['all', 'pending', 'completed'];
$filter_status = isset($_GET['status']) && in_array($_GET['status'], $allowed_statuses, true)
    ? $_GET['status'] : 'all';

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

// Get all evaluation tokens for this student with course details
$query = "
    SELECT
        et.token_id,
        et.token,
        et.is_used,
        et.created_at,
        et.used_at,
        c.id as course_id,
        c.course_code,
        c.name as course_name,
        /* c.description as course_description,*/
        l.level_name,
        s.semester_name,
        ay.year_label,
        d.dep_name
    FROM evaluation_tokens et
    JOIN courses c ON et.course_id = c.id
    LEFT JOIN level l ON c.level_id = l.t_id
    LEFT JOIN semesters s ON et.semester_id = s.semester_id
    LEFT JOIN academic_year ay ON et.academic_year_id = ay.academic_year_id
    LEFT JOIN department d ON c.department_id = d.t_id
    WHERE et.student_user_id = ?
";

// Add status filter
if ($filter_status == 'pending') {
    $query .= " AND et.is_used = 0";
} elseif ($filter_status == 'completed') {
    $query .= " AND et.is_used = 1";
}

// Order by status (pending first) then by creation date
$query .= " ORDER BY et.is_used ASC, et.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$evaluations = [];
$pending_count = 0;
$completed_count = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $evaluations[] = $row;
    if ($row['is_used'] == 0) {
        $pending_count++;
    } else {
        $completed_count++;
    }
}

mysqli_stmt_close($stmt);

$total_count = count($evaluations);

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'Evaluate Courses' => ''
];

// Include header
require_once '../../includes/header.php';
?>

<style>
    .page-header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .filter-tabs {
        display: flex;
        gap: 10px;
        background: white;
        padding: 5px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .filter-tab {
        padding: 10px 20px;
        border: none;
        background: transparent;
        color: #666;
        font-size: 14px;
        font-weight: 500;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s;
    }

    .filter-tab:hover {
        background: #f0f0f0;
        color: #333;
    }

    .filter-tab.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .stats-summary {
        display: flex;
        gap: 20px;
        background: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        flex-wrap: wrap;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .stat-value {
        font-weight: bold;
        font-size: 18px;
    }

    .stat-value.pending {
        color: #ffc107;
    }

    .stat-value.completed {
        color: #28a745;
    }

    .stat-value.total {
        color: #667eea;
    }

    .courses-grid {
        display: grid;
        gap: 20px;
        margin-top: 20px;
    }

    .course-card {
        background: white;
        border-radius: 8px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
        position: relative;
    }

    .course-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .course-card.completed {
        background: #f8f9fa;
        border-left: 4px solid #28a745;
    }

    .course-card.pending {
        border-left: 4px solid #ffc107;
    }

    .status-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 6px 15px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-badge.pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-badge.completed {
        background: #d4edda;
        color: #155724;
    }

    .course-header {
        margin-bottom: 15px;
        padding-right: 100px;
    }

    .course-code {
        font-size: 14px;
        color: #667eea;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .course-name {
        font-size: 20px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .course-meta {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 13px;
        color: #666;
        margin-bottom: 15px;
    }

    .course-meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .course-description {
        font-size: 14px;
        color: #666;
        line-height: 1.6;
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
    }

    .course-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 1px solid #e0e0e0;
    }

    .submission-info {
        font-size: 13px;
        color: #666;
    }

    .submission-info strong {
        color: #333;
    }

    .btn {
        padding: 10px 25px;
        border: none;
        border-radius: 5px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
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
        background: #6c757d;
        color: white;
    }

    .btn-success {
        background: #28a745;
        color: white;
        cursor: default;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .empty-state-icon {
        font-size: 80px;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .empty-state h3 {
        font-size: 24px;
        color: #333;
        margin-bottom: 10px;
    }

    .empty-state p {
        font-size: 16px;
        color: #666;
        margin-bottom: 25px;
    }

    .alert-info-custom {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    @media (max-width: 768px) {
        .page-header-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-tabs {
            flex-direction: column;
        }

        .stats-summary {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1>Available Course Evaluations</h1>
    <p>Select a course below to submit your evaluation</p>
</div>

<!-- Filters and Summary -->
<div class="page-header-actions">
    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?status=all" class="filter-tab <?php echo $filter_status == 'all' ? 'active' : ''; ?>">
            All (<?php echo $total_count; ?>)
        </a>
        <a href="?status=pending" class="filter-tab <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">
            Pending (<?php echo $pending_count; ?>)
        </a>
        <a href="?status=completed" class="filter-tab <?php echo $filter_status == 'completed' ? 'active' : ''; ?>">
            Completed (<?php echo $completed_count; ?>)
        </a>
    </div>

    <!-- Stats Summary -->
    <div class="stats-summary">
        <div class="stat-item">
            <span>📊</span>
            <span class="stat-value total"><?php echo $total_count; ?></span>
            <span>Total</span>
        </div>
        <div class="stat-item">
            <span>⏳</span>
            <span class="stat-value pending"><?php echo $pending_count; ?></span>
            <span>Pending</span>
        </div>
        <div class="stat-item">
            <span>✅</span>
            <span class="stat-value completed"><?php echo $completed_count; ?></span>
            <span>Completed</span>
        </div>
    </div>
</div>

<!-- Information Alert -->
<?php if ($pending_count > 0): ?>
    <div class="alert-info-custom">
        <span style="font-size: 24px;">💡</span>
        <div>
            <strong>Important Reminder:</strong><br>
            Your evaluations are completely anonymous. Please provide honest and constructive feedback
            to help improve the quality of courses. You have <strong><?php echo $pending_count; ?>
                pending evaluation(s)</strong> to complete.
        </div>
    </div>
<?php endif; ?>

<!-- Courses List -->
<?php if (empty($evaluations)): ?>
    <!-- Empty State -->
    <div class="empty-state">
        <div class="empty-state-icon">📚</div>
        <h3>No Evaluations Available</h3>
        <p>You don't have any course evaluations assigned at the moment.<br>
            Evaluations will appear here when they become available.</p>
        <a href="../index.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
<?php else: ?>
    <!-- Courses Grid -->
    <div class="courses-grid">
        <?php foreach ($evaluations as $eval): ?>
            <div class="course-card <?php echo $eval['is_used'] ? 'completed' : 'pending'; ?>">
                <!-- Status Badge -->
                <div class="status-badge <?php echo $eval['is_used'] ? 'completed' : 'pending'; ?>">
                    <?php echo $eval['is_used'] ? '✓ Completed' : 'Pending'; ?>
                </div>

                <!-- Course Header -->
                <div class="course-header">
                    <div class="course-code"><?php echo htmlspecialchars($eval['course_code']); ?></div>
                    <div class="course-name"><?php echo htmlspecialchars($eval['course_name']); ?></div>
                </div>

                <!-- Course Meta Information -->
                <div class="course-meta">
                    <div class="course-meta-item">
                        <span>📚</span>
                        <span><?php echo htmlspecialchars($eval['level_name']); ?></span>
                    </div>
                    <div class="course-meta-item">
                        <span>📅</span>
                        <span><?php echo htmlspecialchars($eval['semester_name']); ?></span>
                    </div>
                    <div class="course-meta-item">
                        <span>📖</span>
                        <span><?php echo htmlspecialchars($eval['year_label']); ?></span>
                    </div>
                    <div class="course-meta-item">
                        <span>🏢</span>
                        <span><?php echo htmlspecialchars($eval['dep_name']); ?></span>
                    </div>
                </div>

                <!-- Course Description -->
                <?php if (!empty($eval['course_description'])): ?>
                    <div class="course-description">
                        <?php echo htmlspecialchars($eval['course_description']); ?>
                    </div>
                <?php endif; ?>

                <!-- Course Footer -->
                <div class="course-footer">
                    <div class="submission-info">
                        <?php if ($eval['is_used']): ?>
                            <strong>Submitted:</strong>
                            <?php echo date('M d, Y h:i A', strtotime($eval['used_at'])); ?>
                        <?php else: ?>
                            <strong>Available since:</strong>
                            <?php echo date('M d, Y', strtotime($eval['created_at'])); ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <?php if ($eval['is_used']): ?>
                            <span class="btn btn-success">
                                ✓ Evaluation Complete
                            </span>
                        <?php else: ?>
                            <?php
                            // POST the token rather than passing it in the URL.
                            // GET parameters appear in web-server access logs, CDN
                            // logs, and browser history — a stolen token would let
                            // an attacker submit an evaluation on behalf of the student.
                            // Submitting via POST keeps the token out of all logs.
                            ?>
                            <form method="POST" action="submit.php" style="display:inline;margin:0">
                                <?php csrf_token_input(); ?>
                                <input type="hidden" name="token"
                                       value="<?php echo htmlspecialchars($eval['token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-primary">
                                    Evaluate Now →
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Summary Footer -->
    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
        <?php if ($pending_count > 0): ?>
            <p style="margin: 0; color: #666; font-size: 14px;">
                You have completed <strong><?php echo $completed_count; ?></strong> out of
                <strong><?php echo $total_count; ?></strong> evaluations
                (<?php echo $total_count > 0 ? round(($completed_count / $total_count) * 100, 1) : 0; ?>% complete)
            </p>
        <?php else: ?>
            <p style="margin: 0; color: #28a745; font-size: 16px; font-weight: 600;">
                🎉 Congratulations! You have completed all <?php echo $completed_count; ?> course evaluations!
            </p>
            <p style="margin: 10px 0 0 0; color: #666; font-size: 14px;">
                Thank you for providing valuable feedback to help improve course quality.
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Note about Anonymity -->
<div style="margin-top: 30px; padding: 20px; background: white; border-radius: 8px; border-left: 4px solid #667eea;">
    <h3 style="margin: 0 0 10px 0; color: #333; font-size: 16px;">🔒 Your Privacy is Protected</h3>
    <ul style="margin: 0; padding-left: 20px; color: #666; font-size: 14px; line-height: 1.8;">
        <li>All evaluations are <strong>completely anonymous</strong></li>
        <li>Your identity is never revealed to lecturers or administrators</li>
        <li>Responses are aggregated with other students' feedback</li>
        <li>Individual responses are only displayed when at least 5 students have submitted evaluations</li>
        <li>Evaluation tokens ensure anonymity while preventing duplicate submissions</li>
        <li>Once submitted, evaluations <strong>cannot be edited or deleted</strong></li>
    </ul>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
