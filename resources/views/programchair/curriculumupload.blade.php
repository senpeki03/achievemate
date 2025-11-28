@extends('programchair.programchairsidebar')

@php
  // Safety defaults
  $curriculums = isset($curriculums) ? collect($curriculums) : collect();
  $campuses    = $campuses  ?? [];
  $colleges    = $colleges  ?? [];
  $programs    = $programs  ?? [];
  $majors      = $majors    ?? [];

  // Generate AY options (no DB)
  $y = (int)date('Y');
  $ayOptions = [];
  for ($i = -1; $i <= 4; $i++) {
    $start = $y + $i;
    $ayOptions[] = $start . '-' . ($start + 1);
  }
@endphp

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Upload Curriculum</h3>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary text-white" title="Filter">
        <i class="bi bi-funnel-fill text-white"></i>
      </button>
      <button class="btn btn-outline-primary px-4 text-white" data-bs-toggle="modal" data-bs-target="#addCurriculumModal">
        <i class="bi bi-plus-lg me-1 text-white"></i> Add Curriculum
      </button>
    </div>
  </div>

  @if(session('imported'))
    <div class="alert alert-success">✅ Curriculum successfully uploaded!</div>
  @elseif(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <form id="uploadForm" method="POST" action="{{ route('programchair.curriculum.upload') }}" enctype="multipart/form-data">
    @csrf

    {{-- Academic Year (generated + custom) --}}
    <div class="mb-3 position-relative dropdown">
      <label class="form-label text-white">Academic Year</label>
      <button type="button" class="form-select text-start d-flex align-items-center justify-content-between"
              id="aySelectBtn" data-bs-toggle="dropdown" aria-expanded="false">
        <span id="aySelectedText" class="text-muted">Select Academic Year</span>
        <i class="bi bi-chevron-down ms-2"></i>
      </button>

      <div class="dropdown-menu w-100 p-0 shadow" aria-labelledby="aySelectBtn" style="max-height: 340px; overflow: hidden;">
        <div id="ayList" class="list-group list-group-flush" style="max-height: 340px; overflow-y: auto;">
          @foreach ($ayOptions as $opt)
            <button type="button" class="list-group-item list-group-item-action d-flex align-items-center ay-item"
                    data-label="{{ $opt }}">
              <i class="bi bi-calendar3 me-2"></i>
              <span class="flex-grow-1">{{ $opt }}</span>
            </button>
          @endforeach

          <div class="list-group-item">
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="bi bi-pencil-square"></i></span>
              <input type="text" id="ayCustomInput" class="form-control" placeholder="Type custom AY (e.g., 2026-2027)">
              <button class="btn btn-primary" type="button" id="ayUseCustom">Use</button>
            </div>
            <small class="text-muted">Format: YYYY-YYYY</small>
          </div>
        </div>
      </div>

      <input type="hidden" name="academic_year" id="AcademicYear" required>
    </div>

    {{-- Upload File Card --}}
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body p-3">
        <div class="text-muted small mb-2">
          Upload PDF File For Curriculum
        </div>

        <label for="pdfFile" class="d-flex align-items-center justify-content-start gap-3 p-2 border border-1 rounded w-100 cursor-pointer" style="border-style: dashed; border-color: #ced4da;">
          <i class="bi bi-file-earmark-pdf-fill fs-3 text-danger"></i>
          <div class="text-start">
            <div id="fileLabel" class="fw-bold text-primary" style="font-size: 15px;">
              Click to upload
            </div>
            <small class="text-muted">PDF file (max. 10MB)</small>
          </div>
          <input type="file" name="pdf" id="pdfFile" class="d-none" accept="application/pdf" required>
        </label>

        <div class="d-flex justify-content-end mt-3">
          <button type="button" class="btn px-4 fw-bold" 
                  style="border: 2px solid #660000; color: #660000; background: transparent;"
                  onmouseover="this.style.backgroundColor='#660000'; this.style.color='white'" 
                  onmouseout="this.style.backgroundColor='transparent'; this.style.color='#660000'"
                  onclick="openImportConfirm()">
            IMPORT
          </button>
        </div>
      </div>
    </div>
  </form>

  {{-- List (no "No Curriculum added" card) --}}
  @if ($curriculums->isNotEmpty())
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
                      <a href="{{ route('programchair.curriculum.view', $curriculum->curriculum_id) }}" class="text-primary small text-decoration-none">
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

{{-- Confirm IMPORT --}}
<div class="modal fade" id="confirmImportModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to add this Curriculum?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button type="button" class="btn px-4 fw-bold" 
                  style="border: 2px solid #660000; color: #660000; background: transparent;"
                  onmouseover="this.style.backgroundColor='#660000'; this.style.color='white'" 
                  onmouseout="this.style.backgroundColor='transparent'; this.style.color='#660000'"
                  onclick="submitImportCurriculum()">
            Yes
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Success Modal --}}
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
          <button type="button" class="btn px-4 fw-bold" 
                  style="border: 2px solid #660000; color: #660000; background: transparent;"
                  onmouseover="this.style.backgroundColor='#660000'; this.style.color='white'" 
                  onmouseout="this.style.backgroundColor='transparent'; this.style.color='#660000'"
                  data-bs-dismiss="modal">
            OK
          </button>
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

<script>
  // AY picker
  document.querySelectorAll('.ay-item').forEach(item => {
    item.addEventListener('click', () => {
      const label = item.getAttribute('data-label');
      setAY(label);
      bootstrap.Dropdown.getOrCreateInstance(document.getElementById('aySelectBtn')).hide();
    });
  });

  document.getElementById('ayUseCustom')?.addEventListener('click', () => {
    const val = (document.getElementById('ayCustomInput').value || '').trim();
    if (!/^\d{4}-\d{4}$/.test(val)) {
      alert('Please use format YYYY-YYYY, e.g., 2026-2027.');
      return;
    }
    setAY(val);
    bootstrap.Dropdown.getOrCreateInstance(document.getElementById('aySelectBtn')).hide();
  });

  function setAY(label) {
    document.getElementById('AcademicYear').value = label;
    const span = document.getElementById('aySelectedText');
    span.innerText = label;
    span.classList.remove('text-muted');
  }

  // Upload flow
  function openImportConfirm() {
    const fileInput = document.getElementById('pdfFile');
    const ay = document.getElementById('AcademicYear').value;
    if (!ay || !fileInput.files.length) {
      alert('Please select Academic Year and choose a PDF file.');
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

  // File label
  document.getElementById('pdfFile')?.addEventListener('change', function () {
    document.getElementById('fileLabel').innerText = this.files[0]?.name || 'Click to upload';
  });

  // Delete flow
  let deleteCurriculumId = null;
  function confirmDeleteCurriculum(id) {
    deleteCurriculumId = id;
    new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
  }
  async function submitDeleteCurriculum() {
    if (!deleteCurriculumId) return;
    try {
      const res = await fetch(`{{ route('programchair.curriculum.destroy', ':id') }}`.replace(':id', deleteCurriculumId), {
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