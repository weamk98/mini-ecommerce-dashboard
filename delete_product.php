<?php
require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: index.php");
exit();
