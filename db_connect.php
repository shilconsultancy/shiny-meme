<?php
// db_connect.php

$servername = "localhost"; // Your database server name, usually 'localhost'
$username = "u324770578_admin";     // Your database username
$password = "c*ST;+A3";         // Your database password
$dbname = "u324770578_booking"; // The name of the database you created

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Optional: Set character set to UTF-8
$conn->set_charset("utf8mb4");
?>