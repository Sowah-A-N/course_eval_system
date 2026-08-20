<?php
/**
 * Institutional Services Report
 *
 * Institution-wide analysis of the once-per-semester administrative evaluation:
 * how students rate central services (Registry, Accounts, Library, etc.) and
 * how each class rates its class advisor.
 *
 * Data source: anonymous responses attached to administrative-scope evaluations
 * (evaluations.scope = 'administrative'). These are answered once per student
 * per semester, independently of course evaluations.
 *
 * Features:
 * - Service ratings grouped by category (side-by-side summary cards)
 * - Per-question breakdown within each service category
 * - Class advisor ratings by class, with per-class anonymity protection
 * - Filter by academic year / semester
 * - Anonymity protection (minimum response threshold)
 *
 * Role Required: ROLE_QUALITY or ROLE_ADMIN
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';

start_secure_session();
check_login();

if (!defined('ROLE_QUALITY')) {
    define('ROLE_QUALITY', 6);
}

if ($_SESSION['role_id'] !== ROLE_ADMIN && $_SESSION['role_id'] !== ROLE_QUALITY) {
    $_SESSION['flash_message'] = 'Access denied.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$page_title = 'Institutional Services Report';

// -- Filters ---------------------------------------------------------------
$filter_academic_year = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$filter_semester      = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;

$academic_years = [];
$result_years = mysqli_query($conn, "SELECT * FROM academic_year ORDER BY start_year DESC");
while ($row = mysqli_fetch_assoc($result_years)) {
    $academic_years[] = $row;
}

$semesters = [];
$result_semesters = mysqli_query($conn, "SELECT * FROM semesters ORDER BY semester_value");
while ($row = mysqli_fetch_assoc($result_semesters)) {
    $semesters[] = $row;
}

// Build a period clause fragment (applied to the evaluations table, alias e)
// plus a shared parameter list, so every query in this report scopes to the
// same academic year / semester selection.
$period_sql    = '';
$period_types  = '';
$period_params = [];

if ($filter_academic_year > 0) {
    $period_sql .= " AND e.academic_year_id = ?";
    $period_types .= 'i';
    $period_params[] = $filter_academic_year;
}
if ($filter_semester > 0) {
    $period_sql .= " AND e.semester_id = ?";
    $period_types .= 'i';
    $period_params[] = $filter_semester;
}

/**
 * Run a report query with the shared period parameters and return all rows.
 * Uses a prepared statement when parameters are present; a plain query
 * otherwise (MYSQLI_REPORT_OFF, so return values are checked).
 */
function ces_run_report($conn, $sql, $types, $params)
{
    $rows = [];
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt === false) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
    }
    return $rows;
}

// -- Number of respondents (administrative submissions in scope) -----------
// Each administrative evaluation row represents one student's once-per-semester
// submission, so this doubles as the respondent count.
$respondent_rows = ces_run_report(
    $conn,
    "SELECT COUNT(*) AS n FROM evaluations e WHERE e.scope = 'administrative'" . $period_sql,
    $period_types,
    $period_params
);
$total_respondents = !empty($respondent_rows) ? intval($respondent_rows[0]['n']) : 0;

// -- Service ratings (all administrative questions except the advisor one) --
$service_rows = ces_run_report(
    $conn,
    "SELECT eq.category, eq.question_id, eq.question_text, eq.display_order,
            AVG(r.response_value) AS avg_rating, COUNT(r.id) AS response_count
     FROM responses r
     JOIN evaluations e ON r.evaluation_id = e.evaluation_id
     JOIN evaluation_questions eq ON r.question_id = eq.question_id
     WHERE e.scope = 'administrative'
       AND eq.scope = 'administrative'
       AND eq.question_text NOT LIKE '%advisor%'" . $period_sql . "
     GROUP BY eq.question_id, eq.category, eq.question_text, eq.display_order
     ORDER BY eq.category, eq.display_order",
    $period_types,
    $period_params
);

// Group service questions by category and compute a weighted category average.
$categories = [];
foreach ($service_rows as $row) {
    $cat = $row['category'] !== null && $row['category'] !== '' ? $row['category'] : 'Other';
    if (!isset($categories[$cat])) {
        $categories[$cat] = array('questions' => array(), 'weighted_sum' => 0, 'responses' => 0);
    }
    $categories[$cat]['questions'][] = $row;
    $categories[$cat]['weighted_sum'] += floatval($row['avg_rating']) * intval($row['response_count']);
    $categories[$cat]['responses']   += intval($row['response_count']);
}
foreach ($categories as $cat => $data) {
    $categories[$cat]['avg'] = $data['responses'] > 0
        ? $data['weighted_sum'] / $data['responses']
        : 0;
}

// -- Class advisor ratings (grouped by class) ------------------------------
$advisor_rows = ces_run_report(
    $conn,
    "SELECT cl.t_id AS class_id, cl.class_name,
            TRIM(CONCAT(COALESCE(adv.f_name, ''), ' ', COALESCE(adv.l_name, ''))) AS advisor_name,
            AVG(r.response_value) AS avg_rating, COUNT(r.id) AS response_count
     FROM responses r
     JOIN evaluations e ON r.evaluation_id = e.evaluation_id
     JOIN evaluation_questions eq ON r.question_id = eq.question_id
     JOIN classes cl ON e.class_id = cl.t_id
     LEFT JOIN user_details adv ON cl.advisor_user_id = adv.user_id
     WHERE e.scope = 'administrative'
       AND eq.question_text LIKE '%advisor%'" . $period_sql . "
     GROUP BY cl.t_id, cl.class_name, advisor_name
     ORDER BY cl.class_name",
    $period_types,
    $period_params
);

$has_enough = $total_respondents >= MIN_RESPONSE_COUNT;

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

    .filter-group label {
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 5px;
        display: block;
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

    .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .btn-secondary { background: #6c757d; color: white; }

    .report-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .meta-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .meta-value { font-size: 32px; font-weight: bold; color: #667eea; }
    .meta-label { font-size: 14px; color: #666; margin-top: 5px; }

    .section-title {
        font-size: 20px;
        font-weight: 600;
        color: #333;
        margin: 30px 0 15px;
    }

    /* Side-by-side category summary cards */
    .category-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 18px;
        margin-bottom: 10px;
    }

    .category-card {
        background: white;
        padding: 22px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border-top: 4px solid #667eea;
    }

    .category-card .cat-name { font-size: 15px; font-weight: 600; color: #444; margin-bottom: 8px; }
    .category-card .cat-rating { font-size: 40px; font-weight: 700; color: #667eea; line-height: 1; }
    .category-card .cat-rating span { font-size: 16px; color: #999; font-weight: 500; }

    .rating-bar {
        height: 8px;
        background: #eef0f6;
        border-radius: 4px;
        overflow: hidden;
        margin: 12px 0 8px;
    }
    .rating-fill { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); }

    .category-card .cat-count { font-size: 13px; color: #888; }

    .breakdown-table, .advisor-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
    }

    .breakdown-table th, .advisor-table th {
        background: #f8f9fa;
        padding: 13px 15px;
        text-align: left;
        font-weight: 600;
        color: #333;
        font-size: 13px;
        border-bottom: 2px solid #e0e0e0;
    }

    .breakdown-table td, .advisor-table td {
        padding: 13px 15px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
        vertical-align: middle;
    }

    .breakdown-table tr:hover, .advisor-table tr:hover { background: #f8f9fa; }

    .cat-group-head {
        background: #eef0f6;
        font-weight: 700;
        color: #4a4a6a;
    }

    .rating-pill {
        display: inline-block;
        min-width: 46px;
        text-align: center;
        padding: 4px 10px;
        border-radius: 14px;
        font-weight: 600;
        font-size: 13px;
        color: white;
    }

    .num { font-variant-numeric: tabular-nums; }

    .protected { color: #b26a00; font-style: italic; }

    .notice {
        background: #fff8e6;
        border: 1px solid #f0d999;
        color: #8a6d1b;
        padding: 18px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .empty-state {
        text-align: center;
        padding: 50px 20px;
        background: white;
        border-radius: 8px;
        color: #666;
    }
</style>

<div class="page-header">
    <h1>Institutional Services Report</h1>
    <p>How students rate central services and class advisors (once-per-semester evaluation)</p>
</div>

<!-- Filters -->
<div class="filters-section">
    <form method="GET">
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
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?php echo $sem['semester_id']; ?>"
                            <?php echo $filter_semester == $sem['semester_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sem['semester_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="institution_services.php" class="btn btn-secondary">Reset</a>
    </form>
</div>

<!-- Report summary -->
<div class="report-meta">
    <div class="meta-card">
        <div class="meta-value num"><?php echo $total_respondents; ?></div>
        <div class="meta-label">Students Responded</div>
    </div>
    <div class="meta-card">
        <div class="meta-value num"><?php echo count($categories); ?></div>
        <div class="meta-label">Service Categories</div>
    </div>
    <div class="meta-card">
        <div class="meta-value num"><?php echo count($advisor_rows); ?></div>
        <div class="meta-label">Classes with Advisor Ratings</div>
    </div>
</div>

<?php if (!$has_enough): ?>
    <div class="notice">
        <strong>Not enough responses to display results.</strong><br>
        To protect student anonymity, service and advisor ratings are shown only once at least
        <?php echo MIN_RESPONSE_COUNT; ?> students have submitted the administrative evaluation for
        the selected period. Currently <?php echo $total_respondents; ?> submitted.
    </div>
<?php else: ?>

    <!-- Service categories: side-by-side summary cards -->
    <div class="section-title">Service Performance by Category</div>
    <?php if (empty($categories)): ?>
        <div class="empty-state">No service responses recorded for this period.</div>
    <?php else: ?>
        <div class="category-cards">
            <?php foreach ($categories as $cat => $data): ?>
                <?php $pct = round(($data['avg'] / 5) * 100); ?>
                <div class="category-card">
                    <div class="cat-name"><?php echo htmlspecialchars($cat); ?></div>
                    <div class="cat-rating num"><?php echo number_format($data['avg'], 2); ?><span> / 5</span></div>
                    <div class="rating-bar"><div class="rating-fill" style="width: <?php echo $pct; ?>%;"></div></div>
                    <div class="cat-count num"><?php echo $data['responses']; ?> response<?php echo $data['responses'] == 1 ? '' : 's'; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Per-question breakdown -->
        <div class="section-title">Question Breakdown</div>
        <table class="breakdown-table">
            <thead>
                <tr>
                    <th scope="col">Question</th>
                    <th scope="col">Average</th>
                    <th scope="col">Responses</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat => $data): ?>
                    <tr class="cat-group-head">
                        <td colspan="3"><?php echo htmlspecialchars($cat); ?> &mdash; <?php echo number_format($data['avg'], 2); ?> / 5</td>
                    </tr>
                    <?php foreach ($data['questions'] as $q): ?>
                        <?php
                        $avg = floatval($q['avg_rating']);
                        // Colour the rating pill: red (low) -> amber -> green (high).
                        if ($avg >= 4.0) {
                            $color = '#28a745';
                        } elseif ($avg >= 3.0) {
                            $color = '#f0ad4e';
                        } else {
                            $color = '#dc3545';
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($q['question_text']); ?></td>
                            <td><span class="rating-pill num" style="background: <?php echo $color; ?>;"><?php echo number_format($avg, 2); ?></span></td>
                            <td class="num"><?php echo intval($q['response_count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Class advisor ratings -->
    <div class="section-title">Class Advisor Ratings</div>
    <?php if (empty($advisor_rows)): ?>
        <div class="empty-state">No advisor responses recorded for this period.</div>
    <?php else: ?>
        <table class="advisor-table">
            <thead>
                <tr>
                    <th scope="col">Class</th>
                    <th scope="col">Advisor</th>
                    <th scope="col">Average</th>
                    <th scope="col">Responses</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($advisor_rows as $ar): ?>
                    <?php $rc = intval($ar['response_count']); ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($ar['class_name']); ?></strong></td>
                        <td><?php echo $ar['advisor_name'] !== '' ? htmlspecialchars($ar['advisor_name']) : '<span class="protected">No advisor assigned</span>'; ?></td>
                        <?php if ($rc >= MIN_RESPONSE_COUNT): ?>
                            <?php
                            $aavg = floatval($ar['avg_rating']);
                            if ($aavg >= 4.0) {
                                $acolor = '#28a745';
                            } elseif ($aavg >= 3.0) {
                                $acolor = '#f0ad4e';
                            } else {
                                $acolor = '#dc3545';
                            }
                            ?>
                            <td><span class="rating-pill num" style="background: <?php echo $acolor; ?>;"><?php echo number_format($aavg, 2); ?></span></td>
                            <td class="num"><?php echo $rc; ?></td>
                        <?php else: ?>
                            <td colspan="2" class="protected">Hidden &mdash; fewer than <?php echo MIN_RESPONSE_COUNT; ?> responses</td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
