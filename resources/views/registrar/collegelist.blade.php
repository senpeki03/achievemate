@extends('registrar.layout')

@section('content')
<div class="container py-3">
  <div class="card shadow-sm rounded-4">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div class="d-flex align-items-center gap-2">
          <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
          </a>
          <h4 class="fw-bold mb-0">
            {{ $department }} Students
            @isset($year) - {{ $year }} @endisset
            @isset($track) - {{ $track }} @endisset
          </h4>
        </div>
      </div>

      <div class="d-flex justify-content-end mb-3">
        <div class="input-group rounded-pill shadow-sm overflow-hidden" style="max-width: 250px;">
          <span class="input-group-text bg-white border-0"><i class="fas fa-search text-secondary"></i></span>
          <input type="text" class="form-control border-0" placeholder="Search..." id="searchInput">
        </div>
      </div>

      <div class="table-responsive" id="studentTable">
        <table class="table table-hover table-sm align-middle">
          <thead class="custom-thead">
            <tr>
              <th></th>
              <th>Srcode</th>
              <th>First Name</th>
              <th>Middle Name</th>
              <th>Last Name</th>
              <th>Year</th>
              <th>Program</th>
              <th>Track</th>
              <th>Email</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody id="studentTableBody">
            @forelse($students as $student)
              <tr id="student-row-{{ $student->userId }}">
                <td><input type="checkbox"></td>
                <td>{{ $student->Srcode }}</td>
                <td>{{ $student->firstname }}</td>
                <td>{{ $student->middlename }}</td>
                <td>{{ $student->lastname }}</td>
                <td>{{ $student->Year }}</td>
                <td>{{ $student->program }}</td>
                <td>{{ $student->track }}</td>
                <td>{{ $student->email }}</td>
                <td class="text-center">
                  <a href="#" class="btn btn-sm btn-warning edit-btn"
                    data-id="{{ $student->userId }}"
                    data-srcode="{{ $student->Srcode }}"
                    data-firstname="{{ $student->firstname }}"
                    data-middlename="{{ $student->middlename }}"
                    data-lastname="{{ $student->lastname }}"
                    data-year="{{ $student->Year }}"
                    data-department="{{ $student->department }}"
                    data-program="{{ $student->program }}"
                    data-track="{{ $student->track }}"
                    data-contact="{{ $student->contact ?? '' }}"
                    data-email="{{ $student->email }}">
                    <i class="fas fa-edit"></i>
                  </a>
                  <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="{{ $student->userId }}">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr><td colspan="10" class="text-center text-muted">No students found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Edit Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content p-4">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Student Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body row g-3">
        <input type="hidden" id="edit_userId">
        <div class="col-md-6">
          <label class="form-label">Srcode</label>
          <input type="text" class="form-control" id="edit_srcode">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" id="edit_email">
        </div>
        <div class="col-md-4">
          <label class="form-label">First Name</label>
          <input type="text" class="form-control" id="edit_firstname">
        </div>
        <div class="col-md-4">
          <label class="form-label">Middle Name</label>
          <input type="text" class="form-control" id="edit_middlename">
        </div>
        <div class="col-md-4">
          <label class="form-label">Last Name</label>
          <input type="text" class="form-control" id="edit_lastname">
        </div>
        <div class="col-md-4">
          <label class="form-label">Year</label>
          <input type="text" class="form-control" id="edit_year">
        </div>
        <div class="col-md-4">
          <label class="form-label">Program</label>
          <input type="text" class="form-control" id="edit_program">
        </div>
        <div class="col-md-4">
          <label class="form-label">Track</label>
          <input type="text" class="form-control" id="edit_track">
        </div>
        <div class="col-md-12">
          <label class="form-label">Contact</label>
          <input type="text" class="form-control" id="edit_contact">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary">Update</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content text-center p-4">
      <div class="mb-3">
        <h4 class="fw-bold">Confirmation</h4>
        <h6 class="fw-bold">Are you sure you want to delete?</h6>
      </div>
      <div class="d-flex justify-content-center gap-4">
        <button type="button" class="btn btn-warning px-4 fw-bold" data-bs-dismiss="modal">NO</button>
        <button type="button" class="btn btn-primary px-4 fw-bold" id="confirmYesBtn">YES</button>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Delete Success Modal -->
<div class="modal fade" id="deleteSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content text-center p-4">
      <div class="d-flex justify-content-center">
        <svg width="72" height="72" viewBox="0 0 72 72">
          <circle class="checkmark-circle" cx="36" cy="36" r="30" fill="none"/>
          <path class="checkmark-check" fill="none" d="M20 37 L32 48 L52 25"/>
        </svg>
      </div>
      <h6 class="mt-3 mb-0">Student Deleted</h6> 
    </div>
  </div>
</div>

<style>
.checkmark-circle {
  stroke: #28a745;
  stroke-width: 4;
  stroke-dasharray: 188.5;
  stroke-dashoffset: 188.5;
  animation: draw-circle 0.5s ease-out forwards;
}
.checkmark-check {
  stroke: #28a745;
  stroke-width: 4;
  stroke-linecap: round;
  stroke-dasharray: 50;
  stroke-dashoffset: 50;
  animation: draw-check 0.4s 0.5s ease-out forwards;
}
@keyframes draw-circle {
  to { stroke-dashoffset: 0; }
}
@keyframes draw-check {
  to { stroke-dashoffset: 0; }
}
</style>


<script>
let deleteId = null;

// Delete Confirmation Handler
document.querySelectorAll('.delete-btn').forEach(button => {
  button.addEventListener('click', function () {
    deleteId = this.dataset.id;
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    confirmModal.show();
  });
});

// Confirm YES → Delete Student → Show Success Modal
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

    // Show animated check modal
    const successModal = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
    successModal.show();

    // Hide after 2 seconds
    setTimeout(() => successModal.hide(), 2000);
  })
  .catch(() => alert('Failed to delete. Please try again.'));
});

// Live Search
document.getElementById('searchInput').addEventListener('input', function () {
  const query = this.value.toLowerCase();
  document.querySelectorAll('#studentTableBody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
  });
});

// Edit Modal Logic
document.querySelectorAll('.edit-btn').forEach(button => {
  button.addEventListener('click', function (e) {
    e.preventDefault();

    document.getElementById('edit_userId').value = this.dataset.id;
    document.getElementById('edit_srcode').value = this.dataset.srcode;
    document.getElementById('edit_firstname').value = this.dataset.firstname;
    document.getElementById('edit_middlename').value = this.dataset.middlename;
    document.getElementById('edit_lastname').value = this.dataset.lastname;
    document.getElementById('edit_year').value = this.dataset.year;
    document.getElementById('edit_program').value = this.dataset.program;
    document.getElementById('edit_track').value = this.dataset.track;
    document.getElementById('edit_email').value = this.dataset.email;
    document.getElementById('edit_contact').value = this.dataset.contact;

    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
  });
});
</script>

@endsection
