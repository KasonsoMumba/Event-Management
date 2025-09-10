<?php
session_start();
include 'db_connect.php';

// Check if user is logged in as organizer
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

// Get EventID from URL parameter
$eventID = isset($_GET['EventID']) ? intval($_GET['EventID']) : 0;

// Validate organizer owns this event
$organizerID = $_SESSION['UserID'];
$stmt = $conn->prepare("SELECT * FROM Events WHERE EventID = ? AND OrganizerID = ?");
$stmt->bind_param("ii", $eventID, $organizerID);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    die("Event not found or you don't have permission to edit it.");
}

// Fetch existing tickets for this event - USING CORRECT TicketTypes TABLE
$tickets = [];
$ticketStmt = $conn->prepare("SELECT * FROM TicketTypes WHERE EventID = ?");
$ticketStmt->bind_param("i", $eventID);
$ticketStmt->execute();
$ticketResult = $ticketStmt->get_result();
while ($ticket = $ticketResult->fetch_assoc()) {
    $tickets[] = $ticket;
}
$ticketStmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update event details
    $title = $_POST['title'];
    $description = $_POST['description'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $venue = $_POST['venue'];
    $capacity = intval($_POST['capacity']);
    $status = $_POST['status'];
    
    // Handle image upload
    $imagePath = $event['Image_Path'] ?? null; // Keep existing image if no new one uploaded
    
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/events/';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileInfo = pathinfo($_FILES['event_image']['name']);
        $extension = strtolower($fileInfo['extension']);
        
        // Validate file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($extension, $allowedTypes)) {
            // Generate unique filename
            $newFilename = 'event_' . $eventID . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $uploadPath)) {
                $imagePath = 'uploads/events/' . $newFilename;
                
                // Delete old image if it exists
                if ($event['Image_Path'] && file_exists('../' . $event['Image_Path'])) {
                    unlink('../' . $event['Image_Path']);
                }
            }
        }
    }
    
    // Add Image_Path column if it doesn't exist
    $checkImageColumn = $conn->query("SHOW COLUMNS FROM Events LIKE 'Image_Path'");
    if ($checkImageColumn->num_rows == 0) {
        $conn->query("ALTER TABLE Events ADD COLUMN Image_Path VARCHAR(255) AFTER Description");
    }
    
    $updateStmt = $conn->prepare("UPDATE Events SET 
        Title = ?, 
        Description = ?, 
        Image_Path = ?,
        Start_Date = ?, 
        End_Date = ?, 
        Venue_Name = ?, 
        Capacity = ?, 
        Status = ?
        WHERE EventID = ?");
    $updateStmt->bind_param("ssssssssi", $title, $description, $imagePath, $startDate, $endDate, $venue, $capacity, $status, $eventID);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Update tickets - USING CORRECT TicketTypes TABLE AND COLUMNS
    if (isset($_POST['ticket_types'])) {
        foreach ($_POST['ticket_types'] as $ticketData) {
            $ticketID = intval($ticketData['id']);
            $type = $ticketData['name'];
            $price = floatval($ticketData['price']);
            $quantity = intval($ticketData['quantity']);
            $saleStart = !empty($ticketData['sale_start']) ? $ticketData['sale_start'] : null;
            $saleEnd = !empty($ticketData['sale_end']) ? $ticketData['sale_end'] : null;
            $description = $ticketData['description'] ?? '';
            
            if ($ticketID > 0) {
                // Update existing ticket - USING CORRECT TABLE AND COLUMNS
                $ticketUpdate = $conn->prepare("UPDATE TicketTypes SET 
                    Type = ?, 
                    Price = ?,
                    Quantity_Available = ?,
                    Sale_Start = ?,
                    Sale_End = ?,
                    Description = ?
                    WHERE TicketTypeID = ?");
                $ticketUpdate->bind_param("sdisssi", $type, $price, $quantity, $saleStart, $saleEnd, $description, $ticketID);
                $ticketUpdate->execute();
                $ticketUpdate->close();
            } else {
                // Add new ticket - USING CORRECT TABLE AND COLUMNS
                $ticketInsert = $conn->prepare("INSERT INTO TicketTypes (EventID, Type, Price, Quantity_Available, Sale_Start, Sale_End, Description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $ticketInsert->bind_param("isdssss", $eventID, $type, $price, $quantity, $saleStart, $saleEnd, $description);
                $ticketInsert->execute();
                $ticketInsert->close();
            }
        }
    }
    
    // Redirect to dashboard with success message
    header("Location: Organizer-dashboard.php?success=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Event Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --secondary-light: #9b5de5;
            --success: #2ec4b6;
            --warning: #ff9f1c;
            --danger: #e71d36;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border: #dee2e6;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius: 12px;
            --transition: all 0.3s ease;
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
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
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
            gap: 1.5rem;
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
            background: rgba(255, 255, 255, 0.1);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        
        .btn-logout {
            background: rgba(255, 255, 255, 0.2);
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
            background: var(--primary);
            color: white;
            border: none;
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
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .button-danger {
            background: var(--danger);
            color: white;
            border: none;
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
        
        .form-container {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-section {
            margin-bottom: 2.5rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .form-section h2 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .form-row {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-col {
            flex: 1;
            min-width: 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .current-image {
            margin: 10px 0;
            text-align: center;
        }
        
        .current-image img {
            border: 2px solid var(--border);
            box-shadow: var(--shadow);
        }
        
        .image-info {
            font-size: 0.85rem;
            color: var(--gray);
            margin: 5px 0 0 0;
            font-style: italic;
        }
        
        input[type="file"] {
            padding: 8px;
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            background: var(--light);
            transition: var(--transition);
        }
        
        input[type="file"]:hover {
            border-color: var(--primary);
            background: rgba(67, 97, 238, 0.05);
        }
        
        input[type="file"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .status-options {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        
        .status-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-option input[type="radio"] {
            margin: 0;
        }
        
        .ticket-types-container {
            margin-bottom: 1.5rem;
        }
        
        .ticket-type {
            background: var(--light);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            position: relative;
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .ticket-header h3 {
            margin: 0;
            color: var(--dark);
            font-size: 1.2rem;
        }
        
        .remove-ticket {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .remove-ticket:hover {
            background: #c81d34;
            transform: scale(1.1);
        }
        
        .add-ticket-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            padding: 0.8rem 1.5rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .add-ticket-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .action-buttons-form {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }
        
        .notification {
            display: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            font-weight: 500;
        }
        
        .notification.error {
            background-color: rgba(231, 29, 54, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        
        .notification.success {
            background-color: rgba(46, 196, 182, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        @media (max-width: 768px) { 
            .navbar {
                flex-direction: column;
                padding: 1rem;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }
            
            .nav-item {
                width: 100%;
                text-align: center;
            }
            
            .user-menu {
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .status-options {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .action-buttons-form {
                flex-direction: column;
                gap: 1rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <a href="../HTML/Index.html" class="nav-brand">
            <i class="fas fa-calendar-alt"></i>
            Event Manager
        </a>
        
        <ul class="nav-menu">
            <li class="nav-item"><a href="Organizer-dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="../HTML/Create-Event.html">Create Event</a></li>
            <li class="nav-item"><a href="my_tickets.php">My Tickets</a></li>
            <li class="nav-item"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
        </ul>
        
        <div class="user-menu">
            <span><i class="fas fa-user-circle"></i> <?= htmlspecialchars($userName) ?></span>
            <a href="?logout=true" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Edit Event</h1>
            <p class="page-subtitle">Update your event details and ticket information</p>
            </div>
        
        <div class="action-buttons">
            <a href="Organizer-dashboard.php" class="custom-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div id="notification" class="notification"></div>
        
        <form method="POST" id="edit-event-form" enctype="multipart/form-data">
            <div class="form-container">
                <div class="form-section">
                    <h2>Event Information</h2>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="title">Event Title</label>
                                <input type="text" id="title" name="title" class="form-control" 
                                       value="<?= htmlspecialchars($event['Title']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" 
                                          rows="4" required><?= htmlspecialchars($event['Description']) ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="event_image">Event Image</label>
                                <?php if (!empty($event['Image_Path'])): ?>
                                    <div class="current-image">
                                        <img src="../<?= htmlspecialchars($event['Image_Path']) ?>" alt="Current Event Image" style="max-width: 200px; max-height: 150px; border-radius: 8px; margin: 10px 0;">
                                        <p class="image-info">Current image</p>
                                    </div>
                                <?php endif; ?>
                                <input type="file" id="event_image" name="event_image" class="form-control" accept="image/*">
                                <small class="form-help">Upload a new image to replace the current one. Supported formats: JPG, PNG, GIF, WebP. Max size: 5MB.</small>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="venue">Venue</label>
                                <input type="text" id="venue" name="venue" class="form-control" 
                                       value="<?= htmlspecialchars($event['Venue_Name']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="capacity">Capacity</label>
                                <input type="number" id="capacity" name="capacity" class="form-control" 
                                       min="1" value="<?= $event['Capacity'] ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="start_date">Start Date & Time</label>
                                <input type="datetime-local" id="start_date" name="start_date" class="form-control" 
                                       value="<?= date('Y-m-d\TH:i', strtotime($event['Start_Date'])) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="end_date">End Date & Time</label>
                                <input type="datetime-local" id="end_date" name="end_date" class="form-control" 
                                       value="<?= date('Y-m-d\TH:i', strtotime($event['End_Date'])) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Event Status</label>
                        <div class="status-options">
                            <div class="status-option">
                                <input type="radio" id="status-upcoming" name="status" value="Upcoming" 
                                    <?= ($event['Status'] === 'Upcoming' ? 'checked' : '') ?>>
                                <label for="status-upcoming">Upcoming</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" id="Draft" name="status" value="Draft" 
                                    <?= ($event['Status'] === 'Draft' ? 'checked' : '') ?>>
                                <label for="status-Draft">Draft</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" id="Published" name="status" value="Published" 
                                    <?= ($event['Status'] === 'Published' ? 'checked' : '') ?>>
                                <label for="status-completed">Published</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" id="status-cancelled" name="status" value="Cancelled" 
                                    <?= ($event['Status'] === 'Cancelled' ? 'checked' : '') ?>>
                                <label for="status-cancelled">Cancelled</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Ticket Types & Pricing</h2>
                    <div class="ticket-types-container" id="ticket-types-container">
                        <?php
$ticketCount = count($tickets);
if ($ticketCount > 0) {
    foreach ($tickets as $index => $ticket) {
        // Format dates for datetime-local input
        $saleStart = !empty($ticket['Sale_Start']) ? date('Y-m-d\TH:i', strtotime($ticket['Sale_Start'])) : '';
        $saleEnd = !empty($ticket['Sale_End']) ? date('Y-m-d\TH:i', strtotime($ticket['Sale_End'])) : '';
                        ?>
                        <div class="ticket-type">
                <div class="ticket-header">
                                <h3>Ticket Type #<?= $index + 1 ?></h3>
                    <button type="button" class="remove-ticket" onclick="removeTicketType(this)">×</button>
                </div>
                            <input type="hidden" name="ticket_types[<?= $index ?>][id]" value="<?= $ticket['TicketTypeID'] ?>">
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Ticket Name</label>
                                        <input type="text" name="ticket_types[<?= $index ?>][name]" 
                                               class="form-control" value="<?= htmlspecialchars($ticket['Type']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label>Price (ZMK)</label>
                                        <input type="number" name="ticket_types[<?= $index ?>][price]" 
                                   class="form-control" min="0" step="0.01" 
                                               value="<?= floatval($ticket['Price']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label>Quantity Available</label>
                                        <input type="number" name="ticket_types[<?= $index ?>][quantity]" 
                                   class="form-control" min="0" 
                                               value="<?= $ticket['Quantity_Available'] ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Sale Start Date & Time</label>
                                        <input type="datetime-local" name="ticket_types[<?= $index ?>][sale_start]" 
                                               class="form-control" value="<?= $saleStart ?>">
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label>Sale End Date & Time</label>
                                        <input type="datetime-local" name="ticket_types[<?= $index ?>][sale_end]" 
                                               class="form-control" value="<?= $saleEnd ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                                <textarea name="ticket_types[<?= $index ?>][description]" 
                                          class="form-control" rows="2"><?= htmlspecialchars($ticket['Description'] ?? '') ?></textarea>
                            </div>
                </div>
                        <?php
    }
} else {
                        ?>
                        <div class="ticket-type">
            <div class="ticket-header">
                <h3>Ticket Type #1</h3>
                <button type="button" class="remove-ticket" onclick="removeTicketType(this)">×</button>
            </div>
            <input type="hidden" name="ticket_types[0][id]" value="0">
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label>Ticket Name</label>
                        <input type="text" name="ticket_types[0][name]" 
                               class="form-control" value="Regular" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label>Price (ZMK)</label>
                        <input type="number" name="ticket_types[0][price]" 
                               class="form-control" min="0" step="0.01" value="49.99" required>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label>Quantity Available</label>
                        <input type="number" name="ticket_types[0][quantity]" 
                               class="form-control" min="1" value="100" required>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label>Sale Start Date & Time</label>
                        <input type="datetime-local" name="ticket_types[0][sale_start]" 
                               class="form-control" value="">
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="form-group">
                        <label>Sale End Date & Time</label>
                        <input type="datetime-local" name="ticket_types[0][sale_end]" 
                               class="form-control" value="">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="ticket_types[0][description]" 
                          class="form-control" rows="2">Standard admission ticket</textarea>
            </div>
                        </div>
                        <?php } ?>
                    </div>
                    
                    <button type="button" class="add-ticket-btn" onclick="addTicketType()">
                        <i class="fas fa-plus"></i> Add Ticket Type
                    </button>
                </div>
                
                <div class="action-buttons-form">
                    <button type="button" class="button-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash"></i> Delete Event
                    </button>
                    <button type="submit" class="custom-btn">
                        <i class="fas fa-save"></i> Update Event
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        let ticketCount = <?= ($ticketCount ?: 1) ?>;
        
        function addTicketType() {
            const container = document.getElementById("ticket-types-container");
            const newIndex = ticketCount++;
            
            const ticketHtml = `
                <div class="ticket-type">
                    <div class="ticket-header">
                        <h3>Ticket Type #${newIndex + 1}</h3>
                        <button type="button" class="remove-ticket" onclick="removeTicketType(this)">×</button>
                    </div>
                    <input type="hidden" name="ticket_types[${newIndex}][id]" value="0">
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Ticket Name</label>
                                <input type="text" name="ticket_types[${newIndex}][name]" 
                                       class="form-control" value="New Ticket Type" required>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label>Price (ZMK)</label>
                                <input type="number" name="ticket_types[${newIndex}][price]" 
                                       class="form-control" min="0" step="0.01" value="0.00" required>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label>Quantity Available</label>
                                <input type="number" name="ticket_types[${newIndex}][quantity]" 
                                       class="form-control" min="1" value="50" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Sale Start Date & Time</label>
                                <input type="datetime-local" name="ticket_types[${newIndex}][sale_start]" 
                                       class="form-control" value="">
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label>Sale End Date & Time</label>
                                <input type="datetime-local" name="ticket_types[${newIndex}][sale_end]" 
                                       class="form-control" value="">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="ticket_types[${newIndex}][description]" 
                                  class="form-control" rows="2"></textarea>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML("beforeend", ticketHtml);
        }
        
        function removeTicketType(button) {
            const ticketType = button.closest(".ticket-type");
            if (document.querySelectorAll(".ticket-type").length > 1) {
                ticketType.remove();
                document.querySelectorAll(".ticket-type").forEach((ticket, index) => {
                    ticket.querySelector("h3").textContent = `Ticket Type #${index + 1}`;
                });
            } else {
                showNotification("You must have at least one ticket type", "error");
            }
        }
        
        function confirmDelete() {
            if (confirm("Are you sure you want to delete this event? This action cannot be undone.")) {
                window.location.href = "Delete_Event.php?EventID=<?= $eventID ?>";
            }
        }
        
        function showNotification(message, type) {
            const notification = document.getElementById("notification");
            notification.textContent = message;
            notification.className = `notification ${type}`;
            notification.style.display = "block";
            
            setTimeout(() => {
                notification.style.display = "none";
            }, 5000);
        }
        
        // Image preview functionality
        document.getElementById("event_image").addEventListener("change", function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Remove existing preview if any
                    const existingPreview = document.querySelector(".image-preview");
                    if (existingPreview) {
                        existingPreview.remove();
                    }
                    
                    // Create new preview
                    const preview = document.createElement("div");
                    preview.className = "image-preview";
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Image Preview" style="max-width: 200px; max-height: 150px; border-radius: 8px; margin: 10px 0; border: 2px solid var(--primary);">
                        <p class="image-info">New image preview</p>
                    `;
                    
                    // Insert after the file input
                    const fileInput = document.getElementById("event_image");
                    fileInput.parentNode.insertBefore(preview, fileInput.nextSibling);
                };
                reader.readAsDataURL(file);
            }
        });
        
        document.getElementById("edit-event-form").addEventListener("submit", function(e) {
            // Validate image upload
            const imageFile = document.getElementById("event_image").files[0];
            if (imageFile) {
                const maxSize = 5 * 1024 * 1024; // 5MB
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                
                if (imageFile.size > maxSize) {
                    e.preventDefault();
                    showNotification("Image file size must be less than 5MB", "error");
                    return;
                }
                
                if (!allowedTypes.includes(imageFile.type)) {
                    e.preventDefault();
                    showNotification("Please upload a valid image file (JPG, PNG, GIF, or WebP)", "error");
                    return;
                }
            }
            
            const startDate = new Date(document.getElementById("start_date").value);
            const endDate = new Date(document.getElementById("end_date").value);
            
            if (endDate <= startDate) {
                e.preventDefault();
                showNotification("End date must be after start date", "error");
            }
            
            const tickets = document.querySelectorAll(".ticket-type");
            let hasError = false;
            
            tickets.forEach(ticket => {
                const price = parseFloat(ticket.querySelector("input[name*=\"price\"]").value);
                const quantity = parseInt(ticket.querySelector("input[name*=\"quantity\"]").value);
                const saleStart = ticket.querySelector("input[name*=\"sale_start\"]").value;
                const saleEnd = ticket.querySelector("input[name*=\"sale_end\"]").value;
                
                if (price < 0) {
                    showNotification("Ticket price cannot be negative", "error");
                    hasError = true;
                }
                
                if (quantity < 1) {
                    showNotification("Ticket quantity must be at least 1", "error");
                    hasError = true;
                }
                
                if (saleStart && saleEnd && new Date(saleEnd) <= new Date(saleStart)) {
                    showNotification("Ticket sale end date must be after sale start date", "error");
                    hasError = true;
                }
            });
            
            if (hasError) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>