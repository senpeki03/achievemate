@extends('registrar.layout')

@section('content')
<style>
    label {
        font-weight: bold;
        font-size: 0.9rem;
    }

    .form-section:first-child {
        margin-top: 150px;
    }

    .form-section:nth-child(2) {
        margin-top: -130px;
    }

    .form-section:nth-child(3) {
        margin-top: -140px;
    }

    .form-section:nth-child(4) {
        margin-top: -130px;
    }

    .p-form-section{
        margin-top: 500px;
    }

    select.form-select:invalid,
    select.form-select option[disabled] {
        color: #6c757d !important;
    }

    select.form-select {
        background-color: #ffffff !important;
        color: #212529;
    }

    select.form-select:disabled {
        background-color: #ffffff !important;
        color: #6c757d !important;
    }

    .full-height-card {
        min-height: calc(100vh - 130px);
        display: flex;
        flex-direction: column;
    }

    .form-body {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .form-section {
        margin-bottom: 0.8rem;
    }

    .form-footer {
        display: flex;
        justify-content: flex-end;
    }

    .card-body {
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }

    .btn-blue {
        background-color: #004aad;
        color: white;
        border: none;
    }

    .btn-blue:hover {
        background-color: #003a89;
    }
</style>

<div class="container mt-3">
    <div class="card full-height-card">
        <div class="card-body d-flex flex-column py-1">
            <form action="{{ route('students.store') }}" method="POST" class="d-flex flex-column flex-grow-1">
                @csrf
                <div class="form-body">
                    <div class="form-section">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <h5 class="mb-3 fw-semibold" style="margin-left: 3px; margin-top: -100px; font-size: 30px;">
                                    Adding Student
                                </h5>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-4">
                                <label class="form-label">First name</label>
                                <input type="text" class="form-control" name="firstname" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle name</label>
                                <input type="text" class="form-control" name="middlename">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last name</label>
                                <input type="text" class="form-control" name="lastname" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="row mb-2">
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">SRCODE</label>
                                <input type="text" class="form-control" name="srcode" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contact</label>
                                <input type="text" class="form-control" name="contact" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <label class="form-label">Year</label>
                                <select class="form-select" name="year" id="year" required>
                                    <option value="" disabled selected>Select Year</option>
                                    <option>First Year</option>
                                    <option>Second Year</option>
                                    <option>Third Year</option>
                                    <option>Fourth Year</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department" id="department" required>
                                    <option value="" disabled selected>Select Department</option>
                                    <option>CICS</option>
                                    <option>CTE</option>
                                    <option>CONAHS</option>
                                    <option>CCJE</option>
                                    <option>CABEIHM</option>
                                    <option>CAS</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <label class="form-label">Program</label>
                                <select class="form-select" name="program" id="program" required>
                                    <option value="" disabled selected>Select Program</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Track</label>
                                <select class="form-select" name="track" id="track" disabled>
                                    <option value="" disabled selected>Select Track</option>
                                    <option>BA (Business Analytics)</option>
                                    <option>NT (Network Technology)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-footer mt-1">
                        <button type="submit" class="btn btn-blue">Save Student</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODALS -->
<!-- Confirmation Modal -->
<div class="modal fade" id="confirmImportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <h5 class="fw-bold mb-3 text-dark">Are you sure you want to add this student?</h5> 
      <div class="d-flex justify-content-center gap-3">
        <button class="btn btn-warning px-4" data-bs-dismiss="modal">NO</button>
        <button class="btn btn-primary px-4" onclick="handleImport()">YES</button>
      </div>
    </div>
  </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-5 rounded-4">
      <h6 class="mb-3 text-dark">Sending Email... Please wait</h6>
      <div class="progress-container">
        <div class="progress-track">
          <div id="progressBar" class="progress-fill"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Duplicate Email Modal -->
<div class="modal fade" id="duplicateEmailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content text-center p-4">
      <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
      <h6 class="mb-0">This email is already taken!</h6>
    </div>
  </div>
</div>

<!-- Success Modal with centered animation -->
<div class="modal fade" id="successImportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content text-center p-4">
      <div class="d-flex justify-content-center">
        <svg width="72" height="72" viewBox="0 0 72 72">
          <circle class="checkmark-circle" cx="36" cy="36" r="30" fill="none"/>
          <path class="checkmark-check" fill="none" d="M20 37 L32 48 L52 25"/>
        </svg>
      </div>
      <h6 class="mt-3 mb-0">Successfully Added</h6> 
    </div>
  </div>
</div>

<style>
.checkmark-circle {
  stroke: #28a745;
  stroke-width: 4;
  stroke-dasharray: 188.5;
  stroke-dashoffset: 188.5;
  animation: draw-circle 0.5s ease-out forwards;
}
.checkmark-check {
  stroke: #28a745;
  stroke-width: 4;
  stroke-linecap: round;
  stroke-dasharray: 50;
  stroke-dashoffset: 50;
  animation: draw-check 0.4s 0.5s ease-out forwards;
}
@keyframes draw-circle {
  to { stroke-dashoffset: 0; }
}
@keyframes draw-check {
  to { stroke-dashoffset: 0; }
}
.progress-container {
  width: 100%;
  height: 8px;
  background: #f1f1f1;
  border-radius: 5px;
}
.progress-track {
  width: 100%;
  height: 100%;
  background: #ddd;
  border-radius: 5px;
}
.progress-fill {
  width: 0%;
  height: 100%;
  background: #0d6efd;
  animation: fillProgress 2s linear forwards;
}
@keyframes fillProgress {
  to { width: 100%; }
}
</style>


<script>
document.querySelector('form').addEventListener('submit', function(e) {
  e.preventDefault();
  const confirmModal = new bootstrap.Modal(document.getElementById('confirmImportModal'));
  confirmModal.show();
});

function handleImport() {
  const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmImportModal'));
  confirmModal.hide();

  const email = document.querySelector('input[name="email"]').value;

  // AJAX check for duplicate email
  fetch(`/check-email?email=${encodeURIComponent(email)}`)
    .then(res => res.json())
    .then(data => {
      if (data.exists) {
        const dupModal = new bootstrap.Modal(document.getElementById('duplicateEmailModal'));
        dupModal.show();
        setTimeout(() => dupModal.hide(), 2000);
      } else {
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        loadingModal.show();

        // Simulate delay for email sending
        setTimeout(() => {
          loadingModal.hide();
          const successModal = new bootstrap.Modal(document.getElementById('successImportModal'));
          successModal.show();
          setTimeout(() => {
            successModal.hide();
            document.querySelector('form').submit();
          }, 2000);
        }, 2500);
      }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const departmentSelect = document.getElementById('department');
    const programSelect = document.getElementById('program');
    const yearSelect = document.getElementById('year');
    const trackSelect = document.getElementById('track');

    const programs = {
        'CABEIHM': ['BSA', 'BSMA', 'BSBA(major FM)', 'BSBA(major MM)', 'BSBA(major HRM)', 'BSHM', 'BSTM'],
        'CICS': ['BSIT'],
        'CTE': ['BEED', 'BSED(major English)', 'BSED(major Mathematics)', 'BSED(major Sciences)', 'BSED(major Filipino)', 'BSED(major Social Studies)'],
        'CCJE': ['BSC'],
        'CONAHS': ['BSN', 'BSND'],
        'CAS': ['BAC', 'BSFT', 'BSP', 'BSFAS']
    };

    departmentSelect.addEventListener('change', function () {
        const selectedDept = this.value;
        programSelect.innerHTML = '<option disabled selected>Select Program</option>';

        if (programs[selectedDept]) {
            programs[selectedDept].forEach(program => {
                const option = document.createElement('option');
                option.textContent = program;
                programSelect.appendChild(option);
            });
        }

        toggleTrack();
    });

    yearSelect.addEventListener('change', toggleTrack);
    programSelect.addEventListener('change', toggleTrack);

    function toggleTrack() {
        const year = yearSelect.value;
        const program = programSelect.value;

        if (program === 'BSIT' && (year === 'Third Year' || year === 'Fourth Year')) {
            trackSelect.disabled = false;
        } else {
            trackSelect.disabled = true;
            trackSelect.value = '';
        }
    }

    trackSelect.disabled = true;
});
</script>
@endsection
