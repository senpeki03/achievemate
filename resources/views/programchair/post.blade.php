@extends('programchair.programchairsidebar')

@section('content')
<div class="container py-4">

  {{-- Flash + Validation --}}
  @if(session('success'))
    <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger rounded-3 shadow-sm">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger rounded-3 shadow-sm">
      <div class="fw-bold mb-2">Please fix the following:</div>
      <ul class="mb-0">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body px-4 py-4">

    <div class="rounded-3 p-3 mb-3" style="background-color:#ffffff;">
      <h5 class="fw-bold text-uppercase mb-0 text-dark">Announcement</h5>
    </div>


      <p class="text-muted mb-3">Type your message. It will be saved as <strong>plain text</strong> (no HTML).</p>

      <form id="announcementForm"
            action="{{ route('programchair.post') }}"
            method="POST"
            enctype="multipart/form-data"
            onsubmit="document.getElementById('bodyInput').value = getEditorPlainText()">
        @csrf

        {{-- Title --}}
        <div class="mb-3">
          <input type="text"
                 name="title"
                 class="form-control form-control-lg rounded-3"
                 placeholder="Title"
                 required
                 value="{{ old('title') }}">
        </div>

        {{-- Plain Text Editor --}}
        <div class="mb-3">
          <input type="hidden" name="body" id="bodyInput" value="{{ old('body') }}">

          <div id="editor"
               contenteditable="true"
               class="form-control rounded-top-3"
               style="height: 180px; overflow-y: auto; white-space: pre-wrap;">{{ old('body') }}</div>

          <div class="d-flex flex-wrap gap-2 p-2 border border-top-0 rounded-bottom-3 bg-light">
            <button type="button" class="btn btn-light" onclick="toggleFullscreen()">
              <i class="bi bi-arrows-fullscreen"></i>
            </button>
            <label class="btn btn-light mb-0">
              <i class="bi bi-image me-1"></i> Cover image
              <input type="file" name="image" accept="image/*" class="d-none">
            </label>
            <div class="ms-auto small text-muted">Body saves as plain text</div>
          </div>
        </div>

        {{-- Academic Year --}}
        <div class="mb-3">
          <label for="academic_year" class="form-label fw-bold">Academic Year</label>
          <div class="input-group">
            <select id="academic_year" name="Academic_year" class="form-select" style="max-width: 260px;"
                    data-old="{{ old('Academic_year') }}">
              {{-- options injected by JS --}}
            </select>

            {{-- Optional manual entry --}}
            <input type="text" id="academic_year_custom" class="form-control d-none"
                   placeholder="YYYY-YYYY (e.g., 2024-2025)"
                   pattern="^\d{4}-\d{4}$">
            <button type="button" id="toggle_custom_ay" class="btn btn-outline-secondary">
              Custom
            </button>
          </div>
          <div class="form-text">
            Default follows the university calendar (Aug–Jul). Use “Custom” to type a specific span.
          </div>
        </div>

        {{-- Semester --}}
        <div class="mb-3">
          <label for="semester" class="form-label fw-bold">Semester</label>
          <select id="semester" name="Semester" class="form-select rounded-3">
            <option value="">Select semester</option>
            <option value="First Semester"
              {{ old('Semester') === 'First Semester' ? 'selected' : '' }}>
              First Semester
            </option>
            <option value="Second Semester"
              {{ old('Semester') === 'Second Semester' ? 'selected' : '' }}>
              Second Semester
            </option>
            <option value="Midyear Term"
              {{ old('Semester') === 'Midyear Term' ? 'selected' : '' }}>
              Midyear Term
            </option>
          </select>
          <div class="form-text">
            If left blank, the system will auto-detect the current semester based on today’s date.
          </div>
        </div>


        {{-- Submission Deadline --}}
        <div class="mb-3">
          <label for="end_date" class="form-label fw-bold">Submission Deadline</label>
          <input type="date" name="End_date" id="end_date" class="form-control rounded-3"
                 required value="{{ old('End_date') }}">
        </div>

        {{-- Optional flag --}}
        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" name="emergency" id="emergencyToggle"
                 {{ old('emergency') ? 'checked' : '' }}>
          <label class="form-check-label" for="emergencyToggle">
            Is this an emergency notification
          </label>
        </div>

        <div class="text-end">
          <button type="button" class="btn btn-primary px-4 fw-bold text-uppercase"
                  onclick="showFinalConfirm()">Post</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Confirm Modal --}}
<div class="modal fade" id="confirmAddModalFinal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="min-height: 260px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to post this announcement?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button type="submit" class="btn btn-primary px-4" form="announcementForm">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Success Modal --}}
@if(session('success'))
  <div class="modal fade show" id="successAddModal" style="display:block;" tabindex="-1" aria-hidden="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
      <div class="modal-content text-center border-0 rounded-4 shadow">
        <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
          <div class="mb-3 d-flex justify-content-center">
            <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4" autoplay muted loop playsinline style="width: 70px; height: 70px;"></video>
          </div>
          <h5 class="text-success fw-bold mb-1">Success</h5>
          <p class="mb-4 text-muted">{{ session('success') }}</p>
          <div>
            <a href="{{ url()->current() }}" class="btn btn-primary px-4">OK</a>
          </div>
        </div>
      </div>
    </div>
  </div>
@endif

{{-- Scripts --}}
<script>
  function getEditorPlainText() {
    const el = document.getElementById('editor');
    return (el.innerText || el.textContent || '').trim();
  }
  function toggleFullscreen() {
    document.getElementById('editor').classList.toggle('fullscreen-editor');
  }
  function showFinalConfirm() {
    document.getElementById('bodyInput').value = getEditorPlainText();
    const modal = new bootstrap.Modal(document.getElementById('confirmAddModalFinal'));
    modal.show();
  }

  // ----- Academic Year UI -----
  (function () {
    function computeCurrentAY(d = new Date()) {
      const y = d.getFullYear();
      const m = d.getMonth() + 1; // 1..12
      return (m >= 8) ? `${y}-${y + 1}` : `${y - 1}-${y}`;
    }
    function makeOptions(baseAY) {
      const start = parseInt(baseAY.split('-')[0], 10);
      return [
        { val: '', label: `Auto (${baseAY})` },
        { val: `${start - 1}-${start}`,     label: `${start - 1}-${start}` },
        { val: `${start}-${start + 1}`,     label: `${start}-${start + 1}` },
        { val: `${start + 1}-${start + 2}`, label: `${start + 1}-${start + 2}` },
      ];
    }
    function populateAYSelect() {
      const sel   = document.getElementById('academic_year');
      const oldVal= sel.getAttribute('data-old') || '';
      const ay    = computeCurrentAY();
      const opts  = makeOptions(ay);
      sel.innerHTML = '';
      opts.forEach(o => {
        const opt = document.createElement('option');
        opt.value = o.val; opt.textContent = o.label;
        sel.appendChild(opt);
      });
      if (oldVal) sel.value = oldVal;
    }
    function wireCustomToggle() {
      const btn = document.getElementById('toggle_custom_ay');
      const sel = document.getElementById('academic_year');
      const inp = document.getElementById('academic_year_custom');
      if (!btn || !sel || !inp) return;

      btn.addEventListener('click', () => {
        const toCustom = inp.classList.contains('d-none');
        if (toCustom) {
          inp.classList.remove('d-none');
          sel.classList.add('d-none');
          btn.textContent = 'Use list';
          if (!inp.value) inp.value = computeCurrentAY();
          sel.name = 'Academic_year_unused';
          inp.name = 'Academic_year';
        } else {
          inp.classList.add('d-none');
          sel.classList.remove('d-none');
          btn.textContent = 'Custom';
          inp.name = 'Academic_year_unused';
          sel.name = 'Academic_year';
        }
      });

      const oldVal = sel.getAttribute('data-old')?.trim() || '';
      const inList = Array.from(sel.options).some(o => o.value === oldVal);
      if (oldVal && !inList) {
        btn.click();
        inp.value = oldVal;
      }
    }
    document.addEventListener('DOMContentLoaded', function () {
      populateAYSelect();
      wireCustomToggle();
    });
  })();
</script>

{{-- Styles --}}
<style>
  .fullscreen-editor {
    position: fixed !important;
    top: 60px; left: 60px; right: 60px; bottom: 60px;
    z-index: 9999;
    background: white;
    padding: 20px;
    overflow-y: auto;
    border: 2px solid #0d6efd;
    border-radius: 12px;
  }
  #editor:focus { outline: none; }
  #editor { white-space: pre-wrap; }
</style>
@endsection
