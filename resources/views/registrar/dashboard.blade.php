@extends('registrar.registrarsidebar')
<link rel="stylesheet" href="{{ asset('css/admindashboard.css') }}">

@section('content')
<div class="container py-4">

  {{-- ================== PAGE TITLE ================== --}}
  <h4 class="fw-bold text-center mb-4" style="color:#2d8bce;">Registrar Analytics Dashboard</h4>

  {{-- ================== TOP STATS (STATIC) ================== --}}
  <div class="row g-4 mb-4">

    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0 rounded-4 h-100" style="background:#fff;">
        <div class="card-body text-center">
          <div class="text-muted small">Total Students Enrolled</div>
          <div class="display-6 fw-bold" style="color:#013a63;">4,520</div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0 rounded-4 h-100" style="background:#fff;">
        <div class="card-body text-center">
          <div class="text-muted small">Students per Department</div>
          <select id="deptSelect" class="form-select form-select-sm mx-auto mb-1" style="max-width:220px;">
            <option value="IT" selected>College of IT</option>
            <option value="Education">College of Education</option>
            <option value="Business">College of Business</option>
            <option value="Engineering">College of Engineering</option>
            <option value="Criminology">College of Criminology</option>
          </select>
          <div class="display-6 fw-bold" id="deptCount" style="color:#013a63;">1,120</div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0 rounded-4 h-100" style="background:#fff;">
        <div class="card-body text-center">
          <div class="text-muted small">Top Performing Programs</div>
          <div class="fs-3 fw-bold" style="color:#013a63;">BSIT, BSEd</div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0 rounded-4 h-100" style="background:#fff;">
        <div class="card-body text-center">
          <div class="text-muted small">Total Graduates</div>
          <div class="display-6 fw-bold" style="color:#013a63;">980</div>
        </div>
      </div>
    </div>

  </div>

  {{-- ================== CHARTS ================== --}}
  <div class="row g-4 mb-4">

    {{-- Left: Trends --}}
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm border-0 rounded-4" style="background:#fff;">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0" style="color:#013a63;">Latin Honors / Dean's Honor Trends</h6>
            <select id="trendSelect" class="form-select form-select-sm" style="max-width:160px;">
              <option value="latin" selected>Latin Honors</option>
              <option value="deans">Dean's Listers</option>
              <option value="both">Both</option>
            </select>
          </div>
          <div style="height:260px;"><canvas id="trendChart"></canvas></div>
        </div>
      </div>
    </div>

    {{-- Right: Department Population --}}
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm border-0 rounded-4" style="background:#fff;">
        <div class="card-body">
          <h6 class="fw-bold mb-2" style="color:#013a63;">Department Population</h6>
          <div style="height:260px;"><canvas id="deptBarChart"></canvas></div>
        </div>
      </div>
    </div>

  </div>

  {{-- ================== TABLE (STATIC) ================== --}}
  <div class="card shadow-sm border-0 rounded-4 mb-4" style="background:#fff;">
    <div class="card-body">
      <h6 class="fw-bold text-center mb-3" style="color:#2d8bce;">Top Performing Programs</h6>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead style="background:#f5f6f8;">
            <tr class="text-muted">
              <th>Program</th>
              <th>Average GPA</th>
              <th>Dean's Listers</th>
              <th>Latin Honors</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>BS Information Technology</td><td>1.45</td><td>75</td><td>12</td></tr>
            <tr><td>BS Education</td><td>1.52</td><td>68</td><td>9</td></tr>
            <tr><td>BS Business Administration</td><td>1.63</td><td>40</td><td>6</td></tr>
            <tr><td>BS Criminology</td><td>1.75</td><td>25</td><td>4</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ================== INSIGHTS (STATIC) ================== --}}
  <div class="card border-0 rounded-4 shadow-sm" style="background:#0d4da3;">
    <div class="card-body text-white">
      <h6 class="fw-bold mb-2"><i class="bi bi-clipboard2-data me-2"></i>Insights</h6>
      <ul class="mb-0">
        <li>Total student population stands at <b>4,520</b> across all colleges this semester.</li>
        <li><b>College of IT</b> remains the largest department with over <b>1,100</b> enrolled students.</li>
        <li><b>BSIT</b> continues to lead as the top-performing program with consistent GPA averages below 1.5.</li>
        <li><b>980</b> graduates have completed their programs, showing strong academic throughput.</li>
        <li>Trends show an upward movement in both Dean’s Listers and Latin Honor recipients over the last 3 years.</li>
      </ul>
    </div>
  </div>

</div>

{{-- ================== SCRIPTS ================== --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script>
  // === Chairperson Palette ===
  const palette = ['#185c8c', '#013a63', '#2d8bce']; // [secondary, primary, accent]

  // === STATIC DATA ===
  const YEARS        = [2022, 2023, 2024, 2025];
  const DEPT_LABELS  = ['IT','Education','Business','Engineering','Criminology'];
  const DEPT_COUNTS  = [1120, 900, 950, 880, 650];
  const DEANS_DATA   = [100, 120, 140, 165];
  const LATIN_DATA   = [120, 135, 150, 170];

  // === Department Selector ===
  const deptSelect = document.getElementById('deptSelect');
  const deptCount  = document.getElementById('deptCount');
  const labelToIndex = { 'IT':0, 'Education':1, 'Business':2, 'Engineering':3, 'Criminology':4 };
  function updateDeptCount(){
    const idx = labelToIndex[deptSelect.value] ?? 0;
    deptCount.textContent = new Intl.NumberFormat().format(DEPT_COUNTS[idx]);
  }
  updateDeptCount();
  deptSelect.addEventListener('change', updateDeptCount);

  // === Trend Chart ===
  const trendCtx = document.getElementById('trendChart').getContext('2d');
  const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
      labels: YEARS,
      datasets: [
        {
          label: "Dean's Listers",
          data: DEANS_DATA,
          borderColor: palette[0],
          backgroundColor: 'rgba(24,92,140,0.15)',
          pointBackgroundColor: palette[0],
          tension: 0.35,
          borderWidth: 2,
          fill: true
        },
        {
          label: "Latin Honors",
          data: LATIN_DATA,
          borderColor: palette[2],
          backgroundColor: 'transparent',
          pointBackgroundColor: palette[2],
          tension: 0.35,
          borderWidth: 2,
          fill: false
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: true }, tooltip: { enabled: true } },
      scales: {
        y: { 
          beginAtZero: true,
          grid: { color: 'rgba(0,0,0,0.08)' },
          title: {
            display: true,
            text: 'Number of Honors',
            color: '#013a63',
            font: { weight: 'bold', size: 13 }
          }
        },
        x: { 
          grid: { color: 'rgba(0,0,0,0.08)' },
          title: {
            display: true,
            text: 'Academic Year',
            color: '#013a63',
            font: { weight: 'bold', size: 13 }
          }
        }
      }
    }
  });

  // === Toggle Line Visibility ===
  document.getElementById('trendSelect').addEventListener('change', (e) => {
    const v = e.target.value; // latin | deans | both
    trendChart.setDatasetVisibility(0, v !== 'latin');
    trendChart.setDatasetVisibility(1, v !== 'deans');
    trendChart.update();
  });

  // === Department Population Bar ===
  const deptCtx = document.getElementById('deptBarChart').getContext('2d');
  new Chart(deptCtx, {
    type: 'bar',
    data: {
      labels: DEPT_LABELS,
      datasets: [{
        label: 'Students',
        data: DEPT_COUNTS,
        backgroundColor: palette[0],
        borderColor: palette[1],
        borderWidth: 1.5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: true }, tooltip: { enabled: true } },
      scales: {
        y: { 
          beginAtZero: true,
          grid: { color: 'rgba(0,0,0,0.08)' },
          title: {
            display: true,
            text: 'Number of Students',
            color: '#013a63',
            font: { weight: 'bold', size: 13 }
          }
        },
        x: { 
          grid: { display: false },
          title: {
            display: true,
            text: 'Departments',
            color: '#013a63',
            font: { weight: 'bold', size: 13 }
          }
        }
      }
    }
  });
</script>
@endsection
