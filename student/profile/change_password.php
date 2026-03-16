<?php

/**
 * Student Change Password
 *
 * Allows students to change their account password.
 *
 * Features:
 * - Current password verification
 * - New password input with confirmation
 * - Password strength validation
 * - Secure password hashing (bcrypt)
 * - CSRF protection
 * - Success/error messages
 *
 * Role Required: ROLE_STUDENT
 */

// Include required files
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';

// Start session and check login
start_secure_session();
check_login();

// Check if user is a student
if ($_SESSION['role_id'] != ROLE_STUDENT) {
    $_SESSION['flash_message'] = 'Access denied. This page is only for students.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

// Get student information
$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Set page title
$page_title = 'Change Password';

// Initialize variables
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token()) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    // Get form inputs
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // Validate inputs
    if (empty($current_password)) {
        $errors[] = 'Current password is required.';
    }

    if (empty($new_password)) {
        $errors[] = 'New password is required.';
    }

    if (empty($confirm_password)) {
        $errors[] = 'Please confirm your new password.';
    }

    // Validate new password strength
    if (!empty($new_password)) {
        if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        }

        if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $new_password)) {
            $errors[] = 'New password must contain at least one uppercase letter.';
        }

        if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $new_password)) {
            $errors[] = 'New password must contain at least one lowercase letter.';
        }

        if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $new_password)) {
            $errors[] = 'New password must contain at least one number.';
        }
    }

    // Validate password confirmation
    if ($new_password !== $confirm_password) {
        $errors[] = 'New password and confirmation do not match.';
    }

    // Validate current password is different from new password
    if ($current_password === $new_password) {
        $errors[] = 'New password must be different from current password.';
    }

    // If no errors, proceed with password change
    if (empty($errors)) {
        // Get current password hash from database
        $query = "SELECT password FROM user_details WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            $errors[] = 'User account not found.';
        } else {
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = 'Current password is incorrect.';
            } else {
                // Hash new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password in database
                $query_update = "UPDATE user_details SET password = ? WHERE user_id = ?";
                $stmt_update = mysqli_prepare($conn, $query_update);
                mysqli_stmt_bind_param($stmt_update, "si", $new_password_hash, $student_id);

                if (mysqli_stmt_execute($stmt_update)) {
                    $_SESSION['flash_message'] = 'Password changed successfully!';
                    $_SESSION['flash_type'] = 'success';
                    header("Location: view.php");
                    exit();
                } else {
                    $errors[] = 'An error occurred while updating your password. Please try again.';
                }

                mysqli_stmt_close($stmt_update);
            }
        }
    }
}

// Set breadcrumb
$breadcrumb = [
    'Dashboard' => '../index.php',
    'My Profile' => 'view.php',
    'Change Password' => ''
];

// Include header
require_once '../../includes/header.php';
?>

<style>
    .password-container {
        max-width: 600px;
        margin: 0 auto;
    }

    .password-card {
        background: white;
        border-radius: 12px;
        padding: 40px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .card-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        color: white;
    }

    .card-title {
        font-size: 24px;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
    }

    .card-subtitle {
        font-size: 14px;
        color: #666;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: #333;
        margin-bottom: 8px;
    }

    .form-label.required::after {
        content: ' *';
        color: #dc3545;
    }

    .form-input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
    }

    .form-input:focus {
        outline: none;
        border-color: #667eea;
    }

    .form-help {
        font-size: 12px;
        color: #999;
        margin-top: 5px;
    }

    .password-strength {
        height: 4px;
        background: #e0e0e0;
        border-radius: 2px;
        margin-top: 8px;
        overflow: hidden;
    }

    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: width 0.3s, background-color 0.3s;
    }

    .password-strength-bar.weak {
        width: 33%;
        background: #dc3545;
    }

    .password-strength-bar.medium {
        width: 66%;
        background: #ffc107;
    }

    .password-strength-bar.strong {
        width: 100%;
        background: #28a745;
    }

    .password-requirements {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
    }

    .password-requirements h4 {
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
    }

    .password-requirements ul {
        margin: 0;
        padding-left: 20px;
        font-size: 13px;
        color: #666;
        line-height: 1.8;
    }

    .requirement-met {
        color: #28a745;
    }

    .btn {
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        width: 100%;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
        display: block;
        text-align: center;
        margin-top: 15px;
    }

    .btn-secondary:hover {
        background: #f8f9ff;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .alert-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    .alert-error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }

    .alert ul {
        margin: 10px 0 0 20px;
        padding: 0;
    }

    @media (max-width: 768px) {
        .password-card {
            padding: 30px 20px;
        }
    }
</style>

<div class="password-container">
    <div class="password-card">
        <!-- Card Header -->
        <div class="card-header">
            <div class="card-icon">🔒</div>
            <h1 class="card-title">Change Password</h1>
            <p class="card-subtitle">Update your account password</p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>✓ Success!</strong><br>
                Your password has been changed successfully. You can now use your new password to log in.
            </div>
            <a href="view.php" class="btn btn-primary">Back to Profile</a>
        <?php else: ?>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Please correct the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Password Change Form -->
            <form method="POST" action="" id="password-form">
                <?php csrf_token_input(); ?>

                <!-- Current Password -->
                <div class="form-group">
                    <label for="current_password" class="form-label required">Current Password</label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        class="form-input"
                        required
                        autocomplete="current-password">
                </div>

                <!-- New Password -->
                <div class="form-group">
                    <label for="new_password" class="form-label required">New Password</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="form-input"
                        required
                        autocomplete="new-password">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strength-bar"></div>
                    </div>
                    <div class="form-help">
                        Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm_password" class="form-label required">Confirm New Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-input"
                        required
                        autocomplete="new-password">
                    <div class="form-help" id="match-message"></div>
                </div>

                <!-- Password Requirements -->
                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <ul id="requirements-list">
                        <li id="req-length">At least <?php echo PASSWORD_MIN_LENGTH; ?> characters</li>
                        <?php if (PASSWORD_REQUIRE_UPPERCASE): ?>
                            <li id="req-uppercase">At least one uppercase letter (A-Z)</li>
                        <?php endif; ?>
                        <?php if (PASSWORD_REQUIRE_LOWERCASE): ?>
                            <li id="req-lowercase">At least one lowercase letter (a-z)</li>
                        <?php endif; ?>
                        <?php if (PASSWORD_REQUIRE_NUMBER): ?>
                            <li id="req-number">At least one number (0-9)</li>
                        <?php endif; ?>
                        <li id="req-match">Passwords must match</li>
                    </ul>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    Change Password
                </button>

                <a href="view.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    // Password strength checker
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const strengthBar = document.getElementById('strength-bar');
    const matchMessage = document.getElementById('match-message');
    const submitBtn = document.getElementById('submit-btn');

    // Requirements elements
    const reqLength = document.getElementById('req-length');
    const reqUppercase = document.getElementById('req-uppercase');
    const reqLowercase = document.getElementById('req-lowercase');
    const reqNumber = document.getElementById('req-number');
    const reqMatch = document.getElementById('req-match');

    function checkPasswordStrength() {
        const password = newPasswordInput.value;
        let strength = 0;

        // Check length
        if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) {
            strength++;
            if (reqLength) reqLength.classList.add('requirement-met');
        } else {
            if (reqLength) reqLength.classList.remove('requirement-met');
        }

        // Check uppercase
        <?php if (PASSWORD_REQUIRE_UPPERCASE): ?>
            if (/[A-Z]/.test(password)) {
                strength++;
                if (reqUppercase) reqUppercase.classList.add('requirement-met');
            } else {
                if (reqUppercase) reqUppercase.classList.remove('requirement-met');
            }
        <?php endif; ?>

        // Check lowercase
        <?php if (PASSWORD_REQUIRE_LOWERCASE): ?>
            if (/[a-z]/.test(password)) {
                strength++;
                if (reqLowercase) reqLowercase.classList.add('requirement-met');
            } else {
                if (reqLowercase) reqLowercase.classList.remove('requirement-met');
            }
        <?php endif; ?>

        // Check number
        <?php if (PASSWORD_REQUIRE_NUMBER): ?>
            if (/[0-9]/.test(password)) {
                strength++;
                if (reqNumber) reqNumber.classList.add('requirement-met');
            } else {
                if (reqNumber) reqNumber.classList.remove('requirement-met');
            }
        <?php endif; ?>

        // Update strength bar
        strengthBar.className = 'password-strength-bar';
        if (strength <= 1) {
            strengthBar.classList.add('weak');
        } else if (strength <= 3) {
            strengthBar.classList.add('medium');
        } else {
            strengthBar.classList.add('strong');
        }

        checkPasswordMatch();
    }

    function checkPasswordMatch() {
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (confirmPassword.length === 0) {
            matchMessage.textContent = '';
            matchMessage.style.color = '';
            if (reqMatch) reqMatch.classList.remove('requirement-met');
            return;
        }

        if (newPassword === confirmPassword) {
            matchMessage.textContent = '✓ Passwords match';
            matchMessage.style.color = '#28a745';
            if (reqMatch) reqMatch.classList.add('requirement-met');
        } else {
            matchMessage.textContent = '✗ Passwords do not match';
            matchMessage.style.color = '#dc3545';
            if (reqMatch) reqMatch.classList.remove('requirement-met');
        }
    }

    // Add event listeners
    newPasswordInput.addEventListener('input', checkPasswordStrength);
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);

    // Form validation
    document.getElementById('password-form').addEventListener('submit', function(e) {
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match. Please check and try again.');
            return false;
        }

        if (newPassword.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
            e.preventDefault();
            alert('Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.');
            return false;
        }
    });
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
