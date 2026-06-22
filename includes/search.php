<?php
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/session.php';
start_secure_session();
if(!isset($_SESSION['user_id'])){http_response_code(401);header('Content-Type:application/json');echo '[]';exit();}

header('Content-Type:application/json');
$role = (int)$_SESSION['role_id'];
$dept = (int)($_SESSION['department_id']??0);
$q = trim($_GET['q']??'');
if(strlen($q)<2||strlen($q)>100){echo '[]';exit();}

$like = '%'.$q.'%';
$results = [];

// Courses — all roles can see their department's courses
if($dept>0){
    $stmt=mysqli_prepare($conn,"SELECT id,course_code,name FROM courses WHERE (course_code LIKE ? OR name LIKE ?) AND department_id=? LIMIT 6");
    mysqli_stmt_bind_param($stmt,"ssi",$like,$like,$dept);
    mysqli_stmt_execute($stmt);
    while($r=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))){
        $url = match($role){
            ROLE_ADMIN   => 'admin/courses/list.php',
            ROLE_HOD     => 'hod/courses/list.php',
            default      => 'admin/courses/list.php',
        };
        $results[]=['type'=>'Course','label'=>htmlspecialchars($r['course_code']),'sub'=>htmlspecialchars($r['name']),'url'=>$url];
    }
    mysqli_stmt_close($stmt);
}

// Students — admin + secretary + HOD
if(in_array($role,[ROLE_ADMIN,ROLE_SECRETARY,ROLE_HOD])){
    $dept_filter = ($role===ROLE_ADMIN) ? '' : ' AND u.department_id=?';
    $stmt=mysqli_prepare($conn,
        "SELECT u.user_id,u.unique_id,u.f_name,u.l_name FROM user_details u
         WHERE u.role_id=? AND (u.unique_id LIKE ? OR u.f_name LIKE ? OR u.l_name LIKE ?)"
        .$dept_filter." LIMIT 6");
    $role_s=ROLE_STUDENT;
    if($role===ROLE_ADMIN){
        mysqli_stmt_bind_param($stmt,"isss",$role_s,$like,$like,$like);
    }else{
        mysqli_stmt_bind_param($stmt,"isssi",$role_s,$like,$like,$like,$dept);
    }
    mysqli_stmt_execute($stmt);
    while($r=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))){
        $url = match($role){
            ROLE_ADMIN     => 'admin/users/list.php',
            ROLE_SECRETARY => 'secretary/students/list.php',
            default        => 'hod/students/list.php',
        };
        $results[]=['type'=>'Student','label'=>htmlspecialchars($r['f_name'].' '.$r['l_name']),'sub'=>htmlspecialchars($r['unique_id']),'url'=>$url];
    }
    mysqli_stmt_close($stmt);
}

// Lecturers — admin + HOD
if(in_array($role,[ROLE_ADMIN,ROLE_HOD])){
    $dept_filter = ($role===ROLE_ADMIN) ? '' : ' AND department_id=?';
    $stmt=mysqli_prepare($conn,
        "SELECT user_id,unique_id,f_name,l_name FROM user_details
         WHERE role_id=? AND (unique_id LIKE ? OR f_name LIKE ? OR l_name LIKE ?)"
        .$dept_filter." LIMIT 5");
    $role_a=ROLE_ADVISOR;
    if($role===ROLE_ADMIN){
        mysqli_stmt_bind_param($stmt,"isss",$role_a,$like,$like,$like);
    }else{
        mysqli_stmt_bind_param($stmt,"isssi",$role_a,$like,$like,$like,$dept);
    }
    mysqli_stmt_execute($stmt);
    while($r=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))){
        $url = ($role===ROLE_ADMIN) ? 'admin/users/list.php' : 'hod/lecturers/list.php';
        $results[]=['type'=>'Lecturer','label'=>htmlspecialchars($r['f_name'].' '.$r['l_name']),'sub'=>htmlspecialchars($r['unique_id']),'url'=>$url];
    }
    mysqli_stmt_close($stmt);
}

echo json_encode(array_slice($results,0,15));
