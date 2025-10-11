@extends('admin.adminlayout')

@section('content')
<div class="container py-5">
    <div class="card shadow rounded p-4">
        <form id="importForm" method="POST" action="{{ route('users.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="text-start border border-4 border-primary rounded p-4 mb-4 d-flex align-items-center gap-3"
                 style="border-style: dashed; cursor: pointer;"
                 onclick="document.getElementById('csvFile').click();">
                <i class='bx bx-file fs-1'></i>
                <strong id="fileLabel">Upload your .csv file</strong>
                <input type="file" name="csv" id="csvFile" accept=".csv" class="d-none" required>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('user.templates') }}" class="text-decoration-none">
                    <i class='bx bx-download'></i> Download CSV Template
                </a>
                <button type="button" class="btn btn-warning px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#confirmImportModal">
                    IMPORT
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmImportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <h5 class="fw-bold mb-3 text-dark">Are you sure you want to<br>import this file?</h5>
      <div class="d-flex justify-content-center gap-3">
        <button class="btn btn-warning px-4" data-bs-dismiss="modal">NO</button>
        <button class="btn btn-primary px-4" onclick="handleImport()">YES</button>
      </div>
    </div>
  </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-5 rounded-4">
      <h6 class="mb-3 text-dark">Sending Email... Please wait</h6>
      <div class="progress-container">
        <div class="progress-track">
          <div id="progressBar" class="progress-fill"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Duplicate Email Modal -->
<div class="modal fade" id="duplicateEmailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content text-center p-4">
      <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
      <h6 class="mb-0">This email is already taken!</h6>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successImportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content text-center p-4">
      <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
      <h6 class="mb-0">Successfully imported file</h6>
    </div>
  </div>
</div>

<style>
.progress-container {
  width: 100%;
  max-width: 300px;
  margin: 0 auto;
}

.progress-track {
  background-color: #e0e0e0;
  border: 3px solid #007bff;
  border-radius: 30px;
  overflow: hidden;
  height: 20px;
  position: relative;
}

.progress-fill {
  height: 100%;
  width: 0%;
  background-color: #007bff;
  border-radius: 30px;
  transition: width 0.3s ease-in-out;
}
</style>

<script>
const fileInput = document.getElementById('csvFile');
const fileLabel = document.getElementById('fileLabel');

fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
        fileLabel.textContent = `Selected: ${fileInput.files[0].name}`;
    }
});

function handleImport() {
    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmImportModal'));
    confirmModal.hide();

    const formData = new FormData(document.getElementById('importForm'));
    const progressBar = document.getElementById('progressBar');
    progressBar.style.width = '0%';

    let progress = 0;
    const maxTime = 3000; // simulate 3 seconds
    const intervalTime = 100;
    const totalTicks = maxTime / intervalTime;
    const increment = 98 / totalTicks;
    let progressInterval;

    fetch("{{ route('users.import') }}", {
        method: "POST",
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: formData
    })
    .then(response => {
        if (response.status === 409) {
            const duplicateModal = new bootstrap.Modal(document.getElementById('duplicateEmailModal'));
            duplicateModal.show();
            setTimeout(() => duplicateModal.hide(), 2000);
            return null;
        }

        // ✅ Only show loading modal after email is confirmed NOT taken
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        loadingModal.show();

        progressInterval = setInterval(() => {
            progress += increment;
            if (progress >= 98) {
                clearInterval(progressInterval);
                progress = 98;
            }
            progressBar.style.width = progress + '%';
        }, intervalTime);

        return response.json();
    })
    .then(data => {
    if (data && data.success) {
        // Get simulated email time from backend
        fetch('/email-time')
            .then(res => res.json())
            .then(emailInfo => {
                const seconds = emailInfo.seconds;
                const intervalTime = 100;
                const totalTicks = seconds * 1000 / intervalTime;
                const increment = 98 / totalTicks;
                let progress = 0;
                let interval = setInterval(() => {
                    progress += increment;
                    if (progress >= 98) {
                        clearInterval(interval);
                        progress = 98;
                    }
                    progressBar.style.width = `${progress}%`;
                }, intervalTime);
            });

        const checkInterval = setInterval(() => {
            fetch('/email-status')
                .then(res => res.json())
                .then(status => {
                    if (status.sent) {
                        clearInterval(checkInterval);
                        progressBar.style.width = '100%';

                        setTimeout(() => {
                            bootstrap.Modal.getInstance(document.getElementById('loadingModal')).hide();
                            const successModal = new bootstrap.Modal(document.getElementById('successImportModal'));
                            successModal.show();
                            fetch('/reset-email-status');
                            setTimeout(() => {
                                successModal.hide();
                                window.location.reload();
                            }, 2000);
                        }, 500);
                    }
                });
        }, 500);
    }
});

}
</script>

@endsection