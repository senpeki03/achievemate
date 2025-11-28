@extends('registrar.registrarsidebar')

@section('content')
<div class="container py-4">

  @php
    $collegeName = $college->College_name ?? 'College';
    $programName = $program->Program_name ?? 'Program';
    $majorName   = $major?->Major_name;
  @endphp

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="fw-bold text-white mb-1">{{ $collegeName }}</h3>
      <p class="text-light mb-0">
        {{ $programName }}
        @if($majorName)
          – <span class="fw-semibold">{{ $majorName }}</span>
        @endif
      </p>
    </div>

    <div>
      {{-- TODO: i-wire sa export route mo --}}
      <a href="#" class="btn btn-outline-light">
        Download Report
      </a>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">

      @if($students->isEmpty())
        <div class="p-4 text-center">
          <h5 class="fw-semibold mb-1">No Fourth-Year Graduating Students</h5>
          <p class="text-muted mb-0">
            There are no Fourth-Year students with graduation forms and requirements
            for this program/major.
          </p>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="width:60px;">No.</th>
                <th style="width:120px;">SR CODE</th>
                <th>Name</th>
                <th class="text-center">Application Form</th>
                <th class="text-center">AppSheet</th>
                <th class="text-center">Lib Cert</th>
                <th class="text-center">PSA</th>
                <th class="text-center">TOR/F137</th>
                <th class="text-center">Honor Applicant?</th>
                <th class="text-center" style="width:100px;">Action</th>
              </tr>
            </thead>
            <tbody>
              {{-- Header row: FOURTH YEAR (left) + Major (center) --}}
              <tr class="table-light border-top border-bottom">
                <td colspan="10" class="py-2">
                  <div class="d-flex align-items-center">
                    {{-- LEFT: YEAR LABEL --}}
                    <div class="fw-semibold">
                      FOURTH YEAR
                    </div>

                    {{-- CENTER: MAJOR NAME --}}
                    <div class="fw-bold text-center flex-grow-1">
                      @if($majorName)
                        {{ $majorName }}
                      @endif
                    </div>

                    {{-- RIGHT: spacer para ma-center talaga yung major --}}
                    <div style="width:120px;"></div>
                  </div>
                </td>
              </tr>

              @foreach($students as $index => $stud)
                @php
                  $srCode = $stud->SRCODE ?? '';

                  $last   = $stud->Last_name   ?? null;
                  $first  = $stud->First_name  ?? null;
                  $middle = $stud->Middle_name ?? null;

                  $displayName = trim(
                    ($last ? $last . ', ' : '') .
                    ($first ?? '') .
                    ($middle ? ' ' . strtoupper(substr($middle, 0, 1)) . '.' : '')
                  );

                  if ($displayName === '') {
                    $displayName = 'Unnamed Student';
                  }

                  // Requirements flags
                  $hasAppSheet = !empty($stud->Approval_Sheet);
                  $hasLibCert  = !empty($stud->Certificate_Library);
                  $hasPSA      = !empty($stud->Birth_Certificate);
                  $hasTorF137  = !empty($stud->reportofgrade_path);

                  $isHonor     = ($stud->remarks ?? null) === 'Honor Applicant';

                  // Application form PDF path
                  $applicationPath = $stud->applicationform_grad ?? null;
                  $applicationUrl  = $applicationPath ? asset($applicationPath) : null;

                  // Graduation requirement row id for evaluation
                  $gradReqId = $stud->GraduationReq_id ?? null;
                @endphp
                <tr class="border-bottom">
                  <td>{{ $index + 1 }}</td>
                  <td>{{ $srCode }}</td>
                  <td>{{ $displayName }}</td>

                  {{-- Application Form --}}
                  <td class="text-center">
                    @if($applicationUrl)
                      <a href="{{ $applicationUrl }}" target="_blank"
                         class="btn btn-sm"
                         style="border:1px solid #ddd; border-radius:6px;"
                         title="View Application Form">
                        <i class="bi bi-eye-fill text-primary"></i>
                      </a>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>

                  <td class="text-center {{ $hasAppSheet ? 'text-success' : 'text-danger' }}">
                    {{ $hasAppSheet ? '✔' : '✘' }}
                  </td>
                  <td class="text-center {{ $hasLibCert ? 'text-success' : 'text-danger' }}">
                    {{ $hasLibCert ? '✔' : '✘' }}
                  </td>
                  <td class="text-center {{ $hasPSA ? 'text-success' : 'text-danger' }}">
                    {{ $hasPSA ? '✔' : '✘' }}
                  </td>
                  <td class="text-center {{ $hasTorF137 ? 'text-success' : 'text-danger' }}">
                    {{ $hasTorF137 ? '✔' : '✘' }}
                  </td>
                  <td class="text-center {{ $isHonor ? 'text-success fw-bold' : 'text-muted' }}">
                    {{ $isHonor ? 'Yes' : '—' }}
                  </td>

                  {{-- Action: check icon only --}}
                  <td class="text-center">
                    @if($gradReqId)
                      <button type="button"
                              class="btn btn-sm btn-outline-success btn-evaluate"
                              style="border-radius:6px;"
                              data-req-id="{{ $gradReqId }}"
                              data-student="{{ $displayName }}">
                        <i class="bi bi-check-lg"></i>
                      </button>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif

    </div>
  </div>
</div>

{{-- =================== Evaluate Modal =================== --}}
<div class="modal fade" id="evaluateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Evaluate Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">
          Are you sure you want to evaluate this student?<br>
          <strong id="evalStudentName"></strong>
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmEvaluateBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>

{{-- =================== Scripts =================== --}}
<script>
  let selectedReqId = null;

  document.querySelectorAll('.btn-evaluate').forEach(btn => {
    btn.addEventListener('click', function () {
      selectedReqId = this.getAttribute('data-req-id');
      const name = this.getAttribute('data-student') || '';

      document.getElementById('evalStudentName').textContent = name;

      const modalEl = document.getElementById('evaluateModal');
      const modal   = new bootstrap.Modal(modalEl);
      modal.show();
    });
  });

  document.getElementById('confirmEvaluateBtn').addEventListener('click', function () {
    if (!selectedReqId) return;

    fetch("{{ route('registrar.graduationlist.evaluate') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        graduation_req_id: selectedReqId,
      }),
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        // simple feedback, then reload
        window.location.reload();
      } else {
        alert(data.message || 'Failed to evaluate student.');
      }
    })
    .catch(() => {
      alert('Failed to evaluate student.');
    });
  });
</script>
@endsection
