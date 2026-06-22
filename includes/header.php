<?php

/**
 * Header Template
 *
 * This file contains the HTML header, navigation menu, and top bar.
 * Include this at the top of all pages after authentication check.
 *
 * Features:
 * - HTML5 doctype and meta tags
 * - CSS links
 * - Navigation menu (role-based)
 * - User information display
 * - Responsive design
 * - Active page highlighting
 *
 * USAGE:
 * require_once 'includes/header.php';
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// SECURITY RESPONSE HEADERS
// ============================================
// Applied on every authenticated page rendered through this header.
// Must be set before any output is sent.

// Prevent this page from being embedded in a frame on another origin
// (clickjacking defence).
header('X-Frame-Options: DENY');

// Stop browsers from MIME-sniffing the response away from the declared
// Content-Type (prevents drive-by download / XSS via content confusion).
header('X-Content-Type-Options: nosniff');

// Only send the origin (no path or query string) as the Referer header
// to external resources, preventing session tokens or tokens in URLs
// from leaking to third-party servers.
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy — restricts where scripts, styles and other
// resources may be loaded from.
// - default-src 'self'        : only same-origin resources by default.
// - script-src 'self' 'unsafe-inline' : inline <script> blocks are still
//   required because this codebase uses them; 'unsafe-inline' should be
//   removed once scripts are moved to external files (Phase 4 refactor).
// - style-src  'self' 'unsafe-inline' : inline <style> blocks are used
//   throughout; same caveat applies.
// - img-src    'self' data:           : data: URIs are used for icons.
// - object-src 'none'                 : disallow Flash / plugins.
// - base-uri   'self'                 : prevent <base> hijacking.
// - form-action 'self'                : forms may only POST to same origin.
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data:; " .
    "object-src 'none'; " .
    "base-uri 'self'; " .
    "form-action 'self';"
);

// Instruct browsers to always use HTTPS for this origin once visited.
// Only sent in production (HTTPS) so that local HTTP dev is not broken.
if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Disable browser features that are unnecessary for this application.
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// ============================================

// Load constants if not already loaded
if (!defined('APP_NAME')) {
    require_once dirname(__DIR__) . '/config/constants.php';
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Get user information from session
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role_id'] ?? 0;
$user_role_name = ROLE_NAMES[$user_role] ?? 'Unknown';

// Map role_id → folder name (avoids string-munging ROLE_NAMES which produces wrong paths)
$_role_folders = [
    ROLE_ADMIN      => 'admin',
    ROLE_HOD        => 'hod',
    ROLE_SECRETARY  => 'secretary',
    ROLE_ADVISOR    => 'advisor',  // ROLE_LECTURER shares this value
    ROLE_STUDENT    => 'student',
    ROLE_QUALITY    => 'quality',
];
$_dashboard_folder = $_role_folders[$user_role] ?? '';

// Determine base URL for links based on current path.
// str_repeat produces e.g. '../../' — rtrim removes the trailing slash so that
// concatenating with '/path' gives '../../path' (single slash, not double).
// D9: historical period picker — load available periods once per request
$_header_periods = [];
if(isset($_SESSION['user_id'])){
    $_res_hp=mysqli_query($conn??null,
        "SELECT s.semester_id,ay.academic_year_id,ay.year_label AS academic_year,s.semester_name,
                IF(s.is_active=1 AND ay.is_active=1,1,0) AS is_current
         FROM semesters s JOIN academic_year ay ON s.academic_year_id=ay.academic_year_id
         ORDER BY ay.start_year DESC, s.semester_value DESC LIMIT 20");
    if($_res_hp) while($r=mysqli_fetch_assoc($_res_hp))$_header_periods[]=$r;
    // Auto-set to current active period on first visit if nothing is stored
    if(empty($_SESSION['view_year_id'])){
        foreach($_header_periods as $_hp){
            if($_hp['is_current']){
                $_SESSION['view_year_id']=$_hp['academic_year_id'];
                $_SESSION['view_semester_id']=$_hp['semester_id'];
                break;
            }
        }
    }
}
$_view_year = (int)($_SESSION['view_year_id']??0);
$_view_sem  = (int)($_SESSION['view_semester_id']??0);
$_view_label = '';
foreach($_header_periods as $_hp){
    if($_hp['academic_year_id']==$_view_year && $_hp['semester_id']==$_view_sem){
        $_view_label = $_hp['academic_year'].' – '.$_hp['semester_name'];
        break;
    }
}

$base_url = '';
$current_path = $_SERVER['PHP_SELF'] ?? '';

$_modules = ['admin' => 7, 'secretary' => 11, 'hod' => 5, 'quality' => 9, 'advisor' => 9, 'student' => 9];
foreach ($_modules as $_mod => $_len) {
    $needle = '/' . $_mod . '/';
    if (strpos($current_path, $needle) !== false) {
        $_after = substr($current_path, strpos($current_path, $needle) + strlen($needle));
        $depth  = substr_count($_after, '/');
        $base_url = rtrim(str_repeat('../', $depth + 1), '/');
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="<?php echo APP_NAME; ?> - <?php echo INSTITUTION_NAME; ?>">
    <meta name="author" content="<?php echo INSTITUTION_NAME; ?>">

    <title><?php echo isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') . ' - ' : ''; ?><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo $base_url; ?>/assets/images/favicon.ico">

    <!-- CSS Files -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/main.css">

    <?php if ($user_role == ROLE_ADMIN): ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/admin.css">
    <?php elseif ($user_role == ROLE_HOD): ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/hod.css">
    <?php elseif ($user_role == ROLE_STUDENT): ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/student.css">
    <?php endif; ?>

    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/forms.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/tables.css">

    <!-- Additional CSS can be added by individual pages -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($base_url . '/' . $css_file, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <style>
        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Bar */
        .top-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .app-name {
            font-size: 20px;
            font-weight: bold;
        }

        .institution-name {
            font-size: 12px;
            opacity: 0.9;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Navigation Menu */
        .nav-menu {
            background: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 0;
        }

        .nav-menu ul {
            list-style: none;
            display: flex;
            padding: 0;
            margin: 0;
        }

        .nav-menu li {
            position: relative;
        }

        .nav-menu a {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s, color 0.3s;
        }

        .nav-menu a:hover {
            background: #f0f0f0;
            color: #667eea;
        }

        .nav-menu a.active {
            background: #667eea;
            color: white;
        }

        /* Dropdown Menu */
        .nav-menu .dropdown {
            position: relative;
        }

        .nav-menu .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            min-width: 200px;
            z-index: 1000;
        }

        .nav-menu .dropdown:hover .dropdown-menu {
            display: block;
        }

        .nav-menu .dropdown-menu a {
            border-bottom: 1px solid #f0f0f0;
        }

        .nav-menu .dropdown-menu a:last-child {
            border-bottom: none;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 14px;
        }

        /* Breadcrumb */
        .breadcrumb {
            padding: 10px 30px;
            background: #f8f9fa;
            font-size: 13px;
            color: #666;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* D6 — table overflow: every data table scrolls horizontally on small screens
           without breaking the page layout */
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { min-width: 400px; }

        /* D6 — hamburger toggle (hidden on desktop) */
        .nav-toggle {
            display: none;
            background: rgba(255,255,255,.2);
            border: none;
            color: white;
            font-size: 22px;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            line-height: 1;
        }

        /* D3 — inline confirm chip (two-step delete confirmation) */
        .confirm-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff3cd; border: 1px solid #ffc107;
            border-radius: 6px; padding: 4px 10px;
            font-size: 13px; color: #856404;
        }
        .confirm-chip a { color: #c0392b; font-weight: 700; text-decoration: none; }
        .confirm-chip a:hover { text-decoration: underline; }
        .confirm-chip .cancel-confirm { color: #666; cursor: pointer; font-size: 16px; }

        /* Responsive */
        @media (max-width: 768px) {
            .top-bar {
                flex-wrap: wrap;
                gap: 10px;
                padding: 12px 15px;
            }

            .top-bar-left { flex: 1; }

            .nav-toggle { display: block; }

            .nav-menu ul {
                display: none;
                flex-direction: column;
            }

            .nav-menu ul.nav-open {
                display: flex;
            }

            .nav-menu .dropdown-menu {
                position: static;
                box-shadow: none;
                border-left: 3px solid #667eea;
                padding-left: 20px;
                display: block !important; /* always visible when parent is open */
            }

            .main-content {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-left">
        <!-- D6: hamburger visible on mobile only -->
        <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">&#9776;</button>
            <div>
                <div class="app-name"><?php echo APP_SHORT_NAME; ?></div>
                <div class="institution-name"><?php echo INSTITUTION_SHORT_NAME; ?></div>
            </div>
        </div>
        <!-- D9: historical period picker -->
        <?php if(!empty($_header_periods)): ?>
        <form method="POST" action="<?php echo $base_url;?>/includes/set_period.php"
              id="period-picker-form" style="margin:0 8px;display:flex;align-items:center;gap:6px">
            <?php csrf_token_input();?>
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'],ENT_QUOTES,'UTF-8');?>">
            <select name="period_key" onchange="document.getElementById('period-picker-form').submit()"
                    title="Switch viewing period"
                    style="padding:5px 10px;border-radius:16px;border:1px solid rgba(255,255,255,0.5);background:rgba(255,255,255,0.15);color:white;font-size:12px;cursor:pointer;max-width:200px">
                <?php foreach($_header_periods as $_hp): ?>
                <option value="<?php echo $_hp['academic_year_id'].'-'.$_hp['semester_id'];?>"
                        <?php echo ($_hp['academic_year_id']==$_view_year&&$_hp['semester_id']==$_view_sem)?'selected':'';?>>
                    <?php echo htmlspecialchars($_hp['academic_year'].' '.$_hp['semester_name'].($_hp['is_current']?' ★':''));?>
                </option>
                <?php endforeach;?>
            </select>
        </form>
        <?php endif;?>
        <!-- D8: global search -->
        <div style="flex:1;max-width:340px;margin:0 10px;position:relative">
            <input type="text" id="gs-input" autocomplete="off" placeholder="Search students, courses…"
                   aria-label="Global search"
                   style="width:100%;padding:7px 12px;border-radius:20px;border:none;font-size:13px;outline:none;box-sizing:border-box">
            <div id="gs-results" style="display:none;position:absolute;top:calc(100%+4px);left:0;right:0;background:white;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.18);z-index:9999;max-height:340px;overflow-y:auto;font-size:13px"></div>
        </div>
        <div class="top-bar-right">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($user_role_name); ?></div>
            </div>
            <form method="POST" action="<?php echo $base_url; ?>/logout.php"
                  id="logout-form" style="display:inline;margin:0;padding:0">
                <?php csrf_token_input(); ?>
                <button type="submit" class="logout-btn"
                        style="background:none;border:none;cursor:pointer;font:inherit">
                    Logout
                </button>
            </form>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="nav-menu">
        <ul>
            <!-- Dashboard (All Roles) -->
            <li>
                <a href="<?php echo $base_url; ?>/<?php echo $_dashboard_folder; ?>/index.php"
                    class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                    Dashboard
                </a>
            </li>

            <?php if ($user_role == ROLE_ADMIN): ?>
                <!-- Admin Menu -->
                <li class="dropdown">
                    <a href="#">Users ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/admin/users/list.php">View All Users</a>
                        <a href="<?php echo $base_url; ?>/admin/users/create.php">Create User</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">Academics ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/admin/departments/list.php">Departments</a>
                        <a href="<?php echo $base_url; ?>/admin/courses/list.php">Courses</a>
                        <a href="<?php echo $base_url; ?>/admin/classes/list.php">Classes</a>
                        <a href="<?php echo $base_url; ?>/admin/levels/list.php">Levels</a>
                        <a href="<?php echo $base_url; ?>/admin/programmes/list.php">Programmes</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">People ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/admin/advisors/list.php">Advisor Assignments</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">Questions ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/admin/questions/list.php">View Questions</a>
                        <a href="<?php echo $base_url; ?>/admin/questions/create.php">Create Question</a>
                        <a href="<?php echo $base_url; ?>/admin/questions/reorder.php">Reorder Questions</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">Evaluations ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/admin/academic_years/list.php">Academic Years</a>
                        <a href="<?php echo $base_url; ?>/admin/semesters/index.php">Semesters</a>
                        <a href="<?php echo $base_url; ?>/admin/tokens/generate.php">Generate Tokens</a>
                        <a href="<?php echo $base_url; ?>/admin/tokens/view.php">View Tokens</a>
                    </div>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>/admin/reports/index.php">Reports</a>
                </li>
                <li class="dropdown">
                    <a href="#">System ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/admin/settings/index.php">System Settings</a>
                        <a href="<?php echo $base_url; ?>/admin/settings/system_info.php">System Info</a>
                        <a href="<?php echo $base_url; ?>/admin/settings/maintenance.php">Maintenance</a>
                    </div>
                </li>

            <?php elseif ($user_role == ROLE_HOD): ?>
                <!-- HOD Menu -->
                <li class="dropdown">
                    <a href="#">Lecturers ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/hod/lecturers/assign_courses.php">Assign Lecturer</a>
                        <a href="<?php echo $base_url; ?>/hod/lecturers/list.php">View Assignments</a>
                    </div>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>/hod/courses/list.php">Courses</a>
                </li>
                <li class="dropdown">
                    <a href="#">Reports ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/hod/reports/department_report.php">Department Overview</a>
                        <a href="<?php echo $base_url; ?>/hod/reports/course_report.php">Course Reports</a>
                        <a href="<?php echo $base_url; ?>/hod/reports/lecturer_report.php">Lecturer Reports</a>
                        <a href="<?php echo $base_url; ?>/hod/reports/completion_report.php">Completion Report</a>
                    </div>
                </li>

            <?php elseif ($user_role == ROLE_STUDENT): ?>
                <!-- Student Menu -->
                <li>
                    <a href="<?php echo $base_url; ?>/student/evaluate/available_courses.php"
                        class="<?php echo $current_page == 'available_courses.php' ? 'active' : ''; ?>">
                        Evaluate Courses
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>/student/evaluate/history.php"
                        class="<?php echo $current_page == 'history.php' ? 'active' : ''; ?>">
                        Submission History
                    </a>
                </li>
                <li class="dropdown">
                    <a href="#">My Account ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/student/profile/view.php">My Profile</a>
                        <a href="<?php echo $base_url; ?>/student/profile/change_password.php">Change Password</a>
                    </div>
                </li>

            <?php elseif ($user_role == ROLE_QUALITY): ?>
                <!-- Quality Assurance Menu -->
                <li class="dropdown">
                    <a href="#">Reports ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/quality/reports/institution_overview.php">Institution Overview</a>
                        <a href="<?php echo $base_url; ?>/quality/reports/department_comparison.php">Department Comparison</a>
                        <a href="<?php echo $base_url; ?>/quality/reports/course_analysis.php">Course Analysis</a>
                        <a href="<?php echo $base_url; ?>/quality/reports/question_analysis.php">Question Analysis</a>
                        <a href="<?php echo $base_url; ?>/quality/reports/trend_analysis.php">Trend Analysis</a>
                    </div>
                </li>

            <?php elseif ($user_role == ROLE_ADVISOR): ?>
                <!-- Advisor Menu -->
                <li>
                    <a href="<?php echo $base_url; ?>/advisor/students/list.php">My Students</a>
                </li>
                <li class="dropdown">
                    <a href="#">Reports ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/advisor/reports/class_report.php">Class Report</a>
                        <a href="<?php echo $base_url; ?>/advisor/reports/completion_report.php">Completion Report</a>
                        <a href="<?php echo $base_url; ?>/advisor/reports/my_courses.php">My Course Results</a>
                        <a href="<?php echo $base_url; ?>/advisor/reports/advisor_performance.php">My Performance</a>
                    </div>
                </li>

            <?php elseif ($user_role == ROLE_SECRETARY): ?>
                <!-- Secretary Menu -->
                <li class="dropdown">
                    <a href="#">Students ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/secretary/students/list.php">View Students</a>
                        <a href="<?php echo $base_url; ?>/secretary/students/create.php">Add Student</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">Lecturers ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/secretary/lecturers/list.php">View Lecturers</a>
                        <a href="<?php echo $base_url; ?>/secretary/lecturers/create.php">Add Lecturer</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">Courses ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/secretary/courses/list.php">View Courses</a>
                        <a href="<?php echo $base_url; ?>/secretary/courses/create.php">Add Course</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">Classes ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/secretary/classes/list.php">View Classes</a>
                        <a href="<?php echo $base_url; ?>/secretary/classes/create.php">Add Class</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">Reports ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/secretary/reports/index.php">Reports Overview</a>
                        <a href="<?php echo $base_url; ?>/secretary/reports/department_overview.php">Department Overview</a>
                        <a href="<?php echo $base_url; ?>/secretary/reports/evaluation_summary.php">Evaluation Summary</a>
                        <a href="<?php echo $base_url; ?>/secretary/exports/index.php">Export Data</a>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Breadcrumb (Optional - can be enabled per page) -->
    <?php if (isset($breadcrumb) && !empty($breadcrumb)): ?>
        <div class="breadcrumb">
            <?php
            $breadcrumb_items = [];
            foreach ($breadcrumb as $label => $url) {
                if ($url) {
                    $breadcrumb_items[] = '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
                } else {
                    $breadcrumb_items[] = htmlspecialchars($label);
                }
            }
            echo implode(' / ', $breadcrumb_items);
            ?>
        </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <div class="main-content">

        <!-- Display flash messages if any -->
        <?php
        if (function_exists('display_session_message')) {
            display_session_message();
        }
        ?>

    <script>
    // D6: hamburger nav toggle
    (function(){
        var btn = document.getElementById('nav-toggle');
        var ul  = document.querySelector('.nav-menu ul');
        if (!btn || !ul) return;
        btn.addEventListener('click', function(){
            var open = ul.classList.toggle('nav-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }());

    // D8: global search
    (function(){
        var inp = document.getElementById('gs-input');
        var box = document.getElementById('gs-results');
        if (!inp) return;
        var timer, lastQ = '';
        var base = '<?php echo rtrim($base_url,'/');?>';
        inp.addEventListener('input', function(){
            clearTimeout(timer);
            var q = inp.value.trim();
            if (q === lastQ) return;
            lastQ = q;
            if (q.length < 2) { box.style.display='none'; return; }
            timer = setTimeout(function(){
                fetch(base + '/includes/search.php?q=' + encodeURIComponent(q))
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        box.innerHTML = '';
                        if (!data.length) {
                            box.innerHTML = '<div style="padding:12px 14px;color:#999">No results</div>';
                        } else {
                            data.forEach(function(item){
                                var a = document.createElement('a');
                                a.href = base + '/' + item.url;
                                a.style.cssText = 'display:block;padding:10px 14px;color:#333;text-decoration:none;border-bottom:1px solid #f0f0f0';
                                a.innerHTML = '<strong>' + item.label + '</strong>'
                                    + (item.sub ? '<span style="color:#999;margin-left:6px;font-size:12px">' + item.sub + '</span>' : '')
                                    + '<span style="float:right;font-size:11px;color:#aaa">' + item.type + '</span>';
                                a.addEventListener('mouseenter', function(){ this.style.background='#f5f5ff'; });
                                a.addEventListener('mouseleave', function(){ this.style.background=''; });
                                box.appendChild(a);
                            });
                        }
                        box.style.display = 'block';
                    }).catch(function(){ box.style.display='none'; });
            }, 280);
        });
        document.addEventListener('click', function(e){ if (!inp.contains(e.target)) box.style.display='none'; });
        inp.addEventListener('keydown', function(e){ if(e.key==='Escape') box.style.display='none'; });
    }());

    // D3: data-confirm links — first click shows an inline chip; second click follows href
    (function(){
        document.addEventListener('click', function(e){
            var el = e.target.closest('a[data-confirm], button[data-confirm]');
            if (!el) return;
            // If there is already a confirm chip open for this element, let it proceed
            if (el.dataset.confirmPending) return;
            e.preventDefault();
            var msg  = el.getAttribute('data-confirm') || 'Are you sure?';
            var href = el.getAttribute('href') || '#';
            // Build inline chip
            var chip = document.createElement('span');
            chip.className = 'confirm-chip';
            chip.innerHTML = msg + '&nbsp; <a href="' + href + '">Yes, proceed</a>'
                           + '&nbsp;<span class="cancel-confirm" title="Cancel">&times;</span>';
            chip.querySelector('.cancel-confirm').addEventListener('click', function(){
                chip.remove();
                delete el.dataset.confirmPending;
            });
            el.dataset.confirmPending = '1';
            el.insertAdjacentElement('afterend', chip);
            // Auto-cancel after 5 s
            setTimeout(function(){ chip.remove(); delete el.dataset.confirmPending; }, 5000);
        });
    }());
    </script>
