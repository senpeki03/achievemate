@extends('programchair.programchairsidebar')

@section('content')
<style>
  /* Keep this page's content under the fixed topbar and never above it */
  .page-root { position: relative; z-index: 1; }
  /* Just in case any table wrapper gets weird, confine it */
  .table-responsive { position: relative; z-index: 1; }
</style>

<div class="container py-4 page-root">
  {{-- Header with back button --}}
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
        <tr>
          <th>College Name</th>
        </tr>
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
          <tr>
            <th>Program Name</th>
          </tr>
        </thead>
        <tbody class="align-middle">
          {{-- Injected by JS --}}
        </tbody>
      </table>
    </div>
  </div>

  {{-- Students --}}
  <div id="students-container" class="d-none">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h4 class="text-dark fw-bold">Students</h4>
      <button id="verify-selected" class="btn btn-success btn-sm" disabled>
        <i class="bi bi-check2-circle me-1"></i> Verify Selected
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-striped bg-white shadow-sm rounded-4 overflow-hidden text-center" id="student-table">
        <thead class="table-light">
          <tr>
            <th style="width:5%;"><input type="checkbox" id="check-all"></th>
            <th style="width:6%;">#</th>
            <th style="width:29%;">Fullname</th>
            <th style="width:12%;">Year Level</th>
            <th style="width:12%;">GWA</th>
            <th style="width:10%;">Rank</th>
            <th style="width:13%;">Status</th>
            <th style="width:13%;">Action</th>
          </tr>
        </thead>
        <tbody class="align-middle">
          {{-- Injected by JS --}}
        </tbody>
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
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to verify this student?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" id="confirmVerifyYesBtn">Yes</button>
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
        <h5 class="text-success fw-bold mb-1" id="verifySuccessTitle">Success!</h5>
        <p class="mb-4 text-muted" id="verifySuccessDesc">Student was successfully verified.</p>
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

@push('head')
  <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

{{-- IMPORTANT: do NOT include jQuery or Bootstrap here; the layout already loads bootstrap.bundle.js --}}
<script>
  let rowCounter = 1;

  // ----- Helpers -----
  function badge(status){
    const map = { 'Pending':'bg-danger', 'Verified':'bg-warning text-dark', 'Approved':'bg-success' };
    return `<span class="badge ${map[status] || 'bg-secondary'}">${status || '—'}</span>`;
  }
  function numOrInf(v){
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : Infinity;
  }
  function round4(n){ return Math.round(n * 10000) / 10000; }
  function fmtGwa(v){
    const n = parseFloat(v);
    return Number.isFinite(n) ? n.toFixed(4) : '—';
  }
  function computeRanks(list){
    let lastGwa = null, lastRank = 0, seen = 0;
    list.forEach(s => {
      if (s.rank !== null && s.rank !== undefined && s.rank !== '') return;
      if (!Number.isFinite(s._gwaNum)) { s.rank = '—'; return; }
      seen++;
      if (lastGwa === null || s._gwaNum !== lastGwa) { lastRank = seen; lastGwa = s._gwaNum; }
      s.rank = lastRank;
    });
  }
  function refreshBulkButton(){
    const any = document.querySelectorAll('.row-check:checked').length > 0;
    document.getElementById('verify-selected').disabled = !any;
  }

  // ----- Colleges -> Programs -----
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

    fetch('/programchair/programs/' + collegeId)
      .then(r => r.json())
      .then(data => {
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
        tbody.innerHTML = '<tr><td class="text-danger text-center">Failed to load programs.</td></tr>';
        console.error('GET /programchair/programs error:', err);
      });
  });

  // ----- Programs -> Students -----
  document.addEventListener('click', function(e){
    const row = e.target.closest('.clickable-program');
    if (!row) return;

    const programId = row.dataset.id;

    document.getElementById('programs-container').classList.add('d-none');
    document.getElementById('students-container').classList.remove('d-none');

    fetch('/programchair/students-by-program/' + programId)
      .then(r => r.json())
      .then(data => {
        const tb = document.querySelector('#student-table tbody');
        tb.innerHTML = '';
        rowCounter = 1;

        if (!data || data.length === 0) {
          tb.innerHTML = '<tr><td colspan="8" class="text-center">No students found.</td></tr>';
          return;
        }

        const normalized = data.map(s => {
            const raw = numOrInf(s.gwa);
            const gwa4 = Number.isFinite(raw) ? round4(raw) : Infinity;
            return { ...s, _gwaNum: gwa4 };
          })
          .sort((a,b) => a._gwaNum !== b._gwaNum
            ? a._gwaNum - b._gwaNum
            : (a.fullname||'').toUpperCase().localeCompare((b.fullname||'').toUpperCase())
          );

        computeRanks(normalized);

        tb.innerHTML = normalized.map(s => `
          <tr>
            <td><input type="checkbox" class="row-check"></td>
            <td>${rowCounter++}</td>
            <td class="text-end">${(s.fullname || '').toUpperCase()}</td>
            <td>${s.year_level ?? '—'}</td>
            <td>${fmtGwa(s.gwa)}</td>
            <td>${s.rank ?? '—'}</td>
            <td class="status-cell fw-bold">${badge(s.status)}</td>
            <td>
              <div class="d-flex justify-content-center align-items-center gap-2">
                <button class="btn btn-sm btn-outline-primary view-file" data-id="${s.application_id}" title="View PDF">
                  <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-success verify-student" title="Verify">
                  <i class="bi bi-check2-circle"></i>
                </button>
              </div>
              <input type="hidden" class="application-id" value="${s.application_id}">
            </td>
          </tr>
        `).join('');

        // reset group controls
        document.getElementById('check-all').checked = false;
        refreshBulkButton();
      })
      .catch(err => {
        const tb = document.querySelector('#student-table tbody');
        tb.innerHTML = '<tr><td colspan="8" class="text-danger text-center">Failed to load students.</td></tr>';
        console.error('GET /programchair/students-by-program error:', err);
      });
  });

  // ===== PDF VIEWER (modal) =====
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.view-file');
    if (!btn) return;
    e.preventDefault();
    const id = btn.dataset.id;
    const url = `/programchair/application/view/${id}?v=` + Date.now();
    document.getElementById('pdfFrame').src = url;
    new bootstrap.Modal(document.getElementById('viewPdfModal')).show();
  });
  document.getElementById('viewPdfModal').addEventListener('hidden.bs.modal', function(){
    document.getElementById('pdfFrame').src = '';
  });

  // ===== SINGLE CONFIRM→VERIFY =====
  let _verifyContext = { id: null, $row: null };

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.verify-student');
    if (!btn) return;

    const row = btn.closest('tr');
    const id = row.querySelector('.application-id')?.value || row.querySelector('.view-file')?.dataset.id;

    _verifyContext = { id, $row: row };
    new bootstrap.Modal(document.getElementById('confirmVerifyModal')).show();
  });

  document.getElementById('confirmVerifyYesBtn').addEventListener('click', function () {
    const btn = this;
    if (!_verifyContext.id) return;

    btn.disabled = true; btn.textContent = 'Verifying...';

    fetch("{{ route('programchair.application.update-status') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({ id: _verifyContext.id, status: 'Verified' })
    })
    .then(r => r.ok ? r.json() : Promise.reject(r))
    .then(() => {
      bootstrap.Modal.getInstance(document.getElementById('confirmVerifyModal')).hide();

      if (_verifyContext.$row) {
        _verifyContext.$row.querySelector('.status-cell').innerHTML = badge('Verified');
        const chk = _verifyContext.$row.querySelector('.row-check');
        if (chk) chk.checked = false;
        refreshBulkButton();
      }

      document.getElementById('verifySuccessTitle').textContent = 'Success!';
      document.getElementById('verifySuccessDesc').textContent = 'Student was successfully verified.';
      new bootstrap.Modal(document.getElementById('verifySuccessModal')).show();

      _verifyContext = { id:null, $row:null };
    })
    .catch(async (xhr) => {
      alert('Failed to update status.');
      console.error('POST update-status error:', xhr.status || '', await xhr.text?.() || xhr);
    })
    .finally(() => { btn.disabled = false; btn.textContent = 'Yes'; });
  });

  // ===== BULK VERIFY =====
  document.addEventListener('change', function(e){
    if (e.target.id === 'check-all') {
      const checked = e.target.checked;
      document.querySelectorAll('.row-check').forEach(cb => cb.checked = checked);
      refreshBulkButton();
    }
    if (e.target.classList?.contains('row-check')) {
      const all = document.querySelectorAll('.row-check').length;
      const sel = document.querySelectorAll('.row-check:checked').length;
      document.getElementById('check-all').checked = (sel > 0 && sel === all);
      refreshBulkButton();
    }
  });

  document.getElementById('verify-selected').addEventListener('click', function(){
    const ids = [];
    const rows = [];
    document.querySelectorAll('#student-table tbody tr').forEach(tr => {
      const cb = tr.querySelector('.row-check');
      if (cb && cb.checked) {
        const id = tr.querySelector('.application-id')?.value;
        if (id) { ids.push(id); rows.push(tr); }
      }
    });
    if (ids.length === 0) return;

    if (!confirm(`Verify ${ids.length} selected student(s)?`)) return;

    const btn = this;
    btn.disabled = true; btn.textContent = 'Verifying...';

    fetch("{{ route('programchair.application.bulk-verify') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({ application_ids: ids })
    })
    .then(r => r.ok ? r.json() : Promise.reject(r))
    .then(() => {
      rows.forEach(tr => {
        tr.querySelector('.status-cell').innerHTML = badge('Verified');
        const cb = tr.querySelector('.row-check');
        if (cb) cb.checked = false;
      });
      document.getElementById('check-all').checked = false;
      refreshBulkButton();

      document.getElementById('verifySuccessTitle').textContent = 'Verified!';
      document.getElementById('verifySuccessDesc').textContent = `${ids.length} student(s) were successfully verified.`;
      new bootstrap.Modal(document.getElementById('verifySuccessModal')).show();
    })
    .catch(async (xhr) => {
      const txt = await xhr.text?.() || '';
      alert('Failed to verify selected.' + (txt ? ` ${txt}` : ''));
    })
    .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Verify Selected'; });
  });

  // Back button
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
  });
</script>
@endsection
