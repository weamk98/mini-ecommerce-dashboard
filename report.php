<?php
require_once "db.php";

// Defaults: today 00:00 to now
$to = isset($_GET["to"]) ? $_GET["to"] : date("Y-m-d\TH:i");
$from = isset($_GET["from"]) ? $_GET["from"] : date("Y-m-d\T00:00");
$machineId = isset($_GET["machine_id"]) ? (int)$_GET["machine_id"] : 0;

// Convert HTML datetime-local to SQL DATETIME
$fromSql = str_replace("T", " ", $from) . ":00";
$toSql   = str_replace("T", " ", $to) . ":00";

// Load machines for dropdown
$machines = [];
$res = $conn->query("SELECT id, name FROM machines WHERE is_active=1 ORDER BY name");
while ($m = $res->fetch_assoc()) $machines[] = $m;

// Report query (group by hour)
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

// Get ideal cycle time for selected machine or average
$ideal = 2.50;
if ($machineId > 0) {
  $r = $conn->query("SELECT ideal_cycle_time_sec FROM machines WHERE id=".(int)$machineId);
  if ($r && $r->num_rows) $ideal = (float)$r->fetch_assoc()["ideal_cycle_time_sec"];
} else {
  $r = $conn->query("SELECT AVG(ideal_cycle_time_sec) AS avg_ideal FROM machines WHERE is_active=1");
  if ($r && $r->num_rows) $ideal = (float)$r->fetch_assoc()["avg_ideal"];
}

function clamp01($x) { return max(0, min(1, $x)); }

$report = [];
$tot = ["planned"=>0,"downtime"=>0,"good"=>0,"scrap"=>0,"runtime"=>0];

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

  $report[] = [
    "bucket" => $r["bucket"],
    "good" => $good,
    "scrap" => $scrap,
    "downtime_min" => (int)round($downtime/60),
    "a" => $a,
    "p" => $p,
    "q" => $q,
    "oee" => $oee,
  ];

  $tot["planned"] += $planned;
  $tot["downtime"] += $downtime;
  $tot["good"] += $good;
  $tot["scrap"] += $scrap;
  $tot["runtime"] += $runtime;
}

$totTotal = $tot["good"] + $tot["scrap"];
$totA = ($tot["planned"]>0) ? clamp01(($tot["planned"]-$tot["downtime"])/$tot["planned"]) : 0;
$totP = ($tot["runtime"]>0) ? clamp01(($ideal*$totTotal)/$tot["runtime"]) : 0;
$totQ = ($totTotal>0) ? clamp01($tot["good"]/$totTotal) : 0;
$totOEE = $totA*$totP*$totQ;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>KPI Report</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
  <h1>KPI Report</h1>
  <p class="subtitle">Hourly aggregation (OEE + Output + Downtime)</p>

  <div class="controls card">
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
      <div class="row">
        <label>From</label>
        <input type="datetime-local" name="from" value="<?= htmlspecialchars($from) ?>">
      </div>
      <div class="row">
        <label>To</label>
        <input type="datetime-local" name="to" value="<?= htmlspecialchars($to) ?>">
      </div>
      <div class="row">
        <label>Machine</label>
        <select name="machine_id">
          <option value="0" <?= $machineId===0 ? "selected":"" ?>>All machines</option>
          <?php foreach($machines as $m): ?>
            <option value="<?= (int)$m["id"] ?>" <?= $machineId===(int)$m["id"] ? "selected":"" ?>>
              <?= htmlspecialchars($m["name"]) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit">Generate</button>

      <a class="ghost" href="export_csv.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&machine_id=<?= (int)$machineId ?>">
        Export CSV
      </a>

      <a class="ghost" href="index.php">Back to Dashboard</a>
    </form>
  </div>

  <div class="grid">
    <div class="card kpi"><div class="kpi-title">OEE (Total)</div><div class="kpi-value"><?= number_format($totOEE*100,1) ?>%</div></div>
    <div class="card kpi"><div class="kpi-title">Availability</div><div class="kpi-value"><?= number_format($totA*100,1) ?>%</div></div>
    <div class="card kpi"><div class="kpi-title">Performance</div><div class="kpi-value"><?= number_format($totP*100,1) ?>%</div></div>
    <div class="card kpi"><div class="kpi-title">Quality</div><div class="kpi-value"><?= number_format($totQ*100,1) ?>%</div></div>
  </div>

  <div class="card">
    <h2>OEE over time</h2>
    <canvas id="oeeLine" height="90"></canvas>
  </div>

  <div class="card">
    <h2>Hourly table</h2>
    <table>
      <thead>
      <tr>
        <th>Hour</th>
        <th class="right">Good</th>
        <th class="right">Scrap</th>
        <th class="right">Downtime (min)</th>
        <th class="right">A%</th>
        <th class="right">P%</th>
        <th class="right">Q%</th>
        <th class="right">OEE%</th>
      </tr>
      </thead>
      <tbody>
      <?php if (count($report) === 0): ?>
        <tr><td colspan="8" class="empty">No data in this range. Run simulate_tick.php a few times.</td></tr>
      <?php else: ?>
        <?php foreach($report as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r["bucket"]) ?></td>
            <td class="right"><?= (int)$r["good"] ?></td>
            <td class="right"><?= (int)$r["scrap"] ?></td>
            <td class="right"><?= (int)$r["downtime_min"] ?></td>
            <td class="right"><?= number_format($r["a"]*100,1) ?></td>
            <td class="right"><?= number_format($r["p"]*100,1) ?></td>
            <td class="right"><?= number_format($r["q"]*100,1) ?></td>
            <td class="right"><strong><?= number_format($r["oee"]*100,1) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const labels = <?= json_encode(array_map(fn($x)=>$x["bucket"], $report)) ?>;
const oee = <?= json_encode(array_map(fn($x)=>round($x["oee"]*100,2), $report)) ?>;

const ctx = document.getElementById("oeeLine");
new Chart(ctx, {
  type: "line",
  data: { labels, datasets: [{ label: "OEE %", data: oee, tension: 0.25 }] },
  options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } }
});
</script>
</body>
</html>
