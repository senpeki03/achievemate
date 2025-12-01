@extends('programchair.programchairsidebar')

@section('title', 'Graduation Status')

@section('content')

<style>
  :root {
    --brand:#0C2340;
    --success:#1e7e34;
    --danger:#c82333;
    --muted:#6b7280;
    --line:#e5e7eb;
  }

  .grad-wrapper{
    margin:20px 25px;
  }

  .grad-container {
    background:#fff;
    border-radius:12px;
    padding:25px;
    box-shadow:0 4px 20px rgba(0,0,0,0.08);
  }

  .grad-header {
    font-size:20px;
    font-weight:700;
    color:var(--brand);
    margin-bottom:4px;
  }

  .sub-header {
    font-size:14px;
    color:var(--muted);
    margin-bottom:20px;
  }

  .section-flex {
    width: 100%;
    display: flex;
    justify-content: center;
    position: relative;
  }

  .year-left {
    position: absolute;
    left: 10px;
    font-weight: 700;
  }

  .major-center {
    text-align: center;
    font-weight: 700;
  }

  table.grad-table {
    width:100%;
    border-collapse:collapse;
    font-size:13px;
  }

  table.grad-table th,
  table.grad-table td {
    padding:9px 10px;
    border:1px solid var(--line);
    vertical-align:middle;
  }

  table.grad-table th {
    background:#f8fafc;
    font-weight:700;
    color:var(--brand);
    text-align:center;
  }

  .status-graduating {
    color:var(--success);
    font-weight:700;
    text-align:center;
  }

  .status-not {
    color:var(--danger);
    font-weight:700;
    text-align:center;
  }

  .status-enrolled {
    color:var(--success);
    font-weight:700;
    text-align:center;
  }

  .section-title {
    background:#f1f5f9;
    font-weight:700;
    color:var(--brand);
    padding:10px;
    font-size:14px;
    text-align:center;
  }

  .check {
    font-size:18px;
    font-weight:bold;
    text-align:center;
  }

  .ok-mark{ color:#16a34a; }
  .x-mark{ color:#dc2626; }

  .academic-deficiencies {
    font-size:12px;
    text-align:center;
  }

  /* PAGINATION */
  .pagination-wrapper {
    display:flex;
    justify-content:flex-end;
    margin-top:15px;
    margin-right:30px;
  }

  /* Pagination Button Styling */
  .pagination .page-link {
      color:#660000;
      border:1px solid #66000066 !important;
  }

  .pagination .page-link:hover {
      color:white !important;
      background:#660000 !important;
  }

  .pagination .active .page-link {
      background:#660000 !important;
      color:white !important;
      border-color:#660000 !important;
  }

  /* REMOVE "Showing 1 to X of X results" */
  .pagination-wrapper .text-muted {
      display:none !important;
  }
</style>


{{-- DOWNLOAD BUTTON (TOP-RIGHT) --}}
<div class="d-flex justify-content-end mb-3" style="margin-right:30px;">
    <a href="{{ route('programchair.graduation.report') }}" 
       class="btn"
       style="
           background:#660000;
           color:#fff;
           font-weight:600;
           border-radius:6px;
           border:1px solid #ffffff;   /* ← WHITE BORDER */
       ">
        <i class="fas fa-download"></i> Download Report
    </a>
</div>



<div class="grad-wrapper">
  <div class="grad-container">

    <div class="grad-header">
      {{ $designation->college->College_name ?? 'College' }}
    </div>

    <div class="sub-header">
      {{ $designation->program->Program_name ?? '' }}
    </div>

    <table class="grad-table">
      <thead>
        <tr>
          <th>No.</th>
          <th>SR CODE</th>
          <th>NAME</th>
          <th>Graduation Status</th>
          <th>Academic Deficiencies / Enrolled</th>
          <th>AppSheet</th>
          <th>Lib Cert</th>
          <th>PSA</th>
          <th>TOR/F137</th>
          <th>Honor Applicant?</th>
        </tr>
      </thead>

      <tbody>

        @php 
            $rowNum = ($studentsPaginated->currentPage()-1) * $studentsPaginated->perPage() + 1; 
        @endphp

        @forelse ($groups as $groupKey => $students)
          @php
              list($year, $majorName) = explode('|', $groupKey, 2);
          @endphp

          <tr>
            <td colspan="10" class="section-title">
              <div class="section-flex">
                <span class="year-left">{{ $year }}</span>
                @if($majorName !== 'No Major')
                    <span class="major-center">Major in {{ $majorName }}</span>
                @endif
              </div>
            </td>
          </tr>

          @foreach($students as $student)
            @php
              $form = $student->graduationForm;
              $req  = $form?->requirement;

              $graduationStatus = '';
              $academicDef = '';

              if ($req && !empty($req->remarks)) {
                  $remarks = strtoupper(trim($req->remarks));
                  if (str_contains($remarks, 'GRADUATING')) {
                      $graduationStatus = 'GRADUATING';
                      $academicDef = 'ENROLLED';
                  } else {
                      $graduationStatus = $remarks;
                  }
              }
            @endphp

            <tr>
              <td class="text-center">{{ $rowNum++ }}</td>
              <td class="text-center">{{ $student->SRCODE }}</td>

              <td>
                {{ $student->Last_name }}, {{ $student->First_name }}
                @if($student->Middle_name)
                  {{ substr($student->Middle_name,0,1) }}.
                @endif
              </td>

              <td class="text-center 
                  {{ $graduationStatus=='GRADUATING' ? 'status-graduating' : ($graduationStatus ? 'status-not' : '') }}">
                {{ $graduationStatus }}
              </td>

              <td class="text-center {{ $academicDef=='ENROLLED' ? 'status-enrolled' : 'academic-deficiencies' }}">
                {{ $academicDef }}
              </td>

              <td class="check">
                @if($req && $req->Approval_Sheet)
                    <span class="ok-mark">✓</span>
                @elseif($req)
                    <span class="x-mark">✗</span>
                @endif
              </td>

              <td class="check">
                @if($req && $req->Certificate_Library)
                    <span class="ok-mark">✓</span>
                @elseif($req)
                    <span class="x-mark">✗</span>
                @endif
              </td>

              <td class="check">
                @if($req && $req->Birth_Certificate)
                    <span class="ok-mark">✓</span>
                @elseif($req)
                    <span class="x-mark">✗</span>
                @endif
              </td>

              <td class="check">
                @if($req && $req->reportofgrade_path)
                    <span class="ok-mark">✓</span>
                @elseif($req)
                    <span class="x-mark">✗</span>
                @endif
              </td>

              <td class="check"></td>
            </tr>

          @endforeach

        @empty
            <tr>
                <td colspan="10" class="text-center text-muted">No students found.</td>
            </tr>
        @endforelse

      </tbody>
    </table>

  </div>
</div>

{{-- PAGINATION (BOTTOM-RIGHT) --}}
<div class="pagination-wrapper">
    {{ $studentsPaginated->links('pagination::bootstrap-5') }}
</div>

@endsection
