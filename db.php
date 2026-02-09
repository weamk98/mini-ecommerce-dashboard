<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "mini_shop";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
