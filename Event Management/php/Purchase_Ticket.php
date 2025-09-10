<?php
session_start();
include 'db_connect.php';

// Get EventID from URL parameter
$eventID = isset($_GET['EventID']) ? intval($_GET['EventID']) : 0;
$ticketTypeID = isset($_GET['TicketTypeID']) ? intval($_GET['TicketTypeID']) : 0;

if (!$eventID) {
    die("No event selected.");
}

// Redirect to the ticket registration page
if ($ticketTypeID > 0) {
    header("Location: Ticket-Registration.php?event_id=" . $eventID . "&ticket_type_id=" . $ticketTypeID);
} else {
    header("Location: Ticket-Registration.php?event_id=" . $eventID);
}
exit();
?>
