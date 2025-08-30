<?php
session_start();
include 'db_connect.php';

// Validate user session
if (!isset($_SESSION['UserID'])) {
    http_response_code(401); // Unauthorized
    exit(json_encode(['status' => 'error', 'message' => 'You must be logged in to register for events.']));
}

$user_id = $_SESSION['UserID'];

// Detect input: POST form or JSON payload
$input = $_POST;

if (empty($input)) {
    $json_input = file_get_contents('php://input');
    $input = json_decode($json_input, true);
    
    // If JSON decoding fails, try to parse as form data
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        parse_str($json_input, $input);
    }
}

// Validate required fields
$required_fields = ['event_id', 'payment_method', 'tickets'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        exit(json_encode(['status' => 'error', 'message' => "Missing required field: $field."]));
    }
}

// Sanitize input - FIXED: FILTER_SANITIZE_STRING is deprecated
$event_id = intval($input['event_id']);
$payment_method = htmlspecialchars($input['payment_method'], ENT_QUOTES, 'UTF-8');
$tickets = $input['tickets']; // Expecting array: ['TicketID' => quantity]

// FIX: Ensure tickets is an array, not a string
if (is_string($tickets)) {
    // Try to decode if it's a JSON string
    $decoded_tickets = json_decode($tickets, true);
    if ($decoded_tickets !== null && json_last_error() === JSON_ERROR_NONE) {
        $tickets = $decoded_tickets;
    } else {
        // If it's not JSON, try to parse as form data
        parse_str($tickets, $parsed_tickets);
        if (isset($parsed_tickets['tickets'])) {
            $tickets = $parsed_tickets['tickets'];
        }
    }
}

// FIX: If tickets is still not an array, create a default structure
if (!is_array($tickets)) {
    $tickets = [];
}

// Validate event exists
$event_stmt = $conn->prepare("SELECT * FROM Events WHERE EventID = ?");
$event_stmt->bind_param("i", $event_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

if ($event_result->num_rows === 0) {
    http_response_code(404);
    exit(json_encode(['status' => 'error', 'message' => 'Event not found.']));
}

// Start transaction
$conn->begin_transaction();

try {
    $total_tickets = 0;
    $total_amount = 0;
    $registration_details = [];

    // FIX: Check if tickets is iterable before foreach
    if (!is_iterable($tickets)) {
        throw new Exception("Invalid tickets data format.");
    }

    foreach ($tickets as $ticket_id => $qty) {
        $ticket_id = intval($ticket_id);
        $qty = intval($qty);
        
        if ($qty <= 0) continue;

        // Lock ticket row to prevent race conditions
        $ticket_stmt = $conn->prepare("SELECT * FROM Tickets WHERE TicketID = ? AND EventID = ? FOR UPDATE");
        $ticket_stmt->bind_param("ii", $ticket_id, $event_id);
        $ticket_stmt->execute();
        $ticket_result = $ticket_stmt->get_result();

        if ($ticket_result->num_rows === 0) {
            throw new Exception("Ticket not found or doesn't belong to this event.");
        }

        $ticket = $ticket_result->fetch_assoc();

        // Check availability
        if ($ticket['Quantity_Available'] < $qty) {
            throw new Exception("Not enough tickets available for: {$ticket['Ticket_Type']}");
        }

        $price = floatval($ticket['Price']);
        $amount = $price * $qty;
        $total_amount += $amount;
        $total_tickets += $qty;

        // Create registrations
        for ($i = 0; $i < $qty; $i++) {
            $insert = $conn->prepare("INSERT INTO Registrations 
                (EventID, UserID, TicketID, Registration_Date, Status, Payment_Status, Payment_Method, Amount_Paid)
                VALUES (?, ?, ?, CURDATE(), 'Confirmed', 'Paid', ?, ?)");
            $insert->bind_param("iiisd", $event_id, $user_id, $ticket_id, $payment_method, $price);

            if (!$insert->execute()) {
                throw new Exception("Failed to create registration record.");
            }

            $registration_id = $conn->insert_id;
            $registration_details[] = [
                'ticket_id' => $ticket_id,
                'registration_id' => $registration_id,
                'price' => $price
            ];
        }

        // Update ticket availability
        $update = $conn->prepare("UPDATE Tickets SET Quantity_Available = Quantity_Available - ? WHERE TicketID = ?");
        $update->bind_param("ii", $qty, $ticket_id);

        if (!$update->execute()) {
            throw new Exception("Failed to update ticket availability.");
        }
    }

    if ($total_tickets === 0) {
        throw new Exception("No valid tickets selected for registration.");
    }

    $conn->commit();

    // Success response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => "Registration completed successfully!",
        'details' => [
            'event_id' => $event_id,
            'total_tickets' => $total_tickets,
            'total_amount' => $total_amount,
            'payment_method' => $payment_method,
            'registrations' => $registration_details
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>