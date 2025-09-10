<?php
session_start();
include 'db_connect.php';
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Admin') {
    header("Location: ../HTML/Login.html");
    exit();
}

// User registration stats
$user_stats = $conn->query("
    SELECT Role, COUNT(*) AS total 
    FROM Users 
    GROUP BY Role
")->fetch_all(MYSQLI_ASSOC);

// Payment stats
$payment_stats = $conn->query("
    SELECT Payment_Status, COUNT(*) AS total
    FROM Registrations
    GROUP BY Payment_Status
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports</title>
<link rel="stylesheet" href="Admin-Dashboard.css">
</head>
<body>
<h1>System Reports</h1>

<h2>User Statistics</h2>
<table border="1" cellpadding="10">
<tr><th>Role</th><th>Total Users</th></tr>
<?php foreach($user_stats as $row): ?>
<tr><td><?= $row['Role'] ?></td><td><?= $row['total'] ?></td></tr>
<?php endforeach; ?>
</table>

<h2>Payment Status</h2>
<table border="1" cellpadding="10">
<tr><th>Status</th><th>Total</th></tr>
<?php foreach($payment_stats as $row): ?>
<tr><td><?= $row['Payment_Status'] ?></td><td><?= $row['total'] ?></td></tr>
<?php endforeach; ?>
</table>

</body>
</html>
