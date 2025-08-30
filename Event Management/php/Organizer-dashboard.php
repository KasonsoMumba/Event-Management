<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in and is an organizer
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Organizer') {
    header("Location: Login.html");
    exit();
}

$organizer_id = $_SESSION['UserID']; // Use this to fetch events

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
    <link rel="stylesheet" href="../stylesheets.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1, h2 {
            color: #333;
        }
        
        .welcome-message {
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .btn {
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            margin-right: 5px;
            display: inline-block;
            transition: opacity 0.2s;
        }
        
        .btn-primary {
            background-color: #4361ee;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .action-buttons {
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Organizer Dashboard</h1>
        <div class="welcome-message">
            Welcome, <?= isset($_SESSION['FirstName']) ? htmlspecialchars($_SESSION['FirstName']) : 'Organizer' ?>
        </div>

        <div class="action-buttons">
            <a href="../html/Create-Event.html" class="btn btn-primary">Create New Event</a>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($event = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= isset($event['Title']) ? htmlspecialchars($event['Title']) : 'Untitled Event' ?></td>
                            <td><?= isset($event['Start_Date']) ? htmlspecialchars($event['Start_Date']) : 'N/A' ?></td>
                            <td><?= isset($event['Venue_Name']) ? htmlspecialchars($event['Venue_Name']) : 'No venue specified' ?></td>
                            <td><?= isset($event['Capacity']) ? htmlspecialchars($event['Capacity']) : '0' ?></td>
                            <td><?= isset($event['Status']) ? htmlspecialchars($event['Status']) : 'Draft' ?></td>
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