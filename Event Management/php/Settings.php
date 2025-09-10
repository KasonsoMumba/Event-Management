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

// Handle system settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $siteName = $_POST['site_name'];
    $adminEmail = $_POST['admin_email'];
    $itemsPerPage = intval($_POST['items_per_page']);
    $allowRegistrations = isset($_POST['allow_registrations']) ? 1 : 0;
    $maintenanceMode = isset($_POST['maintenance_mode']) ? 1 : 0;
    
    // Validate inputs
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid admin email address";
    } elseif ($itemsPerPage < 5 || $itemsPerPage > 100) {
        $error = "Items per page must be between 5 and 100";
    } else {
        // In a real application, you would save these to a settings table
        // For this example, we'll just show a success message
        $message = "System settings updated successfully!";
        
        // Log the action
        logSecurityAction($conn, $_SESSION['UserID'], "Updated system settings");
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate passwords
    if (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters long";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match";
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT Password_hash FROM Users WHERE UserID = ?");
        $stmt->bind_param("i", $_SESSION['UserID']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (password_verify($currentPassword, $user['Password_hash'])) {
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE Users SET Password_hash = ? WHERE UserID = ?");
            $stmt->bind_param("si", $newHash, $_SESSION['UserID']);
            
            if ($stmt->execute()) {
                $message = "Password updated successfully!";
                logSecurityAction($conn, $_SESSION['UserID'], "Changed admin password");
            } else {
                $error = "Error updating password: " . $conn->error;
            }
        } else {
            $error = "Current password is incorrect";
        }
    }
}

// Handle email notifications update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    $notifyNewRegistrations = isset($_POST['notify_new_registrations']) ? 1 : 0;
    $notifyPasswordResets = isset($_POST['notify_password_resets']) ? 1 : 0;
    $notifySystemErrors = isset($_POST['notify_system_errors']) ? 1 : 0;
    
    // In a real application, you would save these to a settings table
    $message = "Notification settings updated successfully!";
    logSecurityAction($conn, $_SESSION['UserID'], "Updated notification settings");
}

// Function to log security actions
function logSecurityAction($conn, $adminId, $action) {
    try {
        // Check if security_logs table exists, create if not
        $conn->query("
            CREATE TABLE IF NOT EXISTS security_logs (
                LogID INT AUTO_INCREMENT PRIMARY KEY,
                AdminID INT,
                Action TEXT,
                Timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $conn->prepare("INSERT INTO security_logs (AdminID, Action) VALUES (?, ?)");
        $stmt->bind_param("is", $adminId, $action);
        $stmt->execute();
    } catch (Exception $e) {
        // Silently fail if logging table doesn't exist
    }
}

// Get current settings (in a real app, these would come from a database)
$siteName = "Event Management System";
$adminEmail = "admin@example.com";
$itemsPerPage = 20;
$allowRegistrations = true;
$maintenanceMode = false;
$notifyNewRegistrations = true;
$notifyPasswordResets = true;
$notifySystemErrors = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings</title>
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
            max-width: 1200px;
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
        
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        .settings-card {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .settings-card h2 {
            margin-top: 0;
            color: var(--dark);
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 1rem;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
        }
        
        .btn { 
            padding: 12px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--secondary);
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: var(--success);
            color: #fff;
        }
        
        .btn-success:hover {
            background: #27ae60;
        }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .tab {
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 5px 5px 0 0;
            background: #f1f1f1;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: var(--secondary);
            color: #fff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                gap: 15px;
            }
            
            nav {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            .container {
                padding: 15px;
            }
            
            .settings-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<header>
    <h1><i class="fas fa-cog"></i> Admin Settings</h1>
    <nav>
        <a href="Admin-Dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="Events.php"><i class="fas fa-calendar-alt"></i> Events</a>
        <a href="Reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="security.php"><i class="fas fa-lock"></i> Security</a>
        <a href="settings.php" style="background: rgba(255,255,255,0.2);"><i class="fas fa-cog"></i> Settings</a>
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

    <div class="tabs">
        <div class="tab active" data-tab="system">System Settings</div>
        <div class="tab" data-tab="security">Security</div>
        <div class="tab" data-tab="notifications">Notifications</div>
    </div>

    <div class="settings-grid">
        <!-- System Settings -->
        <div class="tab-content active" id="system-tab">
            <div class="settings-card">
                <h2><i class="fas fa-sliders-h"></i> System Configuration</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="site_name">Site Name</label>
                        <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($siteName) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Admin Email</label>
                        <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($adminEmail) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="items_per_page">Items Per Page</label>
                        <input type="number" id="items_per_page" name="items_per_page" min="5" max="100" value="<?= $itemsPerPage ?>" required>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="allow_registrations" name="allow_registrations" <?= $allowRegistrations ? 'checked' : '' ?>>
                        <label for="allow_registrations">Allow New User Registrations</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" <?= $maintenanceMode ? 'checked' : '' ?>>
                        <label for="maintenance_mode">Maintenance Mode</label>
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save System Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="tab-content" id="security-tab">
            <div class="settings-card">
                <h2><i class="fas fa-lock"></i> Change Password</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-success">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>

        <!-- Notification Settings -->
        <div class="tab-content" id="notifications-tab">
            <div class="settings-card">
                <h2><i class="fas fa-bell"></i> Email Notifications</h2>
                <form method="POST">
                    <div class="checkbox-group">
                        <input type="checkbox" id="notify_new_registrations" name="notify_new_registrations" <?= $notifyNewRegistrations ? 'checked' : '' ?>>
                        <label for="notify_new_registrations">Notify on New User Registrations</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="notify_password_resets" name="notify_password_resets" <?= $notifyPasswordResets ? 'checked' : '' ?>>
                        <label for="notify_password_resets">Notify on Password Reset Requests</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="notify_system_errors" name="notify_system_errors" <?= $notifySystemErrors ? 'checked' : '' ?>>
                        <label for="notify_system_errors">Notify on System Errors</label>
                    </div>
                    
                    <button type="submit" name="update_notifications" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Notification Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab functionality
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Deactivate all tabs and contents
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Activate current tab and content
                this.classList.add('active');
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
        // Password confirmation validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (newPassword && confirmPassword) {
            function validatePassword() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            newPassword.addEventListener('change', validatePassword);
            confirmPassword.addEventListener('keyup', validatePassword);
        }
    });
</script>
</body>
</html>