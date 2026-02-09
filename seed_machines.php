<?php
require_once "db.php";

$machines = [
  ["Tube Mill A", 2.20],
  ["Tube Mill B", 2.60],
  ["Cutting Line 1", 1.80],
];

$stmt = $conn->prepare("INSERT INTO machines (name, ideal_cycle_time_sec) VALUES (?, ?)");
foreach ($machines as $m) {
  $stmt->bind_param("sd", $m[0], $m[1]);
  $stmt->execute();
}
$stmt->close();

echo "Machines seeded ✅";
