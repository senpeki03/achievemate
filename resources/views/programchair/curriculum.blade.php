@extends('programchair.programchairsidebar')

@php
  // -------- Normalize inputs --------
  $curriculums = isset($curriculums) ? collect($curriculums) : collect();
  $campuses    = $campuses  ?? [];
  $colleges    = $colleges  ?? [];
  $programs    = $programs  ?? [];
  $majors      = $majors    ?? [];

  $locks       = $locks ?? ['campus'=>false,'college'=>false,'program'=>false,'major'=>false];
  $prefill     = $prefill ?? ['Campus_id'=>null,'College_id'=>null,'Program_id'=>null,'Major_id'=>null];
  $accessLevel = $accessLevel ?? 'Campus';

  // -------- Visibility flags --------
  $showProgram = in_array($accessLevel, ['Program','Major'], true);
  $showMajor   = ($accessLevel === 'Major');

  // -------- Locks (bool) --------
  $campusLocked  = !empty($locks['campus']);
  $collegeLocked = !empty($locks['college']);
  $programLocked = !empty($locks['program']);
  $majorLocked   = !empty($locks['major']);

  // -------- Scope / defaults --------
  $scopeCampus   = (int)($prefill['Campus_id']  ?? 0);
  $scopeCollege  = (int)($prefill['College_id'] ?? 0);
  $scopeProgram  = (int)($prefill['Program_id'] ?? 0);
  $scopeMajor    = (int)($prefill['Major_id']   ?? 0);

  // -------- Safe names for locked fields --------
  $prefCampusName = '';
  foreach ($campuses as $c) { if ((int)$c->Campus_id === $scopeCampus) { $prefCampusName = $c->Campus_name; break; } }

  $prefCollegeName = '';
  foreach ($colleges as $co) { if ((int)$co->College_id === $scopeCollege) { $prefCollegeName = $co->College_name; break; } }

  $prefProgramName = '';
  foreach ($programs as $p) { if ((int)$p->Program_id === $scopeProgram) { $prefProgramName = $p->Program_name; break; } }

  $prefMajorName = '';
  foreach ($majors as $m) { if ((int)$m->Major_id === $scopeMajor) { $prefMajorName = $m->Major_name; break; } }
@endphp

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Curriculum</h3>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary text-white" title="Filter">
        <i class="bi bi-funnel-fill text-white"></i>
      </button>
      <button class="btn btn-outline-primary px-4 text-white" data-bs-toggle="modal" data-bs-target="#addCurriculumModal">
        <i class="bi bi-plus-lg me-1 text-white"></i> Add Curriculum
      </button>
    </div>
  </div>

  @if ($curriculums->isEmpty())
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-body py-5">
        <div class="d-flex flex-column justify-content-center align-items-center text-center" style="min-height: 70vh;">
          <img src="{{ asset('img/Curriculum.png') }}" alt="No curriculum" style="width: 100px; height: 100px;" class="mb-4">
          <h5 class="fw-bold text-dark mb-2">No Curriculum added</h5>
          <p class="text-muted mb-4">Start adding curriculum</p>
          <div class="d-flex gap-3">
            <button class="btn btn-outline-primary px-4" data-bs-toggle="modal" data-bs-target="#addCurriculumModal">
              <i class="bi bi-file-earmark-arrow-up me-1"></i> Add Curriculum
            </button>
          </div>
        </div>
      </div>
    </div>
  @else
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-body">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Curriculum</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($curriculums as $curriculum)
              @php
                $filenameParts = explode('_', $curriculum->Curriculum_name, 2);
                $originalName  = isset($filenameParts[1]) ? $filenameParts[1] : $curriculum->Curriculum_name;
              @endphp
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <img src="{{ asset('img/PDF_file_icon.png') }}" alt="PDF" width="40" class="me-3">
                    <div>
                      <h6 class="fw-bold mb-1">{{ $originalName }}</h6>
                      <a href="{{ route('programchair.curriculum.view', $curriculum->curriculum_id) }}" class="text-primary small text-decoration-none">
                        <i class="bi bi-eye me-1"></i> View Details
                      </a>
                    </div>
                  </div>
                </td>
                <td class="text-end">
                  <button
                    class="btn btn-sm btn-outline-danger"
                    data-action="delete"
                    data-id="{{ (int)$curriculum->curriculum_id }}"
                    data-url="{{ route('programchair.curriculum.destroy', $curriculum->curriculum_id) }}"
                  >
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>

{{-- =================== ADD CURRICULUM =================== --}}
<div class="modal fade" id="addCurriculumModal" tabindex="-1">
  <div class="modal-dialog" style="max-width: 800px;">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Curriculum</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="curriculumForm" enctype="multipart/form-data">
        @csrf
        <div class="modal-body">

          {{-- Campus --}}
          @if(!$campusLocked)
            <div class="mb-3">
              <label class="form-label">Campus Name</label>
              <select class="form-select" name="campus_id" id="campusSelect" required>
                <option value="" disabled {{ $scopeCampus ? '' : 'selected' }}>Select Campus</option>
                @foreach($campuses as $c)
                  <option value="{{ (int)$c->Campus_id }}" {{ (int)$c->Campus_id === $scopeCampus ? 'selected' : '' }}>
                    {{ $c->Campus_name }}
                  </option>
                @endforeach
              </select>
            </div>
          @else
            <div class="mb-3">
              <label class="form-label">Campus Name</label>
              <input type="text" class="form-control" value="{{ $prefCampusName }}" disabled>
              <input type="hidden" name="campus_id" value="{{ $scopeCampus }}">
            </div>
          @endif

          {{-- College --}}
          <div class="mb-3">
            <label class="form-label">College Name</label>
            @if(!$collegeLocked)
              <select class="form-select" name="college_id" id="collegeSelect" required>
                <option value="" disabled {{ $scopeCollege ? '' : 'selected' }}>Select College</option>
                @foreach($colleges as $co)
                  <option value="{{ (int)$co->College_id }}" {{ (int)$co->College_id === $scopeCollege ? 'selected' : '' }}>
                    {{ $co->College_name }}
                  </option>
                @endforeach
              </select>
            @else
              <input type="text" class="form-control" value="{{ $prefCollegeName }}" disabled>
              <input type="hidden" name="college_id" value="{{ $scopeCollege }}">
            @endif
          </div>

          {{-- $showProgram == true --}}
          @if($showProgram)
            <div class="mb-3">
              <label class="form-label">Program Name</label>
              @if(!$programLocked)
                <select class="form-select" name="program_id" id="programSelect" required>
                  <option value="" disabled {{ $scopeProgram ? '' : 'selected' }}>Select Program</option>
                  @foreach($programs as $p)
                    <option value="{{ (int)$p->Program_id }}" {{ (int)$p->Program_id === $scopeProgram ? 'selected' : '' }}>
                      {{ $p->Program_name }}
                    </option>
                  @endforeach
                </select>
              @else
                <input type="text" class="form-control" value="{{ $prefProgramName }}" disabled>
                <input type="hidden" name="program_id" value="{{ $scopeProgram }}">
              @endif
            </div>
          @else
            {{-- Not required at this access level: either omit entirely OR keep a single empty hidden --}}
            <input type="hidden" name="program_id" value="">
          @endif


        @if($showMajor)
          <div class="mb-3">
            <label class="form-label">Major</label>
            @if(!$majorLocked)
              <select class="form-select" name="major_id" id="majorSelect" required>
                <option value="" disabled {{ $scopeMajor ? '' : 'selected' }}>Select Major</option>
                @foreach($majors as $m)
                  <option value="{{ (int)$m->Major_id }}" {{ (int)$m->Major_id === $scopeMajor ? 'selected' : '' }}>
                    {{ $m->Major_name }}
                  </option>
                @endforeach
              </select>
            @else
              <input type="text" class="form-control" value="{{ $prefMajorName }}" disabled>
              <input type="hidden" name="major_id" value="{{ $scopeMajor }}">
            @endif
          </div>
        @else
          <input type="hidden" name="major_id" value="">
        @endif


          {{-- (Academic Year removed as requested) --}}
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary px-4" type="button" onclick="confirmCurriculumAdd()">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- =================== CONFIRM / SUCCESS MODALS =================== --}}
<div class="modal fade" id="confirmAddModalFinal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" width="60" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to add this Curriculum?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" onclick="submitAddCurriculum()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="successAddModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4" autoplay muted loop playsinline style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Record has been successfully added</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" width="60" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this curriculum?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteCurriculum()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
                 autoplay muted loop playsinline style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Record has been successfully deleted</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal" onclick="window.location.reload()">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ======================= DATA (ONE JSON BLOB) ======================= --}}
<script type="application/json" id="cfg-json">
{!! json_encode([
  'locks'          => $locks,
  'prefill'        => $prefill,
  'showProgram'    => $showProgram,
  'showMajor'      => $showMajor,
  'colleges'       => $colleges,
  'programs'       => $programs,
  'majors'         => $majors,
  'scopeCampus'    => (int)$scopeCampus,
  'scopeCollege'   => (int)$scopeCollege,
  'csrf'           => csrf_token(),
  // routes
  'insertUrl'      => route('programchair.curriculum.insert'),
]) !!}
</script>

{{-- ======================= SCRIPTS ======================= --}}
<script>
  // Load config safely (no Blade inside this script)
  function readCfg() {
    const el = document.getElementById('cfg-json');
    try { return JSON.parse(el.textContent); } catch(e) { return {}; }
  }
  const CFG = readCfg();

  // ---- delete flow ----
  let deleteCurriculumId = null;
  let deleteUrl = null;

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('button[data-action="delete"]');
    if (!btn) return;
    deleteCurriculumId = parseInt(btn.getAttribute('data-id') || 0);
    deleteUrl = btn.getAttribute('data-url') || null;
    new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
  });

  function submitDeleteCurriculum() {
    if (!deleteCurriculumId || !deleteUrl) return;

    fetch(deleteUrl, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': CFG.csrf,
        'Accept': 'application/json'
      }
    })
    .then(r => r.json())
    .then(data => {
      bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal')).hide();
      if (data && data.success) {
        const ok = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
        ok.show();
        setTimeout(() => { ok.hide(); location.reload(); }, 1200);
      } else {
        alert((data && data.message) || 'Delete failed.');
      }
    })
    .catch(e => alert('Error: ' + e.message));
  }
  window.submitDeleteCurriculum = submitDeleteCurriculum;

  // ---- add flow ----
  function confirmCurriculumAdd() {
    new bootstrap.Modal(document.getElementById('confirmAddModalFinal')).show();
  }
  window.confirmCurriculumAdd = confirmCurriculumAdd;

  function submitAddCurriculum(){
    const fd = new FormData(document.getElementById('curriculumForm'));
    fetch(CFG.insertUrl, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CFG.csrf,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: fd
    })
    .then(async res => {
      const ct = res.headers.get('content-type') || '';
      if (!ct.includes('application/json')) {
        const t = await res.text();
        throw new Error('Non-JSON ('+res.status+'): ' + t.slice(0,120));
      }
      const data = await res.json();
      if (!res.ok || !data.success) {
        if (data.errors) {
          const first = Object.values(data.errors)[0];
          throw new Error(Array.isArray(first) ? first[0] : String(first));
        }
        throw new Error(data.message || 'Insert failed');
      }
      bootstrap.Modal.getInstance(document.getElementById('confirmAddModalFinal')).hide();
      const ok = new bootstrap.Modal(document.getElementById('successAddModal'));
      ok.show();
      setTimeout(() => { ok.hide(); window.location.href = data.redirect; }, 1200);
    })
    .catch(e => alert('Insert failed: ' + e.message));
  }
  window.submitAddCurriculum = submitAddCurriculum;

  // ---- dependent dropdowns ----
  const campusSelect  = document.getElementById('campusSelect');
  const collegeSelect = document.getElementById('collegeSelect');
  const programSelect = document.getElementById('programSelect');
  const majorSelect   = document.getElementById('majorSelect');

  function fillDropdown(select, items, valueKey, textKey) {
    if (!select) return;
    select.innerHTML = '<option value="" disabled selected>Select</option>';
    items.forEach(function(it){
      var o = document.createElement('option');
      o.value = it[valueKey];
      o.textContent = it[textKey];
      select.appendChild(o);
    });
    select.disabled = items.length === 0;
  }
  function reset(select) {
    if (!select) return;
    select.innerHTML = '<option value="" disabled selected>Select</option>';
    select.disabled = true;
  }

  const LOCKS         = CFG.locks || {};
  const SHOW_PROGRAM  = !!CFG.showProgram;
  const SHOW_MAJOR    = !!CFG.showMajor;
  const colleges      = CFG.colleges || [];
  const programs      = CFG.programs || [];
  const majors        = CFG.majors || [];
  const SCOPE_CAMPUS  = parseInt(CFG.scopeCampus || 0);
  const SCOPE_COLLEGE = parseInt(CFG.scopeCollege || 0);

  // Campus -> College
  if (!LOCKS.campus && campusSelect) {
    campusSelect.addEventListener('change', function () {
      var campusId = parseInt(this.value || 0);
      var filtered = colleges.filter(function(c){ return parseInt(c.Campus_id) === campusId; });
      if (collegeSelect) fillDropdown(collegeSelect, filtered, 'College_id', 'College_name');
      if (SHOW_PROGRAM) reset(programSelect);
      if (SHOW_MAJOR)   reset(majorSelect);
    });
  }

  // College -> Program
  if (!LOCKS.college && collegeSelect) {
    collegeSelect.addEventListener('change', function () {
      var campusId  = parseInt(campusSelect ? (campusSelect.value || SCOPE_CAMPUS) : SCOPE_CAMPUS);
      var collegeId = parseInt(this.value || 0);
      var filtered  = programs.filter(function(p){
        return parseInt(p.Campus_id) === campusId && parseInt(p.College_id) === collegeId;
      });
      if (SHOW_PROGRAM && programSelect) {
        fillDropdown(programSelect, filtered, 'Program_id', 'Program_name');
        if (SHOW_MAJOR) reset(majorSelect);
      }
    });
  }

  // Program -> Major
  if (!LOCKS.program && SHOW_MAJOR && programSelect) {
    programSelect.addEventListener('change', function () {
      var campusId  = parseInt(campusSelect ? (campusSelect.value || SCOPE_CAMPUS) : SCOPE_CAMPUS);
      var collegeId = parseInt(collegeSelect ? (collegeSelect.value || SCOPE_COLLEGE) : SCOPE_COLLEGE);
      var programId = parseInt(this.value || 0);
      var filtered  = majors.filter(function(m){
        return parseInt(m.Campus_id)  === campusId &&
               parseInt(m.College_id) === collegeId &&
               parseInt(m.Program_id) === programId;
      });
      if (majorSelect) fillDropdown(majorSelect, filtered, 'Major_id', 'Major_name');
    });
  }

  // Prime on modal open
  var addModal = document.getElementById('addCurriculumModal');
  if (addModal) {
    addModal.addEventListener('shown.bs.modal', function () {
      if (!LOCKS.campus && campusSelect) campusSelect.dispatchEvent(new Event('change'));
      else if (!LOCKS.college && collegeSelect) collegeSelect.dispatchEvent(new Event('change'));
      else if (!LOCKS.program && SHOW_MAJOR && programSelect) programSelect.dispatchEvent(new Event('change'));
    });
  }
</script>

@endsection
