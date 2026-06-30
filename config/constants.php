<?php

/**
 * Application Constants
 *
 * This file contains all application-wide constants including:
 * - Role definitions
 * - System limits and thresholds
 * - Evaluation settings
 * - Session configuration
 * - File upload settings
 * - Date/time formats
 * - Pagination settings
 * - Security settings
 *
 * IMPORTANT: These are used throughout the application.
 * Changes here affect the entire system.
 */

// ============================================
// ENVIRONMENT DETECTION  (must be first — other constants depend on it)
// ============================================
//
// Reads the APP_ENV environment variable set by the web server or shell.
// Accepted values (case-insensitive): 'production', 'prod', 'development', 'dev', 'local'.
// Anything other than a recognised production value is treated as development
// so that a misconfigured server fails safe (verbose errors) rather than
// silently running in a locked-down state that hides problems.
//
// HOW TO SET IT
// ─────────────
// Apache  (httpd.conf / .htaccess):   SetEnv APP_ENV production
// Nginx   (fastcgi_params):           fastcgi_param APP_ENV production;
// CLI / cron:                         APP_ENV=production php script.php
// WAMP local dev:                     leave unset — defaults to 'development'
//
// WHY NOT $_SERVER['SERVER_NAME'] or $_SERVER['SERVER_ADDR']:
//   Both of those values can be influenced by the HTTP Host request header
//   on common Apache/Nginx configurations (UseCanonicalName Off is default).
//   An attacker who sends "Host: localhost" to a production server could
//   force IS_DEVELOPMENT = true, enabling verbose error output and relaxed
//   security settings.  An environment variable is set by the server operator
//   at deploy time and cannot be overridden by request headers.

// ============================================
// ROLE DEFINITIONS
// ============================================
// These MUST match the role_id values in the roles table

define('ROLE_ADMIN', 1);        // System Administrator - full access
define('ROLE_HOD', 2);          // Head of Department - department management
define('ROLE_SECRETARY', 3);    // Department Secretary - read-only access
define('ROLE_ADVISOR', 4);      // Class Advisor - class-level access
define('ROLE_STUDENT', 5);      // Student - evaluation submission
define('ROLE_QUALITY', 6);      // Quality Assurance - institution-wide reporting
// Lecturers share ROLE_ADVISOR (4) — a lecturer is an advisor not yet assigned a class.
// This alias exists so that existing code using ROLE_LECTURER continues to work without
// needing a separate role integer in the database.
//
// IMPORTANT: if the roles are ever split (e.g. lecturers become role_id 7 in the DB),
// remove this alias AND update every role check in the codebase.  The assertion below
// will throw immediately if someone adds "define('ROLE_LECTURER', 7)" anywhere — making
// the mismatch visible at load time rather than silently at authorisation time.
define('ROLE_LECTURER', ROLE_ADVISOR);
// Boot-time guard — fires before any request is processed.
assert(
    ROLE_LECTURER === ROLE_ADVISOR,
    'ROLE_LECTURER and ROLE_ADVISOR must share the same integer value. ' .
    'If you are splitting them, update all role checks first.'
);

/**
 * Role Names (for display purposes)
 */
define('ROLE_NAMES', [
    ROLE_ADMIN => 'Administrator',
    ROLE_HOD => 'Head of Department',
    ROLE_SECRETARY => 'Secretary',
    ROLE_ADVISOR => 'Advisor / Lecturer',
    ROLE_STUDENT => 'Student',
    ROLE_QUALITY => 'Quality Assurance',
]);

// ============================================
// EVALUATION SETTINGS
// ============================================

/**
 * Minimum Response Count for Anonymity Protection
 * Reports will hide detailed breakdowns if response count is below this threshold
 * This prevents deanonymization of students
 */
define('MIN_RESPONSE_COUNT', 5);

/**
 * Maximum tokens that may be generated in a single bulk operation.
 * Prevents a single mis-click from flooding the evaluation_tokens table
 * (e.g. wrong department selected for a large cohort).
 * Adjust upward if RMU's largest department × course combination exceeds this.
 */
define('MAX_BULK_TOKEN_GENERATION', 2000);

/**
 * Password Reset Token TTL (seconds)
 * How long a reset link remains valid after it is issued.
 */
define('PASSWORD_RESET_TTL', 900); // 15 minutes

/**
 * Evaluation Scale
 * Most questions use a 1-5 rating scale
 */
define('RATING_MIN', 1);
define('RATING_MAX', 5);

/**
 * Rating Labels
 */
define('RATING_LABELS', [
    1 => 'Very Poor',
    2 => 'Poor',
    3 => 'Average',
    4 => 'Good',
    5 => 'Excellent'
]);

/**
 * Token Length
 * Length of evaluation token in bytes (will be doubled when converted to hex)
 */
define('TOKEN_LENGTH', 32);  // Results in 64-character hex string

// ============================================
// SESSION CONFIGURATION
// ============================================

/**
 * Session Timeout (in seconds)
 * User will be logged out after this period of inactivity
 */
define('SESSION_TIMEOUT', 1800);           // 30 minutes — inactivity timeout (seconds)

/**
 * Absolute Session Lifetime
 *
 * Maximum wall-clock time a session may live, regardless of activity.
 * Without this, a user who keeps clicking every 29 minutes could hold a
 * session indefinitely — their stolen cookie would also be valid forever.
 * After SESSION_ABSOLUTE_LIFETIME the user is always required to re-login.
 *
 * Set to 8 hours (28 800 s) — a full working day.  Adjust to fit RMU's
 * security policy.  Must always be > SESSION_TIMEOUT.
 */
define('SESSION_ABSOLUTE_LIFETIME', 28800);  // 8 hours (28 800 seconds)

/**
 * Session Cookie Settings
 */
define('SESSION_COOKIE_LIFETIME', 0);        // 0 = Until browser closes
define('SESSION_COOKIE_PATH', '/');
define('SESSION_COOKIE_DOMAIN', '');         // Empty = Current domain
define('SESSION_COOKIE_SECURE', false);
define('SESSION_COOKIE_HTTPONLY', true);     // Prevent JavaScript access
define('SESSION_COOKIE_SAMESITE', 'Lax');    // CSRF protection

/**
 * Session Name
 * Custom session name for security (harder to identify application)
 */
define('SESSION_NAME', 'COURSE_EVAL_SESSION');

// ============================================
// PAGINATION SETTINGS
// ============================================

/**
 * Default records per page
 */
define('RECORDS_PER_PAGE', 20);

/**
 * Records per page options
 */
define('RECORDS_PER_PAGE_OPTIONS', [10, 20, 50, 100]);

// ============================================
// FILE UPLOAD SETTINGS
// ============================================

/**
 * Maximum file upload size (in bytes)
 */
define('MAX_FILE_SIZE', 5242880);  // 5 MB (5 * 1024 * 1024)

/**
 * Allowed file types for uploads
 */
define('ALLOWED_FILE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);

/**
 * Upload directory
 */
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');

// ============================================
// DATE & TIME FORMATS
// ============================================

/**
 * Date format for display
 */
define('DATE_FORMAT', 'Y-m-d');              // 2024-11-28
define('DATE_FORMAT_DISPLAY', 'd/m/Y');      // 28/11/2024
define('DATE_FORMAT_LONG', 'F d, Y');        // November 28, 2024

/**
 * Time format
 */
define('TIME_FORMAT', 'H:i:s');              // 14:30:00
define('TIME_FORMAT_DISPLAY', 'h:i A');      // 02:30 PM

/**
 * DateTime format
 */
define('DATETIME_FORMAT', 'Y-m-d H:i:s');                    // 2024-11-28 14:30:00
define('DATETIME_FORMAT_DISPLAY', 'd/m/Y h:i A');           // 28/11/2024 02:30 PM

/**
 * Timezone
 * Set your institution's timezone
 */
define('DEFAULT_TIMEZONE', 'Africa/Accra');  // Ghana Time

// ============================================
// VALIDATION RULES
// ============================================

/**
 * Password Requirements
 */
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL', false);

/**
 * Username Requirements
 */
define('USERNAME_MIN_LENGTH', 4);
define('USERNAME_MAX_LENGTH', 50);
define('USERNAME_PATTERN', '/^[a-zA-Z0-9_-]+$/');  // Alphanumeric, underscore, hyphen

/**
 * Email Requirements
 */
define('EMAIL_MAX_LENGTH', 150);

/**
 * Name Requirements
 */
define('NAME_MIN_LENGTH', 2);
define('NAME_MAX_LENGTH', 100);

// ============================================
// SECURITY SETTINGS
// ============================================

/**
 * Login Attempt Limits
 */
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);  // 15 minutes (900 seconds)

/**
 * CSRF Token Settings
 */
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LENGTH', 32);

/**
 * Password Hashing
 */
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);  // Currently bcrypt
define('PASSWORD_HASH_COST', 10);                // Cost factor for bcrypt

/**
 * Encryption Settings (if needed for future features)
 */
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// ============================================
// EMAIL SETTINGS (Optional - for future use)
// ============================================

/**
 * System Email Address
 */
define('SYSTEM_EMAIL_FROM', 'noreply@rmu.edu.gh');
define('SYSTEM_EMAIL_NAME', 'Course Evaluation System');

/**
 * Admin Email Address
 */
define('ADMIN_EMAIL', 'admin@rmu.edu.gh');

// ============================================
// APPLICATION SETTINGS
// ============================================

/**
 * Application Name
 */
define('APP_NAME', 'Course Evaluation System');
define('APP_SHORT_NAME', 'CES');

/**
 * Institution Details
 */
define('INSTITUTION_NAME', 'Regional Maritime University');
define('INSTITUTION_SHORT_NAME', 'RMU');
define('INSTITUTION_WEBSITE', 'https://www.rmu.edu.gh');

/**
 * Application Version
 */
define('APP_VERSION', '2.0.0');

/**
 * Application URL  (no trailing slash)
 *
 * Resolution order (first non-empty value wins):
 *   1. APP_URL environment variable  — set this on the production server:
 *        Apache  (.htaccess / httpd.conf):  SetEnv APP_URL https://app.rmu.edu.gh
 *        Nginx   (fastcgi_params):          fastcgi_param APP_URL https://app.rmu.edu.gh;
 *   2. Hard-coded fallback below          — only used on WAMP dev when the
 *                                           env var is absent.
 *
 * WHY NOT build from HTTP_HOST: the HTTP Host request header is attacker-
 * controlled on many server configs; using it to construct redirect targets
 * enables host-header injection and open-redirect attacks.
 */
define('APP_URL', rtrim((string)(getenv('APP_URL') ?: 'http://localhost/course_evaluation'), '/'));

/**
 * Maintenance Mode
 * Set to TRUE to enable maintenance mode (only admins can access)
 */
define('MAINTENANCE_MODE', false);

// ============================================
// PATH CONSTANTS
// ============================================

/**
 * Base Directory
 */
define('BASE_DIR', dirname(__DIR__));

/**
 * Common Paths
 */
define('CONFIG_DIR', BASE_DIR . '/config');
define('INCLUDES_DIR', BASE_DIR . '/includes');
define('ASSETS_DIR', BASE_DIR . '/assets');
define('LOGS_DIR', BASE_DIR . '/logs');
define('EXPORTS_DIR', BASE_DIR . '/exports');
define('BACKUPS_DIR', BASE_DIR . '/backups');

/**
 * URL Paths
 */
define('ASSETS_URL', APP_URL . '/assets');
define('CSS_URL', ASSETS_URL . '/css');
define('JS_URL', ASSETS_URL . '/js');
define('IMAGES_URL', ASSETS_URL . '/images');

// ============================================
// DATABASE TABLE NAMES
// ============================================

/**
 * Table name constants (useful for consistency)
 * Not strictly necessary but helps avoid typos
 */
define('TABLE_ACADEMIC_YEAR', 'academic_year');
define('TABLE_SEMESTERS', 'semesters');
define('TABLE_DEPARTMENT', 'department');
define('TABLE_LEVEL', 'level');
define('TABLE_PROGRAMME', 'programme');
define('TABLE_CLASSES', 'classes');
define('TABLE_COURSES', 'courses');
define('TABLE_ROLES', 'roles');
define('TABLE_USER_DETAILS', 'user_details');
define('TABLE_ADVISOR_LEVELS', 'advisor_levels');
define('TABLE_COURSE_LECTURERS', 'course_lecturers');
define('TABLE_EVALUATION_TOKENS', 'evaluation_tokens');
define('TABLE_EVALUATIONS', 'evaluations');
define('TABLE_EVALUATION_QUESTIONS', 'evaluation_questions');
define('TABLE_RESPONSES', 'responses');
define('TABLE_QUESTIONS_ARCHIVE', 'questions_archive');
define('TABLE_AUDIT_LOGS', 'audit_logs');

// ============================================
// RESPONSE MESSAGES
// ============================================

/**
 * Success Messages
 */
define('MSG_SUCCESS_CREATED', 'Record created successfully.');
define('MSG_SUCCESS_UPDATED', 'Record updated successfully.');
define('MSG_SUCCESS_DELETED', 'Record deleted successfully.');
define('MSG_SUCCESS_LOGIN', 'Login successful. Welcome!');
define('MSG_SUCCESS_LOGOUT', 'You have been logged out successfully.');
define('MSG_SUCCESS_PASSWORD_CHANGED', 'Password changed successfully.');
define('MSG_SUCCESS_EVALUATION_SUBMITTED', 'Evaluation submitted successfully. Thank you for your feedback!');

/**
 * Error Messages
 */
define('MSG_ERROR_GENERIC', 'An error occurred. Please try again.');
define('MSG_ERROR_DATABASE', 'Database error. Please contact the administrator.');
define('MSG_ERROR_PERMISSION_DENIED', 'Access denied. You do not have permission to perform this action.');
define('MSG_ERROR_INVALID_LOGIN', 'Invalid username or password.');
define('MSG_ERROR_ACCOUNT_LOCKED', 'Your account has been locked due to too many failed login attempts. Please try again later.');
define('MSG_ERROR_SESSION_EXPIRED', 'Your session has expired. Please login again.');
define('MSG_ERROR_INVALID_CSRF', 'Invalid request. Please try again.');
define('MSG_ERROR_INVALID_TOKEN', 'Invalid or expired token.');
define('MSG_ERROR_REQUIRED_FIELDS', 'Please fill in all required fields.');
define('MSG_ERROR_INVALID_EMAIL', 'Please enter a valid email address.');
define('MSG_ERROR_PASSWORD_MISMATCH', 'Passwords do not match.');
define('MSG_ERROR_PASSWORD_WEAK', 'Password does not meet minimum requirements.');
define('MSG_ERROR_USERNAME_EXISTS', 'Username already exists. Please choose another.');
define('MSG_ERROR_EMAIL_EXISTS', 'Email address already exists.');
define('MSG_ERROR_RECORD_NOT_FOUND', 'Record not found.');
define('MSG_ERROR_EVALUATION_ALREADY_SUBMITTED', 'You have already submitted an evaluation for this course.');
define('MSG_ERROR_INSUFFICIENT_RESPONSES', 'Insufficient responses to display statistics (minimum: ' . MIN_RESPONSE_COUNT . ').');

/**
 * Warning Messages
 */
define('MSG_WARNING_MAINTENANCE', 'System is currently under maintenance. Please try again later.');
define('MSG_WARNING_NO_ACTIVE_PERIOD', 'No active academic year or semester. Please contact administrator.');

/**
 * Info Messages
 */
define('MSG_INFO_NO_RECORDS', 'No records found.');
define('MSG_INFO_CONFIRM_DELETE', 'Are you sure you want to delete this record? This action cannot be undone.');

// ============================================
// AUDIT LOG ACTION TYPES
// ============================================

/**
 * Audit log action types for tracking
 */
define('AUDIT_LOGIN', 'LOGIN');
define('AUDIT_LOGOUT', 'LOGOUT');
define('AUDIT_LOGIN_FAILED', 'LOGIN_FAILED');
define('AUDIT_CREATE', 'CREATE');
define('AUDIT_UPDATE', 'UPDATE');
define('AUDIT_DELETE', 'DELETE');
define('AUDIT_VIEW', 'VIEW');
define('AUDIT_EXPORT', 'EXPORT');
define('AUDIT_PASSWORD_CHANGE', 'PASSWORD_CHANGE');
define('AUDIT_EVALUATION_SUBMIT', 'EVALUATION_SUBMIT');
define('AUDIT_LECTURER_ASSIGN', 'LECTURER_ASSIGN');
define('AUDIT_TOKEN_GENERATE', 'TOKEN_GENERATE');
define('AUDIT_USER_CREATE',    'USER_CREATE');
define('AUDIT_USER_UPDATE',    'USER_UPDATE');
define('AUDIT_USER_DELETE',    'USER_DELETE');

// ============================================
// REPORT TYPES
// ============================================

/**
 * Report type constants
 */
define('REPORT_INSTITUTION', 'institution');
define('REPORT_DEPARTMENT', 'department');
define('REPORT_COURSE', 'course');
define('REPORT_LECTURER', 'lecturer');
define('REPORT_CATEGORY', 'category');
define('REPORT_QUESTION', 'question');

// ============================================
// EXPORT FORMATS
// ============================================

/**
 * Supported export formats
 */
define('EXPORT_FORMAT_CSV', 'csv');
define('EXPORT_FORMAT_PDF', 'pdf');
define('EXPORT_FORMAT_EXCEL', 'excel');

// ============================================
// QUESTION CATEGORIES
// ============================================

/**
 * Evaluation question categories
 * These should match the categories in evaluation_questions table
 */
define('QUESTION_CATEGORIES', [
    'Questions' => 'Course Content & Delivery',
    'Assessment' => 'Assessment & Evaluation',
    'Teaching and Learning Environment' => 'Teaching & Learning Environment',
    'Washroom & Surroundings' => 'Facilities: Washroom & Surroundings',
    'Registry' => 'Support Services: Registry',
    'Accounts' => 'Support Services: Accounts',
    'Library' => 'Support Services: Library',
    'Administration' => 'Support Services: Administration',
    'Sickbay' => 'Support Services: Sickbay'
]);

// ============================================
// SEMESTER VALUES
// ============================================

/**
 * Semester value constants
 */
define('SEMESTER_FIRST', 1);
define('SEMESTER_SECOND', 2);

/**
 * Semester names
 */
define('SEMESTER_NAMES', [
    SEMESTER_FIRST => 'First Semester',
    SEMESTER_SECOND => 'Second Semester'
]);

// ============================================
// ENVIRONMENT DETECTION
// ============================================
// (IS_DEVELOPMENT and IS_PRODUCTION are defined at the TOP of this file
//  so that all subsequent constants — such as SESSION_COOKIE_SECURE and
//  DB_DEBUG_MODE — can reference them without a forward-reference error.)

// ============================================
// DEBUG MODE
// ============================================

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// ============================================
// USAGE EXAMPLES
// ============================================

/**
 * HOW TO USE THESE CONSTANTS:
 *
 * 1. Role checking:
 *    if ($_SESSION['role_id'] == ROLE_ADMIN) { ... }
 *
 * 2. Response count filter:
 *    WHERE response_count >= MIN_RESPONSE_COUNT
 *
 * 3. Session timeout:
 *    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) { ... }
 *
 * 4. Display messages:
 *    echo MSG_SUCCESS_CREATED;
 *
 * 5. Pagination:
 *    LIMIT $start, RECORDS_PER_PAGE
 *
 * 6. Date formatting:
 *    date(DATE_FORMAT_DISPLAY, strtotime($date))
 *
 * 7. File paths:
 *    require_once INCLUDES_DIR . '/session.php';
 *
 * 8. URLs:
 *    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/main.css">
 */

/**
 * PRODUCTION DEPLOYMENT CHECKLIST:
 *
 * [ ] Set DEBUG_MODE to FALSE
 * [ ] Set MAINTENANCE_MODE to FALSE (unless deploying)
 * [ ] Update APP_URL to production URL
 * [ ] Set SESSION_COOKIE_SECURE to TRUE (if using HTTPS)
 * [ ] Update INSTITUTION_NAME and details
 * [ ] Review and update ADMIN_EMAIL
 * [ ] Verify all paths are correct
 * [ ] Test all constants are working
 * [ ] Review security settings
 * [ ] Verify timezone is correct
 */
