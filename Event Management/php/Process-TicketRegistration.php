<?php
session_start();
include 'db_connect.php';

// Validate user session
if (!isset($_SESSION['UserID'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'You must be logged in to register for events.']));
}

$user_id = $_SESSION['UserID'];

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

// Debug: Log what's being received
error_log("Process-TicketRegistration Debug - event_id: " . $event_id);
error_log("Process-TicketRegistration Debug - payment_method: " . $payment_method);
error_log("Process-TicketRegistration Debug - tickets_json: " . $tickets_json);
error_log("Process-TicketRegistration Debug - All POST data: " . print_r($_POST, true));

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
        $registration_stmt->bind_param("iiisdssssssssssss", 
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
?>