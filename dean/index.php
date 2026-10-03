<?php
/** Dean dashboard — overview of the dean's faculty (its departments). */
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';
start_secure_session();
check_login();
if ($_SESSION['role_id'] !== ROLE_DEAN) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../login.php");
    exit();
}
$dean_id = (int) $_SESSION['user_id'];
$page_title = 'Dean Dashboard';

$faculty = null;
$stmt = mysqli_prepare($conn, "SELECT t_id, faculty_name, faculty_code FROM faculties WHERE dean_user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $dean_id);
mysqli_stmt_execute($stmt);
$faculty = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$departments = [];
if ($faculty) {
    $stmt = mysqli_prepare($conn, "SELECT d.t_id, d.dep_name,
            (SELECT COUNT(*) FROM courses c WHERE c.department_id = d.t_id) AS courses,
            (SELECT COUNT(DISTINCT e.evaluation_id) FROM evaluations e JOIN courses c ON e.course_id = c.id WHERE c.department_id = d.t_id AND e.scope = 'course') AS evals,
            (SELECT ROUND(AVG(CAST(r.response_value AS DECIMAL(10,2))),2) FROM responses r JOIN evaluations e ON r.evaluation_id = e.evaluation_id JOIN courses c ON e.course_id = c.id WHERE c.department_id = d.t_id AND e.scope = 'course') AS avg_rating
        FROM department d WHERE d.faculty_id = ? ORDER BY d.dep_name");
    mysqli_stmt_bind_param($stmt, "i", $faculty['t_id']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $departments[] = $row;
    mysqli_stmt_close($stmt);
}

require_once '../includes/header.php';
?>
<style>
.card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:24px;margin-bottom:22px}
table{width:100%;border-collapse:collapse;font-size:14px}
th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0}
td{padding:11px 12px;border-bottom:1px solid #f0f0f0}
.muted{color:#888;font-size:13px}
.pill{padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600}
.pill.ok{background:#d4edda;color:#155724}
.pill.low{background:#f8d7da;color:#721c24}
</style>
<div class="page-header">
    <h1>Dean Dashboard</h1>
    <p><?php echo $faculty ? htmlspecialchars($faculty['faculty_name'] . ' (' . $faculty['faculty_code'] . ')') : 'No faculty assigned'; ?></p>
</div>
<?php if (!$faculty): ?>
    <div class="card"><p class="muted">You are not yet assigned to a faculty. Ask an administrator to set you as the Dean of a faculty.</p></div>
<?php elseif (empty($departments)): ?>
    <div class="card"><p class="muted">No departments are assigned to this faculty yet.</p></div>
<?php else: ?>
    <div class="card">
        <h2 style="margin-top:0;font-size:18px">Departments in <?php echo htmlspecialchars($faculty['faculty_name']); ?></h2>
        <table>
            <thead><tr><th>Department</th><th>Courses</th><th>Evaluations</th><th>Avg Rating</th></tr></thead>
            <tbody>
            <?php foreach ($departments as $d): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($d['dep_name']); ?></strong></td>
                    <td><?php echo (int)$d['courses']; ?></td>
                    <td><?php echo (int)$d['evals']; ?></td>
                    <td>
                        <?php if ($d['avg_rating'] !== null): ?>
                            <span class="pill <?php echo ($d['avg_rating'] >= 3.0) ? 'ok' : 'low'; ?>"><?php echo $d['avg_rating']; ?> / 5</span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted" style="margin-top:12px">Ratings are shown as a faculty overview. Detailed, anonymity-protected breakdowns are in the Quality reports.</p>
    </div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
