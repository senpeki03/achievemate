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

  /* Wrapper so card is not touching header and has left/right margin */
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
    color:var(--success); /* Green for enrolled status */
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

  .ok-mark{
    color:#16a34a; /* green */
  }
  .x-mark{
    color:#dc2626; /* red */
  }

  .academic-deficiencies {
    font-size:12px;
    text-align:center;
  }

  /* Center alignment for status columns */
  .graduation-status-cell {
    text-align:center;
  }
</style>

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('programchair.graduation.report') }}" 
          class="btn btn-outline-primary"
          style="border:1px solid #ffff; color:#ffff; font-weight:600;">
            <i class="fas fa-download"></i> Download Report
        </a>
    </div>
<div class="grad-wrapper">
  <div class="grad-container">

    {{-- Top header: College + Program --}}
    <div class="grad-header">
      {{ $designation->college->College_name ?? 'College' }}
    </div>

    <div class="sub-header">
      {{ $designation->program->Program_name ?? '' }}
    </div>


    <table class="grad-table">
      <thead>
        <tr>
          <th style="width:45px;">No.</th>
          <th style="width:110px;">SR CODE</th>
          <th>NAME</th>
          <th style="width:140px;">Graduation Status</th>
          <th style="width:220px;">Academic Deficiencies / Currently Enrolled Courses</th>
          <th style="width:70px;">AppSheet</th>
          <th style="width:70px;">Lib Cert</th>
          <th style="width:70px;">PSA</th>
          <th style="width:90px;">TOR/F137</th>
          <th style="width:110px;">Honor Applicant?</th>
        </tr>
      </thead>

      <tbody>
        @php $rowNum = 1; @endphp

        @forelse ($groups as $groupKey => $students)
          @php
            list($year, $majorName) = explode('|', $groupKey, 2);
          @endphp

          {{-- SECTION HEADER PER YEAR + MAJOR --}}
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

          @foreach ($students as $student)
            @php
              $form = $student->graduationForm;
              $req = $form?->requirement;
              
              // Determine graduation status based on remarks
              $graduationStatus = '';
              $academicDeficiencies = '';
              
              if ($req && !empty($req->remarks)) {
                  $remarks = strtoupper(trim($req->remarks));
                  if (str_contains($remarks, 'GRADUATING')) {
                      $graduationStatus = 'GRADUATING';
                      $academicDeficiencies = 'ENROLLED';
                  } else {
                      $graduationStatus = $remarks;
                  }
              }
              // If no remarks or no requirement, leave both columns blank
            @endphp

            <tr>
              <td style="text-align:center;">{{ $rowNum++ }}</td>
              <td style="text-align:center;">{{ $student->SRCODE }}</td>
              <td>
                {{ $student->Last_name }},
                {{ $student->First_name }}
                @if($student->Middle_name)
                  {{ mb_substr($student->Middle_name, 0, 1) }}.
                @endif
              </td>

              {{-- Graduation Status Column --}}
              <td class="graduation-status-cell @if($graduationStatus === 'GRADUATING') status-graduating @elseif(!empty($graduationStatus)) status-not @endif">
                {{ $graduationStatus }}
              </td>

              {{-- Academic Deficiencies / Currently Enrolled Courses Column --}}
              <td class="@if($academicDeficiencies === 'ENROLLED') status-enrolled @else academic-deficiencies @endif">
                {{ $academicDeficiencies }}
              </td>

              {{-- AppSheet -> Approval_Sheet --}}
              <td class="check">
                @if(!$req)
                  {{-- walang GraduationRequirement row => BLANK --}}
                @elseif(!empty($req->Approval_Sheet))
                  <span class="ok-mark">✓</span>
                @else
                  <span class="x-mark">✗</span>
                @endif
              </td>

              {{-- Lib Cert -> Certificate_Library --}}
              <td class="check">
                @if(!$req)
                  {{-- walang row => BLANK --}}
                @elseif(!empty($req->Certificate_Library))
                  <span class="ok-mark">✓</span>
                @else
                  <span class="x-mark">✗</span>
                @endif
              </td>

              {{-- PSA -> Birth_Certificate --}}
              <td class="check">
                @if(!$req)
                  {{-- walang row => BLANK --}}
                @elseif(!empty($req->Birth_Certificate))
                  <span class="ok-mark">✓</span>
                @else
                  <span class="x-mark">✗</span>
                @endif
              </td>

              {{-- TOR/F137 -> reportofgrade_path --}}
              <td class="check">
                @if(!$req)
                  {{-- walang row => BLANK --}}
                @elseif(!empty($req->reportofgrade_path))
                  <span class="ok-mark">✓</span>
                @else
                  <span class="x-mark">✗</span>
                @endif
              </td>

              <td class="check"></td>
            </tr>
          @endforeach

        @empty
          <tr>
            <td colspan="10" class="text-center text-muted">
              No students found for this College / Program / Major.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection