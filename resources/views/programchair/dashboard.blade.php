@extends('programchair.programchairsidebar')  
@section('title','Chairperson Analytics Dashboard')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
  // === MAIN STATS (from Controller) ===
  $totalStudents   = $totalStudents   ?? 0;
  $deansListers    = $deansListers    ?? 0;
  $latinHonors     = $latinHonors     ?? 0;
  $competitions    = $competitions    ?? 0;

  // === GRADUATION SUMMARY (Programs / Majors) ===
  // $gradPrograms is list of majors under the scope of the chair
  $gradPrograms   = $gradPrograms   ?? [];
  $gradApplicants = $gradApplicants ?? array_fill(0, count($gradPrograms), 0);
  $gradNotYet     = $gradNotYet     ?? array_fill(0, count($gradPrograms), 0);

  $colTotals = [];
  foreach ($gradPrograms as $i => $label) {
    $colTotals[$i] = ($gradApplicants[$i] ?? 0) + ($gradNotYet[$i] ?? 0);
  }

  $grandApplicants = array_sum($gradApplicants);
  $grandNotYet     = array_sum($gradNotYet);
  $grandOverall    = $grandApplicants + $grandNotYet;

  // === EXTRA METRICS ===
  $studentsOnLOA        = $studentsOnLOA        ?? 0;
  $latinHonorApplicants = $latinHonorApplicants ?? 0;
  $projectedGradRate    = $projectedGradRate    ?? 0;   // percent (0–100)

  // Sex distribution among applicants
  $maleApplicants   = $maleApplicants   ?? 0;
  $femaleApplicants = $femaleApplicants ?? 0;

  // For Dean’s Listers chart – use same labels as majors (fallback if empty)
  $programLabels  = $programLabels  ?? (count($gradPrograms) ? $gradPrograms : ['NO MAJOR FOUND']);
  $programListers = $programListers ?? (count($gradPrograms) ? $gradApplicants : [0]);

  // For Latin honors pie
  $latinLabels = $latinLabels ?? ['Summa Cum Laude','Magna Cum Laude','Cum Laude'];
  $latinCounts = $latinCounts ?? [0, 0, 0];
@endphp

<style>
  :root{ --brand:#000000; }

  body { background: var(--brand); }
  .container { max-width: 1280px; }
  .dash-title{ font-weight:800; color:#fff; letter-spacing:.3px; text-shadow:0 1px 2px rgba(0,0,0,.3); }
  .dashboard-wrap{ padding-top:.25rem; padding-bottom:1rem; }

  .card-grid{ display:grid; gap:16px; grid-template-columns:repeat(4,1fr); }
  .stat-card{ background:#fff; border-radius:18px; padding:18px 20px; border:1px solid #ccc; box-shadow:0 8px 20px rgba(0,0,0,.05); }
  .stat-card .value{ font-size:2rem; font-weight:800; color:var(--brand); }

  .panel{ background:#fff; border:1px solid #ccc; border-radius:18px; padding:18px; box-shadow:0 8px 20px rgba(0,0,0,.05); }
  .panel h6{ font-weight:800; color:var(--brand); }
  .panel .canvas-wrap{ height:320px; }
  .panel-lg .canvas-wrap{ height:360px; }

  .insights{ background:var(--brand); border-radius:12px; color:#fff; }
  .insights h6{ color:#fff; }
  .insights li{ color:#e0e4ff; }

  .mini-stat{
    border-radius:14px;
    border:1px solid #e5e7eb;
    padding:12px 14px;
    margin-bottom:10px;
    background:#fafafa;
  }
  .mini-stat-label{
    font-size:.8rem;
    text-transform:uppercase;
    letter-spacing:.05em;
    color:#6b7280;
  }
  .mini-stat-value{
    font-size:1.6rem;
    font-weight:800;
    color:#b91c1c;
  }
  .mini-stat-sub{
    font-size:.75rem;
    color:#9ca3af;
  }
</style>

<div class="container dashboard-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="dash-title mb-0">Chairperson Analytics Dashboard</h3>
  </div>

  <!-- Top stats -->
  <div class="card-grid mb-4">
    <div class="stat-card">
      <div class="d-flex align-items-center gap-2 text-muted"><i class="bi bi-people"></i> Total Students Enrolled</div>
      <div class="value mt-1">{{ number_format($totalStudents) }}</div>
    </div>
    <div class="stat-card">
      <div class="d-flex align-items-center gap-2 text-muted"><i class="bi bi-mortarboard"></i> Dean’s Listers</div>
      <div class="value mt-1">{{ number_format($deansListers) }}</div>
    </div>
    <div class="stat-card">
      <div class="d-flex align-items-center gap-2 text-muted"><i class="bi bi-award"></i> Latin Honors</div>
      <div class="value mt-1">{{ number_format($latinHonors) }}</div>
    </div>
    <div class="stat-card">
      <div class="d-flex align-items-center gap-2 text-muted"><i class="bi bi-trophy"></i> Competition Participants</div>
      <div class="value mt-1">{{ number_format($competitions) }}</div>
    </div>
  </div>

  <!-- === GRADUATION SUMMARY TABLE (straight row under cards) === -->
  <div class="panel mb-4">
    <h6 class="mb-3 text-center">
      4th Year BSIT Students’ Academic Standing and Application for Graduation<br>
      Second Semester, AY 2024–2025
    </h6>
    <div class="table-responsive">
      <table class="table table-bordered align-middle text-center">
        <thead class="table-light">
          <tr>
            <th rowspan="2" class="align-middle">CATEGORY</th>
            <th colspan="{{ max(count($gradPrograms), 1) }}" class="text-center">
              FREQUENCY @if(count($gradPrograms)) (per Major) @endif
            </th>
            <th rowspan="2" class="align-middle">TOTAL</th>
          </tr>
          <tr>
            @if(count($gradPrograms))
              @foreach($gradPrograms as $prog)
                <th>{{ $prog }}</th>
              @endforeach
            @else
              <th>NO MAJOR FOUND</th>
            @endif
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-start">Applicants for Graduation</td>
            @if(count($gradPrograms))
              @foreach($gradPrograms as $i => $prog)
                <td>{{ $gradApplicants[$i] ?? 0 }}</td>
              @endforeach
            @else
              <td>0</td>
            @endif
            <td><strong>{{ $grandApplicants }}</strong></td>
          </tr>
          <tr>
            <td class="text-start">Not Yet Graduating</td>
            @if(count($gradPrograms))
              @foreach($gradPrograms as $i => $prog)
                <td>{{ $gradNotYet[$i] ?? 0 }}</td>
              @endforeach
            @else
              <td>0</td>
            @endif
            <td><strong>{{ $grandNotYet }}</strong></td>
          </tr>
          <tr class="fw-semibold">
            <td class="text-start">TOTAL</td>
            @if(count($gradPrograms))
              @foreach($gradPrograms as $i => $prog)
                <td>{{ $colTotals[$i] ?? 0 }}</td>
              @endforeach
            @else
              <td>0</td>
            @endif
            <td>{{ $grandOverall }}</td>
          </tr>
          <tr class="table-secondary fw-bold">
            <td colspan="{{ 1 + max(count($gradPrograms), 1) }}" class="text-end">GRAND TOTAL</td>
            <td>{{ $grandOverall }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- NEW TOP ROW: Sex, Applicants vs Not Yet, mini-stat cards -->
  <div class="row g-3 mb-3">
    <!-- Sex Distribution Pie -->
    <div class="col-lg-4">
      <div class="panel">
        <h6 class="mb-2">Sex Distribution of Applicants for Graduation</h6>
        <div class="canvas-wrap"><canvas id="pieSexApplicants"></canvas></div>
      </div>
    </div>

    <!-- Applicants vs Not Yet per Program/Major -->
    <div class="col-lg-4">
      <div class="panel">
        <h6 class="mb-2">Applicants vs Not Yet Graduating per Program/Major</h6>
        <div class="canvas-wrap"><canvas id="barApplicantsPrograms"></canvas></div>
      </div>
    </div>

    <!-- Right side mini stat boxes -->
    <div class="col-lg-4">
      <div class="panel h-100 d-flex flex-column justify-content-between">
        <div class="mini-stat text-center border-primary-subtle">
          <div class="mini-stat-label">Students on LOA</div>
          <div class="mini-stat-value">{{ $studentsOnLOA }}</div>
          <div class="mini-stat-sub">Current AY</div>
        </div>
        <div class="mini-stat text-center">
          <div class="mini-stat-label">Latin Honor Applicants</div>
          <div class="mini-stat-value">{{ $latinHonorApplicants }}</div>
          <div class="mini-stat-sub">All BA Students</div>
        </div>
        <div class="mini-stat text-center">
          <div class="mini-stat-label">Projected Graduation Rate</div>
          <div class="mini-stat-value">
            {{ number_format($projectedGradRate, 2) }}%
          </div>
          <div class="mini-stat-sub">Candidates vs Total</div>
        </div>
      </div>
    </div>
  </div>

  <!-- SECOND ROW: Dean’s Listers & Latin Honors -->
  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="panel">
        <h6 class="mb-2">Dean’s Listers per Program/Major</h6>
        <div class="canvas-wrap"><canvas id="barPrograms"></canvas></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="panel">
        <h6 class="mb-2">Latin Honors Distribution</h6>
        <div class="canvas-wrap"><canvas id="pieLatin"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Top Performing Programs table + insights -->
  <div class="panel mb-4">
    <h6 class="mb-3 text-center">Top Performing Programs</h6>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr class="text-center">
            <th>Program</th>
            <th>Average GPA</th>
            <th>Dean’s Listers</th>
            <th>Latin Honors</th>
          </tr>
        </thead>
        <tbody class="text-center">
          <tr>
            <td class="text-start">BS Information Technology</td>
            <td>1.45</td>
            <td>76</td>
            <td>12</td>
          </tr>
          <tr>
            <td class="text-start">BS Computer Science</td>
            <td>1.50</td>
            <td>70</td>
            <td>10</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 p-3 insights">
      <h6 class="mb-2"><i class="bi bi-lightbulb"></i> Insights</h6>
      <ul class="mb-0">
        <li><strong>BSIT</strong> leads with the highest performance (GPA <strong>1.45</strong>) followed closely by <strong>BSCS</strong> (GPA <strong>1.50</strong>).</li>
        <li>A total of <strong>{{ number_format($deansListers) }}</strong> students made it to the Dean’s List, showing strong academic engagement.</li>
        <li>There are <strong>{{ number_format($latinHonors) }}</strong> Latin honor recipients, with most being <strong>Magna Cum Laude</strong> awardees.</li>
        <li>BSIT and BSCS together account for over <strong>80%</strong> of all Dean’s Listers and Latin Honors this semester.</li>
        <li>Participation in academic competitions can still be increased to improve external exposure.</li>
      </ul>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  const palette = ['#660000', '#940000', '#4c0000'];
  const grid    = 'rgba(1,58,99,0.12)';
  const fill    = 'rgba(1,58,99,0.12)';

  const programLabels   = @json($programLabels);
  const programListers  = @json($programListers);
  const latinLabels     = @json($latinLabels);
  const latinCounts     = @json($latinCounts);

  const gradPrograms    = @json($gradPrograms);
  const gradApplicants  = @json($gradApplicants);
  const gradNotYet      = @json($gradNotYet);
  const sexLabels       = ['Male','Female'];
  const sexCounts       = [{{ $maleApplicants }}, {{ $femaleApplicants }}];

  const mkXY = (showLegend = true) => ({
    responsive:true,
    maintainAspectRatio:false,
    plugins:{ legend:{ display: showLegend, labels:{ color:'#000' } } },
    scales:{
      x:{ grid:{ color:grid }, ticks:{ color:'#000' } },
      y:{ grid:{ color:grid }, ticks:{ color:'#000' }, beginAtZero:true }
    }
  });

  const mkPie = () => ({
    responsive:true,
    maintainAspectRatio:false,
    plugins:{ legend:{ labels:{ color:'#000', usePointStyle:true, pointStyle:'rectRounded', boxWidth:14 } } }
  });

  // Dean’s Listers per Program/Major
  new Chart(document.getElementById('barPrograms'), {
    type:'bar',
    data:{
      labels: programLabels,
      datasets:[{
        data: programListers,
        backgroundColor: palette,
        borderColor: palette,
        borderWidth: 2,
        borderRadius: 8
      }]
    },
    options: mkXY(false)
  });

  // Latin Honors distribution
  new Chart(document.getElementById('pieLatin'), {
    type:'pie',
    data:{
      labels: latinLabels,
      datasets:[{
        data: latinCounts,
        backgroundColor: palette,
        borderColor:'#fff',
        borderWidth:2,
        hoverOffset:8
      }]
    },
    options: mkPie()
  });

  // Sex distribution of applicants
  new Chart(document.getElementById('pieSexApplicants'), {
    type:'pie',
    data:{
      labels: sexLabels,
      datasets:[{
        data: sexCounts,
        backgroundColor: ['#2563eb','#f97316'],
        borderColor:'#fff',
        borderWidth:2,
        hoverOffset:8
      }]
    },
    options: mkPie()
  });

  // Applicants vs Not Yet per Program/Major
  new Chart(document.getElementById('barApplicantsPrograms'), {
    type:'bar',
    data:{
      labels: gradPrograms,
      datasets:[
        {
          label:'Applicants for Graduation',
          data: gradApplicants,
          backgroundColor: palette[0]
        },
        {
          label:'Not Yet Graduating',
          data: gradNotYet,
          backgroundColor: palette[1]
        }
      ]
    },
    options: mkXY(true)
  });

})();
</script>
@endsection
