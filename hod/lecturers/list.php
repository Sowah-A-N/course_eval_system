<?php

/**
 * HOD Lecturers List
 *
 * View all lecturers in the HOD's department with their course assignments
 * and evaluation statistics.
 *
 * Features:
 * - List all department lecturers
 * - Show assigned courses
 * - Evaluation statistics per lecturer
 * - Average ratings (with anonymity protection)
 * - Search and filter
 * - Link to assign courses
 *
 * Role Required: ROLE_HOD
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

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
$page_title = 'Department Lecturers';

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query for lecturers in department
$where = ["u.department_id = ?", "u.role_id = ?"];
$params = [$department_id, ROLE_ADVISOR];
$types = 'ii';

if (!empty($search)) {
    $where[] = "(u.f_name LIKE ? OR u.l_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = implode(' AND ', $where);

$query = "
    SELECT
        u.user_id,
        u.f_name,
        u.l_name,
        u.email,
        u.is_active,
        COUNT(DISTINCT cl.course_id) as course_count,
        GROUP_CONCAT(DISTINCT c.course_code ORDER BY c.course_code SEPARATOR ', ') as courses
    FROM user_details u
    LEFT JOIN course_lecturers cl ON u.user_id = cl.lecturer_user_id
    LEFT JOIN courses c ON cl.course_id = c.id
    WHERE $where_clause
    GROUP BY u.user_id
    ORDER BY u.f_name, u.l_name
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$lecturers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $lecturers[] = $row;
}
mysqli_stmt_close($stmt);

// Get evaluation statistics for each lecturer (if >= 5 responses)
foreach ($lecturers as &$lecturer) {
    $query_stats = "
        SELECT
            COUNT(DISTINCT et.token_id) as total_evals,
            AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating
        FROM evaluation_tokens et
        JOIN evaluations e ON et.token = e.token
        JOIN responses r ON e.evaluation_id = r.evaluation_id
        JOIN course_lecturers cl ON et.course_id = cl.course_id
        WHERE cl.lecturer_user_id = ?
        AND et.is_used = 1
    ";

    $stmt_stats = mysqli_prepare($conn, $query_stats);
    mysqli_stmt_bind_param($stmt_stats, "i", $lecturer['user_id']);
    mysqli_stmt_execute($stmt_stats);
    $result_stats = mysqli_stmt_get_result($stmt_stats);
    $stats = mysqli_fetch_assoc($result_stats);

    $lecturer['total_evaluations'] = $stats['total_evals'];
    $lecturer['avg_rating'] = ($stats['total_evals'] >= MIN_RESPONSE_COUNT && $stats['avg_rating']) ?
        round($stats['avg_rating'], 2) : null;

    mysqli_stmt_close($stmt_stats);
}

require_once '../../includes/header.php';
?>

<style>
    .search-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .search-form {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .search-input {
        flex: 1;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }

    .btn {
        padding: 10px 20px;
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

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }

    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmin(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .stat-value {
        font-size: 32px;
        font-weight: bold;
        color: #667eea;
    }

    .stat-label {
        font-size: 14px;
        color: #666;
        margin-top: 5px;
    }

    .lecturers-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .lecturers-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .lecturers-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #333;
        font-size: 13px;
        border-bottom: 2px solid #e0e0e0;
    }

    .lecturers-table td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
    }

    .lecturers-table tr:hover {
        background: #f8f9fa;
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

    .course-list {
        font-size: 13px;
        color: #666;
    }

    .no-courses {
        color: #999;
        font-style: italic;
    }

    .rating-display {
        font-weight: bold;
        color: #667eea;
    }

    .rating-insufficient {
        color: #999;
        font-style: italic;
        font-size: 12px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 8px;
    }
</style>

<div class="page-header">
    <h1>Department Lecturers</h1>
    <p>Manage lecturers and course assignments</p>
</div>

<!-- Summary Statistics -->
<div class="stats-summary">
    <div class="stat-card">
        <div class="stat-value"><?php echo count($lecturers); ?></div>
        <div class="stat-label">Total Lecturers</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">
            <?php
            $active = count(array_filter($lecturers, function ($l) {
                return $l['is_active'] == 1;
            }));
            echo $active;
            ?>
        </div>
        <div class="stat-label">Active Lecturers</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">
            <?php
            $with_courses = count(array_filter($lecturers, function ($l) {
                return $l['course_count'] > 0;
            }));
            echo $with_courses;
            ?>
        </div>
        <div class="stat-label">With Assigned Courses</div>
    </div>
</div>

<!-- Search -->
<div class="search-section">
    <form method="GET" class="search-form">
        <input type="text" name="search" class="search-input" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-primary">Search</button>
        <a href="list.php" class="btn btn-secondary">Reset</a>
        <button type="button" onclick="exportTableToCSV('lecturers-table', 'lecturers.csv')" class="btn btn-success">Export CSV</button>
    </form>
</div>

<!-- Lecturers Table -->
<?php if (empty($lecturers)): ?>
    <div class="empty-state">
        <div style="font-size: 80px; opacity: 0.3;">👨‍🏫</div>
        <h3>No Lecturers Found</h3>
        <p style="color: #666;">No lecturers match your search criteria.</p>
    </div>
<?php else: ?>
    <div class="lecturers-table">
        <table id="lecturers-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Assigned Courses</th>
                    <th>Evaluations</th>
                    <th>Avg Rating</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lecturers as $lecturer): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($lecturer['f_name'] . ' ' . $lecturer['l_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($lecturer['email']); ?></td>
                        <td>
                            <?php if ($lecturer['is_active'] == 1): ?>
                                <span class="status-badge status-active">Active</span>
                            <?php else: ?>
                                <span class="status-badge status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lecturer['course_count'] > 0): ?>
                                <strong><?php echo $lecturer['course_count']; ?></strong> course(s)<br>
                                <span class="course-list"><?php echo htmlspecialchars($lecturer['courses']); ?></span>
                            <?php else: ?>
                                <span class="no-courses">No courses assigned</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $lecturer['total_evaluations']; ?></td>
                        <td>
                            <?php if ($lecturer['avg_rating'] !== null): ?>
                                <span class="rating-display"><?php echo $lecturer['avg_rating']; ?> / 5.0</span>
                            <?php else: ?>
                                <span class="rating-insufficient">
                                    <?php echo $lecturer['total_evaluations'] > 0 ? 'Insufficient data' : 'No data'; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="assign_course.php?lecturer_id=<?php echo $lecturer['user_id']; ?>" class="btn btn-primary btn-sm">
                                Assign Courses
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px; text-align: center; font-size: 14px; color: #666;">
        Showing <?php echo count($lecturers); ?> lecturer(s)
    </div>
<?php endif; ?>

<div style="margin-top: 20px; padding: 20px; background: white; border-radius: 8px; border-left: 4px solid #667eea;">
    <h3 style="margin: 0 0 10px 0; font-size: 16px;">📊 Anonymity Protection</h3>
    <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">
        Average ratings are only displayed when a lecturer has received at least <strong><?php echo MIN_RESPONSE_COUNT; ?> evaluations</strong>.
        This protects student anonymity while providing meaningful feedback to lecturers.
    </p>
</div>

<?php require_once '../../includes/footer.php'; ?>
