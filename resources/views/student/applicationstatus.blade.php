@extends('student.studentsidebar', ['unreadCount' => $unreadCount])

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Application Status</h3>
  </div>

  <div class="table-responsive">
    <table class="table table-striped bg-white shadow-sm rounded-4 overflow-hidden align-middle text-center">
      <thead class="table-light">
        <tr>
          <th style="width:60px;">#</th>
          <th style="width:35%;">Type</th>
          <th style="width:12%;">GWA</th>
          <th style="width:12%;">Rank</th>
          <th>Status</th>
          <th style="width:100px;">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($applications as $i => $application)
          <tr>
            <td>{{ $i + 1 }}</td>
            <td class="text-truncate" style="max-width:340px;" title="{{ $application->File_name }}">
              {{ $application->File_name ?? 'Application.pdf' }}
            </td>
            <td>{{ is_numeric($application->GWA) ? number_format($application->GWA, 4) : '—' }}</td>
            <td>{{ $application->Rank ?? '—' }}</td>
            <td>
              @php
                $status = strtoupper($application->Status ?? '');
                $cls = match($status) {
                  'PENDING'  => 'bg-danger text-dark',
                  'VERIFIED' => 'bg-warning text-dark',
                  'APPROVED' => 'bg-success',
                  default    => 'bg-secondary'
                };
              @endphp
              <span class="badge {{ $cls }}">{{ $application->Status ?? 'Unknown' }}</span>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-danger"
                      onclick="confirmDelete({{ $application->Application_id }})">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-muted py-4">No application record found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256"
             style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this application?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteApplication()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Success Delete Modal --}}
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
          <button class="btn btn-primary px-4" data-bs-dismiss="modal"
                  onclick="location.reload()">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  let deleteId = null;

  function confirmDelete(id) {
    deleteId = id;
    new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
  }

  function submitDeleteApplication() {
    if (!deleteId) return;

    // Build the correct URL from the named route
    const url = "{{ route('student.application.destroy', ['id' => '__ID__']) }}".replace('__ID__', deleteId);

    fetch(url, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json'
      }
    })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        // Hide the confirm modal using the existing instance
        const confirmEl = document.getElementById('confirmDeleteModal');
        const confirmInst = bootstrap.Modal.getInstance(confirmEl);
        confirmInst && confirmInst.hide();

        // Show success modal
        new bootstrap.Modal(document.getElementById('deleteSuccessModal')).show();
      } else {
        alert('Delete failed.');
      }
    })
    .catch(() => alert('Delete failed.'));
  }
</script>

@endsection
