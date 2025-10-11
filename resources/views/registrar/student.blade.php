@extends('registrar.registrarsidebar')

@section('content')
<div class="container py-4">
  <!-- 🔷 Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Students</h3>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" title="Filter">
        <i class="bi bi-funnel-fill"></i>
      </button>
      <button class="btn btn-outline-primary px-4 text-white" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-plus-lg me-1 text-white"></i> Add Student
      </button>
    </div>
  </div>

  <!-- 🟦 Show Table if Students Exist -->
  @if (count($students) > 0)
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>College</th>
              <th>Program</th>
              <th>No. of Students</th>
            </tr>
          </thead>
          <tbody>
            @php
              $grouped = $students->groupBy(function($item) {
                $college = optional(optional(optional($item->curriculum)->curriculumAy)->college)->College_name ?? '—';
                $program = optional(optional(optional($item->curriculum)->curriculumAy)->program)->Program_name ?? '—';
                return $college . '|' . $program;
              });
            @endphp

            @foreach ($grouped as $key => $group)
              @php [$college, $program] = explode('|', $key); @endphp
              <tr class="clickable-row" style="cursor:pointer;"
                  data-href="{{ route('registrar.studentlist', ['curriculum_id' => $group->first()->curriculum_id]) }}">
                <td>{{ $loop->iteration }}</td>
                <td>{{ $college }}</td>
                <td>{{ $program }}</td>
                <td>{{ $group->count() }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endif

  <!-- 🟥 Empty State if No Students -->
  @if (count($students) === 0)
  <div class="card border-0 shadow-sm">
    <div class="card-body py-5">
      <div class="d-flex flex-column justify-content-center align-items-center text-center" style="min-height: 68vh;">
        <img src="{{ asset('img/box2.png') }}" alt="No students" style="width: 300px; height: 200px;">
        <h5 class="fw-bold text-dark mb-2">No students added</h5>
        <p class="text-muted mb-4">Start adding students</p>
        <div class="d-flex gap-3">
          <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg me-1"></i> Add Class
          </button>
        </div>
      </div>
    </div>
  </div>
  @endif
</div>

<!-- 🟨 Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" style="margin-top: 40px;">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Class</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="addClassForm" onsubmit="handleStudentUpload(event)">
          @csrf

          <!-- Campus -->
          <div class="mb-3">
            <label for="campusSelect" class="form-label">Campus</label>
            <select class="form-select" id="campusSelect" name="campus_id">
              <option value="" disabled selected>Choose Campus</option>
              @foreach ($campuses as $campus)
                <option value="{{ $campus->Campus_id }}">{{ $campus->Campus_name }}</option>
              @endforeach
            </select>
          </div>

          <!-- College -->
          <div class="mb-3">
            <label for="collegeSelect" class="form-label">College</label>
            <select class="form-select" id="collegeSelect" name="college_id" disabled>
              <option disabled selected>Choose College</option>
            </select>
          </div>

          <!-- Program -->
          <div class="mb-3">
            <label for="programSelect" class="form-label">Program</label>
            <select class="form-select" id="programSelect" name="program_id" disabled>
              <option disabled selected>Choose Program</option>
            </select>
          </div>

          <!-- Major -->
          <div class="mb-3">
            <label for="majorSelect" class="form-label">Major</label>
            <select class="form-select" id="majorSelect" name="major_id" disabled>
              <option disabled selected>Choose Major</option>
            </select>
          </div>

          <!-- Curriculum -->
          <div class="mb-3">
            <label for="curriculumSelect" class="form-label">Curriculum</label>
            <select class="form-select" id="curriculumSelect" name="curriculum_id" disabled>
              <option disabled selected>Choose Curriculum</option>
            </select>
          </div>

          <!-- Academic Year -->
          <div class="mb-3">
            <label for="academicYear" class="form-label">Academic Year</label>
            <input type="text" class="form-control" id="academicYear" readonly>
          </div>

          <!-- Year Level -->
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- 🧠 Dynamic Dropdown Script -->
<script>
function handleStudentUpload(event) {
  event.preventDefault();
  const curriculumId = document.getElementById('curriculumSelect').value;
  const yearLevel = document.getElementById('yearLevel').value;
  if (!curriculumId || !yearLevel) {
    alert('Please select both Curriculum and Year Level.');
    return;
  }
  window.location.href = `/registrar/studentupload?curriculum_id=${encodeURIComponent(curriculumId)}&year_level=${encodeURIComponent(yearLevel)}`;
}

// Data from PHP (JSON-safe — controller must NOT include File_data)
const colleges    = @json($colleges);
const programs    = @json($programs);
const majors      = @json($majors);
const curriculums = @json($curriculums);

// Helpers
function resetSelect(id, defaultText) {
  const el = document.getElementById(id);
  el.innerHTML = `<option disabled selected>${defaultText}</option>`;
  setDisabled(el, true);
}
function setDisabled(el, isDisabled) {
  el.disabled = !!isDisabled;
  if (isDisabled) el.setAttribute('disabled', 'disabled');
  else el.removeAttribute('disabled');
}
function setAY(value) {
  document.getElementById('academicYear').value = value || '';
}

/**
 * Populate Curriculum dropdown.
 * Prefers exact Major match; if none exists, falls back to Campus+College+Program.
 */
function populateCurriculums(campusId, collegeId, programId, majorId /* can be null */) {
  const sel = document.getElementById('curriculumSelect');
  resetSelect('curriculumSelect', 'Choose Curriculum');

  // Base set (no major filter)
  const base = curriculums.filter(c =>
    Number(c.Campus_id)  === Number(campusId)  &&
    Number(c.College_id) === Number(collegeId) &&
    Number(c.Program_id) === Number(programId)
  );

  // Prefer exact-major list if major was chosen and matches exist
  let list = base;
  if (majorId !== null && majorId !== undefined && String(majorId) !== '') {
    const byMajor = base.filter(c => Number(c.Major_id ?? 0) === Number(majorId));
    if (byMajor.length > 0) list = byMajor;
  }

  list.forEach(c => {
    sel.innerHTML += `<option value="${c.curriculum_id}" data-ay="${c.Academic_year}">${c.Curriculum_name}</option>`;
  });

  // Enable if there are real options
  setDisabled(sel, sel.options.length <= 1);

  // Optionally preview AY for first available curriculum
  setAY(!sel.disabled && sel.options[1] ? sel.options[1].getAttribute('data-ay') : '');
}

// Cascade: Campus -> College
document.getElementById('campusSelect').addEventListener('change', function () {
  const campusId = this.value;
  resetSelect('collegeSelect', 'Choose College');
  resetSelect('programSelect', 'Choose Program');
  resetSelect('majorSelect', 'Choose Major');
  resetSelect('curriculumSelect', 'Choose Curriculum');
  setAY('');

  const collegeSelect = document.getElementById('collegeSelect');
  colleges
    .filter(c => Number(c.Campus_id) === Number(campusId))
    .forEach(college => {
      collegeSelect.innerHTML += `<option value="${college.College_id}">${college.College_name}</option>`;
    });
  setDisabled(collegeSelect, collegeSelect.options.length <= 1);
});

// Cascade: College -> Program
document.getElementById('collegeSelect').addEventListener('change', function () {
  const campusId  = document.getElementById('campusSelect').value;
  const collegeId = this.value;

  resetSelect('programSelect', 'Choose Program');
  resetSelect('majorSelect', 'Choose Major');
  resetSelect('curriculumSelect', 'Choose Curriculum');
  setAY('');

  const programSelect = document.getElementById('programSelect');
  programs
    .filter(p => Number(p.Campus_id) === Number(campusId) && Number(p.College_id) === Number(collegeId))
    .forEach(program => {
      programSelect.innerHTML += `<option value="${program.Program_id}">${program.Program_name}</option>`;
    });
  setDisabled(programSelect, programSelect.options.length <= 1);
});

// Cascade: Program -> Major (with fallback to Curriculum if no majors)
document.getElementById('programSelect').addEventListener('change', function () {
  const campusId  = document.getElementById('campusSelect').value;
  const collegeId = document.getElementById('collegeSelect').value;
  const programId = this.value;

  resetSelect('majorSelect', 'Choose Major');
  resetSelect('curriculumSelect', 'Choose Curriculum');
  setAY('');

  const majorSelect = document.getElementById('majorSelect');
  const majorOptions = majors.filter(m =>
    Number(m.Campus_id)  === Number(campusId)  &&
    Number(m.College_id) === Number(collegeId) &&
    Number(m.Program_id) === Number(programId)
  );

  if (majorOptions.length > 0) {
    majorOptions.forEach(m => {
      majorSelect.innerHTML += `<option value="${m.Major_id}">${m.Major_name}</option>`;
    });
    setDisabled(majorSelect, false);
  } else {
    // No majors → populate curriculums immediately (ignore major)
    populateCurriculums(campusId, collegeId, programId, null);
  }
});

// Cascade: Major -> Curriculum
document.getElementById('majorSelect').addEventListener('change', function () {
  const campusId  = document.getElementById('campusSelect').value;
  const collegeId = document.getElementById('collegeSelect').value;
  const programId = document.getElementById('programSelect').value;
  const majorId   = this.value;
  populateCurriculums(campusId, collegeId, programId, majorId);
});

// Show AY when a curriculum is chosen
document.getElementById('curriculumSelect').addEventListener('change', function () {
  const selected = this.options[this.selectedIndex];
  setAY(selected?.getAttribute('data-ay') || '');
});

// Make grouped rows clickable
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.clickable-row').forEach(row => {
    row.addEventListener('click', () => {
      const href = row.getAttribute('data-href');
      if (href) window.location.href = href;
    });
  });
});
</script>

@endsection
