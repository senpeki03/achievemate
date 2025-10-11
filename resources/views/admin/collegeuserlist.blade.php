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
        <h2 class="fw-bold mb-0">Student List</h2>
      </div>

      <!-- Search & Filter Controls -->
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div></div>
        <div class="d-flex align-items-center gap-2">
          <div class="input-group rounded-pill shadow-sm overflow-hidden" style="max-width: 250px;">
            <span class="input-group-text bg-white border-0">
              <i class="fas fa-search text-secondary"></i>
            </span>
            <input type="text" class="form-control border-0" placeholder="Search..." id="searchInput">
          </div>

          <div class="dropdown">
            <button class="btn btn-outline-secondary rounded-pill dropdown-toggle" type="button" id="departmentFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-filter me-1"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="departmentFilterBtn">
              <li><a class="dropdown-item" href="#" onclick="filterDepartment('All')">All</a></li>
              <li><a class="dropdown-item" href="#" onclick="filterDepartment('CAS')">CAS</a></li>
              <li><a class="dropdown-item" href="#" onclick="filterDepartment('CICS')">CICS</a></li>
              <li><a class="dropdown-item" href="#" onclick="filterDepartment('CHS')">CHS</a></li>
              <li><a class="dropdown-item" href="#" onclick="filterDepartment('CCJE')">CCJE</a></li>
              <li><a class="dropdown-item" href="#" onclick="filterDepartment('CABEIHM')">CABEIHM</a></li>
              <li><a class="dropdown-item" href="#" onclick="filterDepartment('CTE')">CTE</a></li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Only Student Tab (No User Tab) -->
      <!-- Table -->
      <div class="tab-content" id="userTabContent">
        <div class="tab-pane fade show active" id="studentList" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-hover table-sm align-middle text-center">
              <thead class="custom-thead">
                <tr>
                  <th></th>
                  <th class="text-nowrap">Srcode</th>
                  <th class="text-nowrap">First Name</th>
                  <th class="text-nowrap">Middle Name</th>
                  <th class="text-nowrap">Last Name</th>
                  <th class="text-nowrap">YEAR</th>
                  <th class="text-nowrap">Department</th>
                  <th class="text-nowrap">Program</th>
                  <th class="text-nowrap">Track</th> <!-- ✅ Added -->
                  <th class="text-nowrap">Email</th>
                </tr>
              </thead>
              <tbody id="studentTableBody">
                @forelse($students as $student)
                <tr data-department="{{ $student->department }}" id="student-row-{{ $student->userId ?? $student->id }}">
                  <td><input type="checkbox"></td>
                  <td>{{ $student->Srcode }}</td>
                  <td>{{ $student->firstname }}</td>
                  <td>{{ $student->middlename }}</td>
                  <td>{{ $student->lastname }}</td>
                  <td>{{ $student->Year }}</td>
                  <td>{{ $student->department }}</td>
                  <td>{{ $student->program }}</td>
                  <td>{{ $student->track ?? '-' }}</td> <!-- ✅ Display Track -->
                  <td>{{ $student->email }}</td>
                </tr>
                @empty
                <tr>
                  <td colspan="10" class="text-center text-muted">No students found.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
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
  const rows = document.querySelectorAll('#studentTableBody tr');
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

function filterDepartment(dept) {
  const rows = document.querySelectorAll('#studentTableBody tr');
  rows.forEach(row => {
    const rowDept = row.getAttribute('data-department');
    row.style.display = (dept === 'All' || rowDept === dept) ? '' : 'none';
  });
}
</script>

@endsection
