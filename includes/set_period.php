<?php
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';
start_secure_session();
if(!isset($_SESSION['user_id'])||$_SERVER['REQUEST_METHOD']!=='POST'){
    http_response_code(403);exit();
}
if(!validate_csrf_token()){http_response_code(403);exit();}

$key = $_POST['period_key']??'';
if(preg_match('/^(\d+)-(\d+)$/',$key,$m)){
    $year_id = (int)$m[1];
    $sem_id  = (int)$m[2];
    // Verify this period exists
    $stmt=mysqli_prepare($conn,
        "SELECT s.semester_id FROM semesters s JOIN academic_year ay ON s.academic_year_id=ay.academic_year_id
         WHERE ay.academic_year_id=? AND s.semester_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt,"ii",$year_id,$sem_id);
    mysqli_stmt_execute($stmt);
    if(mysqli_stmt_get_result($stmt)->num_rows===1){
        $_SESSION['view_year_id']=$year_id;
        $_SESSION['view_semester_id']=$sem_id;
    }
    mysqli_stmt_close($stmt);
}

$redirect = $_POST['redirect']??'';
// Sanitise: only allow same-origin relative paths
if(!preg_match('/^\/[a-zA-Z0-9_\-\.\/\?=&%]+$/',$redirect)){
    $redirect='/';
}
header("Location: $redirect");
exit();
