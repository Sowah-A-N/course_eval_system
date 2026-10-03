<?php
/**
 * HOD — Department evaluation questions (feature 2.3).
 * Add questions that appear in every course evaluation for this department,
 * on top of the standard set. Scope is always 'course', department-level.
 */
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
require_once '../../includes/audit.php';
start_secure_session();
check_login();
if ($_SESSION['role_id'] !== ROLE_HOD) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}
$hod_id = (int) $_SESSION['user_id'];
$department_id = (int) $_SESSION['department_id'];
$page_title = 'Department Questions';

$notice = '';
$notice_type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $notice = 'Invalid security token.';
        $notice_type = 'error';
    } elseif (($_POST['action'] ?? '') === 'add') {
        $text = trim($_POST['question_text'] ?? '');
        if ($text === '') {
            $notice = 'Question text is required.';
            $notice_type = 'error';
        } else {
            $ord = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COALESCE(MAX(display_order),999)+1 AS n FROM evaluation_questions WHERE department_id=" . $department_id . " AND course_id IS NULL"))['n'];
            $cat = 'Department Questions';
            $scope = 'course';
            $stmt = mysqli_prepare($conn,
                "INSERT INTO evaluation_questions (question_text,category,scope,display_order,is_active,department_id,course_id,created_by)
                 VALUES (?,?,?,?,1,?,NULL,?)");
            mysqli_stmt_bind_param($stmt, "sssiii", $text, $cat, $scope, $ord, $department_id, $hod_id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $notice = $ok ? 'Question added.' : 'Could not add the question.';
            $notice_type = $ok ? 'success' : 'error';
            if ($ok) {
                log_audit($conn, $hod_id, 'QUESTION_CREATE', 'evaluation_questions', mysqli_insert_id($conn), null, ['department_id' => $department_id, 'text' => $text]);
            }
        }
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        $qid = (int) ($_POST['question_id'] ?? 0);
        // Only this department's own questions can be toggled.
        $stmt = mysqli_prepare($conn, "UPDATE evaluation_questions SET is_active = 1 - is_active WHERE question_id=? AND department_id=? AND course_id IS NULL");
        mysqli_stmt_bind_param($stmt, "ii", $qid, $department_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $notice = 'Question updated.';
        $notice_type = 'success';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $qid = (int) ($_POST['question_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM evaluation_questions WHERE question_id=? AND department_id=? AND course_id IS NULL");
        mysqli_stmt_bind_param($stmt, "ii", $qid, $department_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $notice = 'Question deleted.';
        $notice_type = 'success';
    }
    $_SESSION['q_notice'] = $notice;
    $_SESSION['q_notice_type'] = $notice_type;
    header("Location: index.php");
    exit();
}
if (isset($_SESSION['q_notice'])) {
    $notice = $_SESSION['q_notice'];
    $notice_type = $_SESSION['q_notice_type'] ?? 'info';
    unset($_SESSION['q_notice'], $_SESSION['q_notice_type']);
}

$questions = [];
$stmt = mysqli_prepare($conn, "SELECT question_id, question_text, display_order, is_active FROM evaluation_questions WHERE department_id=? AND course_id IS NULL ORDER BY display_order, question_id");
mysqli_stmt_bind_param($stmt, "i", $department_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $questions[] = $row;
mysqli_stmt_close($stmt);

require_once '../../includes/header.php';
?>
<style>
.card{max-width:900px;margin:0 auto 22px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:26px}
.btn{padding:9px 18px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
.btn-sm{padding:5px 12px;font-size:13px}
.btn-danger{background:#dc3545;color:#fff}
.btn-secondary{background:#6c757d;color:#fff}
.form-textarea{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px;min-height:80px;font-family:inherit;resize:vertical}
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
    <h1>Department Questions</h1>
    <p>Extra evaluation questions shown for every course in your department.</p>
</div>
<div class="card">
    <?php if ($notice): ?><div class="alert alert-<?php echo htmlspecialchars($notice_type); ?>"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <p class="muted">These are added to the standard questions on every course evaluation in your department.</p>
    <form method="POST" style="margin:16px 0 24px">
        <?php csrf_token_input(); ?>
        <input type="hidden" name="action" value="add">
        <textarea name="question_text" class="form-textarea" required placeholder="e.g., The course materials were relevant to our department's field."></textarea>
        <div style="margin-top:10px"><button type="submit" class="btn btn-primary">Add Question</button></div>
    </form>
    <?php if (empty($questions)): ?>
        <p class="muted">No department questions yet.</p>
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
                            <input type="hidden" name="question_id" value="<?php echo (int)$q['question_id']; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm"><?php echo $q['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this question?');">
                            <?php csrf_token_input(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="question_id" value="<?php echo (int)$q['question_id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once '../../includes/footer.php'; ?>
