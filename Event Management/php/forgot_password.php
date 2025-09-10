<?php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['Email']);

    if (!empty($email)) {
        // Check if user exists
        $stmt = $conn->prepare("SELECT UserID FROM Users WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Insert reset request
            $stmt = $conn->prepare("INSERT INTO password_reset_requests (UserID) VALUES (?)");
            $stmt->bind_param("i", $user['UserID']);
            $stmt->execute();

            echo "<p style='color:green;'>Your request has been submitted. An admin will reset your password.</p>";
        } else {
            echo "<p style='color:red;'>No account found with that email.</p>";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password</title>
<style>
body { font-family: Arial, sans-serif; background:#f5f6fa; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
.form-container { background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.15); width:350px; text-align:center; }
input[type=email], input[type=submit] { width:100%; padding:10px; margin:10px 0; border:1px solid #ccc; border-radius:6px; }
input[type=submit] { background:#3498db; color:white; font-weight:bold; cursor:pointer; }
input[type=submit]:hover { background:#2980b9; }
</style>
</head>
<body>
<div class="form-container">
    <h2>Forgot Password</h2>
    <form method="POST">
        <input type="email" name="Email" placeholder="Enter your email" required>
        <input type="submit" value="Submit Request">
    </form>
    <p><a href="../HTML/Login.html">Back to Login</a></p>
</div>
</body>
</html>
