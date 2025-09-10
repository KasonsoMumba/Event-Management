<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['UserID']);
$userName = $isLoggedIn ? ($_SESSION['FirstName'] ?? 'User') : '';
$user_id = $isLoggedIn ? $_SESSION['UserID'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process the registration form
    processRegistration($conn, $user_id);
    exit();
}

// Handle logout if requested
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../HTML/Login.html");
    exit();
}

if (!isset($_GET['event_id'])) {
    die("No event selected.");
}

$event_id = intval($_GET['event_id']);

// Fetch event details
$event_sql = "SELECT * FROM Events WHERE EventID = ?";
$event_stmt = $conn->prepare($event_sql);
$event_stmt->bind_param("i", $event_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();
$event = $event_result->fetch_assoc();

if (!$event) {
    die("Event not found.");
}

// Fetch ALL ticket types from TicketTypes table
$tickets_sql = "SELECT * FROM TicketTypes WHERE EventID = ? ORDER BY Price ASC";
$tickets_stmt = $conn->prepare($tickets_sql);
if (!$tickets_stmt) {
    die("Error preparing ticket query: " . $conn->error);
}
$tickets_stmt->bind_param("i", $event_id);
if (!$tickets_stmt->execute()) {
    die("Error executing ticket query: " . $tickets_stmt->error);
}
$tickets_result = $tickets_stmt->get_result();
if (!$tickets_result) {
    die("Error getting ticket result: " . $tickets_stmt->error);
}

$tickets = [];
while ($row = $tickets_result->fetch_assoc()) {
    $tickets[] = $row;
}

// Check if sale period is valid for each ticket
$current_date = date('Y-m-d');
foreach ($tickets as &$ticket) {
    $sale_start = $ticket['Sale_Start'];
    $sale_end = $ticket['Sale_End'];
    
    $ticket['is_available'] = true;
    $ticket['availability_message'] = '';
    
    if ($sale_start && $current_date < $sale_start) {
        $ticket['is_available'] = false;
        $ticket['availability_message'] = 'Sales start on ' . date('M j, Y', strtotime($sale_start));
    } elseif ($sale_end && $current_date > $sale_end) {
        $ticket['is_available'] = false;
        $ticket['availability_message'] = 'Sales ended on ' . date('M j, Y', strtotime($sale_end));
    } elseif ($ticket['Quantity_Available'] <= 0) {
        $ticket['is_available'] = false;
        $ticket['availability_message'] = 'Sold out';
    }
}
unset($ticket); // Unset the reference to prevent issues

// Process registration function
function processRegistration($conn, $user_id) {
    // Validate user session
    if (!$user_id) {
        http_response_code(401);
        exit(json_encode(['success' => false, 'message' => 'You must be logged in to register for events.']));
    }

    // Get form data
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $payment_method = isset($_POST['payment_method']) ? htmlspecialchars($_POST['payment_method'], ENT_QUOTES, 'UTF-8') : '';
    $tickets_json = isset($_POST['tickets']) ? $_POST['tickets'] : '';

    // Get attendee information
    $first_name = isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name'], ENT_QUOTES, 'UTF-8') : '';
    $last_name = isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name'], ENT_QUOTES, 'UTF-8') : '';
    $email = isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : '';
    $phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8') : '';
    $address = isset($_POST['address']) ? htmlspecialchars($_POST['address'], ENT_QUOTES, 'UTF-8') : '';
    $city = isset($_POST['city']) ? htmlspecialchars($_POST['city'], ENT_QUOTES, 'UTF-8') : '';
    $postal_code = isset($_POST['postal_code']) ? htmlspecialchars($_POST['postal_code'], ENT_QUOTES, 'UTF-8') : '';
    $country = isset($_POST['country']) ? htmlspecialchars($_POST['country'], ENT_QUOTES, 'UTF-8') : '';
    $special_requirements = isset($_POST['special_requirements']) ? htmlspecialchars($_POST['special_requirements'], ENT_QUOTES, 'UTF-8') : '';
    $how_heard = isset($_POST['how_heard']) ? htmlspecialchars($_POST['how_heard'], ENT_QUOTES, 'UTF-8') : '';

    // Validate required fields
    if (!$event_id || empty($payment_method) || empty($tickets_json) || 
        empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        http_response_code(400);
        $error_msg = "Missing required fields: ";
        if (!$event_id) $error_msg .= "event_id ";
        if (empty($payment_method)) $error_msg .= "payment_method ";
        if (empty($tickets_json)) $error_msg .= "tickets ";
        if (empty($first_name)) $error_msg .= "first_name ";
        if (empty($last_name)) $error_msg .= "last_name ";
        if (empty($email)) $error_msg .= "email ";
        if (empty($phone)) $error_msg .= "phone ";
        exit(json_encode(['success' => false, 'message' => $error_msg]));
    }

    // Parse tickets JSON
    $tickets_data = json_decode($tickets_json, true);
    if (!$tickets_data || !is_array($tickets_data)) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Invalid ticket data format.']));
    }

    // Check if any tickets were selected
    $total_tickets = 0;
    foreach ($tickets_data as $ticket_type_id => $quantity) {
        if ($quantity > 0) {
            $total_tickets += $quantity;
        }
    }

    if ($total_tickets <= 0) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Please select at least one ticket.']));
    }

    // Validate event exists and get organizer ID
    $event_stmt = $conn->prepare("SELECT * FROM Events WHERE EventID = ?");
    $event_stmt->bind_param("i", $event_id);
    $event_stmt->execute();
    $event_result = $event_stmt->get_result();

    if ($event_result->num_rows === 0) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Event not found.']));
    }

    $event = $event_result->fetch_assoc();
    $organizer_id = $event['OrganizerID'];

    // Start transaction
    $conn->begin_transaction();

    try {
        $total_price = 0;
        $registration_ids = [];
        
        // Process each ticket type
        foreach ($tickets_data as $ticket_type_id => $quantity) {
            if ($quantity <= 0) continue;
            
            // Validate ticket type exists and belongs to this event
            $ticket_stmt = $conn->prepare("SELECT * FROM TicketTypes WHERE TicketTypeID = ? AND EventID = ?");
            $ticket_stmt->bind_param("ii", $ticket_type_id, $event_id);
            $ticket_stmt->execute();
            $ticket_result = $ticket_stmt->get_result();
            
            if ($ticket_result->num_rows === 0) {
                throw new Exception("Ticket type not found or does not belong to this event.");
            }
            
            $ticket = $ticket_result->fetch_assoc();
            
            // Check if enough tickets are available
            if ($ticket['Quantity_Available'] < $quantity) {
                throw new Exception("Not enough tickets available for " . $ticket['Type'] . ". Only " . $ticket['Quantity_Available'] . " tickets remaining.");
            }
            
            // Calculate price for this ticket type
            $ticket_price = $ticket['Price'] * $quantity;
            $total_price += $ticket_price;
            
            // Insert registration record for this ticket type with Pending status
            $registration_stmt = $conn->prepare("INSERT INTO Registrations (EventID, UserID, TicketTypeID, Registration_Date, Status, Payment_Status, Payment_Method, Amount_Paid, First_Name, Last_Name, Email, Phone, Address, City, Postal_Code, Country, Special_Requirements, How_Heard) VALUES (?, ?, ?, NOW(), 'Pending', 'Awaiting Payment', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $registration_stmt->bind_param("iiissssssssssss", 
                $event_id, $user_id, $ticket_type_id, $payment_method, $ticket_price,
                $first_name, $last_name, $email, $phone, $address, $city, $postal_code, $country, $special_requirements, $how_heard
            );
            
            if (!$registration_stmt->execute()) {
                throw new Exception("Failed to create registration: " . $conn->error);
            }
            
            $registration_id = $conn->insert_id;
            $registration_ids[] = $registration_id;
            
            // Create payment confirmation record for organizer review
            $payment_confirmation_stmt = $conn->prepare("INSERT INTO PaymentConfirmations (RegistrationID, OrganizerID, Status) VALUES (?, ?, 'Pending')");
            $payment_confirmation_stmt->bind_param("ii", $registration_id, $organizer_id);
            
            if (!$payment_confirmation_stmt->execute()) {
                throw new Exception("Failed to create payment confirmation record: " . $conn->error);
            }
            
            // Note: We are NOT updating ticket availability here - that will happen after organizer approval
            
            $ticket_stmt->close();
            $registration_stmt->close();
            $payment_confirmation_stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => 'Registration submitted successfully! Your tickets are pending approval from the organizer.',
            'registration_ids' => $registration_ids,
            'total_price' => $total_price,
            'tickets_requested' => $total_tickets
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'An error occurred: ' . $e->getMessage()
        ]);
    }

    // Close statements and connection
    $event_stmt->close();
    $conn->close();
    exit();
}

// If not a POST request, display the registration form
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration: <?= htmlspecialchars($event['Title']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Navigation Styles */
        .navbar {
            background: #1e3a8a;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 10px -4px rgba(0,0,0,0.25);
            margin-bottom: 2rem;
            border-bottom: 1px solid #9ca3af;
        }
        
        .nav-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
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
            transition: opacity 0.3s;
        }
        
        .nav-item a:hover {
            opacity: 0.8;
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
            transition: background 0.2s;
        }
        
        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        :root {
            --primary: #1e3a8a;
            --primary-dark: #162a66;
            --secondary: #1f2937;
            --success: #16a34a;
            --warning: #b45309;
            --danger: #b91c1c;
            --light: #f3f4f6;
            --dark: #111827;
            --gray: #4b5563;
            --border: #9ca3af;
            --shadow: 0 6px 10px -4px rgba(0,0,0,0.25);
            --radius: 6px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: var(--primary-dark);
            color: white;
            padding: 2rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            box-shadow: none;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        
        header::before, header::after { display: none; }
        
        .event-title {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 1.5rem 0;
            position: relative;
            z-index: 2;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }
        
        .icon {
            font-size: 1.5rem;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }
        
        @media (max-width: 992px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .summary-section {
                order: -1;
                position: static !important;
                margin-bottom: 2rem;
            }
        }
        
        .tickets-section, .attendee-section {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: none;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }
        
        .section-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
        }
        
        .ticket-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }
        
        .ticket-card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .ticket-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 16px -8px rgba(0,0,0,0.35);
            border-color: var(--secondary);
        }
        
        .ticket-type {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .ticket-price {
            font-size: 1.6rem;
            font-weight: bold;
            color: var(--dark);
            margin-bottom: 1rem;
        }
        
        .ticket-description {
            color: var(--gray);
            margin-bottom: 1.5rem;
            min-height: 60px;
        }
        
        .availability {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .available {
            color: var(--success);
            font-weight: 500;
        }
        
        .sold-out {
            color: var(--danger);
            font-weight: 500;
        }
        
        .not-available {
            color: var(--warning);
            font-weight: 500;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--primary);
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        
        .quantity-btn:disabled {
            background: var(--gray);
            cursor: not-allowed;
        }
        
        .quantity-btn:hover:not(:disabled) {
            background: var(--primary-dark);
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: bold;
        }
        
        .summary-section {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: none;
            border: 1px solid var(--border);
            position: sticky;
            top: 20px;
            height: fit-content;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border);
        }
        
        .total-row {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid var(--border);
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        .summary-tickets {
            max-height: 300px;
            overflow-y: auto;
            margin: 15px 0;
            padding-right: 10px;
        }
        
        .ticket-summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .ticket-summary-item .quantity {
            color: var(--gray);
        }
        
        .payment-section, .contact-section {
            margin-top: 2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        select, input, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border 0.3s;
        }
        
        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: var(--secondary);
            color: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            text-align: center;
            text-decoration: none;
            margin-top: 1.5rem;
        }
        
        .btn:hover {
            background: #111827;
        }
        
        .btn:disabled {
            background: var(--gray);
            cursor: not-allowed;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
        }
        
        .ticket-highlight {
            position: absolute;
            top: 0;
            right: 0;
            padding: 5px 15px;
            color: white;
            border-radius: 0 0 0 var(--radius);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .ticket-sold-out {
            background: rgba(231, 29, 54, 0.1);
        }
        
        .ticket-sold-out .ticket-highlight {
            background: var(--danger);
        }
        
        .ticket-not-available {
            background: rgba(255, 159, 28, 0.1);
        }
        
        .ticket-not-available .ticket-highlight {
            background: var(--warning);
        }
        
        .required::after {
            content: " *";
            color: var(--danger);
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .form-section-title {
            font-size: 1.4rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .error-message {
            color: var(--danger);
            font-size: 0.9rem;
            margin-top: 5px;
            display: none;
        }
        
        input:invalid, select:invalid, textarea:invalid {
            border-color: var(--danger);
        }
        
        .sale-period {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .status-banner {
            background: var(--warning);
            color: white;
            padding: 10px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: var(--radius);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <a href="../HTML/Index.html" class="nav-brand">
            Event Management System
        </a>
        
        <ul class="nav-menu">
            <li class="nav-item"><a href="Attendee-dashboard.php"><i class="fas fa-calendar-alt"></i> Events</a></li>
            <li class="nav-item"><a href="my_tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a></li>
            <li class="nav-item"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
        </ul>
        
        <div class="user-menu">
            <?php if ($isLoggedIn): ?>
                <span><i class="fas fa-user-circle"></i> Welcome, <?= htmlspecialchars($userName) ?></span>
                <a href="?event_id=<?= $event_id ?>&logout=true" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="../HTML/Login.html"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="../HTML/User-Registration.html"><i class="fas fa-user-plus"></i> Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <div class="status-banner">
            <i class="fas fa-info-circle"></i> Your registration will be pending until approved by the organizer.
        </div>
        
        <header>
            <h1 class="event-title"><?= htmlspecialchars($event['Title']) ?></h1>
            <p><?= htmlspecialchars($event['Description']) ?></p>
            
            <div class="event-meta">
                <div class="meta-item">
                    <span class="icon">📅</span>
                    <div>
                        <strong>Date:</strong><br>
                        <?= date('M j, Y', strtotime($event['Start_Date'])) ?> 
                        <?php if ($event['End_Date'] && $event['Start_Date'] != $event['End_Date']): ?>
                            - <?= date('M j, Y', strtotime($event['End_Date'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="meta-item">
                    <span class="icon">📍</span>
                    <div>
                        <strong>Venue:</strong><br>
                        <?= htmlspecialchars($event['Venue_Name']) ?>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="main-content">
            <div class="left-column">
                <div class="tickets-section">
                    <h2 class="section-title">Select Tickets</h2>
                    
                    <?php if (count($tickets) > 0): ?>
                        <div class="ticket-grid">
                            <?php foreach ($tickets as $ticket): ?>
                                <?php 
                                $isAvailable = $ticket['is_available'];
                                $availabilityClass = '';
                                if (!$isAvailable) {
                                    $availabilityClass = $ticket['Quantity_Available'] <= 0 ? 'ticket-sold-out' : 'ticket-not-available';
                                }
                                ?>
                                <div class="ticket-card <?= $availabilityClass ?>">
                                    <?php if (!$isAvailable): ?>
                                        <div class="ticket-highlight">
                                            <?= $ticket['Quantity_Available'] <= 0 ? 'SOLD OUT' : 'NOT AVAILABLE' ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <h3 class="ticket-type"><?= htmlspecialchars($ticket['Type']) ?></h3>
                                    <div class="ticket-price">ZMK <?= number_format($ticket['Price'], 2) ?></div>
                                    <p class="ticket-description"><?= htmlspecialchars($ticket['Description']) ?></p>
                                    
                                    <?php if ($ticket['Sale_Start'] || $ticket['Sale_End']): ?>
                                        <div class="sale-period">
                                            <?php if ($ticket['Sale_Start']): ?>
                                                Sale starts: <?= date('M j, Y', strtotime($ticket['Sale_Start'])) ?>
                                            <?php endif; ?>
                                            <?php if ($ticket['Sale_Start'] && $ticket['Sale_End']): ?><br><?php endif; ?>
                                            <?php if ($ticket['Sale_End']): ?>
                                                Sale ends: <?= date('M j, Y', strtotime($ticket['Sale_End'])) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="availability">
                                        <?php if ($isAvailable): ?>
                                            <span class="available"><?= $ticket['Quantity_Available'] ?> available</span>
                                        <?php else: ?>
                                            <span class="sold-out"><?= $ticket['availability_message'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($isAvailable): ?>
                                        <div class="quantity-selector">
                                            <button class="quantity-btn minus" type="button" data-ticket="<?= $ticket['TicketTypeID'] ?>">-</button>
                                            <input type="number" class="quantity-input" 
                                                   name="tickets[<?= $ticket['TicketTypeID'] ?>]" 
                                                   id="ticket-<?= $ticket['TicketTypeID'] ?>" 
                                                   value="0" 
                                                   min="0" 
                                                   max="<?= $ticket['Quantity_Available'] ?>" 
                                                   data-price="<?= $ticket['Price'] ?>"
                                                   data-name="<?= htmlspecialchars($ticket['Type']) ?>">
                                            <button class="quantity-btn plus" type="button" data-ticket="<?= $ticket['TicketTypeID'] ?>">+</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>No tickets available for this event</h3>
                            <p>Check back later for ticket availability</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="attendee-section">
                    <h2 class="section-title">Attendee Information</h2>
                    
                    <div class="form-section">
                        <h3 class="form-section-title">Primary Contact</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name" class="required">First Name</label>
                                <input type="text" id="first_name" name="first_name" required>
                                <div class="error-message" id="first_name_error">Please enter your first name</div>
                            </div>
                            <div class="form-group">
                                <label for="last_name" class="required">Last Name</label>
                                <input type="text" id="last_name" name="last_name" required>
                                <div class="error-message" id="last_name_error">Please enter your last name</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email" class="required">Email Address</label>
                                <input type="email" id="email" name="email" required>
                                <div class="error-message" id="email_error">Please enter a valid email address</div>
                            </div>
                            <div class="form-group">
                                <label for="phone" class="required">Phone Number</label>
                                <input type="tel" id="phone" name="phone" required>
                                <div class="error-message" id="phone_error">Please enter your phone number</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="form-section-title">Location Details</h3>
                        <div class="form-group">
                            <label for="address">Street Address</label>
                            <input type="text" id="address" name="address">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city">
                            </div>
                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="country">Country</label>
                            <select id="country" name="country">
                                <option value="">Select Country</option>
                                <option value="Zambia" selected>Zambia</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="form-section-title">Additional Information</h3>
                        <div class="form-group">
                            <label for="special_requirements">Special Requirements</label>
                            <textarea id="special_requirements" name="special_requirements" rows="3" placeholder="Any accessibility needs, dietary restrictions, or other special requirements"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="how_heard">How did you hear about this event?</label>
                            <select id="how_heard" name="how_heard">
                                <option value="">Please select</option>
                                <option value="Social Media">Social Media</option>
                                <option value="Friend">Friend or Colleague</option>
                                <option value="Email">Email Newsletter</option>
                                <option value="Website">Website</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="summary-section">
                <h2 class="section-title">Order Summary</h2>
                
                <div class="summary-tickets" id="tickets-summary">
                    <div class="empty-state">No tickets selected</div>
                </div>
                
                <div class="summary-item">
                    <span>Subtotal:</span>
                    <span id="subtotal">ZMK 0.00</span>
                </div>
                <div class="summary-item">
                    <span>Service Fee:</span>
                    <span>ZMK 0.00</span>
                </div>
                <div class="summary-item total-row">
                    <span>Total:</span>
                    <span id="total">ZMK 0.00</span>
                </div>
                
                <form action="" method="POST" id="registration-form">
                    <input type="hidden" name="event_id" value="<?= $event['EventID'] ?>">
                    
                    <div class="payment-section">
                        <h2 class="section-title">Payment Method</h2>
                        
                        <div class="form-group">
                            <label for="payment_method" class="required">Select Payment Method</label>
                            <select name="payment_method" id="payment_method" required>
                                <option value="">Choose a payment method</option>
                                <option value="Card">Credit/Debit Card</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Cash">Cash at Venue</option>
                            </select>
                            <div class="error-message" id="payment_method_error">Please select a payment method</div>
                        </div>
                        
                        <div id="payment-details">
                            <!-- Dynamic payment details will be inserted here based on selection -->
                        </div>
                        
                        <button type="submit" class="btn" id="register-btn" disabled>
                            Complete Registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Check if user is logged in
        <?php if (!$isLoggedIn): ?>
        alert('You must be logged in to register for events. Redirecting to login page...');
        window.location.href = '../HTML/Login.html';
        <?php endif; ?>
        
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const quantityInputs = document.querySelectorAll('.quantity-input');
            const minusButtons = document.querySelectorAll('.quantity-btn.minus');
            const plusButtons = document.querySelectorAll('.quantity-btn.plus');
            const ticketsSummary = document.getElementById('tickets-summary');
            const subtotalElement = document.getElementById('subtotal');
            const totalElement = document.getElementById('total');
            const registerBtn = document.getElementById('register-btn');
            const form = document.getElementById('registration-form');
            const paymentMethod = document.getElementById('payment_method');
            const paymentDetails = document.getElementById('payment-details');
            
            // Update totals function
            function updateTotals() {
                let subtotal = 0;
                let ticketCount = 0;
                let summaryHTML = '';
                
                quantityInputs.forEach(input => {
                    const quantity = parseInt(input.value);
                    const price = parseFloat(input.dataset.price);
                    const ticketId = input.id.split('-')[1];
                    const ticketName = input.dataset.name;
                    
                    if (quantity > 0) {
                        const ticketTotal = quantity * price;
                        subtotal += ticketTotal;
                        ticketCount += quantity;
                        
                        summaryHTML += `
                            <div class="ticket-summary-item">
                                <div>
                                    ${ticketName} <span class="quantity">(x${quantity})</span>
                                </div>
                                <div>ZMK ${ticketTotal.toFixed(2)}</div>
                            </div>
                        `;
                    }
                });
                
                // Update summary display
                if (ticketCount > 0) {
                    ticketsSummary.innerHTML = summaryHTML;
                    registerBtn.disabled = !isFormValid();
                } else {
                    ticketsSummary.innerHTML = '<div class="empty-state">No tickets selected</div>';
                    registerBtn.disabled = true;
                }
                
                // Update totals
                subtotalElement.textContent = `ZMK ${subtotal.toFixed(2)}`;
                totalElement.textContent = `ZMK ${subtotal.toFixed(2)}`;
            }
            
            // Form validation
            function isFormValid() {
                let isValid = true;
                // Get ALL required fields, including dynamically added ones
                const requiredFields = form.querySelectorAll('[required]');
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        showError(field.id + '_error');
                    } else {
                        hideError(field.id + '_error');
                        
                        // Special validation for email
                        if (field.type === 'email' && !isValidEmail(field.value)) {
                            isValid = false;
                            showError(field.id + '_error');
                        }
                    }
                });
                
                // Check if at least one ticket is selected
                let ticketCount = 0;
                quantityInputs.forEach(input => {
                    ticketCount += parseInt(input.value) || 0;
                });
                
                if (ticketCount === 0) {
                    isValid = false;
                }
                
                return isValid;
            }
            
            function isValidEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
            
            function showError(errorId) {
                const errorElement = document.getElementById(errorId);
                if (errorElement) {
                    errorElement.style.display = 'block';
                }
            }
            
            function hideError(errorId) {
                const errorElement = document.getElementById(errorId);
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
            }
            
            // Quantity button handlers
            function setupQuantityButtons() {
                minusButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const ticketId = this.dataset.ticket;
                        const input = document.getElementById(`ticket-${ticketId}`);
                        if (parseInt(input.value) > 0) {
                            input.value = parseInt(input.value) - 1;
                            updateTotals();
                        }
                    });
                });
                
                plusButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const ticketId = this.dataset.ticket;
                        const input = document.getElementById(`ticket-${ticketId}`);
                        const max = parseInt(input.max);
                        
                        if (parseInt(input.value) < max) {
                            input.value = parseInt(input.value) + 1;
                            updateTotals();
                        }
                    });
                });
            }
            
            // Input change handlers
            function setupInputListeners() {
                quantityInputs.forEach(input => {
                    input.addEventListener('change', function() {
                        const value = parseInt(this.value);
                        const max = parseInt(this.max);
                        
                        if (isNaN(value) || value < 0) {
                            this.value = 0;
                        } else if (value > max) {
                            this.value = max;
                        }
                        
                        updateTotals();
                    });
                    
                    input.addEventListener('input', function() {
                        updateTotals();
                    });
                });
                
                // Form field validation
                const formFields = form.querySelectorAll('input, select, textarea');
                formFields.forEach(field => {
                    field.addEventListener('blur', function() {
                        if (this.hasAttribute('required') && !this.value.trim()) {
                            showError(this.id + '_error');
                        } else {
                            hideError(this.id + '_error');
                            
                            // Validate email format
                            if (this.type === 'email' && this.value && !isValidEmail(this.value)) {
                                showError(this.id + '_error');
                            }
                        }
                        registerBtn.disabled = !isFormValid();
                    });
                });
            }
            
            // Payment method handler
            function setupPaymentMethod() {
                paymentMethod.addEventListener('change', function() {
                    updatePaymentDetails(this.value);
                    // Re-validate form after payment method change
                    setTimeout(() => {
                        registerBtn.disabled = !isFormValid();
                    }, 100);
                });
                
                // Initial payment details
                updatePaymentDetails(paymentMethod.value);
            }
            
            function updatePaymentDetails(method) {
                let html = '';
                
                switch(method) {
                    case 'Card':
                        html = `
                            <div class="form-group">
                                <label for="card_number" class="required">Card Number</label>
                                <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required>
                                <div class="error-message" id="card_number_error">Please enter a valid card number</div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="expiry_date" class="required">Expiry Date</label>
                                    <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required>
                                    <div class="error-message" id="expiry_date_error">Please enter expiry date</div>
                                </div>
                                <div class="form-group">
                                    <label for="cvv" class="required">CVV</label>
                                    <input type="text" id="cvv" name="cvv" placeholder="123" required>
                                    <div class="error-message" id="cvv_error">Please enter CVV</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="card_name" class="required">Name on Card</label>
                                <input type="text" id="card_name" name="card_name" required>
                                <div class="error-message" id="card_name_error">Please enter name on card</div>
                            </div>
                        `;
                        break;
                    case 'Mobile Money':
                        html = `
                            <div class="form-group">
                                <label for="mobile_provider" class="required">Mobile Provider</label>
                                <select id="mobile_provider" name="mobile_provider" required>
                                    <option value="">Select Provider</option>
                                    <option value="MTN">MTN</option>
                                    <option value="Airtel">Airtel</option>
                                    <option value="Zamtel">Zamtel</option>
                                </select>
                                <div class="error-message" id="mobile_provider_error">Please select a mobile provider</div>
                            </div>
                            <div class="form-group">
                                <label for="mobile_number" class="required">Mobile Number</label>
                                <input type="tel" id="mobile_number" name="mobile_number" required>
                                <div class="error-message" id="mobile_number_error">Please enter your mobile number</div>
                            </div>
                        `;
                        break;
                    case 'Cash':
                        html = `
                            <div class="form-group">
                                <p>You will pay cash when you arrive at the venue. Please bring exact change if possible.</p>
                            </div>
                        `;
                        break;
                    default:
                        html = '';
                }
                
                paymentDetails.innerHTML = html;
                
                // Add validation to new fields
                const newRequiredFields = paymentDetails.querySelectorAll('[required]');
                newRequiredFields.forEach(field => {
                    field.addEventListener('blur', function() {
                        if (!this.value.trim()) {
                            showError(this.id + '_error');
                        } else {
                            hideError(this.id + '_error');
                        }
                        registerBtn.disabled = !isFormValid();
                    });
                    
                    // Also validate on input for better UX
                    field.addEventListener('input', function() {
                        if (this.value.trim()) {
                            hideError(this.id + '_error');
                        }
                        registerBtn.disabled = !isFormValid();
                    });
                });
                
                // Re-validate form after adding new fields
                registerBtn.disabled = !isFormValid();
            }
            
            // Form submission handler
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Double-check validation before submission
                console.log('Form validation check...');
                if (!isFormValid()) {
                    console.log('Form validation failed');
                    alert('Please fill in all required fields correctly');
                    return false;
                }
                console.log('Form validation passed');
                
                // Additional validation: ensure at least one ticket is selected
                let ticketCount = 0;
                quantityInputs.forEach(input => {
                    ticketCount += parseInt(input.value) || 0;
                });
                
                if (ticketCount === 0) {
                    alert('Please select at least one ticket');
                    return false;
                }
                
                // Build tickets JSON from quantity inputs
                const ticketsData = {};
                quantityInputs.forEach(input => {
                    const quantity = parseInt(input.value);
                    if (quantity > 0) {
                        const ticketId = input.id.split('-')[1];
                        ticketsData[ticketId] = quantity;
                    }
                });
                
                // Create FormData
                const formData = new FormData(form);
                
                // Append tickets JSON
                formData.append('tickets', JSON.stringify(ticketsData));
                
                // Append all attendee information
                const attendeeFields = ['first_name', 'last_name', 'email', 'phone', 'address', 
                                       'city', 'postal_code', 'country', 'special_requirements', 'how_heard'];
                
                attendeeFields.forEach(field => {
                    const element = document.getElementById(field);
                    if (element) {
                        formData.append(field, element.value);
                    }
                });
                
                // Debug: Log what's being sent
                console.log('FormData contents:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
                
                // Submit via AJAX
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(async (response) => {
                    const data = await response.json().catch(() => null);
                    if (!response.ok) {
                        const msg = data && data.message ? data.message : 'Registration failed.';
                        throw new Error(msg);
                    }
                    return data;
                })
                .then(data => {
                    if (data && data.status === 'success') {
                        alert('Registration submitted successfully! Your tickets are pending approval from the organizer.');
                        window.location.href = 'my_tickets.php';
                    } else {
                        const msg = data && data.message ? data.message : 'An unknown error occurred.';
                        alert('Error: ' + msg);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + (error.message || 'An error occurred during registration.'));
                });
            });
            
            // Initialize
            setupQuantityButtons();
            setupInputListeners();
            setupPaymentMethod();
            updateTotals();
        });
    </script>
</body>
</html>
<?php
$event_stmt->close();
$tickets_stmt->close();
$conn->close();
?>