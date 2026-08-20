<?php

/**
 * Student Evaluation Submission Form
 *
 * This is the main evaluation form where students submit their course evaluations.
 *
 * Features:
 * - Token validation (ensures valid, unused token for correct student)
 * - Display course information
 * - Load all active evaluation questions grouped by category
 * - Rating scale (1-5) for each question
 * - CSRF protection
 * - Form validation (all questions required)
 * - Database transaction (atomic operation)
 * - Mark token as used after successful submission
 * - Redirect to success page
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

// ── Evaluation target acquisition (POST-Redirect-GET, tokenless) ────────────
//
// available_courses.php POSTs the chosen target here: scope ('course' or
// 'administrative') plus, for a course, its course_id. There are NO tokens —
// eligibility is derived from the student's own enrolment, so a student can only
// reach evaluations they are entitled to. We store the target in the session and
// redirect to a clean URL (PRG), then render.
//
//   Path 1: POST with 'scope'          → first entry from available_courses.php
//   Path 2: GET after the PRG redirect  → normal render
//   Path 3: POST of the answer form     → handled in "Process form submission"

// Path 1: initial POST from available_courses.php (has 'scope', not the answer form).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scope']) && !isset($_POST['question_1'])) {
    if (!validate_csrf_token()) {
        $_SESSION['flash_message'] = 'Invalid security token.';
        $_SESSION['flash_type']    = 'error';
        header("Location: available_courses.php");
        exit();
    }
    $sel_scope  = ($_POST['scope'] === 'administrative') ? 'administrative' : 'course';
    $sel_course = ($sel_scope === 'course') ? (int)($_POST['course_id'] ?? 0) : 0;
    $_SESSION['pending_eval'] = ['scope' => $sel_scope, 'course_id' => $sel_course];
    header("Location: submit.php");
    exit();
}

// Path 2 / Path 3: read the chosen target from the session key set in Path 1.
$pending = $_SESSION['pending_eval'] ?? null;
if (!$pending || !in_array(($pending['scope'] ?? ''), ['course', 'administrative'], true)) {
    $_SESSION['flash_message'] = 'Please choose an evaluation to complete.';
    $_SESSION['flash_type'] = 'error';
    header("Location: available_courses.php");
    exit();
}
$scope     = $pending['scope'];
$course_id = (int)($pending['course_id'] ?? 0);

// Resolve the student's enrolment and the active period.
$stmt_ctx = mysqli_prepare($conn, "SELECT department_id, level_id, class_id FROM user_details WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_ctx, "i", $student_id);
mysqli_stmt_execute($stmt_ctx);
$stu = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ctx));
mysqli_stmt_close($stmt_ctx);

$period = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM view_active_period LIMIT 1"));
if (!$period) {
    $_SESSION['flash_message'] = 'There is no active evaluation period right now.';
    $_SESSION['flash_type'] = 'error';
    header("Location: index.php");
    exit();
}

$dept_id  = (int)($stu['department_id'] ?? 0);
$level_id = (int)($stu['level_id'] ?? 0);
$class_id = (int)($stu['class_id'] ?? 0);
$year_id  = (int)$period['academic_year_id'];
$sem_id   = (int)$period['semester_id'];

// Build the evaluation context. ($token_data is kept as the variable name the
// rest of this page already uses for the header/render.) No token, and no student
// id is ever attached to the answers.
$token_data = [
    'scope'            => $scope,
    'is_used'          => 0,
    'academic_year_id' => $year_id,
    'semester_id'      => $sem_id,
    'semester_name'    => $period['semester_name'],
    'year_label'       => $period['academic_year'],
    'class_id'         => null,
    'department_id'    => null,
];

if ($scope === 'course') {
    // Eligibility: the course must be in the student's own department + level.
    $stmt_c = mysqli_prepare($conn,
        "SELECT c.id AS course_id, c.course_code, c.name AS course_name, l.level_name, d.dep_name
         FROM courses c
         LEFT JOIN level l ON c.level_id = l.t_id
         LEFT JOIN department d ON c.department_id = d.t_id
         WHERE c.id = ? AND c.department_id = ? AND c.level_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_c, "iii", $course_id, $dept_id, $level_id);
    mysqli_stmt_execute($stmt_c);
    $course = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c));
    mysqli_stmt_close($stmt_c);
    if (!$course) {
        unset($_SESSION['pending_eval']);
        $_SESSION['flash_message'] = 'That course is not available for you to evaluate.';
        $_SESSION['flash_type'] = 'error';
        header("Location: available_courses.php");
        exit();
    }
    $token_data['course_id']   = (int)$course['course_id'];
    $token_data['course_code'] = $course['course_code'];
    $token_data['course_name'] = $course['course_name'];
    $token_data['level_name']  = $course['level_name'];
    $token_data['dep_name']    = $course['dep_name'];
    $completion_course_id = (int)$course['course_id'];
} else {
    // Administrative evaluation — one per student per period. It carries the class
    // (→ advisor) and department so the advisor rating and per-department service
    // stats can be aggregated without any link back to the student.
    $token_data['course_id']    = null;
    $token_data['course_code']  = 'INSTITUTIONAL';
    $token_data['course_name']  = 'Administrative & Services Evaluation';
    $token_data['level_name']   = 'All services';
    $token_data['dep_name']     = 'Institution-wide';
    $token_data['class_id']     = $class_id ?: null;
    $token_data['department_id'] = $dept_id ?: null;
    $completion_course_id = 0;
}

// Block re-submission: reject if a completion already exists for this period.
$stmt_done = mysqli_prepare($conn,
    "SELECT 1 FROM evaluation_completions WHERE student_user_id = ? AND course_id = ? AND academic_year_id = ? AND semester_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_done, "iiii", $student_id, $completion_course_id, $year_id, $sem_id);
mysqli_stmt_execute($stmt_done);
$already_done = mysqli_fetch_row(mysqli_stmt_get_result($stmt_done));
mysqli_stmt_close($stmt_done);
if ($already_done) {
    unset($_SESSION['pending_eval']);
    $_SESSION['flash_message'] = 'You have already submitted this evaluation.';
    $_SESSION['flash_type'] = 'warning';
    header("Location: available_courses.php");
    exit();
}

// Get all active evaluation questions grouped by category
$stmt_q = mysqli_prepare($conn,
    "SELECT question_id, question_text, category, display_order
     FROM evaluation_questions
     WHERE is_active = 1 AND scope = ?
     ORDER BY category, display_order");
mysqli_stmt_bind_param($stmt_q, "s", $scope);
mysqli_stmt_execute($stmt_q);
$result_questions = mysqli_stmt_get_result($stmt_q);
$questions = [];
$questions_by_category = [];

while ($row = mysqli_fetch_assoc($result_questions)) {
    $questions[] = $row;
    $category = $row['category'];
    if (!isset($questions_by_category[$category])) {
        $questions_by_category[$category] = [];
    }
    $questions_by_category[$category][] = $row;
}

if (empty($questions)) {
    $_SESSION['flash_message'] = 'No evaluation questions found. Please contact administration.';
    $_SESSION['flash_type'] = 'error';
    header("Location: available_courses.php");
    exit();
}

// Process form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token()) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    // Validate all questions are answered
    $responses = [];
    foreach ($questions as $question) {
        $question_id = $question['question_id'];
        $response_key = 'question_' . $question_id;

        if (!isset($_POST[$response_key]) || empty($_POST[$response_key])) {
            $errors[] = 'Please answer all questions.';
            break;
        }

        $response_value = intval($_POST[$response_key]);

        // Validate rating is between 1 and 5
        if ($response_value < RATING_MIN || $response_value > RATING_MAX) {
            $errors[] = 'Invalid rating value. Please select a rating between 1 and 5.';
            break;
        }

        $responses[$question_id] = $response_value;
    }

    // If no errors, submit evaluation
    if (empty($errors)) {
        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Record completion FIRST — its UNIQUE(student, course_id, year,
            // semester) key is the atomic double-submit guard (course_id = 0 for
            // the administrative evaluation). A duplicate means already submitted.
            $stmt_done = mysqli_prepare($conn,
                "INSERT INTO evaluation_completions (student_user_id, scope, course_id, academic_year_id, semester_id)
                 VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_done) { throw new RuntimeException('Failed to prepare completion insert.'); }
            mysqli_stmt_bind_param($stmt_done, "isiii", $student_id, $scope, $completion_course_id, $year_id, $sem_id);
            if (!mysqli_stmt_execute($stmt_done)) {
                $dup = (mysqli_stmt_errno($stmt_done) === 1062);
                mysqli_stmt_close($stmt_done);
                if ($dup) {
                    mysqli_rollback($conn);
                    unset($_SESSION['pending_eval']);
                    $_SESSION['flash_message'] = 'This evaluation has already been submitted.';
                    $_SESSION['flash_type'] = 'warning';
                    header("Location: available_courses.php");
                    exit();
                }
                throw new RuntimeException('Failed to record completion.');
            }
            mysqli_stmt_close($stmt_done);

            // Insert the ANONYMOUS evaluation container — no token, no student id.
            // course_id is NULL for the administrative evaluation; class_id and
            // department_id are set only there (to attribute the advisor rating and
            // per-department service stats without linking back to the student).
            $eval_course_id = ($scope === 'course') ? $completion_course_id : null;
            $eval_class_id  = $token_data['class_id'];
            $eval_dept_id   = $token_data['department_id'];
            $stmt_eval = mysqli_prepare($conn,
                "INSERT INTO evaluations (scope, course_id, class_id, department_id, academic_year_id, semester_id)
                 VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt_eval) { throw new RuntimeException('Failed to prepare evaluation insert.'); }
            mysqli_stmt_bind_param($stmt_eval, "siiiii", $scope, $eval_course_id, $eval_class_id, $eval_dept_id, $year_id, $sem_id);
            if (!mysqli_stmt_execute($stmt_eval)) { throw new RuntimeException('Failed to insert evaluation record.'); }

            $evaluation_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_eval);

            // Insert all responses
            $query_insert_response = "
                INSERT INTO responses (
                    evaluation_id,
                    question_id,
                    response_value
                ) VALUES (?, ?, ?)
            ";

            $stmt_response = mysqli_prepare($conn, $query_insert_response);
            if (!$stmt_response) { throw new RuntimeException('Failed to prepare response insert.'); }

            foreach ($responses as $question_id => $response_value) {
                mysqli_stmt_bind_param($stmt_response, "iii", $evaluation_id, $question_id, $response_value);
                if (!mysqli_stmt_execute($stmt_response)) { throw new RuntimeException('Failed to insert a response row.'); }
            }

            mysqli_stmt_close($stmt_response);

            // Participation is already recorded by the completion row above; the
            // answers carry no student reference, so there is nothing to "mark used".

            // Commit transaction
            mysqli_commit($conn);

            // Clear the one-time session token now that submission succeeded.
            // This prevents the form being re-displayed if the student presses
            // Back, and removes the token from session storage.
            unset($_SESSION['pending_eval']);

            // Redirect to success page
            $_SESSION['flash_message'] = 'Your evaluation has been submitted successfully!';
            $_SESSION['flash_type'] = 'success';
            $_SESSION['evaluated_course'] = $token_data['course_name'];
            header("Location: success.php");
            exit();
        } catch (\Throwable $e) {
            // Roll back on ANY failure — including prepare()/execute() returning
            // false, which under MYSQLI_REPORT_OFF would otherwise fall through
            // silently and commit a partial submission.
            mysqli_rollback($conn);
            error_log('[CES] Evaluation submit failed: ' . $e->getMessage());
            $errors[] = 'An error occurred while submitting your evaluation. Please try again.';
        }
    }
}

// Set page title
$page_title = 'Evaluate Course';

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'Available Courses' => 'available_courses.php',
    'Submit Evaluation' => ''
];

// Include header
require_once '../../includes/header.php';
?>

<style>
    .evaluation-container {
        max-width: 900px;
        margin: 0 auto;
    }

    .course-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .course-code {
        font-size: 16px;
        opacity: 0.9;
        margin-bottom: 5px;
    }

    .course-name {
        font-size: 28px;
        font-weight: bold;
        margin-bottom: 15px;
    }

    .course-meta {
        display: flex;
        gap: 25px;
        font-size: 14px;
        opacity: 0.95;
        flex-wrap: wrap;
    }

    .course-meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .evaluation-form {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .category-section {
        margin-bottom: 40px;
    }

    .category-header {
        font-size: 20px;
        font-weight: 600;
        color: #333;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 3px solid #667eea;
    }

    .question-item {
        margin-bottom: 30px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }

    .question-number {
        font-size: 14px;
        font-weight: 600;
        color: #667eea;
        margin-bottom: 8px;
    }

    .question-text {
        font-size: 16px;
        color: #333;
        margin-bottom: 15px;
        line-height: 1.5;
    }

    .rating-scale {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .rating-option {
        position: relative;
    }

    .rating-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }

    .rating-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 15px 20px;
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        min-width: 80px;
    }

    .rating-value {
        font-size: 24px;
        font-weight: bold;
        color: #667eea;
        margin-bottom: 5px;
    }

    .rating-text {
        font-size: 11px;
        color: #666;
        text-align: center;
    }

    .rating-option input[type="radio"]:checked+.rating-label {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        color: white;
    }

    .rating-option input[type="radio"]:checked+.rating-label .rating-value,
    .rating-option input[type="radio"]:checked+.rating-label .rating-text {
        color: white;
    }

    .rating-label:hover {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.2);
    }

    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 40px;
        padding-top: 30px;
        border-top: 2px solid #e0e0e0;
    }

    .btn {
        padding: 15px 40px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
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
        box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
    }

    .alert-error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .alert-info {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        line-height: 1.6;
    }

    .progress-indicator {
        background: white;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 10px;
        z-index: 100;
    }

    .progress-text {
        font-size: 14px;
        color: #666;
    }

    .progress-bar-container {
        flex: 1;
        height: 8px;
        background: #e0e0e0;
        border-radius: 4px;
        margin: 0 20px;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        width: 0%;
        transition: width 0.3s;
    }

    @media (max-width: 768px) {
        .rating-scale {
            flex-direction: column;
        }

        .rating-label {
            width: 100%;
            flex-direction: row;
            justify-content: space-between;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn {
            width: 100%;
        }
    }
</style>

<div class="evaluation-container">
    <!-- Course Header -->
    <div class="course-header">
        <div class="course-code"><?php echo htmlspecialchars($token_data['course_code']); ?></div>
        <div class="course-name"><?php echo htmlspecialchars($token_data['course_name']); ?></div>
        <div class="course-meta">
            <div class="course-meta-item">
                <span>📚</span>
                <span><?php echo htmlspecialchars($token_data['level_name']); ?></span>
            </div>
            <div class="course-meta-item">
                <span>📅</span>
                <span><?php echo htmlspecialchars($token_data['semester_name']); ?></span>
            </div>
            <div class="course-meta-item">
                <span>📖</span>
                <span><?php echo htmlspecialchars($token_data['year_label']); ?></span>
            </div>
            <div class="course-meta-item">
                <span>🏢</span>
                <span><?php echo htmlspecialchars($token_data['dep_name']); ?></span>
            </div>
        </div>
    </div>

    <!-- Instructions -->
    <div class="alert-info">
        <strong>📝 Instructions:</strong><br>
        Please rate each aspect of this course on a scale of 1 to 5, where:<br>
        <strong>1</strong> = Very Poor | <strong>2</strong> = Poor | <strong>3</strong> = Fair | <strong>4</strong> = Good | <strong>5</strong> = Excellent
        <br><br>
        Your responses are <strong>completely anonymous</strong> and will help improve the quality of this course.
        Please answer all questions honestly and thoughtfully. Once submitted, your evaluation cannot be edited.
    </div>

    <!-- Display Errors -->
    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <strong>⚠️ Please correct the following errors:</strong>
            <ul style="margin: 10px 0 0 20px; padding: 0;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Progress Indicator -->
    <div class="progress-indicator" id="progress-indicator">
        <span class="progress-text">Progress: <strong id="answered-count">0</strong> / <strong id="total-count"><?php echo count($questions); ?></strong></span>
        <div class="progress-bar-container">
            <div class="progress-bar" id="progress-bar"></div>
        </div>
        <span class="progress-text"><strong id="progress-percent">0%</strong></span>
    </div>

    <!-- Evaluation Form -->
    <form method="POST" action="" class="evaluation-form" id="evaluation-form">
        <?php csrf_token_input(); ?>

        <?php
        $question_counter = 1;
        foreach ($questions_by_category as $category => $category_questions):
        ?>
            <div class="category-section">
                <div class="category-header"><?php echo htmlspecialchars($category); ?></div>

                <?php foreach ($category_questions as $question): ?>
                    <div class="question-item">
                        <div class="question-number">Question <?php echo $question_counter; ?></div>
                        <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>

                        <div class="rating-scale">
                            <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                                <div class="rating-option">
                                    <input
                                        type="radio"
                                        name="question_<?php echo $question['question_id']; ?>"
                                        id="q<?php echo $question['question_id']; ?>_r<?php echo $rating; ?>"
                                        value="<?php echo $rating; ?>"
                                        class="rating-input"
                                        required>
                                    <label
                                        for="q<?php echo $question['question_id']; ?>_r<?php echo $rating; ?>"
                                        class="rating-label">
                                        <span class="rating-value"><?php echo $rating; ?></span>
                                        <span class="rating-text"><?php echo RATING_LABELS[$rating]; ?></span>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php $question_counter++; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="submit-btn">
                ✓ Submit Evaluation
            </button>
            <a href="available_courses.php" class="btn btn-secondary" onclick="return confirm('Are you sure you want to cancel? Your responses will not be saved.')">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
    // Progress tracking
    const totalQuestions = <?php echo count($questions); ?>;
    const form = document.getElementById('evaluation-form');
    const progressBar = document.getElementById('progress-bar');
    const progressPercent = document.getElementById('progress-percent');
    const answeredCount = document.getElementById('answered-count');
    const submitBtn = document.getElementById('submit-btn');

    function updateProgress() {
        const answeredQuestions = form.querySelectorAll('input[type="radio"]:checked').length;
        const percentage = Math.round((answeredQuestions / totalQuestions) * 100);

        progressBar.style.width = percentage + '%';
        progressPercent.textContent = percentage + '%';
        answeredCount.textContent = answeredQuestions;

        // Enable submit button only when all questions answered
        if (answeredQuestions === totalQuestions) {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
        } else {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.6';
        }
    }

    // Add event listeners to all radio buttons
    const radioButtons = form.querySelectorAll('input[type="radio"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', updateProgress);
    });

    // Initialize progress
    updateProgress();

    // ── Submission state flag ────────────────────────────────────────────
    // Tracks whether the student has confirmed and submitted the form in
    // this page-load.  Used by TWO separate fixes:
    //
    //   Fix #29 — beforeunload false positive:
    //     Without this flag, clicking "Submit" fires the beforeunload
    //     warning ("unsaved responses") even during a normal submission
    //     because the browser briefly unloads the page before the server
    //     redirect arrives.  Guarding on `submitted` suppresses the dialog
    //     for a genuine submit while preserving it for a real navigation away.
    //
    //   Fix #30 — re-enable on back-navigation:
    //     If the student hits the browser Back button after submitting, the
    //     page reloads from cache with the form fully enabled again.  The
    //     server will correctly reject a second submission (token already
    //     used), but the student doesn't know that until after they re-fill
    //     the entire form — a frustrating experience.  The PHP flag below
    //     locks the form immediately on page load when the token is already
    //     spent, preventing any re-entry attempt.
    let submitted = false;

    // Form submission confirmation
    form.addEventListener('submit', function(e) {
        const answeredQuestions = form.querySelectorAll('input[type="radio"]:checked').length;

        if (answeredQuestions < totalQuestions) {
            e.preventDefault();
            alert('Please answer all ' + totalQuestions + ' questions before submitting.');
            return false;
        }

        if (!confirm('Are you sure you want to submit this evaluation? Once submitted, it cannot be edited or deleted.')) {
            e.preventDefault();
            return false;
        }

        // Mark as submitted BEFORE changing the button text so that the
        // beforeunload handler sees the flag immediately if the browser
        // fires it synchronously during the same event loop tick.
        submitted = true;

        // Show loading state
        submitBtn.textContent = 'Submitting...';
        submitBtn.disabled = true;
    });

    // Warn before leaving page if form has answers — but NOT during a
    // legitimate submission (see `submitted` flag explanation above).
    window.addEventListener('beforeunload', function(e) {
        if (submitted) return;   // Normal submit in progress — no warning

        const answeredQuestions = form.querySelectorAll('input[type="radio"]:checked').length;

        if (answeredQuestions > 0 && answeredQuestions < totalQuestions) {
            e.preventDefault();
            e.returnValue = 'You have unsaved responses. Are you sure you want to leave?';
            return e.returnValue;
        }
    });

    // Fix #30 — Lock the form on page load if this token is already used.
    // This covers the browser-Back scenario: the page is served from cache
    // with JS state reset, so the `submitted` flag above is no longer set.
    // PHP passes the current is_used value into JS so we can act immediately
    // without an extra round-trip.
    const tokenAlreadyUsed = <?php echo $token_data['is_used'] ? 'true' : 'false'; ?>;
    if (tokenAlreadyUsed) {
        submitBtn.disabled = true;
        submitBtn.textContent = '✓ Already Submitted';
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
        // Disable all radio inputs so the form looks and feels locked
        form.querySelectorAll('input[type="radio"]').forEach(function(r) {
            r.disabled = true;
        });
    }
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
