@extends('admin.adminsidebar')

@section('content')
<div class="container py-4">
  <!-- Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Add Major</h3>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" title="Filter">
        <i class="bi bi-funnel-fill"></i>
      </button>
      <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#confirmAddModal">
        <i class="bi bi-plus-lg me-1"></i> Create Major
      </button>
    </div>
  </div>

  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Campus</th>
              <th>College</th>
              <th>Program</th>
              <th>Major</th>
              <th>Abbreviation</th>
              <th>Action</th>
            </tr>
          </thead>
            <tbody>
                @forelse($majors as $index => $major)
                  <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $major->Campus_name }}</td>
                    <td>{{ $major->College_abbr }}</td>
                    <td>{{ $major->Program_abbr }}</td>
                    <td>{{ $major->Major_name }}</td>
                    <td>{{ $major->Abbreviation }}</td>
                    <td>
                      <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary me-1"
                                data-bs-toggle="modal"
                                data-bs-target="#editMajorModal"
                                onclick="editMajor(
                                  {{ $major->Major_id }},
                                  '{{ $major->Major_name }}',
                                  '{{ $major->Abbreviation }}',
                                  {{ $major->Campus_id }},
                                  {{ $major->College_id }},
                                  {{ $major->Program_id }}
                                )">
                          <i class="bi bi-pencil-square"></i>
                        </button>

                        <button class="btn btn-sm btn-outline-danger"
                                onclick="confirmDeleteMajor({{ $major->Major_id }})">
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center text-muted py-2">No Found Major in this Table</td>
                  </tr>
                @endforelse
             </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ✅ Create Major Modal -->
  <div class="modal fade" id="confirmAddModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content rounded-4 shadow">
        <div class="modal-header text-black rounded-top-4">
          <h5 class="modal-title fw-bold">Create Major</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <form id="addMajorForm" method="POST" action="{{ route('admin.major.store') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-semibold">Campus</label>
              <select id="inputCampus" name="campus_id" class="form-select" required>
                <option value="" disabled selected>Select a campus</option>
                @foreach($campuses as $campus)
                  <option value="{{ $campus->Campus_id }}">{{ $campus->Campus_name }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">College</label>
              <select id="inputCollege" name="college_id" class="form-select" required>
                <option value="" disabled selected>Select a college</option>
                @foreach($colleges as $college)
                  <option value="{{ $college->College_id }}" data-campus="{{ $college->Campus_id }}">
                    {{ $college->College_name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Program</label>
              <select id="inputProgram" name="program_id" class="form-select" required>
                <option value="" disabled selected>Select a program</option>
                @foreach($programs as $program)
                  <option value="{{ $program->Program_id }}" data-college="{{ $program->College_id }}">
                    {{ $program->Program_name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Major Name</label>
              <input type="text" id="inputMajorName" name="major_name" class="form-control" required>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Abbreviation</label>
              <input type="text" id="inputAbbreviation" name="abbreviation" class="form-control" required>
            </div>
          </form>
        </div>
        <div class="modal-footer px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="showFinalAddModal()">Save Major</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ✏️ Edit Major Modal -->
<div class="modal fade" id="editMajorModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header text-black rounded-top-4">
        <h5 class="modal-title fw-bold">Edit Major</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="editMajorForm" method="POST" action="{{ route('admin.major.update', ['id' => '__ID__']) }}">
          @csrf
          @method('PUT')
          <input type="hidden" name="major_id" id="editMajorId">

          <div class="mb-3">
            <label class="form-label fw-semibold">Campus</label>
            <select id="editCampus" name="campus_id" class="form-select" required>
              <option value="" disabled selected>Select a campus</option>
              @foreach($campuses as $campus)
                <option value="{{ $campus->Campus_id }}">{{ $campus->Campus_name }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">College</label>
            <select id="editCollege" name="college_id" class="form-select" required>
              <option value="" disabled selected>Select a college</option>
              @foreach($colleges as $college)
                <option value="{{ $college->College_id }}" data-campus="{{ $college->Campus_id }}">
                  {{ $college->College_name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Program</label>
            <select id="editProgram" name="program_id" class="form-select" required>
              <option value="" disabled selected>Select a program</option>
              @foreach($programs as $program)
                <option value="{{ $program->Program_id }}" data-college="{{ $program->College_id }}">
                  {{ $program->Program_name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Major Name</label>
            <input type="text" id="editMajorName" name="major_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Abbreviation</label>
            <input type="text" id="editAbbreviation" name="abbreviation" class="form-control" required>
          </div>
        </form>
      </div>
      <div class="modal-footer px-4 pb-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <!-- Update Button -->
        <button type="button" class="btn btn-primary" onclick="submitUpdateMajor()">Update Major</button>
      </div>
    </div>
  </div>
</div>


  <!-- ⚠️ Confirm Add Modal -->
  <div class="modal fade" id="confirmAddModalFinal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
          <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
          <h5 class="fw-bold text-dark mb-4">Are you sure you want to add this major?</h5>
          <div class="d-flex justify-content-center gap-3">
            <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
            <button class="btn btn-primary px-4" onclick="submitAddProgram()">Yes</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ✅ Success Modal -->
  <div class="modal fade" id="successAddModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
      <div class="modal-content text-center border-0 rounded-4 shadow">
        <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
          <div class="mb-3 d-flex justify-content-center">
            <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
                   autoplay muted loop playsinline type="video/mp4"
                   style="width: 70px; height: 70px;"></video>
          </div>
          <h5 class="text-success fw-bold mb-1">Success!</h5>
          <p class="mb-4 text-muted">Record has been successfully added</p>
          <div>
            <button class="btn btn-primary px-4" data-bs-dismiss="modal" onclick="location.reload()">OK</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Success Update Modal -->
<div class="modal fade" id="successUpdateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
                 autoplay muted loop playsinline type="video/mp4"
                 style="width: 70px; height: 70px;"></video>
        </div>
        <h5 class="text-success fw-bold mb-1">Success!</h5>
        <p class="mb-4 text-muted">Record has been successfully updated</p>
        <div>
          <button class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256"
             style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this major?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteProgram()">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- ✅ Success Delete Modal -->
<div class="modal fade" id="deleteSuccessModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content text-center border-0 rounded-4 shadow">
      <div class="modal-body py-5 px-4 d-flex flex-column align-items-center">
        <div class="mb-3 d-flex justify-content-center">
          <video src="https://cdnl.iconscout.com/lottie/premium/preview-watermark/check-mark-animation-download-in-lottie-json-gif-static-svg-file-formats--tick-done-approved-verified-verify-and-cross-pack-sign-symbols-animations-4383659.mp4"
                 autoplay muted loop playsinline type="video/mp4"
                 style="width: 70px; height: 70px;"></video>
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

<!-- ✅ JS for dependent selects and modal flow -->
<script>
      //Edit script
      function editMajor(id, name, abbr, campusId, collegeId, programId) {
        document.getElementById('editMajorId').value = id;
        document.getElementById('editMajorName').value = name;
        document.getElementById('editAbbreviation').value = abbr;
        document.getElementById('editCampus').value = campusId;

        const collegeSelect = document.getElementById('editCollege');
        const programSelect = document.getElementById('editProgram');

        Array.from(collegeSelect.options).forEach(option => {
          if (!option.value) return;
          option.style.display = option.dataset.campus == campusId ? 'block' : 'none';
        });
        collegeSelect.value = collegeId;

        Array.from(programSelect.options).forEach(option => {
          if (!option.value) return;
          option.style.display = option.dataset.college == collegeId ? 'block' : 'none';
        });
        programSelect.value = programId;

        // ✅ Update the form's action URL with the correct ID
        const form = document.getElementById('editMajorForm');
        form.action = `/admin/major/update/${id}`;
      }
 
      //Update Script
      function submitUpdateMajor() {
        const form = document.getElementById('editMajorForm');
        const formData = new FormData(form);

        fetch(form.action, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
            'X-HTTP-Method-Override': 'PUT'
          },
          body: formData
        })
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(data => {
          const successModal = new bootstrap.Modal(document.getElementById('successUpdateModal'));
          successModal.show();
          setTimeout(() => {
            successModal.hide();
            location.reload();
          }, 2000);
        })
        .catch(err => {
          alert("Error updating major. Please try again.");
          console.error(err);
        });

        bootstrap.Modal.getInstance(document.getElementById('editMajorModal')).hide();
      }



  document.addEventListener("DOMContentLoaded", function () {
    const campusSelect = document.getElementById('inputCampus');
    const collegeSelect = document.getElementById('inputCollege');
    const programSelect = document.getElementById('inputProgram');

    campusSelect.addEventListener('change', function () {
      const selectedCampus = this.value;
      Array.from(collegeSelect.options).forEach(option => {
        if (!option.value) return;
        option.style.display = option.dataset.campus === selectedCampus ? 'block' : 'none';
      });
      collegeSelect.value = '';
      programSelect.value = '';
    });

    collegeSelect.addEventListener('change', function () {
      const selectedCollege = this.value;
      Array.from(programSelect.options).forEach(option => {
        if (!option.value) return;
        option.style.display = option.dataset.college === selectedCollege ? 'block' : 'none';
      });
      programSelect.value = '';
    });

    campusSelect.dispatchEvent(new Event('change'));
  });

  function showFinalAddModal() {
    const form = document.getElementById('addMajorForm');
    if (form.checkValidity()) {
      const confirmModal = new bootstrap.Modal(document.getElementById('confirmAddModalFinal'));
      confirmModal.show();
    } else {
      form.reportValidity();
    }
  }

  function submitAddProgram() {
    const form = document.getElementById('addMajorForm');
    const formData = new FormData(form);

    fetch(form.action, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value },
      body: formData
    })
    .then(res => res.ok ? res.json() : Promise.reject(res))
    .then(data => {
      const successModal = new bootstrap.Modal(document.getElementById('successAddModal'));
      successModal.show();
      setTimeout(() => {
        successModal.hide();
        location.reload();
      }, 2000);
    })
    .catch(err => {
      alert("Error saving major. Please try again.");
      console.error(err);
    });

    bootstrap.Modal.getInstance(document.getElementById('confirmAddModalFinal')).hide();
    bootstrap.Modal.getInstance(document.getElementById('confirmAddModal')).hide();
  }


  //Delete

  let majorIdToDelete = null;

  function confirmDeleteMajor(id) {
    majorIdToDelete = id;
    const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    modal.show();
  }

  function submitDeleteProgram() {
    if (!majorIdToDelete) return;

    fetch(`/admin/major/delete/${majorIdToDelete}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
      }
    })
    .then(res => res.ok ? res.json() : Promise.reject(res))
    .then(data => {
      const successModal = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
      successModal.show();
      setTimeout(() => {
        successModal.hide();
        location.reload();
      }, 2000);
    })
    .catch(err => {
      alert("Error deleting major. Please try again.");
      console.error(err);
    });

    // Hide the confirmation modal
    bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal')).hide();
  }
</script>

@endsection
