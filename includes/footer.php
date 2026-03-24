<?php

/**
 * Footer Template
 * 
 * This file contains the footer, closing tags, and JavaScript includes.
 * Include this at the bottom of all pages.
 * 
 * Features:
 * - Footer with copyright and links
 * - JavaScript includes
 * - Closing HTML tags
 * 
 * USAGE:
 * require_once 'includes/footer.php';
 */

// Get base URL for links — same depth-based logic as header.php so it works
// correctly for both shallow (admin/index.php) and deep pages (admin/users/list.php).
$base_url = '';
$_fp = $_SERVER['PHP_SELF'] ?? '';
foreach (['admin' => 7, 'secretary' => 11, 'hod' => 5, 'quality' => 9, 'advisor' => 9, 'student' => 9] as $_fm => $_fl) {
    $needle = '/' . $_fm . '/';
    if (strpos($_fp, $needle) !== false) {
        $_fa   = substr($_fp, strpos($_fp, $needle) + strlen($needle));
        $depth = substr_count($_fa, '/');
        $base_url = rtrim(str_repeat('../', $depth + 1), '/');
        break;
    }
}

// Role → folder map (mirrors header.php)
$_footer_role_folders = [
    ROLE_ADMIN     => 'admin',
    ROLE_HOD       => 'hod',
    ROLE_SECRETARY => 'secretary',
    ROLE_ADVISOR   => 'advisor',
    ROLE_STUDENT   => 'student',
    ROLE_QUALITY   => 'quality',
];
$_footer_dashboard_folder = $_footer_role_folders[$_SESSION['role_id'] ?? 0] ?? '';
?>

</div> <!-- End of main-content -->

<!-- Footer -->
<footer style="background: #2c3e50; color: white; padding: 30px 0; margin-top: auto;">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 30px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-bottom: 20px;">

            <!-- About Section -->
            <div>
                <h3 style="margin-bottom: 15px; font-size: 16px;"><?php echo APP_NAME; ?></h3>
                <p style="font-size: 14px; line-height: 1.6; opacity: 0.9;">
                    A comprehensive course evaluation system for <?php echo INSTITUTION_NAME; ?>
                    providing anonymous feedback and detailed reporting.
                </p>
            </div>

            <!-- Quick Links -->
            <div>
                <h3 style="margin-bottom: 15px; font-size: 16px;">Quick Links</h3>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php if (isset($_SESSION['user_id']) && !empty($_footer_dashboard_folder)): ?>
                        <li style="margin-bottom: 8px;">
                            <a href="<?php echo $base_url; ?>/<?php echo $_footer_dashboard_folder; ?>/index.php"
                                style="color: white; text-decoration: none; font-size: 14px; opacity: 0.9;">
                                Dashboard
                            </a>
                        </li>
                    <?php endif; ?>
                    <li style="margin-bottom: 8px;">
                        <a href="<?php echo INSTITUTION_WEBSITE; ?>"
                            target="_blank"
                            style="color: white; text-decoration: none; font-size: 14px; opacity: 0.9;">
                            <?php echo INSTITUTION_SHORT_NAME; ?> Website
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div>
                <h3 style="margin-bottom: 15px; font-size: 16px;">Support</h3>
                <p style="font-size: 14px; line-height: 1.8; opacity: 0.9;">
                    <strong>Email:</strong> <?php echo ADMIN_EMAIL; ?><br>
                    <strong>System Version:</strong> <?php echo APP_VERSION; ?><br>
                    <strong>Institution:</strong> <?php echo INSTITUTION_NAME; ?>
                </p>
            </div>

        </div>

        <!-- Divider -->
        <div style="border-top: 1px solid rgba(255,255,255,0.2); margin: 20px 0;"></div>

        <!-- Copyright -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="font-size: 13px; opacity: 0.8;">
                &copy; <?php echo date('Y'); ?> <?php echo INSTITUTION_NAME; ?>. All rights reserved.
            </div>
            <div style="font-size: 13px; opacity: 0.8;">
                Developed for <?php echo INSTITUTION_SHORT_NAME; ?>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript Files -->
<script src="<?php echo $base_url; ?>/assets/js/main.js"></script>

<?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == ROLE_ADMIN): ?>
    <script src="<?php echo $base_url; ?>/assets/js/admin.js"></script>
<?php endif; ?>

<!-- Additional JavaScript can be added by individual pages -->
<?php if (isset($additional_js)): ?>
    <?php foreach ($additional_js as $js_file): ?>
        <script src="<?php echo $base_url . '/' . $js_file; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Inline JavaScript for page-specific functionality -->
<?php if (isset($inline_js)): ?>
    <script>
        <?php echo $inline_js; ?>
    </script>
<?php endif; ?>

<!-- Session Timeout Warning (Optional) -->
<script>
    // Session timeout warning
    (function() {
        const SESSION_TIMEOUT = <?php echo SESSION_TIMEOUT; ?> * 1000; // Convert to milliseconds
        const WARNING_TIME = 5 * 60 * 1000; // Warn 5 minutes before timeout

        let lastActivity = Date.now();
        let warningShown = false;

        // Update last activity on user interaction
        document.addEventListener('mousemove', resetActivity);
        document.addEventListener('keypress', resetActivity);
        document.addEventListener('click', resetActivity);
        document.addEventListener('scroll', resetActivity);

        function resetActivity() {
            lastActivity = Date.now();
            warningShown = false;
        }

        // Check session timeout every minute
        setInterval(function() {
            const inactiveTime = Date.now() - lastActivity;
            const timeUntilTimeout = SESSION_TIMEOUT - inactiveTime;

            // Show warning if session will expire soon
            if (timeUntilTimeout <= WARNING_TIME && timeUntilTimeout > 0 && !warningShown) {
                warningShown = true;
                const minutes = Math.ceil(timeUntilTimeout / 60000);
                alert('Your session will expire in ' + minutes + ' minute(s) due to inactivity. Please refresh the page to continue.');
            }

            // Redirect to login if session expired
            if (timeUntilTimeout <= 0) {
                window.location.href = '<?php echo $base_url; ?>/logout.php';
            }
        }, 60000); // Check every minute
    })();
</script>

<!-- Global Helper Functions -->
<script>
    // Confirm delete action
    function confirmDelete(message) {
        message = message || 'Are you sure you want to delete this record? This action cannot be undone.';
        return confirm(message);
    }

    // Show loading spinner
    function showLoading() {
        document.body.style.cursor = 'wait';
    }

    // Hide loading spinner
    function hideLoading() {
        document.body.style.cursor = 'default';
    }

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            }, 5000);
        });
    });

    // Form validation helper
    function validateForm(formId) {
        const form = document.getElementById(formId);
        if (!form) return true;

        const required = form.querySelectorAll('[required]');
        let valid = true;

        required.forEach(function(field) {
            if (!field.value.trim()) {
                field.style.borderColor = 'red';
                valid = false;
            } else {
                field.style.borderColor = '';
            }
        });

        if (!valid) {
            alert('Please fill in all required fields.');
        }

        return valid;
    }

    // Print page helper
    function printPage() {
        window.print();
    }

    // Export table to CSV
    function exportTableToCSV(tableId, filename) {
        filename = filename || 'export.csv';
        const table = document.getElementById(tableId);
        if (!table) {
            alert('Table not found');
            return;
        }

        const rows = table.querySelectorAll('tr');
        const csv = [];

        rows.forEach(function(row) {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            cols.forEach(function(col) {
                rowData.push('"' + col.textContent.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });

        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], {
            type: 'text/csv'
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        window.URL.revokeObjectURL(url);
    }
</script>

</body>

</html>