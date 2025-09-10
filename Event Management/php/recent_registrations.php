<?php
require_once 'db_connect.php';

try {
    $stmt = $pdo->query("
        SELECT r.First_Name, r.Last_Name, e.Title as Event_Title, r.Registration_Date, t.Type as Ticket_Type, r.Status 
        FROM registrations r
        LEFT JOIN events e ON r.EventID = e.EventID
        LEFT JOIN tickettypes t ON r.TicketTypeID = t.TicketTypeID
        ORDER BY r.Registration_Date DESC 
        LIMIT 5
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['First_Name'] . " " . $row['Last_Name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Event_Title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Registration_Date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Ticket_Type']) . "</td>";
        echo "<td><span class='status status-" . strtolower($row['Status']) . "'>" . htmlspecialchars($row['Status']) . "</span></td>";
        echo "</tr>";
    }
} catch(PDOException $e) {
    echo "<tr><td colspan='5'>Error loading recent registrations</td></tr>";
    error_log("Error loading recent registrations: " . $e->getMessage());
}
?>