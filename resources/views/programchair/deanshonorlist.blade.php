@extends('programchair.programchairsidebar')

@section('content')
<style>
  .page-root { position: relative; z-index: 1; }
  .table-responsive { position: relative; z-index: 1; }
  #program-table tbody tr.selected { background: #e7f1ff !important; }

  /* hide the first (selection) column when not verifying */
  .hide-select-col th.select-col,
  .hide-select-col td.select-col { display: none !important; }
  
  /* hide evaluation date column for For Evaluation tab */
  .hide-eval-date th.eval-date-col,
  .hide-eval-date td.eval-date-col { display: none !important; }

  /* Download dropdown styling */
  .download-dropdown .btn-group .btn {
    border-radius: 0.375rem;
  }
  .download-dropdown .dropdown-menu {
    min-width: 200px;
  }
</style>

<div class="container py-4 page-root">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold text-white m-0">Dean's Honor List</h3>
    <button id="back-button" class="btn btn-outline-light d-none">
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
        <tbody class="align-middle"></tbody>
      </table>
    </div>
  </div>

  {{-- Students --}}
  <div id="students-container" class="d-none">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <div class="d-flex flex-column align-items-end gap-2">
        <button id="verify-selected" class="btn btn-success btn-sm" disabled>
          <i class="bi bi-check2-circle me-1"></i> Verify Selected
        </button>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <ul class="nav nav-tabs mb-0" id="statusTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active text-white" id="tab-eval" data-status="For Evaluation" type="button" role="tab" 
                  style="background-color: #660000; border-color: #660000;">
            Initial Review
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link text-dark bg-white border-white" id="tab-approval" data-status="For Approval" type="button" role="tab">
            Final Review
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link text-dark bg-white border-white" id="tab-approved" data-status="Approved" type="button" role="tab">
            Qualified
          </button>
        </li>
      </ul>

      {{-- Download Dropdown --}}
      <div class="download-dropdown">
        <div class="btn-group">
          <button id="download-report" class="btn btn-outline-light btn-sm" disabled
                  title="Select a program and view the Approved tab to download.">
            <i class="bi bi-download me-1"></i> Download
          </button>
          <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle dropdown-toggle-split" 
                  data-bs-toggle="dropdown" aria-expanded="false" id="download-dropdown-toggle" disabled>
            <span class="visually-hidden">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu">
            <li>
              <a class="dropdown-item download-option" href="#" data-type="report">
                <i class="bi bi-file-earmark-text me-2"></i>Download Report
              </a>
            </li>
            <li>
              <a class="dropdown-item download-option" href="#" data-type="posting">
                <i class="bi bi-megaphone me-2"></i>Download for Posting
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped bg-white shadow-sm rounded-4 overflow-hidden text-center" id="student-table">
        <thead class="table-light">
          <tr>
            <th class="select-col" style="width:5%;"><input type="checkbox" id="check-all"></th>
            <th style="width:6%;">#</th>
            <th style="width:28%;">Fullname</th>
            <th style="width:12%;">Year Level</th>
            <th style="width:12%;">GWA</th>
            <th style="width:10%;">Rank</th>
            <th style="width:12%;">Status</th>
            <th style="width:15%;" class="eval-date-col">Evaluation Date</th> <!-- NEW COLUMN -->
            <th style="width:15%;">Action</th>
          </tr>
        </thead>
        <tbody class="align-middle"></tbody>
      </table>
    </div>
  </div>
</div>

{{-- ====== MODALS ====== --}}
{{-- Confirm Verify (single) --}}
<div class="modal fade" id="confirmVerifyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256"
             style="width:60px" class="mb-3" alt="">
        <h5 class="fw-bold text-dark mb-4">Endorse Student for Dean's List Approval</h5>
        <p class="mb-4 text-dark" id="endorseMessage">
          You are about to endorse this student's application to the Dean for final approval.<br>
          <span id="endorseStudentInfo">Student: <span class="fw-bold" id="endorseStudentName">[Student Name]</span><br>GWA: <span id="endorseStudentGwa">[GWA]</span> | Year Level: <span id="endorseStudentYear">[Year]</span></span>
        </p>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-success px-4" id="confirmVerifyYesBtn">Endorse Student</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Verify Success (used for single & bulk) --}}
<div class="modal fade" id="verifySuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
                 autoplay muted loop playsinline type="video/mp4"
                 style="width:70px;height:70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1" id="verifySuccessTitle">Endorsement Successful</h5>
        <p class="mb-4 text-muted" id="verifySuccessDesc">The student's application has been endorsed to the Dean for approval.<br>Status has been updated to <b>For Approval</b>.</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal" id="verifySuccessOkBtn">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- View PDF --}}
<div class="modal fade" id="viewPdfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header">
        <h5 class="modal-title">Student Application</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" style="height: 80vh;">
        <iframe id="pdfFrame" src="" width="100%" height="100%" style="border:0;"></iframe>
      </div>
    </div>
  </div>
</div>

{{-- Download Options Modal --}}
<div class="modal fade" id="downloadOptionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
    <div class="modal-content border-0 rounded-4 shadow">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Download Dean's Honor List</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body pt-0">

        <div class="mb-3">
          <label class="form-label fw-bold">Academic Year</label>
          <select id="dlAcademicYear" class="form-select">
            <option value="">Loading...</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Semester</label>
          <select id="dlSemester" class="form-select">
            <option value="">Loading...</option>
          </select>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="downloadConfirmBtn">
            <i class="bi bi-download me-1"></i> Download
          </button>
        </div>

      </div>
    </div>
  </div>
</div>

{{-- Posting Download Options Modal --}}
<div class="modal fade" id="postingDownloadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
    <div class="modal-content border-0 rounded-4 shadow">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-megaphone me-2"></i>Download for Posting
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body pt-0">

        <div class="mb-3">
          <label class="form-label fw-bold">Academic Year</label>
          <select id="postingAcademicYear" class="form-select">
            <option value="">Loading...</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Semester</label>
          <select id="postingSemester" class="form-select">
            <option value="">Loading...</option>
          </select>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-success" id="postingDownloadConfirmBtn">
            <i class="bi bi-download me-1"></i> Download for Posting
          </button>
        </div>

      </div>
    </div>
  </div>
</div>

@push('head')
  <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

<script>
  // ---------- Globals ----------
  let rowCounter = 1;
  let currentStatusTab = 'For Evaluation';
  window._allStudents = [];
  window._currentProgramId = null;
  let currentDownloadType = 'report'; // 'report' or 'posting'

  // Enable/disable Download Report
  function refreshDownloadBtn() {
    const mainBtn = document.getElementById('download-report');
    const dropdownToggle = document.getElementById('download-dropdown-toggle');
    const hasApproved = (window._allStudents || []).some(s => s.status === 'Approved');
    const enable = !!window._currentProgramId && currentStatusTab === 'Approved' && hasApproved;

    mainBtn.disabled = !enable;
    dropdownToggle.disabled = !enable;
    
    mainBtn.title = enable
      ? 'Download Dean\'s Honor List for this program.'
      : 'Select a program, switch to the Approved tab, and ensure there are approved students.';
  }

  // Hide verify UI on both "For Approval" and "Approved"
  function toggleVerifyUIForTab(status) {
    const bulkBtn = document.getElementById('verify-selected');
    const table = document.getElementById('student-table');

    const noVerify = (status === 'For Approval' || status === 'Approved');

    // bulk verify button
    bulkBtn.classList.toggle('d-none', noVerify);

    // hide the selection column entirely when not verifying
    table.classList.toggle('hide-select-col', noVerify);

    // hide evaluation date column for For Evaluation tab
    const hideEvalDate = (status === 'For Evaluation');
    table.classList.toggle('hide-eval-date', hideEvalDate);

    // clear lingering selections when switching
    const master = document.getElementById('check-all');
    if (master) master.checked = false;
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    refreshBulkButton();
  }

  // ---------- Download Dropdown Functionality ----------
  document.addEventListener('click', function(e) {
    // Handle dropdown option clicks
    if (e.target.closest('.download-option')) {
      e.preventDefault();
      const option = e.target.closest('.download-option');
      const type = option.dataset.type;
      currentDownloadType = type;
      
      if (type === 'report') {
        showReportDownloadModal();
      } else if (type === 'posting') {
        showPostingDownloadModal();
      }
    }
    
    // Handle main download button click (default to report)
    if (e.target.closest('#download-report') && !e.target.closest('#download-report').disabled) {
      e.preventDefault();
      currentDownloadType = 'report';
      showReportDownloadModal();
    }
  });

  function showReportDownloadModal() {
    const programId = window._currentProgramId;
    if (!programId) {
      alert("Please select a program first.");
      return;
    }

    // prepare dropdowns
    const aySelect  = document.getElementById('dlAcademicYear');
    const semSelect = document.getElementById('dlSemester');

    aySelect.innerHTML  = '<option value="">Loading...</option>';
    semSelect.innerHTML = '<option value="">Loading...</option>';

    fetch(`/programchair/deans-honor-list/options/${programId}`)
      .then(r => r.json())
      .then(data => {
        const years = data.academic_years || [];
        const sems  = data.semesters || [];

        aySelect.innerHTML =
          '<option value="">Select Academic Year</option>' +
          years.map(y => `<option value="${y}">${y}</option>`).join('');

        semSelect.innerHTML =
          '<option value="">Select Semester</option>' +
          sems.map(s => `<option value="${s}">${s}</option>`).join('');

        // auto select latest post
        if (data.default_ay) aySelect.value = data.default_ay;
        if (data.default_semester) semSelect.value = data.default_semester;

        new bootstrap.Modal(document.getElementById('downloadOptionsModal')).show();
      })
      .catch(() => {
        alert("Unable to load Academic Year/Semester from posts.");
      });
  }

  function showPostingDownloadModal() {
    const programId = window._currentProgramId;
    if (!programId) {
      alert("Please select a program first.");
      return;
    }

    // prepare dropdowns
    const aySelect  = document.getElementById('postingAcademicYear');
    const semSelect = document.getElementById('postingSemester');

    aySelect.innerHTML  = '<option value="">Loading...</option>';
    semSelect.innerHTML = '<option value="">Loading...</option>';

    fetch(`/programchair/deans-honor-list/options/${programId}`)
      .then(r => r.json())
      .then(data => {
        const years = data.academic_years || [];
        const sems  = data.semesters || [];

        aySelect.innerHTML =
          '<option value="">Select Academic Year</option>' +
          years.map(y => `<option value="${y}">${y}</option>`).join('');

        semSelect.innerHTML =
          '<option value="">Select Semester</option>' +
          sems.map(s => `<option value="${s}">${s}</option>`).join('');

        // auto select latest post
        if (data.default_ay) aySelect.value = data.default_ay;
        if (data.default_semester) semSelect.value = data.default_semester;

        new bootstrap.Modal(document.getElementById('postingDownloadModal')).show();
      })
      .catch(() => {
        alert("Unable to load Academic Year/Semester from posts.");
      });
  }

  // ======= Modal → Confirm Report Download =======
  document.getElementById('downloadConfirmBtn').addEventListener('click', function () {
    const ay   = document.getElementById('dlAcademicYear').value;
    const sem  = document.getElementById('dlSemester').value;

    if (!ay || !sem) {
      alert("Please choose both Academic Year and Semester.");
      return;
    }

    const programId = window._currentProgramId;
    const base = "{{ url('/programchair/deans-honor-list/report') }}/" + programId;

    const url = `${base}?term=${encodeURIComponent(sem)}&ay=${encodeURIComponent(ay)}`;

    bootstrap.Modal.getInstance(document.getElementById('downloadOptionsModal')).hide();

    window.location.href = url;
  });

  // ======= Modal → Confirm Posting Download =======
  document.getElementById('postingDownloadConfirmBtn').addEventListener('click', function () {
    const ay   = document.getElementById('postingAcademicYear').value;
    const sem  = document.getElementById('postingSemester').value;

    if (!ay || !sem) {
      alert("Please choose both Academic Year and Semester.");
      return;
    }

    const programId = window._currentProgramId;
    const base = "{{ url('/programchair/deans-honor-list/download') }}/" + programId;

    const url = `${base}?term=${encodeURIComponent(sem)}&ay=${encodeURIComponent(ay)}`;

    bootstrap.Modal.getInstance(document.getElementById('postingDownloadModal')).hide();

    // Open in new tab for posting download
    window.open(url, '_blank');
  });

  // ---------- Helpers ----------
  function setActiveTab(status) {
    currentStatusTab = status;
    
    // Update tab appearances
    document.querySelectorAll('#statusTabs .nav-link').forEach(btn => {
      const isActive = btn.dataset.status === status;
      if (isActive) {
        btn.classList.add('active', 'text-white');
        btn.classList.remove('text-dark', 'bg-white');
        btn.style.backgroundColor = '#660000';
        btn.style.borderColor = '#660000';
      } else {
        btn.classList.remove('active', 'text-white');
        btn.classList.add('text-dark', 'bg-white');
        btn.style.backgroundColor = '';
        btn.style.borderColor = '';
      }
    });
    
    renderStudentsByStatus(status);
    toggleVerifyUIForTab(status);
    refreshDownloadBtn();
  }

  function updateLocalStatus(appId, newStatus) {
    const it = (window._allStudents || []).find(s => String(s.application_id) === String(appId));
    if (it) it.status = newStatus;
  }

  function badge(status){
    const map = { 'For Evaluation':'bg-danger', 'For Approval':'bg-warning text-dark', 'Approved':'bg-success' };
    return `<span class="badge ${map[status] || 'bg-secondary'}">${status || '-'}</span>`;
  }
  function numOrInf(v){ const n = parseFloat(v); return Number.isFinite(n) ? n : Infinity; }
  function round4(n){ return Math.round(n * 10000) / 10000; }
  function fmtGwa(v){ const n = parseFloat(v); return Number.isFinite(n) ? n.toFixed(4) : '-'; }
  
  // Format date for display
  function fmtDate(dateStr) {
    if (!dateStr) return '-';
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

  function computeRanks(list){
    let lastGwa = null, lastRank = 0, seen = 0;
    list.forEach(s => {
      if (s.rank !== null && s.rank !== undefined && s.rank !== '') return;
      if (!Number.isFinite(s._gwaNum)) { s.rank = '-'; return; }
      seen++;
      if (lastGwa === null || s._gwaNum !== lastGwa) { lastRank = seen; lastGwa = s._gwaNum; }
      s.rank = lastRank;
    });
  }
  function refreshBulkButton(){
    const any = document.querySelectorAll('.row-check:checked').length > 0;
    const bulkBtn = document.getElementById('verify-selected');
    if (!bulkBtn.classList.contains('d-none')) {
      bulkBtn.disabled = !any;
    }
  }

  // ---------- Colleges -> Programs ----------
  document.addEventListener('click', function(e){
    const row = e.target.closest('.clickable-row');
    if (!row) return;

    const collegeId = row.dataset.id;

    document.getElementById('college-container').classList.add('d-none');
    document.getElementById('back-button').classList.remove('d-none');
    document.getElementById('programs-container').classList.remove('d-none');
    document.getElementById('students-container').classList.add('d-none');

    const tbody = document.querySelector('#program-table tbody');
    tbody.innerHTML = '';
    document.querySelector('#student-table tbody').innerHTML = '';
    window._currentProgramId = null;
    refreshDownloadBtn();

    // FIXED: Use simple URL construction
    const url = `/programchair/programs/${collegeId}`;
    
    fetch(url)
      .then(r => {
        console.log('Programs response status:', r.status);
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then(data => {
        console.log('Programs data:', data);
        if (!data || data.length === 0) {
          tbody.innerHTML = '<tr><td class="text-center">No programs found.</td></tr>';
          return;
        }
        tbody.innerHTML = data.map(p => `
          <tr class="clickable-program" data-id="${p.Program_id}">
            <td class="text-uppercase">${p.Program_name}</td>
          </tr>
        `).join('');
      })
      .catch(err => {
        console.error('GET programs error:', err);
        tbody.innerHTML = '<tr><td class="text-danger text-center">Failed to load programs.</td></tr>';
      });
  });

  // ---------- Programs -> Students ----------
  document.addEventListener('click', function(e){
    const row = e.target.closest('.clickable-program');
    if (!row) return;

    document.querySelectorAll('#program-table tbody tr').forEach(tr => tr.classList.remove('selected'));
    row.classList.add('selected');

    const programId = row.dataset.id;
    window._currentProgramId = programId;
    refreshDownloadBtn();

    document.getElementById('programs-container').classList.add('d-none');
    document.getElementById('students-container').classList.remove('d-none');

    // FIXED: Use simple URL construction
    const url = `/programchair/students-by-program/${programId}`;
    
    fetch(url)
      .then(r => {
        console.log('Students response status:', r.status);
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then(data => {
        console.log('Raw students data:', data);
        window._allStudents = (data || []).map(s => {
          const raw = numOrInf(s.gwa);
          const gwa4 = Number.isFinite(raw) ? round4(raw) : Infinity;
          return { ...s, _gwaNum: gwa4 };
        });
        console.log('Processed students:', window._allStudents);
        setActiveTab('For Evaluation'); // default
      })
      .catch(err => {
        console.error('GET students error:', err);
        const tb = document.querySelector('#student-table tbody');
        tb.innerHTML = '<tr><td colspan="8" class="text-danger text-center">Failed to load students: ' + err.message + '</td></tr>';
      });
  });

  // ---------- Tabs ----------
  document.getElementById('statusTabs').addEventListener('click', function(e) {
    if (e.target.classList.contains('nav-link')) {
      setActiveTab(e.target.dataset.status);
    }
  });

  // ---------- Render table ----------
  function renderStudentsByStatus(status) {
      const tb = document.querySelector('#student-table tbody');
      tb.innerHTML = '';
      rowCounter = 1;

      const noVerify = (status === 'For Approval' || status === 'Approved');
      
      // Filter by status, but if no students found with that status, show all for debugging
      let list = (window._allStudents || []).filter(s => s.status === status);
      
      console.log('Rendering students for status:', status, 'Count:', list.length);
      console.log('All students statuses:', (window._allStudents || []).map(s => s.status));

      // If no students with the selected status, show a message but also log all available statuses
      if (list.length === 0) {
          const allStatuses = [...new Set((window._allStudents || []).map(s => s.status))];
          const colspan = status === 'For Evaluation' ? 8 : 9; // Adjust colspan based on visible columns
          tb.innerHTML = `
              <tr>
                  <td colspan="${colspan}" class="text-center">
                      No students found with status: <strong>${status}</strong><br>
                      <small class="text-muted">Available statuses: ${allStatuses.join(', ') || 'None'}</small>
                  </td>
              </tr>
          `;
          const master = document.getElementById('check-all');
          if (master) master.checked = false;
          refreshBulkButton();
          return;
      }

      list.sort((a,b) => a._gwaNum !== b._gwaNum
        ? a._gwaNum - b._gwaNum
        : (a.fullname||'').toUpperCase().localeCompare((b.fullname||'').toUpperCase())
      );
      computeRanks(list);

      tb.innerHTML = list.map(s => {
          const selectCell = noVerify
              ? `<td class="select-col"></td>`
              : `<td class="select-col"><input type="checkbox" class="row-check"></td>`;

          const actionButtons = noVerify
              ? `
                  <div class="d-flex justify-content-center align-items-center gap-2">
                      <button class="btn btn-sm btn-outline-primary view-file" data-id="${s.application_id}" title="View PDF">
                          <i class="bi bi-eye"></i>
                      </button>
                  </div>
              `
              : `
                  <div class="d-flex justify-content-center align-items-center gap-2">
                      <button class="btn btn-sm btn-outline-primary view-file" data-id="${s.application_id}" title="View PDF">
                          <i class="bi bi-eye"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-success verify-student" title="Verify">
                          <i class="bi bi-check2-circle"></i>
                      </button>
                  </div>
              `;

          // For Evaluation Date column - only show for For Approval and Approved
          const evalDateCell = (status === 'For Approval' || status === 'Approved') 
              ? `<td class="eval-date-col">${fmtDate(s.evaluation_date)}</td>`
              : '';

          return `
              <tr>
                  ${selectCell}
                  <td>${rowCounter++}</td>
                  <td class="text-start">${(s.fullname || '').toUpperCase()}</td>
                  <td>${s.year_level ?? '-'}</td>
                  <td>${fmtGwa(s.gwa)}</td>
                  <td>${s.rank ?? '-'}</td>
                  <td class="status-cell fw-bold">${badge(s.status)}</td>
                  ${evalDateCell} <!-- Evaluation Date Column (conditionally rendered) -->
                  <td>
                      ${actionButtons}
                      <input type="hidden" class="application-id" value="${s.application_id}">
                  </td>
              </tr>
          `;
      }).join('');

      toggleVerifyUIForTab(status);
      refreshBulkButton();
      refreshDownloadBtn();
  }

  // ---------- PDF Viewer ----------
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.view-file');
    if (!btn) return;
    e.preventDefault();
    const id = btn.dataset.id;
    // FIXED: Use simple URL construction
    const url = `/programchair/application/view/${id}?v=${Date.now()}`;
    document.getElementById('pdfFrame').src = url;
    new bootstrap.Modal(document.getElementById('viewPdfModal')).show();
  });
  document.getElementById('viewPdfModal').addEventListener('hidden.bs.modal', function(){
    document.getElementById('pdfFrame').src = '';
  });

  // ---------- Single Verify ----------
  let _verifyContext = { id: null, $row: null };

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.verify-student');
    if (!btn) return;

    const row = btn.closest('tr');
    const id = row.querySelector('.application-id')?.value || row.querySelector('.view-file')?.dataset.id;
    const name = row.querySelector('td:nth-child(3)')?.textContent?.trim() || '';
    const gwa = row.querySelector('td:nth-child(5)')?.textContent?.trim() || '';
    const year = row.querySelector('td:nth-child(4)')?.textContent?.trim() || '';

    document.getElementById('endorseStudentName').textContent = name;
    document.getElementById('endorseStudentGwa').textContent = gwa;
    document.getElementById('endorseStudentYear').textContent = year;

    _verifyContext = { id, $row: row };
    new bootstrap.Modal(document.getElementById('confirmVerifyModal')).show();
  });

  document.getElementById('confirmVerifyYesBtn').addEventListener('click', function () {
    const btn = this;
    if (!_verifyContext.id) return;

    btn.disabled = true; btn.textContent = 'Verifying...';

    // Get current date for evaluation
    const currentDate = new Date().toISOString().split('T')[0]; // YYYY-MM-DD format

    fetch("{{ route('programchair.application.update-status') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({ 
        id: _verifyContext.id, 
        status: 'For Approval',
        evaluation_date: currentDate // Send date with the request
      })
    })
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(() => {
      bootstrap.Modal.getInstance(document.getElementById('confirmVerifyModal')).hide();
      updateLocalStatus(_verifyContext.id, 'For Approval');
      
      // Update local student data with evaluation date
      const student = window._allStudents.find(s => String(s.application_id) === String(_verifyContext.id));
      if (student) {
        student.evaluation_date = currentDate;
      }
      
      setActiveTab('For Approval');

      document.getElementById('verifySuccessTitle').textContent = 'Success!';
      document.getElementById('verifySuccessDesc').textContent = 'Student was successfully endorsed for Dean approval.';
      new bootstrap.Modal(document.getElementById('verifySuccessModal')).show();

      _verifyContext = { id:null, $row:null };
    })
    .catch(err => {
      alert('Failed to update status.');
      console.error('POST update-status error:', err);
    })
    .finally(() => { btn.disabled = false; btn.textContent = 'Endorse Student'; });
  });

  // ---------- Bulk Verify ----------
  document.getElementById('verify-selected').addEventListener('click', function(){
    const ids = [];
    document.querySelectorAll('#student-table tbody tr').forEach(tr => {
      const cb = tr.querySelector('.row-check');
      if (cb && cb.checked) {
        const id = tr.querySelector('.application-id')?.value;
        if (id) ids.push(id);
      }
    });
    if (ids.length === 0) return;
    if (!confirm(`Verify ${ids.length} selected student(s)?`)) return;

    const btn = this;
    btn.disabled = true; btn.textContent = 'Verifying...';

    // Get current date for evaluation
    const currentDate = new Date().toISOString().split('T')[0]; // YYYY-MM-DD format

    fetch("{{ route('programchair.application.bulk-verify') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({ 
        application_ids: ids,
        evaluation_date: currentDate // Send date with bulk request
      })
    })
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(() => {
      // Update local student data with evaluation dates
      ids.forEach(id => {
        updateLocalStatus(id, 'For Approval');
        const student = window._allStudents.find(s => String(s.application_id) === String(id));
        if (student) {
          student.evaluation_date = currentDate;
        }
      });
      
      const master = document.getElementById('check-all');
      if (master) master.checked = false;
      refreshBulkButton();
      setActiveTab('For Approval');

      document.getElementById('verifySuccessTitle').textContent = 'Endorsed!';
      document.getElementById('verifySuccessDesc').textContent = `${ids.length} student(s) were successfully endorsed for Dean approval.`;
      new bootstrap.Modal(document.getElementById('verifySuccessModal')).show();
    })
    .catch(err => {
      alert('Failed to verify selected.');
      console.error('POST bulk-verify error:', err);
    })
    .finally(() => { 
      btn.disabled = false; 
      btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Verify Selected'; 
    });
  });

  // ---------- Back button ----------
  document.getElementById('back-button').addEventListener('click', function(){
    if (!document.getElementById('students-container').classList.contains('d-none')) {
      document.getElementById('students-container').classList.add('d-none');
      document.getElementById('programs-container').classList.remove('d-none');
    } else if (!document.getElementById('programs-container').classList.contains('d-none')) {
      document.getElementById('programs-container').classList.add('d-none');
      document.getElementById('college-container').classList.remove('d-none');
      document.getElementById('back-button').classList.add('d-none');
    }
    document.querySelector('#student-table tbody').innerHTML = '';
    document.querySelector('#program-table tbody').innerHTML = '';
    window._currentProgramId = null;
    refreshDownloadBtn();
  });
</script>
@endsection