<?php
include 'db_connect.php';

$Email = $_POST['Email'];
$Password = password_hash($_POST['Password'],PASSWORD_DEFAULT);
$Firstname = $_POST['Firstname'];
$Lastname = $_POST['Lastname'];
$ContactNumber = $_POST['ContactNumber'];
$Role = $_POST['Role'];

$stmt = $conn->prepare("INSERT INTO Users(Email,Password_hash,Firstname,lastname,ContactNumber,Role)VALUES(?,?,?,?,?,?)");
$stmt->bind_param("ssssss",$Email,$Password,$Firstname,$Lastname,$ContactNumber,$Role);


if($stmt->execute()){
   header("location:../	HTML/index.html");
}else{
   echo "Error: ". $stmt->error;
}

$stmt->close();
$conn->close();

?>