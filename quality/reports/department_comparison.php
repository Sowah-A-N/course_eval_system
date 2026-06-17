<?php
/**
 * Department Comparison Report
 *
 * Compare evaluation performance across all departments.
 *
 * Features:
 * - Side-by-side department comparison
 * - Average ratings per department
 * - Completion rates per department
 * - Visual charts
 * - Filter by academic year/semester
 * - Identify best/worst performing departments
 * - Anonymity protection (min 5 responses)
 *
 * Role Required: ROLE_QUALITY or ROLE_ADMIN
 */

// Include required files
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';

// Start session and check login
start_secure_session();
check_login();

// <?php
// TEMPORARY DEBUG - Remove after fixing
// echo "Current Role ID: " . ($_SESSION['role_id'] ?? 'NOT SET') . "<br>";
// echo "ROLE_ADMIN: " . ROLE_ADMIN . "<br>";
// echo "ROLE_QUALITY: " . ROLE_QUALITY . "<br>";
// echo "Check passes: " . (($_SESSION['role_id'] == ROLE_ADMIN || $_SESSION['role_id'] == ROLE_QUALITY) ? 'YES' : 'NO') . "<br>";
// exit(); // Stop here to see the output


// Check role
if (!defined('ROLE_QUALITY')) {
    define('ROLE_QUALITY', 6);
}

if ($_SESSION['role_id'] !== ROLE_ADMIN && $_SESSION['role_id'] !== ROLE_QUALITY) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

// Set page title
$page_title = 'Department Comparison Report';

// Get filter parameters
$filter_academic_year = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$filter_semester = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;

// Get all academic years
$query_years = "SELECT * FROM academic_year ORDER BY start_year DESC";
$result_years = mysqli_query($conn, $query_years);
$academic_years = [];
while ($row = mysqli_fetch_assoc($result_years)) {
    $academic_years[] = $row;
}

// Get all semesters
$query_semesters = "SELECT * FROM semesters ORDER BY semester_value";
$result_semesters = mysqli_query($conn, $query_semesters);
$semesters = [];
while ($row = mysqli_fetch_assoc($result_semesters)) {
    $semesters[] = $row;
}

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$types = '';

if ($filter_academic_year > 0) {
    $where_conditions[] = "et.academic_year_id = ?";
    $params[] = $filter_academic_year;
    $types .= 'i';
}

if ($filter_semester > 0) {
    $where_conditions[] = "et.semester_id = ?";
    $params[] = $filter_semester;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'AND ' . implode(' AND ', $where_conditions) : '';

// Get department comparison data
$query_departments = "
    SELECT
        d.t_id,
        d.dep_name,
        d.dep_code,
        COUNT(DISTINCT et.token_id) as total_tokens,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as completed_tokens,
        COUNT(DISTINCT et.student_user_id) as total_students,
        COUNT(DISTINCT c.id) as total_courses
    FROM department d
    LEFT JOIN courses c ON d.t_id = c.department_id
    LEFT JOIN evaluation_tokens et ON c.id = et.course_id
    WHERE 1=1 $where_clause
    GROUP BY d.t_id
    HAVING completed_tokens >= ?
    ORDER BY completed_tokens DESC
";

$dept_params = $params;
$dept_params[] = MIN_RESPONSE_COUNT;
$dept_types = $types . 'i';

$stmt_departments = mysqli_prepare($conn, $query_departments);
mysqli_stmt_bind_param($stmt_departments, $dept_types, ...$dept_params);
mysqli_stmt_execute($stmt_departments);
$result_departments = mysqli_stmt_get_result($stmt_departments);

$departments = [];
while ($row = mysqli_fetch_assoc($result_departments)) {
    $row['completion_rate'] = $row['total_tokens'] > 0 ?
        round(($row['completed_tokens'] / $row['total_tokens']) * 100, 1) : 0;
    $departments[] = $row;
}
mysqli_stmt_close($stmt_departments);

// Get average ratings per department
foreach ($departments as &$dept) {
    $query_rating = "
        SELECT
            AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating,
            COUNT(r.id) as response_count
        FROM responses r
        JOIN evaluations e ON r.evaluation_id = e.evaluation_id
        JOIN evaluation_tokens et ON e.token = et.token
        JOIN courses c ON et.course_id = c.id
        WHERE c.department_id = ?
        $where_clause
    ";

    $rating_params = [$dept['t_id']];
    $rating_params = array_merge($rating_params, $params);
    $rating_types = 'i' . $types;

    $stmt_rating = mysqli_prepare($conn, $query_rating);
    mysqli_stmt_bind_param($stmt_rating, $rating_types, ...$rating_params);
    mysqli_stmt_execute($stmt_rating);
    $result_rating = mysqli_stmt_get_result($stmt_rating);
    $rating_data = mysqli_fetch_assoc($result_rating);

    $dept['avg_rating'] = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 2) : 0;
    $dept['response_count'] = $rating_data['response_count'];

    mysqli_stmt_close($stmt_rating);
}

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'Reports' => 'index.php',
    'Department Comparison' => ''
];

// Include header
require_once '../../includes/header.php';
?>

<style>
    .filters-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }

    .filter-group label {
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 5px;
        display: block;
        color: #333;
    }

    .filter-group select {
        width: 100%;
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

    .comparison-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .department-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
    }

    .department-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    }

    .department-card.top-performer {
        border: 2px solid #28a745;
    }

    .department-card.needs-attention {
        border: 2px solid #ffc107;
    }

    .rank-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 16px;
    }

    .department-header {
        margin-bottom: 20px;
    }

    .department-name {
        font-size: 20px;
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }

    .department-code {
        font-size: 14px;
        color: #999;
    }

    .rating-display {
        text-align: center;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .rating-value {
        font-size: 48px;
        font-weight: bold;
        color: #667eea;
        line-height: 1;
    }

    .rating-label {
        font-size: 14px;
        color: #666;
        margin-top: 5px;
    }

    .stars {
        font-size: 24px;
        margin-top: 10px;
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-bottom: 15px;
    }

    .stat-item {
        text-align: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #333;
    }

    .stat-label {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }

    .completion-bar {
        background: #e0e0e0;
        height: 10px;
        border-radius: 5px;
        overflow: hidden;
    }

    .completion-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s;
    }

    .comparison-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-top: 30px;
    }

    .comparison-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .comparison-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #333;
        font-size: 13px;
        border-bottom: 2px solid #e0e0e0;
    }

    .comparison-table td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
    }

    .comparison-table tr:hover {
        background: #f8f9fa;
    }

    .alert-info-custom {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    @media (max-width: 768px) {
        .comparison-grid {
            grid-template-columns: 1fr;
        }

        .stats-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1>Department Comparison Report</h1>
    <p>Compare evaluation performance across all departments</p>
</div>

<!-- Filters -->
<div class="filters-section">
    <form method="GET" action="">
        <div class="filters-grid">
            <div class="filter-group">
                <label for="academic_year_id">Academic Year</label>
                <select name="academic_year_id" id="academic_year_id">
                    <option value="0">All Years</option>
                    <?php foreach ($academic_years as $year): ?>
                        <option value="<?php echo $year['academic_year_id']; ?>"
                                <?php echo $filter_academic_year == $year['academic_year_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year['year_label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="semester_id">Semester</label>
                <select name="semester_id" id="semester_id">
                    <option value="0">All Semesters</option>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?php echo $semester['semester_id']; ?>"
                                <?php echo $filter_semester == $semester['semester_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($semester['semester_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Generate Report</button>
            <a href="department_comparison.php" class="btn btn-secondary">Reset</a>
            <?php if (!empty($departments)): ?>
                <button type="button" onclick="window.print()" class="btn btn-secondary">Print Report</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Privacy Notice -->
<div class="alert-info-custom">
    <strong>📊 Anonymity Protection:</strong> Only departments with at least <?php echo MIN_RESPONSE_COUNT; ?> completed evaluations are displayed to protect student anonymity.
</div>

<?php if (empty($departments)): ?>
    <!-- Empty State -->
    <div class="empty-state">
        <div style="font-size: 80px; margin-bottom: 20px; opacity: 0.3;">📊</div>
        <h3>No Department Data Available</h3>
        <p style="color: #666;">
            No departments have sufficient evaluation data to display.<br>
            At least <?php echo MIN_RESPONSE_COUNT; ?> evaluations per department are required.
        </p>
    </div>
<?php else: ?>

    <!-- Department Cards Grid -->
    <div class="comparison-grid">
        <?php
        $rank = 1;
        foreach ($departments as $dept):
            $is_top = $rank <= 3;
            $is_low = $dept['completion_rate'] < 70;
            $card_class = $is_top ? 'top-performer' : ($is_low ? 'needs-attention' : '');
        ?>
            <div class="department-card <?php echo $card_class; ?>">
                <div class="rank-badge">#<?php echo $rank; ?></div>

                <div class="department-header">
                    <div class="department-name"><?php echo htmlspecialchars($dept['dep_name']); ?></div>
                    <div class="department-code"><?php echo htmlspecialchars($dept['dep_code']); ?></div>
                </div>

                <div class="rating-display">
                    <div class="rating-value"><?php echo $dept['avg_rating']; ?></div>
                    <div class="rating-label">Average Rating / 5.0</div>
                    <div class="stars">
                        <?php
                        $full_stars = floor($dept['avg_rating']);
                        for ($i = 0; $i < $full_stars; $i++) echo '⭐';
                        ?>
                    </div>
                </div>

                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $dept['completed_tokens']; ?></div>
                        <div class="stat-label">Submissions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $dept['total_students']; ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $dept['completion_rate']; ?>%</div>
                        <div class="stat-label">Completion</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $dept['total_courses']; ?></div>
                        <div class="stat-label">Courses</div>
                    </div>
                </div>

                <div class="completion-bar">
                    <div class="completion-fill" style="width: <?php echo $dept['completion_rate']; ?>%;"></div>
                </div>
            </div>
        <?php
            $rank++;
        endforeach;
        ?>
    </div>

    <!-- Detailed Comparison Table -->
    <h2 style="font-size: 20px; font-weight: 600; color: #333; margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #667eea;">
        Detailed Comparison Table
    </h2>
    <div class="comparison-table">
        <table>
            <thead>
                <tr>
                    <th scope="col">Rank</th>
                    <th scope="col">Department</th>
                    <th scope="col">Avg Rating</th>
                    <th scope="col">Submissions</th>
                    <th scope="col">Total Tokens</th>
                    <th scope="col">Completion Rate</th>
                    <th scope="col">Students</th>
                    <th scope="col">Courses</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rank = 1;
                foreach ($departments as $dept):
                ?>
                    <tr>
                        <td><strong>#<?php echo $rank; ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($dept['dep_name']); ?></strong><br>
                            <small style="color: #999;"><?php echo htmlspecialchars($dept['dep_code']); ?></small>
                        </td>
                        <td>
                            <strong style="color: #667eea;"><?php echo $dept['avg_rating']; ?></strong> / 5.0
                        </td>
                        <td><?php echo $dept['completed_tokens']; ?></td>
                        <td><?php echo $dept['total_tokens']; ?></td>
                        <td>
                            <strong><?php echo $dept['completion_rate']; ?>%</strong>
                            <div style="width: 100px; height: 6px; background: #e0e0e0; border-radius: 3px; margin-top: 5px;">
                                <div style="width: <?php echo $dept['completion_rate']; ?>%; height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 3px;"></div>
                            </div>
                        </td>
                        <td><?php echo $dept['total_students']; ?></td>
                        <td><?php echo $dept['total_courses']; ?></td>
                    </tr>
                <?php
                    $rank++;
                endforeach;
                ?>
            </tbody>
        </table>
    </div>

    <!-- Report Summary -->
    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h3 style="margin: 0 0 10px 0;">Report Summary</h3>
        <p style="margin: 0; color: #666; font-size: 14px;">
            Generated on <?php echo date('F d, Y h:i A'); ?><br>
            Showing <?php echo count($departments); ?> department(s) with sufficient evaluation data
            <?php if ($filter_academic_year > 0 || $filter_semester > 0): ?>
                <br><strong>Filters Applied:</strong>
                <?php
                $filters = [];
                if ($filter_academic_year > 0) {
                    foreach ($academic_years as $y) {
                        if ($y['academic_year_id'] == $filter_academic_year) {
                            $filters[] = "Academic Year: " . $y['year_label'];
                            break;
                        }
                    }
                }
                if ($filter_semester > 0) {
                    foreach ($semesters as $s) {
                        if ($s['semester_id'] == $filter_semester) {
                            $filters[] = "Semester: " . $s['semester_name'];
                            break;
                        }
                    }
                }
                echo implode(' | ', $filters);
                ?>
            <?php endif; ?>
        </p>
    </div>

<?php endif; ?>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
