<?php
/**
 * Trend Analysis Report
 *
 * Track evaluation performance trends over time.
 * Year-over-year and semester-over-semester comparison.
 *
 * Role Required: ROLE_QUALITY or ROLE_ADMIN
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

// ROLE_QUALITY is defined in constants.php (already loaded above)
// Bug was: !== ROLE_ADMIN || !== ROLE_QUALITY is always true (blocks everyone).
// Fix: use && so only non-admin AND non-quality users are redirected.
if ($_SESSION['role_id'] !== ROLE_ADMIN && $_SESSION['role_id'] !== ROLE_QUALITY) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$page_title = 'Trend Analysis Report';

// Get trends by period
$query = "
    SELECT
        ay.year_label,
        s.semester_name,
        s.semester_value,
        ay.start_year,
        COUNT(DISTINCT CASE WHEN et.is_used = 1 THEN et.token_id END) as completed,
        COUNT(DISTINCT et.token_id) as total,
        AVG(CASE WHEN et.is_used = 1 THEN CAST(r.response_value AS DECIMAL(10,2)) END) as avg_rating
    FROM evaluation_tokens et
    JOIN academic_year ay ON et.academic_year_id = ay.academic_year_id
    JOIN semesters s ON et.semester_id = s.semester_id
    LEFT JOIN evaluations e ON et.token = e.token
    LEFT JOIN responses r ON e.evaluation_id = r.evaluation_id
    GROUP BY ay.academic_year_id, s.semester_id
    HAVING completed >= ?
    ORDER BY ay.start_year DESC, s.semester_value
";

$stmt = mysqli_prepare($conn, $query);
$min_count = MIN_RESPONSE_COUNT;
mysqli_stmt_bind_param($stmt, "i", $min_count);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$trends = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['completion_rate'] = $row['total'] > 0 ? round(($row['completed'] / $row['total']) * 100, 1) : 0;
    $row['avg_rating'] = round($row['avg_rating'], 2);
    $trends[] = $row;
}
mysqli_stmt_close($stmt);

// $breadcrumb = ['Dashboard' => '../index.php', 'Reports' => 'index.php', 'Trend Analysis' => ''];
require_once '../../includes/header.php';
?>

<style>
    .trend-card {background: white; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
    .period-header {display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px;}
    .period-name {font-size: 18px; font-weight: 600; color: #333;}
    .period-rating {font-size: 32px; font-weight: bold; color: #667eea;}
    .metrics-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 15px;}
    .metric-box {text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;}
    .metric-value {font-size: 24px; font-weight: bold; color: #333;}
    .metric-label {font-size: 13px; color: #666; margin-top: 5px;}
    .progress-bar {height: 10px; background: #e0e0e0; border-radius: 5px; overflow: hidden; margin-top: 10px;}
    .progress-fill {height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);}
</style>

<div class="page-header">
    <h1>Trend Analysis Report</h1>
    <p>Track performance trends over time</p>
</div>

<?php if (empty($trends)): ?>
    <div style="text-align: center; padding: 60px; background: white; border-radius: 8px;">
        <div style="font-size: 80px; opacity: 0.3;">📈</div>
        <h3>No Trend Data Available</h3>
        <p style="color: #666;">Insufficient data to display trends (min <?php echo MIN_RESPONSE_COUNT; ?> evaluations per period required).</p>
    </div>
<?php else: ?>

    <?php foreach ($trends as $trend): ?>
        <div class="trend-card">
            <div class="period-header">
                <div class="period-name">
                    <?php echo htmlspecialchars($trend['year_label']); ?> -
                    <?php echo htmlspecialchars($trend['semester_name']); ?>
                </div>
                <div class="period-rating"><?php echo $trend['avg_rating']; ?> / 5.0</div>
            </div>

            <div class="metrics-grid">
                <div class="metric-box">
                    <div class="metric-value"><?php echo $trend['completed']; ?></div>
                    <div class="metric-label">Evaluations</div>
                </div>
                <div class="metric-box">
                    <div class="metric-value"><?php echo $trend['completion_rate']; ?>%</div>
                    <div class="metric-label">Completion Rate</div>
                </div>
                <div class="metric-box">
                    <div class="metric-value"><?php echo $trend['total']; ?></div>
                    <div class="metric-label">Total Tokens</div>
                </div>
            </div>

            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $trend['completion_rate']; ?>%;"></div>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="margin-top: 30px; padding: 20px; background: white; border-radius: 8px; border-left: 4px solid #667eea;">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">📊 Trend Insights</h3>
        <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">
            This report shows evaluation trends across different academic periods.
            Compare completion rates and average ratings to identify improvement or decline patterns.
            Data is displayed for periods with at least <?php echo MIN_RESPONSE_COUNT; ?> evaluations to ensure anonymity.
        </p>
    </div>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
