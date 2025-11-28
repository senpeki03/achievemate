{{-- resources/views/student/viewgrade.blade.php --}}
@extends('student.studentsidebar')

@section('content')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
  :root{
    --accent:#2e7d32; --ink:#1b5e20; --muted:#6b7280;
    --pill:#eef2f6; --pill-border:#cfd8dc; --tile:#ffffff;
    --shadow:0 8px 20px rgba(0,0,0,.06);
  }

  /* ==== MODAL SIZE (mas maliit na width) ==== */
  .modal-dialog.modal-lg{
    max-width: 960px !important;
  }

  .grade-shell{max-width:1100px;margin:24px auto;}
  .panel{background:var(--tile);border-radius:12px;box-shadow:var(--shadow);padding:20px 24px;}

  /* Filter pill (click => picker) */
  .filter-wrap{margin-bottom:14px;}
  .filter-pill{
    display:inline-flex;align-items:center;gap:8px;padding:8px 14px;
    border:1px solid var(--pill-border);background:var(--pill);color:#4b5563;
    border-radius:999px;font-weight:600;cursor:pointer;user-select:none;
  }
  .filter-pill i{color:#667085;}

  /* Feature rows (icons) */
  .features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px 24px;}
  @media (max-width: 992px){.features-grid{grid-template-columns:repeat(2,1fr);} }
  @media (max-width: 640px){.features-grid{grid-template-columns:1fr;} }
  .feat{display:flex;align-items:center;gap:12px;padding:10px 6px;border-radius:10px;cursor:pointer;transition:background .15s;}
  .feat:hover{background:#f9fafb;}
  .feat-icon{width:40px;height:40px;display:flex;align-items:center;justify-content:center;border:2px solid var(--accent);color:var(--accent);border-radius:10px;font-size:18px;background:#fff;}
  .feat-label{font-weight:600;color:#1f2937;}

  /* Modals */
  .modal-grade{border:none;border-radius:16px;}
  .modal-grade .modal-header{background:#f8f9fa;border-bottom:1px solid #e5e7eb;border-radius:16px 16px 0 0;}
  .modal-title{font-weight:700;color:#1f2937;}

  /* ==== CARD BODY AREA (shared List & Table) ==== */
  .grades-modal-body{padding:0;}
  .grades-modal-inner{padding-bottom:0;}
  .grades-content-scroll{padding:16px 18px 18px;}

  /* Tabs (List | Table) */
  .view-tabs{
    display:flex;gap:8px;
    padding:12px 24px 8px;
    border-bottom:1px solid #e5e7eb;
  }
  .view-tab{
    display:inline-flex;align-items:center;gap:8px;border:1px solid #e5e7eb;background:#fff;
    padding:8px 12px;border-radius:999px;font-weight:600;color:#374151; cursor:pointer;
  }
  .view-tab.active{background:#eef6ee;border-color:#b7e0c0;color:#1b5e20;}
  .view-tab i{opacity:.85}

  /* ==== SHARED INNER CARD (List & Table) ==== */
  .grades-card{
    max-width: 900px;
    margin: 0 auto;
    border:1px solid #e5e7eb;
    border-radius:4px;
    background:#fff;
    overflow:hidden;
  }

  /* List view rows */
  .grades-table-view{width:100%;}
  .course-row{
    display:flex;justify-content:space-between;align-items:flex-start;
    padding:16px 24px;border-bottom:1px solid #e5e7eb;
  }
  .grades-table-view .course-row:last-child{border-bottom:none;}
  .course-row:hover{background:#f9fafb;}
  .course-header{flex:1;}
  .course-code{font-weight:700;font-size:1rem;color:#1f2937;margin:0 0 4px;}
  .instructor{color:#4b5563;font-size:.9rem;margin:8px 0 0;font-weight:500;}
  .grade-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:20px;font-weight:600;background:rgba(46,125,50,.1);color:var(--accent);white-space:nowrap;}

  /* Table view */
  .grades-table-wrapper{
    max-width: 900px;
    margin: 0 auto;
    border:1px solid #e5e7eb;
    border-radius:4px;
    overflow:hidden;
    background:#fff;
  }
  .grades-table{width:100%;border-collapse:collapse;}
  .grades-table th, .grades-table td{
    border:1px solid #e5e7eb;padding:8px 10px;font-size:.9rem;vertical-align:middle;
  }
  .grades-table th{
    background:#f8fafb;font-weight:700;color:#111827;white-space:nowrap;
  }
  .grades-table td.desc-cell{
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    text-align:left;
  }
  .status-ok{display:inline-flex;align-items:center;gap:6px}
  .status-ok i{color:#22c55e}

  .no-data{text-align:center;padding:60px 20px;color:#6b7280;}
  .no-data i{font-size:3rem;margin-bottom:16px;color:#d1d5db;}

  /* ===== COPY OF GRADES (COG) IMAGE PREVIEW ===== */
  .copy-alert{margin-bottom: 12px;}
  .cog-frame{
    background:#f3f4f6;
    padding:18px;
    display:flex;
    justify-content:center;
    align-items:flex-start;
  }
  .cog-frame-inner{
    background:#ffffff;
    border:1px solid #d1d5db;
    padding:0;
  }
  .cog-frame-inner img{
    display:block;
    max-width:100%;
    height:auto;
  }

  /* ===== VIEW ALL GRADES – DOCUMENT STYLE PAGE ===== */
  .allgrades-wrapper{
    background:#f3f4f6;
    padding:18px;
  }
  .allgrades-page{
    background:#ffffff;
    border:1px solid #d1d5db;
    margin:0 auto;
    max-width:1024px;
    padding:18px 22px;
    box-shadow:0 4px 12px rgba(0,0,0,.05);
    font-size:13px;
    color:#111827;
  }
  .allgrades-page table{
    width:100%;
    border-collapse:collapse;
    font-size:12px;
  }
  .allgrades-page th,
  .allgrades-page td{
    border:1px solid #111;
    padding:4px 6px;
  }

  /* ==== VERIFICATION MODAL STYLES ==== */
  .verification-code-input {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin: 20px 0;
  }
  .verification-code-input input {
    width: 40px;
    height: 50px;
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    border: 2px solid #ddd;
    border-radius: 8px;
    transition: all 0.3s;
  }
  .verification-code-input input:focus {
    border-color: #2e7d32;
    box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.2);
    outline: none;
  }
  .verification-code-input input.filled {
    border-color: #2e7d32;
    background-color: #f8fff8;
  }
  .countdown-timer {
    text-align: center;
    font-size: 14px;
    margin: 10px 0;
  }
  .countdown-timer.expired {
    color: #dc3545;
  }
  .resend-code {
    text-align: center;
    margin-top: 15px;
  }
  .resend-code a {
    color: #2e7d32;
    text-decoration: none;
    cursor: pointer;
  }
  .resend-code a:hover {
    text-decoration: underline;
  }
  .resend-code a.disabled {
    color: #6c757d;
    cursor: not-allowed;
    text-decoration: none;
  }

  /* ==== REPORT HEADER (logo + university + 2-row info) ==== */
  .rg-header-table{
    width:100%;
    border:none;
    table-layout:auto;
    margin-bottom:4px;
  }
  .rg-header-table td{
    border:none;
    vertical-align:middle;
  }
  .rg-logo-cell{
    width:15%;
    text-align:left;
  }
  .rg-text-cell{
    width:70%;
    text-align:center;
  }
  .rg-spacer-cell{width:15%;}

  .rg-logo-box{
    width:70pt;height:70pt;
    overflow:hidden;
    display:flex;align-items:center;justify-content:center;
  }
  .rg-logo-box img{
    height:100%;width:auto;display:block;object-fit:contain;
  }

  .rg-header-text{text-align:center;line-height:1.0;margin-top:4px;}
  .rg-gov{font-size:9pt;}
  .rg-uni{font-size:16pt;font-weight:800;letter-spacing:.5px;}
  .rg-national{font-size:11pt;font-weight:800;color:#b91c1c;}
  .rg-campus{font-size:10pt;font-weight:700;margin-top:4px;}
  .rg-addr{font-size:9pt;margin-top:4px;}
  .rg-contact{font-size:9pt;margin-top:2px;}

  .rg-divider{width:100%;border-top:1px solid #000;margin:10px 0 4px;}
  .rg-title-main{font-size:14pt;font-weight:800;margin:4px 0 10px;text-align:center;}

  .rg-info-table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:0;
    font-size:10pt;
  }
  .rg-info-table td{
    border:1px solid #000;
    padding:4px 6px;
  }
  .rg-info-label{font-weight:bold;width:18%;}

  .rg-program-bar{
    margin-top:6px;
    font-size:11pt;
    font-weight:700;
    text-align:center;
    border:1px solid #000;
    padding:4px 6px;
  }

  .rg-footer-note{
    margin-top:18px;
    font-size:10px;
    text-align:center;
  }
  .rg-footer-line{
    margin-top:14px;
    font-size:10px;
    display:flex;
    justify-content:space-between;
  }

  /* ==== PRINT – Legal 8.5 x 13 in ==== */
  @media print{
    @page{
      size:8.5in 13in;
      margin:10mm;
    }
    body{margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    .modal{position:static !important;}
    .modal-dialog,.modal-content{box-shadow:none !important;border:none !important;margin:0 !important;}
    .allgrades-wrapper{padding:0;background:#fff;}
    .allgrades-page{border:none;box-shadow:none;max-width:none;}
  }
</style>

<div class="container grade-shell">
  <div class="panel">
    <!-- Filter pill (opens picker directly) -->
    <div class="filter-wrap">
      <div class="filter-pill" id="filterOpen">
        <i class="fa-solid fa-filter"></i>
        <span id="filterLabel">Choose Academic Year &amp; Semester</span>
        <i class="fa-solid fa-chevron-down" style="margin-left:6px;"></i>
      </div>
    </div>

    <!-- Feature buttons -->
    <div class="features-grid">
      <div class="feat" onclick="openGradesModal()">
        <div class="feat-icon"><i class="fa-solid fa-book"></i></div>
        <div class="feat-label">Grades</div>
      </div>

      <div class="feat" onclick="openVerificationModal()">
        <div class="feat-icon"><i class="fa-solid fa-book-open"></i></div>
        <div class="feat-label">View All Grades</div>
      </div>

      <div class="feat" onclick="openCopyGradesModal()">
        <div class="feat-icon"><i class="fa-solid fa-print"></i></div>
        <div class="feat-label">View Copy of Grades</div>
      </div>

      <!-- Upload Grades button -->
      <div class="feat" onclick="window.location.href='{{ route('student.studentgrade') }}'">
        <div class="feat-icon"><i class="fa-solid fa-upload"></i></div>
        <div class="feat-label">Upload Grades</div>
      </div>
    </div>

  </div>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-grade">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Verify Your Identity</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="sendingCodeSection">
          <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Sending verification code to your email...</p>
          </div>
        </div>

        <div id="codeSection" class="d-none">
          <p>A 6-digit verification code has been sent to your email address.</p>
          <p class="text-muted small" id="userEmail"></p>
          
          <div class="mb-3">
            <label class="form-label">Enter 6-digit Verification Code</label>
            <div class="verification-code-input">
              <input type="text" maxlength="1" data-index="0" oninput="moveToNext(this)" onkeydown="handleBackspace(event, this)">
              <input type="text" maxlength="1" data-index="1" oninput="moveToNext(this)" onkeydown="handleBackspace(event, this)">
              <input type="text" maxlength="1" data-index="2" oninput="moveToNext(this)" onkeydown="handleBackspace(event, this)">
              <input type="text" maxlength="1" data-index="3" oninput="moveToNext(this)" onkeydown="handleBackspace(event, this)">
              <input type="text" maxlength="1" data-index="4" oninput="moveToNext(this)" onkeydown="handleBackspace(event, this)">
              <input type="text" maxlength="1" data-index="5" oninput="moveToNext(this)" onkeydown="handleBackspace(event, this)">
            </div>
            <input type="hidden" id="verificationCode">
          </div>

          <div class="countdown-timer" id="countdownTimer">
            Code expires in: <span id="timer">05:00</span>
          </div>

          <div class="resend-code">
            <a href="javascript:void(0)" id="resendCode" onclick="resendVerificationCode()">Resend Code</a>
          </div>
        </div>

        <div id="verificationMessage" class="alert d-none mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success d-none" id="verifyCodeBtn" onclick="verifyCode()">Verify & View Grades</button>
      </div>
    </div>
  </div>
</div>

<!-- Grades Modal -->
<div class="modal fade" id="gradesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-grade">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="gradesModalTitle">Grades</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body grades-modal-body" id="gradesModalBody">
        <div class="grades-modal-inner">
          <!-- tabs -->
          <div class="view-tabs">
            <button class="view-tab active" id="tabList">
              <i class="fa-regular fa-list-alt"></i> List View
            </button>
            <button class="view-tab" id="tabTable">
              <i class="fa-solid fa-table"></i> Table View
            </button>
          </div>

          <!-- shared content area -->
          <div id="gradesContent" class="grades-content-scroll">
            <div class="text-center py-4">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
              <p class="mt-2 text-muted">Loading grades...</p>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Copy of Grades Modal -->
<div class="modal fade" id="copyGradesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-grade">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Student's copy of grades</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="copyGradesModalBody">
        <div class="text-center py-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2 text-muted">Loading copy of grades...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- All Grades Modal -->
<div class="modal fade" id="allGradesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-grade">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">View All Grades</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="allGradesModalBody">
        <div class="text-center py-4">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
          <p class="mt-2 text-muted">Loading all grades...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="window.print()">Export Records</button>
      </div>
    </div>
  </div>
</div>

<!-- AY/SEM Picker Modal -->
<div class="modal fade" id="aySemPickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-grade">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Choose Academic Year &amp; Semester</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3">
        <div class="mb-3">
          <label class="form-label">Academic Year</label>
          <select id="aySelect" class="form-select">
            <option value="">Loading…</option>
          </select>
          <div id="ayHelp" class="form-text text-danger d-none">No Academic Years found.</div>
        </div>
        <div class="mb-2">
          <label class="form-label">Semester</label>
          <select id="semSelect" class="form-select">
            <option value="FIRST">FIRST</option>
            <option value="SECOND">SECOND</option>
            <option value="SUMMER">SUMMER</option>
            <option value="SUMMER2">SUMMER2</option>
          </select>
        </div>
        <small class="text-muted">Example: 2023-2024 SECOND</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="applyAySemBtn">Apply</button>
      </div>
    </div>
  </div>
</div>

<script>
/* ====== CONFIG ====== */
const SEM_MAP = { 'FIRST':'FIRST','SECOND':'SECOND','SUMMER':'MIDYEAR','SUMMER2':'SUMMER2' };
const REPORT_LOGO_URL = "{{ asset('img/Batangas_State_Logo.png') }}";

/* ====== STATE ====== */
let __ayLoaded = false;
let __gradesCache = [];            // reuse when switching tabs
let __currentView  = 'list';       // 'list' | 'table'
window.selectedAY_ID  = localStorage.getItem('selectedAY_ID')  || '';
window.selectedAY_LBL = localStorage.getItem('selectedAY_LBL') || '';
window.selectedSEM    = localStorage.getItem('selectedSEM')    || 'SECOND';

// Verification state
let verificationTimer = null;
let timeLeft = 300; // 5 minutes in seconds

/* ====== HELPERS ====== */
const LOADING_HTML = `
  <div class="text-center py-4">
    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
    <p class="mt-2 text-muted">Loading...</p>
  </div>`;

const esc = s => String(s ?? '').replace(/[&<>"']/g,
  m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])
);

/** Treat null / '' / 0 / NaN as "N/A" (para hindi lumabas na 0 units) */
function normalizeUnits(raw) {
  if (raw === null || raw === undefined || raw === '') return 'N/A';
  const n = parseFloat(raw);
  if (!isFinite(n) || n <= 0) return 'N/A';
  return n;
}

function setFilterLabel(){
  const el = document.getElementById('filterLabel');
  if (!el) return;
  if (window.selectedAY_LBL && window.selectedSEM){
    el.textContent = `${window.selectedAY_LBL} ${String(window.selectedSEM).toUpperCase()}`;
  } else {
    el.textContent = 'Choose Academic Year & Semester';
  }
}

/* ====== VERIFICATION MODAL FUNCTIONS ====== */
function openVerificationModal() {
  const modal = new bootstrap.Modal(document.getElementById('verificationModal'));
  resetVerificationModal();
  modal.show();
  
  // Immediately start sending the code
  sendAutoVerificationCode();
}

function resetVerificationModal() {
  // Reset UI state
  const sendingSection = document.getElementById('sendingCodeSection');
  const codeSection = document.getElementById('codeSection');
  const verifyBtn = document.getElementById('verifyCodeBtn');
  
  // Reset sections visibility
  sendingSection.classList.remove('d-none');
  codeSection.classList.add('d-none');
  verifyBtn.classList.add('d-none');
  
  // Clear code inputs
  const inputs = document.querySelectorAll('.verification-code-input input');
  inputs.forEach(input => {
    input.value = '';
    input.classList.remove('filled');
  });
  
  // Clear verification message
  const messageEl = document.getElementById('verificationMessage');
  messageEl.classList.add('d-none');
  messageEl.textContent = '';
  
  // Clear timer
  if (verificationTimer) {
    clearInterval(verificationTimer);
  }
  timeLeft = 300;
  updateTimerDisplay();
  
  // Remove expired styling
  const countdownTimer = document.getElementById('countdownTimer');
  countdownTimer.classList.remove('expired');
  
  // Enable verify button
  verifyBtn.disabled = false;
  verifyBtn.innerHTML = 'Verify & View Grades';
  
  // Enable resend link
  const resendLink = document.getElementById('resendCode');
  resendLink.classList.remove('disabled');
  resendLink.innerHTML = 'Resend Code';
}

function sendAutoVerificationCode() {
  const sendingSection = document.getElementById('sendingCodeSection');
  const codeSection = document.getElementById('codeSection');
  const verifyBtn = document.getElementById('verifyCodeBtn');
  
  // Show loading state
  sendingSection.classList.remove('d-none');
  codeSection.classList.add('d-none');
  verifyBtn.classList.add('d-none');

  fetch('{{ route("student.grades.send-auto-verification-code") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Show code input section
      sendingSection.classList.add('d-none');
      codeSection.classList.remove('d-none');
      verifyBtn.classList.remove('d-none');
      
      // Show user's email
      document.getElementById('userEmail').textContent = data.username;
      
      // Store the expected code for verification
      document.getElementById('verificationCode').value = data.code;
      
      // Start timer
      startTimer();
      
      showVerificationMessage('Verification code sent to your email.', 'success');
      
      // Focus first code input
      document.querySelector('.verification-code-input input[data-index="0"]').focus();
    } else {
      showVerificationMessage(data.message || 'Failed to send verification code. Please try again.', 'danger');
      sendingSection.classList.add('d-none');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showVerificationMessage('An error occurred. Please try again.', 'danger');
    sendingSection.classList.add('d-none');
  });
}

function verifyCode() {
  const enteredCode = getEnteredCode();
  
  if (enteredCode.length !== 6) {
    showVerificationMessage('Please enter the complete 6-digit code.', 'danger');
    return;
  }
  
  // Show loading state
  const verifyBtn = document.getElementById('verifyCodeBtn');
  const originalText = verifyBtn.innerHTML;
  verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Verifying...';
  verifyBtn.disabled = true;

  fetch('{{ route("student.grades.verify-code") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ code: enteredCode })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // SUCCESS: Close verification modal and open all grades
      showVerificationMessage('Verification successful! Loading your grades...', 'success');
      setTimeout(() => {
        bootstrap.Modal.getInstance(document.getElementById('verificationModal')).hide();
        openAllGradesModalDirect();
      }, 1000);
    } else {
      showVerificationMessage(data.message || 'Invalid verification code.', 'danger');
      verifyBtn.innerHTML = originalText;
      verifyBtn.disabled = false;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showVerificationMessage('Verification failed. Please try again.', 'danger');
    verifyBtn.innerHTML = originalText;
    verifyBtn.disabled = false;
  });
}

function resendVerificationCode() {
  const resendLink = document.getElementById('resendCode');
  
  // Prevent multiple clicks
  if (resendLink.classList.contains('disabled')) {
    return;
  }
  
  resendLink.classList.add('disabled');
  resendLink.innerHTML = 'Resending...';
  
  fetch('{{ route("student.grades.resend-verification-code") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Update the expected code
      document.getElementById('verificationCode').value = data.code;
      
      // Reset timer
      if (verificationTimer) {
        clearInterval(verificationTimer);
      }
      timeLeft = 300;
      startTimer();
      
      showVerificationMessage('A new verification code has been sent to your email.', 'success');
      
      // Clear code inputs and refocus first input
      const inputs = document.querySelectorAll('.verification-code-input input');
      inputs.forEach(input => {
        input.value = '';
        input.classList.remove('filled');
      });
      inputs[0].focus();
      
      // Remove disabled class after successful resend
      resendLink.classList.remove('disabled');
      resendLink.innerHTML = 'Resend Code';
    } else {
      showVerificationMessage(data.message || 'Failed to resend verification code.', 'danger');
      // Re-enable the resend link even on failure
      setTimeout(() => {
        resendLink.classList.remove('disabled');
        resendLink.innerHTML = 'Resend Code';
      }, 30000);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showVerificationMessage('An error occurred. Please try again.', 'danger');
    // Re-enable the resend link even on error
    setTimeout(() => {
      resendLink.classList.remove('disabled');
      resendLink.innerHTML = 'Resend Code';
    }, 30000);
  });
}

function startTimer() {
  if (verificationTimer) {
    clearInterval(verificationTimer);
  }
  
  verificationTimer = setInterval(() => {
    timeLeft--;
    updateTimerDisplay();
    
    if (timeLeft <= 0) {
      clearInterval(verificationTimer);
      document.getElementById('countdownTimer').classList.add('expired');
      document.getElementById('verifyCodeBtn').disabled = true;
      showVerificationMessage('Verification code has expired. Please request a new one.', 'danger');
    }
  }, 1000);
}

function updateTimerDisplay() {
  const minutes = Math.floor(timeLeft / 60);
  const seconds = timeLeft % 60;
  document.getElementById('timer').textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

function getEnteredCode() {
  const inputs = document.querySelectorAll('.verification-code-input input');
  let code = '';
  inputs.forEach(input => {
    code += input.value;
  });
  return code;
}

function moveToNext(input) {
  const value = input.value;
  const index = parseInt(input.getAttribute('data-index'));
  
  if (value) {
    input.classList.add('filled');
    
    // Move to next input if available
    if (index < 5) {
      const nextInput = document.querySelector(`.verification-code-input input[data-index="${index + 1}"]`);
      nextInput.focus();
    }
  } else {
    input.classList.remove('filled');
  }
}

function handleBackspace(event, input) {
  if (event.key === 'Backspace' && !input.value) {
    const index = parseInt(input.getAttribute('data-index'));
    if (index > 0) {
      const prevInput = document.querySelector(`.verification-code-input input[data-index="${index - 1}"]`);
      prevInput.focus();
    }
  }
}

function showVerificationMessage(message, type) {
  const messageEl = document.getElementById('verificationMessage');
  messageEl.textContent = message;
  messageEl.className = `alert alert-${type} mt-3`;
  messageEl.classList.remove('d-none');
}

/* ====== MODAL OPEN FUNCTIONS ====== */
function openGradesModal(){
  const m = new bootstrap.Modal(document.getElementById('gradesModal'));
  m.show();
  __currentView = 'list';
  activateTabs();
  loadGradesData();
}

// This function opens the actual all grades modal (after verification)
function openAllGradesModalDirect() {
  const m = new bootstrap.Modal(document.getElementById('allGradesModal'));
  m.show(); 
  loadAllGradesData();
}

function openCopyGradesModal(){
  const m = new bootstrap.Modal(document.getElementById('copyGradesModal'));
  m.show(); 
  loadCopyGradesData();
}

async function loadAcademicYears(){
  if (__ayLoaded) return;
  const sel = document.getElementById('aySelect');
  const help= document.getElementById('ayHelp');
  if (!sel) return;
  sel.innerHTML = '<option value="">Loading…</option>';
  help?.classList.add('d-none');

  try{
    const r = await fetch(`{{ route('student.grades.years') }}`, {
      headers:{ 'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content }
    });
    const data = await r.json();
    sel.innerHTML = '';
    if (data.success && Array.isArray(data.years) && data.years.length){
      data.years.forEach(y=>{
        const o = document.createElement('option');
        o.value = String(y.id);
        o.text  = y.label;
        o.dataset.label = y.label;
        sel.appendChild(o);
      });
      if (window.selectedAY_ID) sel.value = window.selectedAY_ID;
      __ayLoaded = true;
    } else {
      sel.innerHTML = '<option value="">No Academic Years found</option>';
      help?.classList.remove('d-none');
    }
  }catch{
    sel.innerHTML = '<option value="">No Academic Years found</option>';
    help?.classList.remove('d-none');
  }
}

function openAySemPicker(){
  loadAcademicYears().then(()=>{
    const semSel = document.getElementById('semSelect');
    if (semSel) semSel.value = window.selectedSEM || 'SECOND';
    new bootstrap.Modal(document.getElementById('aySemPickerModal')).show();
  });
}

/* ====== INIT ====== */
document.addEventListener('DOMContentLoaded', ()=>{
  setFilterLabel();
  document.getElementById('filterOpen')?.addEventListener('click', openAySemPicker);

  document.getElementById('applyAySemBtn')?.addEventListener('click', ()=>{
    const aySel  = document.getElementById('aySelect');
    const semSel = document.getElementById('semSelect');

    const ayId  = aySel?.value || '';
    const ayLbl = aySel?.selectedOptions[0]?.dataset?.label || aySel?.selectedOptions[0]?.textContent || '';
    const semH  = (semSel?.value || 'SECOND').toUpperCase();

    if (!ayId){ alert('Please choose an Academic Year.'); return; }

    window.selectedAY_ID  = ayId;
    window.selectedAY_LBL = ayLbl;
    window.selectedSEM    = semH;
    localStorage.setItem('selectedAY_ID', ayId);
    localStorage.setItem('selectedAY_LBL', ayLbl);
    localStorage.setItem('selectedSEM', semH);

    setFilterLabel();
    bootstrap.Modal.getInstance(document.getElementById('aySemPickerModal'))?.hide();
    openGradesModal();
  });

  // Tabs
  document.getElementById('tabList')?.addEventListener('click', ()=>{
    __currentView='list'; activateTabs(); renderCurrent();
  });
  document.getElementById('tabTable')?.addEventListener('click', ()=>{
    __currentView='table'; activateTabs(); renderCurrent();
  });

  // reset bodies on close
  ['gradesModal','copyGradesModal','allGradesModal','verificationModal'].forEach(id=>{
    const el=document.getElementById(id); if(!el) return;
    el.addEventListener('hidden.bs.modal', ()=>{
      if (id==='gradesModal'){
        __gradesCache = [];
        const container = document.getElementById('gradesContent');
        if (container) container.innerHTML = LOADING_HTML.replace('Loading...','Loading grades...');
      } else if (id === 'copyGradesModal') {
        const node = document.getElementById('copyGradesModalBody');
        if (node) node.innerHTML = LOADING_HTML.replace('Loading...','Loading copy of grades...');
      } else if (id === 'allGradesModal') {
        const node = document.getElementById('allGradesModalBody');
        if (node) node.innerHTML = LOADING_HTML.replace('Loading...','Loading all grades...');
      } else if (id === 'verificationModal') {
        resetVerificationModal();
      }
    });
  });
});

/* ====== TABS & VIEW SWITCH ====== */
function activateTabs(){
  document.getElementById('tabList')?.classList.toggle('active', __currentView==='list');
  document.getElementById('tabTable')?.classList.toggle('active', __currentView==='table');
}
function renderCurrent(){
  if (!__gradesCache.length){
    const container = document.getElementById('gradesContent');
    if (container) container.innerHTML = `
      <div class="no-data">
        <i class="fa-solid fa-magnifying-glass"></i>
        <h5>No Grades</h5>
        <p>No records to display.</p>
      </div>`;
    return;
  }
  if (__currentView==='list') renderList(__gradesCache);
  else renderTable(__gradesCache);
}

/* ====== LOAD GRADES (AY + SEM) ====== */
/* ====== LOAD GRADES (AY + SEM) ====== */
function loadGradesData() {
  const content = document.getElementById('gradesContent');
  const title   = document.getElementById('gradesModalTitle');

  const ayId  = window.selectedAY_ID;
  const ayLbl = window.selectedAY_LBL || '';
  const semH  = (window.selectedSEM || 'SECOND').toUpperCase();
  
  // Clean the semester parameter - remove any trailing colon and number
  const semClean = semH.replace(/:.*$/, '');
  const semApi = SEM_MAP[semClean] || semClean;

  content.innerHTML = LOADING_HTML.replace('Loading...','Loading grades...');

  const params = new URLSearchParams();
  params.set('filter', 'ay');
  if (ayId) params.set('ay_id', ayId);
  params.set('sem', semApi);

  console.log('Loading grades with params:', { ayId, ayLbl, semH, semClean, semApi });

  fetch(`{{ route('student.grades.modal') }}?${params.toString()}`, {
    headers: {
      'X-Requested-With':'XMLHttpRequest',
      'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
    }
  })
  .then(r => { 
    if (!r.ok) throw new Error(`HTTP ${r.status}`); 
    return r.json(); 
  })
  .then(d => {
    console.log('Grades response:', d);
    
    if (d.ay_label && d.semester_human) {
      title.textContent = `Grades — ${d.ay_label} • ${d.semester_human}`;
    } else if (ayId) {
      title.textContent = `Grades — ${ayLbl || ('AY #' + ayId)} • ${semH}`;
    } else {
      title.textContent = 'Grades';
    }

    if (!d.success) {
      content.innerHTML = `
        <div class="no-data">
          <i class="fa-solid fa-magnifying-glass"></i>
          <h5>No Grades for Selected Filter</h5>
          <p>${d.message || 'No records found for the chosen AY & Semester.'}</p>
        </div>`;
      __gradesCache = [];
      return;
    }

    __gradesCache = Array.isArray(d.grades) ? d.grades : [];

    if (!__gradesCache.length) {
      content.innerHTML = `
        <div class="no-data">
          <i class="fa-solid fa-magnifying-glass"></i>
          <h5>No Grades for Selected Filter</h5>
          <p>${d.message || 'No grades found.'}</p>
        </div>`;
      return;
    }

    renderCurrent();
  })
  .catch(e => {
    console.error('Error loading grades:', e);
    content.innerHTML = `
      <div class="no-data">
        <i class="fa-solid fa-exclamation-circle"></i>
        <h5>Connection Error</h5>
        <p>Unable to connect to server.</p>
        <p class="text-muted mt-2">${e.message}</p>
      </div>`;
  });
}

/* ====== RENDERERS ====== */
function renderList(grades){
  const content = document.getElementById('gradesContent');
  const html = grades.map(g=>{
    const code = esc(g.course_code||'N/A');
    const name = esc(g.course_name||'Course Name Not Available');
    const units= esc(normalizeUnits(g.units));
    const instr= esc(g.instructor||'Instructor Not Available');
    const grd  = esc(g.grade ?? 'N/A');
    return `
      <div class="course-row">
        <div class="course-header">
          <h6 class="course-code">${code} - ${name}
            <span class="text-muted">(${units} units)</span>
          </h6>
          <p class="instructor">${instr}</p>
          <div class="mt-1">
            ${grd} <i class="fa-solid fa-check" style="color:#22c55e"></i>
          </div>
        </div>
      </div>`;
  }).join('');
  content.innerHTML = `
    <div class="grades-card">
      <div class="grades-table-view">${html}</div>
    </div>`;
}

function renderTable(grades){
  const content = document.getElementById('gradesContent');
  const rows = grades.map(g=>{
    const code = esc(g.course_code||'N/A');
    const name = esc(g.course_name||'Course Name Not Available');
    const units= esc(normalizeUnits(g.units));
    const grd  = esc(g.grade ?? 'N/A');
    const instr= esc(g.instructor||'Instructor Not Available');
    return `
      <tr>
        <td>${code}</td>
        <td class="desc-cell">${name}</td>
        <td class="text-center">${units}</td>
        <td class="text-center">${grd}</td>
        <td class="text-center">
          <span class="status-ok"><i class="fa-solid fa-check"></i></span>
        </td>
        <td>${instr}</td>
      </tr>`;
  }).join('');
  content.innerHTML = `
    <div class="grades-table-wrapper">
      <table class="grades-table">
        <thead>
          <tr>
            <th style="width:110px">Code</th>
            <th>Description</th>
            <th style="width:90px">Units</th>
            <th style="width:90px">Grade</th>
            <th style="width:100px">Status</th>
            <th style="min-width:220px">Instructor</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
}

/* ====== COPY OF GRADES: LOAD IMAGE ====== */
function loadCopyGradesData(){
  const el = document.getElementById('copyGradesModalBody');
  el.innerHTML = `
    <div class="text-center py-4">
      <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
      <p class="mt-2 text-muted">Loading copy of grades...</p>
    </div>`;

  const ayLabel = window.selectedAY_LBL || '';
  const semUi   = (window.selectedSEM || '').toUpperCase();
  const semApi  = SEM_MAP[semUi] || semUi;

  if (!ayLabel || !semUi) {
    el.innerHTML = `
      <div class="no-data">
        <i class="fa-solid fa-filter-circle-xmark"></i>
        <h5>No Filter Selected</h5>
        <p class="text-muted mt-2">
          Please choose an Academic Year and Semester first, then open "View Copy of Grades" again.
        </p>
      </div>`;
    return;
  }

  const params = new URLSearchParams();
  params.set('ay_label', ayLabel);
  params.set('sem', semApi);

  fetch(`{{ route('student.grades.copy-modal') }}?${params.toString()}`, {
    headers:{
      'X-Requested-With':'XMLHttpRequest',
      'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
    }
  })
  .then(r => r.json())
  .then(d => {
    const src = d.image_base64 || d.image || null;

    if (!d.success || !src) {
      el.innerHTML = `
        <div class="no-data">
          <i class="fa-solid fa-file-pdf"></i>
          <h5>No Copy of Grades</h5>
          <p class="text-muted mt-2">
            ${d.message || `No COG image is stored for ${ayLabel} ${semUi}.`}
          </p>
        </div>`;
      return;
    }

    el.innerHTML = `
      <div class="alert alert-info copy-alert" role="alert">
        Printed copy is available upon request in the Registration Services Office.
      </div>
      <div class="cog-frame">
        <div class="cog-frame-inner">
          <img src="${src}" alt="Student's Copy of Grades - ${ayLabel} ${semUi}">
        </div>
      </div>`;
  })
  .catch(() => {
    el.innerHTML = `
      <div class="no-data">
        <i class="fa-solid fa-exclamation-circle"></i>
        <h5>Error</h5>
        <p>Unable to load copy of grades.</p>
      </div>`;
  });
}

/* ====== VIEW ALL GRADES – LOAD FULL DOCUMENT ======
 * Expected JSON from controller:
 * {
 *   success: true,
 *   html: "<tables per semester ...>",   // NO header/INFO table here
 *   profile: { srcode, fullname, fullname_short, program },
 *   generated_at: "mm/dd/YYYY"
 * }
 */
function loadAllGradesData(){
  const el = document.getElementById('allGradesModalBody');
  el.innerHTML = LOADING_HTML.replace('Loading...','Loading all grades...');

  fetch(`{{ route('student.grades.all-modal') }}`, {
    headers:{
      'X-Requested-With':'XMLHttpRequest',
      'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
    }
  })
  .then(r => r.json())
  .then(d => {
    if (!d.success || !d.html){
      el.innerHTML = `
        <div class="no-data">
          <i class="fa-solid fa-history"></i>
          <h5>All Academic Records</h5>
          <p class="text-muted mt-2">${d.message || 'No academic records found to display.'}</p>
        </div>`;
      return;
    }

    const p = d.profile || {};
    const srcode   = esc(p.srcode || '');
    const fullname = esc(p.fullname || '');
    const shortname= esc(p.fullname_short || fullname);
    const program  = esc(p.program || '');
    const today    = esc(d.generated_at || '{{ now('Asia/Manila')->format('m/d/Y') }}');

const headerHtml = `
      <table class="rg-header-table">
        <tr>
          <td class="rg-logo-cell">
            <div class="rg-logo-box">
              <img src="${REPORT_LOGO_URL}" alt="Batangas State University Logo">
            </div>
          </td>
          <td class="rg-text-cell">
            <div class="rg-header-text">
              <div class="rg-gov">Republic of the Philippines</div>
              <div class="rg-uni">BATANGAS STATE UNIVERSITY</div>
              <div class="rg-national">The National Engineering University</div>
              <div class="rg-campus">ARASOF-Nasugbu Campus</div>
              <div class="rg-addr">R. Martinez St., Brgy. Bucana, Nasugbu, Batangas, Philippines 4231</div>
              <div class="rg-contact">Tel Nos.: (+63 43) 416-0350 local 114 | +639190790672</div>
              <div class="rg-contact">
                Email Address: registrar.nasugbu@g.batstate-u.edu.ph |
                Website Address: https://www.batstate-u.edu.ph
              </div>
            </div>
          </td>
          <td class="rg-spacer-cell"></td>
        </tr>
      </table>

      <div class="rg-divider"></div>
      <div class="rg-title-main">REPORT OF GRADES</div>

      <!-- ROW 1 – SRCODE (full row) -->
      <table class="rg-info-table">
        <tr>
          <td class="rg-info-label">SRCODE :</td>
          <td colspan="3">${srcode}</td>
        </tr>
      </table>

      <!-- ROW 2 – FULLNAME (full row) -->
      <table class="rg-info-table">
        <tr>
          <td class="rg-info-label">FULLNAME :</td>
          <td colspan="3">${fullname}</td>
        </tr>
      </table>

      <!-- PROGRAM – separate bar only (no label row para hindi redundant) -->
      <div class="rg-program-bar">
        ${program}
      </div>
    `;


    el.innerHTML = `
      <div class="alert alert-info copy-alert" role="alert">
        Printed copy is available upon request in the Registration Services Office.
      </div>
      <div class="allgrades-wrapper">
        <div class="allgrades-page">
          ${headerHtml}
          ${d.html}
          <div class="rg-footer-note">
            *** NOT VALID WITHOUT UNIVERSITY DRY SEAL ***
          </div>
          <div class="rg-footer-line">
            <span>${shortname}</span>
            <span>${today}</span>
          </div>
        </div>
      </div>`;
  })
  .catch(() => {
    el.innerHTML = `
      <div class="no-data">
        <i class="fa-solid fa-exclamation-circle"></i>
        <h5>Error</h5>
        <p class="text-muted mt-2">Unable to load academic records.</p>
      </div>`;
  });
}
</script>
@endsection