{{-- resources/views/student/latin.blade.php --}}
@extends('student.studentsidebar')
@section('title', 'Latin Honors')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- Route: generate consent PDF --}}
<meta name="route-consent-generate" content="{{ route('student.latin.generate') }}">
{{-- Route: store simple consent flag to latin table --}}
<meta name="route-latin-store" content="{{ route('student.latin.store') }}">

@php
  use Illuminate\Support\Facades\Route as RouteFacade;
  $reqStore = RouteFacade::has('student.latin.requirements.store')
    ? route('student.latin.requirements.store')
    : url('/student/latin/requirements/store');
@endphp
<meta name="route-req-store" content="{{ $reqStore }}">

<div class="container py-4">
  <h3 class="fw-bold mb-1 text-white">Requirements Upload</h3>
  <p class="text-muted mb-4">Upload the following. For the two required items, you may mark <span class="fw-semibold">To Follow</span>.</p>

  @if(session('success'))
    <div class="alert alert-success">
      {{ session('success') }}
      @if(session('pdf_url'))
        <a href="{{ session('pdf_url') }}" target="_blank" class="ms-2">View PDF</a>
      @endif
    </div>
  @endif

  {{-- ===== Guidelines / Policies ===== --}}
  <div class="accordion mb-4" id="latinGuides">
    <div class="accordion-item">
      <h2 class="accordion-header" id="g1h">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#g1" aria-expanded="true" aria-controls="g1">
          Registrar Requirements (depends on department)
        </button>
      </h2>
      <div id="g1" class="accordion-collapse collapse show" aria-labelledby="g1h" data-bs-parent="#latinGuides">
        <div class="accordion-body">
          <ul class="mb-2">
            <li><span class="fw-semibold">TOR</span> (with remark: <em>for evaluation purposes only</em>)</li>
            <li><span class="fw-semibold">Birth Certificate / PSA</span></li>
            <li><span class="fw-semibold">Library Certificate</span></li>
            <li class="mt-2"><span class="fw-semibold">Shifter</span>: include <em>Honorable Dismissal</em> from previous school.</li>
            <li><span class="fw-semibold">4th year / transferee cases</span>: attach BatStateU evaluation copy if applicable.</li>
          </ul>
          <small class="text-muted">Note: some colleges may ask for additional paperwork—follow your department memo.</small>
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header" id="g2h">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#g2" aria-expanded="false" aria-controls="g2">
          Chairperson (Latin Honors) Requirements
        </button>
      </h2>
      <div id="g2" class="accordion-collapse collapse" aria-labelledby="g2h" data-bs-parent="#latinGuides">
        <div class="accordion-body">
          <ul class="mb-2">
            <li><span class="fw-semibold">Consent form / Application form</span> (generate below)</li>
            <li><span class="fw-semibold">Evaluation set</span>: updated prospectus &amp; curriculum sheet, copy of PSA, <span class="fw-semibold">Approval Sheet</span> <small class="text-muted"></small></li>
            <li><span class="fw-semibold">Endorsement letter</span> to Registrar</li>
            <li><span class="fw-semibold">Barangay Clearance</span></li>
          </ul>
          <div class="alert alert-info py-2">
            <div class="fw-semibold mb-1">Eligibility reminder (example rule):</div>
            <small>
              If the student has a grade of <span class="fw-semibold">2.25</span> in any subject, they do <span class="fw-semibold">not</span> qualify for <em>Cum Laude</em> even with a GWA of <span class="fw-semibold">1.75</span>. Apply equivalent thresholds to higher honors.
            </small>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ===== Requirements Upload (card bodies) ===== --}}
  <form id="latinReqForm" class="mb-4" enctype="multipart/form-data" method="POST" action="{{ $reqStore }}">
    @csrf
    <div class="row g-4">

      {{-- Approval Sheet (required, can be To Follow) --}}
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100 border-0">
          <div class="card-body">
            <label class="form-label fw-semibold">Approval Sheet <span class="text-danger">*</span></label>
            <div class="text-muted small mb-2">PDF or Image (JPG/PNG)</div>
            <div class="input-group mb-2">
              <input class="form-control" type="file" name="approval_sheet" id="approval_sheet" accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <div class="form-check ms-1">
              <input class="form-check-input tofollow" type="checkbox" id="approval_to_follow" name="approval_to_follow" value="1" data-target="#approval_sheet">
              <label for="approval_to_follow" class="form-check-label">Mark as <span class="fw-semibold">To Follow</span></label>
            </div>
            <div class="invalid-feedback d-block" id="err_approval" style="display:none;"></div>
          </div>
        </div>
      </div>

      {{-- Library Certificate (required, can be To Follow) --}}
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100 border-0">
          <div class="card-body">
            <label class="form-label fw-semibold">Certificate of Library <span class="text-danger">*</span></label>
            <div class="text-muted small mb-2">PDF or Image (JPG/PNG)</div>
            <div class="input-group mb-2">
              <input class="form-control" type="file" name="library_certificate" id="library_certificate" accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <div class="form-check ms-1">
              <input class="form-check-input tofollow" type="checkbox" id="library_to_follow" name="library_to_follow" value="1" data-target="#library_certificate">
              <label for="library_to_follow" class="form-check-label">Mark as <span class="fw-semibold">To Follow</span></label>
            </div>
            <div class="invalid-feedback d-block" id="err_library" style="display:none;"></div>
          </div>
        </div>
      </div>

      {{-- Barangay Clearance (optional) --}}
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100 border-0">
          <div class="card-body">
            <label class="form-label fw-semibold">Barangay Clearance <span class="text-muted">(optional)</span></label>
            <div class="text-muted small mb-2">PDF or Image (JPG/PNG)</div>
            <input class="form-control" type="file" name="barangay_clearance" id="barangay_clearance" accept=".pdf,.jpg,.jpeg,.png">
          </div>
        </div>
      </div>

      {{-- Birth Certificate (optional) --}}
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100 border-0">
          <div class="card-body">
            <label class="form-label fw-semibold">Birth Certificate <span class="text-muted">(optional)</span></label>
            <div class="text-muted small mb-2">PDF or Image (JPG/PNG)</div>
            <input class="form-control" type="file" name="birth_certificate" id="birth_certificate" accept=".pdf,.jpg,.jpeg,.png">
          </div>
        </div>
      </div>

    </div>

    <div class="d-flex gap-2 mt-4">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-upload me-1"></i> Save Requirements
      </button>
      <button type="button" class="btn btn-outline-secondary" id="btnGenerateConsent">
        <i class="bi bi-file-earmark-text me-1"></i> Generate Consent
      </button>
    </div>
  </form>
</div>

{{-- ===== Consent Modal ===== --}}
<div class="modal fade" id="consentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content rounded-4 overflow-hidden">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Consent Form for the Evaluation of Academic Records</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" style="height:80vh;">
        <iframe id="consentIframe" src="" width="100%" height="100%" style="border:0;"></iframe>
      </div>
      <div class="modal-footer">
        <a id="consentOpenNewTab" class="btn btn-outline-secondary" target="_blank" rel="noopener">Open in new tab</a>
        <button type="button" class="btn btn-primary" id="consentDoneBtn" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const token     = document.querySelector('meta[name="csrf-token"]').content;
  const genUrl    = document.querySelector('meta[name="route-consent-generate"]').content;
  const storeUrl  = document.querySelector('meta[name="route-latin-store"]').content;
  const reqStore  = document.querySelector('meta[name="route-req-store"]').content;

  function qs(name){ return new URLSearchParams(location.search).get(name); }

  // Disable file when "To Follow" is checked
  document.addEventListener('change', (e)=>{
    if(e.target && e.target.classList.contains('tofollow')){
      const targetSel = e.target.getAttribute('data-target');
      const input = document.querySelector(targetSel);
      if(input){
        input.disabled = e.target.checked;
        if(e.target.checked){ input.value = ''; }
      }
    }
  });

  function validateRequired() {
    let ok = true;
    const pairs = [
      { file: '#approval_sheet', check: '#approval_to_follow', err: '#err_approval', label: 'Approval Sheet' },
      { file: '#library_certificate', check: '#library_to_follow', err: '#err_library', label: 'Certificate of Library' },
    ];
    pairs.forEach(p=>{
      const f = document.querySelector(p.file);
      const c = document.querySelector(p.check);
      const e = document.querySelector(p.err);
      const hasFile = f && f.files && f.files.length > 0;
      const marked  = c && c.checked;
      if(!hasFile && !marked){
        ok = false;
        if(e){ e.style.display='block'; e.textContent = `${p.label}: upload a file or mark "To Follow".`; }
      }else{
        if(e){ e.style.display='none'; e.textContent=''; }
      }
    });
    return ok;
  }

  const form = document.getElementById('latinReqForm');
  if(form){
    form.addEventListener('submit', (ev)=>{
      if(!validateRequired()){
        ev.preventDefault();
        ev.stopPropagation();
        window.scrollTo({ top: form.offsetTop - 80, behavior: 'smooth' });
      }
    });
  }

  async function generateAndShow(){
    try {
      const res = await fetch(genUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });
      const ct = res.headers.get('content-type') || '';
      const text = await res.text();
      if (!ct.includes('application/json')) {
        throw new Error(`Server did not return JSON (${res.status}). First bytes: ${text.slice(0,120)}`);
      }
      const j = JSON.parse(text);
      if (!res.ok || !j.ok || !j.url) throw new Error(j.message || `HTTP ${res.status}`);
      const bust = j.url + (j.url.includes('?') ? '&' : '?') + 'v=' + Date.now();
      const iframe = document.getElementById('consentIframe');
      const openA  = document.getElementById('consentOpenNewTab');
      if (iframe) iframe.src = bust;
      if (openA)  openA.href = bust;
      if (window.bootstrap?.Modal) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('consentModal')).show();
      } else {
        window.open(bust, '_blank');
      }
    } catch (e) {
      alert('Unable to generate consent form: ' + (e.message || e));
      console.error(e);
    }
  }

  async function saveLatinConsent(){
    try {
      const res = await fetch(storeUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ consent: 1 })
      });
      const text = await res.text();
      let j = {};
      try { j = JSON.parse(text || '{}'); } catch(_) {}
      if (!res.ok || !j.ok) {
        const msg = j.message || `Save failed (HTTP ${res.status})`;
        throw new Error(msg);
      }
      console.info('Latin consent saved:', j);
    } catch (e) {
      alert('Failed to save Latin consent: ' + (e.message || e));
      console.error(e);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (qs('auto') === 'consent') generateAndShow();
    const genBtn  = document.getElementById('btnGenerateConsent');
    if (genBtn) genBtn.addEventListener('click', generateAndShow);
    const doneBtn = document.getElementById('consentDoneBtn');
    if (doneBtn) doneBtn.addEventListener('click', saveLatinConsent);
  });
})();
</script>
@endsection
