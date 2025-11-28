@extends('registrar.registrarsidebar')

@section('content')
<div class="container py-4">

  @php
    $level          = $level ?? 'college';
    $campusId       = $campusId ?? null;
    $userCampusId   = $userCampusId ?? null;
    $userCampusName = $userCampusName ?? null;

    $backHref = null; 
    $backText = 'Back';

    if ($level === 'program') {
      $backHref = route('registrar.student', ['level' => 'college', 'campus_id' => $campusId]);
      $backText = 'Back to Colleges';
    } elseif ($level === 'major') {
      $backHref = route('registrar.student', [
        'level'     => 'program',
        'campus_id' => $campusId,
        'college_id'=> $collegeId ?? null
      ]);
      $backText = 'Back to Programs';
    }
  @endphp

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-2">
      @if($backHref)
        <a href="{{ $backHref }}" class="btn btn-outline-light rounded-3">
          <i class="bi bi-arrow-left-circle me-1"></i> {{ $backText }}
        </a>
      @endif
      <h3 class="fw-bold text-white mb-0 ms-1">{{ $title ?? 'Students' }}</h3>
    </div>

    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" title="Filter">
        <i class="bi bi-funnel-fill"></i>
      </button>
      <button class="btn btn-outline-primary px-4 text-white" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-plus-lg me-1 text-white"></i> Add Student
      </button>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      @php
        $rows        = collect($rows ?? []);
        $hasStudents = $rows->sum('total') > 0;
      @endphp

      @if(!$hasStudents)
        <div class="d-flex align-items-center justify-content-center" style="height:70vh;">
          <div class="text-center">
            <div class="mx-auto mb-4">
              <img src="{{ asset('img/box2.png') }}" alt="Empty Box" style="width:110px;height:auto;opacity:0.9;">
            </div>
            <h5 class="fw-semibold mb-2">No Students Added</h5>
            <p class="text-muted mb-4">
              Once you add students for this
              {{ $level === 'college' ? 'campus/college' : ($level === 'program' ? 'program' : 'major') }},
              they'll show up here.
            </p>
            <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
              <i class="bi bi-plus-lg me-1"></i> Add Student
            </button>
          </div>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:64px">#</th>
                <th>
                  @if($level === 'college')
                    College
                  @elseif($level === 'program')
                    Program
                  @else
                    Major
                  @endif
                </th>
                <th class="text-end">No. of Students</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $i => $row)
                @php
                  if ($level === 'college') {
                    // College → Programs
                    $href = route('registrar.student', [
                      'level'      => 'program',
                      'campus_id'  => $campusId,
                      'college_id' => $row->id,
                    ]);
                  } elseif ($level === 'program') {
                    // Program → Majors
                    $href = route('registrar.student', [
                      'level'      => 'major',
                      'campus_id'  => $campusId,
                      'program_id' => $row->id,
                    ]);
                  } else {
                    // Major → Student List
                    // Normal major: id has value
                    // "None" row: id = null → we send major_id = 0
                    $href = route('registrar.studentlist', [
                      'campus_id'  => $campusId,
                      'program_id' => $programId ?? null,
                      'major_id'   => $row->id ?? 0,
                    ]);
                  }
                @endphp
                <tr class="clickable-row" data-href="{{ $href }}" style="cursor:pointer;">
                  <td>{{ $i + 1 }}</td>
                  <td class="fw-semibold">{{ $row->name }}</td>
                  <td class="text-end">{{ number_format((int) $row->total) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- ===================== Add Student Modal ===================== --}}
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" style="margin-top: 40px;">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        {{-- IMPORTANT: this goes to the student upload page --}}
        <form id="addClassForm" method="GET" action="{{ route('registrar.studentupload') }}">
          @csrf

          {{-- FIXED CAMPUS (no dropdown) --}}
          <div class="mb-3">
            <label class="form-label">Campus</label>
            <div class="form-control bg-light">
              <strong>{{ $userCampusName ?? 'No campus assigned' }}</strong>
              @if($userCampusId)
                <input type="hidden" name="campus_id" value="{{ $userCampusId }}">
              @endif
              <small class="text-muted d-block mt-1">
                Campus is fixed based on your designation.
              </small>
            </div>
          </div>


          <div class="mb-3">
            <label for="collegeSelect" class="form-label">College</label>
            <select class="form-select" id="collegeSelect" name="college_id" required>
              <option value="" disabled selected>Choose College</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="programSelect" class="form-label">Program</label>
            <select class="form-select" id="programSelect" name="program_id" disabled>
              <option value="" disabled selected>Choose Program (optional)</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="majorSelect" class="form-label">Major</label>
            <select class="form-select" id="majorSelect" name="major_id" disabled>
              <option value="" selected>Choose Major (optional)</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="curriculumSelect" class="form-label">Curriculum</label>
            <select class="form-select" id="curriculumSelect" name="curriculum_id" required disabled>
              <option value="" disabled selected>Choose College first</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="academicYear" class="form-label">Academic Year</label>
            <input type="text" class="form-control" id="academicYear" name="academic_year" placeholder="e.g. 2025-2026" required>
          </div>

          <div class="mb-3">
            <label for="yearLevel" class="form-label">Year Level</label>
            <select class="form-select" id="yearLevel" name="year_level" required>
              <option value="" disabled selected>Select Year Level</option>
              <option value="FIRST YEAR">FIRST YEAR</option>
              <option value="SECOND YEAR">SECOND YEAR</option>
              <option value="THIRD YEAR">THIRD YEAR</option>
              <option value="FOURTH YEAR">FOURTH YEAR</option>
            </select>
          </div>

          <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Add</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- ===================== SCRIPTS ===================== --}}
<script>
/* ---------- Static datasets from controller ---------- */
const colleges    = @json($colleges ?? []);
const programs    = @json($programs ?? []);
const majors      = @json($majors ?? []);

// Get user's campus ID from PHP (fixed for registrar)
const userCampusId = @json($userCampusId);

/* ---------- DOM ---------- */
const campusSelect  = document.getElementById('campusSelect'); // will be null (no dropdown)
const collegeSelect = document.getElementById('collegeSelect');
const programSelect = document.getElementById('programSelect');
const majorSelect   = document.getElementById('majorSelect');
const curriculumSel = document.getElementById('curriculumSelect');

/* ---------- Helpers ---------- */
function setDisabled(el, v){
  if (!el) return;
  v ? el.setAttribute('disabled','disabled') : el.removeAttribute('disabled');
}
function setOptions(el, items, placeholder, {disabled=true, valueKey='value', textKey='text'}={}){
  if (!el) return;
  el.innerHTML = '';
  const ph = document.createElement('option');
  ph.value = '';
  ph.textContent = placeholder;
  ph.disabled = true; 
  ph.selected = true;
  el.appendChild(ph);
  (items||[]).forEach(it => {
    const o = document.createElement('option');
    o.value = it[valueKey];
    o.textContent = it[textKey];
    el.appendChild(o);
  });
  setDisabled(el, disabled);
}
function normMajorId() {
  const opt = majorSelect?.options[majorSelect.selectedIndex];
  if (!opt) return undefined;
  const label = (opt.text||'').trim().toLowerCase();
  if (label === 'none' || label === 'no major' || opt.value === '' || opt.value === '0') return null;
  return opt.value;
}

function initialState() {
  // Registrar: campus fixed via userCampusId
  if (userCampusId) {
    hydrateColleges(userCampusId);
  }
  setOptions(programSelect, [], 'Choose Program (optional)', {disabled:true});
  setOptions(majorSelect,   [], 'Choose Major (optional)', {disabled:true});
  setOptions(curriculumSel, [], 'Choose College first', {disabled:true});
}

/* ---------- Cascade population ---------- */
function hydrateColleges(campusId = null) {
  const effectiveCampusId = campusId || userCampusId;
  if (!effectiveCampusId) return;

  const list = colleges
    .filter(c => Number(c.Campus_id) === Number(effectiveCampusId))
    .map(c => ({ value: c.College_id, text: c.College_name }));

  setOptions(collegeSelect, list, 'Choose College', {disabled: list.length === 0});
  setOptions(programSelect, [], 'Choose Program (optional)', {disabled:true});
  setOptions(majorSelect,   [], 'Choose Major (optional)', {disabled:true});
  setOptions(curriculumSel, [], 'Choose College first', {disabled:true});
}

function hydratePrograms(){
  const campusId  = userCampusId;
  const collegeId = Number(collegeSelect.value);
  if (!campusId || !collegeId) {
    setOptions(programSelect, [], 'Choose Program (optional)', {disabled:true});
    return;
  }

  const list = programs
    .filter(p => Number(p.Campus_id) === campusId && Number(p.College_id) === collegeId)
    .map(p => ({ value: p.Program_id, text: p.Program_name }));
  setOptions(programSelect, list, 'Choose Program (optional)', {disabled:list.length===0});
  setOptions(majorSelect,   [], 'Choose Major (optional)', {disabled:true});
}

function hydrateMajors(){
  const campusId  = userCampusId;
  const collegeId = Number(collegeSelect.value);
  const programId = Number(programSelect.value || 0);
  if (!campusId || !collegeId || !programId) {
    setOptions(majorSelect, [], 'Choose Major (optional)', {disabled:true});
    return;
  }

  const list = majors
    .filter(m =>
      Number(m.Campus_id) === campusId &&
      Number(m.College_id) === collegeId &&
      Number(m.Program_id) === programId
    )
    .map(m => ({ value: m.Major_id, text: m.Major_name }));
  setOptions(majorSelect, list, 'Choose Major (optional)', {disabled:list.length===0});
}

/* ---------- Curriculum loader (AJAX) ---------- */
async function loadCurricula(){
  if (!collegeSelect.value){
    setOptions(curriculumSel, [], 'Choose College first', {disabled:true});
    return;
  }

  const campusId  = userCampusId;
  const collegeId = collegeSelect.value;
  const programId = programSelect.value || '';
  const majorId   = (majorSelect.options.length ? normMajorId() : undefined);

  setOptions(curriculumSel, [], 'Loading curricula...', {disabled:true});

  const url = new URL(@json(route('registrar.curriculum.options')), window.location.origin);
  url.searchParams.set('campus_id', campusId);
  url.searchParams.set('college_id', collegeId);
  if (programId !== '') url.searchParams.set('program_id', programId);
  if (majorId === null) url.searchParams.set('major_id', '');
  else if (majorId !== undefined) url.searchParams.set('major_id', majorId);

  try{
    const res  = await fetch(url.toString(), { headers:{'X-Requested-With':'XMLHttpRequest'} });
    const data = await res.json();
    if (!data.items || data.items.length === 0){
      setOptions(curriculumSel, [], 'No curriculum found for this selection', {disabled:false});
      return;
    }
    const items = data.items.map(x => ({ value:x.id, text:x.text }));
    setOptions(curriculumSel, items, 'Choose Curriculum', {disabled:false});
  }catch(e){
    console.error(e);
    setOptions(curriculumSel, [], 'Failed to load curricula', {disabled:false});
  }
}

/* ---------- Wiring ---------- */
if (collegeSelect) {
  collegeSelect.addEventListener('change', () => {
    hydratePrograms();
    hydrateMajors();
    loadCurricula();
  });
}

if (programSelect) {
  programSelect.addEventListener('change', () => {
    hydrateMajors();
    loadCurricula();
  });
}

if (majorSelect) {
  majorSelect.addEventListener('change', () => {
    loadCurricula();
  });
}

/* ---------- Modal hydration ---------- */
document.addEventListener('shown.bs.modal', e => {
  if (e.target && e.target.id === 'addUserModal') {
    if (userCampusId) {
      hydrateColleges(userCampusId);
    }
    if (collegeSelect.value){
      hydratePrograms();
      hydrateMajors();
      loadCurricula();
    }
  }
});

/* ---------- Init ---------- */
initialState();

/* ---------- Row click navigation ---------- */
document.querySelectorAll('.clickable-row').forEach(row => {
  row.addEventListener('click', () => {
    const href = row.getAttribute('data-href');
    if (href) window.location.href = href;
  });
});
</script>
@endsection
