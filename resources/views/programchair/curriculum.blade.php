@extends('programchair.programchairsidebar')

@php
  // never crash if a controller forgets to pass these
  $curriculums = isset($curriculums) ? collect($curriculums) : collect();
  $campuses    = $campuses  ?? [];
  $colleges    = $colleges  ?? [];
  $programs    = $programs  ?? [];
  $majors      = $majors    ?? [];
@endphp


@section('content')
<div class="container py-4">
  <!-- 🔷 Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Curriculum</h3>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary text-white" title="Filter">
        <i class="bi bi-funnel-fill tetx-white"></i>
      </button>
      <button class="btn btn-outline-primary px-4 text-white" data-bs-toggle="modal" data-bs-target="#addCurriculumModal">
        <i class="bi bi-plus-lg me-1 text-white"></i> Add Curriculum
      </button>
    </div>
  </div>

    {{-- =======================
       CURRICULUM LIST
  ======================== --}}
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
                $originalName  = $filenameParts[1] ?? $curriculum->Curriculum_name;
              @endphp
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <img src="{{ asset('img/PDF_file_icon.png') }}" alt="PDF" width="40" class="me-3">
                    <div>
                      <h6 class="fw-bold mb-1">{{ $originalName }}</h6>
                      <a href="{{ route('curriculum.view', $curriculum->curriculum_id) }}" class="text-primary small text-decoration-none">
                        <i class="bi bi-eye me-1"></i> View Details
                      </a>
                    </div>
                  </div>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-danger" onclick="confirmDeleteCurriculum({{ $curriculum->curriculum_id }})">
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

<!-- ✅ Modal for Adding Curriculum -->
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
          <!-- 🏫 Campus -->
          <div class="mb-3">
            <label class="form-label">Campus Name</label>
            <select class="form-select" name="campus_id" id="campusSelect" required>
              <option value="" disabled selected>Select Campus</option>
              @foreach($campuses as $campus)
                <option value="{{ $campus->Campus_id }}">{{ $campus->Campus_name }}</option>
              @endforeach
            </select>
          </div>

          <!-- 🎓 College -->
          <div class="mb-3">
            <label class="form-label">College Name</label>
              <select class="form-select" name="college_id" id="collegeSelect" required disabled>
                <option value="" disabled selected>Select College</option>
              </select>
          </div>

          <!-- 🧑‍💻 Program -->
          <div class="mb-3">
            <label class="form-label">Program Name</label>
            <select class="form-select" name="program_id" id="programSelect" required disabled>
              <option value="" disabled selected>Select Program</option>
            </select>
          </div>

          <!-- 📚 Major -->
          <div class="mb-3">
            <label class="form-label">Major</label>
            <select class="form-select" name="major_id" id="majorSelect" required disabled>
              <option value="" disabled selected>Select Major</option>
            </select>
          </div>

          <!-- 📆 Academic Year -->
          <div class="mb-3">
            <label class="form-label">Academic Year</label>
            <input type="text" class="form-control" name="academic_year" placeholder="e.g. 2024-2025" required>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary px-4" type="button" onclick="confirmCurriculumAdd()">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- 🔁 Confirmation Modal -->
<div class="modal fade" id="confirmAddModalFinal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to add this Curriculum?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-primary px-4" onclick="submitAddCurriculum()">Yes</button>
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

<!-- ✅ Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete this curriculum?</h5>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger px-4" onclick="submitDeleteCurriculum()">Yes</button>
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
          <button class="btn btn-primary px-4" data-bs-dismiss="modal" onclick="window.location.reload()">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    let deleteCurriculumId = null;

          function filterMajors() {
            const campusId = $('#campusSelect').val();
            const collegeId = $('#collegeSelect').val();
            const programId = $('#programSelect').val();

            $('#majorSelect option').each(function () {
              const matchCampus = $(this).data('campus') == campusId;
              const matchCollege = $(this).data('college') == collegeId;
              const matchProgram = $(this).data('program') == programId;

              if ($(this).val() === "") {
                $(this).show();
              } else if (matchCampus && matchCollege && matchProgram) {
                $(this).show();
              } else {
                $(this).hide();
              }
            });

            $('#majorSelect').val(''); // Reset selection
          }

          $('#campusSelect, #collegeSelect, #programSelect').on('change', filterMajors);






      function confirmDeleteCurriculum(id) {
        deleteCurriculumId = id;
        new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
      }

      function submitDeleteCurriculum() {
        if (!deleteCurriculumId) return;

        fetch(`{{ route('curriculum.destroy', ':id') }}`.replace(':id', deleteCurriculumId), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
      })
        .then(res => res.json())
        .then(data => {
          bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal')).hide();
          if (data.success) {
            const successModal = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
            successModal.show();
            setTimeout(() => {
              location.reload();
            }, 2000);
          } else {
            alert(data.message || 'Delete failed.');
          }
        })
        .catch(err => {
          alert("Error: " + err.message);
          console.error(err);
        });
      }

      document.getElementById('addCurriculumModal').addEventListener('shown.bs.modal', function () {
    document.getElementById('campusSelect').dispatchEvent(new Event('change'));
  });


    const colleges = @json($colleges);
    const programs = @json($programs);
    const majors = @json($majors);

    const campusSelect = document.getElementById("campusSelect");
    const collegeSelect = document.getElementById("collegeSelect");
    const programSelect = document.getElementById("programSelect");
    const majorSelect = document.getElementById("majorSelect");

    // 🏫 Campus → 🎓 College
    campusSelect.addEventListener("change", function () {
      const campusId = parseInt(this.value);
      const filteredColleges = colleges.filter(c => parseInt(c.Campus_id) === campusId);
      fillDropdown(collegeSelect, filteredColleges, 'College_id', 'College_name');
      reset(programSelect);
      reset(majorSelect);
    });

    // 🎓 College → 🧑‍💻 Program
    collegeSelect.addEventListener("change", function () {
      const campusId = parseInt(campusSelect.value);
      const collegeId = parseInt(this.value);
      const filteredPrograms = programs.filter(p => 
        parseInt(p.Campus_id) === campusId && parseInt(p.College_id) === collegeId
      );
      fillDropdown(programSelect, filteredPrograms, 'Program_id', 'Program_name');
      reset(majorSelect);
    });

    // 🧑‍💻 Program → 📚 Major
    programSelect.addEventListener("change", function () {
      const campusId = parseInt(campusSelect.value);
      const collegeId = parseInt(collegeSelect.value);
      const programId = parseInt(this.value);
      const filteredMajors = majors.filter(m => 
        parseInt(m.Campus_id) === campusId &&
        parseInt(m.College_id) === collegeId &&
        parseInt(m.Program_id) === programId
      );
      fillDropdown(majorSelect, filteredMajors, 'Major_id', 'Major_name');
    });

    // Utilities
    function fillDropdown(select, items, valueKey, textKey) {
      select.innerHTML = `<option value="" disabled selected>Select</option>`;
      items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item[valueKey];
        opt.textContent = item[textKey];
        select.appendChild(opt);
      });
      select.disabled = false;
    }

    function reset(select) {
      select.innerHTML = `<option value="" disabled selected>Select</option>`;
      select.disabled = true;
    }

  
  function confirmCurriculumAdd() {
    new bootstrap.Modal(document.getElementById('confirmAddModalFinal')).show();
  }

  function submitAddCurriculum() {
  const form = document.getElementById('curriculumForm');
  const formData = new FormData(form);

  fetch("{{ route('curriculum.insert') }}", {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
    },
    body: formData
  })
  .then(async response => {
    const contentType = response.headers.get('content-type');
    
    if (!response.ok || !contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      throw new Error('Server returned HTML:\n' + text.substring(0, 100));
    }

    return response.json();
  })

  .then(data => {
    if (data.success) {
      const modal = new bootstrap.Modal(document.getElementById('successAddModal'));
      modal.show();
      setTimeout(() => {
        modal.hide();
        window.location.href = data.redirect;
      }, 2000);
    } else {
      alert(data.message || 'Insert failed');
    }
  })
  .catch(error => {
    console.error('Fetch error:', error.message);
    alert('Error: ' + error.message);
  });
}

</script>
@endsection
