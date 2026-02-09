<?php
require_once "db.php";

$now = new DateTime();
$now->setTime((int)$now->format("H"), (int)$now->format("i"), 0);
$ts = $now->format("Y-m-d H:i:s");

$res = $conn->query("SELECT id, ideal_cycle_time_sec FROM machines WHERE is_active=1");
$machines = $res->fetch_all(MYSQLI_ASSOC);

$ins = $conn->prepare("
  INSERT INTO production_minute (machine_id, ts_minute, status, good_count, scrap_count, downtime_sec, planned_sec)
  VALUES (?, ?, ?, ?, ?, ?, 60)
  ON DUPLICATE KEY UPDATE
    status=VALUES(status),
    good_count=VALUES(good_count),
    scrap_count=VALUES(scrap_count),
    downtime_sec=VALUES(downtime_sec)
");

foreach ($machines as $m) {
  $machineId = (int)$m["id"];
  $ideal = (float)$m["ideal_cycle_time_sec"];

  $r = mt_rand(1, 100);
  if ($r <= 75) $status = "RUN";
  elseif ($r <= 90) $status = "IDLE";
  else $status = "DOWN";

  $downtime = 0;
  $good = 0;
  $scrap = 0;

  if ($status === "RUN") {
    $theoretical = (int) floor(60 / $ideal);
    $actual = (int) floor($theoretical * (mt_rand(70, 100) / 100));
    $scrap = (int) floor($actual * (mt_rand(0, 5) / 100));
    $good = $actual - $scrap;
  } elseif ($status === "DOWN") {
    $downtime = mt_rand(20, 60);
  } else { // IDLE
    $downtime = mt_rand(0, 10);
  }

  $ins->bind_param("issiii", $machineId, $ts, $status, $good, $scrap, $downtime);
  $ins->execute();
}

$ins->close();
echo "Simulated minute: $ts ✅";
