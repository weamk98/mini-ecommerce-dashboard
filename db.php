<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "production_monitoring";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) die("DB connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");
