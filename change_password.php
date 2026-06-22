<?php
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/session.php';
require_once 'includes/csrf.php';

start_secure_session();
check_login();

// Students use their own change-password page
if ($_SESSION['role_id'] === ROLE_STUDENT) {
    header("Location: " . get_base_url() . "/student/profile/change_password.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$forced    = !empty($_SESSION['must_change_password']);
$page_title = 'Change Password';
$errors     = [];

// Role → dashboard map
$dashboards = [
    ROLE_ADMIN     => 'admin/index.php',
    ROLE_HOD       => 'hod/index.php',
    ROLE_SECRETARY => 'secretary/index.php',
    ROLE_ADVISOR   => 'advisor/index.php',
    ROLE_QUALITY   => 'quality/index.php',
];
$dashboard = get_base_url() . '/' . ($dashboards[$_SESSION['role_id']] ?? 'login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) $errors[] = 'Invalid security token.';

    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password']     ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$forced && empty($current_password)) $errors[] = 'Current password is required.';
    if (empty($new_password))                 $errors[] = 'New password is required.';
    if (empty($confirm_password))             $errors[] = 'Please confirm your new password.';

    if (!empty($new_password)) {
        if (strlen($new_password) < PASSWORD_MIN_LENGTH)
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $new_password))
            $errors[] = 'Password must contain at least one uppercase letter.';
        if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $new_password))
            $errors[] = 'Password must contain at least one lowercase letter.';
        if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $new_password))
            $errors[] = 'Password must contain at least one number.';
    }

    if ($new_password !== $confirm_password) $errors[] = 'Passwords do not match.';
    if (!$forced && $current_password === $new_password)
        $errors[] = 'New password must differ from current password.';

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT password FROM user_details WHERE user_id=?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$user) {
            $errors[] = 'Account not found.';
        } else {
            $current_ok = $forced || password_verify($current_password, $user['password']);
            if (!$current_ok) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_up = mysqli_prepare($conn,
                    "UPDATE user_details SET password=?, force_password_change=0 WHERE user_id=?");
                mysqli_stmt_bind_param($stmt_up, "si", $hash, $user_id);
                if (mysqli_stmt_execute($stmt_up)) {
                    unset($_SESSION['must_change_password']);
                    $_SESSION['flash_message'] = 'Password changed successfully!';
                    $_SESSION['flash_type']    = 'success';
                    header("Location: $dashboard");
                    exit();
                } else {
                    $errors[] = 'Error updating password. Please try again.';
                }
                mysqli_stmt_close($stmt_up);
            }
        }
    }
}

require_once 'includes/header.php';
?>
<style>
.pw-container{max-width:560px;margin:40px auto}
.pw-card{background:white;border-radius:12px;padding:40px;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.card-icon{width:72px;height:72px;margin:0 auto 18px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;color:white}
.card-title{text-align:center;font-size:22px;font-weight:600;color:#333;margin-bottom:6px}
.card-subtitle{text-align:center;font-size:13px;color:#888;margin-bottom:24px}
.form-group{margin-bottom:22px}
.form-label{display:block;font-size:14px;font-weight:500;margin-bottom:6px}
.form-label.required::after{content:' *';color:#dc3545}
.form-input{width:100%;padding:11px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;box-sizing:border-box}
.form-input:focus{outline:none;border-color:#667eea}
.btn{width:100%;padding:13px;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:white;margin-bottom:12px}
.btn-secondary{background:white;color:#667eea;border:2px solid #667eea;text-decoration:none;display:block;text-align:center;padding:11px;border-radius:8px;font-size:14px;font-weight:500}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:14px;border-radius:8px;margin-bottom:18px}
.alert-error ul{margin:8px 0 0 18px;padding:0}
.forced-note{background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:12px 14px;border-radius:8px;margin-bottom:18px;font-size:13px}
.pw-strength{height:4px;background:#e0e0e0;border-radius:2px;margin-top:7px;overflow:hidden}
.pw-strength-bar{height:100%;width:0;transition:width .3s,background-color .3s}
.pw-strength-bar.weak{width:33%;background:#dc3545}
.pw-strength-bar.medium{width:66%;background:#ffc107}
.pw-strength-bar.strong{width:100%;background:#28a745}
.form-help{font-size:12px;color:#999;margin-top:4px}
</style>

<div class="pw-container">
<div class="pw-card">
    <div class="card-icon">🔒</div>
    <h1 class="card-title">Change Password</h1>
    <p class="card-subtitle">
        <?php echo htmlspecialchars(ROLE_NAMES[$_SESSION['role_id']] ?? ''); ?> — <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
    </p>

    <?php if ($forced): ?>
    <div class="forced-note"><strong>Action required:</strong> You must set a new password before you can continue.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert-error">
        <strong>⚠️ Please correct the following:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST">
    <?php csrf_token_input(); ?>

    <?php if (!$forced): ?>
    <div class="form-group">
        <label class="form-label required">Current Password</label>
        <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
    </div>
    <?php endif; ?>

    <div class="form-group">
        <label class="form-label required">New Password</label>
        <input type="password" name="new_password" id="new_password" class="form-input" required autocomplete="new-password">
        <div class="pw-strength"><div class="pw-strength-bar" id="strength-bar"></div></div>
        <div class="form-help">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters, upper + lower + number</div>
    </div>

    <div class="form-group">
        <label class="form-label required">Confirm New Password</label>
        <input type="password" name="confirm_password" id="confirm_password" class="form-input" required autocomplete="new-password">
        <div class="form-help" id="match-msg"></div>
    </div>

    <button type="submit" class="btn btn-primary">Change Password</button>
    <?php if (!$forced): ?>
    <a href="<?php echo htmlspecialchars($dashboard); ?>" class="btn-secondary">Cancel</a>
    <?php endif; ?>
    </form>
</div>
</div>

<script>
(function(){
    var np=document.getElementById('new_password');
    var cp=document.getElementById('confirm_password');
    var sb=document.getElementById('strength-bar');
    var mm=document.getElementById('match-msg');
    var minLen=<?php echo PASSWORD_MIN_LENGTH; ?>;
    function updateStrength(){
        var p=np.value, s=0;
        if(p.length>=minLen)s++;
        if(/[A-Z]/.test(p))s++;
        if(/[a-z]/.test(p))s++;
        if(/[0-9]/.test(p))s++;
        sb.className='pw-strength-bar';
        if(s<=1)sb.classList.add('weak');
        else if(s<=2)sb.classList.add('medium');
        else sb.classList.add('strong');
        updateMatch();
    }
    function updateMatch(){
        if(!cp.value){mm.textContent='';return;}
        if(np.value===cp.value){mm.textContent='✓ Passwords match';mm.style.color='#28a745';}
        else{mm.textContent='✗ Passwords do not match';mm.style.color='#dc3545';}
    }
    np.addEventListener('input',updateStrength);
    cp.addEventListener('input',updateMatch);
}());
</script>
<?php require_once 'includes/footer.php'; ?>
