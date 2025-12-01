@extends('dean.deansidebar')
<link rel="stylesheet" href="{{ asset('css/deandashboard.css') }}">
@section('title', 'Dean’s Dashboard')

@section('content')
<style>
  :root {
    --brand: #000000;
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

  .dd-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr)); /* 4 cards now */
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
    background: rgba(102,0,0,0.08);
  }

  .stat-icon-circle i {
    color: #660000;
    font-size: 18px;
  }

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
    background: #ffffff;
    border-radius: 18px;
    padding: 18px 18px 16px;
    box-shadow: 0 8px 20px rgba(0,0,0,.05);
    border: 1px solid #e5e7eb;
    color: #111827;
  }

  .dd-insights h3 {
    color: var(--brand);
    margin: 0 0 10px;
    font-weight: 800;
  }

  .dd-insight {
    background: #f9fafb;
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
    color: #111827;
  }

  .dd-insight p {
    margin: 0;
    font-size: .78rem;
    color: #4b5563;
  }

  @media (max-width: 1199.98px) {
    .dd-kpis {
      grid-template-columns: repeat(2, minmax(0, 1fr));
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
    <p class="dd-subtitle">Performance Overview — Designated College</p>

    {{-- =============== TOP CARDS =============== --}}
    <div class="dd-kpis">
      <div class="stat-modern-card">
        <div>
          <div class="stat-head">TOTAL STUDENTS</div>
          <div class="stat-value" id="totalStudents">
            {{ number_format($totalStudents) }}
          </div>
          <div class="stat-sub">Current semester</div>
        </div>
        <div class="stat-icon-circle">
          <i class="bi bi-person-fill"></i>
        </div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">DEAN’S LISTERS</div>
          <div class="stat-value" id="totalListers">
            {{ number_format($totalDeansListers) }}
          </div>
          <div class="stat-sub">This semester</div>
        </div>
        <div class="stat-icon-circle">
          <i class="bi bi-award"></i>
        </div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">LATIN HONORS (GRADUATING)</div>
          <div class="stat-value" id="honorsCount">
            {{ number_format($totalLatinHonors) }}
          </div>
          <div class="stat-sub">Summa / Magna / Cum</div>
        </div>
        <div class="stat-icon-circle">
          <i class="bi bi-mortarboard-fill"></i>
        </div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">ACADEMIC COMPETITION PARTICIPANTS</div>
          <div class="stat-value" id="competitorsCount">
            {{ array_sum($competitionCounts) }}
          </div>
          <div class="stat-sub">Within the college</div>
        </div>
        <div class="stat-icon-circle">
          <i class="bi bi-trophy-fill"></i>
        </div>
      </div>
    </div>

    {{-- =============== 4 CHARTS =============== --}}
    <div class="dd-charts">
      <div class="dd-card">
        <h3>Dean’s Lister by Major</h3>
        <div class="chart-box"><canvas id="growthChart"></canvas></div>
      </div>

      <div class="dd-card">
        <h3>Enrolled Students by College</h3>
        <div class="chart-box"><canvas id="collegeChart"></canvas></div>
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

    {{-- =============== Table + Insights =============== --}}
    <div class="dd-bottom">
      <div class="dd-table">
        <h3>Top Performing Programs</h3>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Program</th>
                <th>Dean’s Listers</th>
                <th>Avg GPA</th>
                <th>Growth Rate</th>
              </tr>
            </thead>
            <tbody id="programTable">
              {{-- JS will fill this from $topPrograms --}}
            </tbody>
          </table>
        </div>
      </div>

      <div class="dd-insights">
        <h3>Performance Insights</h3>
        <div class="dd-insight">
          <h4>📊 Major Distribution</h4>
          <p>The Dean’s Lister pie chart shows which majors contribute most within this college.</p>
        </div>
        <div class="dd-insight">
          <h4>🎓 Graduation Quality</h4>
          <p>Latin honors distribution reflects approved Latin honors applications from this college.</p>
        </div>
        <div class="dd-insight">
          <h4>🏆 Participation</h4>
          <p>Competition metrics can be wired to your events/competition module next.</p>
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
  const palette = ['#660000', '#940000', '#4c0000'];
  const grid    = 'rgba(0,0,0,0.08)';

  // ======== DATA FROM PHP ========
  const pieMajorLabels   = @json($pieMajorLabels);
  const pieMajorValues   = @json($pieMajorValues);
  const pieMajorColors   = @json($pieMajorColors);


  const collegeLabels    = @json($collegeLabels);
  const collegeCounts    = @json($collegeCounts);

  const honorsLabels     = @json($honorsLabels);
  const honorsCounts     = @json($honorsCounts);

  const competitionLabels= @json($competitionLabels);
  const competitionCounts= @json($competitionCounts);

  const topPrograms      = @json($topPrograms);

  // ======== TABLE: TOP PROGRAMS ========
  const tbody = document.getElementById('programTable');
  if (tbody && Array.isArray(topPrograms)) {
    topPrograms.forEach(row => {
      const tr = document.createElement('tr');
      const growthText = row.growth_rate === null ? '—' : row.growth_rate + '%';

      tr.innerHTML = `
        <td>${row.program_name}</td>
        <td>${row.total_listers}</td>
        <td>${row.avg_gpa ?? '—'}</td>
        <td>${growthText}</td>
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

// ========= Dean’s Lister By Major – PIE CHART =========
const growthCtx = document.getElementById('growthChart');
if (growthCtx) {
  new Chart(growthCtx, {
    type: 'pie',
    data: {
      labels: pieMajorLabels,
      datasets: [{
        data: pieMajorValues,
        backgroundColor: pieMajorColors,
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
          position: 'bottom',
          labels: {
            usePointStyle: true,
            pointStyle: 'circle',
            boxWidth: 14,
            font: { weight: '600' },
            color: '#000'
          }
        },
        tooltip: { enabled: true }
      }
    }
  });
}

  // ===== Enrolled Students by College (bar) =====
  const collegeCtx = document.getElementById('collegeChart');
  if (collegeCtx) {
    new Chart(collegeCtx,{
      type: 'bar',
      data: {
        labels: collegeLabels,
        datasets: [{
          label: "Enrolled Students",
          data: collegeCounts,
          backgroundColor: palette,
          borderColor: palette,
          borderWidth: 2,
          borderRadius: 8
        }]
      },
      options: commonXY
    });
  }

  // ===== Honors pie =====
  const honorsCtx = document.getElementById('honorsChart');
  if (honorsCtx) {
    new Chart(honorsCtx,{
      type: 'pie',
      data: {
        labels: honorsLabels,
        datasets: [{
          data: honorsCounts,
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
  }

  // ===== Competition radar =====
  const compCtx = document.getElementById('competitionChart');
  if (compCtx) {
    new Chart(compCtx,{
      type: 'radar',
      data: {
        labels: competitionLabels,
        datasets: [{
          label: "Competitions Joined",
          data: competitionCounts,
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
  }
});
</script>
@endsection
