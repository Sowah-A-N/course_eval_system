<?php
/**
 * Lecturer — Course evaluation questions (feature 2.3).
 * Add questions that appear only on the evaluation for one of the lecturer's
 * own assigned courses, on top of the standard + department questions.
 */
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if ($_SESSION['role_id'] !== ROLE_ADVISOR) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}
$lecturer_id = (int) $_SESSION['user_id'];
$page_title = 'My Course Questions';

// The lecturer's assigned courses — the only ones they may add questions to.
$my_courses = [];
$stmt = mysqli_prepare($conn,
    "SELECT DISTINCT c.id, c.course_code, c.name
     FROM course_lecturers cl JOIN courses c ON c.id = cl.course_id
     WHERE cl.lecturer_user_id = ? AND cl.is_active = 1
     ORDER BY c.course_code");
mysqli_stmt_bind_param($stmt, "i", $lecturer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $my_courses[(int)$row['id']] = $row;
mysqli_stmt_close($stmt);

$course_id = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$owns = isset($my_courses[$course_id]);

$notice = '';
$notice_type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $notice = 'Invalid security token.';
        $notice_type = 'error';
    } elseif (!$owns) {
        $notice = 'You can only manage questions for your own assigned courses.';
        $notice_type = 'error';
    } elseif (($_POST['action'] ?? '') === 'add') {
        $text = trim($_POST['question_text'] ?? '');
        if ($text === '') {
            $notice = 'Question text is required.';
            $notice_type = 'error';
        } else {
            $ord = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COALESCE(MAX(display_order),1999)+1 AS n FROM evaluation_questions WHERE course_id=" . $course_id))['n'];
            $cat = 'Course Questions';
            $scope = 'course';
            $stmt = mysqli_prepare($conn,
                "INSERT INTO evaluation_questions (question_text,category,scope,display_order,is_active,department_id,course_id,created_by)
                 VALUES (?,?,?,?,1,NULL,?,?)");
            mysqli_stmt_bind_param($stmt, "sssiii", $text, $cat, $scope, $ord, $course_id, $lecturer_id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $notice = $ok ? 'Question added.' : 'Could not add the question.';
            $notice_type = $ok ? 'success' : 'error';
            if ($ok) {
                log_audit($conn, $lecturer_id, 'QUESTION_CREATE', 'evaluation_questions', mysqli_insert_id($conn), null, ['course_id' => $course_id, 'text' => $text]);
            }
        }
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        $qid = (int) ($_POST['question_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE evaluation_questions SET is_active = 1 - is_active WHERE question_id=? AND course_id=?");
        mysqli_stmt_bind_param($stmt, "ii", $qid, $course_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $notice = 'Question updated.';
        $notice_type = 'success';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $qid = (int) ($_POST['question_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM evaluation_questions WHERE question_id=? AND course_id=?");
        mysqli_stmt_bind_param($stmt, "ii", $qid, $course_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $notice = 'Question deleted.';
        $notice_type = 'success';
    }
    $_SESSION['q_notice'] = $notice;
    $_SESSION['q_notice_type'] = $notice_type;
    header("Location: index.php?course_id=" . $course_id);
    exit();
}
if (isset($_SESSION['q_notice'])) {
    $notice = $_SESSION['q_notice'];
    $notice_type = $_SESSION['q_notice_type'] ?? 'info';
    unset($_SESSION['q_notice'], $_SESSION['q_notice_type']);
}

$questions = [];
if ($owns) {
    $stmt = mysqli_prepare($conn, "SELECT question_id, question_text, display_order, is_active FROM evaluation_questions WHERE course_id=? ORDER BY display_order, question_id");
    mysqli_stmt_bind_param($stmt, "i", $course_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $questions[] = $row;
    mysqli_stmt_close($stmt);
}

require_once '../../includes/header.php';
?>
<style>
.card{max-width:900px;margin:0 auto 22px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:26px}
.btn{padding:9px 18px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
.btn-sm{padding:5px 12px;font-size:13px}
.btn-danger{background:#dc3545;color:#fff}
.btn-secondary{background:#6c757d;color:#fff}
.form-input,.form-textarea{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
.form-textarea{min-height:80px;font-family:inherit;resize:vertical}
table{width:100%;border-collapse:collapse;font-size:14px}
th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0}
td{padding:11px 12px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.pill{padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600}
.pill.on{background:#d4edda;color:#155724}
.pill.off{background:#f8d7da;color:#721c24}
.alert{padding:12px 16px;border-radius:7px;margin-bottom:16px;font-size:14px}
.alert-success{background:#f0fdf4;border:1px solid #86efac;color:#15803d}
.alert-error{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c}
.alert-info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460}
.muted{color:#888;font-size:13px}
</style>
<div class="page-header">
    <h1>My Course Questions</h1>
    <p>Extra evaluation questions for one of your courses, on top of the standard set.</p>
</div>
<div class="card">
    <?php if ($notice): ?><div class="alert alert-<?php echo htmlspecialchars($notice_type); ?>"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if (empty($my_courses)): ?>
        <p class="muted">You have no assigned courses, so there is nothing to add questions to.</p>
    <?php else: ?>
        <form method="GET" style="margin-bottom:18px">
            <label class="muted">Course:&nbsp;</label>
            <select name="course_id" class="form-input" style="max-width:420px;display:inline-block" onchange="this.form.submit()">
                <option value="0">-- Select one of your courses --</option>
                <?php foreach ($my_courses as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>" <?php echo $course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['course_code'] . ' — ' . $c['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($owns): ?>
            <form method="POST" style="margin:16px 0 24px">
                <?php csrf_token_input(); ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="course_id" value="<?php echo (int)$course_id; ?>">
                <textarea name="question_text" class="form-textarea" required placeholder="e.g., The lab sessions helped me understand the material."></textarea>
                <div style="margin-top:10px"><button type="submit" class="btn btn-primary">Add Question</button></div>
            </form>
            <?php if (empty($questions)): ?>
                <p class="muted">No questions for this course yet.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Question</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($q['question_text']); ?></td>
                            <td><span class="pill <?php echo $q['is_active'] ? 'on' : 'off'; ?>"><?php echo $q['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td style="text-align:right;white-space:nowrap">
                                <form method="POST" style="display:inline">
                                    <?php csrf_token_input(); ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="course_id" value="<?php echo (int)$course_id; ?>">
                                    <input type="hidden" name="question_id" value="<?php echo (int)$q['question_id']; ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm"><?php echo $q['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this question?');">
                                    <?php csrf_token_input(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="course_id" value="<?php echo (int)$course_id; ?>">
                                    <input type="hidden" name="question_id" value="<?php echo (int)$q['question_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php elseif ($course_id > 0): ?>
            <div class="alert alert-error">That course is not one of your assigned courses.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require_once '../../includes/footer.php'; ?>
