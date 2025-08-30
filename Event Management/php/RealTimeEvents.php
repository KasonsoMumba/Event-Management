<?php
include 'db_connect.php';
$organizerID = $_SESSION['UserID'];
$result = $conn->query("SELECT * FROM EVENTS WHERE OrganizerID = $organizerID");

while($event = $result->fetch_assoc()):
?>
<tr>
    <td><?= $event['Title'] ?></td>
    <td><?= $event['Start_Date'] ?></td>
    <td><?= $event['Venue_Name'] ?></td>
    <td><?= getRegistrationCount($event['EventID']) // Implement this function ?></td>
    <td><?= $event['Status'] ?></td>
    <td>
        <a href="Event_Details.php?EventID=<?= $event['EventID'] ?>" class="btn btn-secondary">View</a>
        <a href="Edit_Event.php?EventID=<?= $event['EventID'] ?>" class="btn btn-primary">Edit</a>
    </td>
</tr>
<?php endwhile; ?>