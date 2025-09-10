<?php
session_start();
include 'db_connect.php';

// Ensure only Admins can view logs
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Admin') {
    header("Location: ../HTML/Login.html");
    exit();
}

// Pagination setup
$limit = 50; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filter parameters with proper sanitization
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$action_filter = isset($_GET['action_filter']) ? $conn->real_escape_string($_GET['action_filter']) : '';
$role_filter = isset($_GET['role_filter']) ? $conn->real_escape_string($_GET['role_filter']) : '';
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';

// Build the base query
$query = "
    SELECT 
        a.LogID,
        u.Firstname,
        u.Lastname,
        u.Role,
        a.Action,
        a.Details,
        a.Timestamp
    FROM AuditLogs a
    LEFT JOIN Users u ON a.UserID = u.UserID
    WHERE 1=1
";

// Add search conditions
if (!empty($search)) {
    $query .= " AND (u.Firstname LIKE '%$search%' OR u.Lastname LIKE '%$search%' 
                OR a.Action LIKE '%$search%' OR a.Details LIKE '%$search%')";
}

if (!empty($action_filter)) {
    $query .= " AND a.Action = '$action_filter'";
}

if (!empty($role_filter)) {
    $query .= " AND u.Role = '$role_filter'";
}

if (!empty($date_from)) {
    $query .= " AND DATE(a.Timestamp) >= '$date_from'";
}

if (!empty($date_to)) {
    $query .= " AND DATE(a.Timestamp) <= '$date_to'";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM ($query) as count_table";
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Add sorting, ordering and pagination
$sort = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : 'Timestamp';
$order = isset($_GET['order']) ? $conn->real_escape_string($_GET['order']) : 'DESC';

// Validate sort column to prevent SQL injection
$allowed_sort = ['LogID', 'Firstname', 'Lastname', 'Role', 'Action', 'Details', 'Timestamp'];
if (!in_array($sort, $allowed_sort)) {
    $sort = 'Timestamp';
}

$query .= " ORDER BY $sort $order LIMIT $limit OFFSET $offset";
$logs_result = $conn->query($query);

// Get unique actions and roles for filters
$actions_result = $conn->query("SELECT DISTINCT Action FROM AuditLogs ORDER BY Action");
$roles_result = $conn->query("SELECT DISTINCT Role FROM Users WHERE Role IS NOT NULL ORDER BY Role");

// Prepare filter values for the form
$filters = [
    'search' => htmlspecialchars($search),
    'action_filter' => htmlspecialchars($action_filter),
    'role_filter' => htmlspecialchars($role_filter),
    'date_from' => htmlspecialchars($date_from),
    'date_to' => htmlspecialchars($date_to),
    'sort' => htmlspecialchars($sort),
    'order' => htmlspecialchars($order)
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Logs</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #4361ee;
    --secondary: #3f37c9;
    --success: #4cc9f0;
    --light: #f5f7fb;
    --dark: #2b2d42;
    --gray: #8d99ae;
    --danger: #e63946;
    --warning: #fca311;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f0f2f5;
    color: #333;
    line-height: 1.6;
    padding: 20px;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    padding: 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

h1 {
    color: var(--primary);
    font-size: 28px;
}

.controls {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--secondary);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    opacity: 0.9;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    opacity: 0.9;
}

.filters {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    margin-bottom: 5px;
    font-weight: 600;
    color: var(--dark);
}

.filter-group input,
.filter-group select {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.filter-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.table-container {
    overflow-x: auto;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

table th, table td {
    padding: 12px 15px;
    text-align: left;
}

table thead {
    background: var(--primary);
    color: white;
}

table th {
    font-weight: 600;
    cursor: pointer;
    position: relative;
}

table th:hover {
    background: var(--secondary);
}

table th i {
    margin-left: 5px;
    font-size: 12px;
}

table tbody tr {
    border-bottom: 1px solid #eee;
}

table tbody tr:nth-child(even) {
    background: #f9f9f9;
}

table tbody tr:hover {
    background: #f1f5ff;
}

.pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
    gap: 5px;
}

.pagination a, .pagination span {
    padding: 8px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    text-decoration: none;
    color: var(--primary);
    font-weight: 600;
}

.pagination a:hover {
    background: var(--primary);
    color: white;
}

.pagination .current {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .disabled {
    color: #ccc;
    pointer-events: none;
}

.export-options {
    display: none;
    position: absolute;
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    z-index: 100;
    right: 0;
    top: 40px;
}

.export-options.show {
    display: block;
}

.export-options a {
    display: block;
    padding: 8px 12px;
    color: #333;
    text-decoration: none;
    border-radius: 4px;
    white-space: nowrap;
}

.export-options a:hover {
    background: #f0f2f5;
}

.no-results {
    text-align: center;
    padding: 30px;
    color: var(--gray);
    font-style: italic;
}

.export-container {
    position: relative;
}

@media (max-width: 768px) {
    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .btn {
        padding: 8px 12px;
        font-size: 14px;
    }
    
    .controls {
        flex-wrap: wrap;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-clipboard-list"></i> Audit Logs</h1>
        <div class="controls">
            <button class="btn btn-primary" onclick="resetFilters()">
                <i class="fas fa-sync"></i> Reset Filters
            </button>
            <div class="export-container">
                <button class="btn btn-success" onclick="toggleExportOptions()">
                    <i class="fas fa-download"></i> Export
                </button>
                <div id="exportOptions" class="export-options">
                    <a href="#" onclick="exportLogs('csv')"><i class="fas fa-file-csv"></i> Export as CSV</a>
                    <a href="#" onclick="exportLogs('json')"><i class="fas fa-file-code"></i> Export as JSON</a>
                </div>
            </div>
        </div>
    </div>

    <div class="filters">
        <form method="GET" action="">
            <input type="hidden" name="sort" value="<?php echo $filters['sort']; ?>">
            <input type="hidden" name="order" value="<?php echo $filters['order']; ?>">
            
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" placeholder="Search logs..." value="<?php echo $filters['search']; ?>">
                </div>
                <div class="filter-group">
                    <label for="action_filter">Action</label>
                    <select id="action_filter" name="action_filter">
                        <option value="">All Actions</option>
                        <?php 
                        if ($actions_result && $actions_result->num_rows > 0) {
                            while ($action = $actions_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $action['Action']; ?>" <?php echo $filters['action_filter'] === $action['Action'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($action['Action']); ?>
                            </option>
                        <?php 
                            endwhile; 
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="role_filter">Role</label>
                    <select id="role_filter" name="role_filter">
                        <option value="">All Roles</option>
                        <?php 
                        if ($roles_result && $roles_result->num_rows > 0) {
                            while ($role = $roles_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $role['Role']; ?>" <?php echo $filters['role_filter'] === $role['Role'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['Role']); ?>
                            </option>
                        <?php 
                            endwhile; 
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $filters['date_from']; ?>">
                </div>
                <div class="filter-group">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $filters['date_to']; ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <button type="button" class="btn btn-danger" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>'">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table id="logsTable">
            <thead>
                <tr>
                    <th onclick="sortTable('LogID')">Log ID <?php echo $filters['sort'] === 'LogID' ? ($filters['order'] === 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>') : ''; ?></th>
                    <th onclick="sortTable('Firstname')">User <?php echo $filters['sort'] === 'Firstname' ? ($filters['order'] === 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>') : ''; ?></th>
                    <th onclick="sortTable('Role')">Role <?php echo $filters['sort'] === 'Role' ? ($filters['order'] === 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>') : ''; ?></th>
                    <th onclick="sortTable('Action')">Action <?php echo $filters['sort'] === 'Action' ? ($filters['order'] === 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>') : ''; ?></th>
                    <th>Details</th>
                    <th onclick="sortTable('Timestamp')">Timestamp <?php echo $filters['sort'] === 'Timestamp' ? ($filters['order'] === 'ASC' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>') : ''; ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                <?php while ($log = $logs_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $log['LogID'] ?></td>
                        <td><?= $log['Firstname'] ? htmlspecialchars($log['Firstname'] . ' ' . $log['Lastname']) : 'System' ?></td>
                        <td><?= $log['Role'] ?? 'N/A' ?></td>
                        <td><?= htmlspecialchars($log['Action']) ?></td>
                        <td><?= htmlspecialchars($log['Details']) ?></td>
                        <td><?= date('M j, Y H:i:s', strtotime($log['Timestamp'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="no-results">
                        <i class="fas fa-info-circle"></i> No audit logs found matching your criteria.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?php echo generatePageLink($page - 1, $filters); ?>">&laquo; Previous</a>
        <?php else: ?>
            <span class="disabled">&laquo; Previous</span>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="<?php echo generatePageLink($i, $filters); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="<?php echo generatePageLink($page + 1, $filters); ?>">Next &raquo;</a>
        <?php else: ?>
            <span class="disabled">Next &raquo;</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Toggle export options dropdown
function toggleExportOptions() {
    const exportOptions = document.getElementById('exportOptions');
    exportOptions.classList.toggle('show');
}

// Close export dropdown when clicking elsewhere
document.addEventListener('click', function(event) {
    const exportOptions = document.getElementById('exportOptions');
    const exportButton = document.querySelector('.btn-success');
    
    if (!exportOptions.contains(event.target) && event.target !== exportButton && !exportButton.contains(event.target)) {
        exportOptions.classList.remove('show');
    }
});

// Sort table function
function sortTable(column) {
    const url = new URL(window.location.href);
    const currentSort = url.searchParams.get('sort');
    const currentOrder = url.searchParams.get('order');
    
    let newOrder = 'DESC';
    if (currentSort === column) {
        newOrder = currentOrder === 'DESC' ? 'ASC' : 'DESC';
    }
    
    url.searchParams.set('sort', column);
    url.searchParams.set('order', newOrder);
    window.location.href = url.toString();
}

// Reset all filters
function resetFilters() {
    window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>';
}

// Export logs function
function exportLogs(format) {
    const url = new URL(window.location.href);
    url.searchParams.set('export', format);
    window.location.href = url.toString();
}

// Client-side search functionality
document.getElementById('search').addEventListener('keyup', function () {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#logsTable tbody tr');
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php
// Function to generate pagination links with filters
function generatePageLink($page, $filters) {
    $params = [
        'page' => $page,
        'search' => $filters['search'],
        'action_filter' => $filters['action_filter'],
        'role_filter' => $filters['role_filter'],
        'date_from' => $filters['date_from'],
        'date_to' => $filters['date_to'],
        'sort' => $filters['sort'],
        'order' => $filters['order']
    ];
    
    return $_SERVER['PHP_SELF'] . '?' . http_build_query($params);
}

// Handle export functionality
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    
    // Rebuild the query without LIMIT for export
    $export_query = "
        SELECT 
            a.LogID,
            u.Firstname,
            u.Lastname,
            u.Role,
            a.Action,
            a.Details,
            a.Timestamp
        FROM AuditLogs a
        LEFT JOIN Users u ON a.UserID = u.UserID
        WHERE 1=1
    ";
    
    // Add the same filters as the main query
    if (!empty($search)) {
        $export_query .= " AND (u.Firstname LIKE '%$search%' OR u.Lastname LIKE '%$search%' 
                    OR a.Action LIKE '%$search%' OR a.Details LIKE '%$search%')";
    }

    if (!empty($action_filter)) {
        $export_query .= " AND a.Action = '$action_filter'";
    }

    if (!empty($role_filter)) {
        $export_query .= " AND u.Role = '$role_filter'";
    }

    if (!empty($date_from)) {
        $export_query .= " AND DATE(a.Timestamp) >= '$date_from'";
    }

    if (!empty($date_to)) {
        $export_query .= " AND DATE(a.Timestamp) <= '$date_to'";
    }
    
    $export_query .= " ORDER BY $sort $order";
    $export_result = $conn->query($export_query);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=audit_logs_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('LogID', 'User', 'Role', 'Action', 'Details', 'Timestamp'));
        
        while ($row = $export_result->fetch_assoc()) {
            $user = $row['Firstname'] ? $row['Firstname'] . ' ' . $row['Lastname'] : 'System';
            fputcsv($output, array(
                $row['LogID'],
                $user,
                $row['Role'] ?? 'N/A',
                $row['Action'],
                $row['Details'],
                $row['Timestamp']
            ));
        }
        
        fclose($output);
        exit();
    } elseif ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=audit_logs_' . date('Y-m-d') . '.json');
        
        $logs = array();
        while ($row = $export_result->fetch_assoc()) {
            $user = $row['Firstname'] ? $row['Firstname'] . ' ' . $row['Lastname'] : 'System';
            $logs[] = array(
                'LogID' => $row['LogID'],
                'User' => $user,
                'Role' => $row['Role'] ?? 'N/A',
                'Action' => $row['Action'],
                'Details' => $row['Details'],
                'Timestamp' => $row['Timestamp']
            );
        }
        
        echo json_encode($logs, JSON_PRETTY_PRINT);
        exit();
    }
}
?>
</body>
</html>