@extends('admin.adminsidebar')

@section('content')
<div class="container py-4">

  <!-- 🟪 Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold text-white">User Designation</h4>
      <small class="text-light">User Management > Designation</small>
    </div>
    <a href="{{ route('admin.usermanage') }}" class="btn btn-outline-light rounded-3">
      <i class="bi bi-arrow-left-circle me-1"></i> Back
    </a>
  </div>

  <!-- 👤 User Info -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body px-4 py-4">
      <h5 class="fw-bold mb-3">User Information</h5>
      <div class="row">
        <div class="col-md-2 mb-3">
          <label class="form-label">Title</label>
          <input type="text" class="form-control border-0 border-bottom" value="{{ $user->Title }}" readonly>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">First Name</label>
          <input type="text" class="form-control border-0 border-bottom" value="{{ $user->First_name }}" readonly>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Middle Name</label>
          <input type="text" class="form-control border-0 border-bottom" value="{{ $user->Middle_name }}" readonly>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Last Name</label>
          <input type="text" class="form-control border-0 border-bottom" value="{{ $user->Last_name }}" readonly>
        </div>
        <div class="col-md-12">
          <label class="form-label">Email</label>
          <input type="text" class="form-control border-0 border-bottom" value="{{ $user->Email }}" readonly>
        </div>
      </div>
    </div>
  </div>

  <!-- 🔁 Flush Cache Alert -->
  <div id="flushCacheAlert" class="alert alert-danger d-none d-flex align-items-center justify-content-between px-4" role="alert">
    <div>
      <i class="bi bi-exclamation-circle-fill me-2"></i>
      <strong>Flush Cache:</strong> Modifications have been made. For these changes to take place,
      <a href="javascript:void(0);" class="text-primary fw-bold" onclick="refreshDesignationTable()">clear the cache</a>.
    </div>
  </div>

  <!-- 📋 Designation Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body px-4 py-4">
      <h5 class="fw-bold mb-3">Designation Information</h5>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead id="designationTableHead" class="table-light">
            <tr>
              <th>#</th>
              <th>Designation</th>
              <th class="th-campus">Campus</th>
              <th class="th-college">College</th>
              <th class="th-program">Program</th>
              <th class="th-major">Major</th>
              <th>Action</th>
              <th>
                <!-- Icon to open Add Designation Modal -->
                <button class="btn p-0 bg-transparent shadow-none border-0 float-end"
                        title="Add Designation"
                        data-bs-toggle="modal"
                        data-bs-target="#addDesignationModal">
                  <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="#4a4aef" viewBox="0 0 24 24">
                    <path d="M3 6h12v2H3V6zm0 5h12v2H3v-2zm0 5h8v2H3v-2zm14 0v-2h-2v-2h2v-2h2v2h2v2h-2v2h-2z"/>
                  </svg>
                </button>
              </th>
            </tr>
          </thead>
          <tbody id="designationTableBody">
            @forelse($designations as $index => $designation)
              <tr data-access="{{ strtolower($designation->Access ?? '') }}">
                <td>{{ $index + 1 }}</td>
                <td>{{ $designation->Designation_name }}</td>
                <td class="td-campus">{{ $designation->Campus_name ?? '' }}</td>
                <td class="td-college">{{ $designation->College_abbreviation ?? '' }}</td>
                <td class="td-program">{{ $designation->Program_abbreviation ?? '' }}</td>
                <td class="td-major">{{ $designation->Major_name ?? '' }}</td>
                <td>
                  <button class="btn btn-outline-danger btn-sm" title="Delete"
                          onclick="confirmDeleteDesignation({{ $designation->UserDesignation_id }})">
                    <i class="bi bi-trash3"></i>
                  </button>
                </td>
                <td></td>
              </tr>
            @empty
              <tr class="empty-row">
                <td colspan="8" class="text-center text-muted">No designations found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- 🆕 Add Designation Modal -->
<div class="modal fade" id="addDesignationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg" style="margin-top: 40px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-bottom-0">
        <h5 class="modal-title fw-bold">Add Designation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="designationAddForm">
        @csrf
        <input type="hidden" name="User_id" value="{{ $user->User_id }}">
        <div class="modal-body px-4 pt-3 pb-0">

          <div class="mb-3">
            <label class="form-label">Designation</label>
            <select class="form-select w-100" id="formDesignationSelect" name="Designation_name" required>
              <option value="" selected disabled>Select Designation</option>
              @foreach($designationList as $item)
                <option
                  value="{{ $item->Designation_name }}"
                  data-access="{{ $item->Access }}"
                  data-id="{{ $item->Designation_id }}"
                >
                  {{ $item->Designation_name }}
                </option>
              @endforeach
            </select>
          </div>

          <div id="dependentDropdowns" class="d-none">
            <div class="mb-3" data-key="Campus">
              <label class="form-label">Campus</label>
              <select class="form-select w-100" name="Campus_name" id="campusSelect">
                <option value="" selected disabled>Select Campus</option>
                @foreach($campusList as $campus)
                  <option value="{{ $campus->Campus_name }}">{{ $campus->Campus_name }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3" data-key="College">
              <label class="form-label">College</label>
              <select class="form-select w-100" name="College_id" id="collegeSelect" disabled>
                <option value="" selected disabled>Select College</option>
              </select>
            </div>

            <div class="mb-3" data-key="Program">
              <label class="form-label">Program</label>
              <select class="form-select w-100" name="Program_id" id="programSelect" disabled>
                <option value="" selected disabled>Select Program</option>
              </select>
            </div>

            <div class="mb-3" data-key="Major">
              <label class="form-label">Major</label>
              <select class="form-select w-100" name="Major_id" id="majorSelect" disabled>
                <option value="" selected disabled>Select Major</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer border-0 px-4 pb-4">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary px-4">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ✅ Confirm Add -->
<div class="modal fade" id="confirmAddModalFinal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to add this User?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" id="confirmYesBtn" onclick="submitAddUser()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Success Add -->
<div class="modal fade" id="successAddModal" tabindex="-1">
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

<!-- 🗑️ Delete Confirmation -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body text-center py-5 px-4">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this record?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" id="confirmDeleteBtn">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Success Delete -->
<div class="modal fade" id="deleteSuccessModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
            autoplay muted loop playsinline type="video/mp4" style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Record has been successfully deleted</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal" onclick="location.reload()">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let confirmModal, confirmDeleteModal, deleteSuccessModal, deleteId;

document.addEventListener('DOMContentLoaded', function () {
  confirmModal       = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmAddModalFinal'));
  confirmDeleteModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmDeleteModal'));
  deleteSuccessModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteSuccessModal'));

  const form                   = document.getElementById('designationAddForm');
  const formDesignationSelect  = document.getElementById('formDesignationSelect');
  const dependentDropdowns     = document.getElementById('dependentDropdowns');
  const flushAlert             = document.getElementById('flushCacheAlert');
  const thead                  = document.getElementById('designationTableHead');

  const collegeSelect = document.getElementById('collegeSelect');
  const programSelect = document.getElementById('programSelect');
  const campusSelect  = document.querySelector('[name="Campus_name"]');
  const majorSelect   = document.getElementById('majorSelect');

  // === Access → fields/columns shown (for the Add modal preview) ==============================
  const ACCESS_FIELDS = {
    'Campus':  ['Campus'],
    'College': ['Campus','College'],
    'Program': ['Campus','College','Program'],
    'Major':   ['Campus','College','Program','Major']
  };

  function applyAccessToForm(access) {
    const keys   = ACCESS_FIELDS[access] || [];
    const blocks = document.querySelectorAll('#dependentDropdowns [data-key]');

    blocks.forEach(block => {
      const key   = block.getAttribute('data-key');
      const show  = keys.includes(key);
      block.classList.toggle('d-none', !show);

      const select = block.querySelector('select');
      if (select) {
        if (show) { select.setAttribute('required','required'); }
        else {
          select.removeAttribute('required');
          if (select.tagName === 'SELECT') { select.selectedIndex = 0; }
          else { select.value = ''; }
        }
      }
    });

    dependentDropdowns.classList.toggle('d-none', keys.length === 0);
  }

  // ===== Display logic driven by EXISTING rows =================================================
  function toggleColumn(key, show) {
    const th = document.querySelector(`#designationTableHead th.th-${key}`);
    if (th) th.style.display = show ? '' : 'none';
    document.querySelectorAll(`td.td-${key}`).forEach(td => td.style.display = show ? '' : 'none');
  }

  function applyColumnsFromExistingRows() {
    const rows = Array.from(document.querySelectorAll('#designationTableBody tr[data-access]'));

    // Show Program if any row is Program or Major; show Major only if any row is Major.
    const needsProgram = rows.some(r => ['program','major'].includes(r.dataset.access || ''));
    const needsMajor   = rows.some(r => (r.dataset.access || '') === 'major');

    toggleColumn('program', needsProgram);
    toggleColumn('major', needsMajor);

    syncEmptyRowColspan();
  }

  function syncEmptyRowColspan() {
    const visibleThCount = Array.from(document.querySelectorAll('#designationTableHead th'))
      .filter(th => th.style.display !== 'none').length;
    const emptyTd = document.querySelector('#designationTableBody .empty-row td');
    if (emptyTd) emptyTd.colSpan = visibleThCount;
  }

  // When designation changes in the Add modal
  if (formDesignationSelect) {
    formDesignationSelect.addEventListener('change', function () {
      const access = this.selectedOptions[0]?.getAttribute('data-access') || '';
      applyAccessToForm(access);

      // reset downstream selects
      collegeSelect.innerHTML = '<option disabled selected>Select College</option>';
      collegeSelect.disabled = true;
      programSelect.innerHTML = '<option disabled selected>Select Program</option>';
      programSelect.disabled = true;
      majorSelect.innerHTML   = '<option disabled selected>Select Major</option>';
      majorSelect.disabled = true;
    });
  }

  // Initial state
  applyAccessToForm('');
  applyColumnsFromExistingRows();

  // === Dependent loaders ======================================================================
  if (campusSelect && collegeSelect) {
    campusSelect.addEventListener('change', function () {
      const campusName = this.value;

      collegeSelect.innerHTML = '<option disabled selected>Loading colleges...</option>';
      collegeSelect.disabled = true;

      fetch(`/admin/colleges/by-campus?campus_name=${encodeURIComponent(campusName)}`)
        .then(res => { if (!res.ok) throw new Error('Failed to load'); return res.json(); })
        .then(colleges => {
          if (!Array.isArray(colleges) || colleges.length === 0) {
            collegeSelect.innerHTML = '<option disabled selected>No colleges found for this campus</option>';
          } else {
            collegeSelect.innerHTML = '<option disabled selected>Select College</option>';
            colleges.forEach(college => {
              collegeSelect.innerHTML += `<option value="${college.College_id}">${college.Abbreviation}</option>`;
            });
          }
          collegeSelect.disabled = false;

          programSelect.innerHTML = '<option disabled selected>Select Program</option>';
          programSelect.disabled = true;
          majorSelect.innerHTML   = '<option disabled selected>Select Major</option>';
          majorSelect.disabled = true;
        })
        .catch(() => {
          collegeSelect.innerHTML = '<option disabled selected>Error loading colleges</option>';
          collegeSelect.disabled = true;
        });
    });
  }

  if (collegeSelect && programSelect) {
    collegeSelect.addEventListener('change', function () {
      const collegeId  = this.value;
      const campusName = campusSelect.value;

      programSelect.innerHTML = '<option selected disabled>Loading programs...</option>';
      programSelect.disabled = true;

      fetch(`/admin/programs/by-college?college_id=${collegeId}&campus_name=${encodeURIComponent(campusName)}`)
        .then(res => res.json())
        .then(programs => {
          if (!Array.isArray(programs) || programs.length === 0) {
            programSelect.innerHTML = '<option disabled selected>No program created in this college</option>';
          } else {
            programSelect.innerHTML = '<option disabled selected>Select Program</option>';
            programs.forEach(program => {
              programSelect.innerHTML += `<option value="${program.Program_id}">${program.Program_name}</option>`;
            });
          }
          programSelect.disabled = false;

          majorSelect.innerHTML = '<option disabled selected>Select Major</option>';
          majorSelect.disabled = true;
        })
        .catch(() => {
          programSelect.innerHTML = '<option disabled selected>Error loading programs</option>';
          programSelect.disabled = true;
        });
    });
  }

  if (programSelect && majorSelect) {
    programSelect.addEventListener('change', function () {
      const programId  = this.value;
      const campusName = campusSelect.value;
      const collegeId  = collegeSelect.value;

      majorSelect.innerHTML = '<option selected disabled>Loading majors...</option>';
      majorSelect.disabled = true;

      fetch(`/admin/majors/by-program?program_id=${programId}&college_id=${collegeId}&campus_name=${encodeURIComponent(campusName)}`)
        .then(res => res.json())
        .then(majors => {
          if (!Array.isArray(majors) || majors.length === 0) {
            majorSelect.innerHTML = '<option disabled selected>No majors available</option>';
          } else {
            majorSelect.innerHTML = '<option disabled selected>Select Major</option>';
            majors.forEach(major => {
              majorSelect.innerHTML += `<option value="${major.Major_id}">${major.Major_name}</option>`;
            });
          }
          majorSelect.disabled = false;
        })
        .catch(() => {
          majorSelect.innerHTML = '<option disabled selected>Error loading majors</option>';
          majorSelect.disabled = true;
        });
    });
  }

  document.getElementById('addDesignationModal').addEventListener('hidden.bs.modal', function () {
    dependentDropdowns.classList.add('d-none');
    form.reset();
    formDesignationSelect.value = '';
    majorSelect.innerHTML = '<option disabled selected>Select Major</option>';
    majorSelect.disabled = true;
  });

  window.showFlushCacheAlert = function () {
    if (flushAlert) flushAlert.classList.remove('d-none');
  };

  window.refreshDesignationTable = function () {
    // rebuild header to full set, then re-apply current visibility
    thead.innerHTML = `
      <tr>
        <th>#</th>
        <th>Designation</th>
        <th class="th-campus">Campus</th>
        <th class="th-college">College</th>
        <th class="th-program">Program</th>
        <th class="th-major">Major</th>
        <th>Action</th>
        <th>
          <button class="btn p-0 bg-transparent shadow-none border-0 float-end" title="Add Designation"
                  data-bs-toggle="modal" data-bs-target="#addDesignationModal">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="#4a4aef" viewBox="0 0 24 24">
              <path d="M3 6h12v2H3V6zm0 5h12v2H3v-2zm0 5h8v2H3v-2zm14 0v-2h-2v-2h2v-2h2v2h2v2h-2v2h-2z"/>
            </svg>
          </button>
        </th>
      </tr>
    `;
    flushAlert.classList.add('d-none');

    const flushSuccess = document.createElement('div');
    flushSuccess.className = 'alert alert-success d-flex align-items-center justify-content-between px-4 mt-3';
    flushSuccess.innerHTML = `
      <div>
        <i class="bi bi-check-circle-fill me-2 text-success"></i>
        <strong class="text-success">Cache Flushed Successfully</strong>
      </div>
    `;
    flushAlert.parentNode.insertBefore(flushSuccess, flushAlert.nextSibling);

    // show all TDs then hide by rule
    document.querySelectorAll('td.td-campus, td.td-college, td.td-program, td.td-major')
      .forEach(td => td.style.display = '');

    applyColumnsFromExistingRows();
  };

  document.getElementById('designationAddForm').addEventListener('submit', function (e) {
    e.preventDefault();
    confirmModal.show();
  });

  window.confirmDeleteDesignation = function (id) {
    deleteId = id;
    confirmDeleteModal.show();
  };

  document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    if (!deleteId) return;
    fetch(`/admin/userdesignation/delete/${deleteId}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        confirmDeleteModal.hide();
        deleteSuccessModal.show();
        showFlushCacheAlert();
        setTimeout(() => {
          deleteSuccessModal.hide();
          location.reload();
        }, 2000);
      }
    });
  });
});

let isSubmitting = false;
function submitAddUser() {
  if (isSubmitting) return;
  isSubmitting = true;

  const yesBtn = document.getElementById('confirmYesBtn');
  if (yesBtn) yesBtn.disabled = true;

  const form         = document.getElementById('designationAddForm');
  const formData     = new FormData(form);
  const confirmModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmAddModalFinal'));
  const addModal     = bootstrap.Modal.getOrCreateInstance(document.getElementById('addDesignationModal'));
  const successModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('successAddModal'));

  fetch(`{{ route('admin.userdesignation.store') }}`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: formData
  })
  .then(res => { if (!res.ok) throw res; return res.json(); })
  .then(data => {
    if (data.success) {
      confirmModal.hide();
      addModal.hide();
      successModal.show();
      showFlushCacheAlert();
      setTimeout(() => { successModal.hide(); location.reload(); }, 2000);
    } else {
      if (window.Swal?.fire) Swal.fire({ icon:'error', title:'Add failed', text: data.message || 'Unknown error' });
      else console.error('[Add failed]', data.message || 'Unknown error');
    }
  })
  .catch(async err => {
    try {
      const text = await err.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(text, 'text/html');
      const bodyText = doc.querySelector('body')?.innerText?.trim();
      const cleanMessage = bodyText?.slice(0, 500) || "An unknown error occurred.";
      if (window.Swal?.fire) Swal.fire({ icon:'error', title:'Server Error', text: cleanMessage });
      else console.error('[Server Error]', cleanMessage);
    } catch (e) {
      if (window.Swal?.fire) Swal.fire({ icon:'error', title:'Unexpected Error', text:'Something went wrong while processing the request.' });
      else console.error('[Unexpected Error] Something went wrong while processing the request.');
    }
  })
  .finally(() => {
    isSubmitting = false;
    if (yesBtn) yesBtn.disabled = false;
  });
}
</script>
@endsection
