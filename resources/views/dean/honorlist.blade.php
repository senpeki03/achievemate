@extends('dean.deansidebar')

@section('content')
<div class="container py-4">
  {{-- Header with back button --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
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
        <tbody class="align-middle">
          {{-- Injected by JS --}}
        </tbody>
      </table>
    </div>
  </div>

  {{-- Students --}}
  <div id="students-container" class="d-none">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h4 class="text-dark fw-bold m-0">Students</h4>
      <button id="approve-selected" class="btn btn-success btn-sm" disabled>
        <i class="bi bi-check2-circle me-1"></i> Approve Selected
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
        <tbody class="align-middle">
          {{-- Injected by JS --}}
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ====== MODALS ====== --}}
{{-- Confirm Approve --}}
<div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256"
             style="width:60px" class="mb-3" alt="">
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to approve this student?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" id="confirmApproveYesBtn">Yes</button>
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
            autoplay muted loop playsinline type="video/mp4"
            style="width:70px;height:70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Student was successfully approved.</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- PDF Viewer Modal --}}
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
  let studentCounter = 1;
  let _approveCtx = { id: null, $row: null };

  function badge(status) {
    const cls = { 'Pending':'bg-danger', 'Verified':'bg-warning text-dark', 'Approved':'bg-success' }[status] || 'bg-secondary';
    return `<span class="badge ${cls}">${status || '—'}</span>`;
  }
  function toNum(v){ const n=parseFloat(v); return Number.isFinite(n)?n:Infinity; }
  function fmtGwa(v){ const n=parseFloat(v); return Number.isFinite(n)?n.toFixed(2):'—'; }

  // -------- NAV: College -> Programs --------
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
        tb.append(`
          <tr class="clickable-program" data-id="${p.Program_id}">
            <td class="text-uppercase">${p.Program_name}</td>
          </tr>
        `);
      });
    });
  });

  // -------- NAV: Programs -> Students --------
  $(document).on('click', '.clickable-program', function () {
    const programId = $(this).data('id');

    $('#programs-container').addClass('d-none');
    $('#students-container').removeClass('d-none');

    $.getJSON('/dean/students-by-program/' + programId, function (data) {
      const tb = $('#student-table tbody').empty();
      studentCounter = 1;

      if (!data || data.length === 0) {
        tb.append(`
          <tr>
            <td colspan="8" class="text-center text-muted py-4">
              <div class="d-flex flex-column align-items-center">
                <i class="bi bi-shield-exclamation" style="font-size:24px;"></i>
                <div class="mt-2 fw-semibold">No Program Chairperson–verified applications yet.</div>
                <small>Please check back after your Program Chairperson finishes the review.</small>
              </div>
            </td>
          </tr>
        `);
        $('#check-all').prop('checked', false);
        $('#approve-selected').prop('disabled', true);
        return;
      }

      // sort by GWA ascending; tie-breaker fullname
      const sorted = data.map(s => ({...s, _gwaNum: toNum(s.gwa)}))
        .sort((a,b) => a._gwaNum !== b._gwaNum
            ? a._gwaNum - b._gwaNum
            : (a.fullname||'').toUpperCase().localeCompare((b.fullname||'').toUpperCase()));

      sorted.forEach(s => {
        tb.append(`
          <tr data-app="${s.application_id}">
            <td><input type="checkbox" class="row-check"></td>
            <td>${studentCounter++}</td>
            <td class="text-start">${(s.fullname || '').toUpperCase()}</td>
            <td>${s.year_level || ''}</td>
            <td>${fmtGwa(s.gwa)}</td>
            <td>${s.rank ?? '—'}</td>
            <td class="status-cell fw-bold">${badge(s.status || 'Verified')}</td>
            <td>
              <div class="d-flex justify-content-center align-items-center gap-2">
                <button class="btn btn-sm btn-outline-primary view-file" data-id="${s.application_id}" title="View PDF">
                  <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-success approve-one" title="Approve">
                  <i class="bi bi-check2-circle"></i>
                </button>
              </div>
            </td>
          </tr>
        `);
      });

      $('#check-all').prop('checked', false);
      refreshBulkButton();
    });
  });

  // -------- Open PDF in modal --------
  $(document).on('click', '.view-file', function (e) {
    e.preventDefault();
    const id = $(this).data('id');
    const url = `/dean/application/view/${id}`;
    const iframe = document.getElementById('pdfViewerFrame');
    iframe.src = url;
    const m = new bootstrap.Modal(document.getElementById('pdfViewerModal'));
    m.show();

    // optional: clear iframe when modal hides
    const modalEl = document.getElementById('pdfViewerModal');
    modalEl.addEventListener('hidden.bs.modal', function handler() {
      iframe.src = '';
      modalEl.removeEventListener('hidden.bs.modal', handler);
    });
  });

  // ===== APPROVE (single) with modals =====
  $(document).on('click', '.approve-one', function () {
    const $row = $(this).closest('tr');
    _approveCtx = { id: $row.data('app'), $row };
    new bootstrap.Modal(document.getElementById('confirmApproveModal')).show();
  });

  document.getElementById('confirmApproveYesBtn').addEventListener('click', function () {
    const btn = this;
    if (!_approveCtx.id) return;
    btn.disabled = true; btn.textContent = 'Approving...';

    $.ajax({
      url: "{{ route('dean.application.update-status') }}",
      method: 'POST',
      data: { _token: $('meta[name="csrf-token"]').attr('content'), id: _approveCtx.id, status: 'Approved' },
      complete: () => { btn.disabled = false; btn.textContent = 'Yes'; },
      success: () => {
        bootstrap.Modal.getInstance(document.getElementById('confirmApproveModal')).hide();
        if (_approveCtx.$row) _approveCtx.$row.find('.status-cell').html(badge('Approved'));
        new bootstrap.Modal(document.getElementById('approveSuccessModal')).show();
        _approveCtx = { id:null, $row:null };
      },
      error: () => alert('Failed to update status.')
    });
  });

  // -------- Bulk approve --------
  function refreshBulkButton() {
    $('#approve-selected').prop('disabled', $('.row-check:checked').length === 0);
  }

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

  $('#approve-selected').on('click', function () {
    const ids = [];
    const rows = [];
    $('#student-table tbody tr').each(function () {
      const $tr = $(this);
      if ($tr.find('.row-check').is(':checked')) {
        ids.push($tr.data('app'));
        rows.push($tr);
      }
    });
    if (ids.length === 0) return;

    const btn = $(this).prop('disabled', true).text('Approving...');

    $.ajax({
      url: "{{ route('dean.application.bulk-approve') }}",
      method: 'POST',
      data: { _token: $('meta[name="csrf-token"]').attr('content'), application_ids: ids },
      success: (res) => {
        rows.forEach($tr => $tr.find('.status-cell').html(badge('Approved')));
        $('.row-check').prop('checked', false);
        $('#check-all').prop('checked', false);
        refreshBulkButton();
        btn.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i> Approve Selected');
        alert(res && res.message ? res.message : 'Selected applications approved.');
      },
      error: () => {
        btn.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i> Approve Selected');
        alert('Failed to approve selected.');
      }
    });
  });

  // -------- Back navigation --------
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
  });
</script>
@endsection
