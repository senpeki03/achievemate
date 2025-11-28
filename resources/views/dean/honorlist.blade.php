@extends('dean.deansidebar') 

@section('content')
<style>
  .page-root { position: relative; z-index: 1; }
  .table-responsive { position: relative; z-index: 1; }
  #program-table tbody tr.selected { background: #e7f1ff !important; }

  /* Tab styling */
  .nav-tabs .nav-link {
    color: #495057;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
  }
  .nav-tabs .nav-link.active {
    color: white;
    background-color: #660000;
    border-color: #660000;
  }
  .nav-tabs .nav-link:not(.active):hover {
    color: #660000;
    background-color: #e9ecef;
    border-color: #dee2e6;
  }
</style>

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
      <div class="d-flex flex-column align-items-end gap-2">
        <button id="verify-selected" class="btn btn-success btn-sm" type="button" disabled>
          <i class="bi bi-check2-circle me-1"></i> Approve Selected
        </button>
      </div>
    </div>

    {{-- Tabs + Download button (same row) --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <ul class="nav nav-tabs mb-0" id="statusTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" data-status="For Approval" type="button" role="tab">For Approval</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-status="Approved" type="button" role="tab">Approved</button>
        </li>
      </ul>

      {{-- Download (enabled on Approved tab + program selected) --}}
      @php
        $deanReportUrlTemplate = route('dean.deanshonorlist.report', ['programId' => '__PID__']);
      @endphp
      <button id="download-report"
              class="btn btn-outline-light btn-sm ms-auto"
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
            <th style="width:25%">Fullname</th>
            <th style="width:10%">Year Level</th>
            <th style="width:10%">GWA</th>
            <th style="width:8%">Rank</th>
            <th style="width:10%">Status</th>
            {{-- IMPORTANT: dynamic header text --}}
            <th style="width:13%" id="date-column-header">Evaluation Date</th>
            <th style="width:15%">Action</th>
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
        <h5 class="fw-bold text-dark mb-4">Approve Student for Dean's List</h5>
        <p class="mb-3 text-dark">
          You are about to approve this student's application.<br>
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
        <p class="mb-4 text-muted">The student is now officially recognized as a Dean's Lister.</p>
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
  let currentStatusTab = 'For Approval';

  // ===== Helpers =====
  function badge(status) {
    const cls = {
      'For Evaluation':'bg-danger',
      'For Approval':'bg-warning text-dark',
      'Approved':'bg-success'
    }[status] || 'bg-secondary';
    return `<span class="badge ${cls}">${status || '—'}</span>`;
  }
  
  function toNum(v){ 
    const n = parseFloat(v); 
    return Number.isFinite(n) ? n : Infinity; 
  }
  
  function fmtGwa(v){ 
    const n = parseFloat(v); 
    return Number.isFinite(n) ? n.toFixed(4) : '—'; 
  }

  // Format date for display
  function fmtDate(dateStr) {
    if (!dateStr) return '—';
    try {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-PH', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
      });
    } catch (e) {
      return dateStr;
    }
  }

  function setActiveTab(status) {
    currentStatusTab = status;
    $('#statusTabs .nav-link').removeClass('active');
    $(`#statusTabs .nav-link[data-status="${status}"]`).addClass('active');
    renderStudentsByStatus(status);
    refreshDownloadButton();
  }

  // now accepts approvedDate
  function updateLocalStatus(appId, newStatus, approvedDate = null) {
    const it = (window._allStudents||[]).find(s => String(s.application_id) === String(appId));
    if (it) {
      it.status = newStatus;
      if (approvedDate) {
        // store approved date so Approved tab can show it
        it.approved_date = approvedDate;
      }
    }
  }

  function refreshBulkButton() {
    $('#verify-selected').prop('disabled', $('.row-check:checked').length === 0);
  }

  function refreshDownloadButton() {
    const onApproved = currentStatusTab === 'Approved';
    const hasApproved = (window._allStudents || []).some(s => s.status === 'Approved');
    const can = !!_selectedProgramId && onApproved && hasApproved;
    $('#download-report').prop('disabled', !can);
  }

  // ===== Render =====
  function renderStudentsByStatus(status) {
    const tb = $('#student-table tbody').empty();
    rowNo = 1;

    const data = (window._allStudents || []).filter(s => s.status === status);
    const isApprovedTab = (status === 'Approved');

    // Update column header text depending on tab
    $('#date-column-header').text(isApprovedTab ? 'Approved Date' : 'Evaluation Date');

    if (data.length === 0) {
      tb.append('<tr><td colspan="9" class="text-center text-muted py-4">No students found.</td></tr>');
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
      // Use gwa_formatted if available, otherwise format the original gwa
      const gwaDisplay = s.gwa_formatted || fmtGwa(s.gwa);

      // Choose which date to show
      let dateDisplay;
      if (isApprovedTab) {
        // ONLY approved date sa Approved tab
        dateDisplay = fmtDate(s.approved_date || s.ApprovedDate || s.approvedDate);
      } else {
        // Evaluation date sa For Approval tab
        dateDisplay = fmtDate(s.evaluation_date || s.Date || s.date);
      }
      
      tb.append(`
        <tr data-app="${s.application_id}">
          <td><input type="checkbox" class="row-check"></td>
          <td>${rowNo++}</td>
          <td class="text-start">${(s.fullname || '').toUpperCase()}</td>
          <td>${s.year_level || ''}</td>
          <td>${gwaDisplay}</td>
          <td>${s.rank ?? '—'}</td>
          <td class="status-cell fw-bold">${badge(s.status)}</td>
          <td>${dateDisplay}</td>
          <td>
            <div class="d-flex justify-content-center align-items-center gap-2">
              <button class="btn btn-sm btn-outline-primary view-file" data-id="${s.application_id}" title="View PDF" type="button">
                <i class="bi bi-eye"></i>
              </button>
              ${status === 'For Approval' ? `
                <button class="btn btn-sm btn-outline-success approve-one" title="Approve" type="button">
                  <i class="bi bi-check2-circle"></i>
                </button>
              ` : ''}
            </div>
          </td>
        </tr>
      `);
    });

    $('#check-all').prop('checked', false);
    refreshBulkButton();
  }

  // ===== NAV: College -> Programs - FIXED URL
  $(document).on('click', '.clickable-row', function () {
    const collegeId = $(this).data('id');

    $('#college-container').addClass('d-none');
    $('#back-button').removeClass('d-none');
    $('#programs-container').removeClass('d-none');
    $('#students-container').addClass('d-none');

    $('#program-table tbody').empty();
    $('#student-table tbody').empty();

    $.getJSON("{{ route('dean.programs', ['collegeId' => '__ID__']) }}".replace('__ID__', collegeId), function (data) {
      const tb = $('#program-table tbody').empty();
      if (!data || data.length === 0) {
        tb.append('<tr><td class="text-center">No programs found for this college</td></tr>');
        return;
      }
      data.forEach(p => {
        tb.append(`<tr class="clickable-program" data-id="${p.Program_id}"><td class="text-uppercase">${p.Program_name}</td></tr>`);
      });
    }).fail(function(xhr) {
      console.error('Failed to load programs:', xhr);
      const tb = $('#program-table tbody').empty();
      tb.append('<tr><td class="text-center text-danger">Failed to load programs</td></tr>');
    });
  });

  // ===== NAV: Programs -> Students - FIXED URL
  $(document).on('click', '.clickable-program', function () {
    _selectedProgramId = $(this).data('id');

    $('#programs-container').addClass('d-none');
    $('#students-container').removeClass('d-none');

    $.getJSON("{{ route('dean.students.by.program', ['programId' => '__ID__']) }}".replace('__ID__', _selectedProgramId), function (data) {
      console.log('Students data received:', data);
      
      // Process students data - add numeric GWA for sorting and ensure formatted GWA
      window._allStudents = (data || []).map(s => {
        const gwaFormatted = s.gwa_formatted || fmtGwa(s.gwa);
        return {
          ...s,
          _gwaNum: toNum(s.gwa),
          gwa_formatted: gwaFormatted
          // assume backend may send evaluation_date + approved_date
        };
      });
      
      setActiveTab('For Approval');
      refreshDownloadButton();
    }).fail((xhr) => {
      const tb = $('#student-table tbody').empty();
      tb.append('<tr><td colspan="9" class="text-danger text-center">Failed to load students.</td></tr>');
      console.error('GET students error:', xhr.status, xhr.responseText);
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
    const url = "{{ route('dean.application.view', ['id' => '__ID__']) }}".replace('__ID__', id);
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
    
    const gwaDisplay = s?.gwa_formatted || (s?.gwa ? parseFloat(s.gwa).toFixed(4) : '—');
    $('#approveDeanStudentGwa').text(gwaDisplay);
    
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

        // set local status + approved date (today) for UI
        const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
        updateLocalStatus(_approveCtx.id, 'Approved', today);

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

  // ===== Bulk Approve
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

  $('#verify-selected').on('click', function() {
    const ids = [];
    $('#student-table tbody tr').each(function() {
      const $cb = $(this).find('.row-check');
      if ($cb.is(':checked')) {
        const id = $(this).data('app');
        if (id) ids.push(id);
      }
    });
    
    if (ids.length === 0) return;
    if (!confirm(`Approve ${ids.length} selected student(s)?`)) return;

    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="bi bi-check2-circle me-1"></i> Approving...');

    $.ajax({
      url: "{{ route('dean.application.bulk-approve') }}",
      method: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        application_ids: ids
      },
      success: () => {
        const today = new Date().toISOString().slice(0,10);

        ids.forEach(id => updateLocalStatus(id, 'Approved', today));
        $('#check-all').prop('checked', false);
        refreshBulkButton();
        setActiveTab('Approved');
        
        new bootstrap.Modal(document.getElementById('approveSuccessModal')).show();
      },
      error: (xhr) => {
        alert('Failed to approve selected students.');
        console.error('POST bulk-approve error:', xhr.status, xhr.responseText);
      },
      complete: () => {
        $btn.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i> Approve Selected');
      }
    });
  });

  // ===== Download Report (Dean)
  $(document).on('click', '#download-report', function () {
    if (!_selectedProgramId) { alert('Please select a program first.'); return; }
    const term = prompt('Enter Term (e.g., Second Semester):', ''); if (!term) return;
    const ay   = prompt('Enter Academic Year (e.g., 2024-2025):', ''); if (!ay) return;

    const url = "{{ url('/dean/deanshonorlist/report') }}/" + _selectedProgramId + 
                "?term=" + encodeURIComponent(term) + 
                "&ay=" + encodeURIComponent(ay);

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
