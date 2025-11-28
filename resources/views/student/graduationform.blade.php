@extends('student.studentsidebar')

@section('content')

@php
    $isApplicationClosed = $isApplicationClosed ?? false;
    use Illuminate\Support\Facades\Route as RouteFacade;
    $gradReqUrl = RouteFacade::has('student.graduation.requirements.store')
        ? route('student.graduation.requirements.store')
        : url('/student/graduation/requirements');
@endphp

<link rel="stylesheet" href="{{ asset('css/application.css') }}">

<style>
  .tampered-row { background: #fff3f3 !important; }
  .tampered-row td { border-top-color: #f3b7b7 !important; }
  .qr-grade-badge{ display:none }
  .spinner{ width:14px;height:14px;border:2px solid #ddd;border-top-color:#0d6efd;border-radius:50%;display:inline-block;animation:spin .8s linear infinite;margin-right:.35rem }
  @keyframes spin{ to { transform: rotate(360deg)} }

  .pdf-pages-wrap{ display:flex; gap:12px; flex-wrap:wrap; justify-content:center }
  .pdf-page{ position:relative; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.04); background:#fff }
  .pdf-page canvas{ display:block; height:auto; max-height:380px }
  .page-badge{ position:absolute; bottom:6px; right:8px; background:#111827; color:#fff; font-size:12px; line-height:1; padding:4px 6px; border-radius:6px; opacity:.85 }

  #preview-cor{ display:flex; align-items:center; justify-content:center; min-height:260px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:16px; }
  #preview-cor > *{ margin:auto; }
  #preview-cor img, #preview-cor iframe, #preview-cor canvas{ display:block; margin:0 auto; max-width:100%; max-height:420px; border-radius:8px; }

  .req-card { border:1px solid #e5e7eb; border-radius:14px; padding:16px; background:#fff }
  .req-title { font-weight:700; }
  .req-optional { font-size:12px; color:#6b7280; }
  .req-tofollow { display:flex; align-items:center; gap:8px; }
  .req-badge { display:none; align-items:center; gap:6px; color:#b91c1c; font-weight:700; font-size:13px; }
  .req-badge .dot { display:inline-block; width:10px; height:10px; border-radius:50%; background:#b91c1c; }
  .req-wrap.to-follow .req-badge { display:inline-flex; }
  .req-wrap.to-follow input[type="file"] { opacity:.5; pointer-events:none; }
  .help { font-size:12px; color:#6b7280; }

  /* All Grades Styles */
  .allgrades-wrapper{ background:#f3f4f6; padding:18px; }
  .allgrades-page{ background:#ffffff; border:1px solid #d1d5db; margin:0 auto; max-width:1024px; padding:18px 22px; box-shadow:0 4px 12px rgba(0,0,0,.05); font-size:13px; color:#111827; }
  .allgrades-page table{ width:100%; border-collapse:collapse; font-size:12px; }
  .allgrades-page th, .allgrades-page td{ border:1px solid #111; padding:4px 6px; }
  .grades-accordion .accordion-button:not(.collapsed) { background-color: #e7f1ff; color: #0d6efd; }
  .grade-badge-completed { background-color: #d1e7dd; color: #0f5132; }
  .grade-badge-failed { background-color: #f8d7da; color: #721c24; }
  .grade-badge-incomplete { background-color: #fff3cd; color: #856404; }

    /* Graduation Evaluation Styles */
  .graduation-status-graduated { background-color: #d1e7dd; color: #0f5132; }
  .graduation-status-not-graduated { background-color: #f8d7da; color: #721c24; }
  .graduation-status-pending { background-color: #fff3cd; color: #856404; }

  .course-code { font-family: 'Courier New', monospace; font-weight: bold; }
  .evaluation-table th { background-color: #f8f9fa; }
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
  <meta name="route-cog-upload" content="{{ route('student.graduation.cog.upload') }}">
  <meta name="route-cor-upload" content="{{ route('student.graduation.cor.upload') }}">
  <meta name="route-curriculum" content="{{ route('student.curriculum.subjects') }}">
  <meta name="route-postal-zip" content="{{ url('/postal-ph/zip') }}">
  <meta name="route-grad-save" content="{{ route('student.graduationform.save') }}">
  <meta name="route-grad-generate" content="{{ route('student.graduationform.generate') }}">
  <meta name="route-consent-generate" content="{{ route('student.latin-honors.consent.generate') }}">
  <meta name="route-grad-req-submit" content="{{ $gradReqUrl }}">
  <meta name="route-all-grades" content="{{ route('student.grades.all-modal') }}">
  {{-- Change these meta tags to use the correct route names --}}
  <meta name="route-graduation-evaluate" content="{{ route('student.graduation.evaluate-cor') }}">
  <meta name="route-graduation-status" content="{{ route('student.graduation.evaluation') }}">

  {{-- Public URL to the consent PDF --}}
  <meta name="consent-form-url" content="{{ asset('storage/pdf_templates/BatStateU-FO-REG-09_Consent Form for the Evaluation of Academic Records_Rev. 03.pdf') }}">

  <form id="applicationForm" method="POST" enctype="multipart/form-data" action="javascript:void(0)">
    @csrf

    {{-- ===== STEPPER ===== --}}
    <div class="am-stepper-wrap mb-5">
      <div class="am-stepper-line"><div class="am-stepper-line-fill" id="am-stepper-line-fill"></div></div>
      @php $steps = ['Guidelines','Upload COR','Academic Records','Validation','Fill Up','Generate Application','Requirements Upload']; @endphp
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

    <div class="d-flex justify-content-center">
      <div class="card shadow rounded-4 w-100" style="max-width: 1200px;">
        <div class="card-body px-4 py-5" id="step-content">
          <h5 class="fw-bold mb-4" id="card-title">
            <i class="bi bi-pin-angle-fill text-danger me-2"></i>Guidelines
          </h5>

            {{-- Step 1 --}}
            <div class="step-section" id="step-1">
              <h4 class="fw-bold mb-3">Graduation Guidelines</h4>
              <p class="text-muted">
                A student's completion of academic requirements is recognized through the
                <strong>Graduation Application Process</strong>, which ensures that only qualified candidates are endorsed for graduation.
              </p>

              <ol class="fs-6" style="line-height:1.9;">
                <li>
                  apply for graduation within the prescribed schedule set by the university;
                </li>

                <li>
                  upload the required documents in the system, including:
                  <ul class="ms-4 mt-2">
                    <li>a copy of the <strong>Final Grades</strong>, and</li>
                    <li>a <strong>Certificate of Current Enrollment</strong> (or Certificate of Registration);</li>
                  </ul>
                </li>

                <li>
                  ensure that all uploaded grades correspond to the approved curriculum and that no subjects or grades are missing;
                </li>

                <li>
                  if only the <strong>OJT (On-the-Job Training)</strong> grade is missing, ensure that the OJT description in the Certificate of Registration matches the expected subject description;
                </li>

                <li>
                  upon successful validation, wait for the system prompt indicating status, such as:
                  <ul class="ms-4 mt-2">
                    <li>"Congratulations! You are eligible for the Dean's List."</li>
                    <li>"Congratulations! You are eligible for graduation."</li>
                  </ul>
                </li>

                <li>
                  once declared eligible, accomplish the following:
                  <ul class="ms-4 mt-2">
                    <li>provide <strong>Guardian Information</strong>,</li>
                    <li>attach all <strong>required supporting documents</strong>, and</li>
                    <li>(optional) upload the <strong>Approval Sheet</strong>;</li>
                  </ul>
                </li>

                <li>
                  address any noted deficiencies before submitting the graduation application; and
                </li>

                <li>
                  submit the completed <strong>Graduation Application Form</strong> for Program Chair review.
                </li>
              </ol>
            </div>

          {{-- Step 2: COR --}}
          <div class="text-start step-section" id="step-2" style="display: none;">
            <h4 class="fw-bold mb-2">Upload Your Certificate of Registration (COR)</h4>
            <ul class="small text-muted mb-3">
              <li>Accepted format: <strong>PDF only</strong></li>
              <li>Make sure the file is clear, complete, and official</li>
              <li>Example filename: <code>Lastname_Firstname_COR.pdf</code></li>
            </ul>
            <div class="text-center">
              <input type="file" name="cor" id="file-cor" accept="application/pdf" class="form-control w-50 mx-auto" required onchange="uploadCorPdf()" />
              <div class="mt-3" id="preview-cor"></div>
              <small id="cor-status" class="text-muted d-block mt-2"></small>
            </div>
          </div>

          {{-- Step 3: Academic Records --}}
          <div class="text-start step-section" id="step-3" style="display: none;">
            <h4 class="fw-bold mb-2">Student Academic Records</h4>
            <ul class="small text-muted mb-3">
              <li>View all your academic grades and records from the university database</li>
              <li>Ensure all grades are complete and accurate before proceeding</li>
              <li>This data is fetched directly from your official student records</li>
            </ul>
            
            <div class="text-center">
              <button type="button" id="fetch-all-grades-btn" class="btn btn-primary mb-4" onclick="fetchAllStudentGrades()">
                <span id="fetch-all-grades-text">Load My Academic Records</span>
                <span id="fetch-all-grades-spinner" class="spinner" style="display: none;"></span>
              </button>
              
              {{-- Grades Summary --}}
              <div class="mt-3" id="grades-summary-container" style="display: none;">
                {{-- Summary will be displayed here --}}
              </div>
              
              {{-- All Grades Display --}}
              <div class="mt-4" id="all-grades-container">
                <div class="text-center text-muted py-4">
                  <i class="bi bi-journal-text" style="font-size: 3rem;"></i>
                  <p class="mt-2">Click "Load My Academic Records" to view your complete grade history</p>
                </div>
              </div>
              
              <small id="grades-status" class="text-muted d-block mt-2"></small>
            </div>
          </div>

          {{-- Step 4: Validation --}}
          <div class="text-start step-section" id="step-4" style="display:none;">
            <p class="mb-2 text-muted">Checks performed before proceeding:</p>
            <div class="val-item mb-2"><div class="val-left">1. File Readability</div><div class="val-right" id="v-tamper"><span class="spinner"></span><span class="val-muted">Validating…</span></div></div>
            <div class="val-item mb-2"><div class="val-left">2. Curriculum Match</div><div class="val-right" id="v-irregular"><span class="val-muted">Skipped (no QR)</span></div></div>
            <div class="val-item"><div class="val-left">3. Grade Eligibility</div><div class="val-right" id="v-grades"><span class="val-muted">Manual check after review</span></div></div>
            <small class="text-muted d-block mt-3">You can proceed once the file passes readability.</small>
          </div>

          {{-- Step 5: Fill Up --}}
          <div class="text-start step-section" id="step-5" style="display: none;">
            <h4 class="fw-bold mb-4">Fill Up Graduation Form</h4>
            <div class="row g-3">
              <div class="col-md-3"><label class="form-label">Surname</label><input type="text" class="form-control bg-light" name="surname" value="{{ $prefill['surname'] ?? '' }}" readonly></div>
              <div class="col-md-3"><label class="form-label">First Name</label><input type="text" class="form-control bg-light" name="first_name" value="{{ $prefill['first_name'] ?? '' }}" readonly></div>
              <div class="col-md-3"><label class="form-label">Middle Name</label><input type="text" class="form-control bg-light" name="middle_name" value="{{ $prefill['middle_name'] ?? '' }}" readonly></div>
              <div class="col-md-3"><label class="form-label">Ext.</label><input type="text" class="form-control" name="ext" value="{{ $prefill['ext'] ?? '' }}"></div>

              <div class="col-md-3"><label class="form-label">SR Code</label><input type="text" class="form-control bg-light" name="sr_code" value="{{ $prefill['sr_code'] ?? '' }}" readonly></div>
              <div class="col-md-3"><label class="form-label">Birthdate</label><input type="date" class="form-control" name="birthdate" value="{{ $prefill['birthdate'] ?? '' }}" required></div>
              <div class="col-md-3"><label class="form-label">Place of Birth</label><input type="text" class="form-control" name="place_of_birth" value="{{ $prefill['place_of_birth'] ?? '' }}"></div>
              <div class="col-md-3"><label class="form-label">Contact Number</label><input type="text" class="form-control bg-light" name="contact_number" value="{{ $prefill['contact_number'] ?? '' }}" readonly></div>

              <div class="col-12"><label class="form-label">Email</label><input type="email" class="form-control bg-light" name="email" value="{{ $prefill['email'] ?? '' }}" readonly></div>

              <div class="col-md-6"><label class="form-label">Scholarship Grant</label><input type="text" class="form-control" name="scholarship_grant" value="{{ $prefill['scholarship_grant'] ?? '' }}"></div>
              <div class="col-md-6"><label class="form-label">Parent 1 (Full Name)</label><input type="text" class="form-control" name="parent1" value="{{ $prefill['parent1'] ?? '' }}"></div>
              <div class="col-md-6"><label class="form-label">Parent 1 Contact</label><input type="text" class="form-control" name="parent1_contact" value="{{ $prefill['parent1_contact'] ?? '' }}"></div>
              <div class="col-md-6"><label class="form-label">Parent 2 (Full Name)</label><input type="text" class="form-control" name="parent2" value="{{ $prefill['parent2'] ?? '' }}"></div>
              <div class="col-md-6"><label class="form-label">Parent 2 Contact</label><input type="text" class="form-control" name="parent2_contact" value="{{ $prefill['parent2_contact'] ?? '' }}"></div>

              <div class="col-md-12"><label class="form-label">Region</label><select class="form-select" name="region"><option>Select Region</option></select></div>
              <div class="col-md-12"><label class="form-label">Province</label><select class="form-select" name="province"><option>Select Province</option></select></div>
              <div class="col-md-12"><label class="form-label">City/Municipality</label><select class="form-select" name="city"><option>Select City/Municipality</option></select></div>
              <div class="col-md-8"><label class="form-label">Barangay</label><select class="form-select" name="barangay"><option>Select Barangay</option></select></div>
              <div class="col-md-4"><label class="form-label">ZIP Code</label><input type="text" class="form-control" name="zip_code" value="{{ $prefill['zip_code'] ?? '' }}"></div>
              <div class="col-md-12"><label class="form-label">House No./Street/Subdivision (optional)</label><input type="text" class="form-control" name="home_address" value="{{ $prefill['home_address'] ?? '' }}"></div>

              <div class="col-md-6"><label class="form-label">Secondary School Graduated</label><input type="text" class="form-control" name="secondary_school" value="{{ $prefill['secondary_school'] ?? '' }}"></div>
              <div class="col-md-6"><label class="form-label">Year Graduated</label><input type="text" class="form-control" name="secondary_year" value="{{ $prefill['secondary_year'] ?? '' }}"></div>
              <div class="col-md-6"><label class="form-label">Elementary School Graduated</label><input type="text" class="form-control" name="elementary_school" value="{{ $prefill['elementary_school'] ?? '' }}"></div>
              <div class="col-md-6"><label class="form-label">Year Graduated</label><input type="text" class="form-control" name="elementary_year" value="{{ $prefill['elementary_year'] ?? '' }}"></div>

              <div class="col-md-6"><label class="form-label">College</label><input type="text" class="form-control bg-light" name="college" value="{{ $prefill['college'] ?? '' }}" readonly></div>
              <div class="col-md-6"><label class="form-label">Program</label><input type="text" class="form-control bg-light" name="program" value="{{ $prefill['program'] ?? '' }}" readonly></div>

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

              <div class="col-md-12"><label class="form-label">Major</label><input type="text" class="form-control bg-light" name="major" value="{{ $prefill['major'] ?? '' }}" readonly></div>
            </div>
          </div>

          {{-- PSGC + ZIP --}}
          <script>
          (function () {
            const elRegion   = document.querySelector('select[name="region"]');
            const elProvince = document.querySelector('select[name="province"]');
            const elCity     = document.querySelector('select[name="city"]');
            const elBrgy     = document.querySelector('select[name="barangay"]');
            const elZip      = document.querySelector('input[name="zip_code"]');
            const elHome     = document.querySelector('input[name="home_address"]');

            if (!elRegion || !elProvince || !elCity || !elBrgy) return;

            if (elHome) elHome.addEventListener('input', () => { elHome.dataset.userEdited = '1'; });

            const PSGC = {
              BASE: 'https://psgc.gitlab.io/api',
              async get(path) { const res = await fetch(`${this.BASE}${path}`, { headers: { 'Accept': 'application/json' }}); if (!res.ok) throw new Error(`${path} -> ${res.status}`); return res.json(); },
              regions()                 { return this.get('/regions/'); },
              provincesOf(regCode)      { return this.get(`/regions/${regCode}/provinces/`); },
              citiesMunicipalitiesOf(p) { return this.get(`/provinces/${p}/cities-municipalities/`); },
              barangaysOf(cmCode)       { return this.get(`/cities-municipalities/${cmCode}/barangays/`); },
            };

            function opt(label, value){ const o=document.createElement('option'); o.textContent=label; o.value=value??''; return o; }
            function reset(sel, ph){ sel.innerHTML=''; sel.appendChild(opt(ph,'')); }
            function busy(sel, b){ sel.disabled=!!b; sel.classList.toggle('disabled',!!b); }
            function txt(sel){ return sel.options[sel.selectedIndex]?.text?.trim() || ''; }

            function metaZipUrl(){ return document.querySelector('meta[name="route-postal-zip"]')?.content || '/postal-ph/zip'; }
            function norm(s){
              const v=(s||'').trim();
              if (/^select (region|province|city|municipality|barangay)$/i.test(v)) return '';
              return v.toLowerCase().replace(/^(city of|municipality of)\s+/,'').replace(/\s+city$/,'').replace(/\s+/g,' ').trim();
            }
            function setHomeAuto(addr){
              if (!elHome) return;
              const edited = elHome.dataset.userEdited === '1';
              const prev   = elHome.dataset.autofill || '';
              if (!edited || elHome.value === prev || !elHome.value){
                elHome.value = addr; elHome.dataset.autofill = addr;
              }
            }
            function formatAddr(brgy, city, prov){
              const parts=[]; if (brgy) parts.push(brgy); if (city) parts.push(city); if (prov) parts.push(prov); return parts.join(', ');
            }

            async function loadRegions(){
              reset(elRegion,'Select Region'); reset(elProvince,'Select Province'); reset(elCity,'Select City/Municipality'); reset(elBrgy,'Select Barangay'); if (elZip) elZip.value='';
              busy(elRegion,true);
              try {
                const rows = await PSGC.regions();
                rows.sort((a,b)=>a.name.localeCompare(b.name)).forEach(r => elRegion.appendChild(opt(r.name, r.code)));
              } finally { busy(elRegion,false); }
            }
            async function loadProvinces(regCode){
              reset(elProvince,'Select Province'); reset(elCity,'Select City/Municipality'); reset(elBrgy,'Select Barangay'); if (elZip) elZip.value='';
              if(!regCode) return;
              busy(elProvince,true);
              try {
                const rows = await PSGC.provincesOf(regCode);
                rows.sort((a,b)=>a.name.localeCompare(b.name)).forEach(p => elProvince.appendChild(opt(p.name, p.code)));
              } finally { busy(elProvince,false); }
            }
            async function loadCities(provCode){
              reset(elCity,'Select City/Municipality'); reset(elBrgy,'Select Barangay'); if (elZip) elZip.value='';
              if(!provCode) return;
              busy(elCity,true);
              try {
                const rows = await PSGC.citiesMunicipalitiesOf(provCode);
                rows.sort((a,b)=>a.name.localeCompare(b.name)).forEach(c => elCity.appendChild(opt(c.name, c.code)));
              } finally { busy(elCity,false); }
            }
            async function loadBarangays(cmCode){
              reset(elBrgy,'Select Barangay'); if (elZip) elZip.value='';
              if(!cmCode) return;
              busy(elBrgy,true);
              try {
                const rows = await PSGC.barangaysOf(cmCode);
                rows.sort((a,b)=>a.name.localeCompare(b.name)).forEach(b => elBrgy.appendChild(opt(b.name, b.code)));
              } finally { busy(elBrgy,false); }
              debounce(resolveZip, 200);
            }

            let t=null; function debounce(fn,ms){ clearTimeout(t); t=setTimeout(fn,ms); }

            async function resolveZip(){
              const provTxt = txt(elProvince);
              const cityTxt = txt(elCity);
              const brgyTxt = txt(elBrgy);

              setHomeAuto(formatAddr(norm(brgyTxt)?brgyTxt:'', cityTxt, provTxt));

              const province = norm(provTxt);
              const city     = norm(cityTxt);
              const barangay = norm(brgyTxt);
              if (!province || !city){ if (elZip) elZip.value=''; return; }

              try{
                const url = metaZipUrl() + '?' + new URLSearchParams({ province, city, barangay }).toString();
                const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
                const j   = await res.json();
                if (elZip) elZip.value = j?.zip ?? '';
              }catch{ if (elZip) elZip.value=''; }
            }

            elRegion.addEventListener('change', () => loadProvinces(elRegion.value));
            elProvince.addEventListener('change', () => loadCities(elProvince.value));
            elCity.addEventListener('change', () => { loadBarangays(elCity.value); debounce(resolveZip, 10); });
            elBrgy.addEventListener('change', () => debounce(resolveZip, 10));
            document.addEventListener('DOMContentLoaded', loadRegions);
          })();
          </script>

          {{-- Step 6: Generate Application --}}
          <div class="text-start step-section" id="step-6" style="display: none;">
            <h4 class="fw-bold mb-3">Generate Application</h4>
            <div class="mb-3 d-flex align-items-center gap-2">
              <button id="pdf-generate-btn" type="button" class="btn btn-primary" onclick="generatePdf()">Generate / Refresh Application PDF</button>
              <span id="pdf-status" class="text-muted"></span>
            </div>
            <iframe id="pdf-frame" src="" width="100%" height="620px" class="rounded border shadow-sm"></iframe>
            <div class="mt-2 d-flex justify-content-between">
              <a id="pdf-open-link" href="#" target="_blank" class="small" style="display:none;">Open PDF in new tab</a>
              <span class="small text-muted">Click <strong>Next</strong> to provide the final requirements.</span>
            </div>
          </div>

          {{-- Step 7: Requirements Upload --}}
          <div class="text-start step-section" id="step-7" style="display:none;">
            <h4 class="fw-bold mb-3">Requirements Upload</h4>
            <p class="text-muted small mb-4">Upload the following. For the two required items, you may mark <strong>To Follow</strong>.</p>

            <div class="row g-3">
              <div class="col-md-6">
                <div class="req-card req-wrap" id="req-approval-wrap">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="req-title">Approval Sheet <span class="text-danger">*</span></div>
                    <div class="req-badge" id="req-approval-badge"><span class="dot"></span> To Follow</div>
                  </div>
                  <div class="help mb-2">PDF or Image (JPG/PNG)</div>
                  <input type="file" class="form-control" id="req-approval-file" accept="application/pdf,image/*" />
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <label class="req-tofollow">
                      <input type="checkbox" id="req-approval-tf" onchange="toggleToFollow('approval')" />
                      <span>Mark as <strong>To Follow</strong></span>
                    </label>
                    <small id="req-approval-status" class="text-muted"></small>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="req-card req-wrap" id="req-library-wrap">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="req-title">Certificate of Library <span class="text-danger">*</span></div>
                    <div class="req-badge" id="req-library-badge"><span class="dot"></span> To Follow</div>
                  </div>
                  <div class="help mb-2">PDF or Image (JPG/PNG)</div>
                  <input type="file" class="form-control" id="req-library-file" accept="application/pdf,image/*" />
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <label class="req-tofollow">
                      <input type="checkbox" id="req-library-tf" onchange="toggleToFollow('library')" />
                      <span>Mark as <strong>To Follow</strong></span>
                    </label>
                    <small id="req-library-status" class="text-muted"></small>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="req-card">
                  <div class="req-title">Barangay Clearance <span class="req-optional">(optional)</span></div>
                  <div class="help mb-2">PDF or Image (JPG/PNG)</div>
                  <input type="file" class="form-control" id="req-brgy-file" accept="application/pdf,image/*" />
                  <small id="req-brgy-status" class="text-muted"></small>
                </div>
              </div>

              <div class="col-md-6">
                <div class="req-card">
                  <div class="req-title">Birth Certificate <span class="req-optional">(optional)</span></div>
                  <div class="help mb-2">PDF or Image (JPG/PNG)</div>
                  <input type="file" class="form-control" id="req-birth-file" accept="application/pdf,image/*" />
                  <small id="req-birth-status" class="text-muted"></small>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

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

{{-- Latin Honors Decision Modal --}}
<div class="modal fade" id="latinHonorsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Apply for Latin Honors?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">You want to apply latin honors?</div>
      <div class="modal-footer">
        <button type="button" id="latinHonorsNoBtn" class="btn btn-outline-secondary" data-bs-dismiss="modal">No, thanks</button>
        <button type="button" id="latinHonorsYesBtn" class="btn btn-primary">Yes</button>
      </div>
    </div>
  </div>
</div>

{{-- Submit Success Fallback Modal --}}
<div class="modal fade" id="successSubmitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-body py-4 text-center">
        <div class="mb-2">Application submitted.</div>
        <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="consentPdfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content rounded-4 overflow-hidden">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Consent Form for the Evaluation of Academic Records</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" style="height:80vh;">
        <iframe id="consentPdfFrame" src="" width="100%" height="100%" style="border:0;"></iframe>
      </div>
      <div class="modal-footer">
        <a id="consentOpenNewTab" class="btn btn-outline-secondary" target="_blank">Open in new tab</a>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>

{{-- PDF.js --}}
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script>
  if (window.pdfjsLib?.GlobalWorkerOptions) {
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = "{{ asset('vendor/pdfjs/pdf.worker.min.js') }}";
  }
</script>

<script>
/* ===== Globals ===== */
const APP_CLOSED = @json($isApplicationClosed);
let currentStep = 1;
let isBusy = false;
let validationPass = false;

/* Requirements state */
const reqState = {
  approval: { toFollow:false, file:null },
  library:  { toFollow:false, file:null },
  brgy:     { file:null },
  birth:    { file:null }
};

/* Server paths & generated application PDF cache */
const serverPaths = {
  cor_pdf:'', cor_img:'', cor_text:'', cor_public_preview:'',
  cog_img:'', cog_pdf_url:'',
  app_pdf_url:'' // URL returned by Step 6 generator
};

/* ===== LATIN HONORS ELIGIBILITY FUNCTIONS ===== */
function parseGradesFromHTML(html) {
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    
    // Look for grade patterns in tables
    const gradeCells = tempDiv.querySelectorAll('td');
    const grades = [];
    
    gradeCells.forEach(cell => {
        const text = cell.textContent.trim();
        // Match grade patterns: 1.75, 2.00, 3.00, INC, DRP, etc.
        const gradeMatch = text.match(/([1-3]\.\d{2}|INC|DRP)/i);
        if (gradeMatch) {
            grades.push(gradeMatch[1].toUpperCase());
        }
    });
    
    return grades;
}

function checkLatinHonorsEligibility(gradesData) {
    if (!gradesData || !gradesData.html) {
        return { eligible: false, reason: 'No grade data available' };
    }

    // Use enhanced parsing
    const grades = parseGradesFromHTML(gradesData.html);
    
    if (grades.length === 0) {
        return { eligible: false, reason: 'No grades found in academic records' };
    }

    // Check for INC grades (automatic disqualification)
    const hasINC = grades.some(grade => grade.includes('INC'));
    if (hasINC) {
        return { eligible: false, reason: 'Has INC grade(s) - automatic disqualification' };
    }

    // Check for DRP grades (usually disqualifying)
    const hasDRP = grades.some(grade => grade.includes('DRP'));
    if (hasDRP) {
        return { eligible: false, reason: 'Has DRP grade(s)' };
    }

    // Convert grades to numbers for calculation
    const numericGrades = grades
        .filter(grade => !isNaN(parseFloat(grade)))
        .map(grade => parseFloat(grade));

    if (numericGrades.length === 0) {
        return { eligible: false, reason: 'No valid numeric grades found' };
    }

    // Calculate GWA
    const gwa = numericGrades.reduce((sum, grade) => sum + grade, 0) / numericGrades.length;
    const lowestGrade = Math.min(...numericGrades);

    // Check Latin Honors eligibility based on guidelines
    let eligible = false;
    let honorsLevel = '';

    // SUMMA CUM LAUDE criteria
    if (lowestGrade <= 1.75 && gwa >= 1.0 && gwa <= 1.25) {
        eligible = true;
        honorsLevel = 'SUMMA CUM LAUDE';
    }
    // MAGNA CUM LAUDE criteria
    else if (lowestGrade <= 2.0 && gwa >= 1.26 && gwa <= 1.5) {
        eligible = true;
        honorsLevel = 'MAGNA CUM LAUDE';
    }
    // CUM LAUDE criteria
    else if (lowestGrade <= 2.5 && gwa >= 1.51 && gwa <= 1.75) {
        eligible = true;
        honorsLevel = 'CUM LAUDE';
    }
    // OUTSTANDING criteria - note: this requires checking if no one else qualifies
    // For individual student check, we'll be conservative
    else if (lowestGrade <= 3.0 && gwa <= 1.99) {
        eligible = true;
        honorsLevel = 'POTENTIAL OUTSTANDING AWARD';
    }

    return {
        eligible,
        honorsLevel,
        gwa: gwa.toFixed(4),
        lowestGrade: lowestGrade.toFixed(2),
        totalGrades: numericGrades.length,
        reason: eligible ? '' : `Not qualified for Latin Honors (GWA: ${gwa.toFixed(4)}, Lowest Grade: ${lowestGrade.toFixed(2)})`
    };
}

async function checkLatinHonorsEligibilityFromServer() {
    try {
        const response = await fetch(document.querySelector('meta[name="route-all-grades"]').content, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (response.ok) {
            const gradesData = await response.json();
            return checkLatinHonorsEligibility(gradesData);
        }
    } catch (error) {
        console.error('Error checking Latin Honors eligibility:', error);
    }
    
    return { eligible: false, reason: 'Unable to verify grades' };
}

function showLatinHonorsModal(eligibility) {
    const el = document.getElementById('latinHonorsModal');
    if (!window.bootstrap?.Modal || !el) {
        if (confirm(`You qualify for ${eligibility.honorsLevel}! Do you want to apply for Latin Honors?`)) {
            handleLatinHonorsYes();
        } else {
            handleLatinHonorsNo();
        }
        return;
    }
    
    // Update modal content with eligibility information
    const modalBody = el.querySelector('.modal-body');
    if (modalBody) {
        modalBody.innerHTML = `
            <div class="text-center">
                <div class="alert alert-success mb-3">
                    <h6><i class="bi bi-award-fill me-2"></i>Congratulations!</h6>
                    <p class="mb-1">You qualify for <strong>${eligibility.honorsLevel}</strong></p>
                    <small class="text-muted">GWA: ${eligibility.gwa} | Lowest Grade: ${eligibility.lowestGrade}</small>
                </div>
                <p>Would you like to apply for Latin Honors?</p>
            </div>
        `;
    }
    
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();

    const yes = document.getElementById('latinHonorsYesBtn');
    const no  = document.getElementById('latinHonorsNoBtn');

    yes.onclick = async () => {
        yes.disabled = true;
        try { await handleLatinHonorsYes(); modal.hide(); }
        finally { yes.disabled = false; }
    };

    no.onclick = () => handleLatinHonorsNo();
}

function showSubmissionSuccess(eligibility) {
    const el = document.getElementById('successSubmitModal');
    if (!window.bootstrap?.Modal || !el) {
        alert('Application submitted successfully!');
        handleLatinHonorsNo();
        return;
    }
    
    // Update modal content
    const modalBody = el.querySelector('.modal-body');
    if (modalBody) {
        let message = '<div class="text-center">';
        message += '<div class="mb-3"><i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i></div>';
        message += '<h5 class="mb-3">Application Submitted Successfully!</h5>';
        
        if (!eligibility.eligible && eligibility.reason) {
            message += `<div class="alert alert-info mb-3">
                <small><strong>Note:</strong> ${eligibility.reason}</small>
            </div>`;
        }
        
        message += '<p class="text-muted">Your graduation application has been submitted for review.</p>';
        message += '</div>';
        modalBody.innerHTML = message;
    }
    
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();
    
    // Redirect to application status after modal is closed
    modal._element.addEventListener('hidden.bs.modal', function() {
        handleLatinHonorsNo();
    });
}

/* ===== UI helpers ===== */
function setBusy(state){
  isBusy = state;
  const btn = document.getElementById('next-btn');
  if (btn) btn.innerHTML = state ? 'Please wait…' : (currentStep === 7 ? 'Submit' : 'Next');
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

  const HIDE_CARD_TITLE_STEPS = new Set([2,3,5,7]);
  const titles = { 1:'<i class="bi bi-pin-angle-fill text-danger me-2"></i>Guidelines', 4:'Validation', 6:'Generate Application', 7:'Requirements Upload' };
  const ct = document.getElementById('card-title');
  if (ct) { if (HIDE_CARD_TITLE_STEPS.has(currentStep)) { ct.style.display='none'; ct.innerHTML=''; } else { ct.style.display='block'; if (titles[currentStep]) ct.innerHTML=titles[currentStep]; } }

  for (let i=1;i<=7;i++){
    const s = document.getElementById('step-'+i);
    if (s) s.style.display = i===currentStep ? 'block' : 'none';
  }

  const nextBtn = document.getElementById('next-btn');
  if (nextBtn) nextBtn.innerText = currentStep === 7 ? 'Submit' : 'Next';
}

function goNext() {
  if (isBusy) return;

  if (APP_CLOSED) {
    const el = document.getElementById('applicationClosedModal');
    if (window.bootstrap?.Modal && el) bootstrap.Modal.getOrCreateInstance(el).show();
    return;
  }

  // Step 1 -> 2
  if (currentStep === 1) { currentStep = 2; updateStepperUI(); return; }

  // Step 2: COR
  if (currentStep === 2) {
    const corFile  = document.getElementById('file-cor')?.files?.[0];
    const corStat  = document.getElementById('cor-status');
    if (!corFile) { corStat.textContent = 'Please select your COR PDF.'; return; }
    if (corFile.type !== 'application/pdf') { corStat.textContent = 'COR must be a PDF file.'; return; }
    if (!serverPaths.cor_pdf && !serverPaths.cor_img) { corStat.textContent = 'Still processing COR…'; return; }
    currentStep = 3; updateStepperUI(); return;
  }

  // Step 3: Academic Records
  if (currentStep === 3) {
    const gradesStatus = document.getElementById('grades-status');
    const gradesContainer = document.getElementById('all-grades-container');
    
    // Check if grades have been fetched
    if (gradesContainer.innerHTML.includes('Click "Load My Academic Records"')) {
      gradesStatus.textContent = 'Please load your academic records first.';
      gradesStatus.className = 'text-danger d-block mt-2';
      return;
    }
    
    // Check if there was an error in fetching grades
    if (gradesContainer.querySelector('.alert-danger')) {
      gradesStatus.textContent = 'Please resolve grade fetching errors before proceeding.';
      gradesStatus.className = 'text-danger d-block mt-2';
      return;
    }
    
    currentStep = 4; updateStepperUI();
    runReadabilityValidation();
    return;
  }

  // Step 4: Validation
  if (currentStep === 4) {
    if (!validationPass) { alert('Please wait for validation to complete.'); return; }
    currentStep = 5; updateStepperUI(); return;
  }

  // Step 5: Fill Up -> save -> Step 6 (Generate)
  if (currentStep === 5) {
    setBusy(true);
    saveGraduationFormFields().then((ok)=>{ 
      setBusy(false); 
      if (!ok) return alert('Failed to save graduation form.');
      currentStep = 6; updateStepperUI();
      try { generatePdf(); } catch(e){}
    });
    return;
  }

  // Step 6 -> 7
  if (currentStep === 6) { currentStep = 7; updateStepperUI(); return; }

  // Step 7: Requirements -> validate -> Submit
  if (currentStep === 7) {
    const approvalOk = reqState.approval.toFollow || !!document.getElementById('req-approval-file')?.files?.length;
    const libraryOk  = reqState.library.toFollow  || !!document.getElementById('req-library-file')?.files?.length;
    if (!approvalOk || !libraryOk) {
      let msg = 'Please provide the required items:\n';
      if (!approvalOk) msg += '• Approval Sheet (upload a file or mark To Follow)\n';
      if (!libraryOk)  msg += '• Certificate of Library (upload a file or mark To Follow)\n';
      alert(msg);
      return;
    }
    // cache files
    reqState.approval.file = document.getElementById('req-approval-file')?.files?.[0] || null;
    reqState.library.file  = document.getElementById('req-library-file')?.files?.[0]  || null;
    reqState.brgy.file     = document.getElementById('req-brgy-file')?.files?.[0]     || null;
    reqState.birth.file    = document.getElementById('req-birth-file')?.files?.[0]    || null;

    showConfirmSubmitModal();
    return;
  }

  // Fallback
  currentStep = Math.min(currentStep + 1, 7);
  updateStepperUI();
}

function goBack(){ if (isBusy) return; if (currentStep > 1){ currentStep--; updateStepperUI(); }}

function fetchAllStudentGrades() {
    console.log('Fetching all student grades...');
    
    fetch('{{ route("student.grades.all-modal") }}', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Grades data received:', data);
        if (data.success) {
            // Process the grades data for graduation form
            processGradesForGraduation(data.html);
        } else {
            console.error('Server returned error:', data.message);
            alert('Error: ' + (data.message || 'Failed to load grades'));
        }
    })
    .catch(error => {
        console.error('Error fetching grades:', error);
        alert('Failed to load grades: ' + error.message);
    });
}

/* ===== Process Grades for Graduation Form ===== */
function processGradesForGraduation(htmlContent) {
    console.log('Processing grades for graduation form...');
    
    // Display the grades in the container
    const container = document.getElementById('all-grades-container');
    const summaryContainer = document.getElementById('grades-summary-container');
    
    if (!htmlContent) {
        container.innerHTML = `
            <div class="alert alert-warning">
                <i class="bi bi-info-circle-fill"></i>
                No academic records found for your account.
            </div>
        `;
        return;
    }

    // Create a temporary container to parse the HTML
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = htmlContent;
    
    // Extract profile information if available
    const profile = {
        srcode: '',
        fullname: '',
        program: '',
        generated_at: new Date().toLocaleDateString()
    };
    
    // Try to extract profile info from the HTML structure
    const tables = tempDiv.querySelectorAll('table');
    let foundProfile = false;
    
    tables.forEach(table => {
        const rows = table.querySelectorAll('tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 2) {
                const firstCell = cells[0].textContent.trim();
                const secondCell = cells[1].textContent.trim();
                
                if (firstCell.includes('SRCODE') || firstCell.includes('Student ID')) {
                    profile.srcode = secondCell;
                    foundProfile = true;
                }
                if (firstCell.includes('FULLNAME') || firstCell.includes('Name')) {
                    profile.fullname = secondCell;
                    foundProfile = true;
                }
                if (firstCell.includes('PROGRAM') || firstCell.includes('Course')) {
                    profile.program = secondCell;
                    foundProfile = true;
                }
            }
        });
    });

    // Display summary
    displayGradesSummary({ profile: profile });

    // Display all grades in a styled container
    container.innerHTML = `
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Complete Academic History</h6>
            </div>
            <div class="card-body p-0">
                <div class="allgrades-wrapper" style="background: #f8f9fa; padding: 20px;">
                    <div class="allgrades-page" style="background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 25px; max-width: 100%;">
                        ${htmlContent}
                    </div>
                </div>
            </div>
        </div>
    `;

    // Update status
    const gradesStatus = document.getElementById('grades-status');
    gradesStatus.textContent = 'Academic records loaded successfully.';
    gradesStatus.className = 'text-success d-block mt-2';
    
    // Enable the next button by simulating a successful load
    const fetchBtn = document.getElementById('fetch-all-grades-btn');
    if (fetchBtn) {
        fetchBtn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Records Loaded';
        fetchBtn.classList.remove('btn-primary');
        fetchBtn.classList.add('btn-success');
        fetchBtn.disabled = true;
    }
    
    console.log('Grades processed successfully for graduation form');
}

function openVerificationModalForGraduation() {
    // Open verification modal specifically for graduation form
    const modal = new bootstrap.Modal(document.getElementById('verificationModal'));
    resetVerificationModal();
    modal.show();
    
    // Override the success behavior to continue with graduation form
    window.verificationSuccessCallback = function() {
        // After verification, fetch grades again
        fetchAllStudentGrades();
    };
}

/* ===== Display All Student Grades ===== */
function displayAllStudentGrades(data) {
    const container = document.getElementById('all-grades-container');
    const summaryContainer = document.getElementById('grades-summary-container');

    if (!data.html) {
        container.innerHTML = `
            <div class="alert alert-warning">
                <i class="bi bi-info-circle-fill"></i>
                No academic records found for your account.
            </div>
        `;
        return;
    }

    // Display summary
    displayGradesSummary(data);
    summaryContainer.style.display = 'block';

    // Display all grades in a styled container
    container.innerHTML = `
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Complete Academic History</h6>
            </div>
            <div class="card-body p-0">
                <div class="allgrades-wrapper" style="background: #f8f9fa; padding: 20px;">
                    <div class="allgrades-page" style="background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 25px; max-width: 100%;">
                        ${data.html}
                    </div>
                </div>
            </div>
        </div>
    `;
}

/* ===== Display Grades Summary ===== */
function displayGradesSummary(data) {
    const container = document.getElementById('grades-summary-container');
    
    const profile = data.profile || {};
    const srcode = profile.srcode || '';
    const fullname = profile.fullname || '';
    const program = profile.program || '';
    const generatedAt = data.generated_at || '';

    let html = `
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Student Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>SR Code:</strong><br>
                        <span class="text-muted">${srcode || 'N/A'}</span>
                    </div>
                    <div class="col-md-5">
                        <strong>Full Name:</strong><br>
                        <span class="text-muted">${fullname || 'N/A'}</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Program:</strong><br>
                        <span class="text-muted">${program || 'N/A'}</span>
                    </div>
                </div>
                ${generatedAt ? `
                <div class="row mt-2">
                    <div class="col-12">
                        <small class="text-muted"><i class="bi bi-clock me-1"></i>Generated: ${generatedAt}</small>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
    `;

    container.innerHTML = html;
}

/* ===== Requirements UI handling ===== */
function toggleToFollow(kind){
  const wrap = document.getElementById(`req-${kind}-wrap`);
  const tf   = document.getElementById(`req-${kind}-tf`)?.checked;
  const file = document.getElementById(`req-${kind}-file`);
  const badge= document.getElementById(`req-${kind}-badge`);
  const stat = document.getElementById(`req-${kind}-status`);

  if (tf) { wrap?.classList.add('to-follow'); if (badge) badge.style.display='inline-flex'; if (file) { file.value=''; file.disabled = true; } }
  else    { wrap?.classList.remove('to-follow'); if (badge) badge.style.display='none'; if (file) file.disabled = false; }

  reqState[kind].toFollow = !!tf; if (stat) stat.textContent = tf ? 'Marked as To Follow' : '';
}

/* ===== Server helpers ===== */
function csrfToken(){ return document.querySelector('meta[name="csrf-token"]').getAttribute('content'); }
function route(nameMeta){ return document.querySelector(`meta[name="${nameMeta}"]`)?.content || ''; }

/* ===== COR upload ===== */
/* ===== COR upload ===== */
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

    // 🔹 Autofill Scholarship Grant from COR (if detected)
    if (j.scholarship_grant) {
      const sg = document.querySelector('input[name="scholarship_grant"]');
      if (sg && !sg.value) {
        sg.value = j.scholarship_grant;
      }
    }

    // 🔹 NEW: Show Graduation Evaluation Results
    if (j.graduation_evaluation) {
      displayGraduationEvaluation(j.graduation_evaluation);
    }

    const holder = document.getElementById('preview-cor');
    holder.innerHTML = '';

    if (serverPaths.cor_public_preview) {
      const cb = serverPaths.cor_public_preview + (serverPaths.cor_public_preview.includes('?')?'&':'?') + 'v=' + Date.now();
      if (/\.(png|jpg|jpeg)$/i.test(serverPaths.cor_public_preview)) {
        holder.innerHTML = `<img src="${cb}" alt="COR Preview"/>`;
      } else {
        holder.innerHTML = `<iframe src="${cb}" title="COR PDF"></iframe>`;
      }
    } else {
      await previewPDF(file, 'preview-cor');
    }

    el.textContent = 'COR uploaded successfully. ' + (j.courses_found ? `Found ${j.courses_found} courses.` : '');
  }catch(e){
    console.error(e);
    el.textContent = 'Upload failed.';
  }
}

/* ===== Graduation Evaluation Display ===== */
function displayGraduationEvaluation(evaluation) {
  const container = document.getElementById('step-2');
  if (!container) return;

  // Remove existing evaluation if any
  const existingEval = document.getElementById('graduation-evaluation');
  if (existingEval) {
    existingEval.remove();
  }

  const status = evaluation.graduation_status;
  const isGraduated = status === 'GRADUATED';
  const isCandidate = status === 'CANDIDATE FOR GRADUATION';
  const statusClass = isGraduated ? 'alert-success' : (isCandidate ? 'alert-info' : 'alert-warning');
  const statusIcon = isGraduated ? 'bi-check-circle-fill' : (isCandidate ? 'bi-person-check-fill' : 'bi-exclamation-circle-fill');

  const evaluationHTML = `
    <div id="graduation-evaluation" class="mt-4">
      <div class="alert ${statusClass}">
        <h6><i class="bi ${statusIcon} me-2"></i>Graduation Evaluation: ${status}</h6>
        <p class="mb-2">${evaluation.remarks}</p>
        <small class="text-muted">
          Matched ${evaluation.total_matched} of ${evaluation.total_curriculum_courses} required courses
          (${evaluation.completion_percentage}% complete)
          ${evaluation.student_track ? `| Track: <strong>${evaluation.student_track}</strong>` : ''}
        </small>
        
        ${evaluation.can_apply_for_graduation ? `
          <div class="mt-2">
            <span class="badge bg-success me-2"><i class="bi bi-check-circle me-1"></i>Eligible to Apply for Graduation</span>
            ${!evaluation.can_apply_for_latin ? `
              <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Not eligible for Latin Honors yet</span>
            ` : ''}
          </div>
        ` : `
          <div class="mt-2">
            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Not eligible for graduation yet</span>
          </div>
        `}
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <div class="card bg-light">
            <div class="card-body text-center p-2">
              <h6 class="mb-1">COR Courses</h6>
              <h4 class="text-primary mb-0">${evaluation.cor_courses_count}</h4>
              <small>Currently Enrolled</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card bg-light">
            <div class="card-body text-center p-2">
              <h6 class="mb-1">Grade History</h6>
              <h4 class="text-success mb-0">${evaluation.grade_courses_count}</h4>
              <small>Previously Taken</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card bg-light">
            <div class="card-body text-center p-2">
              <h6 class="mb-1">Total Taken</h6>
              <h4 class="text-info mb-0">${evaluation.combined_courses_count}</h4>
              <small>All Courses Taken</small>
            </div>
          </div>
        </div>
      </div>

      ${evaluation.missing_courses && evaluation.missing_courses.length > 0 ? `
        <div class="card ${isCandidate ? 'border-info' : 'border-warning'} mt-3">
          <div class="card-header ${isCandidate ? 'bg-info text-white' : 'bg-warning text-dark'}">
            <h6 class="mb-0">
              <i class="bi ${isCandidate ? 'bi-info-circle' : 'bi-exclamation-triangle'} me-2"></i>
              ${isCandidate ? 'Remaining Course(s) for Next Semester' : 'Missing Courses'} (${evaluation.total_missing})
            </h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Year Level</th>
                    <th>Semester</th>
                    <th>Track</th>
                    <th>Units</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  ${evaluation.missing_courses.map(course => `
                    <tr>
                      <td><code>${course.code}</code></td>
                      <td>${course.title}</td>
                      <td>${course.year_level}</td>
                      <td>${course.semester}</td>
                      <td>${course.track ? `<span class="badge bg-info">${course.track}</span>` : 'Core'}</td>
                      <td>${course.units}</td>
                      <td>
                        ${isCandidate ? 
                          '<span class="badge bg-info">Next Semester</span>' : 
                          '<span class="badge bg-danger">Never Taken</span>'
                        }
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      ` : ''}

      ${evaluation.matched_courses && evaluation.matched_courses.length > 0 ? `
        <div class="card border-success mt-3">
          <div class="card-header bg-success text-white">
            <h6 class="mb-0"><i class="bi bi-check-circle me-2"></i>Completed Courses (${evaluation.total_matched})</h6>
          </div>
          <div class="card-body">
            <div class="row">
              ${evaluation.matched_courses.slice(0, 12).map(course => `
                <div class="col-md-3 col-6 mb-2">
                  <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                    <div>
                      <small><strong>${course.code}</strong></small>
                      <br><small class="text-muted">${course.source === 'COR' ? 'Enrolled' : 'Completed'}</small>
                    </div>
                  </div>
                </div>
              `).join('')}
              ${evaluation.matched_courses.length > 12 ? `
                <div class="col-12">
                  <small class="text-muted">+ ${evaluation.matched_courses.length - 12} more courses completed</small>
                </div>
              ` : ''}
            </div>
          </div>
        </div>
      ` : ''}
    </div>
  `;

  container.insertAdjacentHTML('beforeend', evaluationHTML);
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
  setValState('v-grades','loading');
  
  try{
    // Check graduation status from server
    const statusResponse = await fetch(route('route-graduation-status'), { // This will now use student.graduation.evaluation
      headers: {
        'X-CSRF-TOKEN': csrfToken(),
        'Accept': 'application/json'
      }
    });
    
    if (statusResponse.ok) {
      const statusData = await statusResponse.json();
      if (statusData.has_evaluation) {
        if (statusData.remarks.includes('GRADUATED')) {
          setValState('v-grades','ok', 'Eligible for graduation');
        } else {
          setValState('v-grades','fail', statusData.remarks);
        }
      } else {
        setValState('v-grades','fail', 'No graduation evaluation performed');
      }
    } else {
      setValState('v-grades','fail', 'Unable to check graduation status');
    }

    // Since we're fetching from database, we can assume readability is good
    setValState('v-tamper','ok', 'Academic records loaded successfully');
    validationPass = true;
  } catch(error) { 
    setValState('v-tamper','fail','Validation error'); 
    setValState('v-grades','fail','Validation error');
    validationPass = false; 
  }
}

/* ===== Save Grad Form ===== */
async function saveGraduationFormFields() {
  const form = document.getElementById('applicationForm');
  const csrf = csrfToken();
  const fd = new FormData();
  fd.append('birthdate',          form.elements['birthdate']?.value || '');
  fd.append('place_of_birth',     form.elements['place_of_birth']?.value || '');
  fd.append('home_address',       form.elements['home_address']?.value || '');
  fd.append('zip_code',           form.elements['zip_code']?.value || '');
  fd.append('secondary_school',   form.elements['secondary_school']?.value || '');
  fd.append('secondary_year',     form.elements['secondary_year']?.value || '');
  fd.append('elementary_school',  form.elements['elementary_school']?.value || '');
  fd.append('elementary_year',    form.elements['elementary_year']?.value || '');
  fd.append('scholarship_grant',  form.elements['scholarship_grant']?.value || '');
  fd.append('parent1',            form.elements['parent1']?.value || '');
  fd.append('parent1_contact',    form.elements['parent1_contact']?.value || '');
  fd.append('parent2',            form.elements['parent2']?.value || '');
  fd.append('parent2_contact',    form.elements['parent2_contact']?.value || '');
  try {
    const res = await fetch(document.querySelector('meta[name="route-grad-save"]').content, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd });
    const j = await res.json().catch(() => ({}));
    return res.ok && j && j.ok === true;
  } catch { return false; }
}

/* ===== Small helpers ===== */
function previewPDF(file, containerId){
  renderPdfFirstPageToCanvas(file).then(c => {
    const holder = document.getElementById(containerId); if (!holder) return;
    holder.innerHTML = ''; const wrap=document.createElement('div'); wrap.className='pdf-page'; wrap.appendChild(c);
    const badge=document.createElement('span'); badge.className='page-badge'; badge.textContent='1';
    wrap.appendChild(badge); holder.appendChild(wrap);
  }).catch(()=>{});
}
async function renderPdfFirstPageToCanvas(file){
  const buf   = await file.arrayBuffer();
  const pdf   = await pdfjsLib.getDocument({ data: buf }).promise;
  const page  = await pdf.getPage(1);
  const vport = page.getViewport({ scale: 2.0 });
  const c = document.createElement('canvas'); c.width = vport.width; c.height = vport.height;
  const ctx = c.getContext('2d', { willReadFrequently: true });
  await page.render({ canvasContext: ctx, viewport: vport }).promise; return c;
}

/* ===== PDF generation (Step 6) ===== */
async function generatePdf() {
  const status = document.getElementById('pdf-status');
  const btn    = document.getElementById('pdf-generate-btn');
  const iframe = document.getElementById('pdf-frame');
  const openA  = document.getElementById('pdf-open-link');
  const csrf   = csrfToken();

  if (btn) btn.disabled = true;
  if (status) status.textContent = 'Generating…';

  try {
    const saved = await saveGraduationFormFields();
    if (!saved) throw new Error('Save failed. Please go back to Step 5 and try again.');

    const res = await fetch(
      document.querySelector('meta[name="route-grad-generate"]').content,
      { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } }
    );
    const j = await res.json();

    if (!res.ok || !j || j.ok !== true || !(j.url || j.public_url)) {
      throw new Error(j?.message || `Server error (${res.status})`);
    }

    const url = (j.url || j.public_url).toString();
    serverPaths.app_pdf_url = url;

    const shown = url + (url.includes('?') ? '&' : '?') + 'v=' + Date.now();
    if (iframe) iframe.src = shown;
    if (openA) { openA.href = shown; openA.style.display = 'inline'; }
    if (status) status.textContent = 'Done.';
  } catch (e) {
    if (status) status.textContent = `Failed to generate PDF: ${e.message || e}`;
  } finally {
    if (btn) btn.disabled = false;
  }
}

/* State & submit */
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
  if (!window.bootstrap?.Modal || !el) { submitApplication(); return; }
  const modal  = bootstrap.Modal.getOrCreateInstance(el);
  modal.show();
  yesBtn.onclick = async () => {
    yesBtn.disabled = true;
    try { await submitApplication(); modal.hide(); } finally { yesBtn.disabled = false; }
  };
}

/* === SUBMIT with Requirements (Step 7) === */
async function submitApplication(){
  const state = collectCurrentStateFromUI();
  const gwa = state.gwa || '';
  const rank = '';

  const formData = new FormData();
  formData.append('type', 'DeanLister');
  formData.append('gwa', gwa);
  formData.append('rank', rank);
  formData.append('context', JSON.stringify(state));

  /* Attach generated application PDF *as STRING URL* to satisfy "applicationform_grad must be a string" */
  let pdfUrl = serverPaths.app_pdf_url || (document.getElementById('pdf-frame')?.src || '');
  // If your backend prefers a relative path, derive it safely:
  try {
    if (pdfUrl) {
      const u = new URL(pdfUrl, window.location.origin);
      // keep query if needed; otherwise you could use just u.pathname
      pdfUrl = u.pathname + u.search;
    }
  } catch (_) { /* ignore if it's already relative */ }

  if (!pdfUrl || pdfUrl === 'about:blank') {
    alert('Please generate your Application PDF in Step 6 before submitting.');
    currentStep = 6; updateStepperUI();
    return;
  }
  formData.append('applicationform_grad', pdfUrl);

  // Requirements
  if (reqState.approval.file) formData.append('approval_sheet', reqState.approval.file);
  formData.append('approval_to_follow', reqState.approval.toFollow ? '1' : '0');

  if (reqState.library.file)  formData.append('library_certificate', reqState.library.file);
  formData.append('library_to_follow', reqState.library.toFollow ? '1' : '0');

  if (reqState.brgy.file)     formData.append('barangay_clearance', reqState.brgy.file);
  if (reqState.birth.file)    formData.append('birth_certificate', reqState.birth.file);

  const submitUrl = document.querySelector('meta[name="route-grad-req-submit"]')?.content
                 || document.querySelector('meta[name="route-application-submit"]')?.content;

  try {
    const resp = await fetch(submitUrl, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: formData
    });
    const raw = await resp.text();
    let data; try { data = JSON.parse(raw); } catch { data = { ok:false, message: raw?.slice(0,200) || `HTTP ${resp.status}` }; }
    
    if (resp.ok && data.ok) {
      // Check Latin Honors eligibility before showing modal
      const eligibility = await checkLatinHonorsEligibilityFromServer();
      if (eligibility.eligible) {
        showLatinHonorsModal(eligibility);
      } else {
        // Not eligible for Latin Honors - redirect directly to application status
        showSubmissionSuccess(eligibility);
      }
    } else {
      alert('Submission failed: ' + (data.message || `HTTP ${resp.status}`));
    }
  } catch (e) { alert('Submission error: ' + (e.message || e)); }
}

/* Latin Honors flow */
function consentFormUrl(){ return document.querySelector('meta[name="consent-form-url"]')?.content || ''; }

function handleLatinHonorsYes() {
    // lands on /student/latin and auto-generates the consent PDF
    window.location.href = "{{ route('student.latin') }}?auto=consent";
}

function handleLatinHonorsNo(){
  const dest = document.querySelector('meta[name="route-application-status"]')?.content;
  if (dest) window.location.href = dest;
}

/* Boot */
document.addEventListener('DOMContentLoaded', () => {
  updateStepperUI();

  const corInput = document.getElementById('file-cor');
  if (corInput) corInput.addEventListener('click', () => { corInput.value = ''; });

  ['approval','library','brgy','birth'].forEach(kind=>{
    const input = document.getElementById(`req-${kind}-file`);
    const stat  = document.getElementById(`req-${kind}-status`);
    if (input && stat){
      input.addEventListener('change', ()=>{
        const f = input.files && input.files[0];
        stat.textContent = f ? `Selected: ${f.name}` : '';
      });
    }
  });
});
</script>
@endsection