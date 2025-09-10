<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is Admin
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Admin') {
    header("Location: Login.html");
    exit();
}

// Fetch dashboard statistics
// Total Users
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'] ?? 0;

// Total Registrations
$totalRegistrations = $conn->query("SELECT COUNT(*) as total FROM registrations")->fetch_assoc()['total'] ?? 0;

// Pending Registrations
$pendingRegistrations = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE Status='Pending'")->fetch_assoc()['total'] ?? 0;

// Total Events
$totalEvents = $conn->query("SELECT COUNT(DISTINCT EventID) as total FROM registrations")->fetch_assoc()['total'] ?? 0;

// Safe session values
$firstName = isset($_SESSION['Firstname']) ? $_SESSION['Firstname'] : 'Admin';
$lastName = isset($_SESSION['Lastname']) ? $_SESSION['Lastname'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Event Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ==== Styling retained from previous dashboard ==== */
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f72585;
            --danger: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f7fb; color: #333; line-height: 1.6; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: var(--dark); color: white; transition: all 0.3s ease; }
        .sidebar-header { padding: 20px; background: var(--secondary); display: flex; align-items: center; }
        .sidebar-header h2 { font-size: 1.5rem; margin-left: 10px; }
        .sidebar-menu { padding: 10px 0; }
        .sidebar-menu ul { list-style: none; }
        .sidebar-menu li { padding: 12px 20px; border-left: 4px solid transparent; transition: all 0.3s; }
        .sidebar-menu li:hover, .sidebar-menu li.active { background: rgba(255,255,255,0.1); border-left: 4px solid var(--info); }
        .sidebar-menu a { color: white; text-decoration: none; display: flex; align-items: center; }
        .sidebar-menu i { margin-right: 10px; font-size: 1.1rem; }

        .main-content { flex: 1; display: flex; flex-direction: column; }
        .header { background: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .search-box { display: flex; align-items: center; background: var(--light); border-radius: 4px; padding: 8px 15px; width: 300px; }
        .search-box input { border: none; background: transparent; outline: none; margin-left: 10px; width: 100%; }
        .user-actions { display: flex; align-items: center; }
        .notification { position: relative; margin-right: 20px; cursor: pointer; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.7rem; display: flex; justify-content: center; align-items: center; }
        .user-profile { display: flex; align-items: center; cursor: pointer; }
        .user-profile img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; object-fit: cover; }

        .content { padding: 30px; flex: 1; overflow-y: auto; }
        .dashboard-title { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }

        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); display: flex; flex-direction: column; align-items: center; }
        .stat-card-icon { width: 50px; height: 50px; border-radius: 8px; display: flex; justify-content: center; align-items: center; font-size: 1.5rem; color: white; margin-bottom: 10px; }
        .bg-primary { background: var(--primary); }
        .bg-success { background: var(--success); }
        .bg-warning { background: var(--warning); }
        .bg-info { background: var(--info); }
        .stat-card-value { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
        .stat-card-title { color: var(--gray); font-size: 0.9rem; text-align: center; }

        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--light-gray); }
        .card-title { font-size: 1.2rem; font-weight: 600; }
        .action-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px,1fr)); gap: 20px; }
        .action-item { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s; cursor: pointer; }
        .action-item:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .action-icon { font-size: 2rem; margin-bottom: 15px; color: var(--primary); }
        .action-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 10px; }
        .action-description { color: var(--gray); font-size: 0.9rem; margin-bottom: 15px; }
        .action-btn { background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 0.9rem; transition: background 0.3s; text-decoration: none; display: inline-block; text-align: center; }
        .action-btn:hover { background: var(--secondary); }

        .footer { background: white; padding: 15px 30px; text-align: center; color: var(--gray); font-size: 0.9rem; border-top: 1px solid var(--light-gray); }

        @media (max-width: 992px) { .sidebar { width: 70px; } .sidebar-header h2, .sidebar-menu span { display: none; } .sidebar-menu li { text-align: center; padding: 15px 10px; } .sidebar-menu i { margin-right: 0; font-size: 1.3rem; } .search-box { width: 200px; } }
        @media (max-width: 768px) { .stats-container, .action-grid { grid-template-columns: 1fr; } .search-box { display: none; } }
    </style>
</head>
<body>
<div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-calendar-alt fa-2x"></i>
            <h2>EventManager</h2>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li class="active"><a href="#"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="Events.php"><i class="fas fa-calendar"></i> <span>Events</span></a></li>
                <li><a href="Reports.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
                <li><a href="Security.php"><i class="fas fa-shield-alt"></i> <span>Security</span></a></li>
                <li><a href="Backups.php"><i class="fas fa-database"></i> <span>Backups</span></a></li>
                <li><a href="AuditLogs.php"><i class="fas fa-history"></i> <span>Audit Logs</span></a></li>
                <li><a href="Settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search..." />
            </div>
            <div class="user-actions">
                <div class="notification" onclick="alert('You have <?php echo $pendingRegistrations; ?> pending registrations!')">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $pendingRegistrations; ?></span>
                </div>
                <div class="user-profile">
                    <img src="../images/admin-avatar.JPG" alt="Admin">
                    <span><?php echo $firstName; ?></span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="dashboard-title">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?php echo $firstName; ?>! Today is <span id="current-date"></span></p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-card-icon bg-primary"><i class="fas fa-users"></i></div>
                    <div class="stat-card-value"><?php echo $totalUsers; ?></div>
                    <div class="stat-card-title">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon bg-success"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-card-value"><?php echo $totalRegistrations; ?></div>
                    <div class="stat-card-title">Total Registrations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon bg-warning"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-card-value"><?php echo $pendingRegistrations; ?></div>
                    <div class="stat-card-title">Pending Registrations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon bg-info"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-card-value"><?php echo $totalEvents; ?></div>
                    <div class="stat-card-title">Total Events</div>
                </div>
            </div>

            <!-- Admin Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Administrative Actions</h2>
                </div>
                <div class="action-grid">
                    <div class="action-item">
                        <div class="action-icon"><i class="fas fa-calendar"></i></div>
                        <h3 class="action-title">Event Monitoring</h3>
                        <p class="action-description">Monitor all events, registrations, and pending approvals.</p>
                        <a href="Events.php" class="action-btn">View Events</a>
                    </div>
                    <div class="action-item">
                        <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                        <h3 class="action-title">Reports</h3>
                        <p class="action-description">Generate system and user reports, including registrations and payments.</p>
                        <a href="Reports.php" class="action-btn">Generate Reports</a>
                    </div>
                    <div class="action-item">
                        <div class="action-icon"><i class="fas fa-shield-alt"></i></div>
                        <h3 class="action-title">Security</h3>
                        <p class="action-description">Monitor suspended users, failed logins, and run security checks.</p>
                        <a href="Security.php" class="action-btn">Check Security</a>
                    </div>
                    <div class="action-item">
                        <div class="action-icon"><i class="fas fa-database"></i></div>
                        <h3 class="action-title">Backups</h3>
                        <p class="action-description">Initiate backups and view backup history for disaster recovery.</p>
                        <a href="Backups.php" class="action-btn">Manage Backups</a>
                    </div>
                    <div class="action-item">
                        <div class="action-icon"><i class="fas fa-history"></i></div>
                        <h3 class="action-title">Audit Logs</h3>
                        <p class="action-description">Review system audit logs and all administrative actions.</p>
                        <a href="AuditLogs.php" class="action-btn">View Logs</a>
                    </div>
                    <div class="action-item">
                        <div class="action-icon"><i class="fas fa-cog"></i></div>
                        <h3 class="action-title">System Settings</h3>
                        <p class="action-description">Manage system-wide configurations and preferences.</p>
                        <a href="Settings.php" class="action-btn">Open Settings</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            &copy; <?php echo date('Y'); ?> Event Management System. All Rights Reserved.
        </div>
    </div>
</div>

<script>
    const now = new Date();
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', options);
</script>
</body>
</html>
