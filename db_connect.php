<?php
// db_connect.php - Simple Database connection for RoadSense Admin Panel

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hazard_mapping";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

?>