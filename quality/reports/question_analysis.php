<?php
/**
 * Question Analysis Report
 *
 * Evaluate effectiveness of evaluation questions.
 * Shows which questions get highest/lowest ratings.
 *
 * Role Required: ROLE_QUALITY or ROLE_ADMIN
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if (!defined('ROLE_QUALITY')) define('ROLE_QUALITY', 6);
if ($_SESSION['role_id'] != ROLE_ADMIN && $_SESSION['role_id'] != ROLE_QUALITY) {
    header("Location: ../../login.php");
    exit();
}

$page_title = 'Question Analysis Report';

// Get question statistics
$query = "
    SELECT
        eq.question_id,
        eq.question_text,
        eq.category,
        COUNT(r.id) as response_count,
        AVG(CAST(r.response_value AS DECIMAL(10,2))) as avg_rating,
        MIN(CAST(r.response_value AS DECIMAL(10,2))) as min_rating,
        MAX(CAST(r.response_value AS DECIMAL(10,2))) as max_rating,
        STDDEV(CAST(r.response_value AS DECIMAL(10,2))) as std_rating
    FROM evaluation_questions eq
    LEFT JOIN responses r ON eq.question_id = r.question_id
    WHERE eq.is_active = 1
    GROUP BY eq.question_id
    HAVING response_count >= ?
    ORDER BY eq.category, eq.display_order
";

$stmt = mysqli_prepare($conn, $query);
$min_count = MIN_RESPONSE_COUNT;
mysqli_stmt_bind_param($stmt, "i", $min_count);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$questions = [];
$by_category = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['avg_rating'] = round($row['avg_rating'], 2);
    $row['std_rating'] = round($row['std_rating'], 2);
    $questions[] = $row;

    if (!isset($by_category[$row['category']])) {
        $by_category[$row['category']] = [];
    }
    $by_category[$row['category']][] = $row;
}
mysqli_stmt_close($stmt);

// $breadcrumb = ['Dashboard' => '../index.php', 'Reports' => 'index.php', 'Question Analysis' => ''];
require_once '../../includes/header.php';
?>

<style>
    .section-title {font-size: 20px; font-weight: 600; color: #333; margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #667eea;}
    .category-section {background: white; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
    .question-item {padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea; margin-bottom: 15px;}
    .question-text {font-size: 16px; font-weight: 500; color: #333; margin-bottom: 15px;}
    .stats-row {display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-top: 10px;}
    .stat-box {text-align: center; padding: 10px; background: white; border-radius: 5px;}
    .stat-value {font-size: 20px; font-weight: bold; color: #667eea;}
    .stat-label {font-size: 12px; color: #666; margin-top: 5px;}
    .rating-bar {height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; margin-top: 10px;}
    .rating-fill {height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);}
</style>

<div class="page-header">
    <h1>Question Analysis Report</h1>
    <p>Evaluate effectiveness of evaluation questions</p>
</div>

<?php if (empty($questions)): ?>
    <div style="text-align: center; padding: 60px; background: white; border-radius: 8px;">
        <div style="font-size: 80px; opacity: 0.3;">❓</div>
        <h3>No Question Data Available</h3>
        <p style="color: #666;">Insufficient responses to display question statistics (min <?php echo MIN_RESPONSE_COUNT; ?> required).</p>
    </div>
<?php else: ?>

    <?php foreach ($by_category as $category => $cat_questions): ?>
        <h2 class="section-title"><?php echo htmlspecialchars($category); ?></h2>
        <div class="category-section">
            <?php foreach ($cat_questions as $q): ?>
                <div class="question-item">
                    <div class="question-text"><?php echo htmlspecialchars($q['question_text']); ?></div>

                    <div class="rating-bar">
                        <div class="rating-fill" style="width: <?php echo ($q['avg_rating'] / 5) * 100; ?>%;"></div>
                    </div>

                    <div class="stats-row">
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $q['avg_rating']; ?></div>
                            <div class="stat-label">Average</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $q['response_count']; ?></div>
                            <div class="stat-label">Responses</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $q['min_rating']; ?></div>
                            <div class="stat-label">Minimum</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $q['max_rating']; ?></div>
                            <div class="stat-label">Maximum</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $q['std_rating']; ?></div>
                            <div class="stat-label">Std Dev</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
