<?php
session_start();
include 'db_connect.php';

// Only admins allowed
if (!isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Initialize messages
$message = $error = '';

// ✅ Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_request'], $_POST['new_password'])) {
    $requestId = intval($_POST['reset_request']);
    $newPassword = $_POST['new_password'];
    
    // Validate password strength
    if (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        // Get user ID for this request
        $stmt = $conn->prepare("SELECT UserID FROM password_reset_requests WHERE RequestID = ? AND Status = 'Pending'");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $userId = $row['UserID'];

            // Hash and update
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE Users SET Password_hash = ? WHERE UserID = ?");
            $stmt->bind_param("si", $hash, $userId);
            
            if ($stmt->execute()) {
                // Mark request as completed
                $stmt = $conn->prepare("UPDATE password_reset_requests SET Status='Completed' WHERE RequestID=?");
                $stmt->bind_param("i", $requestId);
                $stmt->execute();
                
                // Log the action to auditlogs
                logSecurityAction($conn, $_SESSION['UserID'], "Password reset for user $userId");
                
                $message = "Password reset successful for UserID: $userId";
            } else {
                $error = "Error updating password: " . $conn->error;
            }
        } else {
            $error = "Invalid or already processed reset request";
        }
    }
}

// ✅ Handle suspend/unsuspend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $userId = intval($_POST['toggle_status']);
    $status = $_POST['current_status'] === 'active' ? 'suspended' : 'active';
    $reason = !empty($_POST['suspend_reason']) ? $_POST['suspend_reason'] : 'No reason provided';

    $stmt = $conn->prepare("UPDATE Users SET Status = ? WHERE UserID = ?");
    $stmt->bind_param("si", $status, $userId);
    
    if ($stmt->execute()) {
        // Log status change to auditlogs
        if ($status === 'suspended') {
            logSecurityAction($conn, $_SESSION['UserID'], "Suspended user $userId. Reason: $reason");
        } else {
            logSecurityAction($conn, $_SESSION['UserID'], "Activated user $userId");
        }
        
        $message = "UserID $userId status changed to $status";
    } else {
        $error = "Error updating user status: " . $conn->error;
    }
}

// ✅ Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $userId = intval($_POST['change_role']);
    $newRole = $_POST['new_role'];
    $allowedRoles = ['Attendee', 'Organizer', 'Admin'];
    
    if (in_array($newRole, $allowedRoles)) {
        $stmt = $conn->prepare("UPDATE Users SET Role = ? WHERE UserID = ?");
        $stmt->bind_param("si", $newRole, $userId);
        
        if ($stmt->execute()) {
            logSecurityAction($conn, $_SESSION['UserID'], "Changed role for user $userId to $newRole");
            $message = "UserID $userId role changed to $newRole";
        } else {
            $error = "Error updating user role: " . $conn->error;
        }
    } else {
        $error = "Invalid role specified";
    }
}

// ✅ Fetch pending reset requests
$resetRequests = $conn->query("
    SELECT r.RequestID, u.UserID, u.Firstname, u.Lastname, u.Email, r.RequestedAt
    FROM password_reset_requests r
    JOIN Users u ON r.UserID = u.UserID
    WHERE r.Status='Pending'
    ORDER BY r.RequestedAt DESC
");

// ✅ Fetch all users
$users = $conn->query("SELECT UserID, Firstname, Lastname, Email, Role, Status, Created_at FROM Users ORDER BY Created_at DESC");

// ✅ Fetch login attempts (using correct column names from your schema)
$loginAttempts = [];
try {
    $res = $conn->query("
        SELECT Email, Attempted_at as AttemptTime, Success, IP_Address 
        FROM login_attempts 
        ORDER BY Attempted_at DESC 
        LIMIT 20
    ");
    if ($res) $loginAttempts = $res->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $loginAttemptsError = "⚠️ Error accessing login attempts: " . $e->getMessage();
}

// ✅ Fetch security logs from auditlogs table
$securityLogs = [];
try {
    $res = $conn->query("
        SELECT UserID as AdminID, Action, Details, Timestamp 
        FROM auditlogs 
        WHERE Action LIKE '%security%' OR Action LIKE '%reset%' OR Action LIKE '%suspend%' OR Action LIKE '%role%'
        ORDER BY Timestamp DESC 
        LIMIT 10
    ");
    if ($res) $securityLogs = $res->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Silently ignore if table doesn't exist or error
}

// ✅ Fetch system statistics
$stats = [];
try {
    // User statistics
    $res = $conn->query("SELECT COUNT(*) as total_users FROM Users");
    $stats['total_users'] = $res->fetch_assoc()['total_users'];
    
    $res = $conn->query("SELECT COUNT(*) as active_users FROM Users WHERE Status = 'active'");
    $stats['active_users'] = $res->fetch_assoc()['active_users'];
    
    $res = $conn->query("SELECT COUNT(*) as suspended_users FROM Users WHERE Status = 'suspended'");
    $stats['suspended_users'] = $res->fetch_assoc()['suspended_users'];
    
    // Pending requests
    $res = $conn->query("SELECT COUNT(*) as pending_requests FROM password_reset_requests WHERE Status = 'Pending'");
    $stats['pending_requests'] = $res->fetch_assoc()['pending_requests'];
} catch (Exception $e) {
    $statsError = "⚠️ Error fetching statistics";
}

// Function to log security actions to auditlogs table
function logSecurityAction($conn, $adminId, $action) {
    try {
        $stmt = $conn->prepare("INSERT INTO auditlogs (UserID, Action, Details) VALUES (?, 'Security Action', ?)");
        $stmt->bind_param("is", $adminId, $action);
        $stmt->execute();
    } catch (Exception $e) {
        // Silently fail if logging fails
        error_log("Failed to log security action: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gray: #95a5a6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f5f6fa; 
            color: #333;
            line-height: 1.6;
        }
        
        header { 
            background: var(--primary); 
            color: #fff; 
            padding: 15px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        header h1 { 
            margin: 0; 
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        nav a { 
            color: #fff; 
            margin: 0 10px; 
            text-decoration: none; 
            font-weight: 500;
            transition: opacity 0.3s;
            padding: 8px 12px;
            border-radius: 4px;
        }
        
        nav a:hover { 
            background: rgba(255,255,255,0.1);
        }
        
        .container { 
            padding: 20px; 
            max-width: 1800px;
            margin: 0 auto;
        }
        
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .stat-card p {
            color: var(--gray);
            font-weight: 500;
        }
        
        .users { background: linear-gradient(45deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .active { background: linear-gradient(45deg, #43e97b 0%, #38f9d7 100%); color: white; }
        .suspended { background: linear-gradient(45deg, #fa709a 0%, #fee140 100%); color: white; }
        .requests { background: linear-gradient(45deg, #fbc2eb 0%, #a6c1ee 100%); }
        
        section { 
            margin-bottom: 40px; 
            background: #fff; 
            padding: 25px; 
            border-radius: 10px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        h2 { 
            margin-top: 0; 
            color: var(--dark);
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px;
            font-size: 0.9rem;
        }
        
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px 15px; 
            text-align: left; 
        }
        
        th { 
            background: var(--secondary); 
            color: #fff; 
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        tr:nth-child(even) { 
            background: #f9f9f9; 
        }
        
        tr:hover {
            background: #f1f1f1;
        }
        
        .btn { 
            padding: 8px 15px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.85rem;
        }
        
        .btn-reset { 
            background: var(--success); 
            color: #fff; 
        }
        
        .btn-reset:hover { 
            background: #27ae60; 
        }
        
        .btn-toggle { 
            background: var(--warning); 
            color: #fff; 
        }
        
        .btn-toggle:hover { 
            background: #d35400; 
        }
        
        .btn-role {
            background: #9b59b6;
            color: #fff;
        }
        
        .btn-role:hover {
            background: #8e44ad;
        }
        
        input[type=password], select { 
            padding: 8px 12px; 
            border: 1px solid #ccc; 
            border-radius: 5px; 
            font-family: inherit;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-item label {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .action-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 992px) {
            table {
                display: block;
                overflow-x: auto;
            }
            
            nav {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 576px) {
            header {
                flex-direction: column;
                gap: 15px;
            }
            
            .container {
                padding: 15px;
            }
            
            section {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<header>
    <h1><i class="fas fa-shield-alt"></i> Security Dashboard</h1>
    <nav>
        <a href="Admin-Dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="Events.php"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="Reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="security.php" style="background: rgba(255,255,255,0.2);"><i class="fas fa-lock"></i> Security</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<div class="container">
    <?php if (!empty($message)) { ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php } ?>
    
    <?php if (!empty($error)) { ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php } ?>

    <!-- Statistics Overview -->
    <div class="stats-grid">
        <div class="stat-card users">
            <i class="fas fa-users"></i>
            <h3><?= $stats['total_users'] ?? 'N/A' ?></h3>
            <p>Total Users</p>
        </div>
        
        <div class="stat-card active">
            <i class="fas fa-user-check"></i>
            <h3><?= $stats['active_users'] ?? 'N/A' ?></h3>
            <p>Active Users</p>
        </div>
        
        <div class="stat-card suspended">
            <i class="fas fa-user-slash"></i>
            <h3><?= $stats['suspended_users'] ?? 'N/A' ?></h3>
            <p>Suspended Users</p>
        </div>
        
        <div class="stat-card requests">
            <i class="fas fa-key"></i>
            <h3><?= $stats['pending_requests'] ?? 'N/A' ?></h3>
            <p>Pending Reset Requests</p>
        </div>
    </div>

    <!-- Password Reset Requests -->
    <section>
        <h2><i class="fas fa-key"></i> Password Reset Requests</h2>
        <?php if ($resetRequests && $resetRequests->num_rows > 0) { ?>
        <table>
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Requested At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resetRequests->fetch_assoc()) { ?>
                <tr>
                    <td><?= $row['RequestID'] ?></td>
                    <td><?= $row['Firstname'] . " " . $row['Lastname'] ?></td>
                    <td><?= $row['Email'] ?></td>
                    <td><?= date('M j, Y g:i A', strtotime($row['RequestedAt'])) ?></td>
                    <td>
                        <form method="POST" class="action-form">
                            <input type="hidden" name="reset_request" value="<?= $row['RequestID'] ?>">
                            <input type="password" name="new_password" placeholder="New Password" required minlength="8">
                            <button type="submit" class="btn btn-reset btn-sm"><i class="fas fa-sync-alt"></i> Reset</button>
                        </form>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } else { echo "<p>No pending reset requests.</p>"; } ?>
    </section>

    <!-- User Management -->
    <section>
        <h2><i class="fas fa-user-cog"></i> User Management</h2>
        
        <div class="filters">
            <div class="filter-item">
                <label for="roleFilter">Filter by Role:</label>
                <select id="roleFilter">
                    <option value="all">All Roles</option>
                    <option value="Admin">Admin</option>
                    <option value="Organizer">Organizer</option>
                    <option value="Attendee">Attendee</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter">
                    <option value="all">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label for="searchUser">Search:</label>
                <input type="text" id="searchUser" placeholder="Search users...">
            </div>
        </div>
        
        <table id="usersTable">
            <thead>
                <tr>
                    <th>UserID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($users) {
                    $users->data_seek(0); // Reset pointer
                    while ($row = $users->fetch_assoc()) { 
                ?>
                <tr>
                    <td><?= $row['UserID'] ?></td>
                    <td><?= $row['Firstname'] . " " . $row['Lastname'] ?></td>
                    <td><?= $row['Email'] ?></td>
                    <td>
                        <span class="badge 
                            <?= $row['Role'] === 'Admin' ? 'badge-danger' : 
                              ($row['Role'] === 'Organizer' ? 'badge-warning' : 'badge-info') ?>">
                            <?= $row['Role'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $row['Status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <?= ucfirst($row['Status']) ?>
                        </span>
                    </td>
                    <td><?= date('M j, Y', strtotime($row['Created_at'])) ?></td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="toggle_status" value="<?= $row['UserID'] ?>">
                                <input type="hidden" name="current_status" value="<?= $row['Status'] ?>">
                                <button type="button" class="btn btn-toggle btn-sm open-suspend-modal" 
                                    data-userid="<?= $row['UserID'] ?>" 
                                    data-status="<?= $row['Status'] ?>"
                                    data-name="<?= $row['Firstname'] . ' ' . $row['Lastname'] ?>">
                                    <i class="fas <?= $row['Status'] === 'active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i> 
                                    <?= $row['Status'] === 'active' ? 'Suspend' : 'Activate' ?>
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="change_role" value="<?= $row['UserID'] ?>">
                                <select name="new_role" onchange="this.form.submit()" style="padding: 5px;">
                                    <option value="Attendee" <?= $row['Role'] === 'Attendee' ? 'selected' : '' ?>>Attendee</option>
                                    <option value="Organizer" <?= $row['Role'] === 'Organizer' ? 'selected' : '' ?>>Organizer</option>
                                    <option value="Admin" <?= $row['Role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php } 
                } else {
                    echo "<tr><td colspan='7'>Error loading users</td></tr>";
                } ?>
            </tbody>
        </table>
    </section>

    <!-- Login Attempts -->
    <section>
        <h2><i class="fas fa-sign-in-alt"></i> Recent Login Attempts</h2>
        <?php if (!empty($loginAttempts)) { ?>
        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loginAttempts as $attempt) { ?>
                <tr>
                    <td><?= $attempt['Email'] ?></td>
                    <td><?= date('M j, Y g:i A', strtotime($attempt['AttemptTime'])) ?></td>
                    <td>
                        <span class="badge <?= $attempt['Success'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $attempt['Success'] ? 'Success' : 'Failed' ?>
                        </span>
                    </td>
                    <td><?= $attempt['IP_Address'] ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } else { echo "<p>No login attempts recorded.</p>"; } ?>
        <?php if (isset($loginAttemptsError)) echo "<p>$loginAttemptsError</p>"; ?>
    </section>

    <!-- Security Logs -->
    <section>
        <h2><i class="fas fa-clipboard-list"></i> Security Logs</h2>
        <?php if (!empty($securityLogs)) { ?>
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>AdminID</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($securityLogs as $log) { ?>
                <tr>
                    <td><?= date('M j, Y g:i A', strtotime($log['Timestamp'])) ?></td>
                    <td><?= $log['AdminID'] ?></td>
                    <td><?= $log['Details'] ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } else { echo "<p>No security logs available.</p>"; } ?>
    </section>
</div>

<!-- Suspend User Modal -->
<div id="suspendModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Suspend User</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" id="suspendForm">
            <input type="hidden" name="toggle_status" id="modalUserId">
            <input type="hidden" name="current_status" id="modalUserStatus">
            
            <div class="form-group">
                <label for="suspendReason">Reason for suspension (optional):</label>
                <textarea name="suspend_reason" id="suspendReason" rows="3" placeholder="Enter reason for suspension"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn" style="background: var(--gray);" id="cancelSuspend">Cancel</button>
                <button type="submit" class="btn btn-toggle">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Table filtering
    document.addEventListener('DOMContentLoaded', function() {
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const searchInput = document.getElementById('searchUser');
        const usersTable = document.getElementById('usersTable');
        const rows = usersTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        function filterTable() {
            const roleValue = roleFilter.value;
            const statusValue = statusFilter.value;
            const searchValue = searchInput.value.toLowerCase();
            
            for (let i = 0; i < rows.length; i++) {
                const roleCell = rows[i].cells[3];
                const statusCell = rows[i].cells[4];
                const nameCell = rows[i].cells[1];
                const emailCell = rows[i].cells[2];
                
                // Extract just the role text from the badge
                const roleText = roleCell.querySelector('.badge') ? 
                                roleCell.querySelector('.badge').textContent.trim() : 
                                roleCell.textContent.trim();
                
                // Extract just the status text from the badge
                const statusText = statusCell.querySelector('.badge') ? 
                                  statusCell.querySelector('.badge').textContent.trim().toLowerCase() : 
                                  statusCell.textContent.trim().toLowerCase();
                
                const roleMatch = roleValue === 'all' || roleText === roleValue;
                const statusMatch = statusValue === 'all' || statusText.includes(statusValue);
                const searchMatch = nameCell.textContent.toLowerCase().includes(searchValue) || 
                                   emailCell.textContent.toLowerCase().includes(searchValue);
                
                rows[i].style.display = roleMatch && statusMatch && searchMatch ? '' : 'none';
            }
        }
        
        roleFilter.addEventListener('change', filterTable);
        statusFilter.addEventListener('change', filterTable);
        searchInput.addEventListener('input', filterTable);
        
        // Modal functionality
        const suspendModal = document.getElementById('suspendModal');
        const suspendButtons = document.querySelectorAll('.open-suspend-modal');
        const closeButtons = document.querySelectorAll('.modal-close');
        const cancelSuspend = document.getElementById('cancelSuspend');
        const modalTitle = document.getElementById('modalTitle');
        
        suspendButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-userid');
                const status = this.getAttribute('data-status');
                const userName = this.getAttribute('data-name');
                
                document.getElementById('modalUserId').value = userId;
                document.getElementById('modalUserStatus').value = status;
                
                if (status === 'active') {
                    modalTitle.textContent = `Suspend User: ${userName}`;
                } else {
                    modalTitle.textContent = `Activate User: ${userName}`;
                }
                
                suspendModal.style.display = 'flex';
            });
        });
        
        function closeModals() {
            suspendModal.style.display = 'none';
        }
        
        closeButtons.forEach(button => {
            button.addEventListener('click', closeModals);
        });
        
        cancelSuspend.addEventListener('click', closeModals);
        
        window.addEventListener('click', function(event) {
            if (event.target === suspendModal) {
                closeModals();
            }
        });
    });
</script>
</body>
</html>