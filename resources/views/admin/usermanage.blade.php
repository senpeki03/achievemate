@extends('admin.adminsidebar')

@section('content')
<div class="container py-4">
  <!-- 🔷 Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-white">User Management</h4>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" title="Filter">
        <i class="bi bi-funnel-fill"></i>
      </button>
      <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-plus-lg me-1"></i> Add User
      </button>
    </div>
  </div>

  <!-- 📋 User Table -->
  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Fullname</th>
              <th>Email</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($users as $index => $user)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $user->Title }} {{ $user->First_name }} {{ strtoupper(substr($user->Middle_name, 0, 1)) }}. {{ $user->Last_name }}</td>
                <td>{{ $user->Email }}</td>
                <td>
                  <button class="btn btn-outline-primary btn-sm me-1" title="Edit"
                    onclick='openEditModal(@json($user))'>
                    <i class="bi bi-pencil-square"></i>
                  </button>
                    <button class="btn btn-outline-info btn-sm me-1" title="Designation"
                            onclick="window.location.href='{{ route('admin.userdesignation', $user->User_id) }}'">
                    <i class="bi bi-person-square"></i>
                    </button>
                  <button class="btn btn-outline-danger btn-sm" title="Delete" onclick="showDeleteModal({{ $user->User_id }})">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted">No users yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog" style="margin-top: 40px; max-width: 1000px;">
    <div class="modal-content border-0 rounded-0 shadow">
      <form id="userForm">
        @csrf
        <div class="modal-header border-bottom-0">
          <h5 class="modal-title fw-bold">User Information</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body px-4 pt-3 pb-2">
          <div class="row mb-3">
            <div class="col-md-2">
              <label class="form-label">Title</label>
              <input type="text" name="Title" class="form-control border-0 border-bottom" placeholder="Title" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">First Name</label>
              <input type="text" name="First_name" class="form-control border-0 border-bottom" placeholder="First name" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Middle Name</label>
              <input type="text" name="Middle_name" class="form-control border-0 border-bottom" placeholder="Middle name">
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name</label>
              <input type="text" name="Last_name" class="form-control border-0 border-bottom" placeholder="Last name" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-12">
              <label class="form-label">Email</label>
              <input type="email" name="Email" class="form-control border-0 border-bottom" placeholder="Email" required>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="showConfirmModal()">Add User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ✅ Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog" style="margin-top: 40px; max-width: 1000px;">
    <div class="modal-content border-0 rounded-0 shadow">
      <form id="editUserForm">
        @csrf
        @method('PATCH')
        <input type="hidden" id="editUserId">
        <div class="modal-header border-bottom-0">
          <h5 class="modal-title fw-bold">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body px-4 pt-3 pb-2">
          <div class="row mb-3">
            <div class="col-md-2">
              <label class="form-label">Title</label>
              <input type="text" name="Title" id="editTitle" class="form-control border-0 border-bottom" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">First Name</label>
              <input type="text" name="First_name" id="editFirstName" class="form-control border-0 border-bottom" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Middle Name</label>
              <input type="text" name="Middle_name" id="editMiddleName" class="form-control border-0 border-bottom">
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name</label>
              <input type="text" name="Last_name" id="editLastName" class="form-control border-0 border-bottom" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-12">
              <label class="form-label">Email</label>
              <input type="email" name="Email" id="editEmail" class="form-control border-0 border-bottom" required>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" onclick="submitEditUser()">Update User</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ✅ Confirm Modal -->
<div class="modal fade" id="confirmAddModalFinal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to add this User?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" id="confirmYesBtn" onclick="submitAddUser()">Yes</button>
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
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this user?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteUser()">Yes</button>
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
        <p class="mb-4 text-muted">Record has been successfully added</p>
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
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4" autoplay muted loop playsinline type="video/mp4" style="width: 70px; height: 70px;"></video>
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
          <button class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

@if(session('duplicateEmail'))
    <div class="modal fade show" id="duplicateEmailModal" tabindex="-1" style="display: block;" aria-modal="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center border-0 shadow">
                <div class="mx-auto mb-3">
                    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 40px;"></i>
                </div>
                <h5 class="fw-bold text-danger mb-1">Duplicate Email Found!</h5>
                <p class="mb-0 text-muted">{{ session('duplicateEmail') }}</p>
            </div>
        </div>
    </div>
@endif


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
function openEditModal(user) {
  document.getElementById('editUserId').value = user.User_id;
  document.getElementById('editTitle').value = user.Title;
  document.getElementById('editFirstName').value = user.First_name;
  document.getElementById('editMiddleName').value = user.Middle_name;
  document.getElementById('editLastName').value = user.Last_name;
  document.getElementById('editEmail').value = user.Email;

  new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function submitEditUser() {
  const id = document.getElementById('editUserId').value;
  const formData = new FormData(document.getElementById('editUserForm'));

  fetch(`/admin/usermanage/update/${id}`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json'
    },
    body: formData
  })
  .then(response => {
    if (!response.ok) throw new Error('Failed to update');
    return response.json();
  })
  .then(data => {
    new bootstrap.Modal(document.getElementById('successUpdateModal')).show();
    setTimeout(() => location.reload(), 2000);
  })
  .catch(err => {
    alert("Error updating user: " + err.message);
  });
}

let confirmModal = null;
let deleteId = null;

function showConfirmModal() {
    console.log("Button clicked!");  // Debugging line
    confirmModal = new bootstrap.Modal(document.getElementById('confirmAddModalFinal'));
    confirmModal.show();
}

function submitAddUser() {
    const formData = new FormData(document.getElementById('userForm'));

    fetch("{{ route('admin.usermanage.store') }}", {
        method: "POST",
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        // Check if the email is duplicate
        if (data.status === 'email_duplicate') {
            // Show duplicate email modal if the response indicates a duplicate
            const modal = new bootstrap.Modal(document.getElementById('duplicateEmailModal'));
            modal.show();
        } else if (data.status === 'success') {
            // Show success modal if the user is added successfully
            confirmModal.hide();
            new bootstrap.Modal(document.getElementById('successAddModal')).show();
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(error => {
        alert("Something went wrong:\n\n" + error.message);
    });
}



function showDeleteModal(id) {
  deleteId = id;
  new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
}

function submitDeleteUser() {
  fetch(`/admin/usermanage/delete/${deleteId}`, {
    method: "DELETE",
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json'
    }
  })
  .then(response => {
    if (!response.ok) {
      return response.text().then(text => { throw new Error(text) });
    }
    return response.json();
  })
  .then(data => {
    const successModal = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
    successModal.show();
    setTimeout(() => location.reload(), 2000);
  })
  .catch(error => {
    console.error("Error deleting user:", error);
    alert("Error deleting user:\n\n" + error.message);
  });
}

// Show and automatically close the modal after 2 seconds
if (document.getElementById('duplicateEmailModal')) {
    const modal = new bootstrap.Modal(document.getElementById('duplicateEmailModal'));
    modal.show(); // Show the modal
    setTimeout(function () {
        modal.hide(); // Hide the modal after 2 seconds
    }, 2000);
}


</script>
@endsection
