<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Organizer') {
    header("Location: Login.html");
    exit();
}

if (!isset($_GET['EventID'])) {
    die("Event ID not provided.");
}

$eventID = intval($_GET['EventID']);
$organizerID = $_SESSION['UserID'];

// Ensure the event belongs to the logged-in organizer
$stmt = $conn->prepare("DELETE FROM Events WHERE EventID = ? AND OrganizerID = ?");
$stmt->bind_param("ii", $eventID, $organizerID);

if ($stmt->execute()) {
    header("Location: Organizer-dashboard.php?deleted=1");
    exit();
} else {
    echo "Error deleting event.";
}

$stmt->close();
$conn->close();
?>
