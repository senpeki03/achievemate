@extends('programchair.programchairsidebar')

@php
  // Safety defaults so the view never crashes
  $curriculums     = isset($curriculums) ? collect($curriculums) : collect();
  $curriculum_ays  = isset($curriculum_ays) ? collect($curriculum_ays) : collect();
  $campuses        = $campuses  ?? [];
  $colleges        = $colleges  ?? [];
  $programs        = $programs  ?? [];
  $majors          = $majors    ?? [];
@endphp

@section('content')
<div class="container py-4">
  {{-- Title bar --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Upload Curriculum</h3>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary text-white" title="Filter">
        <i class="bi bi-funnel-fill tetx-white"></i>
      </button>
      <button class="btn btn-outline-primary px-4 text-white" data-bs-toggle="modal" data-bs-target="#addCurriculumModal">
        <i class="bi bi-plus-lg me-1 text-white"></i> Add Curriculum
      </button>
    </div>
  </div>

  {{-- Flash messages --}}
  @if(session('imported'))
    <div class="alert alert-success">✅ Curriculum successfully uploaded!</div>
  @elseif(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- =======================
       UPLOAD FORM (no nesting)
  ======================== --}}
  <form id="uploadForm" method="POST" action="{{ route('curriculum.upload') }}" enctype="multipart/form-data">
    @csrf

    {{-- Curriculum AY picker (custom dropdown with delete icons on LEFT) --}}
    <div class="mb-3 position-relative dropdown">
      <label class="form-label text-white">Academic Year, Campus, College, Program</label>

      {{-- Visible trigger styled like a select --}}
      <button type="button"
              class="form-select text-start d-flex align-items-center justify-content-between"
              id="aySelectBtn" data-bs-toggle="dropdown" aria-expanded="false">
        <span id="aySelectedText" class="text-muted">Select Curriculum AY</span>
        <i class="bi bi-chevron-down ms-2"></i>
      </button>

      {{-- Dropdown menu --}}
      <div class="dropdown-menu w-100 p-0 shadow" aria-labelledby="aySelectBtn" style="max-height: 340px; overflow: hidden;">
        <div class="p-2 border-bottom bg-light">
          <input type="text" id="aySearch" class="form-control form-control-sm" placeholder="Search…">
        </div>

        <div id="ayList" class="list-group list-group-flush" style="max-height: 280px; overflow-y: auto;">
          @foreach ($curriculum_ays as $ay)
            @php
              $label = trim(($ay->Academic_year ?? '').' | '.($ay->campus->Campus_name ?? '').' - '.($ay->program->Program_name ?? ''));
            @endphp
            <button type="button"
                    class="list-group-item list-group-item-action d-flex align-items-center ay-item"
                    data-id="{{ $ay->CurriculumAY_id }}"
                    data-label="{{ $label }}">
              {{-- delete icon on the LEFT --}}
              <i class="bi bi-trash text-danger me-2 ay-delete" title="Delete this AY"
                 data-id="{{ $ay->CurriculumAY_id }}"></i>
              <span class="flex-grow-1 text-truncate">{{ $label }}</span>
            </button>
          @endforeach

          @if($curriculum_ays->isEmpty())
            <div class="p-3 text-center text-muted">No Curriculum AY yet.</div>
          @endif
        </div>
      </div>

      {{-- Hidden required field posted with the upload form --}}
      <input type="hidden" name="curriculum_ay_id" id="CurriculumAY_id" required>
    </div>

    {{-- Upload File --}}
    <div class="text-start border border-4 border-primary rounded p-4 mb-4 d-flex align-items-center gap-3"
         style="border-style: dashed; cursor: pointer;"
         onclick="document.getElementById('pdfFile').click();">
      <i class='bx bx-file fs-1'></i>
      <strong id="fileLabel">Upload your .pdf file</strong>
      <input type="file" name="pdf" id="pdfFile" accept="application/pdf" class="d-none" required>
    </div>

    <div class="d-flex justify-content-end">
      <button type="button" class="btn btn-warning px-4 fw-bold" onclick="openImportConfirm()">IMPORT</button>
    </div>
  </form>

  {{-- =======================
       CURRICULUM LIST
  ======================== --}}
  @if ($curriculums->isEmpty())
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-body py-5">
        <div class="d-flex flex-column justify-content-center align-items-center text-center" style="min-height: 38vh;">
          <img src="{{ asset('img/Curriculum.png') }}" alt="No curriculum" style="width: 100px; height: 100px;" class="mb-4">
          <h5 class="fw-bold text-dark mb-2">No Curriculum added</h5>
          <p class="text-muted mb-4">Start adding curriculum</p>
          <div class="d-flex gap-3">
            <button class="btn btn-outline-primary px-4" data-bs-toggle="modal" data-bs-target="#addCurriculumModal">
              <i class="bi bi-file-earmark-arrow-up me-1"></i> Add Curriculum
            </button>
          </div>
        </div>
      </div>
    </div>
  @else
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-body">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Curriculum</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($curriculums as $curriculum)
              @php
                $filenameParts = explode('_', $curriculum->Curriculum_name, 2);
                $originalName  = $filenameParts[1] ?? $curriculum->Curriculum_name;
              @endphp
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <img src="{{ asset('img/PDF_file_icon.png') }}" alt="PDF" width="40" class="me-3">
                    <div>
                      <h6 class="fw-bold mb-1">{{ $originalName }}</h6>
                      <a href="{{ route('curriculum.view', $curriculum->curriculum_id) }}" class="text-primary small text-decoration-none">
                        <i class="bi bi-eye me-1"></i> View Details
                      </a>
                    </div>
                  </div>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-danger" onclick="confirmDeleteCurriculum({{ $curriculum->curriculum_id }})">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>

{{-- =======================
     MODALS (siblings, not nested)
======================== --}}

{{-- Confirm IMPORT (upload) --}}
<div class="modal fade" id="confirmImportModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to add this Curriculum?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" onclick="submitImportCurriculum()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Success Modal (reused for both flows) --}}
<div class="modal fade" id="successAddModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
                 autoplay muted loop playsinline type="video/mp4"
                 style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted" id="successMessage">Operation completed.</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Add Curriculum (Create AY) --}}
<div class="modal fade" id="addCurriculumModal" tabindex="-1">
  <div class="modal-dialog" style="max-width: 800px;">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Curriculum</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      {{-- separate form (NOT nested) --}}
      <form id="curriculumForm">
        @csrf
        <div class="modal-body">
          {{-- Campus --}}
          <div class="mb-3">
            <label class="form-label">Campus Name</label>
            <select class="form-select" name="campus_id" id="campusSelect" required>
              <option value="" disabled selected>Select Campus</option>
              @foreach($campuses as $campus)
                <option value="{{ $campus->Campus_id }}">{{ $campus->Campus_name }}</option>
              @endforeach
            </select>
          </div>

          {{-- College --}}
          <div class="mb-3">
            <label class="form-label">College Name</label>
            <select class="form-select" name="college_id" id="collegeSelect" required disabled>
              <option value="" disabled selected>Select College</option>
            </select>
          </div>

          {{-- Program --}}
          <div class="mb-3">
            <label class="form-label">Program Name</label>
            <select class="form-select" name="program_id" id="programSelect" required disabled>
              <option value="" disabled selected>Select Program</option>
            </select>
          </div>

          {{-- Major --}}
          <div class="mb-3">
            <label class="form-label">Major</label>
            <select class="form-select" name="major_id" id="majorSelect" required disabled>
              <option value="" disabled selected>Select Major</option>
            </select>
          </div>

          {{-- Academic Year --}}
          <div class="mb-3">
            <label class="form-label">Academic Year</label>
            <input type="text" class="form-control" name="academic_year" placeholder="e.g. 2024-2025" required>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary px-4" type="button" onclick="openCreateAyConfirm()">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Confirm Create AY --}}
<div class="modal fade" id="confirmCreateAyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Create this Curriculum AY?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" onclick="submitCreateCurriculumAy()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Delete Confirmation (Curriculum file) --}}
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this curriculum?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteCurriculum()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Delete Success --}}
<div class="modal fade" id="deleteSuccessModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
                 autoplay muted loop playsinline type="video/mp4"
                 style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Record has been successfully deleted</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Delete Confirmation (Curriculum AY) --}}
<div class="modal fade" id="confirmDeleteAyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this Curriculum AY?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteAy()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Delete Success (Curriculum AY) --}}
<div class="modal fade" id="deleteAySuccessModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
                 autoplay muted loop playsinline type="video/mp4"
                 style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Curriculum AY has been successfully deleted</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>


{{-- =======================
     SCRIPTS
======================== --}}
<script>
  // ---------- Custom AY dropdown: delete via modal (single handler) ----------
  let deleteAyId = null;

  document.querySelectorAll('.ay-delete').forEach(icon => {
    icon.addEventListener('click', (e) => {
      e.stopPropagation(); // don't select the AY
      deleteAyId = icon.getAttribute('data-id');
      new bootstrap.Modal(document.getElementById('confirmDeleteAyModal')).show();
    });
  });

  async function submitDeleteAy() {
    if (!deleteAyId) return;

    try {
      const res = await fetch(`{{ route('curriculumay.destroy', ':id') }}`.replace(':id', deleteAyId), {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const data = await res.json();
      bootstrap.Modal.getInstance(document.getElementById('confirmDeleteAyModal')).hide();

      if (!res.ok || !data.success) {
        throw new Error(data.message || 'Delete failed');
      }

      // If the deleted AY was selected, clear the selection UI + hidden input
      if (document.getElementById('CurriculumAY_id').value == deleteAyId) {
        document.getElementById('CurriculumAY_id').value = '';
        const span = document.getElementById('aySelectedText');
        span.innerText = 'Select Curriculum AY';
        span.classList.add('text-muted');
      }

      // Remove the row from the dropdown list
      const row = document.querySelector(`.ay-item .ay-delete[data-id="${deleteAyId}"]`)?.closest('.ay-item');
      row?.parentNode?.removeChild(row);

      // Success modal
      const ok = new bootstrap.Modal(document.getElementById('deleteAySuccessModal'));
      ok.show();

    } catch (err) {
      alert('Delete failed: ' + err.message);
      console.error(err);
    } finally {
      deleteAyId = null;
    }
  }

  // ---------- Dependent dropdowns for "Add Curriculum" (Create AY) ----------
  const colleges = @json($colleges);
  const programs = @json($programs);
  const majors   = @json($majors);

  const campusSelect  = document.getElementById("campusSelect");
  const collegeSelect = document.getElementById("collegeSelect");
  const programSelect = document.getElementById("programSelect");
  const majorSelect   = document.getElementById("majorSelect");

  function fillDropdown(select, items, valueKey, textKey) {
    select.innerHTML = `<option value="" disabled selected>Select</option>`;
    items.forEach(item => {
      const opt = document.createElement('option');
      opt.value = item[valueKey];
      opt.textContent = item[textKey];
      select.appendChild(opt);
    });
    select.disabled = items.length === 0;
  }
  function reset(select) {
    select.innerHTML = `<option value="" disabled selected>Select</option>`;
    select.disabled = true;
  }

  if (campusSelect) {
    campusSelect.addEventListener("change", function () {
      const campusId = parseInt(this.value);
      const filteredColleges = colleges.filter(c => parseInt(c.Campus_id) === campusId);
      fillDropdown(collegeSelect, filteredColleges, 'College_id', 'College_name');
      reset(programSelect);
      reset(majorSelect);
    });
    collegeSelect?.addEventListener("change", function () {
      const campusId = parseInt(campusSelect.value);
      const collegeId = parseInt(this.value);
      const filteredPrograms = programs.filter(p =>
        parseInt(p.Campus_id) === campusId && parseInt(p.College_id) === collegeId
      );
      fillDropdown(programSelect, filteredPrograms, 'Program_id', 'Program_name');
      reset(majorSelect);
    });
    programSelect?.addEventListener("change", function () {
      const campusId = parseInt(campusSelect.value);
      const collegeId = parseInt(collegeSelect.value);
      const programId = parseInt(this.value);
      const filteredMajors = majors.filter(m =>
        parseInt(m.Campus_id) === campusId &&
        parseInt(m.College_id) === collegeId &&
        parseInt(m.Program_id) === programId
      );
      fillDropdown(majorSelect, filteredMajors, 'Major_id', 'Major_name');
    });
  }

  // ---------- Custom AY dropdown: select + search ----------
  // select ay
  document.querySelectorAll('.ay-item').forEach(item => {
    item.addEventListener('click', (e) => {
      if (e.target && e.target.classList.contains('ay-delete')) return; // ignore delete click
      const id = item.getAttribute('data-id');
      const label = item.getAttribute('data-label');
      document.getElementById('CurriculumAY_id').value = id;
      const span = document.getElementById('aySelectedText');
      span.innerText = label;
      span.classList.remove('text-muted');
      bootstrap.Dropdown.getOrCreateInstance(document.getElementById('aySelectBtn')).hide();
    });
  });

  // search filter
  document.getElementById('aySearch')?.addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#ayList .ay-item').forEach(btn => {
      const label = (btn.getAttribute('data-label') || '').toLowerCase();
      btn.style.display = label.includes(q) ? '' : 'none';
    });
  });

  // ---------- Upload flow ----------
  function openImportConfirm() {
    const fileInput = document.getElementById('pdfFile');
    const ayHidden  = document.getElementById('CurriculumAY_id');
    if (!fileInput.files.length || !ayHidden.value) {
      alert('Please select Curriculum AY and choose a PDF file.');
      return;
    }
    new bootstrap.Modal(document.getElementById('confirmImportModal')).show();
  }
  window.openImportConfirm = openImportConfirm;

  async function submitImportCurriculum() {
    const form = document.getElementById('uploadForm');
    const formData = new FormData(form);
    const url = form.action;
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });
      const ct = res.headers.get('content-type') || '';
      if (!ct.includes('application/json')) {
        const text = await res.text();
        throw new Error(`Non-JSON (${res.status}): ${text.slice(0,120)}`);
      }
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.message || 'Upload failed');

      bootstrap.Modal.getInstance(document.getElementById('confirmImportModal')).hide();
      document.getElementById('successMessage').innerText = 'Curriculum has been successfully added';
      const ok = new bootstrap.Modal(document.getElementById('successAddModal'));
      ok.show();
      setTimeout(() => { ok.hide(); location.reload(); }, 1500);
    } catch (err) {
      alert('Upload failed: ' + err.message);
      console.error('❌ Upload Error:', err);
    }
  }

  // show selected file name
  document.getElementById('pdfFile')?.addEventListener('change', function () {
    document.getElementById('fileLabel').innerText = this.files[0]?.name || 'Upload your .pdf file';
  });

  // ---------- Create Curriculum AY flow ----------
  function openCreateAyConfirm() {
    new bootstrap.Modal(document.getElementById('confirmCreateAyModal')).show();
  }
  async function submitCreateCurriculumAy() {
    const form = document.getElementById('curriculumForm');
    const fd   = new FormData(form);
    try {
      const res = await fetch("{{ route('curriculum.insert') }}", {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
      });
      const ct = res.headers.get('content-type') || '';
      if (!ct.includes('application/json')) {
        const text = await res.text();
        throw new Error(`Non-JSON (${res.status}): ${text.slice(0,120)}`);
      }
      const data = await res.json();
      if (!res.ok || !data.success) {
        if (data.errors) {
          const first = Object.values(data.errors)[0]?.[0] ?? 'Validation failed';
          throw new Error(first);
        }
        throw new Error(data.message || 'Insert failed');
      }

      bootstrap.Modal.getInstance(document.getElementById('confirmCreateAyModal')).hide();
      bootstrap.Modal.getInstance(document.getElementById('addCurriculumModal'))?.hide();

      document.getElementById('successMessage').innerText = 'Curriculum AY created.';
      const ok = new bootstrap.Modal(document.getElementById('successAddModal'));
      ok.show();
      setTimeout(() => { ok.hide(); location.reload(); }, 1200);
    } catch (err) {
      alert('Insert failed: ' + err.message);
      console.error('❌ Insert Error:', err);
    }
  }

  // ---------- Delete flow (Curriculum file) ----------
  let deleteCurriculumId = null;
  function confirmDeleteCurriculum(id) {
    deleteCurriculumId = id;
    new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
  }
  async function submitDeleteCurriculum() {
    if (!deleteCurriculumId) return;
    try {
      const res = await fetch(`{{ route('curriculum.destroy', ':id') }}`.replace(':id', deleteCurriculumId), {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      const data = await res.json();
      bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal')).hide();

      if (data.success) {
        const ok = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
        ok.show();
        setTimeout(() => { ok.hide(); location.reload(); }, 1200);
      } else {
        alert(data.message || 'Delete failed.');
      }
    } catch (err) {
      alert("Error: " + err.message);
      console.error(err);
    }
  }
</script>
@endsection
