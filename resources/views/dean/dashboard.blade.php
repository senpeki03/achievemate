@extends('dean.deansidebar')
<link rel="stylesheet" href="{{ asset('css/deandashboard.css') }}">
@section('title', 'Dean’s Dashboard')

@section('content')


<div class="dd-root">
  <div class="container"><!-- use container to avoid oversizing -->

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
        <div class="stat-icon-circle" style="background:linear-gradient(135deg,#ff416c,#ff4b2b)"><i class="bi bi-person-fill"></i></div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">TOTAL USERS</div>
          <div class="stat-value" id="totalUsers">4</div>
          <div class="stat-sub">+8.3% Since last month</div>
        </div>
        <div class="stat-icon-circle" style="background:linear-gradient(135deg,#f7971e,#ffd200)"><i class="bi bi-people-fill"></i></div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">DEAN’S LISTERS</div>
          <div class="stat-value" id="totalListers">215</div>
          <div class="stat-sub">This semester</div>
        </div>
        <div class="stat-icon-circle" style="background:linear-gradient(135deg,#11998e,#38ef7d)"><i class="bi bi-award"></i></div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">LATIN HONORS (GRADUATING)</div>
          <div class="stat-value" id="honorsCount">45</div>
          <div class="stat-sub">Summa/Magna/Cum</div>
        </div>
        <div class="stat-icon-circle" style="background:linear-gradient(135deg,#9333ea,#3b82f6)"><i class="bi bi-mortarboard-fill"></i></div>
      </div>

      <div class="stat-modern-card">
        <div>
          <div class="stat-head">ACADEMIC COMPETITION PARTICIPANTS</div>
          <div class="stat-value" id="competitorsCount">28</div>
          <div class="stat-sub">College-wide</div>
        </div>
        <div class="stat-icon-circle" style="background:linear-gradient(135deg,#0575e6,#00f260)"><i class="bi bi-trophy-fill"></i></div>
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

    <!-- =============== Table + Insights (optional) =============== -->
    <div class="dd-bottom">
      <div class="dd-table">
        <h3 style="color:#0f3057;margin:0 0 8px">Top Performing Programs</h3>
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
        <h3 style="color:#0f3057;margin:0 0 8px">Performance Insights</h3>
        <div class="dd-insight"><h4>📈 Steady Academic Improvement</h4><p>Dean’s Listers increased by 18% vs last semester.</p></div>
        <div class="dd-insight"><h4>🎓 Graduation Quality</h4><p>21% of graduates receive Latin honors.</p></div>
        <div class="dd-insight"><h4>🏆 Participation</h4><p>Academic competitions up 25% YoY.</p></div>
      </div>
    </div>

  </div>
</div>

<script>
(function ensureChartJs(cb){
  if (window.Chart) return cb();
  const s=document.createElement('script');
  s.src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js";
  s.onload=cb; document.head.appendChild(s);
})(function init(){
  // table data
  const programs = ["IT","CS","IS","Eng","BA","Ed"];
  const programListers = [50,45,40,30,28,22];
  const programGPA = [1.45,1.48,1.50,1.60,1.55,1.58];
  const growthRates = [10,8,6,4,5,3];

  const tbody=document.getElementById('programTable');
  if (tbody){
    programs.forEach((p,i)=>{
      const tr=document.createElement('tr');
      tr.innerHTML=`<td>${p}</td><td>${programListers[i]}</td><td>${programGPA[i]}</td><td>${growthRates[i]}%</td>`;
      tbody.appendChild(tr);
    });
  }

  const common={ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} };

  new Chart(document.getElementById('growthChart'),{
    type:'line',
    data:{ labels:["1st Sem 2024","2nd Sem 2024","1st Sem 2025"],
      datasets:[{ label:"Dean’s Listers", data:[180,205,215], borderColor:"#2563eb", backgroundColor:"rgba(37,99,235,.18)", fill:true, tension:.35 }]},
    options:{ ...common, scales:{ y:{ beginAtZero:true, ticks:{ precision:0 }}}}
  });

  new Chart(document.getElementById('programChart'),{
    type:'bar',
    data:{ labels:programs, datasets:[{ label:"Dean’s Listers", data:programListers, backgroundColor:"#3b82f6" }]},
    options:{ ...common, scales:{ y:{ beginAtZero:true, ticks:{ precision:0 }}}}
  });

  new Chart(document.getElementById('honorsChart'),{
    type:'doughnut',
    data:{ labels:["Summa","Magna","Cum Laude"], datasets:[{ data:[5,18,22], backgroundColor:["#facc15","#60a5fa","#34d399"] }]},
    options:{ responsive:true, plugins:{ legend:{ position:"bottom" } } }
  });

  new Chart(document.getElementById('competitionChart'),{
    type:'radar',
    data:{ labels:["IT Dept","CS Dept","BA Dept","Eng Dept","Ed Dept"],
      datasets:[{ label:"Competitions Joined", data:[8,6,4,5,5], borderColor:"#f87171", backgroundColor:"rgba(248,113,113,.20)", pointBackgroundColor:"#ef4444"}]},
    options:{ responsive:true, plugins:{ legend:{ position:"bottom" } } }
  });
});
</script>
@endsection
