<?php

/**
 * Student Evaluation History
 *
 * Displays all completed course evaluations for the student.
 * Shows submission dates and course information.
 *
 * Features:
 * - List all completed evaluations
 * - Show course code, name, submission date
 * - Filter by semester/academic year
 * - Sort by date
 * - Search by course name
 * - Export to CSV
 * - Cannot view actual responses (anonymity protection)
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
$page_title = 'Evaluation History';

// Get filter parameters
$filter_academic_year = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$filter_semester = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
// Explicit allowlist — the value is interpolated directly into the SQL ORDER BY
// clause and must never reach that point without a strict whitelist check.
$sort_order = strtoupper(trim($_GET['order'] ?? 'DESC'));
if (!in_array($sort_order, ['ASC', 'DESC'], true)) {
    $sort_order = 'DESC';
}

// Get all academic years for filter
$query_years = "
    SELECT DISTINCT
        ay.academic_year_id,
        ay.year_label
    FROM evaluation_tokens et
    JOIN academic_year ay ON et.academic_year_id = ay.academic_year_id
    WHERE et.student_user_id = ? AND et.is_used = 1
    ORDER BY ay.start_year DESC
";

$stmt_years = mysqli_prepare($conn, $query_years);
mysqli_stmt_bind_param($stmt_years, "i", $student_id);
mysqli_stmt_execute($stmt_years);
$result_years = mysqli_stmt_get_result($stmt_years);
$academic_years = [];
while ($row = mysqli_fetch_assoc($result_years)) {
    $academic_years[] = $row;
}
mysqli_stmt_close($stmt_years);

// Get all semesters for filter
$query_semesters = "SELECT * FROM semesters ORDER BY semester_value";
$result_semesters = mysqli_query($conn, $query_semesters);
$semesters = [];
while ($row = mysqli_fetch_assoc($result_semesters)) {
    $semesters[] = $row;
}

// Build query for completed evaluations
$query = "
    SELECT
        et.token_id,
        et.used_at,
        c.course_code,
        c.name as course_name,
        l.level_name,
        s.semester_name,
        ay.year_label,
        d.dep_name,
        e.evaluation_id
    FROM evaluation_tokens et
    JOIN courses c ON et.course_id = c.id
    LEFT JOIN level l ON c.level_id = l.t_id
    LEFT JOIN semesters s ON et.semester_id = s.semester_id
    LEFT JOIN academic_year ay ON et.academic_year_id = ay.academic_year_id
    LEFT JOIN department d ON c.department_id = d.t_id
    LEFT JOIN evaluations e ON et.token = e.token
    WHERE et.student_user_id = ?
    AND et.is_used = 1
";

// Add filters
$params = [$student_id];
$types = 'i';

if ($filter_academic_year > 0) {
    $query .= " AND et.academic_year_id = ?";
    $params[] = $filter_academic_year;
    $types .= 'i';
}

if ($filter_semester > 0) {
    $query .= " AND et.semester_id = ?";
    $params[] = $filter_semester;
    $types .= 'i';
}

if (!empty($search_query)) {
    $query .= " AND (c.course_code LIKE ? OR c.name LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

// Add sorting
$query .= " ORDER BY et.used_at $sort_order";

// Execute query
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$evaluations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $evaluations[] = $row;
}
mysqli_stmt_close($stmt);

$total_count = count($evaluations);

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'Evaluation History' => ''
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
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
    }

    .filter-group label {
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 5px;
        color: #333;
    }

    .filter-group select,
    .filter-group input {
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }

    .filter-actions {
        display: flex;
        gap: 10px;
        align-items: center;
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

    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

    .stat-icon {
        font-size: 32px;
        margin-bottom: 10px;
    }

    .stat-value {
        font-size: 32px;
        font-weight: bold;
        color: #667eea;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 14px;
        color: #666;
    }

    .history-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .history-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .history-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #333;
        font-size: 13px;
        border-bottom: 2px solid #e0e0e0;
        white-space: nowrap;
    }

    .history-table th a {
        color: #333;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .history-table th a:hover {
        color: #667eea;
    }

    .history-table td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
    }

    .history-table tr:hover {
        background: #f8f9fa;
    }

    .course-info {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .course-code {
        font-weight: 600;
        color: #667eea;
        font-size: 14px;
    }

    .course-name {
        color: #333;
        font-size: 14px;
    }

    .course-meta {
        color: #999;
        font-size: 12px;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        background: #d4edda;
        color: #155724;
        display: inline-block;
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
        align-items: center;
        gap: 10px;
    }

    @media (max-width: 768px) {
        .filters-grid {
            grid-template-columns: 1fr;
        }

        .filter-actions {
            flex-direction: column;
            width: 100%;
        }

        .btn {
            width: 100%;
        }

        .history-table {
            overflow-x: auto;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1>Evaluation History</h1>
    <p>View all your completed course evaluations</p>
</div>

<!-- Summary Statistics -->
<div class="summary-stats">
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?php echo $total_count; ?></div>
        <div class="stat-label">Total Submissions</div>
    </div>

    <?php if (!empty($academic_years)): ?>
        <div class="stat-card">
            <div class="stat-icon">📖</div>
            <div class="stat-value"><?php echo count($academic_years); ?></div>
            <div class="stat-label">Academic Years</div>
        </div>
    <?php endif; ?>

    <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-value">
            <?php
            if (!empty($evaluations)) {
                echo date('M Y', strtotime($evaluations[0]['used_at']));
            } else {
                echo 'N/A';
            }
            ?>
        </div>
        <div class="stat-label">Most Recent</div>
    </div>
</div>

<!-- Filters -->
<div class="filters-section">
    <form method="GET" action="">
        <div class="filters-grid">
            <!-- Academic Year Filter -->
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

            <!-- Semester Filter -->
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

            <!-- Search -->
            <div class="filter-group">
                <label for="search">Search Courses</label>
                <input type="text"
                    name="search"
                    id="search"
                    placeholder="Course code or name"
                    value="<?php echo htmlspecialchars($search_query); ?>" maxlength="100">
            </div>

            <!-- Sort Order -->
            <div class="filter-group">
                <label for="order">Sort By Date</label>
                <select name="order" id="order">
                    <option value="desc" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="asc" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                </select>
            </div>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="history.php" class="btn btn-secondary">Reset</a>
            <?php if (!empty($evaluations)): ?>
                <button type="button" onclick="exportTableToCSV('history-table', 'evaluation_history.csv')" class="btn btn-success">
                    Export to CSV
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Privacy Notice -->
<div class="alert-info-custom">
    <span style="font-size: 24px;">🔒</span>
    <div>
        <strong>Privacy Protection:</strong><br>
        This page shows only the metadata of your submissions (course name, date, etc.).
        Your actual responses remain completely anonymous and cannot be viewed by anyone,
        including yourself, to maintain the integrity of the evaluation system.
    </div>
</div>

<!-- Evaluation History Table -->
<?php if (empty($evaluations)): ?>
    <!-- Empty State -->
    <div class="empty-state">
        <div class="empty-state-icon">📜</div>
        <h3>No Evaluation History</h3>
        <?php if (!empty($search_query) || $filter_academic_year > 0 || $filter_semester > 0): ?>
            <p>No evaluations found matching your search criteria.<br>
                Try adjusting your filters or search terms.</p>
            <a href="history.php" class="btn btn-secondary">Clear Filters</a>
        <?php else: ?>
            <p>You haven't completed any course evaluations yet.<br>
                Start evaluating courses to see your submission history here.</p>
            <a href="available_courses.php" class="btn btn-primary">Evaluate Courses</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <!-- History Table -->
    <div class="history-table">
        <table id="history-table">
            <thead>
                <tr>
                    <th scope="col" style="width: 5%;">#</th>
                    <th scope="col" style="width: 35%;">Course</th>
                    <th scope="col" style="width: 15%;">Level</th>
                    <th scope="col" style="width: 15%;">Semester</th>
                    <th scope="col" style="width: 15%;">Academic Year</th>
                    <th scope="col" style="width: 20%;">
                        <a href="?order=<?php echo $sort_order == 'DESC' ? 'asc' : 'desc'; ?>&academic_year_id=<?php echo $filter_academic_year; ?>&semester_id=<?php echo $filter_semester; ?>&search=<?php echo urlencode($search_query); ?>">
                            Submitted On <?php echo $sort_order == 'DESC' ? '↓' : '↑'; ?>
                        </a>
                    </th>
                    <th scope="col" style="width: 10%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $counter = 1;
                foreach ($evaluations as $eval):
                ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td>
                            <div class="course-info">
                                <div class="course-code"><?php echo htmlspecialchars($eval['course_code']); ?></div>
                                <div class="course-name"><?php echo htmlspecialchars($eval['course_name']); ?></div>
                                <div class="course-meta"><?php echo htmlspecialchars($eval['dep_name']); ?></div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($eval['level_name']); ?></td>
                        <td><?php echo htmlspecialchars($eval['semester_name']); ?></td>
                        <td><?php echo htmlspecialchars($eval['year_label']); ?></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($eval['used_at'])); ?><br>
                            <small style="color: #999;"><?php echo date('h:i A', strtotime($eval['used_at'])); ?></small>
                        </td>
                        <td>
                            <span class="status-badge">✓ Submitted</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Table Summary -->
    <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 14px; color: #666; text-align: center;">
        Showing <?php echo $total_count; ?> evaluation(s)
        <?php if (!empty($search_query)): ?>
            matching "<?php echo htmlspecialchars($search_query); ?>"
        <?php endif; ?>
        <?php if ($filter_academic_year > 0 || $filter_semester > 0): ?>
            with applied filters
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Additional Information -->
<div style="margin-top: 30px; padding: 20px; background: white; border-radius: 8px; border-left: 4px solid #667eea;">
    <h3 style="margin: 0 0 10px 0; color: #333; font-size: 16px;">📊 About Your Evaluation History</h3>
    <ul style="margin: 0; padding-left: 20px; color: #666; font-size: 14px; line-height: 1.8;">
        <li>This history shows all course evaluations you have submitted</li>
        <li>Individual responses are not displayed to maintain anonymity</li>
        <li>Evaluation data is aggregated with other students' responses</li>
        <li>Your feedback helps improve course quality at <?php echo INSTITUTION_NAME; ?></li>
        <li>Records are kept for institutional quality assurance purposes</li>
    </ul>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
