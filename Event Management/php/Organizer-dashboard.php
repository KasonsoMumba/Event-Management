<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in and is an organizer
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Organizer') {
    header("Location: ../HTML/Login.html");
    exit();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['UserID']);
$userName = $isLoggedIn ? ($_SESSION['FirstName'] ?? 'User') : '';

// Handle logout if requested
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../HTML/Login.html");
    exit();
}

$organizer_id = $_SESSION['UserID'];

// Fetch events created by this organizer
$query = "SELECT * FROM Events WHERE OrganizerID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $organizer_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Navigation */
        .navbar {
            background: #1e3a8a;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 10px -4px rgba(0,0,0,0.25);
            margin-bottom: 2rem;
        }
        .nav-brand {
            font-size: 1.3rem;
            font-weight: bold;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            margin: 0;
        }
        .nav-item a {
            color: white;
            text-decoration: none;
            font-weight: 500;
        }
        .nav-item a:hover { opacity: 0.8; }
        .btn-logout {
            background: rgba(255, 255, 255, 0.18);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-logout:hover { background: rgba(255, 255, 255, 0.3); }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f3f4f6;
            color: #111827;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 24px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        h2 {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            color: #1e293b;
        }

        /* Table styling */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
            font-size: 0.95rem;
        }
        th {
            background: #f1f5f9;
            font-weight: 600;
            color: #374151;
        }
        tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        tbody tr:hover {
            background: #f1f5f9;
        }
        td {
            border-bottom: 1px solid #e5e7eb;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Buttons */
        .btn {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            margin-right: 5px;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: #16a34a; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Status pills */
        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-upcoming { background: #e0ecff; color: #1e40af; }
        .status-published { background: #e8f5e9; color: #166534; }
        .status-draft { background: #f3f4f6; color: #374151; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        /* Success message */
        .success-message {
            background: #e7f5eb;
            color: #166534;
            padding: 0.9rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            border: 1px solid #b5e0c2;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <span class="nav-brand">
            <i class="fas fa-user-circle"></i> Welcome, <?= htmlspecialchars($userName) ?>
        </span>
        
        <ul class="nav-menu">
            <li class="nav-item"><a href="Organizer-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a href="Organizer-Approval.php"><i class="fas fa-box"></i>Orders</a></li>
            <li class="nav-item"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
        </ul>

        <a href="?logout=true" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> Event updated successfully!
            </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="../HTML/Create-Event.html" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Create New Event</a>
        </div>

        <h2>Your Events</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th style="width: 220px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($event = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($event['Title'] ?? 'Untitled Event') ?></td>
                            <td><?= htmlspecialchars($event['Start_Date'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($event['Venue_Name'] ?? 'No venue specified') ?></td>
                            <td><?= htmlspecialchars($event['Capacity'] ?? '0') ?></td>
                            <td>
                                <?php $status = strtolower($event['Status'] ?? 'draft'); ?>
                                <span class="status-pill status-<?= htmlspecialchars($status) ?>">
                                    <?= htmlspecialchars($event['Status'] ?? 'Draft') ?>
                                </span>
                            </td>
                            <td>
                                <a href="Event_Details.php?EventID=<?= $event['EventID'] ?>" class="btn btn-secondary">View</a>
                                <a href="Edit_Event.php?EventID=<?= $event['EventID'] ?>" class="btn btn-primary">Edit</a>
                                <a href="Delete_Event.php?EventID=<?= $event['EventID'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this event?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>You haven't created any events yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
