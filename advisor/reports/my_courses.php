<?php
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
start_secure_session();
check_login();
if($_SESSION['role_id'] !== ROLE_ADVISOR){
    $_SESSION['flash_message']='Access denied.';
    $_SESSION['flash_type']='error';
    header("Location:../../login.php");
    exit();
}
$lecturer_id = $_SESSION['user_id'];
$page_title = 'My Course Evaluation Results';

// Active period
$result_period=mysqli_query($conn,"SELECT * FROM view_active_period LIMIT 1");
$active_period=mysqli_fetch_assoc($result_period);

// All periods this lecturer has evaluations for
$stmt_periods=mysqli_prepare($conn,
    "SELECT DISTINCT ay.academic_year_id,ay.year_label AS academic_year,s.semester_id,s.semester_name
     FROM evaluation_tokens et
     JOIN course_lecturers cl ON et.course_id=cl.course_id
     JOIN academic_year ay ON et.academic_year_id=ay.academic_year_id
     JOIN semesters s ON et.semester_id=s.semester_id
     WHERE cl.lecturer_user_id=? AND et.is_used=1
     ORDER BY ay.academic_year DESC, s.semester_value DESC");
mysqli_stmt_bind_param($stmt_periods,"i",$lecturer_id);
mysqli_stmt_execute($stmt_periods);
$periods=[];
$res_periods=mysqli_stmt_get_result($stmt_periods);
while($r=mysqli_fetch_assoc($res_periods))$periods[]=$r;
mysqli_stmt_close($stmt_periods);

// Selected period — default to active
$sel_year  = isset($_GET['year_id'])    ? intval($_GET['year_id'])    : ($active_period['academic_year_id']??0);
$sel_sem   = isset($_GET['semester_id'])? intval($_GET['semester_id']): ($active_period['semester_id']??0);

// Per-course evaluation averages
$courses = [];
if($sel_year && $sel_sem){
    $stmt_c=mysqli_prepare($conn,
        "SELECT c.id,c.course_code,c.name,
                COUNT(DISTINCT et.token_id) AS total_tokens,
                SUM(et.is_used) AS used_tokens,
                AVG(CAST(r.response_value AS DECIMAL(10,2))) AS avg_rating,
                COUNT(DISTINCT r.id) AS response_count
         FROM courses c
         JOIN course_lecturers cl ON c.id=cl.course_id AND cl.lecturer_user_id=? AND cl.is_active=1
         LEFT JOIN evaluation_tokens et ON et.course_id=c.id AND et.academic_year_id=? AND et.semester_id=?
         LEFT JOIN evaluations e ON et.token=e.token
         LEFT JOIN responses r ON e.evaluation_id=r.evaluation_id
         GROUP BY c.id
         ORDER BY c.course_code");
    mysqli_stmt_bind_param($stmt_c,"iii",$lecturer_id,$sel_year,$sel_sem);
    mysqli_stmt_execute($stmt_c);
    $res_c=mysqli_stmt_get_result($stmt_c);
    while($r=mysqli_fetch_assoc($res_c)){
        $r['avg_rating'] = ($r['response_count']>=MIN_RESPONSE_COUNT&&$r['avg_rating'])
            ? round($r['avg_rating'],2) : null;
        $r['completion'] = $r['total_tokens']>0
            ? round($r['used_tokens']/$r['total_tokens']*100,1) : 0;
        $courses[]=$r;
    }
    mysqli_stmt_close($stmt_c);

    // Per-question breakdown (aggregate across all my courses for selected period)
    $stmt_q=mysqli_prepare($conn,
        "SELECT eq.question_text,AVG(CAST(r.response_value AS DECIMAL(10,2)))AS avg,COUNT(r.id)AS cnt
         FROM responses r
         JOIN evaluation_questions eq ON r.question_id=eq.question_id
         JOIN evaluations e ON r.evaluation_id=e.evaluation_id
         JOIN evaluation_tokens et ON e.token=et.token
         JOIN course_lecturers cl ON et.course_id=cl.course_id AND cl.lecturer_user_id=?
         WHERE et.academic_year_id=? AND et.semester_id=?
         GROUP BY r.question_id,eq.question_text
         ORDER BY eq.display_order");
    mysqli_stmt_bind_param($stmt_q,"iii",$lecturer_id,$sel_year,$sel_sem);
    mysqli_stmt_execute($stmt_q);
    $res_q=mysqli_stmt_get_result($stmt_q);
    $questions=[];
    while($r=mysqli_fetch_assoc($res_q)){
        $r['avg']=round($r['avg'],2);
        $questions[]=$r;
    }
    mysqli_stmt_close($stmt_q);
}

require_once '../../includes/header.php';
?>
<style>
.results-container{max-width:1000px;margin:0 auto}
.period-nav{background:white;padding:16px 20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.period-nav label{font-size:13px;font-weight:500;color:#555}
.period-nav select{padding:7px 12px;border:1px solid #ddd;border-radius:5px;font-size:13px}
.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:24px}
.sum-card{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);text-align:center}
.sum-val{font-size:36px;font-weight:700;color:#667eea}
.sum-lbl{font-size:13px;color:#666;margin-top:4px}
.course-card{background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:20px;margin-bottom:16px}
.course-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.course-code{font-size:16px;font-weight:700;color:#333}
.course-name{font-size:13px;color:#666}
.rating-pill{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:6px 16px;border-radius:20px;font-size:15px;font-weight:700}
.rating-pending{color:#999;font-size:12px;font-style:italic}
.progress-bar{background:#e9ecef;height:8px;border-radius:4px;overflow:hidden;margin-top:4px}
.progress-fill{height:100%;background:#667eea;border-radius:4px}
.q-row{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #f5f5f5}
.q-row:last-child{border-bottom:none}
.q-text{flex:1;font-size:13px;color:#444}
.q-bar{flex:0 0 120px;background:#e9ecef;height:12px;border-radius:6px;overflow:hidden}
.q-fill{height:100%;border-radius:6px;background:linear-gradient(90deg,#667eea,#764ba2)}
.q-val{flex:0 0 50px;font-size:13px;font-weight:700;color:#667eea;text-align:right}
.anon-note{font-size:12px;color:#999;margin-top:8px;font-style:italic}
</style>
<div class="results-container">
<div class="page-header">
<h1>My Course Evaluation Results</h1>
<p>See how your students rated your courses</p>
</div>

<div class="period-nav">
<label>Period</label>
<form method="GET" style="display:flex;gap:8px;align-items:center">
<select name="year_id" onchange="this.form.submit()">
<option value="0">Select Year</option>
<?php
$seen_years=[];
foreach($periods as $p){
    if(in_array($p['academic_year_id'],$seen_years))continue;
    $seen_years[]=$p['academic_year_id'];
    $sel=$p['academic_year_id']==$sel_year?'selected':'';
    echo "<option value=\"{$p['academic_year_id']}\" $sel>".htmlspecialchars($p['academic_year'])."</option>";
}
?>
</select>
<select name="semester_id" onchange="this.form.submit()">
<option value="0">Select Semester</option>
<?php foreach($periods as $p): ?>
<?php if($p['academic_year_id']!=$sel_year)continue;?>
<option value="<?php echo $p['semester_id'];?>" <?php echo $p['semester_id']==$sel_sem?'selected':'';?>>
    <?php echo htmlspecialchars($p['semester_name']);?>
</option>
<?php endforeach;?>
</select>
</form>
<?php if($active_period&&$sel_year==$active_period['academic_year_id']&&$sel_sem==$active_period['semester_id']): ?>
<span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600">Current Period</span>
<?php endif;?>
</div>

<?php if(empty($courses)): ?>
<div style="text-align:center;padding:60px 20px;background:white;border-radius:8px">
<div style="font-size:60px;opacity:0.3">📊</div>
<h3>No results yet</h3>
<p style="color:#666">No evaluation data for the selected period.</p>
</div>
<?php else:
// Compute summary stats
$total_evals = array_sum(array_column($courses,'used_tokens'));
$rated_courses = array_filter($courses, fn($c)=>$c['avg_rating']!==null);
$overall_avg = count($rated_courses)>0
    ? round(array_sum(array_column(array_values($rated_courses),'avg_rating'))/count($rated_courses),2)
    : null;
?>
<div class="summary-grid">
<div class="sum-card">
<div class="sum-val"><?php echo count($courses);?></div>
<div class="sum-lbl">Courses Assigned</div>
</div>
<div class="sum-card">
<div class="sum-val"><?php echo $total_evals;?></div>
<div class="sum-lbl">Evaluations Received</div>
</div>
<div class="sum-card">
<div class="sum-val"><?php echo $overall_avg??'—';?></div>
<div class="sum-lbl">Overall Average / 5</div>
</div>
</div>

<?php foreach($courses as $c): ?>
<div class="course-card">
<div class="course-header">
<div>
<div class="course-code"><?php echo htmlspecialchars($c['course_code']);?></div>
<div class="course-name"><?php echo htmlspecialchars($c['name']);?></div>
</div>
<?php if($c['avg_rating']!==null): ?>
<div class="rating-pill"><?php echo $c['avg_rating'];?> / 5.0</div>
<?php else: ?>
<div class="rating-pending">
<?php echo $c['used_tokens']>0?'Insufficient responses (min '.MIN_RESPONSE_COUNT.')':'No evaluations yet';?>
</div>
<?php endif;?>
</div>
<div style="font-size:12px;color:#888;margin-bottom:6px">
Completion: <?php echo $c['completion'];?>%
(<?php echo (int)$c['used_tokens'];?> / <?php echo (int)$c['total_tokens'];?> tokens used)
</div>
<div class="progress-bar"><div class="progress-fill" style="width:<?php echo $c['completion'];?>%"></div></div>
</div>
<?php endforeach;?>

<?php if(!empty($questions)): ?>
<div style="background:white;padding:24px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-top:8px">
<h3 style="margin:0 0 16px;color:#333;font-size:16px">Question Breakdown (all your courses)</h3>
<?php foreach($questions as $q): ?>
<div class="q-row">
<div class="q-text"><?php echo htmlspecialchars($q['question_text']);?></div>
<div class="q-bar"><div class="q-fill" style="width:<?php echo min(100,$q['avg']/5*100);?>%"></div></div>
<div class="q-val"><?php echo $q['avg'];?></div>
</div>
<?php endforeach;?>
<p class="anon-note">Results shown only when there are at least <?php echo MIN_RESPONSE_COUNT;?> responses (anonymity protection).</p>
</div>
<?php endif;?>
<?php endif;?>
</div>
<?php require_once '../../includes/footer.php';?>
