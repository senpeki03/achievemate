@extends('registrar.registrarsidebar')

@section('content')
<div class="container py-4">
  <!-- 🔷 Title Bar with College -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold text-white mb-0">{{ $collegeName }}</h4>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" title="Filter">
        <i class="bi bi-funnel-fill"></i>
      </button>
      <a href="{{ route('registrar.student') }}" class="btn btn-outline-primary px-4 rounded-3">
        <i class="bi bi-arrow-left-circle me-1"></i> Back
      </a>
    </div>
  </div>

  <!-- ℹ️ CSV Format Info -->
  <div class="alert alert-info d-flex align-items-center gap-2 mb-2 py-2" role="alert" style="font-size: 14px;">
    <i class="bi bi-info-circle-fill fs-5"></i>
    <div>
      CSV file must have column name <strong>SRCODE, FIRSTNAME, MIDDLENAME, LASTNAME, CONTACT</strong> and <strong>EMAIL</strong>. 
      It should be <code>,</code> or <code>;</code> separated file format.
    </div>
  </div>

  <!-- 🟦 Upload Section -->
  <div class="border border-2 border-dashed rounded bg-white px-3 py-3 position-relative shadow-sm">
    <div class="text-muted small mb-2">
      Upload CSV File For Student List of <strong class="text-uppercase">{{ $programName }}</strong>
    </div>

    <!-- 📤 Upload Form -->
    <form id="upload-form" action="{{ route('student.upload.csv') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="curriculum_id" value="{{ $curriculumId }}">
      <input type="hidden" name="year_level" value="{{ request('year_level') }}">
      
      <label for="csv_file" class="d-flex align-items-center justify-content-start gap-3 p-2 border border-1 rounded w-100 cursor-pointer" style="border-style: dashed; border-color: #ced4da;">
        <i class="bi bi-file-earmark-spreadsheet-fill fs-3 text-secondary"></i>
        <div class="text-start">
          <div id="fileLabelText" class="fw-bold text-primary" style="font-size: 15px;">
            Click to upload
          </div>
          <small class="text-muted">Comma separated .csv (max. 10MB)</small>
        </div>
        <input type="file" name="csv_file" id="csv_file" class="d-none" required onchange="updateFileName()">
      </label>

      <div class="d-flex justify-content-end mt-2">
        <button type="button" class="btn btn-primary px-3 py-1" data-bs-toggle="modal" data-bs-target="#confirmAddModalFinal">
          <i class="bi bi-upload me-1"></i> Upload
        </button>
      </div>
    </form>
  </div>

  <div class="mt-2 text-end">
    <a href="{{ route('student.template.download') }}" class="text-decoration-none text-primary fw-semibold">
      <i class="bi bi-download me-1"></i>Download Template
    </a>
  </div>
</div>

<!-- 🔶 Confirm Modal -->
<div class="modal fade" id="confirmAddModalFinal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to upload this student list?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" onclick="submitAddCurriculum()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Success Modal -->
@if(session('successAddModal'))
<div class="modal fade show" id="successAddModal" tabindex="-1" style="display: block;" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4" autoplay muted loop playsinline style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Upload Successful!</h5>
        <p class="mb-4 text-muted">Student records have been successfully added.</p>
        <div>
          <a href="{{ url()->current() }}" class="btn btn-primary px-4">OK</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

<!-- ⚠️ Duplicate Email Modal -->
@if(session('duplicateEmail'))
<div class="modal fade show" id="duplicateEmailModal" tabindex="-1" style="display: block;" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center border-0 shadow">
      <div class="mx-auto mb-3">
        <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 40px;"></i>
      </div>
      <h5 class="fw-bold text-danger mb-1">Duplicate Email Found!</h5>
      <p class="mb-0 text-muted">Some students were not uploaded because their email already exists in the system.</p>
    </div>
  </div>
</div>
@endif

<!-- ❌ Error Modal -->
@if(session('error'))
<div class="modal fade show" id="uploadErrorModal" tabindex="-1" style="display:block;" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center border-0 shadow">
      <div class="mx-auto mb-3">
        <i class="bi bi-x-circle-fill text-danger" style="font-size: 40px;"></i>
      </div>
      <h5 class="fw-bold text-danger mb-1">Upload Failed!</h5>
      <p class="mb-3 text-muted">{{ session('error') }}</p>
      <div class="d-flex justify-content-center">
        <a href="{{ url()->current() }}" class="btn btn-primary px-4">OK</a>
      </div>
    </div>
  </div>
</div>
@endif

<!-- 🧠 Script -->

<!-- ✅ Add jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+sJU5yExlq6GSYGSHk7tPXikynS1zKcO7RUGz1w="
        crossorigin="anonymous"></script>
        
<script>
  function submitAddCurriculum() {
    document.getElementById('upload-form').submit();
    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmAddModalFinal'));
    confirmModal.hide();
  }

  function updateFileName() {
    const input = document.getElementById('csv_file');
    const labelText = document.getElementById('fileLabelText');
    if (input.files.length > 0) {
        labelText.textContent = input.files[0].name;
    } else {
        labelText.textContent = 'Click to upload';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const successEl = document.getElementById('successAddModal');
    const duplicateEl = document.getElementById('duplicateEmailModal');

    if (successEl) {
      const modal = new bootstrap.Modal(successEl);
      modal.show();
      setTimeout(() => {
        window.location.href = "{{ url()->current() }}";
      }, 2000);
    }

    if (duplicateEl) {
      const modal = new bootstrap.Modal(duplicateEl);
      modal.show();
      setTimeout(() => {
        const bsModal = bootstrap.Modal.getInstance(duplicateEl);
        bsModal.hide();
      }, 2000);
    }
  });
</script>
@endsection
