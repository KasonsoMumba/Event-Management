<?php
session_start();
include 'db_connect.php';

// Ensure Organizer is logged in
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Organizer') {
    header("Location: Login.html");
    exit();
}

$organizer_id = $_SESSION['UserID'];

// Validate event ID
if (!isset($_GET['EventID'])) {
    die("Event ID missing.");
}
$event_id = $_GET['EventID'];

// Fetch event to verify ownership
$eventQuery = "SELECT * FROM Events WHERE EventID = ? AND OrganizerID = ?";
$stmt = $conn->prepare($eventQuery);
$stmt->bind_param("ii", $event_id, $organizer_id);
$stmt->execute();
$eventResult = $stmt->get_result();
$event = $eventResult->fetch_assoc();

if (!$event) {
    die("Event not found or you do not have permission.");
}
$stmt->close();

// Handle form submission to update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['Status'];
    $updateQuery = "UPDATE Events SET Status = ? WHERE EventID = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("si", $newStatus, $event_id);
    $stmt->execute();
    $stmt->close();
    header("Location: Edit_Event_Types.php?EventID=$event_id");
    exit();
}

// Handle adding a ticket type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ticket'])) {
    $ticketType = $_POST['Type'];
    $price = floatval($_POST['Price']);
    
    if ($ticketType && $price > 0) {
        $insertQuery = "INSERT INTO TicketTypes (EventID, Type, Price) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("isd", $event_id, $ticketType, $price);
        $stmt->execute();
        $stmt->close();
        header("Location: Edit_Event_Types.php?EventID=$event_id");
        exit();
    }
}

// Handle ticket deletion
if (isset($_GET['delete_ticket'])) {
    $ticketTypeID = $_GET['delete_ticket'];
    $deleteQuery = "DELETE FROM TicketTypes WHERE TicketTypeID = ? AND EventID = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("ii", $ticketTypeID, $event_id);
    $stmt->execute();
    $stmt->close();
    header("Location: Edit_Event_Types.php?EventID=$event_id");
    exit();
}

// Fetch all ticket types for this event
$ticketsQuery = "SELECT * FROM TicketTypes WHERE EventID = ?";
$stmt = $conn->prepare($ticketsQuery);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$ticketsResult = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event Tickets & Status</title>
    <link rel="stylesheet" href="../stylesheets.css">
</head>
<body>
    <h1>Edit Event: <?= htmlspecialchars($event['Title']) ?></h1>

    <form method="POST">
        <label for="Status">Change Event Status:</label>
        <select name="Status" id="Status" required>
            <?php
            $statuses = ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'];
            foreach ($statuses as $status): ?>
                <option value="<?= $status ?>" <?= $event['Status'] === $status ? 'selected' : '' ?>>
                    <?= $status ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="update_status">Update Status</button>
    </form>

    <hr>

    <h2>Ticket Types</h2>
    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Price (USD)</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($ticket = $ticketsResult->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($ticket['Type']) ?></td>
                    <td>$<?= number_format($ticket['Price'], 2) ?></td>
                    <td>
                        <a href="Edit_Event_Types.php?EventID=<?= $event_id ?>&delete_ticket=<?= $ticket['TicketTypeID'] ?>"
                           onclick="return confirm('Are you sure you want to delete this ticket type?');"
                           class="btn btn-danger">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <h3>Add New Ticket Type</h3>
    <form method="POST">
        <input type="text" name="Type" placeholder="e.g., VIP, Regular" required>
        <input type="number" name="Price" step="0.01" min="0" placeholder="Price" required>
        <button type="submit" name="add_ticket">Add Ticket</button>
    </form>

    <br><a href="Organizer_Dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
