@extends('admin.adminsidebar')

@section('content')
<div class="container py-4">
  <!-- Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Add Program</h3>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" title="Filter">
        <i class="bi bi-funnel-fill"></i>
      </button>
      <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#confirmAddModal">
        <i class="bi bi-plus-lg me-1"></i> Create Program
      </button>
    </div>
  </div>

  <!-- Program Table -->
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Campus</th>
              <th>College</th>
              <th>Program</th>
              <th>Abbreviation</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($programs as $index => $program)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $program->Campus_name }}</td>
                <td>{{ $program->College_Abbreviation }}</td>
                <td>{{ $program->Program_name }}</td>
                <td>{{ $program->Abbreviation }}</td>
                <td>
                  <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal"
                    data-bs-target="#editCollegeModal"
                    onclick="editProgram({{ $program->Program_id }}, '{{ $program->Program_name }}', '{{ $program->Abbreviation }}', {{ $program->Campus_id }}, {{ $program->College_id }})">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete({{ $program->Program_id }})">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center">No Program Found</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Add Program Modal -->
<div class="modal fade" id="confirmAddModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header text-black rounded-top-4">
        <h5 class="modal-title fw-bold">Create Program</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="mb-3">
          <label class="form-label fw-semibold">Campus</label>
          <select id="inputCollegeCampus" name="campus_id" class="form-select" required>
            <option value="" disabled selected>Select a campus</option>
            @foreach($campuses as $campus)
              <option value="{{ $campus->Campus_id }}">{{ $campus->Campus_name }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">College</label>
          <select id="inputCollegeId" name="college_id" class="form-select" required>
            <option value="" disabled selected>Select a college</option>
            @foreach($colleges as $college)
              <option value="{{ $college->College_id }}" data-campus="{{ $college->Campus_id }}">
                {{ $college->College_name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Abbreviation</label>
          <input type="text" id="inputCollegeAbbr" name="abbreviation" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Program Name</label>
          <input type="text" id="inputCollegeName" name="name" class="form-control @error('name') is-invalid @enderror" required>
          <small id="duplicateError" class="text-danger mt-1" style="display: none;"></small>
          @error('name')
            <small class="text-danger">{{ $message }}</small>
          @enderror
        </div>
      </div>
      <div class="modal-footer px-4 pb-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="showFinalAddModal()">Save Program</button>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Edit Program Modal -->
<div class="modal fade" id="editCollegeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <form id="editProgramForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-header bg-primary text-white rounded-top-4">
          <h5 class="modal-title fw-bold">Edit Program</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <input type="hidden" id="editProgramId">

          <div class="mb-3">
            <label class="form-label fw-semibold">Campus</label>
            <select name="campus_id" id="editProgramCampus" class="form-select" required>
              @foreach($campuses as $campus)
                <option value="{{ $campus->Campus_id }}">{{ $campus->Campus_name }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">College</label>
            <select name="college_id" id="editProgramCollege" class="form-select" required>
              @foreach($colleges as $college)
                <option value="{{ $college->College_id }}">{{ $college->College_name }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Program Name</label>
            <input type="text" name="name" id="editProgramName" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Abbreviation</label>
            <input type="text" name="abbreviation" id="editProgramAbbr" class="form-control" required>
          </div>
        </div>

        <div class="modal-footer px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Program</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ✅ Confirmation Modal (Final Step) -->
<div class="modal fade" id="confirmAddModalFinal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to add this Program?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" onclick="submitAddProgram()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

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
        <p class="mb-4 text-muted">Record has been successfully added</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this Program?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteProgram()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- ✅ Success Update Modal -->
<div class="modal fade" id="successUpdateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
                 autoplay muted loop playsinline type="video/mp4"
                 style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Record has been successfully updated</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Success Delete Modal -->
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

<!-- Hidden Create Form -->
<form id="createProgramForm" action="{{ route('admin.program.store') }}" method="POST">
  @csrf
  <input type="hidden" name="name" id="hiddenProgramName">
  <input type="hidden" name="campus_id" id="hiddenCampusId">
  <input type="hidden" name="college_id" id="hiddenCollegeId">
  <input type="hidden" name="abbreviation" id="hiddenProgramAbbr">
</form>

<form id="deleteProgramForm" method="POST" style="display: none;">
  @csrf
  @method('DELETE')
</form>

<script>
  function showFinalAddModal() {
    const name = document.getElementById('inputCollegeName').value.trim();
    const campusId = document.getElementById('inputCollegeCampus').value;
    const collegeId = document.getElementById('inputCollegeId').value;
    const abbr = document.getElementById('inputCollegeAbbr').value.trim();
    const errorMessage = document.getElementById('duplicateError');

    if (!name || !campusId || !collegeId || !abbr) {
      alert('Please complete all fields.');
      return;
    }

    const existing = @json($programs);
    const isDuplicate = existing.some(program =>
      program.Program_name === name &&
      program.Campus_id == campusId &&
      program.College_id == collegeId
    );

    if (isDuplicate) {
      errorMessage.innerText = "This program already exists in this campus and college.";
      errorMessage.style.display = "block";
      return;
    } else {
      errorMessage.style.display = "none";
    }

    document.getElementById('hiddenProgramName').value = name;
    document.getElementById('hiddenCampusId').value = campusId;
    document.getElementById('hiddenCollegeId').value = collegeId;
    document.getElementById('hiddenProgramAbbr').value = abbr;

    new bootstrap.Modal(document.getElementById('confirmAddModalFinal')).show();
  }

  function submitAddProgram() {
    document.getElementById('createProgramForm').submit();
  }

  let deleteProgramId = null;

  function confirmDelete(id) {
    deleteProgramId = id;
    new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
  }

  function submitDeleteProgram() {
    const form = document.getElementById('deleteProgramForm');
    form.action = `/admin/program/delete/${deleteProgramId}`;
    form.submit();
  }

  function editProgram(id, name, abbr, campusId, collegeId) {
    const form = document.getElementById('editProgramForm');
    form.action = `/admin/program/update/${id}`;
    document.getElementById('editProgramId').value = id;
    document.getElementById('editProgramName').value = name;
    document.getElementById('editProgramAbbr').value = abbr;
    document.getElementById('editProgramCampus').value = campusId;
    document.getElementById('editProgramCollege').value = collegeId;
  }

  // ✅ Filter College dropdown based on selected Campus
  document.addEventListener('DOMContentLoaded', function () {
    const campusSelect = document.getElementById('inputCollegeCampus');
    const collegeSelect = document.getElementById('inputCollegeId');

    campusSelect.addEventListener('change', function () {
      const selectedCampusId = this.value;

      Array.from(collegeSelect.options).forEach(option => {
        if (!option.value) return;
        option.style.display = option.dataset.campus === selectedCampusId ? 'block' : 'none';
      });

      collegeSelect.value = "";
    });
  });

  @if(session('added'))
    window.addEventListener('DOMContentLoaded', () => {
      new bootstrap.Modal(document.getElementById('successAddModal')).show();
      setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('successAddModal')).hide(), 3000);
    });
  @endif

  @if(session('updated'))
    window.addEventListener('DOMContentLoaded', () => {
      new bootstrap.Modal(document.getElementById('successUpdateModal')).show();
      setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('successUpdateModal')).hide(), 3000);
    });
  @endif

  @if(session('deleted'))
    window.addEventListener('DOMContentLoaded', () => {
      new bootstrap.Modal(document.getElementById('deleteSuccessModal')).show();
      setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('deleteSuccessModal')).hide(), 3000);
    });
  @endif
</script>

@endsection
