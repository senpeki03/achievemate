{{-- resources/views/student/studentgrade.blade.php --}}
@extends('student.studentsidebar')

@section('content')

<style>
  :root{
    --gold:#D4AF37; --gold-glow:rgba(212,175,55,.45);
    --gray:#5A5A5A; --rail:rgba(255,255,255,.25);
  }
  .am-stepper{position:relative;margin:8px auto 14px;max-width:520px;width:100%;padding-top:6px}
  .am-rail{height:6px;background:var(--rail);border-radius:999px}
  .am-steps-row{display:flex;justify-content:space-between;align-items:center;height:86px;margin-top:-28px}
  .am-step-node{display:flex;flex-direction:column;align-items:center;gap:8px;text-align:center;user-select:none}
  .am-step-node .dot{
    width:56px;height:56px;border-radius:999px;display:flex;align-items:center;justify-content:center;
    font-weight:800;font-size:20px;color:#fff;border:4px solid rgba(255,255,255,.85);box-shadow:0 8px 18px rgba(0,0,0,.12)
  }
  .am-step-node .label{font-weight:700;font-size:1rem;line-height:1.1;white-space:nowrap}
  .am-step-node.active .dot{ background:var(--gold); box-shadow:0 0 0 6px rgba(255,255,255,.08) inset, 0 0 16px var(--gold-glow) }
  .am-step-node.active .label{color:var(--gold)}
  .am-step-node.upcoming .dot{background:var(--gray)} .am-step-node.upcoming .label{color:#e6f2ff}
  .am-step-node.done .dot{background:var(--gold); box-shadow:0 0 12px var(--gold-glow)} .am-step-node.done .label{color:var(--gold)}
  .cog-table,.cog-summary{width:100%;border-collapse:collapse}
  .cog-table th,.cog-table td,.cog-summary td{border:1px solid #444;padding:.5rem .6rem}
  .cog-summary td.label{font-weight:600}.cog-summary td.wide{min-width:260px}
  .nothing-row td{text-align:center;font-style:italic;color:#666}
  .slim-card .card-body{padding:16px 18px}
  .preview-container {max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;}
  .preview-image {max-width: 100%; height: auto;}
  .ocr-processing {background: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 15px 0;}
  @media (max-width:720px){
    .am-stepper{max-width:92vw}
    .am-steps-row{height:82px;margin-top:-26px}
    .am-step-node .dot{width:50px;height:50px;font-size:18px}
  }
</style>

<div class="container py-4" id="applicationContainer">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Upload Grades</h3>
  </div>

  {{-- META routes --}}
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="grade-cog-upload" content="{{ route('student.upload.preview') }}">
  <meta name="route-save-qr-output" content="{{ route('student.save-qr-output') }}">
  <meta name="route-qr-output-file" content="{{ route('student.qr.output') }}">
  <meta name="route-save-student-grades" content="{{ route('student.grades.store') }}">

  <form id="applicationForm" method="POST" enctype="multipart/form-data" action="javascript:void(0)">
    @csrf

    {{-- stepper --}}
    <div class="am-stepper">
      <div class="am-rail"></div>
      <div class="am-steps-row">
        <div id="node-1" class="am-step-node active">
          <div class="dot"><span id="icon-1">1</span></div>
          <div class="label">Upload COG</div>
        </div>
        <div id="node-2" class="am-step-node upcoming">
          <div class="dot"><span id="icon-2">2</span></div>
          <div class="label">Review &amp; Confirm</div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-center">
      <div class="card shadow rounded-4 w-100 slim-card" style="max-width: 1100px;">
        <div class="card-body" id="step-content">

          {{-- step 1 --}}
          <div class="text-start" id="step-1">
            <h4 class="fw-bold mb-2">Upload Your Certificate of Grades (COG)</h4>
            <ul class="small text-muted mb-3">
              <li>Accepted format: <strong>PDF</strong> only</li>
              <li>Make sure the file is clear, complete, and official</li>
              <li>Example filename: <code>Lastname_Firstname_COG.pdf</code></li>
            </ul>

            <div class="text-center">
              <input type="file" name="cog" id="file-grade" accept="application/pdf"
                class="form-control w-50 mx-auto" required />
              
              {{-- Preview will be inserted here after upload --}}
              <div class="mt-3" id="preview-grade"></div>
              
              <small id="cog-status" class="d-block mt-2 text-muted">Waiting for file…</small>
            </div>
          </div>

          {{-- step 2 --}}
          <div class="text-start" id="step-2" style="display:none;">
            <p class="mb-3 text-muted">Review the extracted details from your COG before submitting.</p>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Fullname</label>
                <input type="text" id="fullname" class="form-control" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">SRCODE</label>
                <input type="text" id="srcode" class="form-control" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">College</label>
                <input type="text" id="college" class="form-control" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Academic Year</label>
                <input type="text" id="academic_year" class="form-control" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Program</label>
                <input type="text" id="program" class="form-control" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Semester</label>
                <input type="text" id="semester" class="form-control" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Year Level</label>
                <input type="text" id="year_level" class="form-control" readonly>
              </div>
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
          </div> <!-- /step-2 -->
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between mt-4 px-2" style="max-width: 1100px; margin: 0 auto;">
      <button type="button" onclick="goBack()" class="btn btn-outline-secondary px-4">Back</button>
      <button type="button" id="next-btn" onclick="goNext()" class="btn btn-primary px-4">Next</button>
    </div>
  </form>
</div>

<!-- Final Submit Confirmation -->
<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="min-height: 280px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256"
             style="width: 60px;" class="mb-3" alt="Confirm"/>
        <h5 class="fw-bold text-dark mb-3">Submit your grades now?</h5>
        <p class="text-muted mb-4" style="max-width:520px;margin:0 auto;">
          This will <strong>save the extracted COG courses and grades to the database</strong>
          under your student record. Please review the details before proceeding.
        </p>
        <div class="d-flex justify-content-center gap-3">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">No, Review Again</button>
          <button type="button" id="confirmSubmitYesBtn" class="btn btn-primary px-4">Yes, Submit</button>
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
          <video
            src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
            autoplay muted loop playsinline type="video/mp4"
            style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Grades submitted!</h5>
        <p class="mb-2 text-muted">Your COG has been recorded successfully.</p>
        <p id="successCounts" class="small text-muted mb-4"></p>
        <div>
          <button type="button" id="successOkBtn" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const csrfToken = () => document.querySelector('meta[name="csrf-token"]').content;
const routeMeta = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';

let currentStep = 1;
let __parsedCog = null;
let confirmModal, successModal;

function updateStepperUI(){
  const s1=document.getElementById('step-1'), s2=document.getElementById('step-2');
  s1.style.display = currentStep===1?'block':'none';
  s2.style.display = currentStep===2?'block':'none';

  const n1=document.getElementById('node-1'), n2=document.getElementById('node-2');
  const i1=document.getElementById('icon-1'), i2=document.getElementById('icon-2');
  if(currentStep===1){
    n1.classList.add('active'); n1.classList.remove('done');
    n2.classList.add('upcoming'); n2.classList.remove('active');
    i1.textContent='1'; i2.textContent='2';
  }else{
    n1.classList.remove('active'); n1.classList.add('done'); i1.textContent='✓';
    n2.classList.remove('upcoming'); n2.classList.add('active'); i2.textContent='2';
  }
  document.getElementById('next-btn').innerText = currentStep===2?'Finish':'Next';
}

async function goNext(){
  console.log('goNext called, currentStep:', currentStep);
  console.log('__parsedCog:', __parsedCog);
  
  if(currentStep===1){
    const f=document.getElementById('file-grade')?.files?.[0];
    const status=document.getElementById('cog-status');
    if(!f){ 
      status.textContent='Please select your COG PDF.';
      status.className = 'd-block mt-2 text-danger';
      return; 
    }
    await uploadStep1(f, status);
    return;
  }
  if(currentStep===2){
    console.log('Submitting data:', __parsedCog);
    
    if (!__parsedCog || !__parsedCog.rows || __parsedCog.rows.length === 0) {
      alert('No course data found to submit. Please check if the PDF was processed correctly.');
      return;
    }
    
    if (!confirmModal) confirmModal = new bootstrap.Modal(document.getElementById('confirmSubmitModal'));
    confirmModal.show();
  }
}

function goBack(){ 
  if(currentStep>1){ 
    currentStep=1; 
    updateStepperUI(); 
  } 
}

async function uploadStep1(file, statusEl) {
  try {
    statusEl.textContent = 'Uploading and processing…';
    statusEl.className = 'd-block mt-2 text-info';
    
    const fd = new FormData(); 
    fd.append('cog_file', file);
    
    const res = await fetch(routeMeta('grade-cog-upload'), {
      method: 'POST', 
      headers: {
        'X-CSRF-TOKEN': csrfToken(),
        'Accept': 'application/json'
      }, 
      body: fd
    });
    
    const data = await res.json();
    
    console.log('Full server response:', data);
    
    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Upload failed');
    }

    // Show the cropped preview immediately in Step 1
    const previewContainer = document.getElementById('preview-grade');
    
    if (data.has_preview && data.preview_image) {
      previewContainer.innerHTML = `
        <div class="preview-container p-3 bg-light mt-3">
          <h6 class="fw-bold mb-2">Document Preview</h6>
          <img src="${data.preview_image}" alt="COG Preview" class="preview-image border rounded">
          <div class="mt-2 text-center">
            <small class="text-muted">Cropped document preview</small>
          </div>
        </div>
      `;
    }

    // Check for parsed data in multiple possible response formats
    const parsedData = data.parsed_data || data.data || data;
    
    console.log('Parsed data structure:', parsedData);
    
    // Normalize the data structure to ensure consistency
    let normalizedData = null;
    
    if (parsedData && (parsedData.rows || parsedData.courses || parsedData.meta)) {
      // Data is already parsed by the server
      normalizedData = {
        meta: parsedData.meta || {},
        rows: parsedData.rows || parsedData.courses || []
      };
      
      // Ensure rows have consistent field names
      normalizedData.rows = normalizedData.rows.map(row => ({
        code: row.code || row.course_code || '',
        title: row.title || row.course_title || '',
        units: row.units || '',
        grade: row.grade || '',
        section: row.section || '',
        instructor: row.instructor || ''
      }));
      
      __parsedCog = normalizedData;
      
      console.log('Normalized parsed data:', __parsedCog);
      
      // Fill the form fields
      document.getElementById('fullname').value = normalizedData.meta.fullname || '';
      document.getElementById('srcode').value = normalizedData.meta.srcode || '';
      document.getElementById('college').value = normalizedData.meta.college || '';
      document.getElementById('academic_year').value = normalizedData.meta.academic_year || '';
      document.getElementById('program').value = normalizedData.meta.program || '';
      document.getElementById('semester').value = normalizedData.meta.semester || '';
      document.getElementById('year_level').value = normalizedData.meta.year_level || '';
      
      // Render the grade rows
      renderGradeRows(normalizedData.rows);
      
      // Update summary
      document.getElementById('sum-courses').textContent = normalizedData.rows.length || '—';
      document.getElementById('sum-units').textContent = calculateTotalUnits(normalizedData.rows) || '—';
      document.getElementById('sum-gwa').textContent = calculateGWA(normalizedData.rows) || '—';
      
      statusEl.innerHTML = '✅ <strong>Processing complete! Ready for review.</strong>';
      statusEl.className = 'd-block mt-2 text-success';
      
      // Auto-proceed to step 2
      currentStep = 2;
      updateStepperUI();
      
    } else {
      // Fallback: use the old method with file reading
      statusEl.innerHTML = '✅ <strong>Processed. Loading details...</strong>';
      statusEl.className = 'd-block mt-2 text-success';
      
      // Proceed to step 2 and load from file
      currentStep = 2;
      updateStepperUI();
      
      // Load data from the output file
      setTimeout(() => {
        loadAndRenderFromFile();
      }, 100);
    }
    
  } catch(e) { 
    console.error('Upload error:', e);
    let errorMessage = e.message;
    if (errorMessage.includes('Imagick') || errorMessage.includes('image processing')) {
      errorMessage = 'Server configuration issue. Please contact administrator.';
    } else if (errorMessage.includes('PDF')) {
      errorMessage = 'PDF processing failed. The file may be corrupted or protected.';
    }
    
    statusEl.innerHTML = `❌ <strong>Upload failed:</strong> ${errorMessage}`;
    statusEl.className = 'd-block mt-2 text-danger';
    document.getElementById('preview-grade').innerHTML = '';
    
    // Reset file input on error
    document.getElementById('file-grade').value = '';
  }
}

// Helper function to calculate total units
function calculateTotalUnits(rows) {
  return rows.reduce((total, row) => total + (parseFloat(row.units) || 0), 0);
}

// Helper function to calculate GWA
function calculateGWA(rows) {
  let totalWeight = 0;
  let totalUnits = 0;
  
  rows.forEach(row => {
    const units = parseFloat(row.units) || 0;
    const grade = parseFloat(row.grade);
    
    if (!isNaN(grade) && units > 0) {
      totalWeight += grade * units;
      totalUnits += units;
    }
  });
  
  return totalUnits > 0 ? (totalWeight / totalUnits).toFixed(4) : 'N/A';
}

// Helper function to render grade rows
function renderGradeRows(rows) {
  const tbody = document.getElementById('grade-table-body');
  
  if (!rows || rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No course data found.</td></tr>';
    return;
  }
  
  const esc = s => String(s || '').replace(/[&<>"']/g, m => 
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  
  tbody.innerHTML = rows.map((row, index) => `
    <tr>
      <td class="text-center">${index + 1}</td>
      <td>${esc(row.code)}</td>
      <td>${esc(row.title)}</td>
      <td class="text-center">${esc(row.units)}</td>
      <td class="text-center">${esc(row.grade)}</td>
      <td class="text-center">${esc(row.section)}</td>
      <td>${esc(row.instructor)}</td>
    </tr>
  `).join('') + '<tr class="nothing-row"><td colspan="7">** END OF RECORD **</td></tr>';
}

async function loadAndRenderFromFile(){
  const url = routeMeta('route-qr-output-file');
  const tb  = document.getElementById('grade-table-body');
  const sumCourses=document.getElementById('sum-courses');
  const sumUnits=document.getElementById('sum-units');
  const sumGwa=document.getElementById('sum-gwa');

  try{
    const res = await fetch(url, { headers:{'Accept':'text/plain,*/*'} });
    const txt = res.ok ? (await res.text()) : '';
    console.log('Raw file content:', txt);
    
    const parsed = parseCombinedOutput(txt);
    __parsedCog = parsed;

    console.log('Parsed from file:', parsed);

    // Fill meta fields
    document.getElementById('fullname').value = parsed.meta.fullname || '';
    document.getElementById('srcode').value = parsed.meta.srcode || '';
    document.getElementById('college').value = parsed.meta.college || '';
    document.getElementById('academic_year').value = parsed.meta.academic_year || '';
    document.getElementById('program').value = parsed.meta.program || '';
    document.getElementById('semester').value = parsed.meta.semester || '';
    document.getElementById('year_level').value = parsed.meta.year_level || '';

    renderRows(parsed.rows);
    sumCourses.textContent = parsed.rows.length || '—';
    sumUnits.textContent   = Number.isFinite(parsed.totalUnits) ? parsed.totalUnits : '—';
    sumGwa.textContent     = parsed.gwa || '—';
  }catch(e){
    console.error('Error loading from file:', e);
    tb.innerHTML='<tr><td colspan="7" class="text-center text-muted">Failed to load grades data.</td></tr>';
  }

  function renderRows(rows){
      if(!rows.length){
          tb.innerHTML='<tr><td colspan="7" class="text-center text-muted">No course data found in the extracted text.</td></tr>';
          return;
      }
      
      const esc = s => String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
      
      tb.innerHTML = rows.map((r, index) => `
          <tr>
              <td class="text-center">${index + 1}</td>
              <td>${esc(r.code)}</td>
              <td>${esc(r.title)}</td>
              <td class="text-center">${esc(r.units)}</td>
              <td class="text-center">${esc(r.grade)}</td>
              <td class="text-center">${esc(r.section)}</td>
              <td>${esc(r.instructor)}</td>
          </tr>
      `).join('') + '<tr class="nothing-row"><td colspan="7">** END OF RECORD **</td></tr>';
  }
}

/* POST parsed data to DB */
async function submitGrades(){
  console.log('submitGrades called');
  console.log('__parsedCog:', __parsedCog);
  
  const saveUrl = routeMeta('route-save-student-grades');
  const btn = document.getElementById('next-btn');

  if(!__parsedCog || !__parsedCog.rows || __parsedCog.rows.length === 0){
    console.error('No data to submit:', __parsedCog);
    alert('Nothing to submit — no parsed rows. Please check if the PDF was processed correctly.');
    return;
  }

  // Get the preview image data if available
  const previewImage = document.querySelector('#preview-grade img');
  let imageData = null;
  
  if (previewImage && previewImage.src) {
    imageData = previewImage.src;
    console.log('Including image data in submission');
  }

  // Ensure all fields are strings to pass validation
  const payload = {
    meta: {
      fullname:      String(document.getElementById('fullname').value || ''),
      srcode:        String(document.getElementById('srcode').value || ''),
      college:       String(document.getElementById('college').value || ''),
      academic_year: String(document.getElementById('academic_year').value || ''),
      program:       String(document.getElementById('program').value || ''),
      semester:      String(document.getElementById('semester').value || ''),
      year_level:    String(document.getElementById('year_level').value || ''),
    },
    rows: __parsedCog.rows.map(r => ({
      code:        String(r.code || ''),
      title:       String(r.title || ''),
      units:       String(r.units !== undefined && r.units !== null ? r.units : ''),
      grade:       String(r.grade || ''),
      section:     String(r.section || ''),
      instructor:  String(r.instructor || '')
    })),
    image_data: imageData
  };

  console.log('Submitting payload with units:', payload.rows.map(r => ({
    code: r.code,
    units: r.units,
    units_type: typeof r.units
  })));

  try{
    btn.disabled = true; 
    btn.textContent = 'Saving…';
    
    const res = await fetch(saveUrl, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken(),
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const json = await res.json();
    
    console.log('Save response:', json);
    
    if(!res.ok || !json.ok){
      console.error('Save failed', json);
      alert(json.message || 'Saving failed. Please try again.');
      return;
    }

    if (confirmModal) confirmModal.hide();
    if (!successModal) successModal = new bootstrap.Modal(document.getElementById('successSubmitModal'));
    const counts = document.getElementById('successCounts');
    
    let successMessage = `Inserted: ${json.inserted ?? 0} • Updated: ${json.updated ?? 0}`;
    if (json.image_saved !== undefined) {
      successMessage += ` • Image: ${json.image_saved ? 'Saved' : 'Not Saved'}`;
    }
    
    if (counts) counts.textContent = successMessage;
    successModal.show();
    
    // ✅ UPDATED: Redirect to view grades page after successful submission
    const successOkBtn = document.getElementById('successOkBtn');
    if (successOkBtn) {
      // Remove any existing event listeners and add new one
      successOkBtn.replaceWith(successOkBtn.cloneNode(true));
      document.getElementById('successOkBtn').addEventListener('click', function() {
        // Redirect to view grades page
        window.location.href = json.redirect_url || '{{ route("student.grades.view") }}';
      });
    }
    
    // Also auto-redirect after 3 seconds if user doesn't click OK
    setTimeout(() => {
      window.location.href = json.redirect_url || '{{ route("student.grades.view") }}';
    }, 3000);
    
  }catch(e){
    console.error('Submission error:', e);
    alert('Network error while saving: ' + e.message);
  }finally{
    btn.disabled = false; 
    btn.textContent = 'Finish';
  }
}

/* Parser for your file format */
function parseCombinedOutput(text = '') {
    const lines = String(text).replace(/\r/g, '').split('\n');
    const meta = { 
        fullname: '', 
        srcode: '', 
        college: '', 
        academic_year: '', 
        program: '', 
        semester: '', 
        year_level: '' 
    };
    const rows = [];
    
    console.log('Raw text for parsing:', text);

    // More comprehensive header parsing
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        
        // Fullname and SRCODE - more flexible matching
        if (line.match(/Fullname|Name|Student Name/i)) {
            const nameMatch = line.match(/(?:Fullname|Name|Student Name)\s*:?\s*(.+?)(?:\s+SRCODE\s*:?\s*(\w+))?/i);
            if (nameMatch) {
                meta.fullname = (nameMatch[1] || '').trim();
                if (nameMatch[2]) meta.srcode = nameMatch[2].trim();
            }
            // Also check next line for SRCODE if not found
            if (!meta.srcode && i + 1 < lines.length) {
                const srcodeMatch = lines[i + 1].match(/SRCODE\s*:?\s*(\w+)/i);
                if (srcodeMatch) meta.srcode = srcodeMatch[1].trim();
            }
        }
        
        // College
        if (line.match(/College/i) && !meta.college) {
            const collegeMatch = line.match(/College\s*:?\s*(.+?)(?:\s+Academic Year|$)/i);
            if (collegeMatch) meta.college = collegeMatch[1].trim();
        }
        
        // Academic Year
        if (line.match(/Academic Year|School Year/i) && !meta.academic_year) {
            const yearMatch = line.match(/(?:Academic Year|School Year)\s*:?\s*(.+?)(?:\s+Program|$)/i);
            if (yearMatch) meta.academic_year = yearMatch[1].trim();
        }
        
        // Program
        if (line.match(/Program|Course/i) && !meta.program) {
            const programMatch = line.match(/(?:Program|Course)\s*:?\s*(.+?)(?:\s+Semester|$)/i);
            if (programMatch) meta.program = programMatch[1].trim();
        }
        
        // Semester
        if (line.match(/Semester/i) && !meta.semester) {
            const semesterMatch = line.match(/Semester\s*:?\s*(.+?)(?:\s+Year Level|$)/i);
            if (semesterMatch) meta.semester = semesterMatch[1].trim();
        }
        
        // Year Level
        if (line.match(/Year Level|Year|Level/i) && !meta.year_level) {
            const yearLevelMatch = line.match(/(?:Year Level|Year|Level)\s*:?\s*(.+)/i);
            if (yearLevelMatch) meta.year_level = yearLevelMatch[1].trim();
        }
    }

    // Course row parsing
    let inGradesSection = false;
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        
        // Look for start of grades table
        if (line.match(/Course Code|Subject Code|Code|No\.|#/) && 
            line.match(/Units|Credit|Grade|Section|Instructor/)) {
            inGradesSection = true;
            continue;
        }
        
        // Look for end of grades section
        if (line.match(/Total|GWA|General Weighted Average|NOTHING FOLLOWS/i)) {
            inGradesSection = false;
        }
        
        if (inGradesSection && line) {
            // Try to parse course rows with various patterns
            const patterns = [
                // Pattern for: "1   IT 321    Human-Computer Interaction    3    1.50    IT-NT-3201    PAYTAREN, ALBERT V."
                /^\s*(\d+)\s+([A-Z]{2}\s+\d{3})\s+(.+?)\s+(\d+(?:\.\d+)?)\s+([0-4](?:\.\d{1,3})?|INC|DRP|W|PASS)\s+([A-Z0-9\-]+)\s+(.+)$/i,
                
                // More general pattern
                /^\s*(\d+)\s+([A-Z]+\s*\d+[A-Z]?)\s+(.+?)\s+(\d+(?:\.\d+)?)\s+([0-4](?:\.\d{1,3})?|INC|DRP|W|PASS)\s+([A-Z0-9\-]+)\s+(.+)$/i
            ];
            
            for (const pattern of patterns) {
                const match = line.match(pattern);
                if (match) {
                    rows.push({
                        idx: parseInt(match[1], 10),
                        code: match[2].trim(),
                        title: match[3].trim(),
                        units: match[4].trim(),
                        grade: match[5].trim(),
                        section: match[6].trim(),
                        instructor: match[7].trim()
                    });
                    break;
                }
            }
        }
    }

    // Calculate totals
    let totalUnits = rows.reduce((sum, row) => sum + (parseFloat(row.units) || 0), 0);
    
    // Calculate GWA
    let totalWeight = 0;
    let totalUnitCount = 0;
    
    rows.forEach(row => {
        const units = parseFloat(row.units) || 0;
        const grade = parseFloat(row.grade);
        
        if (!isNaN(grade) && units > 0) {
            totalWeight += grade * units;
            totalUnitCount += units;
        }
    });
    
    const gwa = totalUnitCount > 0 ? (totalWeight / totalUnitCount).toFixed(4) : 'N/A';

    console.log('Final parsed data:', { meta, rows, totalUnits, gwa });
    
    return { meta, rows, totalUnits, gwa };
}

document.addEventListener('DOMContentLoaded', () => {
  updateStepperUI();
  const yesBtn = document.getElementById('confirmSubmitYesBtn');
  if (yesBtn) yesBtn.addEventListener('click', async () => { await submitGrades(); });
  
  // Add file input change listener
  const fileInput = document.getElementById('file-grade');
  if (fileInput) {
    fileInput.addEventListener('change', function() {
      const statusEl = document.getElementById('cog-status');
      if (this.files && this.files[0]) {
        statusEl.textContent = `File selected: ${this.files[0].name}`;
        statusEl.className = 'd-block mt-2 text-info';
        // Clear previous preview
        document.getElementById('preview-grade').innerHTML = '';
        // Reset parsed data when new file is selected
        __parsedCog = null;
      } else {
        statusEl.textContent = 'Waiting for file...';
        statusEl.className = 'd-block mt-2 text-muted';
      }
    });
  }
});
</script>

@endsection