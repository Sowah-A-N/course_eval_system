<?php
/**
 * Secretary — Seminar / short-course attendee roster (own department only).
 * When a course has attendees here, ONLY those students may evaluate it.
 */
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';
start_secure_session();
check_login();
if ($_SESSION['role_id'] !== ROLE_SECRETARY) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}
$department_id = (int) $_SESSION['department_id'];

$course_id = intval($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT id, course_code, name, course_type FROM courses WHERE id=? AND department_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $course_id, $department_id);
mysqli_stmt_execute($stmt);
$course = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$course) {
    $_SESSION['flash_message'] = 'Course not found in your department.';
    $_SESSION['flash_type'] = 'error';
    header("Location: list.php");
    exit();
}

$notice = '';
$notice_type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $notice = 'Invalid security token.';
        $notice_type = 'error';
    } elseif (($_POST['action'] ?? '') === 'add') {
        $q = trim($_POST['student_query'] ?? '');
        if ($q === '') {
            $notice = 'Enter a student email or ID.';
            $notice_type = 'error';
        } else {
            $stmt = mysqli_prepare($conn, "SELECT user_id, f_name, l_name FROM user_details WHERE role_id = ? AND is_active = 1 AND (email = ? OR unique_id = ?) LIMIT 1");
            $role = ROLE_STUDENT;
            mysqli_stmt_bind_param($stmt, "iss", $role, $q, $q);
            mysqli_stmt_execute($stmt);
            $stu = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!$stu) {
                $notice = 'No active student found for "' . htmlspecialchars($q) . '".';
                $notice_type = 'error';
            } else {
                $ins = mysqli_prepare($conn, "INSERT IGNORE INTO seminar_attendees (course_id, student_user_id) VALUES (?, ?)");
                mysqli_stmt_bind_param($ins, "ii", $course_id, $stu['user_id']);
                mysqli_stmt_execute($ins);
                $added = mysqli_stmt_affected_rows($ins) > 0;
                mysqli_stmt_close($ins);
                $notice = trim($stu['f_name'] . ' ' . $stu['l_name']) . ($added ? ' added to the roster.' : ' is already on the roster.');
                $notice_type = 'success';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'remove') {
        $sid = intval($_POST['student_user_id'] ?? 0);
        $del = mysqli_prepare($conn, "DELETE FROM seminar_attendees WHERE course_id = ? AND student_user_id = ?");
        mysqli_stmt_bind_param($del, "ii", $course_id, $sid);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
        $notice = 'Student removed from the roster.';
        $notice_type = 'success';
    }
    $_SESSION['roster_notice'] = $notice;
    $_SESSION['roster_notice_type'] = $notice_type;
    header("Location: attendees.php?course_id=" . $course_id);
    exit();
}

if (isset($_SESSION['roster_notice'])) {
    $notice = $_SESSION['roster_notice'];
    $notice_type = $_SESSION['roster_notice_type'] ?? 'info';
    unset($_SESSION['roster_notice'], $_SESSION['roster_notice_type']);
}

$roster = [];
$stmt = mysqli_prepare($conn, "SELECT u.user_id, u.f_name, u.l_name, u.email, u.unique_id
    FROM seminar_attendees sa JOIN user_details u ON u.user_id = sa.student_user_id
    WHERE sa.course_id = ? ORDER BY u.l_name, u.f_name");
mysqli_stmt_bind_param($stmt, "i", $course_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $roster[] = $row;
mysqli_stmt_close($stmt);

$page_title = 'Seminar Attendees';
require_once '../../includes/header.php';
?>
<style>
.card{max-width:900px;margin:0 auto 22px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:26px}
.btn{padding:9px 18px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
.btn-secondary{background:#6c757d;color:#fff}
.btn-danger{background:#dc3545;color:#fff;padding:5px 12px;font-size:13px}
.form-input{padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
table{width:100%;border-collapse:collapse;font-size:14px}
th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0}
td{padding:11px 12px;border-bottom:1px solid #f0f0f0}
.alert{padding:12px 16px;border-radius:7px;margin-bottom:16px;font-size:14px}
.alert-success{background:#f0fdf4;border:1px solid #86efac;color:#15803d}
.alert-error{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c}
.alert-info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460}
.muted{color:#888;font-size:13px}
</style>

<div class="page-header">
    <h1>Seminar Attendees</h1>
    <p><?php echo htmlspecialchars($course['course_code'] . ' — ' . $course['name']); ?></p>
</div>

<div class="card">
    <?php if ($notice): ?>
        <div class="alert alert-<?php echo htmlspecialchars($notice_type); ?>"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>

    <?php if ($course['course_type'] !== 'short'): ?>
        <div class="alert alert-info">This is a regular course. An attendee roster only affects short courses / seminars.</div>
    <?php endif; ?>

    <p class="muted">When this list has at least one student, <strong>only</strong> those students may evaluate this seminar (the audience setting is ignored). Leave it empty to use the audience setting instead.</p>

    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:16px 0 24px">
        <?php csrf_token_input(); ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="course_id" value="<?php echo (int)$course_id; ?>">
        <input type="text" name="student_query" class="form-input" style="flex:1;min-width:240px" placeholder="Student email or ID (e.g. RMU…)" required>
        <button type="submit" class="btn btn-primary">Add Student</button>
        <a href="edit.php?id=<?php echo (int)$course_id; ?>" class="btn btn-secondary">Back to Course</a>
    </form>

    <?php if (empty($roster)): ?>
        <p class="muted">No attendees yet. This seminar currently uses its audience setting.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Student ID</th><th>Name</th><th>Email</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($roster as $r): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($r['unique_id'] ?? ''); ?></strong></td>
                    <td><?php echo htmlspecialchars(trim($r['f_name'] . ' ' . $r['l_name'])); ?></td>
                    <td><?php echo htmlspecialchars($r['email']); ?></td>
                    <td style="text-align:right">
                        <form method="POST" style="display:inline" onsubmit="return confirm('Remove this student from the roster?');">
                            <?php csrf_token_input(); ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="course_id" value="<?php echo (int)$course_id; ?>">
                            <input type="hidden" name="student_user_id" value="<?php echo (int)$r['user_id']; ?>">
                            <button type="submit" class="btn btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted" style="margin-top:12px"><?php echo count($roster); ?> attendee(s).</p>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
