@extends('admin.adminsidebar')

@section('content')
<div class="container py-4">
  <!-- 🔷 Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Designation</h3>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" title="Filter">
        <i class="bi bi-funnel-fill"></i>
      </button>
      <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addDesignationModal">
        <i class="bi bi-plus-lg me-1"></i> Create Designation
      </button>
    </div>
  </div>

  <!-- 📋 Designation Table -->
  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Designation</th>
              <th>Privilege</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($designations as $index => $designation)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $designation->Designation_name }}</td>
                <td>{{ $designation->Access }}</td>
                <td>
                  <button class="btn btn-outline-primary btn-sm" onclick="showEditModal({{ json_encode($designation) }})">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                  <button class="btn btn-outline-danger btn-sm" onclick="showDeleteModal({{ $designation->Designation_id }})">
                    <i class="bi bi-trash3-fill"></i>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Add/Edit Modal -->
<div class="modal fade" id="addDesignationModal" tabindex="-1">
  <div class="modal-dialog" style="margin-top: 40px; max-width: 800px;">
    <div class="modal-content border-0 shadow">
      <form id="designationForm">
        @csrf
        <input type="hidden" name="Designation_id" id="Designation_id">
        <div class="modal-header border-bottom-0">
          <h5 class="modal-title fw-bold" id="modalTitle">Add Designation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body px-4 pt-3 pb-2">
          <div class="row mb-3">
            <div class="col-md-12 mb-3">
              <label class="form-label">Designation</label>
              <input type="text" name="Designation_name" id="Designation_name" class="form-control border-0 border-bottom" placeholder="Enter Designation" required>
            </div>
            <div class="col-md-12">
              <label class="form-label">Access</label>
              <select name="Access" id="Access" class="form-select border-0 border-bottom" required>
                <option value="" disabled selected>Select Access Level</option>
                <option value="University">University</option>
                <option value="Campus">Campus</option>
                <option value="College">College</option>
                <option value="Program">Program</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="saveBtn" onclick="showConfirmModal()">Add Designation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ✅ Edit Designation Modal -->
<div class="modal fade" id="editDesignationModal" tabindex="-1">
  <div class="modal-dialog" style="margin-top: 40px; max-width: 800px;">
    <div class="modal-content border-0 shadow">
      <form id="editDesignationForm">
        @csrf
        @method('PATCH')
        <input type="hidden" name="Designation_id" id="editDesignationId">

        <div class="modal-header border-bottom-0">
          <h5 class="modal-title fw-bold">Edit Designation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body px-4 pt-3 pb-2">
          <div class="row mb-3">
            <div class="col-md-12 mb-3">
              <label class="form-label">Designation</label>
              <input type="text" name="Designation_name" id="editDesignationName" class="form-control border-0 border-bottom" required>
            </div>
            <div class="col-md-12">
              <label class="form-label">Access</label>
              <select name="Access" id="editAccess" class="form-select border-0 border-bottom" required>
                <option value="" disabled>Select Access Level</option>
                <option value="University">University</option>
                <option value="Campus">Campus</option>
                <option value="College">College</option>
                <option value="Program">Program</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer border-0 px-4 pb-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" onclick="submitEditDesignation()">Update Designation</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ✅ Confirm Add Modal -->
<div class="modal fade" id="confirmAddModalFinal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to save this record?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" id="confirmYesBtn" onclick="submitDesignation()">Yes</button>
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
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4" autoplay muted loop playsinline style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Record has been successfully saved</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal" onclick="location.reload()">OK</button>
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
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this record?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" id="confirmDeleteBtn">Yes</button>
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
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4" autoplay muted loop playsinline type="video/mp4" style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Record has been successfully deleted</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal" onclick="location.reload()">OK</button>
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
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4" autoplay muted loop playsinline type="video/mp4" style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Record has been successfully updated</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal" onclick="location.reload()">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  let deleteDesignationId = null;

  function showConfirmModal() {
    const name = document.getElementById('Designation_name').value;
    const access = document.getElementById('Access').value;
    if (name.trim() && access.trim()) {
      const confirmModal = new bootstrap.Modal(document.getElementById('confirmAddModalFinal'));
      confirmModal.show();
    } else {
      alert("Please fill in all fields.");
    }
  }

  function submitDesignation() {
    const id = document.getElementById('Designation_id').value;
    const form = document.getElementById('designationForm');
    const formData = new FormData(form);

    const url = id ? `/designation/update/${id}` : "{{ route('designation.store') }}";
    const method = id ? 'POST' : 'POST';

    fetch(url, {
      method: method,
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmAddModalFinal'));
      confirmModal.hide();
      document.getElementById('addDesignationModal').classList.remove('show');
      const successModal = new bootstrap.Modal(document.getElementById(id ? 'successUpdateModal' : 'successAddModal'));
      successModal.show();
    })
    .catch(err => console.error(err));
  }

    function showEditModal(designation) {
     document.getElementById('editDesignationId').value = designation.Designation_id;
     document.getElementById('editDesignationName').value = designation.Designation_name;
     document.getElementById('editAccess').value = designation.Access;

     const modal = new bootstrap.Modal(document.getElementById('editDesignationModal'));
     modal.show();
    }

    function showDeleteModal(id) {
        deleteDesignationId = id;
        const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        modal.show();
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    if (!deleteDesignationId) return;

    fetch(`/admin/designation/delete/${deleteDesignationId}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json'
      }
    })
    .then(response => {
      if (response.ok) {
        const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
        confirmModal.hide();

        const successModal = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
        successModal.show();

        // Auto-hide after 2 seconds
        setTimeout(() => {
          successModal.hide();
          location.reload();
        }, 2000);
      } else {
        alert("Failed to delete.");
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert("Error deleting record.");
    });
  });
</script>
@endsection
