<?php
session_start();

if(!isset($_SESSION['UserID']) || $_SESSION['Role'] != 'Organizer'){
 header("Location: OrganizerLogin.html");
 exit();

include 'db_connect.php';

$Upcoming_Events_Count = 0;
$Total_Registrations = 0;
$Total_Revenue = 0;

$count_Stmt = $conn->prepare("SELECT COUNT(*) FROM Events WHERE OrganizerID = ? AND start_dateTime > NOW()");
$count_stmt->bind_param("i", $_SESSION['UserID']);
$count_stmt->execute();
$count_stmt->bind_result($Upcoming_Event_Count);
$count_stmt->fetch();
$count_stmt->close();



$stats_stmt = $conn->prepare("SELECT COUNT(r.RegistationID) As reg_count, SUM(t.amount) As revenue FROM Registrations r
JOIN Tickets t ON r.TicketID = t.TicketID
JOIN Events e ON t.EventID = e.EventID
WHERE e.OrganizerID = ?");


$stats_stmt->bind_param("i", $_SESSION['UserID']);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
if($stats_row = $stats_result->fetch_assoc()){
   $total_registrations = $stats_row['reg_count'];
   $total_revenue = $stats_row['revenue'] ?? 0;}

$stats_stmt->close();

$Events = [];
$Event_stmt = $conn->prepare("SELECT e.*, COUNT(r.registrationID) AS  registration_count FROM Events e
LEFT JOIN Tickets t ON e.EventID = t.EventID
LEFT JOIN Registration r ON t.TicketID = r.TicketID
WHERE e.OrganizerID = ?
GROUP BY e.EventID
ORDER BY e.start_datetime DESC");


$event_stmt->bind_param("i", $_SESSION['UserID']);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

while($row = $event_Result->fetch_assoc()){
   $events[] = $row;
}
$event_stmt->close();
$conn->close();

include 'Organize dashboard.html';
?>
