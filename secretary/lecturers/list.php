<?php
/**
 * Secretary Lecturers List
 * View all lecturers in the secretary's department.
 * Role Required: ROLE_SECRETARY
 */

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/session.php';

start_secure_session();
check_login();

if ($_SESSION['role_id'] !== ROLE_SECRETARY) {
    $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../login.php");
    exit();
}

$department_id = $_SESSION['department_id'];
$page_title = 'Manage Lecturers';

// Get filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where = ["u.department_id = ?", "u.role_id = ?"];
$params = [$department_id, ROLE_ADVISOR];
$types = 'ii';

if ($filter_status != 'all') {
    $status_val = $filter_status == 'active' ? 1 : 0;
    $where[] = "u.is_active = ?";
    $params[] = $status_val;
    $types .= 'i';
}
if (!empty($search)) {
    $where[] = "(u.f_name LIKE ? OR u.l_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = implode(' AND ', $where);

$query = "
    SELECT
        u.user_id,
        u.f_name,
        u.l_name,
        u.email,
        u.is_active,
        COUNT(DISTINCT cl.course_id) as course_count
    FROM user_details u
    LEFT JOIN course_lecturers cl ON u.user_id = cl.lecturer_user_id
    WHERE $where_clause
    GROUP BY u.user_id
    ORDER BY u.f_name, u.l_name
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$lecturers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $lecturers[] = $row;
}
mysqli_stmt_close($stmt);

require_once '../../includes/header.php';
?>

<style>
    .top-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px}
    .filters-section{background:white;padding:20px;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
    .filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:15px}
    .filter-group label{font-size:14px;font-weight:500;margin-bottom:5px;display:block}
    .filter-group select,.filter-group input{width:100%;padding:10px;border:1px solid #ddd;border-radius:5px}
    .btn{padding:10px 20px;border:none;border-radius:5px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.3s}
    .btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
    .btn-secondary{background:#6c757d;color:white}
    .btn-success{background:#28a745;color:white}
    .btn-danger{background:#dc3545;color:white}
    .btn-sm{padding:6px 12px;font-size:12px}
    .lecturers-table{background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
    .lecturers-table table{width:100%;border-collapse:collapse}
    .lecturers-table th{background:#f8f9fa;padding:15px;text-align:left;font-weight:600;border-bottom:2px solid #e0e0e0}
    .lecturers-table td{padding:15px;border-bottom:1px solid #f0f0f0}
    .lecturers-table tr:hover{background:#f8f9fa}
    .status-badge{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;text-transform:uppercase}
    .status-active{background:#d4edda;color:#155724}
    .status-inactive{background:#f8d7da;color:#721c24}
    .empty-state{text-align:center;padding:60px 20px;background:white;border-radius:8px}
</style>

<div class="page-header">
    <h1>Manage Lecturers</h1>
    <p>View and manage lecturers in your department</p>
</div>

<div class="top-actions">
    <div>
        <a href="create.php" class="btn btn-primary">+ Add New Lecturer</a>
    </div>
    <div style="color:#666;font-size:14px">
        Total: <strong><?php echo count($lecturers);?></strong> lecturer(s)
    </div>
</div>

<div class="filters-section">
    <form method="GET">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $filter_status=='all'?'selected':'';?>>All</option>
                    <option value="active" <?php echo $filter_status=='active'?'selected':'';?>>Active</option>
                    <option value="inactive" <?php echo $filter_status=='inactive'?'selected':'';?>>Inactive</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name or email..." value="<?php echo htmlspecialchars($search);?>" maxlength="100">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="list.php" class="btn btn-secondary">Reset</a>
        <button type="button" onclick="exportTableToCSV('lecturers-table','lecturers.csv')" class="btn btn-success">Export CSV</button>
    </form>
</div>

<?php if(empty($lecturers)): ?>
    <div class="empty-state">
        <div style="font-size:80px;opacity:0.3">👨‍🏫</div>
        <h3>No Lecturers Found</h3>
        <p style="color:#666">No lecturers match your search criteria.</p>
        <a href="create.php" class="btn btn-primary">Add First Lecturer</a>
    </div>
<?php else: ?>
    <div class="lecturers-table">
        <table id="lecturers-table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Courses Assigned</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($lecturers as $lecturer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($lecturer['f_name'].' '.$lecturer['l_name']);?></td>
                        <td><?php echo htmlspecialchars($lecturer['email']);?></td>
                        <td><?php echo $lecturer['course_count'];?> course(s)</td>
                        <td>
                            <?php if($lecturer['is_active']==1): ?>
                                <span class="status-badge status-active">Active</span>
                            <?php else: ?>
                                <span class="status-badge status-inactive">Inactive</span>
                            <?php endif;?>
                        </td>
                        <td>
                            <a href="edit.php?id=<?php echo $lecturer['user_id'];?>" class="btn btn-primary btn-sm">Edit</a>
                            <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this lecturer?')">
    <input type="hidden" name="id" value="<?php echo $lecturer['user_id'];?>">
    <?php csrf_token_input(); ?>
    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
</form>
                        </td>
                    </tr>
                <?php endforeach;?>
            </tbody>
        </table>
    </div>
<?php endif;?>

<script>
function exportTableToCSV(tableId,filename){
    const table=document.getElementById(tableId);
    let csv=[];
    for(let row of table.rows){
        let csvRow=[];
        for(let cell of row.cells){
            if(row.cells.indexOf(cell)!==row.cells.length-1){
                csvRow.push('"'+cell.innerText.replace(/"/g,'""')+'"');
            }
        }
        csv.push(csvRow.join(','));
    }
    const csvContent=csv.join('\n');
    const blob=new Blob([csvContent],{type:'text/csv'});
    const url=URL.createObjectURL(blob);
    const a=document.createElement('a');
    a.href=url;
    a.download=filename;
    a.click();
}
</script>

<?php require_once '../../includes/footer.php';?>
