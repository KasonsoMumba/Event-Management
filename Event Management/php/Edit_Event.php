<?php
session_start();
include 'db_connect.php';

// Check if user is logged in as organizer
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Organizer') {
    header("Location: Login.html");
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
    
    $updateStmt = $conn->prepare("UPDATE Events SET 
        Title = ?, 
        Description = ?, 
        Start_Date = ?, 
        End_Date = ?, 
        Venue_Name = ?, 
        Capacity = ?, 
        Status = ?
        WHERE EventID = ?");
    $updateStmt->bind_param("sssssisi", $title, $description, $startDate, $endDate, $venue, $capacity, $status, $eventID);
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
    
    // Redirect to dashboard after update
    header("Location: Organizer-dashboard.php");
    exit();
}

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Event Management System</title>
    <link rel="stylesheet" href="../stylesheets.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #4361ee; }
        .header h1 { color: #4361ee; margin: 0; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .btn { padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; transition: background-color 0.3s; border: none; cursor: pointer; }
        .btn-primary { background-color: #4361ee; color: white; }
        .btn-primary:hover { background-color: #3a56d4; }
        .btn-logout { background-color: #f72585; color: white; }
        .btn-logout:hover { background-color: #e91e63; }
        .btn-success { background-color: #38b000; color: white; }
        .btn-success:hover { background-color: #2d8c00; }
        .btn-danger { background-color: #f72585; color: white; }
        .btn-danger:hover { background-color: #e91e63; }
        .notification { display: none; padding: 15px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; }
        .notification.error { background-color: #ffe6e6; color: #d32f2f; border: 1px solid #f44336; }
        .notification.success { background-color: #e6f7e6; color: #2e7d32; border: 1px solid #4caf50; }
        .form-container { background: white; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 30px; }
        .form-section { margin-bottom: 40px; padding-bottom: 30px; border-bottom: 1px solid #e0e0e0; }
        .form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .form-section h2 { color: #4361ee; margin-bottom: 20px; font-size: 24px; }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-col { flex: 1; min-width: 0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 16px; transition: border-color 0.3s; }
        .form-control:focus { outline: none; border-color: #4361ee; }
        textarea.form-control { resize: vertical; min-height: 100px; }
        .status-options { display: flex; gap: 20px; flex-wrap: wrap; }
        .status-option { display: flex; align-items: center; gap: 8px; }
        .status-option input[type="radio"] { margin: 0; }
        .ticket-types-container { margin-bottom: 20px; }
        .ticket-type { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #e0e0e0; }
        .ticket-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .ticket-header h3 { margin: 0; color: #333; }
        .remove-ticket { background: #f72585; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; }
        .remove-ticket:hover { background: #e91e63; }
        .add-ticket-btn { background: #4361ee; color: white; border: none; border-radius: 5px; padding: 12px 20px; cursor: pointer; font-size: 16px; font-weight: bold; display: flex; align-items: center; gap: 8px; transition: background-color 0.3s; }
        .add-ticket-btn:hover { background: #3a56d4; }
        .action-buttons { display: flex; justify-content: space-between; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; }
        @media (max-width: 768px) { 
            .form-row { flex-direction: column; gap: 0; } 
            .form-col { width: 100%; } 
            .header { flex-direction: column; gap: 15px; text-align: center; } 
            .user-info { justify-content: center; } 
            .status-options { flex-direction: column; gap: 10px; } 
            .action-buttons { flex-direction: column; gap: 15px; } 
            .btn { width: 100%; text-align: center; } 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit Event: ' . htmlspecialchars($event['Title']) . '</h1>
            <div class="user-info">
                <span>Welcome, ' . htmlspecialchars($_SESSION['FirstName'] ?? 'Organizer') . '</span>
                <a href="Organizer-dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </div>
        </div>
        
        <div id="notification" class="notification"></div>
        
        <form method="POST" id="edit-event-form">
            <div class="form-container">
                <div class="form-section">
                    <h2>Event Information</h2>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="title">Event Title</label>
                                <input type="text" id="title" name="title" class="form-control" 
                                       value="' . htmlspecialchars($event['Title']) . '" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" 
                                          rows="4" required>' . htmlspecialchars($event['Description']) . '</textarea>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="venue">Venue</label>
                                <input type="text" id="venue" name="venue" class="form-control" 
                                       value="' . htmlspecialchars($event['Venue_Name']) . '" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="capacity">Capacity</label>
                                <input type="number" id="capacity" name="capacity" class="form-control" 
                                       min="1" value="' . $event['Capacity'] . '" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="start_date">Start Date & Time</label>
                                <input type="datetime-local" id="start_date" name="start_date" class="form-control" 
                                       value="' . date('Y-m-d\TH:i', strtotime($event['Start_Date'])) . '" required>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="end_date">End Date & Time</label>
                                <input type="datetime-local" id="end_date" name="end_date" class="form-control" 
                                       value="' . date('Y-m-d\TH:i', strtotime($event['End_Date'])) . '" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Event Status</label>
                        <div class="status-options">
                            <div class="status-option">
                                <input type="radio" id="status-upcoming" name="status" value="Upcoming" 
                                    ' . ($event['Status'] === 'Upcoming' ? 'checked' : '') . '>
                                <label for="status-upcoming">Upcoming</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" id="Draft" name="status" value="Draft" 
                                    ' . ($event['Status'] === 'Draft' ? 'checked' : '') . '>
                                <label for="status-Draft">Draft</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" id="Published" name="status" value="Published" 
                                    ' . ($event['Status'] === 'Published' ? 'checked' : '') . '>
                                <label for="status-completed">Published</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" id="status-cancelled" name="status" value="Cancelled" 
                                    ' . ($event['Status'] === 'Cancelled' ? 'checked' : '') . '>
                                <label for="status-cancelled">Cancelled</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Ticket Types & Pricing</h2>
                    <div class="ticket-types-container" id="ticket-types-container">';

$ticketCount = count($tickets);
if ($ticketCount > 0) {
    foreach ($tickets as $index => $ticket) {
        // Format dates for datetime-local input
        $saleStart = !empty($ticket['Sale_Start']) ? date('Y-m-d\TH:i', strtotime($ticket['Sale_Start'])) : '';
        $saleEnd = !empty($ticket['Sale_End']) ? date('Y-m-d\TH:i', strtotime($ticket['Sale_End'])) : '';
        
        echo '<div class="ticket-type">
                <div class="ticket-header">
                    <h3>Ticket Type #' . ($index + 1) . '</h3>
                    <button type="button" class="remove-ticket" onclick="removeTicketType(this)">×</button>
                </div>
                <input type="hidden" name="ticket_types[' . $index . '][id]" value="' . $ticket['TicketTypeID'] . '">
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Ticket Name</label>
                            <input type="text" name="ticket_types[' . $index . '][name]" 
                                   class="form-control" value="' . htmlspecialchars($ticket['Type']) . '" required>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label>Price (ZMK)</label>
                            <input type="number" name="ticket_types[' . $index . '][price]" 
                                   class="form-control" min="0" step="0.01" 
                                   value="' . floatval($ticket['Price']) . '" required>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label>Quantity Available</label>
                            <input type="number" name="ticket_types[' . $index . '][quantity]" 
                                   class="form-control" min="0" 
                                   value="' . $ticket['Quantity_Available'] . '" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Sale Start Date & Time</label>
                            <input type="datetime-local" name="ticket_types[' . $index . '][sale_start]" 
                                   class="form-control" value="' . $saleStart . '">
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label>Sale End Date & Time</label>
                            <input type="datetime-local" name="ticket_types[' . $index . '][sale_end]" 
                                   class="form-control" value="' . $saleEnd . '">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="ticket_types[' . $index . '][description]" 
                              class="form-control" rows="2">' . htmlspecialchars($ticket['Description'] ?? '') . '</textarea>
                </div>
            </div>';
    }
} else {
    echo '<div class="ticket-type">
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
        </div>';
}

echo '</div>
                    
                    <button type="button" class="add-ticket-btn" onclick="addTicketType()">
                        <span>+</span> Add Ticket Type
                    </button>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete Event</button>
                    <button type="submit" class="btn btn-success">Update Event</button>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        let ticketCount = ' . ($ticketCount ?: 1) . ';
        
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
                window.location.href = "Delete_Event.php?EventID=' . $eventID . '";
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
        
        document.getElementById("edit-event-form").addEventListener("submit", function(e) {
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
</html>';

$conn->close();
?>