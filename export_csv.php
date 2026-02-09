<?php
require_once "db.php";

$to = isset($_GET["to"]) ? $_GET["to"] : date("Y-m-d\TH:i");
$from = isset($_GET["from"]) ? $_GET["from"] : date("Y-m-d\T00:00");
$machineId = isset($_GET["machine_id"]) ? (int)$_GET["machine_id"] : 0;

$fromSql = str_replace("T", " ", $from) . ":00";
$toSql   = str_replace("T", " ", $to) . ":00";

// Ideal cycle time
$ideal = 2.50;
if ($machineId > 0) {
  $r = $conn->query("SELECT ideal_cycle_time_sec FROM machines WHERE id=".(int)$machineId);
  if ($r && $r->num_rows) $ideal = (float)$r->fetch_assoc()["ideal_cycle_time_sec"];
} else {
  $r = $conn->query("SELECT AVG(ideal_cycle_time_sec) AS avg_ideal FROM machines WHERE is_active=1");
  if ($r && $r->num_rows) $ideal = (float)$r->fetch_assoc()["avg_ideal"];
}

function clamp01($x) { return max(0, min(1, $x)); }

$sql = "
SELECT
  DATE_FORMAT(ts_minute, '%Y-%m-%d %H:00:00') AS bucket,
  SUM(planned_sec) AS planned_sec,
  SUM(downtime_sec) AS downtime_sec,
  SUM(good_count) AS good,
  SUM(scrap_count) AS scrap,
  SUM(CASE WHEN status='RUN' THEN planned_sec - downtime_sec ELSE 0 END) AS runtime_sec
FROM production_minute
WHERE ts_minute BETWEEN ? AND ?
";
$params = [$fromSql, $toSql];
$types = "ss";

if ($machineId > 0) {
  $sql .= " AND machine_id = ? ";
  $params[] = $machineId;
  $types .= "i";
}

$sql .= " GROUP BY bucket ORDER BY bucket ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$filename = "kpi_report_" . date("Ymd_His") . ".csv";

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

$out = fopen("php://output", "w");

// Header
fputcsv($out, ["hour", "good", "scrap", "downtime_min", "availability_pct", "performance_pct", "quality_pct", "oee_pct"]);

foreach ($rows as $r) {
  $planned = (float)$r["planned_sec"];
  $downtime = (float)$r["downtime_sec"];
  $good = (int)$r["good"];
  $scrap = (int)$r["scrap"];
  $total = $good + $scrap;
  $runtime = (float)$r["runtime_sec"];

  $a = ($planned > 0) ? clamp01(($planned - $downtime) / $planned) : 0;
  $p = ($runtime > 0) ? clamp01(($ideal * $total) / $runtime) : 0;
  $q = ($total > 0) ? clamp01($good / $total) : 0;
  $oee = $a * $p * $q;

  fputcsv($out, [
    $r["bucket"],
    $good,
    $scrap,
    (int)round($downtime/60),
    round($a*100, 2),
    round($p*100, 2),
    round($q*100, 2),
    round($oee*100, 2),
  ]);
}

fclose($out);
exit;
