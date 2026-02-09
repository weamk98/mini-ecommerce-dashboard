<?php
// db.php
$host = "localhost";
$user = "root";
$password = ""; // XAMPP default is empty
$database = "mini_shop";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

// Ensure correct charset for umlauts etc.
$conn->set_charset("utf8mb4");
