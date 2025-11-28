@extends('student.studentsidebar')

@section('content')

@php
    $isApplicationClosed = $isApplicationClosed ?? false;
@endphp


<link rel="stylesheet" href="{{ asset('css/application.css') }}">

<style>
  /* highlight any row where OCR grade ≠ QR grade (suspected tamper) */
  .tampered-row { background:#fff3f3!important; }
  .tampered-row td { border-top-color:#f3b7b7!important; }

  /* small badge showing official QR grade next to the uploaded grade */
  .qr-grade-badge{
    display:inline-block; margin-left:.35rem; padding:.1rem .4rem;
    font-size:.75rem; border:1px solid #b91c1c; color:#b91c1c;
    border-radius:.4rem; background:#fff; white-space:nowrap;
  }

  .cog-table, .cog-summary { width:100%; border-collapse:collapse; }
  .cog-table th, .cog-table td, .cog-summary td { border:1px solid #444; padding:.5rem .6rem; }
  .cog-summary td.label { font-weight:600; }
  .cog-summary td.wide { min-width:260px; }
  .nothing-row td { text-align:center; font-style:italic; color:#666; }
  .val-ok { color: #198754; }
  .val-fail { color: #dc3545; }
  .val-muted { color: #6c757d; }
  .spinner { 
      display: inline-block; 
      width: 1rem; 
      height: 1rem; 
      border: 2px solid currentColor;
      border-right-color: transparent;
      border-radius: 50%;
      animation: spinner .75s linear infinite;
      margin-right: 0.5rem;
  }

  @keyframes spinner {
      to { transform: rotate(360deg); }
  }
</style>


<div class="container py-4" id="applicationContainer">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Application</h3>
  </div>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="route-generate-primary" content="{{ route('student.pdf.generate') }}">
    <meta name="route-generate-fallback" content="{{ route('student.pdf.generate.json') }}">
    <meta name="route-application-submit" content="{{ route('student.application.submit') }}">
    <meta name="route-application-status" content="{{ route('student.application.status') }}">
    <meta name="route-generate-pdf-json" content="{{ route('student.pdf.generate.json') }}">
    <meta name="route-save-cor-output" content="{{ route('student.save-cor-output') }}">
    <meta name="route-save-cog-output" content="{{ route('student.save-cog-output') }}">
    <meta name="route-save-cog-debug" content="{{ route('student.cog.debug') }}">
    <meta name="route-cor-output-file" content="{{ route('student.cor.output.txt') }}">
    <meta name="route-cog-output-file" content="{{ route('student.cog.output.txt') }}">
    <meta name="route-check-cor-cog-codes" content="{{ route('student.checkCorCogCodes') }}">
    <meta name="route-cog-output-file"     content="{{ route('student.cog.output') }}">
    <meta name="route-parse-qr-output-file" content="{{ route('student.cog.qrGradesOnly') }}">
    <meta name="route-parse-ocr-output-file" content="{{ route('student.cog.ocrGradesOnly') }}">
    <meta name="route-cog-ocr-output-file" content="{{ route('student.cog.ocr-output') }}">
    <meta name="route-cog-upload" content="{{ route('student.cog.upload') }}">
    <meta name="route-cor-upload" content="{{ route('student.cor.upload') }}">
    <meta name="route-qr-resolve" content="{{ route('student.qr.resolve') }}">
    <meta name="route-curriculum" content="{{ route('student.curriculum.subjects') }}">
    <meta name="route-cog-academic-info" content="{{ route('student.cog.academic-info') }}">
    <meta name="route-validate-application-period" content="{{ route('student.validate.application-period') }}">


  <form id="applicationForm" method="POST" enctype="multipart/form-data" action="javascript:void(0)">
    @csrf

    {{-- ===== STEPPER ===== --}}
    <div class="am-stepper-wrap mb-5">
      <div class="am-stepper-line">
        <div class="am-stepper-line-fill" id="am-stepper-line-fill"></div>
      </div>

      @php
        $steps = ['Guidelines','Upload COR','Upload Grades','Validation','Consent','Review & Confirm','Generate Application'];
      @endphp
      <div class="am-steps" id="am-steps">
        @foreach ($steps as $index => $label)
          <div class="am-step" data-idx="{{ $index + 1 }}">
            <div class="am-step-dot">
              <span class="am-step-num">{{ $index + 1 }}</span>
              <svg class="am-step-check" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20 6L9 17l-5-5" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="am-step-label">{{ $label }}</div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Card --}}
    <div class="d-flex justify-content-center">
      <div class="card shadow rounded-4 w-100" style="max-width: 1200px;">
        <div class="card-body px-4 py-5" id="step-content">
          <h5 class="fw-bold mb-4" id="card-title">
            <i class="bi bi-pin-angle-fill text-danger me-2"></i>Guidelines
          </h5>

          {{-- Step 1: Guidelines --}}
          <div class="step-section" id="step-1">
            <ol class="text-muted fs-6" style="line-height: 1.9;">
              <li>Apply at the end of the semester.</li>
              <li>Be enrolled in the required academic load prescribed by the program.</li>
              <li>Have no grade lower than 2.50 in any course.</li>
              <li>Have no failed, dropped or withdrawn grade.</li>
              <li>Have not committed any major offense during the semester of application.</li>
              <li>Do not include NSTP in the computation of GWA.</li>
              <li>Not be enrolled in OJT in the previous semester.</li>
              <li>
                Have obtained an average rating as follows:
                <ul class="ms-4 mt-2">
                  <li><strong>Tech Savant:</strong> GWA 1.0000 – 1.2500</li>
                  <li><strong>Tech Virtuoso:</strong> GWA 1.2501 – 1.5000</li>
                  <li><strong>Tech Prodigy:</strong> GWA 1.5001 – 1.7500</li>
                </ul>
              </li>
            </ol>
          </div>

          {{-- Step 2: Upload COR (PDF only) --}}
          <div class="text-start step-section" id="step-2" style="display: none;">
            <h4 class="fw-bold mb-2">Upload Your Certificate of Registration (COR)</h4>
            <ul class="small text-muted mb-3">
              <li>Accepted format: <strong>PDF only</strong></li>
              <li>Make sure the file is clear, complete, and official</li>
              <li>Example filename: <code>Lastname_Firstname_COR.pdf</code></li>
            </ul>
            <div class="text-center">
              <input type="file" name="cor" id="file-cor" accept="application/pdf"
                     class="form-control w-50 mx-auto" required
                     onchange="uploadCorPdf()" />
              <div class="mt-3" id="preview-cor"></div>
              <small id="cor-status" class="text-muted d-block mt-2"></small>
            </div>
          </div>

          {{-- Step 3: Upload Grades (Image or PDF) --}}
          <div class="text-start step-section" id="step-3" style="display: none;">
            <h4 class="fw-bold mb-2">Upload Your Certificate of Grades (COG)</h4>
            <ul class="small text-muted mb-3">
              <li>Accepted formats: <strong>PDF</strong></li>
              <li>Ensure the document is clear, complete, and official</li>
              <li>Example filename: <code>Lastname_Firstname_COG.pdf</code></li>
            </ul>
            <div class="text-center">
              <input
                type="file"
                name="cog"
                id="file-grade"
                accept="application/pdf,.pdf"
                class="form-control w-50 mx-auto"
                required
                onchange="handleCogUpload(this)"
              />
              <div class="mt-3" id="preview-grade"></div>
              <small id="cog-status" class="text-muted d-block mt-2"></small>
            </div>
          </div>

          {{-- Step 4: Validation --}}
          <div class="text-start step-section" id="step-4" style="display:none;">
              <p class="mb-2 text-muted">Checks performed before proceeding:</p>

              {{-- NEW: Application Period Validation --}}
              <div class="val-item mb-2">
                  <div class="val-left">1. Application Period Match</div>
                  <div class="val-right" id="v-period">
                      <span class="spinner"></span><span class="val-muted">Checking academic period…</span>
                  </div>
              </div>

              <div class="val-item mb-2">
                  <div class="val-left">2. Document Authenticity</div>
                  <div class="val-right" id="v-tamper">
                      <span class="spinner"></span><span class="val-muted">Validating…</span>
                  </div>
              </div>

              <div class="val-item mb-2">
                  <div class="val-left">3. Curriculum Match</div>
                  <div class="val-right" id="v-irregular">
                      <span class="spinner"></span><span class="val-muted">Checking curriculum…</span>
                  </div>
              </div>

              <div class="val-item">
                  <div class="val-left">4. Grade Eligibility</div>
                  <div class="val-right" id="v-grades">
                      <span class="spinner"></span><span class="val-muted">Scanning grades…</span>
                  </div>
              </div>

              <small class="text-muted d-block mt-3">You can only proceed if all validations are passed.</small>
          </div>

          {{-- Step 5: Consent --}}
          <div class="text-start step-section" id="step-5" style="display: none;">
            <h4 class="fw-bold mb-4">Consent</h4>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="consent1" checked>
              <label class="form-check-label" for="consent1">I have read and understand the terms and conditions.</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="consent2" checked>
              <label class="form-check-label" for="consent2">I grant permission for the forms and documents to be recorded and saved for review.</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="consent3" checked>
              <label class="form-check-label" for="consent3">I grant permission for the data generated to be posted if needed.</label>
            </div>
          </div>

          {{-- Step 6: Review & Confirm --}}
          <div class="text-start step-section" id="step-6" style="display: none;">
              <p class="mb-3 text-muted">Review your details and extracted grades before final submission.</p>

              <div class="row g-3 mb-3">
                  <div class="col-md-6"><label class="form-label">Fullname</label><input type="text" id="fullname" class="form-control" readonly></div>
                  <div class="col-md-6"><label class="form-label">SRCODE</label><input type="text" id="srcode" class="form-control" readonly></div>
                  <div class="col-md-6"><label class="form-label">College</label><input type="text" id="college" class="form-control" readonly></div>
                  <div class="col-md-6"><label class="form-label">Academic Year</label><input type="text" id="academic_year" class="form-control" readonly></div>
                  <div class="col-md-6"><label class="form-label">Program</label><input type="text" id="program" class="form-control" readonly></div>
                  <div class="col-md-6"><label class="form-label">Semester</label><input type="text" id="semester" class="form-control" readonly></div>
                  <div class="col-md-6"><label class="form-label">Year Level</label><input type="text" id="year_level" class="form-control" readonly></div>
              </div>

              <div class="table-responsive cog-wrap">
                  <table class="cog-table" id="extracted-grade-table">
                      <thead>
                          <tr>
                              <th class="text-center" style="width:50px">#</th>
                              <th style="width:130px">Course Code</th>
                              <th>Course Title</th>
                              <th class="text-center" style="width:70px">Units</th>
                              <th class="text-center" style="width:80px">Grade</th>
                              <th class="text-center" style="width:140px">Section</th>
                              <th style="width:260px">Instructor</th>
                          </tr>
                      </thead>
                      <tbody id="grade-table-body">
                          <tr><td colspan="7" class="text-center text-muted">Loading grade data...</td></tr>
                      </tbody>
                  </table>
                  <table class="cog-summary" id="cog-summary-table">
                      <tbody>
                          <tr>
                              <td class="label">Total no of Course</td>
                              <td class="value" id="sum-courses">—</td>
                              <td class="label">Total no of Units</td>
                              <td class="value" id="sum-units">—</td>
                              <td class="label wide">General Weighted Average (GWA)</td>
                              <td class="value" id="sum-gwa">—</td>
                          </tr>
                      </tbody>
                  </table>
              </div>

              <div class="d-flex justify-content-between align-items-center mt-3">
                  <div><span id="save-feedback" class="text-success" style="display:none;">✅ Saved successfully!</span></div>
                  <small id="paths-feedback" class="text-muted"></small>
              </div>
          </div>

          <!-- Step 7: Generate Application -->
          <div class="text-start step-section" id="step-7" style="display: none;">
            <div class="mb-3 d-flex align-items-center gap-2">
              <button id="pdf-generate-btn" type="button" class="btn btn-primary" onclick="generatePdf()">Generate / Refresh Application PDF</button>
              <span id="pdf-status" class="text-muted"></span>
            </div>
            <iframe id="pdf-frame" src="" width="100%" height="620px" class="rounded border shadow-sm"></iframe>
            <div class="mt-2 d-flex justify-content-between">
              <a id="pdf-open-link" href="#" target="_blank" class="small" style="display:none;">Open PDF in new tab</a>
              <span class="small text-muted">Click <strong>Finish</strong> to submit your application after generating your PDF.</span>
            </div>
          </div>


        </div>
      </div>
    </div>

    {{-- Navigation --}}
    <div class="d-flex justify-content-between mt-4 px-2" style="max-width: 1200px; margin: 0 auto;">
      <button type="button" onclick="goBack()" class="btn btn-outline-secondary px-4">Back</button>
      <button type="button" id="next-btn" onclick="goNext()" class="btn btn-primary px-4">Next</button>
    </div>
  </form>
</div>

@if ($isApplicationClosed)
  <div class="modal fade" id="applicationClosedModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-body">Applications are now closed.</div></div></div>
  </div>
@endif

{{-- ===================== DIALOGS ===================== --}}
{{-- Qualified --}}
<div class="modal fade" id="qualifiedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 560px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title text-success fw-bold">Congratulations!</h5></div>
      <div class="modal-body pt-0"><p>Your application meets all the Dean's List requirements.<br>You may now proceed to the next step.</p></div>
      <div class="modal-footer border-0"><button type="button" id="qualifiedContinueBtn" class="btn btn-primary">Continue</button></div>
    </div>
  </div>
</div>

{{-- Not Qualified --}}
<div class="modal fade" id="notQualifiedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title text-danger fw-bold">Not Qualified for Dean's List</h5></div>
      <div class="modal-body pt-0">
        <p>Unfortunately, your application does not meet the Dean's List requirements.</p>
        <p>Detected grade(s): <strong>2.75 / 3.00 / INC / DROP</strong>.</p>
        <p class="small text-muted mb-0">Note: Only students with grades 2.50 and above, with no INC or DROP, are eligible.</p>
      </div>
      <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button></div>
    </div>
  </div>
</div>

{{-- Application Period Mismatch Modal --}}
<div class="modal fade" id="applicationPeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 640px;">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title text-warning fw-bold">Application Period Mismatch</h5>
            </div>
            <div class="modal-body pt-0">
                <p id="applicationPeriodMessage">Your academic period does not match any active Dean's List posting.</p>
                <p class="small text-muted mb-0">Please check the announcement board for current application periods.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

{{-- Authenticity (Tamper) FAIL --}}
<div class="modal fade" id="tamperFailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 640px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title text-danger fw-bold">Document Authenticity Check Failed</h5></div>
      <div class="modal-body pt-0">
        <p>Your uploaded Certificate could not be verified.</p>
        <p class="mb-1">Reason:</p>
        <p id="tamperReason" class="text-danger small mb-2"></p>
        <p class="mb-1">Possible causes:</p>
        <ul class="mb-2">
          <li>QR code is invalid or unreadable</li>
          <li>The document appears altered or tampered</li>
          <li>The uploaded file is not an official copy</li>
        </ul>
        <p class="small text-muted mb-0">Note: Please re-upload a valid official document from the Student Portal.</p>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-between">
        <button type="button" id="tamperReuploadBtn" class="btn btn-primary">Re-upload Document</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

{{-- Curriculum Mismatch --}}
<div class="modal fade" id="curriculumMismatchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 640px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title text-warning fw-bold">Curriculum Mismatch Detected</h5></div>
      <div class="modal-body pt-0">
        <p>Your uploaded COR/COG do not fully match the standard curriculum sequence.</p>
        <p>This indicates that you are classified as an irregular student, which does not meet the eligibility requirements for the Dean's List.</p>
        <p class="small text-muted mb-0">Note: Only students with a regular academic load and curriculum match are qualified.</p>
      </div>
      <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button></div>
    </div>
  </div>
</div>

<!-- Final Submit Confirmation -->
<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" alt="Confirm"/>
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to submit this Application?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button type="button" id="confirmSubmitYesBtn" class="btn btn-primary px-4">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Success Submit -->
<div class="modal fade" id="successSubmitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
                 autoplay muted loop playsinline type="video/mp4"
                 style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Your application has been submitted.</p>
        <div><button type="button" id="successOkBtn" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button></div>
      </div>
    </div>
  </div>
</div>


{{-- ================= Libraries ================= --}}
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script>
  if (window.pdfjsLib?.GlobalWorkerOptions) {
    const localWorker = "{{ asset('vendor/pdfjs/pdf.worker.min.js') }}";
    const cdnWorker   = "https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js";
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = localWorker;
    // Soft fallback check
    fetch(localWorker, { method: 'HEAD' })
      .then(r => { if (!r.ok) window.pdfjsLib.GlobalWorkerOptions.workerSrc = cdnWorker; })
      .catch(() => { window.pdfjsLib.GlobalWorkerOptions.workerSrc = cdnWorker; });
  }
</script>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="https://unpkg.com/@zxing/library@0.20.0"></script>
<script src="https://unpkg.com/qr-scanner@1.4.2/qr-scanner.umd.min.js"></script>
<script> QrScanner.WORKER_PATH = 'https://unpkg.com/qr-scanner@1.4.2/qr-scanner-worker.min.js'; </script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof APP_CLOSED !== 'undefined' && APP_CLOSED) {
        showModal('#applicationClosedModal');
        const nextBtn = document.getElementById('next-btn');
        if (nextBtn) nextBtn.disabled = true;
    }
});

/* ===================== Tesseract explicit paths (robust web setup) ===================== */
const TESS_OPTS = {
  workerPath: 'https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/worker.min.js',
  corePath:   'https://cdn.jsdelivr.net/npm/tesseract.js-core@5/tesseract-core.wasm.js',
  langPath:   'https://tessdata.projectnaptha.com/4.0.0'
};

let matchingPostId = null;

/* === AUTO-TRIM HELPERS === */
let __autoProceedTimer = null;
function maybeAutoProceedToValidation() {
  if (currentStep !== 3) return;

  if (!window.cogWorkDonePromise) {
    window.cogWorkDonePromise = (ocrReady || Promise.resolve()).catch(()=>{});
  }

  clearTimeout(__autoProceedTimer);
  __autoProceedTimer = setTimeout(() => {
    const hasQR  = (window.lastQrRawText || '').trim().length > 0;
    const hasOCR = (window.lastOcrRawText || '').trim().length > 0;
    if (hasQR && hasOCR) {
      currentStep = 4;
      updateStepperUI();
      extractThenValidate();
    }
  }, 80);
}
function canvasAutoTrim(srcCanvas, fuzz = 18) {
  const w = srcCanvas.width, h = srcCanvas.height;
  const ctx = srcCanvas.getContext('2d', { willReadFrequently: true });
  const id = ctx.getImageData(0, 0, w, h);
  const d = id.data;
  const isWhiteish = (i) => {
    const r = d[i], g = d[i+1], b = d[i+2];
    return (r >= 255 - fuzz) && (g >= 255 - fuzz) && (b >= 255 - fuzz);
  };
  let top=h, left=w, bottom=0, right=0;
  for (let y=0; y<h; y++) for (let x=0; x<w; x++) {
    const i = (y*w + x) * 4;
    if (!isWhiteish(i)) { if (y < top) top = y; if (y > bottom) bottom = y; if (x < left) left = x; if (x > right) right = x; }
  }
  if (top >= bottom || left >= right) return srcCanvas;
  const pad = Math.floor(Math.min(w,h) * 0.01);
  left=Math.max(0,left-pad); right=Math.min(w-1,right+pad); top=Math.max(0,top-pad); bottom=Math.min(h-1,bottom+pad);
  const cw = right - left + 1, ch = bottom - top + 1;
  const out = document.createElement('canvas'); out.width = cw; out.height = ch;
  out.getContext('2d').drawImage(srcCanvas, left, top, cw, ch, 0, 0, cw, ch);
  return out;
}
function canvasToBlob(canvas, mime = 'image/png', quality = 0.95) {
  return new Promise((resolve) => { canvas.toBlob((blob) => resolve(blob), mime, quality); });
}

/* ======== SAFE OCR PATCH HELPERS ======== */
function scaleCanvasMax(src, maxEdge = 2200) {
  const w = src?.width | 0, h = src?.height | 0;
  if (!w || !h) return src;
  const max = Math.max(w, h);
  if (max <= maxEdge) return src;
  const ratio = maxEdge / max;
  const cw = Math.max(1, Math.round(w * ratio));
  const ch = Math.max(1, Math.round(h * ratio));
  const out = document.createElement('canvas');
  out.width = cw; out.height = ch;
  out.getContext('2d', { willReadFrequently: true }).drawImage(src, 0, 0, cw, ch);
  return out;
}
function isCanvasUsable(c){ return !!(c && c.width > 0 && c.height > 0); }
function canvasToPngBlob(canvas, quality = 0.92){
  return new Promise((resolve, reject) => {
    if (!isCanvasUsable(canvas)) return reject(new Error('Empty canvas'));
    canvas.toBlob(b => b ? resolve(b) : reject(new Error('toBlob failed')), 'image/png', quality);
  });
}
/** Robust OCR: scale big canvases, use Blob, retry smaller; always inject TESS_OPTS */
async function tesseractRecognizeSafe(canvas, lang = 'eng', opts = {}){
  if (!isCanvasUsable(canvas)) throw new Error('Canvas 0×0; nothing to OCR');
  let scaled = scaleCanvasMax(canvas, 2200);
  try {
    const blob = await canvasToPngBlob(scaled, 0.92);
    const { data } = await Tesseract.recognize(blob, lang, { ...TESS_OPTS, ...opts });
    return (data?.text || '').trim();
  } catch(e1){
    console.warn('OCR first attempt failed, retrying smaller…', e1);
    try {
      const smaller = scaleCanvasMax(scaled, 1400);
      const blob2 = await canvasToPngBlob(smaller, 0.9);
      const { data } = await Tesseract.recognize(blob2, lang, { ...TESS_OPTS, ...opts });
      return (data?.text || '').trim();
    } catch(e2){
      console.warn('OCR second attempt failed', e2);
      return ''; // never bubble errors; upstream UI handles empty OCR
    }
  }
}
</script>

<script>
/* ===== Flags / Globals ===== */
const APP_CLOSED = @json($isApplicationClosed);
let currentStep = 1;
let isBusy = false;
let validationPass = false;

let tamperTarget = 'cog';
let tamperDetails = '';
const serverPaths = { cor_pdf:'', cor_img:'', cor_text:'', cor_public_preview:'', cog_img:'' };

window.lastOcrRawText = '';
window.lastQrRawText  = '';
window.lastCorQr = '';
window.corMetaFromOcr = {};
let lastParsedMeta = {};
let lastParsedRows = [];

let parsedFromQR  = { meta:{}, rows:[] };
let parsedFromOCR = { meta:{}, rows:[] };

/* NEW: mismatch state */
let lastMismatches = [];
let mismatchIndex = new Map();

/* NEW: OCR readiness gate */
let ocrReadyResolve = null;
let ocrReady = new Promise(res => (ocrReadyResolve = res));

/* ===== Modal helper ===== */
function showModal(selector){
  const el = document.querySelector(selector);
  if (!el) return { hide(){}, show(){} };
  if (!window.bootstrap?.Modal) {
    console.warn('Bootstrap Modal not available for', selector);
    return { hide(){}, show(){} };
  }
  const inst = bootstrap.Modal.getOrCreateInstance(el);
  inst.show();
  return inst;
}

/* ===== UI helpers ===== */
function setBusy(state){
  isBusy = state;
  const btn = document.getElementById('next-btn');
  if (btn) {
    btn.disabled = state;
    btn.innerHTML = state ? 'Please wait…' : (currentStep === 7 ? 'Finish' : 'Next');
  }
}
function updateStepperUI(){
  const steps = Array.from(document.querySelectorAll('#am-steps .am-step'));
  const total = steps.length || 1;
  steps.forEach((el, idx) => {
    const n = idx + 1;
    el.classList.remove('completed','active','upcoming');
    if (n < currentStep) el.classList.add('completed');
    else if (n === currentStep) el.classList.add('active');
    else el.classList.add('upcoming');
  });
  const line = document.getElementById('am-stepper-line-fill');
  if (line) line.style.width = Math.max(0, Math.min(100, ((currentStep-1) / (total-1)) * 100)) + '%';
  const HIDE_CARD_TITLE_STEPS = new Set([2,3,5]);
  const titles = {1:'<i class="bi bi-pin-angle-fill text-danger me-2"></i>Guidelines',4:'Validation',6:'Review & Confirm',7:'Generate Application'};
  const ct = document.getElementById('card-title');
  if (ct) {
    if (HIDE_CARD_TITLE_STEPS.has(currentStep)) { ct.style.display='none'; ct.innerHTML=''; }
    else { ct.style.display='block'; if (titles[currentStep]) ct.innerHTML=titles[currentStep]; }
  }
  for (let i=1;i<=7;i++){ const s=document.getElementById('step-'+i); if (s) s.style.display = i===currentStep?'block':'none'; }
  const nextBtn = document.getElementById('next-btn');
  if (nextBtn) nextBtn.innerText = currentStep === 7 ? 'Finish' : 'Next';
}
function goNext(){
  if (isBusy) return;
  if (APP_CLOSED) { showModal('#applicationClosedModal'); return; }
  if (currentStep === 2) {
    const f = document.getElementById('file-cor').files[0];
    const status = document.getElementById('cor-status');
    if (!f) { status.textContent = 'Please select your COR PDF.'; return; }
    if (f.type !== 'application/pdf') { status.textContent = 'COR must be a PDF file.'; return; }
    if (!serverPaths.cor_img && !serverPaths.cor_pdf) { status.textContent = 'Still processing COR…'; return; }
  }
  if (currentStep === 3) {
    const f = document.getElementById('file-grade').files[0];
    const status = document.getElementById('cog-status');
    if (!f) { status.textContent = 'Please select your COG file.'; return; }
    const ok = (f.type && f.type.startsWith('image/')) || f.type === 'application/pdf';
    if (!ok) { status.textContent = 'COG must be an image or a PDF.'; return; }
    currentStep = 4; updateStepperUI();
    extractThenValidate();
    return;
  }
  if (currentStep === 4) {
    if (!validationPass) { alert('Please wait for validation to complete or resolve any issues.'); return; }
    currentStep = 5; updateStepperUI(); return;
  }
  if (currentStep === 5) {
    const c1 = document.getElementById('consent1').checked;
    const c2 = document.getElementById('consent2').checked;
    const c3 = document.getElementById('consent3').checked;
    if (!c1 || !c2 || !c3) { alert('Please check all consent checkboxes.'); return; }
    currentStep = 6; updateStepperUI(); return;
  }
  if (currentStep === 6) setTimeout(fetchAndDisplayStructuredCogOutput, 100);
  if (currentStep === 6) { currentStep = 7; updateStepperUI(); generatePdf(); return; }
  if (currentStep === 7) { showConfirmSubmitModal(); return; }
  if (currentStep < 7) { currentStep++; updateStepperUI(); }
}
function goBack(){ if (isBusy) return; if (currentStep > 1){ currentStep--; updateStepperUI(); }}

/* ===== Server helpers ===== */
function csrfToken(){ return document.querySelector('meta[name="csrf-token"]').getAttribute('content'); }
function route(nameMeta){
  const v = document.querySelector(`meta[name="${nameMeta}"]`)?.content || '';
  if (!v && window.console) console.debug(`[meta route missing] ${nameMeta}`);
  return v;
}
async function saveDebugRaw(text, source){
  const url =
    (document.querySelector('meta[name="route-save-cog-output"]')?.content) ||
    (document.querySelector('meta[name="route-save-cog-debug"]')?.content) ||
    '';

  if (!url) return;

  try {
    await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        meta:   {},
        rows:   [],
        totals: {},
        pdf_text: `[SOURCE:${source}]` + "\n" + String(text || ''),
        ocr_full: String(text || ''),
        qr_raw: ''
      })
    });
  } catch(e) {
    console.warn('saveDebugRaw failed', e);
  }
}

async function validateGrades() {
  const response = await fetch('/student/validate-grades', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
    },
    body: JSON.stringify({}),
  });
  const result = await response.json();
  if (result.status === 'fail') {
    console.log('Mismatches:', result.mismatches);
  } else if (result.status === 'success') {
    alert(result.message);
  } else {
    alert('An error occurred.');
  }
}

/* Wait until an <img> has real dimensions */
async function ensureImageReady(img){
  if (!img) return false;
  if (img.complete && (img.naturalWidth || img.width)) return true;
  try { await img.decode?.(); } catch(_) {}
  return (img.naturalWidth || 0) > 0 && (img.naturalHeight || 0) > 0;
}

const metaTag = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';
async function fetchText(metaName){
  const url = metaTag(metaName);
  if (!url) return '';
  try {
    const res = await fetch(url, { headers: { 'Accept': 'text/plain,*/*' } });
    return res.ok ? await res.text() : '';
  } catch { return ''; }
}

/* ====== Parsed-files reader (supports symbols and int encodings) ====== */
function parseSimpleGradesTable(md=''){
  const out = [];
  const norm = (raw) => {
    let s = String(raw ?? '').trim().toUpperCase();
    if (s === 'INCOMPLETE') s = 'INC';
    if (s === 'DROP') s = 'DRP';
    if (['INC','DRP','W'].includes(s)) return s;
    if (s === '100') return '1.00';
    if (s === '125') return '1.25';
    if (s === '150') return '1.50';
    if (s === '175') return '1.75';
    if (s === '200') return '2.00';
    if (/^\d(?:\.\d{1,4})?$/.test(s)) return Number(s).toFixed(2);
    const just = s.replace(/[^0-9.]/g,'');
    return just ? Number(just).toFixed(2) : '';
  };
  const rx = /^\s*\|\s*\d+\s*\|\s*(100|125|150|175|200|[0-3](?:\.\d{1,4})?|INC|INCOMPLETE|DRP|DROP|W)\s*\|\s*$/i;
  md.split('\n').forEach(line => {
    const m = line.match(rx);
    if (m) {
      const v = norm(m[1]);
      if (v) out.push(v);
    }
  });
  return out;
}
async function fetchParsedFilesGrades(){
  const qrTxt  = await fetchText('route-parse-qr-output-file');
  const ocrTxt = await fetchText('route-parse-ocr-output-file');
  return { qrGrades: parseSimpleGradesTable(qrTxt), ocrGrades: parseSimpleGradesTable(ocrTxt) };
}

/* ===== Normalize helpers (JS side) ===== */
function normalizeGradeTokenStrict(tok='') {
  let t = String(tok).trim().toUpperCase();
  if (t === 'INCOMPLETE') t = 'INC';
  if (t === 'DROP') t = 'DRP';
  if (['INC','DRP','W'].includes(t)) return t;
  if (t === '100') return '1.00';
  if (t === '125') return '1.25';
  if (t === '150') return '1.50';
  if (t === '175') return '1.75';
  if (t === '200') return '2.00';
  if (/^\d(?:\.\d+)?$/.test(t)) return Number(t).toFixed(2);
  const just = t.replace(/[^0-9.]/g,'');
  if (just && !isNaN(just)) return Number(just).toFixed(2);
  return '';
}

/**
 * Parse grades-only from the markdown table saved in cog_output.txt
 */
function parseGradesOnlyFromMarkdown(text='') {
  const out = [];
  const lines = (text || '').split('\n');
  const rowRx = /^\|\s*(\d+)\s*\|\s*.+?\|\s*.+?\|\s*\d+\s*\|\s*(100|125|150|175|200|[0-3](?:\.\d{1,4})?|INC|INCOMPLETE|DRP|DROP|W)\s*\|\s*.+?\|\s*.+?\|?\s*$/i;
  for (const line of lines) {
    const m = line.match(rowRx);
    if (m) out.push(normalizeGradeTokenStrict(m[2]));
  }
  return out;
}

/**
 * Parse grades-only from OCR plain text lines
 */
function parseGradesOnlyFromOcrPlain(text='') {
  const out = [];
  const lines = (text || '').split('\n').map(s=>s.trim()).filter(Boolean);

  const strictRx = /^\s*(\d+)\s+.+?\s+(\d{1,2})\s+(100|125|150|175|200|[0-3](?:\.\d{1,4})?)\s+[A-Za-z0-9-]+\s+/i;
  const lastGradeRx = /(?:^|\s)(100|125|150|175|200|[0-3]\.[0-9]{1,4})(?:\s|$)/gi;

  for (const line of lines) {
    let g = '';
    const s = line.replace(/\s{2,}/g,' ');

    const m = s.match(strictRx);
    if (m) {
      g = normalizeGradeTokenStrict(m[3]);
    } else {
      let gg = '', m2;
      while ((m2 = lastGradeRx.exec(s)) !== null) gg = m2[1];
      g = normalizeGradeTokenStrict(gg);
    }
    if (g) out.push(g);
  }
  return out;
}

/**
 * Convenience: fetch both files and return {gradesOut, gradesOcr, rawOut, rawOcr}
 */
async function fetchGradesOnlyPair() {

  const rawOut = await fetchText('route-cog-output-file');
  const rawOcrGradesOnly = await fetchText('route-parse-ocr-output-file');
  const rawOcrPlain      = await fetchText('route-cog-ocr-output-file');

  const gradesOut = parseGradesOnlyFromMarkdown(rawOut);

  let gradesOcr = [];
  if (rawOcrGradesOnly && /[\|\n]/.test(rawOcrGradesOnly)) {
    gradesOcr = parseGradesOnlyFromMarkdown(rawOcrGradesOnly);
  }
  if (gradesOcr.length === 0) {
    gradesOcr = parseGradesOnlyFromOcrPlain(rawOcrPlain);
  }

  return { gradesOut, gradesOcr, rawOut, rawOcr: rawOcrGradesOnly || rawOcrPlain };
}

/* ===== QR scan helpers ===== */
async function scanQrFromUploadedPdf(pdfFile){
  try{
    const canvas = await renderPdfFirstPageToCanvas(pdfFile);
    try {
      const r = await QrScanner.scanImage(canvas, { returnDetailedScanResult:true, inversionAttempts:'attemptBoth' });
      if (r?.data) return String(r.data);
    } catch(_){}
    try {
      // ZXing requires an <img>, not a canvas
      const reader = new ZXing.BrowserQRCodeReader();
      const imgEl = new Image();
      imgEl.src = canvas.toDataURL('image/png');
      await imgEl.decode?.();
      const r2 = await reader.decodeFromImage(imgEl);
      if (r2?.text) return String(r2.text);
    } catch(_){}
    try{
      const ctx = canvas.getContext('2d', { willReadFrequently:true });
      const id  = ctx.getImageData(0,0,canvas.width,canvas.height);
      const res = jsQR(id.data, canvas.width, canvas.height);
      if (res?.data) return String(res.data);
    }catch(_){}
  }catch(_){}
  return '';
}
async function scanQrFromFullImageUrl(url){
  if (!url) return '';
  const img = await new Promise((resolve, reject) => {
    const i = new Image();
    i.crossOrigin = 'anonymous';
    i.onload = () => resolve(i);
    i.onerror = reject;
    i.src = url + (url.includes('?') ? '&' : '?') + 'v=' + Date.now();
  });
  const c = document.createElement('canvas');
  c.width  = img.naturalWidth || img.width;
  c.height = img.naturalHeight || img.height;
  c.getContext('2d', { willReadFrequently:true }).drawImage(img,0,0);
  try{
    const r = await QrScanner.scanImage(c, { returnDetailedScanResult:true, inversionAttempts:'attemptBoth' });
    if (r?.data) return String(r.data);
  }catch(_){}
  try {
    const reader = new ZXing.BrowserQRCodeReader();
    const r2 = await reader.decodeFromImage(img);
    if (r2?.text) return String(r2.text);
  } catch {}
  try{
    const ctx = c.getContext('2d', { willReadFrequently:true });
    const id  = ctx.getImageData(0,0,c.width,c.height);
    const res = jsQR(id.data, c.width, c.height);
    if (res?.data) return String(res.data);
  }catch(_){}
  return '';
}

/* ===========================================================
   1) UPLOADERS
   =========================================================== */
function resetCogState(){
  window.lastQrRawText  = '';
  window.lastOcrRawText = '';
  parsedFromQR  = { meta:{}, rows:[] };
  parsedFromOCR = { meta:{}, rows:[] };
  lastParsedMeta = {};
  lastParsedRows = [];
  mismatchIndex = new Map();
  lastMismatches = [];

  ocrReady = new Promise(res => (ocrReadyResolve = res));

  const holder = document.getElementById('preview-grade');
  if (holder) holder.innerHTML = '';
  const tb = document.getElementById('grade-table-body');
  if (tb) tb.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No rows detected. Please re-upload a clearer image.</td></tr>';
  (document.getElementById('sum-courses')||{}).textContent = '—';
  (document.getElementById('sum-units')||{}).textContent   = '—';
  (document.getElementById('sum-gwa')||{}).textContent     = '—';
}

async function uploadCorPdf(){
  const input = document.getElementById('file-cor');
  const file = input.files && input.files[0];
  if (!file) return;

  const el = document.getElementById('cor-status');
  el.textContent = 'Uploading & processing…';
  try{
    const fd = new FormData(); fd.append('pdf', file);
    const res = await fetch(route('route-cor-upload'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept':'application/json' }, body: fd });
    if(!res.ok) throw new Error(await res.text());
    const j = await res.json();

    serverPaths.cor_pdf  = j.cor_pdf_path || '';
    serverPaths.cor_img  = j.cor_png_path || j.cor_upload_png_path || j.cropped_png_path || serverPaths.cor_img || '';
    serverPaths.cor_text = j.cor_text_path || j.cor_output_txt_path || '';
    serverPaths.cor_public_preview = j.cor_png_url || j.cor_upload_png_url || j.cropped_png_url || j.cor_pdf_url || '';

    if (serverPaths.cor_public_preview) {
      const cb = serverPaths.cor_public_preview + (serverPaths.cor_public_preview.includes('?')?'&':'?') + 'v=' + Date.now();
      if (serverPaths.cor_public_preview.toLowerCase().endsWith('.png')) {
        document.getElementById('preview-cor').innerHTML =
          `<img src="${cb}" class="img-fluid rounded border" style="max-height:420px" />`;
      } else {
        document.getElementById('preview-cor').innerHTML =
          `<iframe src="${cb}" width="100%" height="420" class="rounded border"></iframe>`;
      }
    } else {
      previewPDF(file, 'preview-cor');
    }

    await extractCorPdf();

    el.textContent = 'COR uploaded successfully. (File extracted and processed.)';
    bumpPathsFeedback();
  }catch(e){
    console.error(e);
    el.textContent = 'Upload failed.';
  }
}

/** ORIGINAL: handleCogUpload — will be wrapped by PDF-only patch later */
async function handleCogUpload(input) {
  resetCogState();
  const f = input.files && input.files[0];
  if (!f) return;

  const status = document.getElementById('cog-status');
  const holder = document.getElementById('preview-grade');
  status.textContent = '';

  window.cogWorkDonePromise = (async () => {
    if (f.type && f.type.startsWith('image/')) {
      await uploadCogImageTrimmed(f);
      return;
    }

    if (f.type === 'application/pdf') {
      try {
        status.textContent = 'Uploading COG PDF…';
        const fd = new FormData(); fd.append('pdf', f);

        const res = await fetch(route('route-cog-upload'), {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
          body: fd
        });
        let j = {}; try { j = await res.json(); } catch(_) {}

        serverPaths.cog_img =
          j.cog_image_path ?? j.cog_path ?? j.absolute_path ?? j.full_path ?? j.abs_path ?? j.file_path ?? j.filepath ?? j.storage_path ?? j.path ?? '';

        const pngUrl = j.cog_image_url ?? j.public_url ?? j.url ?? '';
        const pdfUrl = j.cog_pdf_url || (pngUrl && pngUrl.endsWith('.pdf') ? pngUrl : '');

        if (pngUrl && !pngUrl.endsWith('.pdf')) {
          const cb = pngUrl + (pngUrl.includes('?')?'&':'?') + 'v=' + Date.now();
          holder.innerHTML = `<img id="cogPreviewImg" src="${cb}" class="img-fluid rounded border" style="max-height:380px" />`;
        } else if (pdfUrl) {
          holder.innerHTML = '<div class="text-muted small">Rendering PDF preview…</div>';
          try {
            let c = null;
            try {
              c = await renderPdfUrlFirstPageToCanvas(pdfUrl);
              c.className = 'img-fluid rounded border';
              c.style.maxHeight = '380px';
              holder.innerHTML = '';
              holder.appendChild(c);
            } catch (e) {
              console.warn('PDF preview render failed, trying local render…', e);
              holder.innerHTML = '<div class="text-danger small">PDF preview failed; server URL may block rendering. Try downloading and re-uploading as image.</div>';
            }

            if (c && c.width && c.height) {
              await runQrOcrFromCanvas(c, status, { pdfUrl, fallbackFile: f });
            } else {
              status.textContent = 'Cannot OCR (preview unavailable).';
            }
          } catch {
            holder.innerHTML = '<div class="text-danger small">PDF preview failed; will try local render.</div>';
          }
        } else {
          holder.innerHTML = '<div class="text-muted small">No server preview; rendering local PDF…</div>';
        }

        const pdfText = (j.pdf_text ?? j.ocr_text ?? '').trim();
        if (pdfText) {
          window.lastOcrRawText = pdfText;
          parsedFromOCR = parsePlainCOGText(pdfText);
          try{ ocrReadyResolve && ocrReadyResolve(); }catch(_){}
          status.textContent = 'Captured PDF text from server.';
        } else {
          const imgEl = document.getElementById('cogPreviewImg');
          if (imgEl) {
            status.textContent = 'OCR-ing image preview…';
            const c = document.createElement('canvas');
            c.width = imgEl.naturalWidth || imgEl.width;
            c.height = imgEl.naturalHeight || imgEl.height;
            c.getContext('2d', { willReadFrequently:true }).drawImage(imgEl, 0, 0);
            await runQrOcrFromCanvas(c, status);
          } else if (pdfUrl) {
            status.textContent = 'OCR-ing rendered PDF preview…';
            const c = await renderPdfUrlFirstPageToCanvas(pdfUrl);
            await runQrOcrFromCanvas(c, status);
          } else {
            status.textContent = 'OCR-ing local PDF (client-side)…';
            const buf = await f.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
            const page = await pdf.getPage(1);
            const vp = page.getViewport({ scale: 2.0 });
            const c = document.createElement('canvas');
            c.width = vp.width; c.height = vp.height;
            await page.render({ canvasContext: c.getContext('2d', { willReadFrequently:true }), viewport: vp }).promise;
            c.className = 'img-fluid rounded border'; c.style.maxHeight = '380px';
            holder.innerHTML = ''; holder.appendChild(c);
            await runQrOcrFromCanvas(c, status);
          }
        }

        if (!window.lastQrRawText) {
          status.textContent = 'Scanning QR (local PDF)…';
          const qrRaw = await scanQrFromUploadedPdf(f);
          if (qrRaw) {
            window.lastQrRawText = qrRaw;
            try {
              const api = await resolveQrOnServer(qrRaw);
              parsedFromQR = mapServerJsonToParsed(api);
              status.textContent = 'QR captured.';
            } catch {
              const local = parseQrPayload(qrRaw);
              parsedFromQR = local?.rows?.length ? local : { meta:{}, rows:[] };
            }
          }
        }

        if ((window.lastQrRawText || '').trim()) {
          maybeAutoProceedToValidation();
        }
      } catch (err) {
        console.error(err);
        status.textContent = 'Upload failed. Please try a clearer PDF.';
      }
      return;
    }

    status.textContent = 'COG must be an image or a PDF.';
  })();
}

async function uploadCogImageTrimmed(file) {
  const status = document.getElementById('cog-status');
  const holder = document.getElementById('preview-grade');

  window.cogWorkDonePromise = (async () => {
    status.textContent = 'Preparing image…';

    const tmpUrl = URL.createObjectURL(file);
    const img = await new Promise((res, rej) => { const i = new Image(); i.onload=()=>res(i); i.onerror=rej; i.src=tmpUrl; });
    const c = document.createElement('canvas');
    c.width = img.width; c.height = img.height;
    c.getContext('2d', { willReadFrequently:true }).drawImage(img, 0, 0);
    URL.revokeObjectURL(tmpUrl);

    let trimmed = canvasAutoTrim(c, 18);
    if (!isCanvasUsable(trimmed)) {
      status.textContent = 'Image load failed (0×0). Please re-upload a clearer image.';
      throw new Error('Trimmed canvas 0×0');
    }

    holder.innerHTML = '';
    trimmed.className = 'img-fluid rounded border';
    trimmed.style.maxHeight = '380px';
    holder.appendChild(trimmed);

    const blob = await canvasToBlob(trimmed, 'image/png', 0.95);
    const pngFile = new File([blob], (file.name || 'cog.png').replace(/\.(jpe?g|png)$/i, '.png'), { type:'image/png' });

    status.textContent = 'Uploading…';
    const fd = new FormData(); fd.append('image', pngFile);
    const res = await fetch(route('route-cog-upload'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept':'application/json' }, body: fd });
    if (!res.ok) { status.textContent = 'Upload failed.'; throw new Error(await res.text()); }
    const j = await res.json();

    serverPaths.cog_img =
      j.cog_image_path ?? j.cog_path ?? j.absolute_path ?? j.full_path ??
      j.abs_path ?? j.file_path ?? j.filepath ?? j.storage_path ?? j.path ?? '';

    const publicUrl = j.cog_image_url ?? j.public_url ?? j.url ?? '';
    if (publicUrl) {
      const cb = publicUrl + (publicUrl.includes('?')?'&':'?') + 'v=' + Date.now();
      holder.innerHTML = `<img src="${cb}" class="img-fluid rounded border" style="max-height:380px" />`;
    }

    status.textContent = 'Scanning QR…';
    await runQrOcrFromCanvas(trimmed, status);
  })();

  return window.cogWorkDonePromise;
}

/* QR/OCR from a canvas */
async function runQrOcrFromCanvas(canvas, statusEl, opts = {}) {
  const { pdfUrl = '', fallbackFile = null } = opts;

  // ---- QR FIRST ------------------------------------------------------------
  let qrText = '';
  try {
    const r = await QrScanner.scanImage(canvas, {
      returnDetailedScanResult: true,
      inversionAttempts: 'attemptBoth'
    });
    if (r?.data) qrText = String(r.data);
  } catch {}

  if (!qrText) {
    try {
      const reader = new ZXing.BrowserQRCodeReader();
      const imgEl = new Image();
      imgEl.src = canvas.toDataURL('image/png');
      await imgEl.decode?.();
      const r2 = await reader.decodeFromImage(imgEl);
      if (r2?.text) qrText = String(r2.text);
    } catch {}
  }

  if (!qrText) {
    try {
      const g = canvas.getContext('2d', { willReadFrequently:true });
      const id = g.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(id.data, canvas.width, canvas.height);
      if (code?.data) qrText = String(code.data);
    } catch {}
  }

  window.lastQrRawText = qrText || '';

  if (qrText) {
    const api = await resolveQrOnServer(qrText);

    if (api && Array.isArray(api.grades) && api.grades.length) {
      parsedFromQR = mapServerJsonToParsed(api);
    } else {
      if (api?.qr_url) window.lastQrUrl = api.qr_url;
      const local = parseQrPayload(qrText);
      parsedFromQR = local?.rows?.length ? local : { meta:{}, rows:[] };
    }

    maybeAutoProceedToValidation();
  }

  // ---- OCR NEXT ------------------------------------------------------------
  if (!isCanvasUsable(canvas)) {
    if (statusEl) statusEl.textContent = 'Preview not ready — rebuilding…';
    let rebuilt = null;

    const imgEl = document.getElementById('cogPreviewImg');
    if (await ensureImageReady(imgEl)) {
      rebuilt = document.createElement('canvas');
      rebuilt.width  = imgEl.naturalWidth || imgEl.width;
      rebuilt.height = imgEl.naturalHeight || imgEl.height;
      rebuilt.getContext('2d', { willReadFrequently:true }).drawImage(imgEl, 0, 0);
    }

    if (!isCanvasUsable(rebuilt) && pdfUrl) {
      try { rebuilt = await renderPdfUrlFirstPageToCanvas(pdfUrl); } catch(_) {}
    }

    if (!isCanvasUsable(rebuilt) && fallbackFile) {
      try { rebuilt = await renderPdfFirstPageToCanvas(fallbackFile); } catch(_) {}
    }

    if (isCanvasUsable(rebuilt)) {
      canvas = rebuilt;
    } else {
      if (statusEl) statusEl.textContent = 'Cannot prepare image for OCR (0×0).';
      throw new Error('Canvas 0×0 after rebuild');
    }
  }

  if (statusEl) statusEl.textContent = 'Running OCR…';
  try {
    window.lastOcrRawText = await tesseractRecognizeSafe(canvas, 'eng', {
      ...TESS_OPTS,
      tessedit_char_blacklist:'[]{}<>~`^'
    });
  } catch (e) {
    console.error('OCR failed', e);
    window.lastOcrRawText = '';
    if (statusEl) statusEl.textContent = 'OCR failed — try clearer image.';
  }

  if (window.lastOcrRawText.trim()) { await saveDebugRaw(window.lastOcrRawText, 'OCR'); }
  parsedFromOCR = parsePlainCOGText(window.lastOcrRawText || '');

  try { ocrReadyResolve && ocrReadyResolve(); } catch(_) {}

  if (statusEl) {
    statusEl.textContent = qrText
      ? 'QR + OCR captured. Proceed to Validation.'
      : 'OCR captured (no QR found).';
  }
  bumpPathsFeedback?.();
}

function bumpPathsFeedback(){
  const f = document.getElementById('paths-feedback');
  const source = window.lastQrRawText ? 'Source: QR ✅' : (window.lastOcrRawText ? 'Source: OCR' : '');
  f.textContent =
    `${source}  |  COR PDF: ${!!serverPaths.cor_pdf ? 'ok' : '—'}  |  COR PNG: ${!!serverPaths.cor_img ? 'ok' : '—'}  |  COG PNG: ${!!serverPaths.cog_img ? 'ok' : '—'}`;
}

/* ===========================================================
   2) QR helpers / server resolve
   =========================================================== */
async function resolveQrOnServer(qrText){
  const res = await fetch(route('route-qr-resolve'), {
    method: 'POST',
    headers: {
      'Content-Type':'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'Accept':'application/json'
    },
    body: JSON.stringify({ payload: String(qrText || '') })
  });

  // Try to read JSON either way so we can surface details
  const json = await res.json().catch(() => ({}));

  if (res.ok) {
    window.lastQrUrl = json?.qr_url || window.lastQrUrl || '';
    return json;
  }

  const qrUrl = json?.qr_url || extractQrUrlFromPayload(qrText) || '';
  window.lastQrUrl = qrUrl;

  console.warn('QR resolve returned', res.status, json?.error || 'Unknown error');
  return {
    ok: false,
    error: json?.error || json?.message || 'No grades table found',
    header: {},
    grades: [],
    qr_url: qrUrl
  };
}

function extractQrUrlFromPayload(p){
  try {
    if (/^https?:\/\//i.test(p)) return p;
    const j = JSON.parse(p);
    if (j?.url) return j.url;
    if (j?.link) return j.link;
  } catch(_) {}
  try {
    const maybe = atob(p);
    const j = JSON.parse(maybe);
    if (j?.url) return j.url;
  } catch(_) {}
  return '';
}
function splitCodeTitle(name){ const m = name.match(/^([A-Za-z]{2,}(?:\s*[-/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s+(.+)$/); return m?{code:m[1],title:m[2]}:{code:'',title:name}; }
function mapServerJsonToParsed(api){
  const h = api?.header || {};
  const meta = sanitizeMeta({
    fullname: fixEncoding(h['Fullname'] || ''),
    srcode: (h['SRCODE'] || '').replace(/[.\s]/g,'-').replace(/-+/g,'-'),
    college: fixEncoding(h['College'] || ''),
    academic_year: h['Academic Year'] || '',
    program: fixEncoding(h['Program'] || ''),
    semester: (h['Semester'] || '').toString().trim(),
    year_level: (h['Year Level'] || '').toString().trim(),
    total_units: api?.total_units != null ? String(api.total_units) : '',
    total_courses: api?.total_courses != null ? String(api.total_courses) : '',
    gwa: api?.gwa != null ? String(api.gwa) : ''
  });
  const rows=[]; (api?.grades||[]).forEach((g,i)=>{ const name=(g.name||'').trim(); const {code,title}=splitCodeTitle(name);
    rows.push({ idx:i+1, code, title:fixEncoding(title), units:g.units!=null?String(g.units):'', grade:g.grade!=null?String(g.grade):'', section:g.section||'', instructor:fixEncoding(g.instructor||'')}); });
  return { meta, rows };
}

/* ===========================================================
   3) OCR parsing + utilities
   =========================================================== */
function fixEncoding(s=''){ try { return decodeURIComponent(escape(s)); } catch(_){}
  return s.replace(/Ã‘/g,'Ñ').replace(/Ã/g,'Ñ').replace(/Ã±/g,'ñ')
          .replace(/Ã"/g,'Ó').replace(/Ã³/g,'ó').replace(/Ã‰/g,'É').replace(/Ã©/g,'é')
          .replace(/Ã/g,'Á').replace(/Ã¡/g,'á').replace(/Ã/g,'Í').replace(/Ã­/g,'í')
          .replace(/Ãš/g,'Ú').replace(/Ãº/g,'ú'); }
const PLACEHOLDER_TOKENS = new Set(['FULLNAME','SRCODE','COLLEGE','PROGRAM','SEMESTER','YEAR LEVEL','ACADEMIC YEAR','-','—','N/A','NA']);
function looksFilled(key, val){ if (val==null) return false; const v=String(val).trim(); if(!v) return false;
  const up=v.toUpperCase(); if(PLACEHOLDER_TOKENS.has(up)) return false;
  if(key==='srcode') return /\d/.test(v); if(key==='fullname') return v.length>=5;
  if(key==='academic_year') return /\d{4}\s*-\s*\d{4}/.test(v)||/\b\d{4}\b/.test(v); return true; }
function sanitizeMeta(meta={}){
  const out = {...meta};
  out.srcode  = cleanSrcode(out.srcode||'');
  out.program = cleanProgram(out.program||'');
  for (const k of ['fullname','college','semester','year_level','academic_year']) {
    if (!looksFilled(k, out[k])) out[k] = '';
  }
  return out;
}
function preferFilled(a,b,key){ return looksFilled(key,a)?a:(looksFilled(key,b)?b:''); }
function mergeMetaPreferFilled(a={},b={}){ const keys=['fullname','srcode','college','program','semester','year_level','academic_year','total_units','total_courses','gwa']; const m={}; for(const k of keys) m[k]=preferFilled(a[k],b[k],k); return m; }

function sliceAfter(lines, idx, stopRx){ if (idx === -1) return ''; let seg = lines[idx].replace(/^\s*[^:]+:\s*/,'').trim(); if (stopRx) { const flags = stopRx.flags && stopRx.flags.includes('i') ? 'i' : ''; seg = seg.replace(new RegExp(stopRx.source + '.*$', flags), '').trim(); } return seg.trim(); }
const HDR = {
  fullname:       /^(?:Fullname|Fuliname|Fulname)\s*:?\s*/i,
  srcode:         /^(?:SRCODE|SR\s*CODE|SR-CODE)\s*:?\s*/i,
  college:        /^(?:College)\s*:?\s*/i,
  program:        /^(?:Program)\s*:?\s*/i,
  semester:       /^(?:Semester)\s*:?\s*/i,
  year_level:     /^(?:Year\s*Level|YearLevel)\s*:?\s*/i,
  academic_year:  /^(?:Academic\s*Year|AcademicYear)\s*:?\s*/i,
};
const RX = { academic_year_val: /([0-9]{4}\s*[-–—]\s*[0-9]{4}|[0-9]{4})/i };
function extractMetaWithAliases(lines){
  const idxOf = (rx)=> lines.findIndex(l => rx.test(l));

  const iFull = idxOf(HDR.fullname);
  const iCol  = idxOf(HDR.college);
  const iProg = idxOf(HDR.program);
  const iSem  = idxOf(HDR.semester);
  const iYLvl = idxOf(HDR.year_level);
  const iAY   = idxOf(HDR.academic_year);
  const iSR   = idxOf(HDR.srcode);

  const lineFull = iFull !== -1 ? lines[iFull] : '';
  const lineCol  = iCol  !== -1 ? lines[iCol]  : '';
  const lineProg = iProg !== -1 ? lines[iProg] : '';
  const lineSem  = iSem  !== -1 ? lines[iSem]  : '';
  const lineAY   = iAY   !== -1 ? lines[iAY]   : '';
  const lineSR   = iSR   !== -1 ? lines[iSR]   : '';

  const fullname   = iFull !== -1 ? sliceAfter(lines, iFull, /SRCODE\s*:?\s*/i) : '';
  const college    = iCol  !== -1 ? sliceAfter(lines, iCol,  /Academic\s*Year\s*:?\s*/i) : '';
  const programRaw = iProg !== -1 ? sliceAfter(lines, iProg, /Semester\s*:?\s*/i) : '';
  const program    = cleanProgram(programRaw);
  const semester   = iSem  !== -1 ? lineSem.replace(HDR.semester,'').trim() : '';
  const yearLvl    = iYLvl !== -1 ? lines[iYLvl].replace(HDR.year_level,'').trim() : '';

  let srcode = iSR !== -1 ? lineSR.replace(HDR.srcode,'').trim() : '';
  srcode = cleanSrcode(srcode);

  let ay = '';
  if (iAY !== -1) {
    const m = lineAY.match(RX.academic_year_val);
    ay = m ? m[1] : lineAY.replace(HDR.academic_year,'').trim();
  }

  if (!srcode && lineFull) {
    const mSR = lineFull.match(/SRCODE\s*:\s*([A-Z0-9\-]+)/i);
    if (mSR) srcode = mSR[1].trim();
  }
  srcode = cleanSrcode(srcode);

  if (!ay && lineCol) {
    const mAY = lineCol.match(/Academic\s*Year\s*:\s*([0-9]{4}\s*[-–—]\s*[0-9]{4}|[0-9]{4})/i);
    if (mAY) ay = mAY[1].trim();
  }

  let sem2 = semester;
  if (!sem2 && lineProg) {
    const m = lineProg.match(/Semester\s*:\s*([A-Za-z]+)/i);
    if (m) sem2 = m[1].trim();
  }

  return {
    fullname,
    srcode,
    college,
    program,
    semester: sem2,
    year_level: yearLvl,
    academic_year: ay
  };
}

function parsePlainCOGText(text){
  let cleaned = text.replace(/\r/g,'').replace(/[""]/g,'"').replace(/[''']/g,"'").replace(/[—–−]/g,'-').replace(/\u00A0/g, ' ');
  const rawLines = cleaned.split('\n').map(s => s.trim()).filter(Boolean);
  const meta0 = extractMetaWithAliases(rawLines);

  const allText = rawLines.join(' ');
  if (!meta0.srcode) { const m = allText.match(/SRCODE\s*:\s*([A-Z0-9\-]+)/i); if (m) meta0.srcode = m[1].trim(); }
  if (!meta0.academic_year) { const m = allText.match(/Academic\s*Year\s*:\s*([0-9]{4}\s*[-–—]\s*[0-9]{4}|[0-9]{4})/i); if (m) meta0.academic_year = m[1].trim(); }
  if (!meta0.semester) { const m = allText.match(/Semester\s*:\s*([A-Za-z]+)/i); if (m) meta0.semester = m[1].trim(); }

  const meta = sanitizeMeta(meta0);

  const dropIfMatches = [
    /Course\s*Code|CourseCode|Couse\s*Title|Units|Grade|Section|Instructor/i,
    /NOTHING FOLLOWS/i,
    /Total\s+no\s+of\s+Units/i,
    /Total\s+no\s+of\s+Course/i,
    /General\s+Weighted\s+Average/i,
    /^BATANGAS\s+STATE\s+UNIVERSITY/i, /ARASOF/i, /Student's\s+Copy\s+of\s+Grades/i,
    /^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}/
  ];

  const lines = rawLines.map(s => s.replace(/\s{2,}/g,' ').replace(/\|/g,' ').replace(/\b1T\b/g,'IT').replace(/\bNTT\b/g,'IT'))
                        .filter(s => !dropIfMatches.some(rx => rx.test(s)));

  const rows = [];
  for (let i=0; i<lines.length; i++){
    let line = lines[i];
    let m = line.match(
      /^(?:\[\s*(\d+)\s*\]\s*)?([A-Za-z]{2,}(?:\s*[-/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s+(.+?)\s+(\d{1,2})\s+(100|125|150|175|200|[0-2]\.\d{1,4})\s+([A-Za-z]+(?:-?[A-Za-z]+)*-?\d{3,4}[A-Za]?)\s+(.+)$/i
    );
    if (!m){
      const next = (lines[i+1] || '');
      const joined = (line + ' ' + next).replace(/\s{2,}/g,' ');
      const m2 = joined.match(
        /^(?:\[\s*(\d+)\s*\]\s*)?([A-Za-z]{2,}(?:\s*[-/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s+(.+?)\s+(\d{1,2})\s+(100|125|150|175|200|[0-2]\.\d{1,4})\s+([A-Za-z]+(?:-?[A-Za-z]+)*-?\d{3,4}[A-Za]?)\s+(.+)$/i
      );
      if (m2){ m = m2; i += 1; }
    }
    if (!m){
      const m3 = line.match(/^(?:\[\s*(\d+)\s*\]\s*)?([A-Za-z]{2,}(?:\s*[-/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s+(.+)$/);
      if (m3){
        rows.push({ idx: m3[1] || (rows.length+1), code: m3[2].trim(), title: fixEncoding(m3[3].trim()), units: '', grade: '', section: '', instructor: '' });
      }
      continue;
    }
    const idx = m[1] || (rows.length+1);
    const code = m[2].trim();
    const title = fixEncoding(m[3].trim());
    let units = m[4].trim();
    let grade = normalizeGradeToken(m[5]);
    const section = (m[6]||'').toUpperCase().trim();
    const instructor = fixEncoding((m[7]||'').trim());
    if (isNaN(parseFloat(units))) units = '';
    rows.push({ idx, code, title, units, grade, section, instructor });
  }
  return { meta, rows };
}
function normalizeGradeToken(tok){
  let t = tok.trim().toUpperCase();
  if (t === 'INCOMPLETE') t = 'INC';
  if (t === 'DROP') t = 'DRP';
  if (['INC','DRP','W'].includes(t)) return t;
  if (t==='100') return '1.00';
  if (t==='125') return '1.25';
  if (t==='150') return '1.50';
  if (t==='175') return '1.75';
  if (t==='200') return '2.00';
  if (/^\d\.\d{1,4}$/.test(t)) return t;
  if (/^\d$/.test(t)) return t + '.00';
  return t.replace(/[^\d.]/g,'');
}

/* ===========================================================
   4) Extraction + Validation flow
   =========================================================== */
async function extractThenValidate(){
    try{
        setBusy(true);

        if (window.cogWorkDonePromise) {
            try { await window.cogWorkDonePromise; } catch(_) {}
        }

        await extractFromCog();
        await runValidations(); // This now includes application period validation
    }catch(e){
        console.error(e);
        alert('Validation failed to run. Please try re-uploading a clearer COG image.');
    }finally{
        setBusy(false);
    }
}

/* keep only the SR Code token */
function cleanSrcode(v=''){
  const s = String(v).replace(/Sex\s*:.*$/i,'').trim();
  const m = s.match(/[A-Z0-9-]+/i);
  return m ? m[0] : '';
}
function cleanProgram(v=''){
  const s = String(v).trim();
  const after = s.replace(/^.*?\bProgram\s*:\s*/i,'');
  return (after && after !== s) ? after.trim() : s;
}

async function extractFromCog(){
  const alreadyHave =
    (window.lastQrRawText && parsedFromQR?.rows?.length) ||
    (window.lastOcrRawText && parsedFromOCR?.rows?.length);

  if (alreadyHave) {
    const qrMeta  = sanitizeMeta(parsedFromQR?.meta  || {});
    const ocrMeta = sanitizeMeta(parsedFromOCR?.meta || {});
    const corMeta = sanitizeMeta(window.corMetaFromOcr || {});
    const mergedMeta = mergeMetaPreferFilled(
      mergeMetaPreferFilled(corMeta, qrMeta),
      ocrMeta
    );

    const rows = (parsedFromOCR?.rows?.length ? parsedFromOCR.rows
              : parsedFromQR?.rows?.length ? parsedFromQR.rows
              : []);

    lastParsedMeta = mergedMeta;
    lastParsedRows = rows;

    fillExtractedFields(lastParsedMeta);
    renderGradesTable(lastParsedRows, lastParsedMeta);
    bumpPathsFeedback?.();
    await autoSaveCog(lastParsedMeta, lastParsedRows);
    return;
  }

  const meta = mergeMetaPreferFilled(sanitizeMeta(parsedFromQR.meta||{}), sanitizeMeta(parsedFromOCR.meta||{}));
  const rows = parsedFromOCR.rows?.length ? parsedFromOCR.rows : parsedFromQR.rows || [];
  lastParsedMeta = meta; lastParsedRows = rows;
  fillExtractedFields(meta); renderGradesTable(rows, meta);
  await autoSaveCog(meta, rows);
}

/* ---------- Validation helpers ---------- */
function setValState(id, state, extra=''){
  const el = document.getElementById(id); if (!el) return;
  if (state==='loading'){ el.innerHTML = `<span class="spinner"></span><span class="val-muted">Checking…</span>`; return; }
  if (state==='ok'){ el.innerHTML = `✅ <span class="val-ok">Passed</span>` + (extra?` <small class="val-muted">(${extra})</small>`:''); return; }
  if (state==='fail'){ el.innerHTML = `❌ <span class="val-fail">Failed</span>` + (extra?` <small class="val-muted">(${extra})</small>`:''); }
}

function canonicalKeyFrom(code='', title=''){
  const c=(code||'').toUpperCase().replace(/\s+/g,' ').trim();
  const t=(title||'').toUpperCase().replace(/\s+/g,' ').replace(/[^A-Z0-9 ]/g,'').trim();
  return (c||'x') + '||' + t;
}
function buildMismatchIndex(mismatches=[]){
  mismatchIndex = new Map();
  (mismatches||[]).forEach(m=>{
    mismatchIndex.set(m._key || canonicalKeyFrom(m.code, m.title), { qr: m.qr, ocr: m.ocr });
  });
}

const DISQUAL_GRADE_MIN = 2.75;

function diffQrOcrRows(qrRows = [], ocrRows = []) {
  const canon = s => (s||'').toUpperCase().replace(/\s+/g,' ').trim();
  const gradeNorm = (g='') => {
    const t = String(g).replace(/\s+/g,'').trim().toUpperCase();
    if (t === '100') return '1.00';
    if (t === '125') return '1.25';
    if (t === '150') return '1.50';
    if (t === '175') return '1.75';
    if (t === '200') return '2.00';
    if (t === 'INCOMPLETE') return 'INC';
    if (t === 'DROP') return 'DRP';
    return t;
  };

  const qrByCode  = new Map();
  const ocrByCode = new Map();
  qrRows.forEach(r  => { const k = canon(r.code); if (k) qrByCode.set(k, r); });
  ocrRows.forEach(r => { const k = canon(r.code); if (k) ocrByCode.set(k, r); });

  const mismatches = [];
  ocrByCode.forEach((ocr, k) => {
    const qr = qrByCode.get(k);
    if (!qr) return;
    const qg = gradeNorm(qr.grade);
    const og = gradeNorm(ocr.grade);
    if (qg && og && qg !== og) {
      mismatches.push({
        code: qr.code || ocr.code || '',
        title: qr.title || ocr.title || '',
        qr: qg,
        ocr: og,
        _key: canonicalKeyFrom(qr.code||ocr.code||'', qr.title||ocr.title||'')
      });
    }
  });

  const missingInOCR = [];
  qrByCode.forEach((qr, k) => {
    if (!ocrByCode.has(k)) missingInOCR.push(qr);
  });

  const extraInOCR = [];
  ocrByCode.forEach((ocr, k) => {
    if (!qrByCode.has(k)) extraInOCR.push(ocr);
  });

  return { mismatches, missingInOCR, extraInOCR };
}

function formatMismatchLines(mismatches=[]){
  return '<ul style="margin-left:1rem">' + mismatches.map(m =>
    `<li><strong>${escapeHtml(m.code || '')}</strong> — ${escapeHtml(m.title || '')}: ` +
    `QR = <strong>${escapeHtml(m.qr)}</strong>, Uploaded = <strong>${escapeHtml(m.ocr)}</strong></li>`
  ).join('') + '</ul>';
}

async function waitUntil(pred, { timeout=3500, interval=120 } = {}) {
  const t0 = Date.now();
  while (!pred()) {
    if (Date.now() - t0 > timeout) return false;
    await new Promise(r => setTimeout(r, interval));
  }
  return true;
}
const sleep = (ms)=>new Promise(r=>setTimeout(r,ms));

/* ===========================================================
   VALIDATION HELPERS - SIMPLIFIED COURSE MISMATCH DISPLAY
   =========================================================== */

// Function to fetch and parse mismatches from grades_mismatches.txt
async function fetchGradeMismatches() {
    try {
        // Try to fetch from grades_mismatches.txt
        const response = await fetch('/storage/app/cog/grades_mismatches.txt');
        if (response.ok) {
            const mismatchesText = await response.text();
            return parseMismatchesFromText(mismatchesText);
        }
    } catch (error) {
        console.log('No grades_mismatches.txt found:', error);
    }
    return [];
}

// Parse mismatches from grades_mismatches.txt - only get course codes
function parseMismatchesFromText(mismatchesText) {
    const mismatches = [];
    const lines = mismatchesText.split('\n').filter(line => line.trim());
    
    lines.forEach(line => {
        // Extract just the course code from formats like:
        // "IT 221 - QR: 1.25, OCR: 3.00" or "IT 221"
        const match = line.match(/([A-Za-z]+\s+\d+)/);
        if (match) {
            mismatches.push(match[1].trim());
        }
    });
    
    return mismatches;
}

// Function to format mismatch message in the simple format you want
function formatMismatchMessage(mismatchCourses) {
    if (!mismatchCourses || mismatchCourses.length === 0) {
        return 'No specific course mismatches detected.';
    }
    
    // Create simple messages like "Your Grades in IT 221 is not Match"
    return mismatchCourses.map(course => 
        `Your Grades in ${escapeHtml(course)} is not Match`
    ).join('<br>');
}

/* ===========================================================
   VALIDATION HELPERS - COR vs COG COURSE CODE MATCHING
   =========================================================== */

// Function to fetch and parse course codes from COR output
async function fetchCorCourses() {
  try {
    const url = window.COR_OUTPUT_URL || '/student/cor/output';
    const response = await fetch(url);
    if (response.ok) {
      const corText = await response.text();
      return parseCorCourses(corText);
    }
  } catch (error) {
    console.log('Could not fetch COR output:', error);
  }
  return [];
}

async function fetchCogCourses() {
  try {
    const url = window.COG_OUTPUT_URL || '/student/cog/output.txt';
    const response = await fetch(url);
    if (response.ok) {
      const cogText = await response.text();
      return parseCogCourses(cogText);
    }
  } catch (error) {
    console.log('Could not fetch COG output:', error);
  }
  return [];
}


function parseCorCourses(corText) {
  const lines = corText.split('\n');
  const coursesSet = new Set();
  let inCourses = false;

  for (const rawLine of lines) {
    const line = rawLine.trim();
    if (!line) continue;

    if (line.startsWith('COURSE CODE')) {
      inCourses = true;
      continue;
    }

    if (inCourses) {
      if (
        line.startsWith('Scholarship/s:') ||
        line.startsWith('ASSESSMENT') ||
        line.startsWith('Approved by:')
      ) {
        break;
      }

      const m = line.match(/^([A-Za-z]{2,}\s*\d{3})/);
      if (m) {
        const code = m[1].replace(/\s+/g, ' ').toUpperCase();
        coursesSet.add(code);
      }
    }
  }

  return Array.from(coursesSet).sort();
}

function parseCogCourses(cogText) {
  const lines = cogText.split('\n');
  const coursesSet = new Set();

  // From COURSES: table
  let inCoursesSection = false;
  for (const rawLine of lines) {
    const line = rawLine.trim();
    if (!line) continue;

    if (line.startsWith('COURSES:')) {
      inCoursesSection = true;
      continue;
    }

    if (inCoursesSection) {
      if (line.startsWith('SUMMARY:') || line.startsWith('RAW EXTRACTED TEXT:')) {
        inCoursesSection = false;
        continue;
      }

      if (line.includes('|')) {
        const firstPart = line.split('|')[0].trim();
        if (/^[A-Za-z]{2,}\s*\d{3}$/.test(firstPart)) {
          const code = firstPart.replace(/\s+/g, ' ').toUpperCase();
          coursesSet.add(code);
        }
      }
    }
  }

  // Backup: RAW EXTRACTED TEXT block
  for (const rawLine of lines) {
    const line = rawLine.trim();
    const m = line.match(/^\d+\s+([A-Za-z]{2,}\s*\d{3})\b/);
    if (m) {
      const code = m[1].replace(/\s+/g, ' ').toUpperCase();
      coursesSet.add(code);
    }
  }

  return Array.from(coursesSet).sort();
}

function findCourseMismatches(corCourses, cogCourses) {
  const mismatches = [];
  const corSet = new Set(corCourses);
  const cogSet = new Set(cogCourses);

  corCourses.forEach(code => {
    if (!cogSet.has(code)) {
      mismatches.push({
        course: code,
        type: 'missing_in_cog'
      });
    }
  });

  cogCourses.forEach(code => {
    if (!corSet.has(code)) {
      mismatches.push({
        course: code,
        type: 'missing_in_cor'
      });
    }
  });

  return mismatches;
}

async function runValidations() {
  setValState('v-tamper', 'loading', 'Comparing grades…');
  setValState('v-irregular', 'loading', 'Checking curriculum…');
  setValState('v-grades', 'loading', 'Scanning disqualifying grades…');

  try {
    // wait for background OCR / parsing work
    if (window.cogWorkDonePromise) {
      try { await window.cogWorkDonePromise; } catch (_) {}
    }
    if (typeof ocrReady !== 'undefined' && ocrReady?.then) {
      try { await ocrReady; } catch (_) {}
    }

    /* =======================================================
       1) COR vs COG COURSE CODE MATCHING (FIRST GATE)
       ======================================================= */
    const corCourses = await fetchCorCourses();
    const cogCourses = await fetchCogCourses();

    console.log('COR Courses:', corCourses);
    console.log('COG Courses:', cogCourses);

    if (corCourses.length > 0 && cogCourses.length > 0) {
      const courseMismatches = findCourseMismatches(corCourses, cogCourses);

      if (courseMismatches.length > 0) {
        // FAIL in Document Authenticity
        setValState('v-tamper', 'fail', 'Course mismatch detected');

        const reasonEl = document.getElementById('tamperReason');
        if (reasonEl) {
          reasonEl.innerHTML = `
            <p class="mb-2">
              <strong>The Course in your COR and COG do not Match.</strong>
            </p>
            <p class="small text-muted mb-0">
              The system detected that the list of courses in your
              Certificate of Registration (COR) is different from the list
              of courses in your Certificate of Grades (COG).
            </p>
          `;
        }

        showModal('#tamperFailModal');
        validationPass = false;
        return; // stop here; do not continue to QR/OCR grade checks
      }
    }

    /* =======================================================
       2) EXISTING QR vs OCR / PARSED FILES VALIDATION
          (HINDI KO TINANGGAL, 그대로)
       ======================================================= */

    const qrRows  = Array.isArray(parsedFromQR?.rows)  ? parsedFromQR.rows  : [];
    const ocrRows = Array.isArray(parsedFromOCR?.rows) ? parsedFromOCR.rows : [];

    let decidedByParsedFiles = false;
    try {
      const { qrGrades, ocrGrades } = await fetchParsedFilesGrades();
      if (qrGrades.length || ocrGrades.length) {
        decidedByParsedFiles = true;

        const A = qrGrades, B = ocrGrades;
        const N = Math.max(A.length, B.length);
        const rowMismatches = [];
        for (let i = 0; i < N; i++) {
          if ((A[i] || '') !== (B[i] || '')) {
            rowMismatches.push({ index: i + 1, qr: A[i] || '—', ocr: B[i] || '—' });
          }
        }

        if (rowMismatches.length > 0) {
          setValState('v-tamper', 'fail', `${rowMismatches.length} grade mismatch(es)`);

          const reasonEl = document.getElementById('tamperReason');
          if (reasonEl) {
            reasonEl.innerHTML = `Found ${rowMismatches.length} grade discrepancy(ies) between official records and your uploaded document.`;
          }

          showModal('#tamperFailModal');
          validationPass = false;
          return;
        } else {
          setValState('v-tamper', 'ok', 'Parsed files match');
        }
      }
    } catch (_) {}

    if (!decidedByParsedFiles) {
      const hasQR  = qrRows.length > 0;
      const hasOCR = ocrRows.length > 0;

      if (!hasOCR) {
        if (hasQR) {
          setValState('v-tamper', 'ok', 'Verified via QR only');
          setValState('v-irregular', 'ok', 'Checked');
          setValState('v-grades', 'ok', 'No disqualifying grades found');

          lastParsedRows = qrRows.slice();
          lastParsedMeta = mergeMetaPreferFilled(parsedFromQR.meta || {}, parsedFromOCR.meta || {});
          buildMismatchIndex([]);
          renderGradesTable(lastParsedRows, lastParsedMeta);
          await autoSaveCog(lastParsedMeta, lastParsedRows);

          validationPass = true;
          return;
        }
        setValState('v-tamper', 'fail', 'No QR/OCR data');
        const reasonEl = document.getElementById('tamperReason');
        if (reasonEl) {
          reasonEl.innerHTML = `We couldn't extract any grade rows from your uploaded COG and no QR data was found.`;
        }
        tamperTarget = 'cog';
        showModal('#tamperFailModal');
        validationPass = false;
        return;
      }

      const diff = diffQrOcrRows(qrRows, ocrRows);
      const mismatches = diff.mismatches || [];

      if (mismatches.length > 0) {
        setValState('v-tamper', 'fail', `${mismatches.length} mismatched grade(s)`);

        const reasonEl = document.getElementById('tamperReason');
        if (reasonEl) {
          reasonEl.innerHTML = `Found ${mismatches.length} grade discrepancy(ies) between official records and your uploaded document.`;
        }

        showModal('#tamperFailModal');
        validationPass = false;
        return;
      } else {
        setValState('v-tamper', 'ok', 'QR and OCR grades match');
      }
      buildMismatchIndex(mismatches);
    }

    /* =======================================================
       3) DISQUALIFYING GRADES (2.75 / 3.00 / INC / DRP)
       ======================================================= */

    const disqual = (Array.isArray(parsedFromOCR?.rows) ? parsedFromOCR.rows : []).some(r => {
      const g = String(r.grade || '').toUpperCase().trim();
      return g === 'INC' || g === 'DRP' || g === 'W' || parseFloat(g) >= DISQUAL_GRADE_MIN;
    });
    if (disqual) setValState('v-grades', 'fail', 'Found 2.75 / 3.00 / INC / DROP');
    else setValState('v-grades', 'ok', 'No disqualifying grades');

    /* =======================================================
       4) IRREGULAR CHECK (currently static)
       ======================================================= */

    const isIrregular = false;
    if (isIrregular) setValState('v-irregular', 'fail', 'Sequence mismatch');
    else setValState('v-irregular', 'ok', 'Checked');

    /* =======================================================
       5) MERGE META + RENDER + SAVE
       ======================================================= */

    lastParsedMeta = mergeMetaPreferFilled(
      mergeMetaPreferFilled(parsedFromQR?.meta || {}, parsedFromOCR?.meta || {}),
      window.corMetaFromOcr || {}
    );
    lastParsedRows = (parsedFromOCR?.rows || []).length ? parsedFromOCR.rows.slice() : qrRows.slice();

    renderGradesTable(lastParsedRows, lastParsedMeta);
    await autoSaveCog(lastParsedMeta, lastParsedRows);

    validationPass = !disqual;
  } catch (e) {
    console.error('Validation error', e);
    setValState('v-tamper', 'fail', 'Unexpected error');
    validationPass = false;
  }
}


async function fetchCogOutput() {
  const url = route('route-cog-output-file');
  const res = await fetch(url, { headers: { 'Accept': 'text/plain,*/*' }});
  return res.ok ? await res.text() : '';
}

function parseCoursesData(textData) {
  const lines = textData.split('\n');
  const courses = [];
  lines.forEach(line => {
    const match = line.match(/^(\d+)\s+([A-Za-z0-9\s/-]+)\s+(.+?)\s+(\d+)\s+([\d.]+)\s+(.+)\s+(.+)$/);
    if (match) {
      courses.push({
        idx: match[1],
        code: match[2],
        title: match[3],
        units: match[4],
        grade: match[5],
        section: match[6],
        instructor: match[7]
      });
    }
  });
  return courses;
}
function normalizeGrade(grade) {
  if (!grade) return '';
  return parseFloat(grade.trim()).toFixed(2);
}
function compareCourses(ocrCourses, outputCourses) {
  const mismatches = [];
  ocrCourses.forEach((ocrCourse, index) => {
    const outputCourse = outputCourses[index];
    if (ocrCourse && outputCourse) {
      const ocrGrade = normalizeGrade(ocrCourse.grade);
      const outputGrade = normalizeGrade(outputCourse.grade);
      if (ocrGrade !== outputGrade) {
        mismatches.push({
          index: index + 1,
          ocrGrade: ocrGrade,
          outputGrade: outputGrade,
          courseCode: ocrCourse.code,
          courseTitle: ocrCourse.title
        });
      }
    }
  });
  return mismatches;
}
function showDocumentAuthenticationFailure(mismatches) {
  const mismatchList = mismatches.map(mismatch => {
    return `
      <li>
        Course: ${mismatch.courseCode} - ${mismatch.courseTitle}<br>
        OCR Grade: ${mismatch.ocrGrade}, Output Grade: ${mismatch.outputGrade}
      </li>
    `;
  }).join('');
  const failureMessage = `
      <h5 class="text-danger">Document Authentication Failed</h5>
      <p>The grades do not match for the following courses:</p>
      <ul>${mismatchList}</ul>
      <p>Please contact the registrar or re-upload the correct document.</p>
  `;
  document.getElementById('tamperReason').innerHTML = failureMessage;
}

/* ===========================================================
   5) UI fill + table render
   =========================================================== */
function setIfEmpty(id, value){ const el=document.getElementById(id); if(!el) return; if(!el.value) el.value=value; }
function fillExtractedFields(meta){
  const set = (id, v) => { const el=document.getElementById(id); if (el) el.value=(v||''); };
  set('fullname', (meta.fullname||'').replace(/\s{2,}/g,' ').trim());
  set('srcode', meta.srcode||'');
  set('college', meta.college||'');
  set('academic_year', meta.academic_year||'');
  set('program', meta.program||'');
  set('semester', meta.semester||'');
  set('year_level', meta.year_level||'');
}
function escapeHtml(s=''){ return s.replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

function renderGradesTable(rows, meta){
  const tb=document.getElementById('grade-table-body');
  const sumCourses=document.getElementById('sum-courses');
  const sumUnits=document.getElementById('sum-units');
  const sumGwa=document.getElementById('sum-gwa');

  if (!rows.length){
    tb.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No rows detected. Please re-upload a clearer image.</td></tr>';
    sumCourses.textContent = meta?.total_courses || '—';
    sumUnits.textContent   = meta?.total_units   || '—';
    sumGwa.textContent     = meta?.gwa           || '—';
    return;
  }

  const bodyHtml = rows.map(r => {
    const key = canonicalKeyFrom(r.code, r.title);
    const mm  = mismatchIndex.get(key);
    const gradeHtml = mm
      ? `${escapeHtml(r.grade||'')} <span class="qr-grade-badge" title="Official QR grade">QR: ${escapeHtml(mm.qr)}</span>`
      : `${escapeHtml(r.grade||'')}`;

    return `
      <tr class="${mm ? 'tampered-row' : ''}">
        <td class="text-center locked-cell">${r.idx}</td>
        <td class="locked-cell">${escapeHtml(r.code||'')}</td>
        <td class="locked-cell">${escapeHtml(r.title||'')}</td>
        <td class="text-center locked-cell">${escapeHtml(r.units||'')}</td>
        <td class="text-center locked-cell">${gradeHtml}</td>
        <td class="text-center locked-cell">${escapeHtml(r.section||'')}</td>
        <td class="locked-cell">${escapeHtml(r.instructor||'')}</td>
      </tr>`;
  }).join('');

  const nothingRow = `<tr class="nothing-row"><td colspan="7">** NOTHING FOLLOWS **</td></tr>`;
  tb.innerHTML = bodyHtml + nothingRow;

  const totalUnits   = meta?.total_units   ? Number(meta.total_units)   : rows.reduce((a,r)=>a+(parseFloat(r.units)||0),0);
  const totalCourses = meta?.total_courses ? Number(meta.total_courses) : rows.length;

  let sum=0,u=0; rows.forEach(r=>{ const unit=parseFloat(r.units); const g=parseFloat((r.grade||'').replace(/[^0-9.]/g,'')); if(Number.isFinite(unit)&&Number.isFinite(g)){ sum+=unit*g; u+=unit; } });
  const gwa = u>0 ? (sum/u).toFixed(4) : (meta?.gwa || '');

  sumCourses.textContent = Number.isFinite(totalCourses) ? totalCourses : rows.length;
  sumUnits.textContent   = Number.isFinite(totalUnits)   ? totalUnits   : '—';
  sumGwa.textContent     = gwa || '—';
}

/* ===========================================================
   6) COR helpers (render + OCR + save)
   =========================================================== */
async function renderPdfFirstPageToCanvas(file){
  const buf   = await file.arrayBuffer();
  const pdf   = await pdfjsLib.getDocument({ data: buf }).promise;
  const page  = await pdf.getPage(1);
  const vport = page.getViewport({ scale: 2.0 });
  const c = document.createElement('canvas');
  c.width = vport.width; c.height = vport.height;
  const ctx = c.getContext('2d', { willReadFrequently:true });
  await page.render({ canvasContext: ctx, viewport: vport }).promise;
  return c;
}
async function qrFromCanvas(canvas){
  try { const res = await QrScanner.scanImage(canvas, { returnDetailedScanResult:true, inversionAttempts:'attemptBoth' }); if (res && res.data) return String(res.data); } catch {}
  try {
    const reader = new ZXing.BrowserQRCodeReader();
    const imgEl = new Image();
    imgEl.src = canvas.toDataURL('image/png');
    await imgEl.decode?.();
    const res = await reader.decodeFromImage(imgEl);
    if (res && res.text) return String(res.text);
  } catch {}
  try { const g = canvas.getContext('2d', { willReadFrequently:true }); const id = g.getImageData(0,0,canvas.width,canvas.height); const code = jsQR(id.data, canvas.width, canvas.height); if (code && code.data) return String(code.data); } catch {}
  return '';
}
function parseCorHeaderFromText(textRaw){
  const lines = (textRaw || '').replace(/\r/g,'').split('\n').map(s=>s.trim()).filter(Boolean);
  const get = (rx) => { const i = lines.findIndex(l => rx.test(l)); if (i === -1) return ''; return lines[i].replace(/^[^:]+:\s*/i,'').trim(); };
  return { fullname:get(/Full\s*name|Fullname/i), srcode:get(/SRCODE|SR\s*CODE|SR-CODE/i), college:get(/College/i), program:get(/Program/i), semester:get(/Semester/i), year_level:get(/Year\s*Level|YearLevel/i), academic_year:get(/Academic\s*Year|AcademicYear/i) };
}
function buildCorPlainText(meta, ocrText){
  const v = x => (x==null || x==='') ? '' : String(x);
  const L = [];
  L.push(`Fullname       : ${v(meta.fullname)}`);
  L.push(`SRCODE         : ${v(meta.srcode)}`);
  L.push(`College        : ${v(meta.college)}`);
  L.push(`Program        : ${v(meta.program)}`);
  L.push(`Semester       : ${v(meta.semester)}`);
  L.push(`Year Level     : ${v(meta.year_level)}`);
  L.push(`Academic Year  : ${v(meta.academic_year)}`);
  L.push('');
  L.push('----- OCR TEXT (raw) -----');
  L.push((ocrText||'').trim());
  return L.join('\n');
}
async function extractCorPdf(){
  try{
    const input = document.getElementById('file-cor');
    const file  = input?.files?.[0];
    if (!file) return;

    const canvas = await renderPdfFirstPageToCanvas(file);
    window.lastCorQr = await qrFromCanvas(canvas) || '';

    let ocrText = '';
    try {
      ocrText = await tesseractRecognizeSafe(canvas, 'eng', {
        ...TESS_OPTS,
        tessedit_char_blacklist: '[]{}<>~`^'
      });
    } catch(e) {
      console.error('COR OCR failed', e);
    }

    const meta = parseCorHeaderFromText(ocrText);
    window.corMetaFromOcr = (meta || {});

    const finalText = buildCorPlainText(meta, ocrText);

    const url = route('route-save-cor-output');
    if (url) {
      await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept':'application/json' },
        body: JSON.stringify({ raw_text: finalText })
      });
    }

    const el = document.getElementById('cor-status');
    if (el) el.textContent = 'COR extracted and saved.';
  }catch(e){
    console.error('COR extract failed', e);
    const el = document.getElementById('cor-status');
    if (el) el.textContent = 'COR extraction failed (try a clearer PDF).';
  }
}

/* ===========================================================
   7) BUILD TEXT BLOCKS (COG) + SAVE
   =========================================================== */
function buildHeaderBlock(meta){
  const v = x => (x==null || x==='') ? '' : String(x);
  const L = [];
  L.push('BATANGAS STATE UNIVERSITY');
  L.push('ARASOF-Nasugbu Campus');
  L.push('');
  L.push("Student's Copy of Grades");
  L.push('');
  L.push(`Fullname : ${v(meta.fullname)}`);
  L.push(`SRCODE : ${v(meta.srcode)}`);
  L.push(`College : ${v(meta.college)}`);
  L.push(`Program : ${v(meta.program)}`);
  L.push(`Year Level : ${v(meta.year_level)}`);
  L.push(`Academic Year : ${v(meta.academic_year)}`);
  L.push(`Semester : ${v(meta.semester)}`);
  L.push('');
  return L.join('\n');
}
function buildTableBlock(rows){
  const v = x => (x==null || x==='') ? '' : String(x);
  const L = [];
  L.push('| # | Course Code | Course Title | Units | Grade | Section | Instructor |');
  L.push('|---|-------------|--------------|-------|-------|---------|------------|');
  rows.forEach(r=> L.push(`| ${v(r.idx)} | ${v(r.code)} | ${v(r.title)} | ${v(r.units)} | ${v(r.grade)} | ${v(r.section)} | ${v(r.instructor)} |`));
  L.push('');
  return L.join('\n');
}
function buildTotalsBlock(metaOrTotals, rows){
  const v = x => (x==null || x==='') ? '' : String(x);
  const totalCourses = v(metaOrTotals.total_courses || rows.length);
  const totalUnits   = v(metaOrTotals.total_units || rows.reduce((a,r)=>a+(parseFloat(r.units)||0),0));
  let sum=0,u=0; rows.forEach(r=>{ const uu=parseFloat(r.units); const g=parseFloat((r.grade||'').replace(/[^0-9.]/g,'')); if(Number.isFinite(uu)&&Number.isFinite(g)){ sum+=uu*g; u+=uu; } });
  const gwa = v(metaOrTotals.gwa || (u>0?(sum/u).toFixed(4):''));
  const L = [];
  L.push(`Total no of Course : ${totalCourses}`);
  L.push(`Total no of Units  : ${totalUnits}`);
  L.push(`General Weighted Average (GWA) : ${gwa}`);
  return L.join('\n');
}
function buildQrFullSection(qrMeta, qrRows){
  const head = buildHeaderBlock(qrMeta);
  const table = buildTableBlock(qrRows);
  const totals = buildTotalsBlock(qrMeta, qrRows);
  return `${head}${table}${totals}`;
}
function buildOcrFullSection(ocrMeta, ocrRows){
  const head = buildHeaderBlock(ocrMeta);
  const table = buildTableBlock(ocrRows);
  const totals = buildTotalsBlock(ocrMeta, ocrRows);
  return `${head}${table}${totals}`;
}

async function autoSaveCog(meta, rows){
  const qrMeta  = sanitizeMeta(parsedFromQR?.meta  || {});
  const ocrMeta = sanitizeMeta(parsedFromOCR?.meta || {});
  const corMeta = sanitizeMeta(window.corMetaFromOcr || {});
  const uiMeta  = sanitizeMeta(meta || {});
  const finalMeta = mergeMetaPreferFilled(
    mergeMetaPreferFilled(
      mergeMetaPreferFilled(qrMeta, ocrMeta),
      corMeta
    ),
    uiMeta
  );

  const ocrRows = parsedFromOCR?.rows?.length ? parsedFromOCR.rows : (rows || []);

  try{
    await fetch(route('route-save-cog-output'), {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'Accept':'application/json'
      },
      body: JSON.stringify({
        meta: finalMeta,
        rows: ocrRows,
        totals: {
          total_units: ocrRows.reduce((a,r)=>a+(parseFloat(r.units)||0),0),
          gwa: (()=>{ let sum=0,u=0;
            ocrRows.forEach(r=>{ const uu=parseFloat(r.units);
              const g=parseFloat(String(r.grade||'').replace(/[^0-9.]/g,''));
              if(Number.isFinite(uu)&&Number.isFinite(g)){ sum+=uu*g; u+=uu; }});
            return u>0 ? (sum/u).toFixed(4) : '';
          })()
        },
        qr_raw:   (window.lastQrRawText || ''),
        qr_url:   (window.lastQrUrl || ''),   // persist registrar link
        pdf_text: (window.lastOcrRawText || ''),
        ocr_full: (window.lastOcrRawText || '')
      })
    });

    const ok = document.getElementById('save-feedback');
    if (ok) { ok.style.display='inline'; setTimeout(()=> ok.style.display='none', 2000); }
  }catch(e){
    console.warn('autoSaveCog failed', e);
  }
}

/* ===========================================================
   8) PDF + submit
   =========================================================== */
async function generatePdf() {
  const status = document.getElementById('pdf-status');
  const btn = document.getElementById('pdf-generate-btn');
  const iframe = document.getElementById('pdf-frame');
  const openA = document.getElementById('pdf-open-link');

  const { rows, meta, total_units, gwa } = collectCurrentStateFromUI();

  // DEBUG: Log what paths we're sending
  console.log('=== PDF GENERATION DEBUG ===');
  console.log('COR path from serverPaths:', serverPaths.cor_img);
  console.log('COG path from serverPaths:', serverPaths.cog_img);
  console.log('COR exists in serverPaths:', !!serverPaths.cor_img);
  console.log('COG exists in serverPaths:', !!serverPaths.cog_img);

  // Use the exact paths that were set during upload
  const payload = {
    cor_png_path: serverPaths.cor_img || "/home/u780655614/domains/achievemate.website/AchieveMate/AchieveMate/storage/app/cor/cor_upload.png",
    cog_png_path: serverPaths.cog_img || "/home/u780655614/domains/achievemate.website/AchieveMate/AchieveMate/storage/app/cog/cog_upload.png",
    meta,
    rows,
    totals: { total_units, gwa }
  };

  console.log('Final payload being sent:', payload);

  const pdfUrl = '/student/pdf/generate-json';
  const csrf   = csrfToken();

  if (btn) btn.disabled = true;
  if (status) status.textContent = 'Generating PDF...';

  try {
    console.log('Sending PDF generation request to:', pdfUrl);
    const res = await fetch(pdfUrl, { 
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json', 
        'X-CSRF-TOKEN': csrf, 
        'Accept': 'application/json' 
      },
      body: JSON.stringify(payload)
    });
    
    console.log('Response status:', res.status);
    const json = await res.json();
    console.log('Response JSON:', json);
    
    if (!res.ok || !(json && (json.ok || json.public_url || json.url || json.path))) {
      const msg = (json && (json.message || json.error)) ? ` (${json.message || json.error})` : '';
      throw new Error(`Server error ${res.status}${msg}`);
    }

    let url = (json.public_url || json.url || '').toString().trim();
    if (!url && json.path) {
      const p = json.path.replace(/\\\\/g,'/').replace(/\\/g,'/');
      const anchor = '/storage/app/public/';
      const i = p.lastIndexOf(anchor);
      if (i !== -1) url = '/storage/' + p.substring(i + anchor.length);
    }
    if (!url) url = '/storage/pdf_output/filled_dean_form.pdf';

    const finalUrl = url + (url.includes('?') ? '&' : '?') + 'v=' + Date.now();
    if (iframe) iframe.src = finalUrl;
    if (openA) { openA.href = finalUrl; openA.style.display = 'inline'; }
    if (status) status.textContent = 'PDF generated successfully!';
    
    console.log('PDF generated successfully:', finalUrl);
  } catch (e) {
    console.error('PDF generation failed:', e);
    if (status) status.textContent = `Failed to generate PDF: ${e.message || e}`;
  } finally { 
    if (btn) btn.disabled = false; 
  }
}

function collectCurrentStateFromUI(){
  const rows=[]; document.querySelectorAll('#extracted-grade-table tbody tr').forEach(tr=>{
    const t=tr.querySelectorAll('td'); if(t.length<7) return; const maybeIdx=t[0].textContent.trim(); if(maybeIdx.includes('NOTHING FOLLOWS')) return;
    rows.push({ idx:maybeIdx, code:t[1].textContent.trim(), title:t[2].textContent.trim(), units:t[3].textContent.trim(), grade:t[4].textContent.trim(), section:t[5].textContent.trim(), instructor:t[6].textContent.trim() });
  });
  const byId=id=>document.getElementById(id)?.value?.trim()||'';
  const meta={ fullname:byId('fullname'), srcode:byId('srcode'), college:byId('college'), academic_year:byId('academic_year'), program:byId('program'), semester:byId('semester'), year_level:byId('year_level') };
  const total_units=rows.reduce((a,r)=>a+(parseFloat(r.units)||0),0);
  let sum=0,u=0; rows.forEach(r=>{ const unit=parseFloat(r.units); const g=parseFloat((r.grade||'').replace(/[^0-9.]/g,'')); if(Number.isFinite(unit)&&Number.isFinite(g)){ sum+=unit*g; u+=unit; } });
  const gwa=u>0?(sum/u).toFixed(4):''; return { rows, meta, total_units, gwa };
}
function showConfirmSubmitModal(){
  const yesBtn = document.getElementById('confirmSubmitYesBtn');
  const modal  = showModal('#confirmSubmitModal');
  yesBtn.onclick = async () => {
    yesBtn.disabled = true;
    try {
      await submitApplication();
      const inst = bootstrap.Modal.getInstance(document.getElementById('confirmSubmitModal'));
      inst?.hide();
    } finally { yesBtn.disabled = false; }
  };
}
async function validateApplicationPeriod() {
    try {
        console.log('Starting application period validation...');
        
        // Get the validation route
        const validationRoute = route('route-validate-application-period');
        console.log('Validation route:', validationRoute);
        
        if (!validationRoute) {
            console.error('Validation route not found');
            return {
                valid: false,
                message: 'Validation route not configured.',
                canProceed: false,
                postId: null
            };
        }

        // Call the server-side validation directly
        const validationResponse = await fetch(validationRoute, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({}) // No need to send academic_year/semester - server will extract from COG
        });
        
        console.log('Validation response status:', validationResponse.status);
        
        const validationResult = await validationResponse.json();
        console.log('Validation result:', validationResult);
        
        // Store the matching Post_id globally
        if (validationResult.valid && validationResult.post_id) {
            matchingPostId = validationResult.post_id;
            console.log('Matching post ID set:', matchingPostId);
        }
        
        return {
            valid: validationResult.valid,
            message: validationResult.message,
            canProceed: validationResult.valid,
            academicYear: validationResult.post?.academic_year || '',
            semester: validationResult.post?.semester || '',
            postId: validationResult.post_id
        };
        
    } catch (error) {
        console.error('Application period validation failed:', error);
        return {
            valid: false,
            message: 'Failed to validate application period. Please try again.',
            canProceed: false,
            postId: null
        };
    }
}
/* ===== Modal for application period issues ===== */
function showApplicationPeriodModal(message) {
    const messageEl = document.getElementById('applicationPeriodMessage');
    if (messageEl) {
        messageEl.textContent = message;
    }
    showModal('#applicationPeriodModal');
}

async function runValidations() {
    try {
        setBusy(true);

        // NEW: Add application period validation FIRST
        setValState('v-period', 'loading', 'Checking application period…');
        const periodValidation = await validateApplicationPeriod();
        
        console.log('Period validation result:', periodValidation);
        
        if (!periodValidation.valid) {
            setValState('v-period', 'fail', periodValidation.message);
            validationPass = false;
            
            // Show modal about application period mismatch
            showApplicationPeriodModal(periodValidation.message);
            setBusy(false);
            return;
        } else {
            setValState('v-period', 'ok', `Matches ${periodValidation.semester} Semester, AY ${periodValidation.academicYear}`);
        }

        // Wait for COG processing if needed
        if (window.cogWorkDonePromise) {
            setValState('v-tamper', 'loading', 'Waiting for OCR/QR…');
            try { 
                await window.cogWorkDonePromise; 
            } catch(_) {
                console.warn('COG work promise failed');
            }
        }

        // Continue with extraction and other validations
        await extractFromCog();
        await runOtherValidations();
        
    } catch(e) {
        console.error('Validation failed:', e);
        alert('Validation failed to run. Please try re-uploading a clearer COG image.');
    } finally {
        setBusy(false);
    }
}

// Add this debug function to test the validation directly
async function testValidationDirectly() {
    console.log('=== Testing Validation Directly ===');
    
    const validationRoute = route('route-validate-application-period');
    console.log('Route:', validationRoute);
    
    try {
        const response = await fetch(validationRoute, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({})
        });
        
        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('Full response:', result);
        
        return result;
    } catch (error) {
        console.error('Test failed:', error);
        return { error: error.message };
    }
}

// You can call this from browser console to test: testValidationDirectly()

/* ===== Update the existing runValidations to runOtherValidations ===== */
async function runOtherValidations() {
    setValState('v-tamper', 'loading', 'Comparing grades…');
    setValState('v-irregular', 'loading', 'Checking curriculum…');
    setValState('v-grades', 'loading', 'Scanning disqualifying grades…');

    try {
        // Your existing validation logic here...
        // This is the content of your current runValidations function
        // but without the application period check
        
        // COR vs COG COURSE CODE MATCHING
        const corCourses = await fetchCorCourses();
        const cogCourses = await fetchCogCourses();

        console.log('COR Courses:', corCourses);
        console.log('COG Courses:', cogCourses);

        if (corCourses.length > 0 && cogCourses.length > 0) {
            const courseMismatches = findCourseMismatches(corCourses, cogCourses);

            if (courseMismatches.length > 0) {
                setValState('v-tamper', 'fail', 'Course mismatch detected');
                const reasonEl = document.getElementById('tamperReason');
                if (reasonEl) {
                    reasonEl.innerHTML = `
                        <p class="mb-2">
                            <strong>The Course in your COR and COG do not Match.</strong>
                        </p>
                        <p class="small text-muted mb-0">
                            The system detected that the list of courses in your
                            Certificate of Registration (COR) is different from the list
                            of courses in your Certificate of Grades (COG).
                        </p>
                    `;
                }
                showModal('#tamperFailModal');
                validationPass = false;
                return;
            }
        }

        // Continue with QR/OCR validation...
        const qrRows  = Array.isArray(parsedFromQR?.rows)  ? parsedFromQR.rows  : [];
        const ocrRows = Array.isArray(parsedFromOCR?.rows) ? parsedFromOCR.rows : [];

        let decidedByParsedFiles = false;
        try {
            const { qrGrades, ocrGrades } = await fetchParsedFilesGrades();
            if (qrGrades.length || ocrGrades.length) {
                decidedByParsedFiles = true;
                const A = qrGrades, B = ocrGrades;
                const N = Math.max(A.length, B.length);
                const rowMismatches = [];
                for (let i = 0; i < N; i++) {
                    if ((A[i] || '') !== (B[i] || '')) {
                        rowMismatches.push({ index: i + 1, qr: A[i] || '—', ocr: B[i] || '—' });
                    }
                }
                if (rowMismatches.length > 0) {
                    setValState('v-tamper', 'fail', `${rowMismatches.length} grade mismatch(es)`);
                    const reasonEl = document.getElementById('tamperReason');
                    if (reasonEl) {
                        reasonEl.innerHTML = `Found ${rowMismatches.length} grade discrepancy(ies) between official records and your uploaded document.`;
                    }
                    showModal('#tamperFailModal');
                    validationPass = false;
                    return;
                } else {
                    setValState('v-tamper', 'ok', 'Parsed files match');
                }
            }
        } catch (_) {}

        if (!decidedByParsedFiles) {
            const hasQR  = qrRows.length > 0;
            const hasOCR = ocrRows.length > 0;

            if (!hasOCR) {
                if (hasQR) {
                    setValState('v-tamper', 'ok', 'Verified via QR only');
                    setValState('v-irregular', 'ok', 'Checked');
                    setValState('v-grades', 'ok', 'No disqualifying grades found');

                    lastParsedRows = qrRows.slice();
                    lastParsedMeta = mergeMetaPreferFilled(parsedFromQR.meta || {}, parsedFromOCR.meta || {});
                    buildMismatchIndex([]);
                    renderGradesTable(lastParsedRows, lastParsedMeta);
                    await autoSaveCog(lastParsedMeta, lastParsedRows);

                    validationPass = true;
                    return;
                }
                setValState('v-tamper', 'fail', 'No QR/OCR data');
                const reasonEl = document.getElementById('tamperReason');
                if (reasonEl) {
                    reasonEl.innerHTML = `We couldn't extract any grade rows from your uploaded COG and no QR data was found.`;
                }
                tamperTarget = 'cog';
                showModal('#tamperFailModal');
                validationPass = false;
                return;
            }

            const diff = diffQrOcrRows(qrRows, ocrRows);
            const mismatches = diff.mismatches || [];

            if (mismatches.length > 0) {
                setValState('v-tamper', 'fail', `${mismatches.length} mismatched grade(s)`);
                const reasonEl = document.getElementById('tamperReason');
                if (reasonEl) {
                    reasonEl.innerHTML = `Found ${mismatches.length} grade discrepancy(ies) between official records and your uploaded document.`;
                }
                showModal('#tamperFailModal');
                validationPass = false;
                return;
            } else {
                setValState('v-tamper', 'ok', 'QR and OCR grades match');
            }
            buildMismatchIndex(mismatches);
        }

        // DISQUALIFYING GRADES
        const disqual = (Array.isArray(parsedFromOCR?.rows) ? parsedFromOCR.rows : []).some(r => {
            const g = String(r.grade || '').toUpperCase().trim();
            return g === 'INC' || g === 'DRP' || g === 'W' || parseFloat(g) >= DISQUAL_GRADE_MIN;
        });
        if (disqual) setValState('v-grades', 'fail', 'Found 2.75 / 3.00 / INC / DROP');
        else setValState('v-grades', 'ok', 'No disqualifying grades');

        // IRREGULAR CHECK
        const isIrregular = false;
        if (isIrregular) setValState('v-irregular', 'fail', 'Sequence mismatch');
        else setValState('v-irregular', 'ok', 'Checked');

        // MERGE META + RENDER + SAVE
        lastParsedMeta = mergeMetaPreferFilled(
            mergeMetaPreferFilled(parsedFromQR?.meta || {}, parsedFromOCR?.meta || {}),
            window.corMetaFromOcr || {}
        );
        lastParsedRows = (parsedFromOCR?.rows || []).length ? parsedFromOCR.rows.slice() : qrRows.slice();

        renderGradesTable(lastParsedRows, lastParsedMeta);
        await autoSaveCog(lastParsedMeta, lastParsedRows);

        validationPass = !disqual;
        
    } catch (e) {
        console.error('Validation error', e);
        setValState('v-tamper', 'fail', 'Unexpected error');
        validationPass = false;
    }
}

/* ===== Update submitApplication to include Post_id ===== */
async function submitApplication(){
    const state = collectCurrentStateFromUI();
    const gwa   = state.gwa || '';
    const rank  = '';
    const fileInput = document.getElementById('file-grade');
    const file = fileInput?.files?.[0];
    if (!file) { 
        alert('Please upload your COG/grades PDF before submitting.'); 
        return; 
    }

    // Make sure we have a valid Post_id
    if (!matchingPostId) {
        alert('No valid application period found. Please complete validation first.');
        return;
    }

    const formData = new FormData();
    formData.append('type', 'DeanLister');
    formData.append('file_name', file.name);
    formData.append('gwa', gwa);
    formData.append('rank', rank);
    formData.append('post_id', matchingPostId); // Include the Post_id
    formData.append('context', JSON.stringify(state));
    formData.append('file', file);
    formData.append('_token', csrfToken());

    const submitUrl = route('route-application-submit') || '/student/application/submit';

    try {
        const resp = await fetch(submitUrl, { method: 'POST', body: formData });
        const data = await resp.json().catch(()=> ({}));
        if (data.ok) {
            showModal('#successSubmitModal');
        } else {
            alert('Submission failed: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Submission error: ' + (e.message || e));
    }
}

function redirectToStatus(){
  const meta = document.querySelector('meta[name="route-application-status"]');
  const href = meta ? meta.content : '/student/applicationstatus';
  window.location.href = href;
}

/* ===========================================================
   9) COG OUTPUT FETCHING FOR STEP 6 - UPDATED FOR NEW FORMAT
   =========================================================== */

// Function to extract and display the structured grades data from the new format
async function fetchAndDisplayStructuredCogOutput() {
    const tableBody = document.getElementById('grade-table-body');
    const sumCourses = document.getElementById('sum-courses');
    const sumUnits = document.getElementById('sum-units');
    const sumGwa = document.getElementById('sum-gwa');
    
    if (!tableBody) return;

    try {
        const response = await fetch(route('route-cog-output-file'));
        if (response.ok) {
            const cogOutput = await response.text();
            
            // Parse the new format
            const parsedData = parseNewCogOutputFormat(cogOutput);
            
            if (parsedData && parsedData.rows.length > 0) {
                renderParsedDataToTable(parsedData, tableBody, sumCourses, sumUnits, sumGwa);
            } else {
                tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No course data found in output.</td></tr>';
            }
        } else {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Failed to load grades data.</td></tr>';
        }
    } catch (error) {
        console.error('Error fetching COG output:', error);
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Error loading grades data.</td></tr>';
    }
}

// Function to parse the new COG output format
function parseNewCogOutputFormat(fullText) {
    if (!fullText) return null;

    const result = {
        meta: {},
        rows: [],
        totals: {}
    };

    const lines = fullText.split('\n').map(line => line.trim()).filter(line => line);
    
    let currentSection = '';
    let inCoursesSection = false;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        // Detect sections
        if (line.includes('PARSED DATA:')) {
            currentSection = 'parsed';
            continue;
        } else if (line.includes('COURSES:')) {
            currentSection = 'courses';
            inCoursesSection = true;
            continue;
        } else if (line.includes('SUMMARY:')) {
            currentSection = 'summary';
            inCoursesSection = false;
            continue;
        } else if (line.includes('RAW EXTRACTED TEXT:')) {
            currentSection = 'raw';
            break; // Stop parsing after raw text starts
        }

        // Parse based on current section
        if (currentSection === 'parsed') {
            // Parse metadata
            if (line.includes('Fullname:')) {
                result.meta.fullname = line.replace('Fullname:', '').trim();
            } else if (line.includes('SRCODE:')) {
                result.meta.srcode = line.replace('SRCODE:', '').trim();
            } else if (line.includes('College:')) {
                result.meta.college = line.replace('College:', '').trim();
            } else if (line.includes('Academic Year:')) {
                result.meta.academic_year = line.replace('Academic Year:', '').trim();
            } else if (line.includes('Program:')) {
                result.meta.program = line.replace('Program:', '').trim();
            } else if (line.includes('Semester:')) {
                result.meta.semester = line.replace('Semester:', '').trim();
            } else if (line.includes('Year Level:')) {
                result.meta.year_level = line.replace('Year Level:', '').trim();
            }
        } else if (currentSection === 'courses' && inCoursesSection) {
            // Parse course lines in format: "ES 101 | Environmental Sciences | 3 | 1.50 | IT-2203 | MERCADO, ALBERT S."
            if (line.includes('|') && !line.includes('COURSES:')) {
                const parts = line.split('|').map(part => part.trim());
                if (parts.length >= 6) {
                    result.rows.push({
                        idx: result.rows.length + 1,
                        code: parts[0] || '',
                        title: parts[1] || '',
                        units: parts[2] || '',
                        grade: parts[3] || '',
                        section: parts[4] || '',
                        instructor: parts[5] || ''
                    });
                }
            }
        } else if (currentSection === 'summary') {
            // Parse summary information
            if (line.includes('Total Courses:')) {
                result.totals.total_courses = line.replace('Total Courses:', '').trim();
            } else if (line.includes('Total Units:')) {
                result.totals.total_units = line.replace('Total Units:', '').trim();
            } else if (line.includes('GWA:')) {
                result.totals.gwa = line.replace('GWA:', '').trim();
            }
        }
    }

    // If no courses were found in the PARSED DATA section, try parsing from RAW EXTRACTED TEXT
    if (result.rows.length === 0) {
        parseFromRawText(fullText, result);
    }

    return result;
}

// Fallback function to parse from raw text section
function parseFromRawText(fullText, result) {
    const lines = fullText.split('\n');
    let inRawTable = false;
    let courseIndex = 0;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();

        // Start of course table in raw text
        if (line.includes('#Course Code') || line.includes('Course CodeCourse Title')) {
            inRawTable = true;
            continue;
        }

        // Stop at end markers
        if (line.includes('** NOTHING FOLLOWS **') || line.includes('Total no of Course')) {
            inRawTable = false;
        }

        // Parse course lines from raw text
        if (inRawTable && /^\d+\s+[A-Z]/.test(line)) {
            // Match pattern: "1 ES 101 Environmental Sciences 3 1.50 IT-2203 MERCADO, ALBERT S."
            const courseMatch = line.match(/^(\d+)\s+([A-Za-z\s\d]+?)\s+([\w\s().,-]+?)\s+(\d+)\s+([\d.]+)\s+([A-Z0-9-]+)\s+(.+)$/);
            
            if (courseMatch) {
                courseIndex++;
                result.rows.push({
                    idx: courseIndex,
                    code: courseMatch[2].trim(),
                    title: courseMatch[3].trim(),
                    units: courseMatch[4].trim(),
                    grade: courseMatch[5].trim(),
                    section: courseMatch[6].trim(),
                    instructor: courseMatch[7].trim()
                });
            }
        }

        // Parse totals from raw text
        if (line.includes('Total no of Course')) {
            const match = line.match(/Total no of Course\s+(\d+)/);
            if (match) result.totals.total_courses = match[1];
        } else if (line.includes('Total no of Units')) {
            const match = line.match(/Total no of Units\s+(\d+)/);
            if (match) result.totals.total_units = match[1];
        } else if (line.includes('General Weighted Average (GWA)')) {
            const match = line.match(/General Weighted Average \(GWA\)\s+([\d.]+)/);
            if (match) result.totals.gwa = match[1];
        }
    }
}

// Function to extract only the structured data part (the specific section you want)
function extractStructuredData(fullText) {
    if (!fullText) return null;

    const lines = fullText.split('\n');
    let inStructuredSection = false;
    const structuredLines = [];
    let foundEnd = false;

    for (const line of lines) {
        // Start capturing when we find the student info section
        if (line.includes('Fullname :') && line.includes('SRCODE :')) {
            inStructuredSection = true;
        }

        // Stop capturing after the GWA line
        if (inStructuredSection && line.includes('General Weighted Average (GWA)')) {
            structuredLines.push(line.trim());
            foundEnd = true;
            break;
        }

        if (inStructuredSection) {
            // Skip the header line with "Course CodeCourse Title"
            if (line.includes('Course CodeCourse Title')) {
                continue;
            }
            structuredLines.push(line.trim());
        }
    }

    return foundEnd ? structuredLines.join('\n') : null;
}

// Function to parse structured data into table format
function parseStructuredDataToTable(data) {
    if (!data) return null;

    const lines = data.split('\n').filter(line => line.trim());
    const result = {
        meta: {},
        rows: [],
        totals: {}
    };

    let inCoursesSection = false;
    let courseIndex = 0;

    for (const line of lines) {
        // Parse metadata lines
        if (line.includes('Fullname :')) {
            const fullnameMatch = line.match(/Fullname\s*:\s*([^]+?)\s+SRCODE\s*:\s*([\w-]+)/);
            if (fullnameMatch) {
                result.meta.fullname = fullnameMatch[1].trim();
                result.meta.srcode = fullnameMatch[2].trim();
            }
            continue;
        }

        if (line.includes('College :')) {
            const collegeMatch = line.match(/College\s*:\s*([^]+?)\s+Academic Year\s*:\s*([\d-]+)/);
            if (collegeMatch) {
                result.meta.college = collegeMatch[1].trim();
                result.meta.academic_year = collegeMatch[2].trim();
            }
            continue;
        }

        if (line.includes('Program :')) {
            const programMatch = line.match(/Program\s*:\s*([^]+?)\s+Semester\s*:\s*([\w]+)/);
            if (programMatch) {
                result.meta.program = programMatch[1].trim();
                result.meta.semester = programMatch[2].trim();
            }
            continue;
        }

        if (line.includes('Year Level :')) {
            const yearLevelMatch = line.match(/Year Level\s*:\s*([\w]+)/);
            if (yearLevelMatch) {
                result.meta.year_level = yearLevelMatch[1].trim();
            }
            continue;
        }

        // Start of courses section (look for the first numbered course)
        if (/^\s*\d+\s+[A-Z]/.test(line)) {
            inCoursesSection = true;
        }

        // Parse course rows
        if (inCoursesSection) {
            // Skip if it's the end marker
            if (line.includes('** NOTHING FOLLOWS **')) {
                inCoursesSection = false;
                continue;
            }

            // Parse course line - matches: "1 ES 101   Environmental Sciences                 3     1.50  IT-2203     MERCADO, ALBERT S."
            const courseMatch = line.match(/^\s*(\d+)\s+([A-Za-z]+\s+\d+)\s+([^]+?)\s+(\d+)\s+([\d.]+)\s+([\w-]+)\s+([^]+)$/);
            if (courseMatch) {
                courseIndex++;
                result.rows.push({
                    idx: courseIndex,
                    code: courseMatch[2].trim(),
                    title: courseMatch[3].trim(),
                    units: courseMatch[4].trim(),
                    grade: courseMatch[5].trim(),
                    section: courseMatch[6].trim(),
                    instructor: courseMatch[7].trim()
                });
            }
        }

        // Parse totals
        if (line.includes('Total no of Course')) {
            const match = line.match(/Total no of Course\s+(\d+)/);
            if (match) result.totals.total_courses = match[1];
        }

        if (line.includes('Total no of Units')) {
            const match = line.match(/Total no of Units\s+(\d+)/);
            if (match) result.totals.total_units = match[1];
        }

        if (line.includes('General Weighted Average (GWA)')) {
            const match = line.match(/General Weighted Average \(GWA\)\s+([\d.]+)/);
            if (match) result.totals.gwa = match[1];
        }
    }

    return result;
}

// Function to render parsed data to the table
function renderParsedDataToTable(parsedData, tableBody, sumCourses, sumUnits, sumGwa) {
    if (!parsedData.rows.length) {
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No course data found in output.</td></tr>';
        return;
    }

    // Build table rows
    const rowsHtml = parsedData.rows.map(row => `
        <tr>
            <td class="text-center locked-cell">${row.idx}</td>
            <td class="locked-cell">${escapeHtml(row.code)}</td>
            <td class="locked-cell">${escapeHtml(row.title)}</td>
            <td class="text-center locked-cell">${escapeHtml(row.units)}</td>
            <td class="text-center locked-cell">${escapeHtml(row.grade)}</td>
            <td class="text-center locked-cell">${escapeHtml(row.section)}</td>
            <td class="locked-cell">${escapeHtml(row.instructor)}</td>
        </tr>
    `).join('');

    // Add "nothing follows" row
    const nothingRow = '<tr class="nothing-row"><td colspan="7">** NOTHING FOLLOWS **</td></tr>';
    tableBody.innerHTML = rowsHtml + nothingRow;

    // Update summary fields
    if (sumCourses) {
        sumCourses.textContent = parsedData.totals.total_courses || parsedData.rows.length;
    }
    if (sumUnits) {
        sumUnits.textContent = parsedData.totals.total_units || parsedData.rows.reduce((sum, row) => sum + (parseFloat(row.units) || 0), 0);
    }
    if (sumGwa) {
        sumGwa.textContent = parsedData.totals.gwa || calculateGWA(parsedData.rows);
    }

    // Also update the form fields with metadata
    fillExtractedFields(parsedData.meta);
}

// Helper function to calculate GWA from rows
function calculateGWA(rows) {
    let totalPoints = 0;
    let totalUnits = 0;
    
    rows.forEach(row => {
        const units = parseFloat(row.units) || 0;
        const grade = parseFloat(row.grade) || 0;
        
        if (units > 0 && grade > 0) {
            totalPoints += units * grade;
            totalUnits += units;
        }
    });
    
    return totalUnits > 0 ? (totalPoints / totalUnits).toFixed(4) : '—';
}

// Load structured COG data when entering step 6
const originalGoNext = window.goNext;
window.goNext = function() {
    const previousStep = currentStep;
    
    // Call the original function
    originalGoNext();
    
    // After navigation, check if we moved to Step 6
    if (previousStep === 5 && currentStep === 6) {
        // Load structured COG data when entering step 6
        setTimeout(fetchAndDisplayStructuredCogOutput, 100);
    }
};

// Also load structured data if page is refreshed on step 6
document.addEventListener('DOMContentLoaded', function() {
    if (currentStep === 6) {
        setTimeout(fetchAndDisplayStructuredCogOutput, 100);
    }
});

document.addEventListener('DOMContentLoaded', () => {
  updateStepperUI();
  const ok = document.getElementById('successOkBtn');
  if (ok) ok.addEventListener('click', redirectToStatus);
  const qBtn = document.getElementById('qualifiedContinueBtn');
  if (qBtn) qBtn.addEventListener('click', () => {
    const modal = bootstrap.Modal.getInstance(document.getElementById('qualifiedModal'));
    modal?.hide();
    currentStep = 5; updateStepperUI();
  });
  const tBtn = document.getElementById('tamperReuploadBtn');
  if (tBtn) tBtn.addEventListener('click', () => {
    const modal = bootstrap.Modal.getInstance(document.getElementById('tamperFailModal'));
    modal?.hide();
    if (tamperTarget === 'cor') {
      currentStep = 2; updateStepperUI(); document.getElementById('file-cor')?.click();
    } else {
      currentStep = 3; updateStepperUI(); document.getElementById('file-grade')?.click();
    }
  });

  const cogInput = document.getElementById('file-grade');
  if (cogInput) cogInput.addEventListener('click', () => {
    cogInput.value = '';
    const ok = document.getElementById('save-feedback'); if (ok) ok.style.display = 'none';
  });
  const corInput = document.getElementById('file-cor');
  if (corInput) corInput.addEventListener('click', () => { corInput.value = ''; });
});

/* ---------- tiny PDF preview fallback ---------- */
async function previewPDF(file, containerId){
  try{
    const c = await renderPdfFirstPageToCanvas(file);
    const holder = document.getElementById(containerId);
    if (holder) { holder.innerHTML = ''; c.className = 'img-fluid rounded border'; c.style.maxHeight = '420px'; holder.appendChild(c); }
  }catch(_){}
}

/* ---------- QR payload fallbacks ---------- */
function parseQrPayload(payload){
  try { if (/^https?:\/\//i.test(payload)) { const u = new URL(payload); const raw = u.searchParams.get('d') || u.searchParams.get('data') || ''; if (raw) return parseQrPayload(raw); } } catch(_){}
  try { const maybe = atob(payload); const j = JSON.parse(maybe); return mapQrJsonToParsed(j); } catch(_){}
  try { const j = JSON.parse(payload); return mapQrJsonToParsed(j); } catch(_){}
  return parsePlainCOGText(payload);
}
function mapQrJsonToParsed(j){
  const meta = sanitizeMeta({
    fullname: fixEncoding(j?.header?.Fullname||''),
    srcode: (j?.header?.SRCODE||'').replace(/[.\s]/g,'-').replace(/-+/g,'-'),
    college: fixEncoding(j?.header?.College||''),
    academic_year: j?.header?.['Academic Year']||'',
    program: fixEncoding(j?.header?.Program||''),
    semester: (j?.header?.Semester||'').toString().trim(),
    year_level: (j?.header?.['Year Level']||'').toString().trim(),
    total_units: j?.total_units!=null?String(j.total_units):'',
    total_courses: j?.total_courses!=null?String(j.total_courses):'',
    gwa: j?.gwa!=null?String(j.gwa):''
  });
  const rows=[]; (j?.grades||[]).forEach((g,i)=>{ const name=(g.name||'').trim(); const {code,title}=splitCodeTitle(name);
    rows.push({ idx:i+1, code, title:fixEncoding(title), units:g.units!=null?String(g.units):'', grade:g.grade!=null?String(g.grade):'', section:g.section||'', instructor:fixEncoding(g.instructor||'')}); });
  return { meta, rows };
}

async function renderPdfUrlFirstPageToCanvas(url){
  async function renderFromSource(src){
    const pdf = await pdfjsLib.getDocument(src).promise;
    const page = await pdf.getPage(1);
    const scale = 1.8, dpr = window.devicePixelRatio || 1;
    const vpCSS = page.getViewport({ scale });
    const vp    = page.getViewport({ scale: scale * dpr });

    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(vp.width));
    c.height = Math.max(1, Math.round(vp.height));
    c.style.width  = Math.round(vpCSS.width)  + 'px';
    c.style.height = Math.round(vpCSS.height) + 'px';

    const ctx = c.getContext('2d', { willReadFrequently:true });
    await page.render({ canvasContext: ctx, viewport: vp }).promise;

    if (!isCanvasUsable(c)) throw new Error('Rendered canvas is 0×0');
    return c;
  }

  try {
    return await renderFromSource({ url });
  } catch (e1) {
    try {
      const res = await fetch(url, { credentials: 'same-origin' });
      const buf = await res.arrayBuffer();
      return await renderFromSource({ data: buf });
    } catch (e2) {
      console.error('PDF render failed from URL and ArrayBuffer', e1, e2);
      throw e2;
    }
  }
}
</script>

@endsection