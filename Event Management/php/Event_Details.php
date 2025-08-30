<?php
session_start();
include 'db_connect.php';

// Get EventID from URL parameter
$eventID = isset($_GET['EventID']) ? intval($_GET['EventID']) : 0;

// Fetch event details
$eventQuery = "SELECT * FROM Events WHERE EventID = ?";
$stmt = $conn->prepare($eventQuery);
$stmt->bind_param("i", $eventID);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    die("Event not found.");
}

// Set default values for potentially null fields
$event['Title'] = $event['Title'] ?? 'Untitled Event';
$event['Description'] = $event['Description'] ?? 'No description available';
$event['Venue_Name'] = $event['Venue_Name'] ?? 'Venue not specified';
$event['Status'] = $event['Status'] ?? 'Unknown';
$event['Capacity'] = $event['Capacity'] ?? 0;
$event['Start_Date'] = $event['Start_Date'] ?? date('Y-m-d H:i:s');
$event['End_Date'] = $event['End_Date'] ?? $event['Start_Date'];

// Fetch ticket types for this event
$ticketQuery = "SELECT * FROM TicketTypes WHERE EventID = ?";
$ticketStmt = $conn->prepare($ticketQuery);
$ticketStmt->bind_param("i", $eventID);
$ticketStmt->execute();
$ticketResult = $ticketStmt->get_result();
$ticketTypes = $ticketResult->fetch_all(MYSQLI_ASSOC);
$ticketStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['Title']) ?> - Event Details</title>
    <link rel="stylesheet" href="../stylesheets.css">
    <style>
        .event-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .event-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .event-header h1 {
            color: #4361ee;
            margin-bottom: 10px;
        }
        
        .event-meta {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            color: #666;
            flex-wrap: wrap;
        }
        
        .event-content {
            display: flex;
            gap: 30px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }
        
        .event-image {
            flex: 1;
            min-width: 300px;
            background-color: #f0f0f0;
            min-height: 300px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .event-details {
            flex: 1;
            min-width: 300px;
        }
        
        .event-description {
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .ticket-types {
            margin-top: 40px;
        }
        
        .ticket-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .ticket-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .ticket-price {
            font-size: 20px;
            font-weight: bold;
            color: #4361ee;
        }
        
        .ticket-description {
            margin-bottom: 15px;
            color: #666;
        }
        
        .btn-purchase {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4361ee;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .btn-purchase:hover {
            background-color: #3a56d4;
        }
        
        .event-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .status-upcoming {
            background-color: #4cc9f0;
            color: white;
        }
        
        .status-ongoing {
            background-color: #38b000;
            color: white;
        }
        
        .status-cancelled {
            background-color: #f72585;
            color: white;
        }
        
        .status-completed {
            background-color: #6c757d;
            color: white;
        }
        
        .status-unknown {
            background-color: #adb5bd;
            color: white;
        }
        
        @media (max-width: 768px) {
            .event-content {
                flex-direction: column;
            }
            
            .event-meta {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="event-container">
        <div class="event-header">
            <h1><?= htmlspecialchars($event['Title']) ?></h1>
            <div class="event-meta">
                <span><?= date('F j, Y', strtotime($event['Start_Date'])) ?></span>
                <span><?= htmlspecialchars($event['Venue_Name']) ?></span>
                <span class="event-status status-<?= strtolower($event['Status'] ?? 'unknown') ?>">
                    <?= htmlspecialchars($event['Status']) ?>
                </span>
            </div>
        </div>
        
        <div class="event-content">
            <div class="event-image">
                <!-- Placeholder for event image - you can replace with actual image from your database -->
                <img src="https://via.placeholder.com/600x400?text=Event+Image" alt="Event Image">
            </div>
            
            <div class="event-details">
                <div class="event-description">
                    <h3>About This Event</h3>
                    <p><?= nl2br(htmlspecialchars($event['Description'])) ?></p>
                </div>
                
                <div class="event-info">
                    <h3>Event Details</h3>
                    <p><strong>Date:</strong> <?= date('F j, Y', strtotime($event['Start_Date'])) ?></p>
                    <p><strong>Time:</strong> <?= date('g:i A', strtotime($event['Start_Date'])) ?></p>
                    <p><strong>End Time:</strong> <?= date('g:i A', strtotime($event['End_Date'])) ?></p>
                    <p><strong>Venue:</strong> <?= htmlspecialchars($event['Venue_Name']) ?></p>
                    <p><strong>Capacity:</strong> <?= number_format($event['Capacity']) ?> attendees</p>
                </div>
            </div>
        </div>
        
        <div class="ticket-types">
            <h2>Ticket Options</h2>
            
            <?php if (count($ticketTypes) > 0): ?>
                <?php foreach ($ticketTypes as $ticket): ?>
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <div class="ticket-name"><?= htmlspecialchars($ticket['Type'] ?? 'General Admission') ?></div>
                            <div class="ticket-price">ZMK<?= number_format($ticket['Price'] ?? 0, 2) ?></div>
                        </div>
                        <div class="ticket-description">
                            Standard admission to the event
                        </div>
                        <?php if (isset($_SESSION['UserID'])): ?>
                            <a href="Purchase_Ticket.php?TicketTypeID=<?= $ticket['TicketTypeID'] ?? 0 ?>" class="btn-purchase">
                                Purchase Ticket
                            </a>
                        <?php else: ?>
                            <a href="Login.html" class="btn-purchase">
                                Login to Purchase
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ticket-card">
                    <p>No ticket options available for this event yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>