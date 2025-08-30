<?php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Optional: Redirect to attendee dashboard instead of showing error
    header("Location: Attendee-dashboard.php");
    exit();
}

if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Attendee') {
    header("Location: Login.php");
    exit();
}

$attendee_id = $_SESSION['UserID'];
$event_id = $_POST['EventID'] ?? null;
$ticket_type_id = $_POST['TicketTypeID'] ?? null;
$quantity = $_POST['Quantity'] ?? 1;

// Validate input
if (!$event_id || !$ticket_type_id || $quantity < 1) {
    die("Invalid input");
}

// Optional: Check if ticket type belongs to event
$check = $conn->prepare("SELECT Price FROM TicketTypes WHERE TicketTypeID = ? AND EventID = ?");
$check->bind_param("ii", $ticket_type_id, $event_id);
$check->execute();
$result = $check->get_result();
if ($result->num_rows === 0) {
    die("Invalid ticket type for this event");
}
$row = $result->fetch_assoc();
$price = $row['Price'];
$total = $price * $quantity;
$check->close();

// Save purchase (you should have a Purchases or Tickets table)
$insert = $conn->prepare("INSERT INTO Purchases (UserID, EventID, TicketTypeID, Quantity, TotalPrice) VALUES (?, ?, ?, ?, ?)");
$insert->bind_param("iiiid", $attendee_id, $event_id, $ticket_type_id, $quantity, $total);
$insert->execute();
$insert->close();

echo "Ticket purchased successfully. Total: $" . number_format($total, 2);
echo "<br><a href='Attendee-dashboard.php'>Return to Dashboard</a>";

$conn->close();
?>
