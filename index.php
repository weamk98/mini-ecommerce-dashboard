<?php require_once "db.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Production Monitoring Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <h1>Production Monitoring Dashboard</h1>
    <p class="subtitle">Live-ish KPIs (OEE, Output, Downtime)</p>

    <div class="controls card">
      <div class="row">
        <label>Machine</label>
        <select id="machine">
          <option value="0">All machines</option>
          <?php
          $res = $conn->query("SELECT id, name FROM machines WHERE is_active=1 ORDER BY name");
          while ($m = $res->fetch_assoc()) {
            echo '<option value="'.(int)$m["id"].'">'.htmlspecialchars($m["name"]).'</option>';
          }
          ?>
        </select>
      </div>

      <div class="row">
        <label>Range</label>
        <select id="minutes">
          <option value="60">Last 60 minutes</option>
          <option value="240">Last 4 hours</option>
          <option value="480">Last 8 hours</option>
          <option value="1440">Today (24h)</option>
        </select>
      </div>

      <button id="refreshBtn">Refresh</button>
      <a class="ghost" href="simulate_tick.php" target="_blank">Simulate 1 minute data</a>
      <a class="ghost" href="report.php">KPI Report + CSV</a>
    </div>

    <div class="grid">
      <div class="card kpi"><div class="kpi-title">OEE</div><div class="kpi-value" id="kpi_oee">–</div></div>
      <div class="card kpi"><div class="kpi-title">Availability</div><div class="kpi-value" id="kpi_a">–</div></div>
      <div class="card kpi"><div class="kpi-title">Performance</div><div class="kpi-value" id="kpi_p">–</div></div>
      <div class="card kpi"><div class="kpi-title">Quality</div><div class="kpi-value" id="kpi_q">–</div></div>

      <div class="card kpi"><div class="kpi-title">Good</div><div class="kpi-value" id="kpi_good">–</div></div>
      <div class="card kpi"><div class="kpi-title">Scrap</div><div class="kpi-value" id="kpi_scrap">–</div></div>
      <div class="card kpi"><div class="kpi-title">Downtime (min)</div><div class="kpi-value" id="kpi_dt">–</div></div>
    </div>

    <div class="card">
      <h2>OEE Breakdown</h2>
      <canvas id="oeeChart" height="90"></canvas>
    </div>
  </div>

<script>
let chart;

function pct(x){ return (x*100).toFixed(1) + "%"; }

async function loadKPIs() {
  const machineId = document.getElementById("machine").value;
  const minutes = document.getElementById("minutes").value;

  const res = await fetch(`api_kpis.php?machine_id=${machineId}&minutes=${minutes}`);
  const data = await res.json();

  document.getElementById("kpi_oee").textContent = pct(data.oee);
  document.getElementById("kpi_a").textContent = pct(data.availability);
  document.getElementById("kpi_p").textContent = pct(data.performance);
  document.getElementById("kpi_q").textContent = pct(data.quality);

  document.getElementById("kpi_good").textContent = data.good;
  document.getElementById("kpi_scrap").textContent = data.scrap;
  document.getElementById("kpi_dt").textContent = Math.round(data.downtime_sec / 60);

  const ctx = document.getElementById("oeeChart");
  const values = [data.availability*100, data.performance*100, data.quality*100, data.oee*100];
  const labels = ["Availability", "Performance", "Quality", "OEE"];

  if (chart) chart.destroy();
  chart = new Chart(ctx, {
    type: "bar",
    data: { labels, datasets: [{ label: "Percent", data: values }] },
    options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } }
  });
}

document.getElementById("refreshBtn").addEventListener("click", loadKPIs);
document.getElementById("machine").addEventListener("change", loadKPIs);
document.getElementById("minutes").addEventListener("change", loadKPIs);

loadKPIs();
setInterval(loadKPIs, 15000); // auto refresh every 15s
</script>
</body>
</html>
