<?php
// Database configuration
// IMPORTANT: Update these values for your environment
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Set your database password here
define('DB_NAME', 'audit_system');

// Create connection
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>
