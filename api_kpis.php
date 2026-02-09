<?php
require_once "db.php";

$machineId = isset($_GET["machine_id"]) ? (int)$_GET["machine_id"] : 0;
$minutes = isset($_GET["minutes"]) ? max(1, (int)$_GET["minutes"]) : 60;

$where = "WHERE ts_minute >= (NOW() - INTERVAL ? MINUTE)";
$params = [$minutes];
$types = "i";

if ($machineId > 0) {
  $where .= " AND machine_id = ?";
  $params[] = $machineId;
  $types .= "i";
}

$sql = "
SELECT
  SUM(planned_sec) AS planned_sec,
  SUM(downtime_sec) AS downtime_sec,
  SUM(good_count) AS good,
  SUM(scrap_count) AS scrap,
  SUM(CASE WHEN status='RUN' THEN planned_sec - downtime_sec ELSE 0 END) AS runtime_sec
FROM production_minute
$where
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get avg ideal cycle time for machine selection
$ideal = 2.50;
if ($machineId > 0) {
  $r = $conn->query("SELECT ideal_cycle_time_sec FROM machines WHERE id=".(int)$machineId);
  if ($r && $r->num_rows) $ideal = (float)$r->fetch_assoc()["ideal_cycle_time_sec"];
} else {
  // average ideal cycle time across active machines
  $r = $conn->query("SELECT AVG(ideal_cycle_time_sec) AS avg_ideal FROM machines WHERE is_active=1");
  if ($r && $r->num_rows) $ideal = (float)$r->fetch_assoc()["avg_ideal"];
}

$planned = (float)($row["planned_sec"] ?? 0);
$downtime = (float)($row["downtime_sec"] ?? 0);
$good = (int)($row["good"] ?? 0);
$scrap = (int)($row["scrap"] ?? 0);
$total = $good + $scrap;
$runtime = (float)($row["runtime_sec"] ?? 0);

$availability = ($planned > 0) ? max(0, min(1, ($planned - $downtime) / $planned)) : 0;
$performance = ($runtime > 0) ? max(0, min(1, ($ideal * $total) / $runtime)) : 0;
$quality = ($total > 0) ? max(0, min(1, $good / $total)) : 0;
$oee = $availability * $performance * $quality;

header("Content-Type: application/json");
echo json_encode([
  "planned_sec" => (int)$planned,
  "downtime_sec" => (int)$downtime,
  "good" => $good,
  "scrap" => $scrap,
  "availability" => $availability,
  "performance" => $performance,
  "quality" => $quality,
  "oee" => $oee
]);
