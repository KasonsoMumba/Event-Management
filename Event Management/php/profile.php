<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: ../HTML/Login.html");
    exit();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['UserID']);
$userName = $isLoggedIn ? ($_SESSION['Firstname'] ?? 'User') : '';
$userID = $_SESSION['UserID'];
$userRole = $_SESSION['Role'] ?? 'Attendee';

// Handle logout if requested
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../HTML/Login.html");
    exit();
}

// Check if we're in edit mode
$editMode = isset($_GET['edit']) && $_GET['edit'] === 'true';

// Fetch user data
$userQuery = "SELECT * FROM Users WHERE UserID = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userID);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();

// Handle form submission for profile updates
$updateMessage = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $contactnumber = $_POST['contactnumber'] ?? '';

    // Basic validation
    if (empty($firstname) || empty($lastname) || empty($email)) {
        $updateMessage = 'Please fill in all required fields.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $updateMessage = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        // Update query
        $updateQuery = "UPDATE Users SET 
                        Firstname = ?, 
                        Lastname = ?, 
                        Email = ?, 
                        ContactNumber = ? 
                        WHERE UserID = ?";
        
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ssssi", 
            $firstname, $lastname, $email, $contactnumber, $userID);
        
        if ($updateStmt->execute()) {
            $updateMessage = 'Profile updated successfully!';
            $messageType = 'success';
            
            // Update session variables
            $_SESSION['Firstname'] = $firstname;
            $_SESSION['Lastname'] = $lastname;
            $_SESSION['Email'] = $email;
            
            // Refresh user data
            $userData['Firstname'] = $firstname;
            $userData['Lastname'] = $lastname;
            $userData['Email'] = $email;
            $userData['ContactNumber'] = $contactnumber;
            
            // Switch back to view mode after successful update
            $editMode = false;
            
        } else {
            $updateMessage = 'Error updating profile: ' . $conn->error;
            $messageType = 'error';
        }
        $updateStmt->close();
    }
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Event Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-light: #213e94;
            --primary-dark: #162a66;
            --secondary: #1f2937;
            --success: #16a34a;
            --warning: #b45309;
            --danger: #b91c1c;
            --light: #f3f4f6;
            --dark: #111827;
            --gray: #4b5563;
            --border: #9ca3af;
            --shadow: 0 6px 10px -4px rgba(0, 0, 0, 0.25);
            --radius: 6px;
            --transition: all 0.2s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f8fafc;
            color: var(--dark);
            line-height: 1.6;
            padding-bottom: 2rem;
        }
        
        /* Navigation Styles */
        .navbar {
            background: var(--primary);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }
        
        .nav-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
            margin: 0;
        }
        
        .nav-item a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
        }
        
        .nav-item a:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        
        .btn-logout {
            background: rgba(255, 255, 255, 0.18);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .profile-card {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: none;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            margin-right: 1.5rem;
        }
        
        .profile-info h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .profile-role {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: var(--light);
            color: var(--primary);
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .profile-status {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }
        
        .status-active {
            background: rgba(22, 163, 74, 0.15);
            color: var(--success);
        }
        
        .status-suspended {
            background: rgba(185, 28, 28, 0.15);
            color: var(--danger);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .info-grid, .form-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
        
        .info-group, .form-group {
            margin-bottom: 1rem;
        }
        
        .info-group.full-width, .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .info-value {
            padding: 0.8rem 1rem;
            background: var(--light);
            border-radius: var(--radius);
            min-height: 44px;
            display: flex;
            align-items: center;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--secondary);
            color: white;
            border: 1px solid var(--border);
        }
        
        .btn-primary:hover {
            background: #111827;
        }
        
        .btn-secondary {
            background: var(--primary);
            color: white;
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--primary-dark);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }
        
        .message-success {
            background: rgba(22, 163, 74, 0.1);
            color: var(--success);
            border-color: rgba(22, 163, 74, 0.2);
        }
        
        .message-error {
            background: rgba(185, 28, 28, 0.1);
            color: var(--danger);
            border-color: rgba(185, 28, 28, 0.2);
        }
        
        .readonly-field {
            background-color: var(--light);
            cursor: not-allowed;
        }
        
        .account-info {
            background: var(--light);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .account-info h3 {
            margin-bottom: 1rem;
            color: var(--primary);
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <a href="../HTML/Index.html" class="nav-brand">
            <span class="nav-brand">
            <i class="fas fa-user-circle"></i> Welcome
        </span>
        </a>
        
        <ul class="nav-menu">
            <?php if ($userRole === 'Attendee'): ?>
                <li class="nav-item"><a href="Attendee-dashboard.php"><i class="fas fa-calendar-alt"></i> Events</a></li>
                <li class="nav-item"><a href="my_tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a></li>
            <?php elseif ($userRole === 'Organizer'): ?>
                <li class="nav-item"><a href="Organizer-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item"><a href="../HTML/Create-Event.html"><i class="fas fa-plus-circle"></i> Create Event</a></li>
            <?php endif; ?>
            <li class="nav-item"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
        </ul>
        
        <div class="user-menu">
            <?php if ($isLoggedIn): ?>
                
                <a href="?logout=true" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="../HTML/Login.html">Login</a>
                <a href="../HTML/User-Registration.html">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle"><?= $editMode ? 'Edit your account information' : 'View your account information and preferences' ?></p>
        </div>

        <?php if ($updateMessage): ?>
            <div class="message message-<?= $messageType ?>">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($updateMessage) ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($userData['Firstname'] ?? 'U', 0, 1) . substr($userData['Lastname'] ?? 'S', 0, 1)) ?>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($userData['Firstname'] ?? '') ?> <?= htmlspecialchars($userData['Lastname'] ?? '') ?></h2>
                    <span class="profile-role"><?= htmlspecialchars($userRole) ?></span>
                    <span class="profile-status status-<?= htmlspecialchars(strtolower($userData['Status'] ?? 'active')) ?>">
                        <?= htmlspecialchars(ucfirst($userData['Status'] ?? 'active')) ?>
                    </span>
                </div>
            </div>

            <?php if (!$editMode): ?>
                <!-- View Mode -->
                <div class="info-grid">
                    <div class="info-group">
                        <label>User ID</label>
                        <div class="info-value"><?= htmlspecialchars($userID) ?></div>
                    </div>
                    
                    <div class="info-group">
                        <label>Email Address</label>
                        <div class="info-value"><?= htmlspecialchars($userData['Email'] ?? '') ?></div>
                    </div>
                    
                    <div class="info-group">
                        <label>First Name</label>
                        <div class="info-value"><?= htmlspecialchars($userData['Firstname'] ?? '') ?></div>
                    </div>
                    
                    <div class="info-group">
                        <label>Last Name</label>
                        <div class="info-value"><?= htmlspecialchars($userData['Lastname'] ?? '') ?></div>
                    </div>
                    
                    <div class="info-group">
                        <label>Contact Number</label>
                        <div class="info-value"><?= htmlspecialchars($userData['ContactNumber'] ?? '') ?></div>
                    </div>
                    
                    <div class="info-group">
                        <label>Account Created</label>
                        <div class="info-value"><?= htmlspecialchars($userData['Created_at'] ?? '') ?></div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="?edit=true" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Edit Mode -->
                <form method="POST" action="profile.php">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="user_id">User ID</label>
                            <input type="text" id="user_id" value="<?= htmlspecialchars($userID) ?>" class="readonly-field" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?= htmlspecialchars($userData['Email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="firstname">First Name *</label>
                            <input type="text" id="firstname" name="firstname" 
                                   value="<?= htmlspecialchars($userData['Firstname'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="lastname">Last Name *</label>
                            <input type="text" id="lastname" name="lastname" 
                                   value="<?= htmlspecialchars($userData['Lastname'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="contactnumber">Contact Number</label>
                            <input type="tel" id="contactnumber" name="contactnumber" 
                                   value="<?= htmlspecialchars($userData['ContactNumber'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="role">User Role</label>
                            <input type="text" id="role" value="<?= htmlspecialchars($userRole) ?>" class="readonly-field" readonly>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="profile.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="account-info">
                <h3>Account Information</h3>
                <div class="info-grid">
                    <div class="info-group">
                        <label>User Role</label>
                        <div class="info-value"><?= htmlspecialchars($userRole) ?></div>
                    </div>
                    
                    <div class="info-group">
                        <label>Account Status</label>
                        <div class="info-value">
                            <span class="profile-status status-<?= htmlspecialchars(strtolower($userData['Status'] ?? 'active')) ?>">
                                <?= htmlspecialchars(ucfirst($userData['Status'] ?? 'active')) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <label>Member Since</label>
                        <div class="info-value"><?= htmlspecialchars($userData['Created_at'] ?? '') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>