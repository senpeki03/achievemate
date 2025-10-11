@extends('admin.adminsidebar')

@section('content')
<div class="container py-4">

  <!-- 🔷 Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-white">Add College</h4>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" title="Filter">
        <i class="bi bi-funnel-fill"></i>
      </button>
      <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#confirmAddModal">
        <i class="bi bi-plus-lg me-1"></i> Create College
      </button>
    </div>
  </div>

  <!-- 📋 College Table -->
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Campus</th>
              <th>College</th>
              <th>Abbreviation</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($colleges as $index => $college)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $college->Campus_name }}</td>
                <td>{{ $college->College_name }}</td>
                <td>{{ $college->Abbreviation }}</td>
                <td>
                  <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal"
                    data-bs-target="#editCollegeModal"
                    onclick="editCollege({{ $college->College_id }}, '{{ $college->College_name }}', '{{ $college->Abbreviation }}', {{ $college->Campus_id }})">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete({{ $college->College_id }})">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center">No College Found</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Hidden Forms -->
<form id="createCollegeForm" action="{{ route('admin.college.store') }}" method="POST">
  @csrf
  <input type="hidden" name="name" id="hiddenCollegeName">
  <input type="hidden" name="campus_id" id="hiddenCampusId">
  <input type="hidden" name="abbreviation" id="hiddenCollegeAbbr">
</form>

<form id="deleteCollegeForm" method="POST" style="display: none;">
  @csrf
  @method('DELETE')
</form>

<!-- ✅ Add College Modal -->
<div class="modal fade" id="confirmAddModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header text-black rounded-top-4">
        <h5 class="modal-title fw-bold">Create College</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <!-- Abbreviation -->
        <div class="mb-3">
        <label class="form-label fw-semibold">Campus Name</label>
        <select id="inputCollegeCampus" name="campus_id" class="form-select" required>
            <option value="" disabled selected>Select a campus</option>
            @foreach($campuses as $campus)
            <option value="{{ $campus->Campus_id }}">{{ $campus->Campus_name }}</option>
            @endforeach
        </select>
        </div>

        <!-- Abbreviation -->
        <div class="mb-3">
        <label class="form-label fw-semibold">Abbreviation</label>
        <input type="text" id="inputCollegeAbbr" name="abbreviation" class="form-control" required>
        </div>

        <!-- College Name -->
        <div class="mb-3">
        <label class="form-label fw-semibold">College Name</label>
        <input type="text" id="inputCollegeName" name="name" class="form-control @error('name') is-invalid @enderror" required>
        <small id="duplicateError" class="text-danger mt-1" style="display: none;"></small>
        @error('name')
            <small class="text-danger">{{ $message }}</small>
        @enderror
        </div>

      </div>
      <div class="modal-footer px-4 pb-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="showFinalAddModal()">Save College</button>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Edit College Modal -->
<div class="modal fade" id="editCollegeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <form id="editCollegeForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-header bg-primary text-white rounded-top-4">
          <h5 class="modal-title fw-bold">Edit College</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <input type="hidden" id="editCollegeId">
          <div class="mb-3">
            <label class="form-label fw-semibold">College Name</label>
            <input type="text" name="name" id="editCollegeName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Abbreviation</label>
            <input type="text" name="abbreviation" id="editCollegeAbbr" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Campus</label>
            <select name="campus_id" id="editCollegeCampus" class="form-select" required>
              @foreach($campuses as $campus)
                <option value="{{ $campus->Campus_id }}">{{ $campus->Campus_name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="modal-footer px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update College</button>
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
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to add this college?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" onclick="submitAddCollege()">Yes</button>
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
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this college?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteCollege()">Yes</button>
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

<script>
  function showFinalAddModal() {
    const name = document.getElementById('inputCollegeName').value.trim().toLowerCase();
    const campusId = document.getElementById('inputCollegeCampus').value;
    const abbr = document.getElementById('inputCollegeAbbr').value.trim();
    const errorMessage = document.getElementById('duplicateError');

    if (!name || !campusId || !abbr) {
      alert('Please complete all fields.');
      return;
    }

    // ✅ FIX: check against College_name and Campus_id
    const existing = @json($colleges);
    const isDuplicate = existing.some(college =>
      college.College_name.toLowerCase() === name &&
      college.Campus_id == campusId
    );

    if (isDuplicate) {
      errorMessage.innerText = "This College is already added in this campus";
      errorMessage.style.display = "block";
      return; // ✅ STOP — don't show confirmation modal
    } else {
      errorMessage.style.display = "none";
    }

    // ✅ Proceed to show modal if no duplicate
    document.getElementById('hiddenCollegeName').value = name;
    document.getElementById('hiddenCampusId').value = campusId;
    document.getElementById('hiddenCollegeAbbr').value = abbr;

    new bootstrap.Modal(document.getElementById('confirmAddModalFinal')).show();
  }


  function submitAddCollege() {
    document.getElementById('createCollegeForm').submit();
  }

  let deleteCollegeId = null;
  function confirmDelete(id) {
    deleteCollegeId = id;
    new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
  }

  function submitDeleteCollege() {
    const form = document.getElementById('deleteCollegeForm');
    form.action = `/admin/college/delete/${deleteCollegeId}`;
    form.submit();
  }

  function editCollege(id, name, abbr, campusId) {
    const form = document.getElementById('editCollegeForm');
    form.action = `/admin/college/update/${id}`;
    document.getElementById('editCollegeId').value = id;
    document.getElementById('editCollegeName').value = name;
    document.getElementById('editCollegeAbbr').value = abbr;
    document.getElementById('editCollegeCampus').value = campusId;
  }

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
