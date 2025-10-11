@extends('student.studentsidebar')

@section('content')

@php
    $isApplicationClosed = $isApplicationClosed ?? false;
@endphp

<link rel="stylesheet" href="{{ asset('css/application.css') }}">

<style>
  /* highlight any row where OCR grade ≠ QR grade (suspected tamper) */
  .tampered-row { background: #fff3f3 !important; }
  .tampered-row td { border-top-color: #f3b7b7 !important; }

  /* small badge showing official QR grade next to the uploaded grade */
  .qr-grade-badge{
    display:inline-block; margin-left:.35rem; padding:.1rem .4rem;
    font-size:.75rem; border:1px solid #b91c1c; color:#b91c1c;
    border-radius:.4rem; background:#fff; white-space:nowrap;
  }
</style>

<div class="container py-4" id="applicationContainer">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Application</h3>
  </div>

  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="route-generate-primary"  content="{{ route('student.pdf.generate') }}">
  <meta name="route-generate-fallback" content="{{ route('student.pdf.generate.json') }}">
  <meta name="route-application-submit" content="{{ route('student.application.submit') }}">
  <meta name="route-application-status" content="{{ url('/student/applicationstatus') }}">
  <meta name="route-save-cor-output" content="{{ route('student.save-cor-output') }}">
  <meta name="route-save-cog-output" content="{{ route('student.save-cog-output') }}">
  <meta name="route-cog-upload" content="{{ route('student.cog.upload') }}">
  <meta name="route-cor-upload" content="{{ route('student.cor.upload') }}">
  <meta name="route-qr-resolve" content="{{ route('student.qr.resolve') }}">
  <meta name="route-curriculum" content="{{ route('student.curriculum.subjects') }}">
  <meta name="route-save-cog-debug" content="{{ route('student.save-cog-debug') }}">

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
              <li>Accepted formats: <strong>Image (JPG/PNG)</strong> or <strong>PDF</strong></li>
              <li>Ensure the document is clear, complete, and official</li>
              <li>Example filename: <code>Lastname_Firstname_COG.jpg</code> or <code>.pdf</code></li>
            </ul>
            <div class="text-center">
              <input
                type="file"
                name="cog"
                id="file-grade"
                accept="image/*,application/pdf"
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

            <div class="val-item mb-2">
              <div class="val-left">1. Document Authenticity</div>
              <div class="val-right" id="v-tamper">
                <span class="spinner"></span><span class="val-muted">Validating…</span>
              </div>
            </div>

            <div class="val-item mb-2">
              <div class="val-left">2. Curriculum Match</div>
              <div class="val-right" id="v-irregular">
                <span class="spinner"></span><span class="val-muted">Checking curriculum…</span>
              </div>
            </div>

            <div class="val-item">
              <div class="val-left">3. Grade Eligibility</div>
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
                  <tr><td colspan="7" class="text-center text-muted">No rows detected. Please re-upload a clearer image.</td></tr>
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

          {{-- Step 7: Generate Application --}}
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
      <div class="modal-body pt-0"><p>Your application meets all the Dean’s List requirements.<br>You may now proceed to the next step.</p></div>
      <div class="modal-footer border-0"><button type="button" id="qualifiedContinueBtn" class="btn btn-primary">Continue</button></div>
    </div>
  </div>
</div>

{{-- Not Qualified --}}
<div class="modal fade" id="notQualifiedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title text-danger fw-bold">Not Qualified for Dean’s List</h5></div>
      <div class="modal-body pt-0">
        <p>Unfortunately, your application does not meet the Dean’s List requirements.</p>
        <p>Detected grade(s): <strong>2.75 / 3.00 / INC / DROP</strong>.</p>
        <p class="small text-muted mb-0">Note: Only students with grades 2.50 and above, with no INC or DROP, are eligible.</p>
      </div>
      <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button></div>
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
        <p>This indicates that you are classified as an irregular student, which does not meet the eligibility requirements for the Dean’s List.</p>
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

<!-- Authenticity (Tamper) FAIL -->
<div class="modal fade" id="tamperFailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 640px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title text-danger fw-bold">Document Authentication Check Failed</h5></div>
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


{{-- ================= Libraries ================= --}}
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script>
  if (window.pdfjsLib?.GlobalWorkerOptions) {
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = "{{ asset('vendor/pdfjs/pdf.worker.min.js') }}";
  }
</script>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="https://unpkg.com/@zxing/library@0.20.0"></script>
<script src="https://unpkg.com/qr-scanner@1.4.2/qr-scanner.umd.min.js"></script>
<script> QrScanner.WORKER_PATH = 'https://unpkg.com/qr-scanner@1.4.2/qr-scanner-worker.min.js'; </script>

{{-- === AUTO-TRIM HELPERS === --}}
<script>
/* Auto-proceed to Validation once QR exists — but wait for OCR gate */
function maybeAutoProceedToValidation() {
  if (currentStep !== 3) return;

  // If no global promise yet, tie it to the OCR gate so extractThenValidate will await something.
  if (!window.cogWorkDonePromise) {
    window.cogWorkDonePromise = (ocrReady || Promise.resolve()).catch(()=>{});
  }

  if ((window.lastQrRawText || '').trim()) {
    currentStep = 4;
    updateStepperUI();
    extractThenValidate(); // will await cogWorkDonePromise
  }
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
let mismatchIndex = new Map(); // key -> { qr, ocr }

/* NEW: OCR readiness gate */
let ocrReadyResolve = null;
let ocrReady = new Promise(res => (ocrReadyResolve = res));

/* ===== Modal helper (NEW) ===== */
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
    extractThenValidate(); // async
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
  if (currentStep === 6) { currentStep = 7; updateStepperUI(); generatePdf(); return; }
  if (currentStep === 7) { showConfirmSubmitModal(); return; }
  if (currentStep < 7) { currentStep++; updateStepperUI(); }
}
function goBack(){ if (isBusy) return; if (currentStep > 1){ currentStep--; updateStepperUI(); }}

/* ===== Server helpers ===== */
function csrfToken(){ return document.querySelector('meta[name="csrf-token"]').getAttribute('content'); }
function route(nameMeta){ return document.querySelector(`meta[name="${nameMeta}"]`)?.content || ''; }
async function saveDebugRaw(text, source){
  try{
    await fetch(route('route-save-cog-debug'), {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept':'application/json' },
      body: JSON.stringify({ text: `[SOURCE:${source}]` + "\n" + String(text || '') })
    });
  }catch(e){ /* ignore */ }
}

document.getElementById('applicationForm').addEventListener('submit', async function(event) {
  event.preventDefault();  // Prevent default form submission
  
  // Get the CSRF token from the meta tag
  const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  
  // Prepare form data
  const formData = new FormData(this);

  try {
    const response = await fetch(route('student.application.submit'), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,  // Include CSRF token
      },
      body: formData  // FormData will automatically handle file uploads and text fields
    });

    const result = await response.json();
    if (response.ok) {
      console.log('Application submitted successfully:', result);
      // Handle success (update UI, show success message, etc.)
    } else {
      console.error('Error submitting application:', result);
      // Handle error (show error message)
    }
  } catch (error) {
    console.error('Request failed:', error);
    // Handle network or other errors
  }
});

async function validateGrades() {
    const response = await fetch('/student/validate-grades', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({
            // Include any necessary data, e.g., file paths or content
        }),
    });

    const result = await response.json();

    if (result.status === 'fail') {
        // Handle mismatches and show them
        console.log('Mismatches:', result.mismatches);
    } else if (result.status === 'success') {
        alert(result.message);
    } else {
        alert('An error occurred.');
    }
}




/* ===== QR scan helpers ===== */
async function scanQrFromUploadedPdf(pdfFile){
  try{
    const canvas = await renderPdfFirstPageToCanvas(pdfFile);
    try{
      const r = await QrScanner.scanImage(canvas, { returnDetailedScanResult:true, inversionAttempts:'attemptBoth' });
      if (r?.data) return String(r.data);
    }catch(_){}
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

  /* reset OCR gate */
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

async function handleCogUpload(input) {
  resetCogState();
  const f = input.files && input.files[0];
  if (!f) return;

  const status = document.getElementById('cog-status');
  const holder = document.getElementById('preview-grade');
  status.textContent = '';

  // BUONG trabaho ilagay sa promise para ma-await sa validation
  window.cogWorkDonePromise = (async () => {
    // Image branch
    if (f.type && f.type.startsWith('image/')) {
      await uploadCogImageTrimmed(f);           // function below now also uses the gate
      return;
    }

    // PDF branch
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
          j.cog_image_path ?? j.cog_path ?? j.absolute_path ?? j.full_path ??
          j.abs_path ?? j.file_path ?? j.filepath ?? j.storage_path ?? j.path ?? '';

        const pngUrl = j.cog_image_url ?? j.public_url ?? j.url ?? '';
        const pdfUrl = j.cog_pdf_url || (pngUrl && pngUrl.endsWith('.pdf') ? pngUrl : '');

        // Preview preference: PNG > PDF URL > local render
        if (pngUrl && !pngUrl.endsWith('.pdf')) {
          const cb = pngUrl + (pngUrl.includes('?')?'&':'?') + 'v=' + Date.now();
          holder.innerHTML = `<img id="cogPreviewImg" src="${cb}" class="img-fluid rounded border" style="max-height:380px" />`;
        } else if (pdfUrl) {
          holder.innerHTML = '<div class="text-muted small">Rendering PDF preview…</div>';
          try {
            const c = await renderPdfUrlFirstPageToCanvas(pdfUrl);
            c.className = 'img-fluid rounded border'; c.style.maxHeight = '380px';
            holder.innerHTML = ''; holder.appendChild(c);
          } catch {
            holder.innerHTML = '<div class="text-danger small">PDF preview failed; will try local render.</div>';
          }
        } else {
          holder.innerHTML = '<div class="text-muted small">No server preview; rendering local PDF…</div>';
        }

        // Use server text if available; else run local OCR
        const pdfText = (j.pdf_text ?? j.ocr_text ?? '').trim();
        if (pdfText) {
          window.lastOcrRawText = pdfText;
          parsedFromOCR = parsePlainCOGText(pdfText);
          /* OCR is ready immediately (server provided) */
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

        // Always try QR from the uploaded PDF (final pass)
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

        /* ➜ If a QR was captured in any path above, jump to Validation now */
        if ((window.lastQrRawText || '').trim()) {
          maybeAutoProceedToValidation(); // waits via cogWorkDonePromise/OCR gate
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

  // gawing part ng global promise ang buong flow
  window.cogWorkDonePromise = (async () => {
    status.textContent = 'Preparing image…';

    const tmpUrl = URL.createObjectURL(file);
    const img = await new Promise((res, rej) => { const i = new Image(); i.onload=()=>res(i); i.onerror=rej; i.src=tmpUrl; });
    const c = document.createElement('canvas');
    c.width = img.width; c.height = img.height;
    c.getContext('2d', { willReadFrequently:true }).drawImage(img, 0, 0);
    URL.revokeObjectURL(tmpUrl);

    let trimmed = canvasAutoTrim(c, 18);

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

  // return the promise so callers may await (optional)
  return window.cogWorkDonePromise;
}

/* QR/OCR from a canvas */
async function runQrOcrFromCanvas(canvas, statusEl) {
  let qrText = '';
  try {
    const r = await QrScanner.scanImage(canvas, { returnDetailedScanResult: true, inversionAttempts: 'attemptBoth' });
    if (r?.data) qrText = String(r.data);
  } catch {}
  if (!qrText) {
    try {
      const reader = new ZXing.BrowserQRCodeReader();
      const r2 = await reader.decodeFromImage(canvas);
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
    try {
      const api = await resolveQrOnServer(qrText);
      parsedFromQR = mapServerJsonToParsed(api);
    } catch {
      const local = parseQrPayload(qrText);
      parsedFromQR = local?.rows?.length ? local : { meta:{}, rows:[] };
    }

    // ➜ auto-go to Validation when QR is captured (from canvas path)
    maybeAutoProceedToValidation(); // waits via cogWorkDonePromise/OCR gate
  }

  if (statusEl) statusEl.textContent = 'Running OCR…';
  const dataUrl = canvas.toDataURL('image/png');
  const { data } = await Tesseract.recognize(dataUrl, 'eng', { tessedit_char_blacklist:'[]{}<>~`^' });
  window.lastOcrRawText = data?.text || '';
  if (window.lastOcrRawText.trim()) { await saveDebugRaw(window.lastOcrRawText, 'OCR'); }
  parsedFromOCR = parsePlainCOGText(window.lastOcrRawText || '');

  // 👇 debug log after parsing OCR
  console.log('OCR len=', (window.lastOcrRawText||'').length,
              'OCR rows=', (parsedFromOCR.rows||[]).length,
              'QR rows=', (parsedFromQR.rows||[]).length);

  /* OCR ready now */
  try { ocrReadyResolve && ocrReadyResolve(); } catch(_) {}

  if (statusEl) statusEl.textContent = qrText ? 'QR + OCR captured. Proceed to Validation.' : 'OCR captured (no QR found).';
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
    method: 'POST', headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept':'application/json' },
    body: JSON.stringify({ payload: qrText })
  });
  if (!res.ok) throw new Error(await res.text());
  return await res.json();
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
          .replace(/Ã“/g,'Ó').replace(/Ã³/g,'ó').replace(/Ã‰/g,'É').replace(/Ã©/g,'é')
          .replace(/Ã/g,'Á').replace(/Ã¡/g,'á').replace(/Ã/g,'Í').replace(/Ã­/g,'í')
          .replace(/Ãš/g,'Ú').replace(/Ãº/g,'ú'); }
const PLACEHOLDER_TOKENS = new Set(['FULLNAME','SRCODE','COLLEGE','PROGRAM','SEMESTER','YEAR LEVEL','ACADEMIC YEAR','-','—','N/A','NA']);
function looksFilled(key, val){ if (val==null) return false; const v=String(val).trim(); if(!v) return false;
  const up=v.toUpperCase(); if(PLACEHOLDER_TOKENS.has(up)) return false;
  if(key==='srcode') return /\d/.test(v); if(key==='fullname') return v.length>=5;
  if(key==='academic_year') return /\d{4}\s*-\s*\d{4}/.test(v)||/\b\d{4}\b/.test(v); return true; }
function sanitizeMeta(meta={}){ const out={...meta}; for(const k of ['fullname','srcode','college','program','semester','year_level','academic_year']) if(!looksFilled(k,out[k])) out[k]=''; return out; }
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
  const iFull = idxOf(HDR.fullname), iCol=idxOf(HDR.college), iProg=idxOf(HDR.program),
        iSem=idxOf(HDR.semester), iYLvl=idxOf(HDR.year_level), iAY=idxOf(HDR.academic_year), iSR=idxOf(HDR.srcode);
  const lineFull = iFull !== -1 ? lines[iFull] : '';
  const lineCol  = iCol  !== -1 ? lines[iCol]  : '';
  const lineProg = iProg !== -1 ? lines[iProg] : '';
  const lineSem  = iSem  !== -1 ? lines[iSem]  : '';
  const lineAY   = iAY   !== -1 ? lines[iAY]   : '';
  const lineSR   = iSR   !== -1 ? lines[iSR]   : '';

  const fullname = iFull !== -1 ? sliceAfter(lines, iFull, /SRCODE\s*:?\s*/i) : '';
  const college  = iCol  !== -1 ? sliceAfter(lines, iCol,  /Academic\s*Year\s*:?\s*/i) : '';
  const program  = iProg !== -1 ? sliceAfter(lines, iProg, /Semester\s*:?\s*/i) : '';
  const semester = iSem  !== -1 ? lineSem.replace(HDR.semester,'').trim() : '';
  const yearLvl  = iYLvl !== -1 ? lines[iYLvl].replace(HDR.year_level,'').trim() : '';
  let   srcode   = iSR   !== -1 ? lineSR.replace(HDR.srcode,'').trim() : '';
  let   ay       = '';

  if (iAY !== -1) {
    const m = lineAY.match(RX.academic_year_val);
    ay = m ? m[1] : lineAY.replace(HDR.academic_year,'').trim();
  }
  if (!srcode && lineFull) {
    const mSR = lineFull.match(/SRCODE\s*:\s*([A-Z0-9\-]+)/i);
    if (mSR) srcode = mSR[1].trim();
  }
  if (!ay && lineCol) {
    const mAY = lineCol.match(/Academic\s*Year\s*:\s*([0-9]{4}\s*[-–—]\s*[0-9]{4}|[0-9]{4})/i);
    if (mAY) ay = mAY[1].trim();
  }
  let sem2 = semester;
  if (!sem2 && lineProg) {
    const m = lineProg.match(/Semester\s*:\s*([A-Za-z]+)/i);
    if (m) sem2 = m[1].trim();
  }
  return { fullname, srcode, college, program, semester: sem2, year_level: yearLvl, academic_year: ay };
}
function parsePlainCOGText(text){
  let cleaned = text.replace(/\r/g,'').replace(/[“”]/g,'"').replace(/[‘’]/g,"'").replace(/[—–−]/g,'-').replace(/\u00A0/g, ' ');
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
      /^(?:\[\s*(\d+)\s*\]\s*)?([A-Za-z]{2,}(?:\s*[-/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s+(.+?)\s+(\d{1,2})\s+([0-2]\.\d{1,4}|100|150|200)\s+([A-Za-z]+(?:-?[A-Za-z]+)*-?\d{3,4}[A-Za]?)\s+(.+)$/
    );
    if (!m){
      const next = (lines[i+1] || '');
      const joined = (line + ' ' + next).replace(/\s{2,}/g,' ');
      const m2 = joined.match(
        /^(?:\[\s*(\d+)\s*\]\s*)?([A-Za-z]{2,}(?:\s*[-/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s+(.+?)\s+(\d{1,2})\s+([0-2]\.\d{1,4}|100|150|200)\s+([A-Za-z]+(?:-?[A-Za-z]+)*-?\d{3,4}[A-Za]?)\s+(.+)$/
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
  let t = tok.trim();
  if (/^\d{3}$/.test(t)) { if (t==='150') return '1.50'; if (t==='200') return '2.00'; if (t==='100') return '1.00'; }
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

    // IMPORTANT: wait until COG OCR/QR work is finished (if any)
    if (window.cogWorkDonePromise) {
      setValState('v-tamper','loading','Waiting for OCR/QR…');
      try { await window.cogWorkDonePromise; } catch(_) {}
    }

    await extractFromCog();     // fill UI from whatever we have now
    await runValidations();     // now both QR and OCR should be ready
  }catch(e){
    console.error(e);
    alert('Validation failed to run. Please try re-uploading a clearer COG image.');
  }finally{
    setBusy(false);
  }
}

async function extractFromCog(){
  const alreadyHave =
    (window.lastQrRawText && parsedFromQR?.rows?.length) ||
    (window.lastOcrRawText && parsedFromOCR?.rows?.length);

  if (alreadyHave) {
    const qrMeta  = sanitizeMeta(parsedFromQR?.meta  || {});
    const ocrMeta = sanitizeMeta(parsedFromOCR?.meta || {});
    const corMeta = sanitizeMeta(window.corMetaFromOcr || {});
    const mergedMeta = mergeMetaPreferFilled( mergeMetaPreferFilled(qrMeta, ocrMeta), corMeta );

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

/* ---- Strict per-course grade compare ---- */
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
function compareQrOcrGradesStrict(qrRows = [], ocrRows = []) {
  const canonCode  = (s='') => (s||'').toUpperCase().replace(/\s+/g,' ').trim();
  const gradeNorm  = (g='') => {
    const t = String(g).replace(/\s+/g,'').trim();
    if (t === '100') return '1.00';
    if (t === '150') return '1.50';
    if (t === '200') return '2.00';
    return t;
  };

  const byCode = new Map();
  for (const r of qrRows) {
    const c = canonCode(r.code);
    if (c) byCode.set(c, r);
  }

  const mismatches = [];
  for (const ocr of ocrRows) {
    const c = canonCode(ocr.code);
    const qr = c && byCode.get(c);
    if (!qr) continue;

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
  }
  return mismatches;
}

/* NEW: full row diff (mismatch + missing/extra) */
function diffQrOcrRows(qrRows = [], ocrRows = []) {
  const canon = s => (s||'').toUpperCase().replace(/\s+/g,' ').trim();
  const gradeNorm = (g='') => {
    const t = String(g).replace(/\s+/g,'').trim();
    if (t === '100') return '1.00';
    if (t === '150') return '1.50';
    if (t === '200') return '2.00';
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

// STRICT VALIDATION: both QR & OCR must be present and all grades equal
// Function to compare grades row by row
async function runValidations() {
    setValState('v-tamper', 'loading', 'Comparing QR vs OCR from saved text...');
    
    const cogOcrData = window.lastOcrRawText || '';  // Raw OCR text from the file
    const cogOutputData = await fetchCogOutput();  // Fetch the content of cog_output.txt

    // Parse both the OCR and output data into structured objects (arrays of course rows)
    const ocrCourses = parseCoursesData(cogOcrData);
    const outputCourses = parseCoursesData(cogOutputData);

    // Compare the parsed course data
    const mismatches = compareCourses(ocrCourses, outputCourses);

    // If mismatches exist, handle them
    if (mismatches.length > 0) {
        // Display failure reason (Document Authentication Error)
        showDocumentAuthenticationFailure(mismatches);
        setValState('v-tamper', 'fail', 'Grades mismatch found. Cannot proceed.');
        console.log(mismatches);  // Log the mismatches
    } else {
        setValState('v-tamper', 'ok', 'QR and OCR match');
        setValState('v-irregular', 'ok', 'Checked');
        setValState('v-grades', 'ok', 'No disqualifying grades');
        validationPass = true;
    }
}

// Fetch the content of cog_output.txt
async function fetchCogOutput() {
    const response = await fetch(route('student.cog.output'));  // Modify with your route
    const data = await response.text();
    return data;
}

// Parse the course data (grades, course codes, etc.)
function parseCoursesData(textData) {
    const lines = textData.split('\n');
    const courses = [];

    lines.forEach(line => {
        // Assuming each line represents a course entry with grades and other details
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

// Normalize grades (strip spaces, ensure consistent formatting)
function normalizeGrade(grade) {
    if (!grade) return '';
    return parseFloat(grade.trim()).toFixed(2);  // Normalize to 2 decimal places
}

// Compare the grades row by row
function compareCourses(ocrCourses, outputCourses) {
    const mismatches = [];

    // Iterate over each row and compare grades
    ocrCourses.forEach((ocrCourse, index) => {
        const outputCourse = outputCourses[index];

        // If the grades don't match, record the mismatch
        if (ocrCourse && outputCourse) {
            const ocrGrade = normalizeGrade(ocrCourse.grade);
            const outputGrade = normalizeGrade(outputCourse.grade);

            if (ocrGrade !== outputGrade) {
                mismatches.push({
                    index: index + 1,  // Row index (starting from 1)
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

// Display a failure message when mismatches are found
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

    document.getElementById('failure-reason').innerHTML = failureMessage;  // Display in the UI
}




/* ===========================================================
   5) UI fill + table render (chip-aware)
   =========================================================== */
function setIfEmpty(id, value){ const el=document.getElementById(id); if(!el) return; if(!el.value) el.value=value; }
function fillExtractedFields(meta){
  setIfEmpty('fullname', (meta.fullname || '').replace(/\s{2,}/g,' ').trim());
  setIfEmpty('srcode', meta.srcode || ''); setIfEmpty('college', meta.college || '');
  setIfEmpty('academic_year', meta.academic_year || ''); setIfEmpty('program', meta.program || '');
  setIfEmpty('semester', meta.semester || ''); setIfEmpty('year_level', meta.year_level || '');
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
  try { const reader = new ZXing.BrowserQRCodeReader(); const res = await reader.decodeFromImage(canvas); if (res && res.text) return String(res.text); } catch {}
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

    const pngDataUrl = canvas.toDataURL('image/png');
    const { data } = await Tesseract.recognize(pngDataUrl, 'eng', { tessedit_char_blacklist:'[]{}<>~`^' });
    const ocrText = (data?.text || '').trim();

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
  const finalMeta = mergeMetaPreferFilled( mergeMetaPreferFilled( mergeMetaPreferFilled(qrMeta, ocrMeta), corMeta ), uiMeta );

  const qrRows  = parsedFromQR?.rows || [];
  const ocrRows = parsedFromOCR?.rows || rows || [];

  const timestampStr = new Date().toISOString().replace('T',' ').slice(0,19);
  const qrUrl = extractQrUrlFromPayload(window.lastQrRawText || '');

  const qrSection  = buildQrFullSection(Object.keys(qrMeta).length?qrMeta:finalMeta, qrRows.length?qrRows:rows);
  const ocrSection = buildOcrFullSection(Object.keys(ocrMeta).length?ocrMeta:finalMeta, ocrRows);

  const textOut = [
    `[TIME] ${timestampStr}`,
    `[SOURCE:QR]`,
    qrUrl || (window.lastQrRawText || ''),
    '===================================================',
    '',
    qrSection,
    '',
    '',
    '===============OCR TEXT===============',
    '',
    ocrSection
  ].join('\n');
  
  try{
    await fetch(route('route-save-cog-output'), {
      method:'POST',
      headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN': csrfToken(),'Accept':'application/json' },
      body: JSON.stringify({
        cog_raw: textOut,
        qr_raw: qrUrl || (window.lastQrRawText || ''),
        ocr_full: window.lastOcrRawText || '',
        meta: finalMeta,
        rows: rows
      })
    });
    const ok = document.getElementById('save-feedback');
    if (ok) { ok.style.display='inline'; setTimeout(()=> ok.style.display='none', 2000); }
  }catch(e){ /* ignore */ }
}

/* ===========================================================
   8) PDF + submit
   =========================================================== */
async function generatePdf() {
  const status = document.getElementById('pdf-status');
  const btn    = document.getElementById('pdf-generate-btn');
  const iframe = document.getElementById('pdf-frame');
  const openA  = document.getElementById('pdf-open-link');

  const { rows, meta, total_units, gwa } = collectCurrentStateFromUI();

  const payload = {
    cor_png_path: serverPaths.cor_img || "",
    cog_png_path: serverPaths.cog_img || "",
    meta,
    rows,
    totals: { total_units, gwa }
  };

  const primaryUrl  = route('route-generate-primary');
  const fallbackUrl = route('route-generate-fallback');
  const csrf        = csrfToken();

  if (btn) btn.disabled = true;
  if (status) status.textContent = 'Generating…';

  async function postTo(url){
    return fetch(url, { method: 'POST', headers: { 'Content-Type':'application/json','X-CSRF-TOKEN': csrf,'Accept':'application/json' }, body: JSON.stringify(payload) });
  }

  try {
    let res = await postTo(primaryUrl);
    let json = {}; try { json = await res.json(); } catch(_) {}

    if (res.status === 410) {
      if (status) status.textContent = 'Switching to new generator…';
      res = await postTo(fallbackUrl);
      try { json = await res.json(); } catch(_) {}
    }

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
    if (status) status.textContent = 'Done.';
  } catch (e) {
    if (status) status.textContent = `Failed to generate PDF: ${e.message || e}`;
  } finally { if (btn) btn.disabled = false; }
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
async function submitApplication(){
  showModal('#successSubmitModal');
}
function redirectToStatus(){
  const meta = document.querySelector('meta[name="route-application-status"]');
  const href = meta ? meta.content : '/student/applicationstatus';
  window.location.href = href;
}
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

  // Ensure change fires even if same filename is chosen again
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

// Render a remote PDF URL (first page) to a canvas using pdf.js
async function renderPdfUrlFirstPageToCanvas(url){
  const pdf = await pdfjsLib.getDocument({ url }).promise;
  const page = await pdf.getPage(1);
  const vport = page.getViewport({ scale: 2.0 });
  const c = document.createElement('canvas');
  c.width = vport.width; c.height = vport.height;
  const ctx = c.getContext('2d', { willReadFrequently:true });
  await page.render({ canvasContext: ctx, viewport: vport }).promise;
  return c;
}
</script>

@endsection
