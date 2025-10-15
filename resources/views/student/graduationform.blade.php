@extends('student.studentsidebar')

@section('content')

@php
    $isApplicationClosed = $isApplicationClosed ?? false;
@endphp

<link rel="stylesheet" href="{{ asset('css/application.css') }}">

<style>
  .tampered-row { background: #fff3f3 !important; }
  .tampered-row td { border-top-color: #f3b7b7 !important; }
  .qr-grade-badge{ display:none } /* hidden since we removed QR compare */
  .spinner{ width:14px;height:14px;border:2px solid #ddd;border-top-color:#0d6efd;border-radius:50%;display:inline-block;animation:spin .8s linear infinite;margin-right:.35rem }
  @keyframes spin{ to { transform: rotate(360deg)} }
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
        $steps = ['Guidelines','Upload COR','Upload Grades','Validation','Fill Up','Consent','Review & Confirm','Generate Application'];
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
                name="grades_pdf"
                id="file-grade"
                accept="application/pdf,image/*"
                class="form-control w-50 mx-auto"
                required
                onchange="handleCogUpload(this)"
              />
              <div class="mt-3" id="preview-grade"></div>
              <div class="mt-2 small text-muted" id="grades-pages" style="display:none;"></div>
              <small id="grades-status" class="text-muted d-block mt-2"></small>
            </div>
          </div>

          {{-- Step 4: Validation (no QR) --}}
          <div class="text-start step-section" id="step-4" style="display:none;">
            <p class="mb-2 text-muted">Checks performed before proceeding:</p>

            <div class="val-item mb-2">
              <div class="val-left">1. File Readability</div>
              <div class="val-right" id="v-tamper">
                <span class="spinner"></span><span class="val-muted">Validating…</span>
              </div>
            </div>

            <div class="val-item mb-2">
              <div class="val-left">2. Curriculum Match</div>
              <div class="val-right" id="v-irregular">
                <span class="val-muted">Skipped (no QR)</span>
              </div>
            </div>

            <div class="val-item">
              <div class="val-left">3. Grade Eligibility</div>
              <div class="val-right" id="v-grades">
                <span class="val-muted">Manual check after review</span>
              </div>
            </div>

            <small class="text-muted d-block mt-3">You can proceed once the file passes readability.</small>
          </div>

          {{-- Step 5: Fill Up --}}
          <div class="text-start step-section" id="step-5" style="display: none;">
            <h4 class="fw-bold mb-4">Fill Up Graduation Form</h4>
            <div class="row g-3">
              <div class="col-md-3"><label class="form-label">Surname</label><input type="text" class="form-control" name="surname" value="{{ $prefill['surname'] ?? '' }}" required></div>
              <div class="col-md-3"><label class="form-label">First Name</label><input type="text" class="form-control" name="first_name" value="{{ $prefill['first_name'] ?? '' }}" required></div>
              <div class="col-md-3"><label class="form-label">Middle Name</label><input type="text" class="form-control" name="middle_name" value="{{ $prefill['middle_name'] ?? '' }}"></div>
              <div class="col-md-3"><label class="form-label">Ext.</label><input type="text" class="form-control" name="ext" value="{{ $prefill['ext'] ?? '' }}"></div>
              <div class="col-md-3"><label class="form-label">SR Code</label><input type="text" class="form-control" name="sr_code" value="{{ $prefill['sr_code'] ?? '' }}" required></div>
              <div class="col-md-3"><label class="form-label">Birthdate</label><input type="date" class="form-control" name="birthdate" value="{{ $prefill['birthdate'] ?? '' }}" required></div>
              <div class="col-md-3"><label class="form-label">Place of Birth</label><input type="text" class="form-control" name="place_of_birth" value="{{ $prefill['place_of_birth'] ?? '' }}"></div>
              <div class="col-md-3"><label class="form-label">Contact Number</label><input type="text" class="form-control" name="contact_number" value="{{ $prefill['contact_number'] ?? '' }}"></div>
              <div class="col-12"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="{{ $prefill['email'] ?? '' }}" required></div>

              {{-- PSGC address block unchanged --}}
              <div class="col-md-12">
                <label class="form-label">Region</label>
                <select class="form-select" name="region"><option>Select Region</option></select>
              </div>
              <div class="col-md-12">
                <label class="form-label">Province</label>
                <select class="form-select" name="province"><option>Select Province</option></select>
              </div>
              <div class="col-md-12">
                <label class="form-label">City/Municipality</label>
                <select class="form-select" name="city"><option>Select City/Municipality</option></select>
              </div>
              <div class="col-md-8"><label class="form-label">Barangay</label><select class="form-select" name="barangay"><option>Select Barangay</option></select></div>
              <div class="col-md-4"><label class="form-label">ZIP Code</label><input type="text" class="form-control" name="zip_code"></div>
              <div class="col-md-12"><label class="form-label">House No./Street/Subdivision (optional)</label><input type="text" class="form-control" name="home_address"></div>

              <div class="col-md-6"><label class="form-label">Secondary School Graduated</label><input type="text" class="form-control" name="secondary_school"></div>
              <div class="col-md-6"><label class="form-label">Year Graduated</label><input type="text" class="form-control" name="secondary_year"></div>
              <div class="col-md-6"><label class="form-label">Elementary School Graduated</label><input type="text" class="form-control" name="elementary_school"></div>
              <div class="col-md-6"><label class="form-label">Year Graduated</label><input type="text" class="form-control" name="elementary_year"></div>
              <div class="col-md-6"><label class="form-label">College</label><input type="text" class="form-control" name="college"></div>
              <div class="col-md-6"><label class="form-label">Program</label><input type="text" class="form-control" name="program"></div>

              <div class="col-md-12"><label class="form-label">Graduation Period</label>
                <div class="form-check form-check-inline ms-2">
                  <input class="form-check-input" type="checkbox" name="grad_period_december" id="grad_period_december">
                  <label class="form-check-label" for="grad_period_december">December</label>
                  <input type="text" class="form-control d-inline-block ms-2" style="width:80px;" name="grad_year_december" placeholder="Year">
                </div>
                <div class="form-check form-check-inline ms-2">
                  <input class="form-check-input" type="checkbox" name="grad_period_may" id="grad_period_may">
                  <label class="form-check-label" for="grad_period_may">May</label>
                  <input type="text" class="form-control d-inline-block ms-2" style="width:80px;" name="grad_year_may" placeholder="Year">
                </div>
                <div class="form-check form-check-inline ms-2">
                  <input class="form-check-input" type="checkbox" name="grad_period_midterm" id="grad_period_midterm">
                  <label class="form-check-label" for="grad_period_midterm">Midterm</label>
                  <input type="text" class="form-control d-inline-block ms-2" style="width:80px;" name="grad_year_midterm" placeholder="Year">
                </div>
              </div>

              <div class="col-md-12"><label class="form-label">Major</label><input type="text" class="form-control" name="major"></div>
            </div>
          </div>

          {{-- PSGC script block (unchanged) --}}
          <script>
          // PSGC Address Dropdown Functionality (unchanged)
          /* ... keep your existing PSGC code here exactly as in your original file ... */
          </script>

          {{-- Step 6: Consent --}}
          <div class="text-start step-section" id="step-6" style="display: none;">
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

          {{-- Step 7: Review & Confirm --}}
          <div class="text-start step-section" id="step-7" style="display: none;">
            <p class="mb-3 text-muted">Review your details before final submission.</p>

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
                  <tr><td colspan="7" class="text-center text-muted">No rows detected. Please re-upload a clearer document (optional).</td></tr>
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

          <!-- Step 8: Generate Application -->
          <div class="text-start step-section" id="step-8" style="display: none;">
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


{{-- ================= Libraries ================= --}}
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script>
  if (window.pdfjsLib?.GlobalWorkerOptions) {
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = "{{ asset('vendor/pdfjs/pdf.worker.min.js') }}";
  }
</script>
{{-- Removed: tesseract.js, jsQR, @zxing, qr-scanner (no QR/OCR now) --}}

<script>
/* ===== Globals ===== */
const APP_CLOSED = @json($isApplicationClosed);
let currentStep = 1;
let isBusy = false;
let validationPass = false;

const serverPaths = { cor_pdf:'', cor_img:'', cor_text:'', cor_public_preview:'', cog_img:'', cog_pdf_url:'' };

/* ===== UI helpers ===== */
function setBusy(state){
  isBusy = state;
  const btn = document.getElementById('next-btn');
  if (btn) {
    btn.disabled = state;
    btn.innerHTML = state ? 'Please wait…' : (currentStep === 8 ? 'Finish' : 'Next');
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
  const titles = {
    1:'<i class="bi bi-pin-angle-fill text-danger me-2"></i>Guidelines',
    4:'Validation',
    7:'Review & Confirm',
    8:'Generate Application'
  };
  const ct = document.getElementById('card-title');
  if (ct) {
    if (HIDE_CARD_TITLE_STEPS.has(currentStep)) { ct.style.display='none'; ct.innerHTML=''; }
    else { ct.style.display='block'; if (titles[currentStep]) ct.innerHTML=titles[currentStep]; }
  }

  for (let i=1;i<=8;i++){
    const s = document.getElementById('step-'+i);
    if (s) s.style.display = i===currentStep ? 'block' : 'none';
  }

  const nextBtn = document.getElementById('next-btn');
  if (nextBtn) nextBtn.innerText = currentStep === 8 ? 'Finish' : 'Next';
}

function goNext() {
  if (isBusy) return;

  if (APP_CLOSED) {
    const el = document.getElementById('applicationClosedModal');
    if (window.bootstrap?.Modal && el) bootstrap.Modal.getOrCreateInstance(el).show();
    return;
  }

  if (currentStep === 2) {
    const corFile  = document.getElementById('file-cor')?.files?.[0];
    const corStat  = document.getElementById('cor-status');
    if (!corFile) { corStat.textContent = 'Please select your COR PDF.'; return; }
    if (corFile.type !== 'application/pdf') { corStat.textContent = 'COR must be a PDF file.'; return; }
    if (!serverPaths.cor_pdf && !serverPaths.cor_img) { corStat.textContent = 'Still processing COR…'; return; }
    currentStep = 3; updateStepperUI(); return;
  }

  if (currentStep === 3) {
    const cogFile = document.getElementById('file-grade')?.files?.[0];
    const cogStat = document.getElementById('grades-status');
    if (!cogFile) { cogStat.textContent = 'Please select your COG file.'; return; }
    currentStep = 4; updateStepperUI();
    // trigger basic validation (readability)
    runReadabilityValidation();
    return;
  }

  if (currentStep === 4) {
    if (!validationPass) { alert('Please wait for validation to complete.'); return; }
    currentStep = 5; updateStepperUI(); return;
  }

  if (currentStep === 5) {
    setBusy(true);
    saveGraduationFormFields().then((ok)=>{ setBusy(false); if (!ok) return alert('Failed to save graduation form.'); currentStep=6; updateStepperUI(); });
    return;
  }

  if (currentStep === 6) {
    const c1 = document.getElementById('consent1')?.checked;
    const c2 = document.getElementById('consent2')?.checked;
    const c3 = document.getElementById('consent3')?.checked;
    if (!c1 || !c2 || !c3) return alert('Please check all consent checkboxes.');
    currentStep = 7; updateStepperUI(); return;
  }

  if (currentStep === 7) { currentStep = 8; updateStepperUI(); generatePdf(); return; }

  if (currentStep === 8) { showConfirmSubmitModal(); return; }

  currentStep++; updateStepperUI();
}

function goBack(){ if (isBusy) return; if (currentStep > 1){ currentStep--; updateStepperUI(); }}

/* ===== Server helpers ===== */
function csrfToken(){ return document.querySelector('meta[name="csrf-token"]').getAttribute('content'); }
function route(nameMeta){ return document.querySelector(`meta[name="${nameMeta}"]`)?.content || ''; }

/* ===== COR upload (unchanged) ===== */
async function uploadCorPdf(){
  const input = document.getElementById('file-cor');
  const file = input.files && input.files[0];
  if (!file) return;

  const el = document.getElementById('cor-status');
  el.textContent = 'Uploading & processing…';

  try{
    const fd = new FormData();
    fd.append('cor', file);

    const res = await fetch(route('route-cor-upload'), {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: fd,
      credentials: 'same-origin'
    });
    if(!res.ok) throw new Error(await res.text());
    const j = await res.json();

    serverPaths.cor_pdf  = j.cor_pdf_path || '';
    serverPaths.cor_img  = j.cor_png_path || j.cor_upload_png_path || j.cropped_png_path || serverPaths.cor_img || '';
    serverPaths.cor_text = j.cor_text_path || j.cor_output_txt_path || '';
    serverPaths.cor_public_preview = j.cor_png_url || j.cor_upload_png_url || j.cropped_png_url || j.cor_pdf_url || '';

    if (serverPaths.cor_public_preview) {
      const cb = serverPaths.cor_public_preview + (serverPaths.cor_public_preview.includes('?')?'&':'?') + 'v=' + Date.now();
      if (/\.(png|jpg|jpeg)$/i.test(serverPaths.cor_public_preview)) {
        document.getElementById('preview-cor').innerHTML =
          `<img src="${cb}" class="img-fluid rounded border" style="max-height:420px" />`;
      } else {
        document.getElementById('preview-cor').innerHTML =
          `<iframe src="${cb}" width="100%" height="420" class="rounded border"></iframe>`;
      }
    } else {
      await previewPDF(file, 'preview-cor');
    }

    el.textContent = 'COR uploaded successfully.';
  }catch(e){
    console.error(e);
    el.textContent = 'Upload failed.';
  }
}

/* ===== NEW: COG upload with NO QR scan + page count ===== */
async function handleCogUpload(input) {
  const f = input.files && input.files[0];
  if (!f) return;

  const status = document.getElementById('grades-status');
  const holder = document.getElementById('preview-grade');
  const pagesEl= document.getElementById('grades-pages');
  status.textContent = 'Uploading…';
  holder.innerHTML = '';
  pagesEl.style.display = 'none';
  pagesEl.textContent = '';

  try {
    // build form data depending on file type
    const fd = new FormData();
    if (f.type === 'application/pdf') {
      fd.append('pdf', f);
    } else if (f.type && f.type.startsWith('image/')) {
      fd.append('image', f);
    } else {
      status.textContent = 'COG must be an image or a PDF.';
      return;
    }

    const res = await fetch(route('route-cog-upload'), {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept':'application/json' },
      body: fd
    });
    const j = await res.json();
    if (!res.ok || j.ok === false) {
      status.textContent = j.message || 'Upload failed.';
      return;
    }

    // Preview and page count
    const pngUrl = j.cog_image_url ?? j.public_url ?? j.url ?? '';
    const pdfUrl = j.cog_pdf_url  ?? (/\.(pdf)$/i.test(pngUrl) ? pngUrl : '');

    serverPaths.cog_img = j.cog_image_path ?? j.cog_path ?? j.absolute_path ?? j.full_path ?? j.abs_path ?? j.file_path ?? j.filepath ?? j.storage_path ?? j.path ?? '';
    serverPaths.cog_pdf_url = pdfUrl || '';

    if (f.type === 'application/pdf' || pdfUrl) {
      // Render first page preview via pdf.js
      const urlForPdf = pdfUrl || URL.createObjectURL(f);
      const canvas = await renderPdfUrlFirstPageToCanvas(urlForPdf);
      canvas.className = 'img-fluid rounded border';
      canvas.style.maxHeight = '380px';
      holder.innerHTML = '';
      holder.appendChild(canvas);

      // Get page count
      const pdfDoc = await pdfjsLib.getDocument({ url: urlForPdf }).promise;
      const pages = pdfDoc.numPages || 1;
      pagesEl.textContent = `Pages: ${pages}`;
      pagesEl.style.display = 'block';
      if (!pdfUrl) URL.revokeObjectURL(urlForPdf);
    } else {
      // Image preview
      const imgSrc = pngUrl || URL.createObjectURL(f);
      holder.innerHTML = `<img src="${imgSrc}" class="img-fluid rounded border" style="max-height:380px" />`;
      pagesEl.textContent = 'Pages: 1';
      pagesEl.style.display = 'block';
      if (!pngUrl) setTimeout(()=>URL.revokeObjectURL(imgSrc), 0);
    }

    status.textContent = 'Grades file uploaded.';
  } catch (e) {
    console.error(e);
    status.textContent = 'Upload failed.';
  }
}

/* ===== Validation (readability only) ===== */
function setValState(id, state, extra=''){
  const el = document.getElementById(id); if (!el) return;
  if (state==='loading'){ el.innerHTML = `<span class="spinner"></span><span class="val-muted">Checking…</span>`; return; }
  if (state==='ok'){ el.innerHTML = `✅ <span class="val-ok">Passed</span>` + (extra?` <small class="val-muted">(${extra})</small>`:''); return; }
  if (state==='fail'){ el.innerHTML = `❌ <span class="val-fail">Failed</span>` + (extra?` <small class="val-muted">(${extra})</small>`:''); }
}
async function runReadabilityValidation(){
  setValState('v-tamper','loading');
  try{
    const pagesEl= document.getElementById('grades-pages');
    const text = pagesEl?.textContent || '';
    const m = text.match(/Pages:\s*(\d+)/i);
    const pages = m ? parseInt(m[1],10) : 0;
    if (pages >= 1) {
      setValState('v-tamper','ok', `${pages} page(s) readable`);
      validationPass = true;
    } else {
      setValState('v-tamper','fail','No pages detected');
      validationPass = false;
    }
  }catch{
    setValState('v-tamper','fail','Validation error');
    validationPass = false;
  }
}

/* ===== Save Grad Form (unchanged) ===== */
async function saveGraduationFormFields() {
  const form = document.getElementById('applicationForm');
  const csrf = csrfToken();
  const fd = new FormData();
  fd.append('birthdate', form.elements['birthdate']?.value || '');
  fd.append('place_of_birth', form.elements['place_of_birth']?.value || '');
  fd.append('home_address', form.elements['home_address']?.value || '');
  fd.append('zip_code', form.elements['zip_code']?.value || '');
  fd.append('secondary_school', form.elements['secondary_school']?.value || '');
  fd.append('secondary_year', form.elements['secondary_year']?.value || '');
  fd.append('elementary_school', form.elements['elementary_school']?.value || '');
  fd.append('elementary_year', form.elements['elementary_year']?.value || '');
  fd.append('_token', csrf);
  try {
    const response = await fetch('/student/graduation-form', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd });
    return response.ok;
  } catch { return false; }
}

/* ===== Small helpers kept from your original ===== */
function previewPDF(file, containerId){
  renderPdfFirstPageToCanvas(file).then(c => {
    const holder = document.getElementById(containerId);
    if (holder) { holder.innerHTML = ''; c.className = 'img-fluid rounded border'; c.style.maxHeight = '420px'; holder.appendChild(c); }
  }).catch(()=>{});
}
async function renderPdfFirstPageToCanvas(file){
  const buf   = await file.arrayBuffer();
  const pdf   = await pdfjsLib.getDocument({ data: buf }).promise;
  const page  = await pdf.getPage(1);
  const vport = page.getViewport({ scale: 2.0 });
  const c = document.createElement('canvas');
  c.width = vport.width; c.height = vport.height;
  const ctx = c.getContext('2d', { willReadFrequently: true });
  await page.render({ canvasContext: ctx, viewport: vport }).promise;
  return c;
}
async function renderPdfUrlFirstPageToCanvas(url){
  const pdf = await pdfjsLib.getDocument({ url }).promise;
  const page = await pdf.getPage(1);
  const vport = page.getViewport({ scale: 2.0 });
  const c = document.createElement('canvas');
  c.width = vport.width; c.height = vport.height;
  const ctx = c.getContext('2d', { willReadFrequently: true });
  await page.render({ canvasContext: ctx, viewport: vport }).promise;
  return c;
}

/* ===== PDF generation + submit (unchanged) ===== */
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
    return fetch(url, { method: 'POST', headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' }, body: JSON.stringify(payload) });
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
  const el = document.getElementById('confirmSubmitModal');
  if (!window.bootstrap?.Modal || !el) return;
  const modal  = bootstrap.Modal.getOrCreateInstance(el);
  modal.show();
  yesBtn.onclick = async () => {
    yesBtn.disabled = true;
    try { await submitApplication(); modal.hide(); } finally { yesBtn.disabled = false; }
  };
}
async function submitApplication(){
  const state = collectCurrentStateFromUI();
  const gwa = state.gwa || '';
  const rank = '';
  const fileInput = document.getElementById('file-grade');
  const file = fileInput && fileInput.files && fileInput.files[0];
  if (!file) return alert('Please upload your COG before submitting.');

  const formData = new FormData();
  formData.append('type', 'DeanLister');
  formData.append('file_name', file.name);
  formData.append('gwa', gwa);
  formData.append('rank', rank);
  formData.append('context', JSON.stringify(state));
  formData.append('file', file);
  formData.append('_token', csrfToken());
  try {
    const resp = await fetch('/student/application/submit', { method: 'POST', body: formData });
    const data = await resp.json();
    if (data.ok) {
      const el = document.getElementById('successSubmitModal');
      if (window.bootstrap?.Modal && el) bootstrap.Modal.getOrCreateInstance(el).show();
    } else {
      alert('Submission failed: ' + (data.message || 'Unknown error'));
    }
  } catch (e) { alert('Submission error: ' + (e.message || e)); }
}

document.addEventListener('DOMContentLoaded', () => {
  updateStepperUI();
  document.getElementById('successOkBtn')?.addEventListener('click', () => {
    const meta = document.querySelector('meta[name="route-application-status"]');
    window.location.href = meta ? meta.content : '/student/applicationstatus';
  });

  const cogInput = document.getElementById('file-grade');
  if (cogInput) cogInput.addEventListener('click', () => {
    cogInput.value = '';
    const ok = document.getElementById('save-feedback'); if (ok) ok.style.display = 'none';
  });
  const corInput = document.getElementById('file-cor');
  if (corInput) corInput.addEventListener('click', () => { corInput.value = ''; });
});
</script>

@endsection
