@extends('admin.adminlayout')

@section('content')
<div class="container py-4">
  <div class="card shadow-sm rounded-4">
    <div class="card-body">
      <!-- Title -->
      <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.user.list') }}" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>
        </a>
        <h2 class="fw-bold mb-0">User List</h2>
      </div>

      <!-- Search & Filter -->
      <div class="d-flex justify-content-end align-items-center mb-3 flex-wrap">
        <div class="input-group rounded-pill shadow-sm overflow-hidden" style="max-width: 250px;">
          <span class="input-group-text bg-white border-0">
            <i class="fas fa-search text-secondary"></i>
          </span>
          <input type="text" class="form-control border-0" placeholder="Search..." id="searchInput">
        </div>
      </div>

      <!-- User Table -->
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle text-center">
          <thead class="custom-thead">
            <tr>
              <th></th>
              <th class="text-nowrap">Srcode</th>
              <th class="text-nowrap">First Name</th>
              <th class="text-nowrap">Middle Name</th>
              <th class="text-nowrap">Last Name</th>
              <th class="text-nowrap">Email</th>
              <th class="text-nowrap">User Type</th>
            </tr>
          </thead>
          <tbody>
            @forelse($users as $user)
            <tr>
              <td><input type="checkbox"></td>
              <td>{{ $user->Srcode }}</td>
              <td>{{ $user->firstname }}</td>
              <td>{{ $user->middlename }}</td>
              <td>{{ $user->lastname }}</td>
              <td>{{ $user->email }}</td>
              <td>{{ $user->usertype }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="text-center text-muted">No users found.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content text-center p-4">
      <div class="mb-3">
        <i class="fas fa-trash-alt fa-3x text-danger"></i>
        <h5 class="mt-3 fw-bold">Are you sure you want to delete this student?</h5>
      </div>
      <div class="d-flex justify-content-center gap-4">
        <button type="button" class="btn btn-primary px-4 fw-bold" data-bs-dismiss="modal">NO</button>
        <button type="button" class="btn btn-danger px-4 fw-bold" id="confirmYesBtn">YES</button>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="deleteSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content text-center p-4">
      <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
      <h6 class="mb-0">Student successfully deleted</h6>
    </div>
  </div>
</div>

<script>
let deleteId = null;

function confirmDelete(id) {
  deleteId = id;
  new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
}

document.getElementById('searchInput').addEventListener('input', function () {
  const query = this.value.toLowerCase();
  const rows = document.querySelectorAll('tbody tr');

  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  });
});

document.getElementById('confirmYesBtn').addEventListener('click', function () {
  fetch(`/students/${deleteId}`, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
  })
  .then(res => res.ok ? res.json() : Promise.reject(res))
  .then(() => {
    bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal')).hide();
    document.getElementById(`student-row-${deleteId}`)?.remove();
    new bootstrap.Modal(document.getElementById('deleteSuccessModal')).show();
  })
  .catch(() => alert('Failed to delete. Please try again.'));
});
</script>

@endsection
