@extends('dean.deansidebar')

@section('content')
<div class="container py-4">
  {{-- Header with back button --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white m-0">Dean's Honor List</h3>
    <button id="back-button" class="btn btn-outline-light d-none" type="button">
      <i class="bi bi-arrow-left-circle me-1"></i> Back
    </button>
  </div>

  {{-- Colleges --}}
  <div id="college-container" class="table-responsive mb-3">
    <table class="table table-striped bg-white shadow-sm rounded-4 overflow-hidden">
      <thead class="table-light text-center">
        <tr><th>College Name</th></tr>
      </thead>
      <tbody class="align-middle">
        @forelse ($colleges as $college)
          <tr class="clickable-row" data-id="{{ $college->College_id }}">
            <td class="text-uppercase">{{ $college->College_name }}</td>
          </tr>
        @empty
          <tr><td class="text-center">No colleges available for your designation.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Programs --}}
  <div id="programs-container" class="d-none">
    <h4 class="text-dark fw-bold">Programs</h4>
    <div class="table-responsive mb-3">
      <table class="table table-striped bg-white shadow-sm rounded-4 overflow-hidden" id="program-table">
        <thead class="table-light text-center">
          <tr><th>Program Name</th></tr>
        </thead>
        <tbody class="align-middle"><!-- Injected by JS --></tbody>
      </table>
    </div>
  </div>

  {{-- Students --}}
  <div id="students-container" class="d-none">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <h4 class="text-dark fw-bold mb-0">Students</h4>
      <div class="d-flex flex-column align-items-end gap-2">
        {{-- Label kept as requested --}}
        <button id="verify-selected" class="btn btn-success btn-sm" type="button" disabled>
          <i class="bi bi-check2-circle me-1"></i> Verify Selected
        </button>
      </div>
    </div>

    {{-- Tabs + Download button (same row) --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <ul class="nav nav-tabs mb-0" id="statusTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-status="For Evaluation" type="button" role="tab">For Evaluation</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-status="For Approval" type="button" role="tab">For Approval</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link active" data-status="Approved" type="button" role="tab">Approved</button>
        </li>
      </ul>

      {{-- Download (enabled on Approved tab + program selected) --}}
      @php
        // Build a URL template we can safely swap the program id into (__PID__)
        $deanReportUrlTemplate = route('dean.deanshonorlist.report', ['programId' => '__PID__']);
      @endphp
      <button id="download-report"
              class="btn btn-outline-primary btn-sm ms-auto"
              data-url-template="{{ $deanReportUrlTemplate }}"
              disabled
              title="Select a program and open the Approved tab to download."
              type="button">
        <i class="bi bi-download me-1"></i> Download Report
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-striped bg-white shadow-sm rounded-4 overflow-hidden text-center" id="student-table">
        <thead class="table-light">
          <tr>
            <th style="width:4%"><input type="checkbox" id="check-all"></th>
            <th style="width:5%">#</th>
            <th style="width:30%">Fullname</th>
            <th style="width:12%">Year Level</th>
            <th style="width:10%">GWA</th>
            <th style="width:9%">Rank</th>
            <th style="width:12%">Status</th>
            <th style="width:18%">Action</th>
          </tr>
        </thead>
        <tbody class="align-middle"><!-- Injected by JS --></tbody>
      </table>
    </div>
  </div>
</div>

{{-- ===== Modals ===== --}}
{{-- Confirm Approve (single) --}}
<div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256"
             style="width:60px" class="mb-3" alt="">
        <h5 class="fw-bold text-dark mb-4">Approve Student for Dean’s List</h5>
        <p class="mb-3 text-dark">
          You are about to approve this student’s application.<br>
          <span>Student: <b id="approveDeanStudentName">[Student]</b><br>
          GWA: <span id="approveDeanStudentGwa">[GWA]</span> | Year Level: <span id="approveDeanStudentYear">[Year]</span></span>
        </p>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-success px-4" id="confirmApproveYesBtn" type="button">Approve Student</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Approve Success --}}
<div class="modal fade" id="approveSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video
            src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
            autoplay muted loop playsinline style="width:70px;height:70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Approval Successful</h5>
        <p class="mb-4 text-muted">The student is now officially recognized as a Dean’s Lister.</p>
        <div><button class="btn btn-primary px-4" data-bs-dismiss="modal" type="button">OK</button></div>
      </div>
    </div>
  </div>
</div>

{{-- PDF Viewer --}}
<div class="modal fade" id="pdfViewerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 overflow-hidden">
      <div class="modal-header">
        <h5 class="modal-title">Student Application (PDF)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" style="height:80vh;">
        <iframe id="pdfViewerFrame" src="" width="100%" height="100%" style="border:0;"></iframe>
      </div>
    </div>
  </div>
</div>

@push('head')
  <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  // ===== Globals =====
  let rowNo = 1;
  window._allStudents = [];
  let _selectedProgramId = null;

  // ===== Helpers =====
  function badge(status) {
    const cls = { 'For Evaluation':'bg-danger', 'For Approval':'bg-warning text-dark', 'Approved':'bg-success' }[status] || 'bg-secondary';
    return `<span class="badge ${cls}">${status || '—'}</span>`;
  }
  function toNum(v){ const n=parseFloat(v); return Number.isFinite(n)?n:Infinity; }
  function fmtGwa(v){ const n=parseFloat(v); return Number.isFinite(n)?n.toFixed(2):'—'; }

  function setActiveTab(status) {
    $('#statusTabs .nav-link').removeClass('active');
    $(`#statusTabs .nav-link[data-status="${status}"]`).addClass('active');
    renderStudentsByStatus(status);
    refreshDownloadButton();
  }

  function updateLocalStatus(appId, newStatus) {
    const it = (window._allStudents||[]).find(s => String(s.application_id) === String(appId));
    if (it) it.status = newStatus;
  }

  function refreshBulkButton() {
    $('#verify-selected').prop('disabled', $('.row-check:checked').length === 0);
  }

  function refreshDownloadButton() {
    const onApproved = $('#statusTabs .nav-link.active').data('status') === 'Approved';
    const hasApproved = (window._allStudents || []).some(s => s.status === 'Approved');
    const can = !!_selectedProgramId && onApproved && hasApproved;
    $('#download-report').prop('disabled', !can);
  }

  // ===== Render =====
  function renderStudentsByStatus(status) {
    const tb = $('#student-table tbody').empty();
    rowNo = 1;

    const data = (window._allStudents || []).filter(s => s.status === status);
    if (data.length === 0) {
      tb.append('<tr><td colspan="8" class="text-center text-muted py-4">No students found.</td></tr>');
      $('#check-all').prop('checked', false);
      refreshBulkButton();
      return;
    }

    // sort by lowest GWA first, then by name
    data.sort((a,b) => a._gwaNum !== b._gwaNum
      ? a._gwaNum - b._gwaNum
      : (a.fullname||'').toUpperCase().localeCompare((b.fullname||'').toUpperCase())
    );

    data.forEach(s => {
      tb.append(`
        <tr data-app="${s.application_id}">
          <td><input type="checkbox" class="row-check"></td>
          <td>${rowNo++}</td>
          <td class="text-start">${(s.fullname || '').toUpperCase()}</td>
          <td>${s.year_level || ''}</td>
          <td>${fmtGwa(s.gwa)}</td>
          <td>${s.rank ?? '—'}</td>
          <td class="status-cell fw-bold">${badge(s.status)}</td>
          <td>
            <div class="d-flex justify-content-center align-items-center gap-2">
              <button class="btn btn-sm btn-outline-primary view-file" data-id="${s.application_id}" title="View PDF" type="button">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-sm btn-outline-success approve-one" title="Approve" type="button">
                <i class="bi bi-check2-circle"></i>
              </button>
            </div>
          </td>
        </tr>
      `);
    });

    $('#check-all').prop('checked', false);
    refreshBulkButton();
  }

  // ===== NAV: College -> Programs
  $(document).on('click', '.clickable-row', function () {
    const collegeId = $(this).data('id');

    $('#college-container').addClass('d-none');
    $('#back-button').removeClass('d-none');
    $('#programs-container').removeClass('d-none');
    $('#students-container').addClass('d-none');

    $('#program-table tbody').empty();
    $('#student-table tbody').empty();

    $.getJSON('/dean/programs/' + collegeId, function (data) {
      const tb = $('#program-table tbody').empty();
      if (!data || data.length === 0) {
        tb.append('<tr><td class="text-center">No programs found</td></tr>');
        return;
      }
      data.forEach(p => {
        tb.append(`<tr class="clickable-program" data-id="${p.Program_id}"><td class="text-uppercase">${p.Program_name}</td></tr>`);
      });
    });
  });

  // ===== NAV: Programs -> Students
  $(document).on('click', '.clickable-program', function () {
    _selectedProgramId = $(this).data('id');

    $('#programs-container').addClass('d-none');
    $('#students-container').removeClass('d-none');

    $.getJSON('/dean/students-by-program/' + _selectedProgramId, function (data) {
      window._allStudents = (data || []).map(s => ({...s, _gwaNum: toNum(s.gwa)}));
      setActiveTab('Approved');   // Dean starts on Approved
      refreshDownloadButton();
    }).fail((xhr) => {
      const tb = $('#student-table tbody').empty();
      tb.append('<tr><td colspan="8" class="text-danger text-center">Failed to load students.</td></tr>');
      console.error('GET /dean/students-by-program error:', xhr.status, xhr.responseText);
    });
  });

  // ===== Tabs
  $('#statusTabs').on('click', '.nav-link', function() {
    setActiveTab($(this).data('status'));
  });

  // ===== PDF Viewer
  $(document).on('click', '.view-file', function (e) {
    e.preventDefault();
    const id = $(this).data('id');
    const url = `/dean/application/view/${id}`;
    const iframe = document.getElementById('pdfViewerFrame');
    iframe.src = url;
    new bootstrap.Modal(document.getElementById('pdfViewerModal')).show();
    $('#pdfViewerModal').one('hidden.bs.modal', () => { iframe.src=''; });
  });

  // ===== Approve (single)
  let _approveCtx = { id: null, $row: null };
  $(document).on('click', '.approve-one', function () {
    const $row = $(this).closest('tr');
    const appId = $row.data('app');
    _approveCtx = { id: appId, $row };

    const s = (window._allStudents || []).find(x => String(x.application_id) === String(appId));
    $('#approveDeanStudentName').text(s?.fullname ?? '—');
    $('#approveDeanStudentGwa').text(s?.gwa ? parseFloat(s.gwa).toFixed(2) : '—');
    $('#approveDeanStudentYear').text(s?.year_level ?? '—');

    new bootstrap.Modal(document.getElementById('confirmApproveModal')).show();
  });

  document.getElementById('confirmApproveYesBtn').addEventListener('click', function () {
    const btn = this;
    if (!_approveCtx.id) return;
    btn.disabled = true; btn.textContent = 'Approving...';

    $.ajax({
      url: "{{ route('dean.application.update-status') }}",
      method: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        id: _approveCtx.id,
        status: 'Approved'
      },
      complete: () => { btn.disabled = false; btn.textContent = 'Approve Student'; },
      success: () => {
        bootstrap.Modal.getInstance(document.getElementById('confirmApproveModal')).hide();
        updateLocalStatus(_approveCtx.id, 'Approved');
        setActiveTab('Approved');
        new bootstrap.Modal(document.getElementById('approveSuccessModal')).show();
        _approveCtx = { id:null, $row:null };
        refreshDownloadButton();
      },
      error: (xhr) => {
        alert('Failed to update status.');
        console.error('POST approve error:', xhr.status, xhr.responseText);
      }
    });
  });

  // ===== Bulk check
  $(document).on('change', '#check-all', function () {
    $('.row-check').prop('checked', this.checked);
    refreshBulkButton();
  });
  $(document).on('change', '.row-check', function () {
    const all = $('.row-check').length;
    const sel = $('.row-check:checked').length;
    $('#check-all').prop('checked', sel > 0 && sel === all);
    refreshBulkButton();
  });

  // ===== Download Report (Dean) — uses data-url-template from button
  $(document).on('click', '#download-report', function () {
    if (!_selectedProgramId) { alert('Please select a program first.'); return; }
    const term = prompt('Enter Term (e.g., Second Semester):', ''); if (!term) return;
    const ay   = prompt('Enter Academic Year (e.g., 2024-2025):', ''); if (!ay) return;

    const template = $(this).data('url-template'); // e.g. /dean/deanshonorlist/report/__PID__
    const baseUrl  = String(template).replace('__PID__', _selectedProgramId);
    const url      = `${baseUrl}?term=${encodeURIComponent(term)}&ay=${encodeURIComponent(ay)}`;

    // Navigate (not window.open) so DomPDF ->download() triggers OS save dialog consistently
    window.location.href = url;
  });

  // ===== Back
  $('#back-button').on('click', function () {
    if (!$('#students-container').hasClass('d-none')) {
      $('#students-container').addClass('d-none');
      $('#programs-container').removeClass('d-none');
    } else if (!$('#programs-container').hasClass('d-none')) {
      $('#programs-container').addClass('d-none');
      $('#college-container').removeClass('d-none');
      $('#back-button').addClass('d-none');
    }
    $('#student-table tbody').empty();
    $('#program-table tbody').empty();
    $('#check-all').prop('checked', false);
    refreshBulkButton();
    refreshDownloadButton();
  });
</script>
@endsection
