<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db_connect.php';

if(!isset($_SESSION['UserID']) || $_SESSION['Role'] != 'Organizer'){
    header("Location: ../Organizer-dashboard.html");
    exit();
}

// Get form data
$Title = $_POST['Title'];
$Description = $_POST['Description'];
$Start_Date = date('Y-m-d H:i:s', strtotime($_POST['Start_Date']));
$End_Date = date('Y-m-d H:i:s', strtotime($_POST['End_Date']));
$Venue = $_POST['Venue'];
$Capacity = intval($_POST['Capacity']);
$Status = $_POST['Status'] ?? 'Draft'; // Default to Draft if not provided
$OrganizerID = $_SESSION['UserID'];

// Prepare statement with CORRECT column names
$stmt = $conn->prepare("INSERT INTO Events (OrganizerID, Title, Description, Start_Date, End_Date, Venue_Name, Capacity, Status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

// Check for prepare errors
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("isssssis", $OrganizerID, $Title, $Description, $Start_Date, $End_Date, $Venue, $Capacity, $Status);

if($stmt->execute()){
    $EventID = $conn->insert_id;

    // Process tickets - USING TICKETTYPES TABLE FOR CONSISTENCY
    if(isset($_POST['Ticket_Type']) && is_array($_POST['Ticket_Type'])) {
        $Ticket_Types = $_POST['Ticket_Type'];
        $Prices = $_POST['Ticket_Price'];
        $Quantities = $_POST['Ticket_Quantity'];
        $Descriptions = $_POST['Ticket_Description'] ?? array();
        $Sale_Starts = $_POST['Ticket_Sale_Start'] ?? array();
        $Sale_Ends = $_POST['Ticket_Sale_End'] ?? array();
        
        for($i = 0; $i < count($Ticket_Types); $i++){
            // Skip empty entries
            if(empty(trim($Ticket_Types[$i]))) continue;
            
            $price_val = floatval($Prices[$i]);
            $ticketDescription = $Descriptions[$i] ?? '';
            
            // Set default sale periods if not provided
            $saleStart = !empty($Sale_Starts[$i]) ? date('Y-m-d', strtotime($Sale_Starts[$i])) : date('Y-m-d');
            $saleEnd = !empty($Sale_Ends[$i]) ? date('Y-m-d', strtotime($Sale_Ends[$i])) : date('Y-m-d', strtotime('+1 month'));
            
            // Use TicketTypes table with correct column names
            $ticket_stmt = $conn->prepare("INSERT INTO TicketTypes (EventID, Type, Price, Quantity_Available, Description, Sale_Start, Sale_End) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ticket_stmt->bind_param("isdisss", $EventID, $Ticket_Types[$i], $price_val, $Quantities[$i], 
                                    $ticketDescription, $saleStart, $saleEnd);
            
            if(!$ticket_stmt->execute()) {
                error_log("Ticket insert error: " . $ticket_stmt->error);
            }
            $ticket_stmt->close();
        }
    }

    header("Location: Organizer-dashboard.php?EventID=".$EventID);
    exit();
} else {
    // Detailed error logging
    error_log("Event insertion error: " . $stmt->error);
    die("Error: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>