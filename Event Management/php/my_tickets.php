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
$userName = $isLoggedIn ? ($_SESSION['FirstName'] ?? 'User') : '';

// Handle logout if requested
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../HTML/Login.html");
    exit();
}

$user_id = $_SESSION['UserID'];

// Fetch user's tickets with event details
$query = "
    SELECT 
        r.RegistrationID,
        r.Registration_Date,
        r.Status,
        r.Payment_Status,
        r.Amount_Paid,
        r.Payment_Method,
        r.TicketKey,
        e.Title AS Event_Title,
        e.Start_Date,
        e.Venue_Name,
        'General Admission' AS Ticket_Type,
        r.Amount_Paid AS Price
    FROM Registrations r
    INNER JOIN Events e ON r.EventID = e.EventID
    WHERE r.UserID = ?
    ORDER BY r.Registration_Date DESC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing query: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}
$result = $stmt->get_result();
if (!$result) {
    die("Error getting result: " . $stmt->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - Event Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-light: #213e94;
            --primary-dark: #162a66;
            --secondary: #1f2937;
            --secondary-light: #374151;
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
            max-width: 1200px;
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
            max-width: 600px;
            margin: 0 auto;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
        }
        
        .custom-btn {
            background: var(--secondary);
            color: white;
            border: 1px solid var(--border);
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        .custom-btn:hover {
            background: #111827;
            transform: translateY(-1px);
        }
        
        .button-danger {
            background: var(--danger);
            color: white;
            border: 1px solid var(--border);
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        .button-danger:hover {
            background: #c81d34;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .tickets-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .ticket-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: none;
            transition: var(--transition);
            position: relative;
            border: 1px solid var(--border);
        }
        
        .ticket-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 16px -8px rgba(0, 0, 0, 0.35);
        }
        
        .ticket-header {
            background: var(--primary-dark);
            color: white;
            padding: 1.2rem 1.5rem;
            position: relative;
            border-bottom: 1px solid var(--border);
        }
        
        .ticket-header::after { display: none; }
        
        .ticket-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .ticket-type {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .ticket-body {
            padding: 1.5rem;
        }
        
        .ticket-detail {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            gap: 0.8rem;
        }
        
        .ticket-icon {
            width: 40px;
            height: 40px;
            background: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .ticket-info {
            flex: 1;
        }
        
        .ticket-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.2rem;
        }
        
        .ticket-value {
            font-weight: 500;
            color: var(--dark);
        }
        
        .ticket-footer {
            padding: 1rem 1.5rem;
            background: var(--light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ticket-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-confirmed {
            background: rgba(46, 196, 182, 0.15);
            color: var(--success);
        }
        
        .status-pending {
            background: rgba(255, 159, 28, 0.15);
            color: var(--warning);
        }
        
        .status-cancelled {
            background: rgba(231, 29, 54, 0.15);
            color: var(--danger);
        }
        
        .ticket-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .no-tickets {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: none;
            border: 1px solid var(--border);
        }
        
        .no-tickets-icon {
            font-size: 3.5rem;
            color: var(--gray);
            margin-bottom: 1.2rem;
        }
        
        .no-tickets-title {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .no-tickets-text {
            color: var(--gray);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 1rem;
            }
            
            .nav-menu {
                margin-top: 1rem;
                gap: 1rem;
            }
            
            .tickets-container {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 2rem;
            }
        }
        
        .ticket-id {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .payment-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .payment-paid {
            background: rgba(46, 196, 182, 0.15);
            color: var(--success);
        }
        
        .payment-pending {
            background: rgba(255, 159, 28, 0.15);
            color: var(--warning);
        }
        
        /* Print Button Styles */
        .print-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            margin-top: 1rem;
        }
        
        .print-btn:hover {
            background: var(--primary-dark);
        }
        
        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .print-ticket, .print-ticket * {
                visibility: visible;
            }
            
            .print-ticket {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
                background: white;
                border: 2px solid #000;
            }
            
            .no-print {
                display: none !important;
            }
            
            .ticket-key-code {
                font-family: monospace;
                font-size: 1.5rem;
                letter-spacing: 2px;
                background: #f0f0f0;
                padding: 10px;
                text-align: center;
                margin: 15px 0;
                border: 1px dashed #000;
            }
        }
        
        /* Print Preview Modal */
        .print-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }
        
        .print-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: black;
        }
        
        .ticket-key-display {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 1.1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar no-print">
        <a href="../HTML/Index.html" class="nav-brand">
            Event Management System
        </a>
        
        <ul class="nav-menu">
            <li class="nav-item"><a href="Attendee-dashboard.php"><i class="fas fa-calendar-alt"></i> Events</a></li>
            <li class="nav-item"><a href="my_tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a></li>
        </ul>
        
        <div class="user-menu">
            <?php if ($isLoggedIn): ?>
                <span><i class="fas fa-user-circle"></i> Welcome, <?= htmlspecialchars($userName) ?></span>
                <a href="?logout=true" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="../HTML/Login.html">Login</a>
                <a href="../HTML/User-Registration.html">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container no-print">
        <div class="page-header">
            <h1 class="page-title">My Tickets</h1>
            <p class="page-subtitle">View and manage all your event tickets in one place</p>
        </div>
        
        <div class="action-buttons">
            <a href="Attendee-dashboard.php" class="custom-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="logout.php" class="button-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <div class="tickets-container">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($ticket = $result->fetch_assoc()): 
                    // Determine status classes
                    $status_class = '';
                    if (strtolower($ticket['Status']) == 'confirmed') {
                        $status_class = 'status-confirmed';
                    } elseif (strtolower($ticket['Status']) == 'pending') {
                        $status_class = 'status-pending';
                    } else {
                        $status_class = 'status-cancelled';
                    }
                    
                    $payment_class = (strtolower($ticket['Payment_Status']) == 'paid') ? 'payment-paid' : 'payment-pending';
                ?>
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <span class="ticket-id">#<?= $ticket['RegistrationID'] ?></span>
                            <h2 class="ticket-title"><?= htmlspecialchars($ticket['Event_Title']) ?></h2>
                            <span class="ticket-type"><?= htmlspecialchars($ticket['Ticket_Type'] ?? 'General Admission') ?></span>
                        </div>
                        
                        <div class="ticket-body">
                            <div class="ticket-detail">
                                <div class="ticket-icon">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="ticket-info">
                                    <div class="ticket-label">Event Date</div>
                                    <div class="ticket-value"><?= date('F j, Y', strtotime($ticket['Start_Date'])) ?></div>
                                </div>
                            </div>
                            
                            <div class="ticket-detail">
                                <div class="ticket-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="ticket-info">
                                    <div class="ticket-label">Venue</div>
                                    <div class="ticket-value"><?= htmlspecialchars($ticket['Venue_Name']) ?></div>
                                </div>
                            </div>
                            
                            <div class="ticket-detail">
                                <div class="ticket-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="ticket-info">
                                    <div class="ticket-label">Payment Method</div>
                                    <div class="ticket-value"><?= htmlspecialchars($ticket['Payment_Method']) ?></div>
                                </div>
                            </div>
                            
                            <div class="ticket-detail">
                                <div class="ticket-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="ticket-info">
                                    <div class="ticket-label">Registration Date</div>
                                    <div class="ticket-value"><?= date('F j, Y', strtotime($ticket['Registration_Date'])) ?></div>
                                </div>
                            </div>

                            <!-- Display Ticket Key if available -->
                            <?php if (!empty($ticket['TicketKey'])): ?>
                            <div class="ticket-detail">
                                <div class="ticket-icon">
                                    <i class="fas fa-key"></i>
                                </div>
                                <div class="ticket-info">
                                    <div class="ticket-label">Ticket Key</div>
                                    <div class="ticket-key-display"><?= htmlspecialchars($ticket['TicketKey']) ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ticket-footer">
                            <div>
                                <span class="ticket-status <?= $status_class ?>">
                                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($ticket['Status']) ?>
                                </span>
                                <span class="payment-status <?= $payment_class ?>" style="margin-left: 0.5rem;">
                                    <i class="fas fa-credit-card"></i> <?= htmlspecialchars($ticket['Payment_Status']) ?>
                                </span>
                            </div>
                            <div class="ticket-price">ZMK <?= number_format($ticket['Price'] ?? 0, 2) ?></div>
                        </div>

                        <!-- Print Button for Approved Tickets -->
                        <?php if (strtolower($ticket['Status']) === 'confirmed' && strtolower($ticket['Payment_Status']) === 'paid'): ?>
                        <div style="text-align: center; padding: 1rem; border-top: 1px solid var(--border);">
                            <button class="print-btn" onclick="showPrintPreview(<?= $ticket['RegistrationID'] ?>, '<?= htmlspecialchars($ticket['Event_Title']) ?>', '<?= htmlspecialchars($ticket['Venue_Name']) ?>', '<?= date('F j, Y', strtotime($ticket['Start_Date'])) ?>', '<?= htmlspecialchars($ticket['Ticket_Type'] ?? 'General Admission') ?>', 'ZMK <?= number_format($ticket['Price'] ?? 0, 2) ?>', '<?= !empty($ticket['TicketKey']) ? htmlspecialchars($ticket['TicketKey']) : '' ?>')">
                                <i class="fas fa-print"></i> Print Ticket
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-tickets">
                    <div class="no-tickets-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <h2 class="no-tickets-title">No Tickets Yet</h2>
                    <p class="no-tickets-text">You haven't purchased any tickets yet. Browse our events and find something you'll love!</p>
                    <a href="Attendee-dashboard.php" class="custom-btn">
                        <i class="fas fa-calendar-alt"></i> Browse Events
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Print Preview Modal -->
    <div id="printModal" class="print-modal no-print">
        <div class="print-modal-content">
            <span class="close-modal" onclick="closePrintModal()">&times;</span>
            <div id="printPreviewContent"></div>
            <div style="text-align: center; margin-top: 20px;">
                <button class="print-btn" onclick="printTicket()">
                    <i class="fas fa-print"></i> Print Now
                </button>
            </div>
        </div>
    </div>

    <script>
        // Function to show print preview
        function showPrintPreview(registrationId, eventTitle, venue, eventDate, ticketType, price, ticketKey) {
            const printContent = `
                <div class="print-ticket">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h2>Event Management System</h2>
                        <h3>Official Event Ticket</h3>
                    </div>
                    
                    <div style="border: 2px solid #000; padding: 20px; border-radius: 10px;">
                        <h2 style="text-align: center; margin-bottom: 15px;">${eventTitle}</h2>
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                            <div>
                                <strong>Venue:</strong> ${venue}<br>
                                <strong>Date:</strong> ${eventDate}
                            </div>
                            <div>
                                <strong>Ticket Type:</strong> ${ticketType}<br>
                                <strong>Price:</strong> ${price}
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin: 20px 0;">
                            <div style="font-size: 18px; font-weight: bold;">Ticket Key</div>
                            <div class="ticket-key-code">${ticketKey}</div>
                            <div style="font-size: 14px; color: #555;">Present this code at the event entrance</div>
                        </div>
                        
                        <div style="border-top: 1px dashed #000; padding-top: 15px; text-align: center;">
                            <div style="display: flex; justify-content: space-between;">
                                <div>
                                    <strong>Registration ID:</strong> #${registrationId}
                                </div>
                                <div>
                                    <strong>Issued:</strong> ${new Date().toLocaleDateString()}
                                </div>
                            </div>
                            <div style="margin-top: 15px; font-size: 12px;">
                                This ticket is non-transferable and must be presented at the event.
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('printPreviewContent').innerHTML = printContent;
            document.getElementById('printModal').style.display = 'block';
        }
        
        // Function to close the print modal
        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';
        }
        
        // Function to print the ticket
        function printTicket() {
            const printContent = document.getElementById('printPreviewContent').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContent;
            
            // Rebind events after printing
            closePrintModal();
            location.reload(); // Simple way to restore functionality
        }
        
        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('printModal');
            if (event.target == modal) {
                closePrintModal();
            }
        };
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>