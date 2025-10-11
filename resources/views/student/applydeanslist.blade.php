@extends('student.studentsidebar')

@section('content')
<div class="container py-5">
    <div class="card shadow rounded p-4">
        <form id="pdfUploadForm" method="POST" action="{{ route('pdf.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="text-start border border-4 border-primary rounded p-4 mb-4 d-flex align-items-center gap-3"
                 style="border-style: dashed; cursor: pointer;"
                 onclick="document.getElementById('pdfFile').click();">
                <i class='bx bx-file fs-1'></i>
                <strong id="fileLabel">Upload your .pdf file</strong>
                <input type="file" name="pdf" id="pdfFile" accept="application/pdf" class="d-none" required>
            </div>

            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-warning px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#consentModal">
                    IMPORT
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ✅ Consent Modal -->
<div class="modal fade" id="consentModal" tabindex="-1" aria-labelledby="consentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content p-4">
      <h5 class="fw-bold mb-3">INFORMED CONSENT DECLARATION</h5>
      <p class="mb-3">Directions: Kindly check the box to signify your consent in the following statements:</p>

      <form id="consentForm">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="consent1">
          <label class="form-check-label" for="consent1">
            I have read and understand the terms and conditions of applying in the {{ session('department', 'DHL') }}
          </label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="consent2">
          <label class="form-check-label" for="consent2">
            I grant permission for the forms and documents to be recorded and saved for the purpose of review by the {{ session('department') ?? '' }}
          </label>
        </div>
        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" id="consent3">
          <label class="form-check-label" for="consent3">
            I grant permission for the data generated from the documents and forms (online and offline) to be posted in the {{ session('department') ?? '' }} Facebook group including my name, program, photo, year level and section, rank, and rating/GWA
          </label>
        </div>

        <div class="text-end">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="submitWithConsent()">Agree and Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ✅ Waiting Modal -->
<div class="modal fade" id="waitingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <div class="spinner-border text-primary mb-3" role="status"></div>
      <h5 class="fw-bold">Processing your document...</h5>
      <p class="text-muted">Please wait while we validate your registration form and grades.</p>
    </div>
  </div>
</div>

<script>
const pdfInput = document.getElementById('pdfFile');
const fileLabel = document.getElementById('fileLabel');

pdfInput.addEventListener('change', () => {
    if (pdfInput.files.length > 0) {
        fileLabel.textContent = `Selected: ${pdfInput.files[0].name}`;
    }
});

function submitWithConsent() {
    const c1 = document.getElementById('consent1').checked;
    const c2 = document.getElementById('consent2').checked;
    const c3 = document.getElementById('consent3').checked;

    if (!(c1 && c2 && c3)) {
        alert("⚠️ Please check all consent boxes before uploading.");
        return;
    }

    const formData = new FormData(document.getElementById('pdfUploadForm'));

    // Show waiting modal
    const waitingModal = new bootstrap.Modal(document.getElementById('waitingModal'));
    waitingModal.show();

    fetch("{{ route('pdf.upload') }}", {
        method: "POST",
        body: formData,
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(res => res.json())
    .then(data => {
        waitingModal.hide();
        if (data.status === 'success') {
            document.body.innerHTML = data.html; // Directly replace with dlpreview contents
        } else {
            alert(data.message);
        }
    })
    .catch(err => {
        waitingModal.hide();
        alert("❌ Upload failed. Please try again.");
        console.error(err);
    });
}
</script>
@endsection
