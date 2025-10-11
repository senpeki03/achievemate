@extends('registrar.registrarsidebar')
<link rel="stylesheet" href="{{ asset('css/admindashboard.css') }}">

@section('content')
<div class="container py-4">

  <!-- Toggle and Title -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0 text-white">Dashboard Summary</h4>
  </div>

  <div class="row g-4 mb-4">
    @php
      $stats = [
        [
          'count' => number_format($totalStudents),
          'label' => 'TOTAL STUDENTS',
          'growth' => '+12.5%',
          'icon' => 'bi-person-fill',
          'color' => 'linear-gradient(135deg, #ff416c, #ff4b2b)'
        ],
        [
          'count' => number_format($totalUsers),
          'label' => 'TOTAL USERS',
          'growth' => '+8.3%',
          'icon' => 'bi-people-fill',
          'color' => 'linear-gradient(135deg, #f7971e, #ffd200)'
        ],
        [
          'count' => number_format($loginCount),
          'label' => 'TOTAL LOGINS',
          'growth' => '+3.48%',
          'icon' => 'bi-box-arrow-in-right',
          'color' => 'linear-gradient(135deg, #11998e, #38ef7d)'
        ],
        [
          'count' => number_format($logoutCount),
          'label' => 'TOTAL LOGOUTS',
          'growth' => '+1.2%',
          'icon' => 'bi-box-arrow-right',
          'color' => 'linear-gradient(135deg, #0575e6, #00f260)'
        ]
      ];
    @endphp

    @foreach ($stats as $stat)
    <div class="col-md-3">
      <div class="card stat-modern-card p-3 shadow-sm border-0">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <small class="text-muted text-uppercase fw-bold">{{ $stat['label'] }}</small>
            <h3 class="fw-bold mt-1 text-dark">{{ $stat['count'] }}</h3>
            <p class="text-success small mb-0 mt-2">
              <i class="bi bi-arrow-up-right"></i> {{ $stat['growth'] }} 
              <span class="text-muted">Since last month</span>
            </p>
          </div>
          <div class="stat-icon-circle" style="background: {{ $stat['color'] }}">
            <i class="bi {{ $stat['icon'] }}"></i>
          </div>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  <!-- 📊 Chart -->
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card shadow-sm border-0 rounded-4 chart-card">
        <div class="card-body">
          <h5 class="fw-bold mb-3 chart-title">Enrolled Students by Department</h5>
          <div style="height: 250px;">
            <canvas id="enrollmentChart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- 📊 Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartCtx = document.getElementById('enrollmentChart').getContext('2d');

// Ensure you have correct data in `chartData` before the chart is created
const chartData = {
  labels: {!! json_encode($departments) !!},
  datasets: [{
    label: 'Number of Enrolled Students',
    data: {!! json_encode($departmentCounts) !!}, // Ensure departmentCounts is not empty
    borderColor: '#013A63',
    backgroundColor: 'rgba(1, 58, 99, 0.85)',  // Solid fill color
    tension: 0.4,
    fill: true,
    pointRadius: 5,
    pointHoverRadius: 8,
    borderWidth: 2 // Ensure border is visible
  }]
};

// Check the chartData before rendering
console.log(chartData.labels);
console.log(chartData.datasets[0].data);

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { enabled: true }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1, // Ensure whole number steps
        callback: function(value) {
          return Number.isInteger(value) ? value : null;  // Skip non-integers
        },
        color: '#000'
      },
      grid: { color: 'rgba(0,0,0,0.1)' }
    },
    x: {
      ticks: { color: '#000' },
      grid: { color: 'rgba(0,0,0,0.1)' }
    }
  }
};

// Create the chart
const enrollmentChart = new Chart(chartCtx, {
  type: 'line',  // Line chart type
  data: chartData,
  options: chartOptions
});
</script>

@endsection
