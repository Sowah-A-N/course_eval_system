<?php
/**
 * Advisor Students List
 * 
 * Displays all students in the advisor's assigned classes/levels.
 * Shows student information and evaluation completion status.
 * 
 * Features:
 * - List all students in assigned levels
 * - Filter by level
 * - Show evaluation completion status
 * - Export to CSV option
 * - Search functionality
 * - Sort by various fields
 * 
 * Role Required: ROLE_ADVISOR
 */

// Include required files
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';

// Start session and check login
start_secure_session();
check_login();

// Check if user is an advisor
if ($_SESSION['role_id'] != ROLE_ADVISOR) {
    $_SESSION['flash_message'] = 'Access denied. This page is only for advisors.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

// Get advisor information
$advisor_id = $_SESSION['user_id'];
$advisor_name = $_SESSION['full_name'];
$department_id = $_SESSION['department_id'];

// Set page title
$page_title = 'My Students';

// Get filter parameters
$filter_level = isset($_GET['level_id']) ? intval($_GET['level_id']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'desc' ? 'DESC' : 'ASC';

// Get advisor's assigned levels
$query_levels = "
    SELECT 
        al.level_id,
        l.level_name,
        l.level_number
    FROM advisor_levels al
    JOIN level l ON al.level_id = l.t_id
    WHERE al.advisor_id = ?
    ORDER BY l.level_number
";

$stmt_levels = mysqli_prepare($conn, $query_levels);
mysqli_stmt_bind_param($stmt_levels, "i", $advisor_id);
mysqli_stmt_execute($stmt_levels);
$result_levels = mysqli_stmt_get_result($stmt_levels);
$assigned_levels = [];
$level_ids = [];
while ($row = mysqli_fetch_assoc($result_levels)) {
    $assigned_levels[] = $row;
    $level_ids[] = $row['level_id'];
}
mysqli_stmt_close($stmt_levels);

// If no levels assigned, show message
if (empty($level_ids)) {
    $no_assignment = true;
} else {
    $no_assignment = false;
    
    // Build query to get students
    $query = "
        SELECT 
            u.user_id,
            u.f_name,
            u.l_name,
            u.username,
            u.email,
            u.unique_id,
            u.is_active,
            l.level_name,
            l.level_number,
            c.class_name,
            d.dep_name,
            d.dep_code,
            (SELECT COUNT(*) FROM evaluation_tokens et WHERE et.student_user_id = u.user_id) as total_tokens,
            (SELECT COUNT(*) FROM evaluation_tokens et WHERE et.student_user_id = u.user_id AND et.is_used = 1) as completed_tokens
        FROM user_details u
        LEFT JOIN level l ON u.level_id = l.t_id
        LEFT JOIN classes c ON u.class_id = c.t_id
        LEFT JOIN department d ON u.department_id = d.t_id
        WHERE u.role_id = ?
        AND u.level_id IN (" . implode(',', array_fill(0, count($level_ids), '?')) . ")
        AND u.department_id = ?
    ";
    
    // Add level filter if specified
    if ($filter_level > 0 && in_array($filter_level, $level_ids)) {
        $query .= " AND u.level_id = ?";
    }
    
    // Add search filter
    if (!empty($search_query)) {
        $query .= " AND (u.f_name LIKE ? OR u.l_name LIKE ? OR u.email LIKE ? OR u.unique_id LIKE ?)";
    }
    
    // Add sorting
    switch ($sort_by) {
        case 'name':
            $query .= " ORDER BY u.f_name $sort_order, u.l_name $sort_order";
            break;
        case 'level':
            $query .= " ORDER BY l.level_number $sort_order, u.f_name ASC";
            break;
        case 'class':
            $query .= " ORDER BY c.class_name $sort_order, u.f_name ASC";
            break;
        case 'email':
            $query .= " ORDER BY u.email $sort_order";
            break;
        case 'status':
            $query .= " ORDER BY u.is_active $sort_order, u.f_name ASC";
            break;
        case 'completion':
            $query .= " ORDER BY completed_tokens $sort_order, u.f_name ASC";
            break;
        default:
            $query .= " ORDER BY u.f_name ASC, u.l_name ASC";
    }
    
    // Prepare statement
    $stmt = mysqli_prepare($conn, $query);
    
    // Bind parameters dynamically
    $types = 'i'; // role_id
    $params = [ROLE_STUDENT];
    
    // Add level_ids
    foreach ($level_ids as $lid) {
        $types .= 'i';
        $params[] = $lid;
    }
    
    // Add department_id
    $types .= 'i';
    $params[] = $department_id;
    
    // Add level filter if specified
    if ($filter_level > 0 && in_array($filter_level, $level_ids)) {
        $types .= 'i';
        $params[] = $filter_level;
    }
    
    // Add search parameters
    if (!empty($search_query)) {
        $search_param = "%$search_query%";
        $types .= 'ssss';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Bind parameters
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    // Execute query
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Fetch students
    $students = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Calculate completion percentage
        if ($row['total_tokens'] > 0) {
            $row['completion_percentage'] = round(($row['completed_tokens'] / $row['total_tokens']) * 100, 1);
        } else {
            $row['completion_percentage'] = 0;
        }
        $students[] = $row;
    }
    
    mysqli_stmt_close($stmt);
}

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'My Students' => ''
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
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-success {
        background: #28a745;
        color: white;
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
    
    .students-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .students-table table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .students-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #e0e0e0;
        white-space: nowrap;
    }
    
    .students-table th a {
        color: #333;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .students-table th a:hover {
        color: #667eea;
    }
    
    .students-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .students-table tr:hover {
        background: #f8f9fa;
    }
    
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: inline-block;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    
    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }
    
    .progress-mini {
        width: 80px;
        height: 8px;
        background: #e0e0e0;
        border-radius: 4px;
        overflow: hidden;
        display: inline-block;
        vertical-align: middle;
        margin-right: 5px;
    }
    
    .progress-mini-bar {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }
    
    .no-data {
        text-align: center;
        padding: 40px;
        color: #666;
        font-style: italic;
    }
    
    .alert-info-custom {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 20px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1>My Students</h1>
    <p>View and manage students in your assigned classes</p>
</div>

<?php if ($no_assignment): ?>
    <!-- No Assignment Message -->
    <div class="alert-info-custom">
        <strong>No Class Assignments</strong><br>
        You have not been assigned to any classes yet. Please contact your department head.
    </div>
<?php else: ?>

    <!-- Statistics -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-value"><?php echo count($students); ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">
                <?php 
                $active_count = count(array_filter($students, function($s) { return $s['is_active'] == 1; }));
                echo $active_count;
                ?>
            </div>
            <div class="stat-label">Active Students</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">
                <?php 
                $completed_students = count(array_filter($students, function($s) { return $s['completed_tokens'] > 0; }));
                echo $completed_students;
                ?>
            </div>
            <div class="stat-label">Students with Evaluations</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">
                <?php 
                $completion_rate = count($students) > 0 ? 
                    round(($completed_students / count($students)) * 100, 1) : 0;
                echo $completion_rate;
                ?>%
            </div>
            <div class="stat-label">Participation Rate</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" action="">
            <div class="filters-grid">
                <!-- Level Filter -->
                <div class="filter-group">
                    <label for="level_id">Filter by Level</label>
                    <select name="level_id" id="level_id">
                        <option value="0">All Levels</option>
                        <?php foreach ($assigned_levels as $level): ?>
                            <option value="<?php echo $level['level_id']; ?>" 
                                    <?php echo $filter_level == $level['level_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($level['level_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Search -->
                <div class="filter-group">
                    <label for="search">Search Students</label>
                    <input type="text" 
                           name="search" 
                           id="search" 
                           placeholder="Name, Email, or Student ID"
                           value="<?php echo htmlspecialchars($search_query); ? maxlength="100">">
                </div>
                
                <!-- Sort By -->
                <div class="filter-group">
                    <label for="sort">Sort By</label>
                    <select name="sort" id="sort">
                        <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name</option>
                        <option value="level" <?php echo $sort_by == 'level' ? 'selected' : ''; ?>>Level</option>
                        <option value="class" <?php echo $sort_by == 'class' ? 'selected' : ''; ?>>Class</option>
                        <option value="email" <?php echo $sort_by == 'email' ? 'selected' : ''; ?>>Email</option>
                        <option value="status" <?php echo $sort_by == 'status' ? 'selected' : ''; ?>>Status</option>
                        <option value="completion" <?php echo $sort_by == 'completion' ? 'selected' : ''; ?>>Completion</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="list.php" class="btn btn-secondary">Reset</a>
                <button type="button" onclick="exportTableToCSV('students-table', 'students.csv')" class="btn btn-success">
                    Export to CSV
                </button>
            </div>
        </form>
    </div>

    <!-- Students Table -->
    <?php if (empty($students)): ?>
        <div class="students-table">
            <div class="no-data">
                No students found matching your criteria.
            </div>
        </div>
    <?php else: ?>
        <div class="students-table">
            <table id="students-table">
                <thead>
                    <tr>
                        <th scope="col">
                            <a href="?sort=name&order=<?php echo $sort_by == 'name' && $sort_order == 'ASC' ? 'desc' : 'asc'; ?>&level_id=<?php echo $filter_level; ?>&search=<?php echo urlencode($search_query); ?>">
                                Student Name <?php echo $sort_by == 'name' ? ($sort_order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th scope="col">Student ID</th>
                        <th scope="col">
                            <a href="?sort=email&order=<?php echo $sort_by == 'email' && $sort_order == 'ASC' ? 'desc' : 'asc'; ?>&level_id=<?php echo $filter_level; ?>&search=<?php echo urlencode($search_query); ?>">
                                Email <?php echo $sort_by == 'email' ? ($sort_order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th scope="col">
                            <a href="?sort=level&order=<?php echo $sort_by == 'level' && $sort_order == 'ASC' ? 'desc' : 'asc'; ?>&level_id=<?php echo $filter_level; ?>&search=<?php echo urlencode($search_query); ?>">
                                Level <?php echo $sort_by == 'level' ? ($sort_order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th scope="col">
                            <a href="?sort=class&order=<?php echo $sort_by == 'class' && $sort_order == 'ASC' ? 'desc' : 'asc'; ?>&level_id=<?php echo $filter_level; ?>&search=<?php echo urlencode($search_query); ?>">
                                Class <?php echo $sort_by == 'class' ? ($sort_order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th scope="col">
                            <a href="?sort=status&order=<?php echo $sort_by == 'status' && $sort_order == 'ASC' ? 'desc' : 'asc'; ?>&level_id=<?php echo $filter_level; ?>&search=<?php echo urlencode($search_query); ?>">
                                Status <?php echo $sort_by == 'status' ? ($sort_order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th scope="col">
                            <a href="?sort=completion&order=<?php echo $sort_by == 'completion' && $sort_order == 'ASC' ? 'desc' : 'asc'; ?>&level_id=<?php echo $filter_level; ?>&search=<?php echo urlencode($search_query); ?>">
                                Evaluations <?php echo $sort_by == 'completion' ? ($sort_order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?php echo htmlspecialchars($student['f_name'] . ' ' . $student['l_name']); ?>
                                </strong>
                            </td>
                            <td><?php echo htmlspecialchars($student['unique_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo htmlspecialchars($student['level_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($student['is_active'] == 1): ?>
                                    <span class="status-badge status-active">Active</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="progress-mini">
                                    <div class="progress-mini-bar" style="width: <?php echo $student['completion_percentage']; ?>%;"></div>
                                </div>
                                <?php echo $student['completed_tokens']; ?>/<?php echo $student['total_tokens']; ?>
                                (<?php echo $student['completion_percentage']; ?>%)
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Summary -->
    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 14px; color: #666;">
        Showing <?php echo count($students); ?> student(s)
        <?php if ($filter_level > 0): ?>
            in selected level
        <?php endif; ?>
        <?php if (!empty($search_query)): ?>
            matching "<?php echo htmlspecialchars($search_query); ?>"
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php
// Include footer
require_once '../../includes/footer.php';
?>