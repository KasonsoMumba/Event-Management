<?php
session_start();
include 'db_connect.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

// Validate required fields
$required = ['Email', 'Password'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        die("Error: Missing $field");
    }
}

$Email = $_POST['Email'];
$Password = $_POST['Password'];

// Prepare SQL to get user with role
$stmt = $conn->prepare("SELECT UserID, Password_hash, Role, FirstName FROM Users WHERE Email = ?");
$stmt->bind_param("s", $Email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($Password, $user['Password_hash'])) {
        // Set session variables
        $_SESSION['UserID'] = $user['UserID'];
        $_SESSION['Role'] = $user['Role'];
        $_SESSION['Email'] = $Email;
        $_SESSION['FirstName'] = $user['FirstName'];
        $_SESSION['LastName'] = $row['LastName'];

        // Redirect based on role
        switch ($user['Role']) {
            case 'Admin':
                header("Location: Admin-Dashboard.php");
                break;
            case 'Organizer':
                header("Location: Organizer-Dashboard.php");
                break;
            case 'Attendee':
                header("Location: Attendee-Dashboard.php");
                break;
            default:
                header("Location: Login_failed.html");
                break;
        }
        exit();
    }
}

// Login failed
header("Location: Login_failed.html");
exit();

// Clean up
$stmt->close();
$conn->close();
?>