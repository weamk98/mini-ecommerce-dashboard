<?php
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

$name = trim($_POST['name'] ?? '');
$price = $_POST['price'] ?? '';

if ($name === '' || !is_numeric($price)) {
    header("Location: index.php");
    exit();
}

$price = (float)$price;

$stmt = $conn->prepare("INSERT INTO products (name, price) VALUES (?, ?)");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("sd", $name, $price);
$stmt->execute();
$stmt->close();

header("Location: index.php");
exit();
