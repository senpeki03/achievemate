@extends('dean.deansidebar')
<link rel="stylesheet" href="{{ asset('css/deandashboard.css') }}">
@section('title', 'Dean’s Dashboard')

@section('content')
<style>
  :root {
    --brand: #000000; /* same as Chairperson dashboard :root */
  }

  body {
    background: var(--brand);
  }

  .dd-root {
    padding: 0.75rem 0 2.5rem;
  }

  .dd-root .container {
    max-width: 1280px;
  }

  .dd-title {
    font-weight: 800;
    color: #ffffff;
    letter-spacing: .3px;
    text-shadow: 0 1px 2px rgba(0,0,0,.35);
    margin-bottom: .25rem;
  }

  .dd-subtitle {
    color: #e5e7eb;
    margin-bottom: 1.5rem;
  }

  /* KPI cards – match stat-card style of Chairperson */
  .dd-kpis {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }

  .stat-modern-card {
    background: #ffffff;
    border-radius: 18px;
    padding: 18px 20px;
    border: 1px solid #d1d5db;
    box-shadow: 0 8px 20px rgba(0,0,0,.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
  }

  .stat-head {
    font-size: .75rem;
    font-weight: 600;
    letter-spacing: .04em;
    color: #6b7280;
  }

  .stat-value {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--brand);
    line-height: 1.1;
  }

  .stat-sub {
    font-size: .75rem;
    color: #9ca3af;
  }

  .stat-icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(102,0,0,0.08); /* subtle maroon tint */
  }

  .stat-icon-circle i {
    color: #660000;
    font-size: 18px;
  }

  /* Cards for charts and table/insights – mimic .panel */
  .dd-charts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }

  .dd-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #d1d5db;
    padding: 18px 18px 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,.05);
  }

  .dd-card h3 {
    font-size: 1rem;
    font-weight: 800;
    margin-bottom: 10px;
    color: var(--brand);
  }

  .chart-box {
    height: 280px;
  }

  .dd-bottom {
    display: grid;
    grid-template-columns: 3fr 2fr;
    gap: 16px;
    margin-top: 8px;
  }

  .dd-table {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #d1d5db;
    padding: 18px 18px 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,.05);
  }

  .dd-table h3 {
    color: var(--brand);
    margin: 0 0 8px;
    font-weight: 800;
  }

  .dd-table table {
    width: 100%;
    border-collapse: collapse;
    font-size: .9rem;
  }

  .dd-table thead th {
    text-align: left;
    padding: 8px 10px;
    border-bottom: 1px solid #e5e7eb;
    color: #4b5563;
    font-weight: 700;
    font-size: .8rem;
    text-transform: uppercase;
  }

  .dd-table tbody td {
    padding: 7px 10px;
    border-bottom: 1px solid #f3f4f6;
    color: #111827;
  }

  .dd-table tbody tr:last-child td {
    border-bottom: none;
  }

  .dd-insights {
    background: var(--brand);
    border-radius: 18px;
    padding: 18px 18px 16px;
    box-shadow: 0 12px 30px rgba(0,0,0,.45);
    color: #e5e7eb;
  }

  .dd-insights h3 {
    color: #ffffff;
    margin: 0 0 10px;
    font-weight: 800;
  }

  .dd-insight {
    background: rgba(255,255,255,0.04);
    border-radius: 12px;
    padding: 10px 12px;
    margin-bottom: 8px;
  }

  .dd-insight:last-child {
    margin-bottom: 0;
  }

  .dd-insight h4 {
    margin: 0 0 4px;
    font-size: .9rem;
    color: #f9fafb;
  }

  .dd-insight p {
    margin: 0;
    font-size: .78rem;
    color: #d1d5db;
  }

  @media (max-width: 1199.98px) {
    .dd-kpis {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  @media (max-width: 991.98px) {
    .dd-charts {
      grid-template-columns: 1fr;
    }
    .dd-bottom {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 767.98px) {
    .dd-kpis {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 575.98px) {
    .dd-kpis {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="dd-root">
  <div class="container">

    <h1 class="dd-title">Dean’s Dashboard</h1>
    <p class="dd-subtitle">Overall College Performance Overview — Across All Programs</p>

    <!-- =============== TOP 5 CARDS (Registrar style) =============== -->
    <div class="dd-kpis">
      <div class="stat-modern-card">
        <div>
          <div class="stat-head">TOTAL STUDENTS</div>
          <div class="stat-value" id="totalStudents">1,480</div>
          <div class="stat-sub">Current semester</div>
        </div>
        <div class="stat-icon-circle">
          <i class="bi bi-person-fill"></i>
        </div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">TOTAL USERS</div>
          <div class="stat-value" id="totalUsers">4</div>
          <div class="stat-sub">+8.3% Since last month</div>
        </div>
        <div class="stat-icon-circle">
          <i class="bi bi-people-fill"></i>
        </div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">DEAN’S LISTERS</div>
          <div class="stat-value" id="totalListers">215</div>
          <div class="stat-sub">This semester</div>
        </div>
        <div class="stat-icon-circle">
          <i class="bi bi-award"></i>
        </div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">LATIN HONORS (GRADUATING)</div>
          <div class="stat-value" id="honorsCount">45</div>
          <div class="stat-sub">Summa/Magna/Cum</div>
        </div>
        <div class="stat-icon-circle">
          <i class="bi bi-mortarboard-fill"></i>
        </div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">ACADEMIC COMPETITION PARTICIPANTS</div>
          <div class="stat-value" id="competitorsCount">28</div>
          <div class="stat-sub">College-wide</div>
        </div>
        <div class="stat-icon-circle">
          <i class="bi bi-trophy-fill"></i>
        </div>
      </div>
    </div>

    <!-- =============== 4 CHARTS =============== -->
    <div class="dd-charts">
      <div class="dd-card">
        <h3>Dean’s Lister Growth (College-Wide)</h3>
        <div class="chart-box"><canvas id="growthChart"></canvas></div>
      </div>

      <div class="dd-card">
        <h3>Program-wise Dean’s Listers</h3>
        <div class="chart-box"><canvas id="programChart"></canvas></div>
      </div>

      <div class="dd-card">
        <h3>Latin Honors Distribution</h3>
        <div class="chart-box"><canvas id="honorsChart"></canvas></div>
      </div>

      <div class="dd-card">
        <h3>Academic Competitions by Department</h3>
        <div class="chart-box"><canvas id="competitionChart"></canvas></div>
      </div>
    </div>

    <!-- =============== Table + Insights =============== -->
    <div class="dd-bottom">
      <div class="dd-table">
        <h3>Top Performing Programs</h3>
        <div class="table-responsive">
          <table>
            <thead>
            <tr>
              <th>Program</th><th>Dean’s Listers</th><th>Avg GPA</th><th>Growth Rate</th>
            </tr>
            </thead>
            <tbody id="programTable"></tbody>
          </table>
        </div>
      </div>

      <div class="dd-insights">
        <h3>Performance Insights</h3>
        <div class="dd-insight">
          <h4>📈 Steady Academic Improvement</h4>
          <p>Dean’s Listers increased by 18% vs last semester.</p>
        </div>
        <div class="dd-insight">
          <h4>🎓 Graduation Quality</h4>
          <p>21% of graduates receive Latin honors.</p>
        </div>
        <div class="dd-insight">
          <h4>🏆 Participation</h4>
          <p>Academic competitions up 25% YoY.</p>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function ensureChartJs(cb){
  if (window.Chart) return cb();
  const s = document.createElement('script');
  s.src = "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js";
  s.onload = cb;
  document.head.appendChild(s);
})(function init(){
  // === shared palette (same feel as Chairperson dashboard) ===
  const palette = ['#660000', '#940000', '#4c0000'];
  const grid    = 'rgba(0,0,0,0.08)';

  // table data
  const programs      = ["IT","CS","IS","Eng","BA","Ed"];
  const programListers= [50,45,40,30,28,22];
  const programGPA    = [1.45,1.48,1.50,1.60,1.55,1.58];
  const growthRates   = [10,8,6,4,5,3];

  const tbody = document.getElementById('programTable');
  if (tbody){
    programs.forEach((p,i)=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${p}</td>
        <td>${programListers[i]}</td>
        <td>${programGPA[i]}</td>
        <td>${growthRates[i]}%</td>
      `;
      tbody.appendChild(tr);
    });
  }

  const commonXY = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: grid }, ticks: { color: '#000' } },
      y: { grid: { color: grid }, ticks: { color: '#000', precision: 0 }, beginAtZero: true }
    }
  };

  // Growth line chart
  new Chart(document.getElementById('growthChart'),{
    type: 'line',
    data: {
      labels: ["1st Sem 2024","2nd Sem 2024","1st Sem 2025"],
      datasets: [{
        label: "Dean’s Listers",
        data: [180,205,215],
        borderColor: palette[1],
        backgroundColor: 'rgba(148,0,0,0.16)',
        fill: true,
        tension: .35,
        pointBackgroundColor: '#ffffff',
        pointBorderColor: palette[1],
        pointBorderWidth: 2,
        pointRadius: 4
      }]
    },
    options: commonXY
  });

  // Program-wise bar chart
  new Chart(document.getElementById('programChart'),{
    type: 'bar',
    data: {
      labels: programs,
      datasets: [{
        label: "Dean’s Listers",
        data: programListers,
        backgroundColor: palette,
        borderColor: palette,
        borderWidth: 2,
        borderRadius: 8
      }]
    },
    options: commonXY
  });

  // Honors doughnut chart
  new Chart(document.getElementById('honorsChart'),{
    type: 'pie',
    data: {
      labels: ["Summa","Magna","Cum Laude"],
      datasets: [{
        data: [5,18,22],
        backgroundColor: palette,
        borderColor: '#ffffff',
        borderWidth: 2,
        hoverOffset: 8
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "bottom",
          labels: {
            usePointStyle: true,
            pointStyle: 'rectRounded',
            boxWidth: 14,
            color: '#000'
          }
        }
      }
    }
  });

  // Competition radar chart
  new Chart(document.getElementById('competitionChart'),{
    type: 'radar',
    data: {
      labels: ["IT Dept","CS Dept","BA Dept","Eng Dept","Ed Dept"],
      datasets: [{
        label: "Competitions Joined",
        data: [8,6,4,5,5],
        borderColor: palette[1],
        backgroundColor: 'rgba(148,0,0,0.18)',
        pointBackgroundColor: palette[1],
        pointBorderColor: '#ffffff',
        pointRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: "bottom" } },
      scales: {
        r: {
          grid: { color: 'rgba(0,0,0,0.1)' },
          angleLines: { color: 'rgba(0,0,0,0.15)' },
          pointLabels: { color: '#000' },
          ticks: { display: false, beginAtZero: true }
        }
      }
    }
  });
});
</script>
@endsection
