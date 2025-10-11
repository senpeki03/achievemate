@extends('admin.adminsidebar')

@section('content')
<div class="container py-4">
  <!-- 🔷 Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-white">Add Campus</h4>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" title="Filter">
        <i class="bi bi-funnel-fill"></i>
      </button>
      <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#confirmAddModal">
        <i class="bi bi-plus-lg me-1"></i> Create Campus
      </button>
    </div>
  </div>

  <!-- 📋 Campus Table -->
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Campus Name</th>
              <th>Address</th>
              <th>Date Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($campuses as $index => $campus)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $campus->Campus_name }}</td>
                <td>{{ $campus->Location }}</td>
                <td>{{ $campus->Created_at }}</td>
                <td>
                  <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal"
                    data-bs-target="#editCampusModal"
                    onclick="editCampus({{ $campus->Campus_id }}, '{{ $campus->Campus_name }}', '{{ $campus->Location }}')">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete({{ $campus->Campus_id }})">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center">No Campus Found</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Hidden Forms -->
<form id="createCampusForm" action="{{ route('admin.campus.store') }}" method="POST">
  @csrf
  <input type="hidden" name="name" id="hiddenCampusName">
  <input type="hidden" name="location" id="hiddenCampusLocation">
</form>

<form id="deleteCampusForm" method="POST" style="display: none;">
  @csrf
  @method('DELETE')
</form>

<!-- 🔵 Confirm Add Modal -->
<div class="modal fade" id="confirmAddModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header bg-primary text-white rounded-top-4">
        <h5 class="modal-title fw-bold">Create Campus</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="mb-3">
          <label class="form-label fw-semibold">Campus Name</label>
          <input type="text" id="inputCampusName" class="form-control" placeholder="Enter campus name" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Address</label>
          <input type="text" id="inputCampusLocation" class="form-control" placeholder="Enter campus location" required>
        </div>
      </div>
      <div class="modal-footer px-4 pb-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="confirmAddCampus()">Save Campus</button>
      </div>
    </div>
  </div>
</div>

<!-- ✏️ Edit Campus Modal -->
<div class="modal fade" id="editCampusModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <form id="editCampusForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-header bg-primary text-white rounded-top-4">
          <h5 class="modal-title fw-bold">Edit Campus</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <input type="hidden" id="editCampusId">
          <div class="mb-3">
            <label class="form-label fw-semibold">Campus Name</label>
            <input type="text" name="name" id="editCampusName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Location</label>
            <input type="text" name="location" id="editCampusLocation" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Campus</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ✅ Add Confirmation Modal -->
<div class="modal fade" id="confirmAddModalFinal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body d-flex flex-column justify-content-center align-items-center text-center"
           style="height: 300px;">
           
        <!-- 🔔 Exclamation Icon -->
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256"
             alt="Exclamation mark Icon"
             width="60"
             class="mb-4">

        <h5 class="fw-bold text-dark mb-4">Are you sure you want to add this campus?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" onclick="submitAddCampus()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- ✅ Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body d-flex flex-column justify-content-center align-items-center text-center" style="height: 300px;">
        
        <!-- ⚠️ Exclamation Icon -->
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256"
             alt="Exclamation mark Icon"
             width="60"
             class="mb-4">
        
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this campus?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteCampus()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- ✅ Success Add Modal -->
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
  let deleteCampusId = null;

  function confirmAddCampus() {
    const name = document.getElementById('inputCampusName').value.trim();
    const location = document.getElementById('inputCampusLocation').value.trim();

    if (!name || !location) {
      alert("Please complete all fields.");
      return;
    }

    document.getElementById('hiddenCampusName').value = name;
    document.getElementById('hiddenCampusLocation').value = location;

    const modal = new bootstrap.Modal(document.getElementById('confirmAddModalFinal'));
    modal.show();
  }

  function submitAddCampus() {
    document.getElementById('createCampusForm').submit();
  }

  function confirmDelete(id) {
    deleteCampusId = id;
    const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    modal.show();
  }

  function submitDeleteCampus() {
    const form = document.getElementById('deleteCampusForm');
    form.action = `/admin/campus/delete/${deleteCampusId}`;
    form.submit();
  }

  function editCampus(id, name, location) {
    const form = document.getElementById('editCampusForm');
    form.action = `/admin/campus/update/${id}`;
    document.getElementById('editCampusId').value = id;
    document.getElementById('editCampusName').value = name;
    document.getElementById('editCampusLocation').value = location;
  }

  @if(session('added'))
    window.addEventListener('DOMContentLoaded', () => {
      const modal = new bootstrap.Modal(document.getElementById('successAddModal'));
      modal.show();
      setTimeout(() => modal.hide(), 3000);
    });
  @endif

  @if(session('updated'))
    window.addEventListener('DOMContentLoaded', () => {
      const modal = new bootstrap.Modal(document.getElementById('successUpdateModal'));
      modal.show();
      setTimeout(() => modal.hide(), 3000);
    });
  @endif

  @if(session('deleted'))
    window.addEventListener('DOMContentLoaded', () => {
      const modal = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
      modal.show();
      setTimeout(() => modal.hide(), 3000);
    });
  @endif
</script>
@endsection
