<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Failed</title>
    <link rel="stylesheet" href="styles.css"> <!-- Optional: link to your CSS -->
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .fail-container {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0px 4px 10px rgba(0,0,0,0.15);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        h1 {
            color: #e74c3c;
            margin-bottom: 15px;
        }
        p {
            margin: 10px 0;
            color: #333;
        }
        .btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="fail-container">
        <h1>Login Failed</h1>
        <p>Invalid email or password. Please try again.</p>
        <p>If you don’t have an account, you can register below.</p>
        <a href="../HTML/Login.html" class="btn">Back to Login</a>
        <a href="../HTML/User-registration.html" class="btn" style="background:#2ecc71;">Register</a>
    </div>
</body>
</html>
