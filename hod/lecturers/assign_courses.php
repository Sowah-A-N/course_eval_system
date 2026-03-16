<?php

/**
 * HOD Assign Course to Lecturer
 *
 * Assign or reassign courses to lecturers within the department.
 *
 * Features:
 * - Assign lecturer to course(s)
 * - Remove lecturer from course(s)
 * - View current assignments
 * - Support multiple lecturers per course
 * - Academic year and semester specific
 *
 * Role Required: ROLE_HOD
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] != ROLE_HOD) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$hod_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'];
$page_title = 'Assign Course';

// Get parameters
$lecturer_id = isset($_GET['lecturer_id']) ? intval($_GET['lecturer_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

$errors = [];
$success = false;

// Get active academic period
$query_period = "SELECT * FROM view_active_period LIMIT 1";
$result_period = mysqli_query($conn, $query_period);
$active_period = mysqli_fetch_assoc($result_period);

if (!$active_period) {
    $_SESSION['flash_message'] = 'No active academic period. Please contact administration.';
    $_SESSION['flash_type'] = 'error';
    header("Location: list.php");
    exit();
}

// Get all department lecturers
$query_lecturers = "
    SELECT user_id, f_name, l_name, email
    FROM user_details
    WHERE department_id = ?
    AND role_id = ?
    AND is_active = 1
    ORDER BY f_name, l_name
";
$stmt_lec = mysqli_prepare($conn, $query_lecturers);
mysqli_stmt_bind_param($stmt_lec, "ii", $department_id, $role_lec);
$role_lec = ROLE_ADVISOR;
mysqli_stmt_execute($stmt_lec);
$result_lec = mysqli_stmt_get_result($stmt_lec);
$lecturers = [];
while ($row = mysqli_fetch_assoc($result_lec)) {
    $lecturers[] = $row;
}
mysqli_stmt_close($stmt_lec);

// Get all department courses
$query_courses = "
    SELECT c.id, c.course_code, c.name, l.level_name, s.semester_name
    FROM courses c
    LEFT JOIN level l ON c.level_id = l.t_id
    LEFT JOIN semesters s ON c.semester_id = s.semester_id
    WHERE c.department_id = ?
    ORDER BY c.course_code
";
$stmt_courses = mysqli_prepare($conn, $query_courses);
mysqli_stmt_bind_param($stmt_courses, "i", $department_id);
mysqli_stmt_execute($stmt_courses);
$result_courses = mysqli_stmt_get_result($stmt_courses);
$courses = [];
while ($row = mysqli_fetch_assoc($result_courses)) {
    $courses[] = $row;
}
mysqli_stmt_close($stmt_courses);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_token()) {
        $errors[] = 'Invalid security token.';
    }

    $selected_lecturer = isset($_POST['lecturer_id']) ? intval($_POST['lecturer_id']) : 0;
    $selected_courses = isset($_POST['courses']) ? $_POST['courses'] : [];

    if ($selected_lecturer == 0) {
        $errors[] = 'Please select a lecturer.';
    }

    if (empty($selected_courses)) {
        $errors[] = 'Please select at least one course.';
    }

    if (empty($errors)) {
        // Verify lecturer belongs to department
        $query_verify = "SELECT user_id FROM user_details WHERE user_id = ? AND department_id = ?";
        $stmt_verify = mysqli_prepare($conn, $query_verify);
        mysqli_stmt_bind_param($stmt_verify, "ii", $selected_lecturer, $department_id);
        mysqli_stmt_execute($stmt_verify);
        if (mysqli_stmt_get_result($stmt_verify)->num_rows == 0) {
            $errors[] = 'Invalid lecturer selection.';
        }
        mysqli_stmt_close($stmt_verify);

        if (empty($errors)) {
            $success_count = 0;

            foreach ($selected_courses as $cid) {
                $cid = intval($cid);

                // Check if assignment already exists
                $query_check = "
                    SELECT id FROM course_lecturers
                    WHERE course_id = ?
                    AND lecturer_user_id = ?
                    AND academic_year_id = ?
                    AND semester_id = ?
                ";
                $stmt_check = mysqli_prepare($conn, $query_check);
                mysqli_stmt_bind_param(
                    $stmt_check,
                    "iiii",
                    $cid,
                    $selected_lecturer,
                    $active_period['academic_year_id'],
                    $active_period['semester_id']
                );
                mysqli_stmt_execute($stmt_check);
                $exists = mysqli_stmt_get_result($stmt_check)->num_rows > 0;
                mysqli_stmt_close($stmt_check);

                if (!$exists) {
                    // Insert new assignment
                    $query_insert = "
                        INSERT INTO course_lecturers
                        (course_id, lecturer_user_id, academic_year_id, semester_id, assigned_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ";
                    $stmt_insert = mysqli_prepare($conn, $query_insert);
                    mysqli_stmt_bind_param(
                        $stmt_insert,
                        "iiii",
                        $cid,
                        $selected_lecturer,
                        $active_period['academic_year_id'],
                        $active_period['semester_id']
                    );

                    if (mysqli_stmt_execute($stmt_insert)) {
                        $success_count++;
                    }
                    mysqli_stmt_close($stmt_insert);
                }
            }

            if ($success_count > 0) {
                $success = true;
                $_SESSION['flash_message'] = "Successfully assigned $success_count course(s) to lecturer.";
                $_SESSION['flash_type'] = 'success';
            } else {
                $errors[] = 'No new assignments were made. Lecturer may already be assigned to selected courses.';
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<style>
    .form-container {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: #333;
        margin-bottom: 8px;
    }

    .form-label.required::after {
        content: ' *';
        color: #dc3545;
    }

    .form-select {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 14px;
    }

    .form-select:focus {
        outline: none;
        border-color: #667eea;
    }

    .course-checkbox-list {
        max-height: 400px;
        overflow-y: auto;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        padding: 15px;
    }

    .course-checkbox-item {
        padding: 10px;
        margin-bottom: 10px;
        background: #f8f9fa;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .course-checkbox-item:hover {
        background: #e9ecef;
    }

    .course-checkbox-item input[type="checkbox"] {
        margin-right: 10px;
        cursor: pointer;
    }

    .course-checkbox-item label {
        cursor: pointer;
        display: flex;
        align-items: center;
    }

    .course-info {
        flex: 1;
    }

    .course-code {
        font-weight: 600;
        color: #333;
    }

    .course-meta {
        font-size: 12px;
        color: #666;
        margin-top: 3px;
    }

    .btn {
        padding: 12px 30px;
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

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .alert-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    .alert-error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }

    .select-all-btn {
        padding: 8px 15px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 13px;
        cursor: pointer;
        margin-bottom: 10px;
    }
</style>

<div class="page-header">
    <h1>Assign Course to Lecturer</h1>
    <p>Academic Year: <?php echo htmlspecialchars($active_period['academic_year']); ?> - <?php echo htmlspecialchars($active_period['semester_name']); ?></p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <strong>✓ Success!</strong> Course(s) assigned successfully.
        <div style="margin-top: 10px;">
            <a href="list.php">Back to Lecturers</a> |
            <a href="assign_course.php">Assign More Courses</a>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>⚠️ Please correct the following errors:</strong>
        <ul style="margin: 10px 0 0 20px; padding: 0;">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" action="">
        <?php csrf_token_input(); ?>

        <!-- Select Lecturer -->
        <div class="form-group">
            <label for="lecturer_id" class="form-label required">Select Lecturer</label>
            <select name="lecturer_id" id="lecturer_id" class="form-select" required>
                <option value="0">-- Select Lecturer --</option>
                <?php foreach ($lecturers as $lec): ?>
                    <option value="<?php echo $lec['user_id']; ?>"
                        <?php echo ($lecturer_id == $lec['user_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lec['f_name'] . ' ' . $lec['l_name'] . ' (' . $lec['email'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Select Courses -->
        <div class="form-group">
            <label class="form-label required">Select Courses</label>
            <button type="button" class="select-all-btn" onclick="toggleAll()">Select All / Deselect All</button>
            <div class="course-checkbox-list">
                <?php foreach ($courses as $course): ?>
                    <div class="course-checkbox-item">
                        <label>
                            <input type="checkbox"
                                name="courses[]"
                                value="<?php echo $course['id']; ?>"
                                <?php echo ($course_id == $course['id']) ? 'checked' : ''; ?>>
                            <div class="course-info">
                                <div class="course-code">
                                    <?php echo htmlspecialchars($course['course_code']); ?> -
                                    <?php echo htmlspecialchars($course['name']); ?>
                                </div>
                                <div class="course-meta">
                                    <?php echo htmlspecialchars($course['level_name']); ?> •
                                    <?php echo htmlspecialchars($course['semester_name']); ?>
                                </div>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Assign Courses</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
    function toggleAll() {
        const checkboxes = document.querySelectorAll('input[name="courses[]"]');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    }
</script>

<?php require_once '../../includes/footer.php'; ?>
