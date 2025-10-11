@extends('registrar.registrarsidebar')

@section('content')
<div class="container py-4">
  <!-- 🔷 Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Students</h3>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('registrar.student') }}" class="btn btn-outline-light rounded-3">
        <i class="bi bi-arrow-left-circle me-1"></i> Back
      </a>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>SRCODE</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Contact</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            @if(isset($students) && count($students) > 0)
              @foreach($students as $index => $student)
                <tr>
                  <td>{{ $index + 1 }}</td>
                  <td>{{ $student->SRCODE }}</td>
                  <td>{{ $student->First_name }} {{ $student->Middle_name }} {{ $student->Last_name }}</td>
                  <td>{{ $student->Email }}</td>
                  <td>{{ $student->Contact ?? '-' }}</td>
                  <td>
                    <div class="d-flex justify-content-center gap-2">
                      <a href="#" class="btn btn-sm btn-outline-primary" title="Edit">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                      <button class="btn btn-sm btn-outline-danger" title="Delete"
                              onclick="showDeleteModal({{ $student->Student_id }})">
                        <i class="bi bi-trash3"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              @endforeach
            @else
              <tr>
                <td colspan="6" class="text-center">No students found.</td>
              </tr>
            @endif
          </tbody>
        </table>
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
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this student?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteStudent()">Yes</button>
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
          <button class="btn btn-primary px-4" data-bs-dismiss="modal" onclick="window.location.reload()">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Delete Logic -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  let studentToDeleteId = null;

  window.showDeleteModal = function(id) {
    studentToDeleteId = id;
    const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    modal.show();
  }

  window.submitDeleteStudent = function() {
    if (!studentToDeleteId) return;

    fetch(`/registrar/studentlist/delete/${studentToDeleteId}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Accept': 'application/json',
      },
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const rowCount = document.querySelectorAll("tbody tr").length;
        const modal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
        modal.hide();

        const successModal = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
        successModal.show();

        setTimeout(() => {
          if (rowCount <= 1) {
            window.location.href = "{{ route('registrar.student') }}";
          } else {
            location.reload();
          }
        }, 2000);
      } else {
        alert("Failed to delete student.");
      }
    })
    .catch(error => {
      console.error(error);
      alert("An error occurred.");
    });
  }
});
</script>
@endsection
