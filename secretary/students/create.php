<?php
/**
 * Secretary Create Student
 * Add new student to the department.
 * Role Required: ROLE_SECRETARY
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/csrf.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_SECRETARY) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$department_id = $_SESSION['department_id'];
$page_title = 'Add New Student';
$errors = [];
$success = false;

// Get levels
$levels = [];
$result_levels = mysqli_query($conn, "SELECT * FROM level ORDER BY level_number");
while ($row = mysqli_fetch_assoc($result_levels)) $levels[] = $row;

// Get classes in department
$query_classes = "SELECT * FROM classes WHERE department_id = ? ORDER BY class_name";
$stmt_classes = mysqli_prepare($conn, $query_classes);
mysqli_stmt_bind_param($stmt_classes, "i", $department_id);
mysqli_stmt_execute($stmt_classes);
$result_classes = mysqli_stmt_get_result($stmt_classes);
$classes = [];
while ($row = mysqli_fetch_assoc($result_classes)) $classes[] = $row;
mysqli_stmt_close($stmt_classes);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf_token()) {
        $errors[] = 'Invalid security token.';
    }
    
    $f_name = trim($_POST['f_name'] ?? '');
    $l_name = trim($_POST['l_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $unique_id = trim($_POST['unique_id'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $level_id = intval($_POST['level_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($f_name)) $errors[] = 'First name is required.';
    if (empty($l_name)) $errors[] = 'Last name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if (empty($unique_id)) $errors[] = 'Student ID is required.';
    if (empty($username)) $errors[] = 'Username is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    elseif (strlen($password) < PASSWORD_MIN_LENGTH) $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    if ($level_id == 0) $errors[] = 'Please select a level.';
    if ($class_id == 0) $errors[] = 'Please select a class.';
    
    // Check for duplicates
    if (empty($errors)) {
        $query_check = "SELECT user_id FROM user_details WHERE email = ? OR username = ? OR unique_id = ?";
        $stmt_check = mysqli_prepare($conn, $query_check);
        mysqli_stmt_bind_param($stmt_check, "sss", $email, $username, $unique_id);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) {
            $errors[] = 'Email, username, or student ID already exists.';
        }
        mysqli_stmt_close($stmt_check);
    }
    
    // Insert student
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO user_details (username, password, email, f_name, l_name, unique_id, role_id, department_id, level_id, class_id, is_active, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $query);
        $role = ROLE_STUDENT;
        mysqli_stmt_bind_param($stmt, "ssssssiiii", $username, $password_hash, $email, $f_name, $l_name, $unique_id, $role, $department_id, $level_id, $class_id, $is_active);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = true;
            $_SESSION['flash_message'] = 'Student created successfully!';
            $_SESSION['flash_type'] = 'success';
            header("Location: list.php");
            exit();
        } else {
            $errors[] = 'Error creating student.';
        }
        mysqli_stmt_close($stmt);
    }
}

require_once '../../includes/header.php';
?>

<style>
    .form-container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
    .form-group{margin-bottom:20px}
    .form-label{display:block;font-size:14px;font-weight:500;margin-bottom:5px}
    .form-label.required::after{content:' *';color:#dc3545}
    .form-input,.form-select{width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:5px;font-size:14px}
    .form-input:focus,.form-select:focus{outline:none;border-color:#667eea}
    .form-checkbox{margin-right:8px}
    .btn{padding:12px 30px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;margin-right:10px}
    .btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
    .btn-secondary{background:#6c757d;color:white}
    .alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px}
</style>

<div class="page-header">
    <h1>Add New Student</h1>
    <p>Create a new student record</p>
</div>

<?php if(!empty($errors)): ?>
<div class="alert-error">
    <strong>⚠️ Errors:</strong>
    <ul style="margin:10px 0 0 20px;padding:0">
        <?php foreach($errors as $error): ?>
            <li><?php echo htmlspecialchars($error);?></li>
        <?php endforeach;?>
    </ul>
</div>
<?php endif;?>

<div class="form-container">
    <form method="POST">
        <?php csrf_token_input();?>
        
        <div class="form-group">
            <label class="form-label required">First Name</label>
            <input type="text" name="f_name" class="form-input" value="<?php echo htmlspecialchars($_POST['f_name']??'');?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label required">Last Name</label>
            <input type="text" name="l_name" class="form-input" value="<?php echo htmlspecialchars($_POST['l_name']??'');?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label required">Email</label>
            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($_POST['email']??'');?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label required">Student ID</label>
            <input type="text" name="unique_id" class="form-input" value="<?php echo htmlspecialchars($_POST['unique_id']??'');?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label required">Username</label>
            <input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($_POST['username']??'');?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label required">Password</label>
            <input type="password" name="password" class="form-input" required>
            <small style="color:#666">Minimum <?php echo PASSWORD_MIN_LENGTH;?> characters</small>
        </div>
        
        <div class="form-group">
            <label class="form-label required">Level</label>
            <select name="level_id" class="form-select" required>
                <option value="0">-- Select Level --</option>
                <?php foreach($levels as $level): ?>
                    <option value="<?php echo $level['t_id'];?>" <?php echo(isset($_POST['level_id'])&&$_POST['level_id']==$level['t_id'])?'selected':'';?>>
                        <?php echo htmlspecialchars($level['level_name']);?>
                    </option>
                <?php endforeach;?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label required">Class</label>
            <select name="class_id" class="form-select" required>
                <option value="0">-- Select Class --</option>
                <?php foreach($classes as $class): ?>
                    <option value="<?php echo $class['t_id'];?>" <?php echo(isset($_POST['class_id'])&&$_POST['class_id']==$class['t_id'])?'selected':'';?>>
                        <?php echo htmlspecialchars($class['class_name']);?>
                    </option>
                <?php endforeach;?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="is_active">
                <input type="checkbox" id="is_active" name="is_active" class="form-checkbox" <?php echo(isset($_POST['is_active'])||!isset($_POST['f_name']))?'checked':'';?>>
                <span class="form-label" style="display:inline">Active</span>
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary">Create Student</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once '../../includes/footer.php';?>
