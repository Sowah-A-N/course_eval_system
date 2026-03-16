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

// Determine base URL for links based on current path
$base_url = '';
$current_path = $_SERVER['PHP_SELF'] ?? '';

// Check if we're in a module directory or its subdirectory
if (strpos($current_path, '/admin/') !== false) {
    // Count slashes after /admin/ to determine depth
    $after_admin = substr($current_path, strpos($current_path, '/admin/') + 7);
    $depth = substr_count($after_admin, '/');
    $base_url = str_repeat('../', $depth + 1);
} elseif (strpos($current_path, '/secretary/') !== false) {
    $after_secretary = substr($current_path, strpos($current_path, '/secretary/') + 11);
    $depth = substr_count($after_secretary, '/');
    $base_url = str_repeat('../', $depth + 1);
} elseif (strpos($current_path, '/hod/') !== false) {
    $after_hod = substr($current_path, strpos($current_path, '/hod/') + 5);
    $depth = substr_count($after_hod, '/');
    $base_url = str_repeat('../', $depth + 1);
} elseif (strpos($current_path, '/quality/') !== false) {
    $after_quality = substr($current_path, strpos($current_path, '/quality/') + 9);
    $depth = substr_count($after_quality, '/');
    $base_url = str_repeat('../', $depth + 1);
} elseif (strpos($current_path, '/advisor/') !== false) {
    $after_advisor = substr($current_path, strpos($current_path, '/advisor/') + 9);
    $depth = substr_count($after_advisor, '/');
    $base_url = str_repeat('../', $depth + 1);
} elseif (strpos($current_path, '/student/') !== false) {
    $after_student = substr($current_path, strpos($current_path, '/student/') + 9);
    $depth = substr_count($after_student, '/');
    $base_url = str_repeat('../', $depth + 1);
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

    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>

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
            <link rel="stylesheet" href="<?php echo $base_url . '/' . $css_file; ?>">
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

        /* Responsive */
        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                gap: 15px;
            }

            .nav-menu ul {
                flex-direction: column;
            }

            .nav-menu .dropdown-menu {
                position: static;
                box-shadow: none;
                border-left: 3px solid #667eea;
                padding-left: 20px;
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
            <div>
                <div class="app-name"><?php echo APP_SHORT_NAME; ?></div>
                <div class="institution-name"><?php echo INSTITUTION_SHORT_NAME; ?></div>
            </div>
        </div>
        <div class="top-bar-right">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($user_role_name); ?></div>
            </div>
            <a href="<?php echo $base_url; ?>/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="nav-menu">
        <ul>
            <!-- Dashboard (All Roles) -->
            <li>
                <a href="<?php echo $base_url; ?>/<?php echo strtolower(str_replace(' ', '', $user_role_name)); ?>/index.php"
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
                    <a href="#">Departments ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/admin/departments/list.php">View Departments</a>
                        <a href="<?php echo $base_url; ?>/admin/departments/create.php">Create Department</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">Courses ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/admin/courses/list.php">View Courses</a>
                        <a href="<?php echo $base_url; ?>/admin/courses/create.php">Create Course</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">Questions ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/admin/questions/list.php">View Questions</a>
                        <a href="<?php echo $base_url; ?>/admin/questions/create.php">Create Question</a>
                        <a href="<?php echo $base_url; ?>/admin/questions/list.php">Archived Questions</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="#">Settings ▾</a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $base_url; ?>/admin/academic_years/list.php">Academic Years</a>
                        <a href="<?php echo $base_url; ?>/admin/semesters/index.php">Semesters</a>
                        <a href="<?php echo $base_url; ?>/admin/tokens/generate.php">Generate Tokens</a>
                    </div>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>/admin/reports/index.php">Reports</a>
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
                <li>
                    <a href="<?php echo $base_url; ?>/student/profile/view.php"
                        class="<?php echo $current_page == 'view.php' ? 'active' : ''; ?>">
                        My Profile
                    </a>
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
                    </div>
                </li>

            <?php elseif ($user_role == ROLE_SECRETARY): ?>
                <!-- Secretary Menu -->
                <li>
                    <a href="<?php echo $base_url; ?>/secretary/courses/list.php">Courses</a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>/secretary/students/index.php">Students</a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>/secretary/reports/index.php">Reports</a>
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
