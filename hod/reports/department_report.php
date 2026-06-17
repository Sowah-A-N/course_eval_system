<?php
/**
 * HOD Department Report
 * Comprehensive department performance statistics.
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_HOD) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$department_id = $_SESSION['department_id'];
$page_title = 'Department Report';

// Get filters
$filter_year = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$filter_semester = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;

// Get academic years and semesters
$academic_years = [];
$result_years = mysqli_query($conn, "SELECT * FROM academic_year ORDER BY start_year DESC");
while ($row = mysqli_fetch_assoc($result_years)) $academic_years[] = $row;

$semesters = [];
$result_sems = mysqli_query($conn, "SELECT * FROM semesters ORDER BY semester_value");
while ($row = mysqli_fetch_assoc($result_sems)) $semesters[] = $row;

// Build query
$where = ["c.department_id = ?"];
$params = [$department_id];
$types = 'i';

if ($filter_year > 0) {
    $where[] = "et.academic_year_id = ?";
    $params[] = $filter_year;
    $types .= 'i';
}
if ($filter_semester > 0) {
    $where[] = "et.semester_id = ?";
    $params[] = $filter_semester;
    $types .= 'i';
}

$where_clause = implode(' AND ', $where);

// Get statistics
$query_stats = "
    SELECT 
        COUNT(DISTINCT c.id) as total_courses,
        COUNT(DISTINCT et.token_id) as total_tokens,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as completed,
        AVG(CASE WHEN et.is_used = 1 THEN CAST(r.response_value AS DECIMAL(10,2)) END) as avg_rating
    FROM courses c
    LEFT JOIN evaluation_tokens et ON c.id = et.course_id
    LEFT JOIN evaluations e ON et.token = e.token
    LEFT JOIN responses r ON e.evaluation_id = r.evaluation_id
    WHERE $where_clause
";

$stmt = mysqli_prepare($conn, $query_stats);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$completion_rate = $stats['total_tokens'] > 0 ? 
    round(($stats['completed'] / $stats['total_tokens']) * 100, 1) : 0;
$avg_rating = $stats['completed'] >= MIN_RESPONSE_COUNT ? 
    round($stats['avg_rating'], 2) : null;

// Get category performance
$category_stats = [];
if ($stats['completed'] >= MIN_RESPONSE_COUNT) {
    $query_cats = "
        SELECT 
            eq.category,
            COUNT(r.id) as response_count,
            AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating
        FROM evaluation_questions eq
        JOIN responses r ON eq.question_id = r.question_id
        JOIN evaluations e ON r.evaluation_id = e.evaluation_id
        JOIN evaluation_tokens et ON e.token = et.token
        JOIN courses c ON et.course_id = c.id
        WHERE $where_clause
        GROUP BY eq.category
        ORDER BY avg_rating DESC
    ";
    
    $stmt_cats = mysqli_prepare($conn, $query_cats);
    mysqli_stmt_bind_param($stmt_cats, $types, ...$params);
    mysqli_stmt_execute($stmt_cats);
    $result_cats = mysqli_stmt_get_result($stmt_cats);
    
    while ($row = mysqli_fetch_assoc($result_cats)) {
        $row['avg_rating'] = round($row['avg_rating'], 2);
        $category_stats[] = $row;
    }
    mysqli_stmt_close($stmt_cats);
}

require_once '../../includes/header.php';
?>

<style>
.filters-section{background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
.filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:15px}
.filter-group label{font-size:14px;font-weight:500;margin-bottom:5px;display:block}
.filter-group select{width:100%;padding:10px;border:1px solid #ddd;border-radius:5px}
.btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
.btn-secondary{background:#6c757d;color:white}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:30px}
.stat-card{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);text-align:center}
.stat-value{font-size:36px;font-weight:bold;color:#667eea}
.stat-label{font-size:14px;color:#666;margin-top:5px}
.category-card{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:15px;border-left:4px solid #667eea}
.category-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.category-name{font-weight:600;color:#333}
.category-rating{font-size:24px;font-weight:bold;color:#667eea}
.progress-bar{height:8px;background:#e0e0e0;border-radius:4px;overflow:hidden;margin-top:10px}
.progress-fill{height:100%;background:linear-gradient(90deg,#667eea 0%,#764ba2 100%)}
</style>

<div class="page-header">
<h1>Department Performance Report</h1>
<p>Evaluation statistics for your department</p>
</div>

<div class="filters-section">
<form method="GET">
<div class="filters-grid">
<div class="filter-group">
<label>Academic Year</label>
<select name="academic_year_id">
<option value="0">All Years</option>
<?php foreach($academic_years as $year): ?>
<option value="<?php echo $year['academic_year_id'];?>" <?php echo $filter_year==$year['academic_year_id']?'selected':'';?>>
<?php echo htmlspecialchars($year['year_label']);?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="filter-group">
<label>Semester</label>
<select name="semester_id">
<option value="0">All Semesters</option>
<?php foreach($semesters as $sem): ?>
<option value="<?php echo $sem['semester_id'];?>" <?php echo $filter_semester==$sem['semester_id']?'selected':'';?>>
<?php echo htmlspecialchars($sem['semester_name']);?>
</option>
<?php endforeach;?>
</select>
</div>
</div>
<button type="submit" class="btn btn-primary">Generate Report</button>
<a href="department_report.php" class="btn btn-secondary">Reset</a>
</form>
</div>

<div class="stats-grid">
<div class="stat-card">
<div class="stat-value"><?php echo $completion_rate;?>%</div>
<div class="stat-label">Completion Rate</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $stats['completed'];?></div>
<div class="stat-label">Evaluations</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $stats['total_courses'];?></div>
<div class="stat-label">Courses</div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo $avg_rating?$avg_rating.'/5.0':'N/A';?></div>
<div class="stat-label">Avg Rating</div>
</div>
</div>

<?php if(!empty($category_stats)): ?>
<h2 style="font-size:20px;font-weight:600;margin:30px 0 15px;border-bottom:2px solid #667eea;padding-bottom:10px">Category Performance</h2>
<?php foreach($category_stats as $cat): ?>
<div class="category-card">
<div class="category-header">
<span class="category-name"><?php echo htmlspecialchars($cat['category']);?></span>
<span class="category-rating"><?php echo $cat['avg_rating'];?>/5.0</span>
</div>
<div style="font-size:13px;color:#666;margin-bottom:10px">
Based on <?php echo $cat['response_count'];?> responses
</div>
<div class="progress-bar">
<div class="progress-fill" style="width:<?php echo ($cat['avg_rating']/5)*100;?>%"></div>
</div>
</div>
<?php endforeach;?>
<?php endif;?>

<?php require_once '../../includes/footer.php';?>
