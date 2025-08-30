<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: Login.html");
    exit();
}

$user_id = $_SESSION['UserID'];
$user_name = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];

// Fetch user's purchased tickets with event details
$query = "
    SELECT 
        r.RegistrationID,
        r.Registration_Date,
        r.Status,
        r.Payment_Status,
        r.Payment_Method,
        r.Amount_Paid,
        t.Ticket_Type,
        t.Price,
        e.EventID,
        e.Title AS EventTitle,
        e.Description AS EventDescription,
        e.Start_Date,
        e.End_Date,
        e.Venue_Name,
        e.Status AS EventStatus
    FROM Registrations r
    INNER JOIN Tickets t ON r.TicketID = t.TicketID
    INNER JOIN Events e ON r.EventID = e.EventID
    WHERE r.UserID = ?
    ORDER BY r.Registration_Date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - Event Management System</title>
    <link rel="stylesheet" href="../stylesheets.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4361ee;
        }
        
        .header h1 {
            color: #4361ee;
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: #4361ee;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3a56d4;
        }
        
        .btn-logout {
            background-color: #f72585;
            color: white;
        }
        
        .btn-logout:hover {
            background-color: #e91e63;
        }
        
        .tickets-container {
            margin-top: 30px;
        }
        
        .ticket-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 25px;
            border-left: 5px solid #4361ee;
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .ticket-title {
            color: #333;
            margin: 0;
            font-size: 24px;
        }
        
        .ticket-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .ticket-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .status-confirmed {
            background-color: #38b000;
            color: white;
        }
        
        .status-pending {
            background-color: #ff9500;
            color: white;
        }
        
        .status-cancelled {
            background-color: #f72585;
            color: white;
        }
        
        .payment-paid {
            background-color: #38b000;
            color: white;
        }
        
        .payment-awaiting {
            background-color: #ff9500;
            color: white;
        }
        
        .payment-refunded {
            background-color: #6c757d;
            color: white;
        }
        
        .ticket-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-group {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .detail-value {
            color: #333;
            font-size: 16px;
        }
        
        .ticket-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn-download {
            background-color: #4361ee;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-download:hover {
            background-color: #3a56d4;
        }
        
        .btn-cancel {
            background-color: #f72585;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-cancel:hover {
            background-color: #e91e63;
        }
        
        .no-tickets {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-tickets h2 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .event-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .event-upcoming {
            background-color: #4cc9f0;
            color: white;
        }
        
        .event-ongoing {
            background-color: #38b000;
            color: white;
        }
        
        .event-completed {
            background-color: #6c757d;
            color: white;
        }
        
        .event-cancelled {
            background-color: #f72585;
            color: white;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .user-info {
                justify-content: center;
            }
            
            .ticket-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .ticket-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Tickets</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                <a href="Attendee-dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </div>
        </div>
        
        <div class="tickets-container">
            <?php if (count($tickets) > 0): ?>
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <h2 class="ticket-title">
                                <?php echo htmlspecialchars($ticket['EventTitle']); ?>
                                <span class="event-status event-<?php echo strtolower($ticket['EventStatus'] ?? 'upcoming'); ?>">
                                    <?php echo htmlspecialchars($ticket['EventStatus']); ?>
                                </span>
                            </h2>
                            <div class="ticket-meta">
                                <span class="ticket-badge status-<?php echo strtolower($ticket['Status']); ?>">
                                    <?php echo htmlspecialchars($ticket['Status']); ?>
                                </span>
                                <span class="ticket-badge payment-<?php echo strtolower(str_replace(' ', '-', $ticket['Payment_Status'])); ?>">
                                    <?php echo htmlspecialchars($ticket['Payment_Status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="ticket-details">
                            <div class="detail-group">
                                <div class="detail-label">Ticket Type</div>
                                <div class="detail-value"><?php echo htmlspecialchars($ticket['Ticket_Type']); ?></div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Price Paid</div>
                                <div class="detail-value">ZMK <?php echo number_format($ticket['Amount_Paid'], 2); ?></div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Purchase Date</div>
                                <div class="detail-value"><?php echo date('F j, Y', strtotime($ticket['Registration_Date'])); ?></div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Payment Method</div>
                                <div class="detail-value"><?php echo htmlspecialchars($ticket['Payment_Method']); ?></div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Event Date</div>
                                <div class="detail-value"><?php echo date('F j, Y', strtotime($ticket['Start_Date'])); ?></div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Event Time</div>
                                <div class="detail-value">
                                    <?php echo date('g:i A', strtotime($ticket['Start_Date'])); ?> - 
                                    <?php echo date('g:i A', strtotime($ticket['End_Date'])); ?>
                                </div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Venue</div>
                                <div class="detail-value"><?php echo htmlspecialchars($ticket['Venue_Name']); ?></div>
                            </div>
                            
                            <div class="detail-group">
                                <div class="detail-label">Registration ID</div>
                                <div class="detail-value">#<?php echo $ticket['RegistrationID']; ?></div>
                            </div>
                        </div>
                        
                        <div class="ticket-actions">
                            <a href="download_ticket.php?registration_id=<?php echo $ticket['RegistrationID']; ?>" class="btn-download">
                                Download Ticket
                            </a>
                            <?php if ($ticket['Status'] === 'Confirmed' && strtotime($ticket['Start_Date']) > time()): ?>
                                <a href="cancel_ticket.php?registration_id=<?php echo $ticket['RegistrationID']; ?>" class="btn-cancel">
                                    Cancel Ticket
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-tickets">
                    <h2>No Tickets Purchased Yet</h2>
                    <p>You haven't purchased any tickets yet. Browse events to find something exciting!</p>
                    <a href="Events.php" class="btn btn-primary">Browse Events</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>