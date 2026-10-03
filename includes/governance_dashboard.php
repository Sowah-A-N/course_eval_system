<?php
/**
 * Shared institution-wide dashboard for Provost / Vice-Chancellor.
 * The including page sets $page_title and $role_label and has already done the
 * role check. Both roles see the whole institution and link to the full reports.
 */
if (!defined('ROLE_PROVOST')) { exit(); }

$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
    (SELECT COUNT(*) FROM department) AS departments,
    (SELECT COUNT(*) FROM courses) AS courses,
    (SELECT COUNT(*) FROM user_details WHERE role_id = " . ROLE_STUDENT . " AND is_active = 1) AS students,
    (SELECT COUNT(*) FROM evaluations WHERE scope = 'course') AS course_evals,
    (SELECT COUNT(*) FROM evaluations WHERE scope = 'administrative') AS admin_evals"));

$avg = mysqli_fetch_row(mysqli_query($conn, "SELECT ROUND(AVG(CAST(response_value AS DECIMAL(10,2))),2) FROM responses r JOIN evaluations e ON r.evaluation_id = e.evaluation_id WHERE e.scope = 'course'"))[0];

require __DIR__ . '/header.php';
?>
<style>
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:26px}
.stat-card{background:#fff;padding:22px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);text-align:center}
.stat-value{font-size:34px;font-weight:700;color:#667eea}
.stat-label{font-size:13px;color:#666;margin-top:6px}
.reports-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px}
.report-card{background:#fff;border-radius:10px;padding:22px;box-shadow:0 2px 8px rgba(0,0,0,.08);border-left:5px solid #667eea;text-decoration:none;color:inherit;display:block;transition:transform .2s}
.report-card:hover{transform:translateY(-4px)}
.report-title{font-size:17px;font-weight:700;color:#333;margin-bottom:6px}
.report-desc{font-size:13px;color:#666}
</style>
<div class="page-header">
    <h1><?php echo htmlspecialchars($role_label); ?> Dashboard</h1>
    <p>Institution-wide view of teaching-and-learning evaluations.</p>
</div>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['students']); ?></div><div class="stat-label">Active Students</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['departments']); ?></div><div class="stat-label">Departments</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['courses']); ?></div><div class="stat-label">Courses</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['course_evals']); ?></div><div class="stat-label">Course Evaluations</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo $avg !== null ? $avg : '—'; ?></div><div class="stat-label">Avg Course Rating / 5</div></div>
</div>
<h2 style="font-size:18px;color:#333;margin:0 0 14px">Reports</h2>
<div class="reports-grid">
    <a class="report-card" href="<?php echo $base_url; ?>/quality/reports/institution_overview.php"><div class="report-title">Institution Overview</div><div class="report-desc">Overall completion and category performance.</div></a>
    <a class="report-card" href="<?php echo $base_url; ?>/quality/reports/department_comparison.php"><div class="report-title">Department Comparison</div><div class="report-desc">Compare departments side by side.</div></a>
    <a class="report-card" href="<?php echo $base_url; ?>/quality/reports/institution_services.php"><div class="report-title">Institutional Services</div><div class="report-desc">Central services and class-advisor ratings.</div></a>
    <a class="report-card" href="<?php echo $base_url; ?>/quality/reports/trend_analysis.php"><div class="report-title">Trends</div><div class="report-desc">Results over time across semesters.</div></a>
</div>
<?php require __DIR__ . '/footer.php'; ?>
