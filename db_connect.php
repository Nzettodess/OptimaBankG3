<?php
// Database configuration for XAMPP
$host = "localhost"; // XAMPP usually uses localhost
$username = "root";  // Default XAMPP MySQL username
$password = "";      // Default XAMPP MySQL password is empty
$database = "optimabank"; // Your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for proper encoding
$conn->set_charset("utf8mb4");

// Error reporting (for development only)
error_reporting(E_ALL);
ini_set('display_errors',1);
?>