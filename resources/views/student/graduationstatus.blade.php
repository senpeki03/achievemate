@extends('student.studentsidebar')

@section('content')
@php
    // --- Helper flags using your actual columns ---
    $req              = $graduationRequirements ?? null;
    $hasAppSheet      = $req && !empty($req->Approval_Sheet);
    $hasLibCert       = $req && !empty($req->Certificate_Library);
    $hasPsa           = $req && !empty($req->Birth_Certificate);
    $hasTorF137       = $req && !empty($req->reportofgrade_path);
    $isHonorApplicant = false;
@endphp

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">

            <div class="card shadow-sm mt-3">
                <div class="card-header grad-header d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Graduation Status
                    </h4>
                </div>

                <div class="card-body">

                    {{-- Success Alert --}}
                    @if(session('delete_success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        {{ session('delete_success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif

                    {{-- Error Alert --}}
                    @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif

                    {{-- ================== SUMMARY TABLE ONLY ================== --}}
                    <h5 class="mb-3">Graduation Requirements Summary</h5>

                    <div class="table-responsive grad-summary-wrap">
                        <table class="table table-bordered align-middle grad-summary-table mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center small-col">No.</th>
                                    <th class="text-center">SR CODE</th>
                                    <th>NAME</th>
                                    <th class="text-center">Graduation Status</th>
                                    <th class="text-center">AppSheet</th>
                                    <th class="text-center">Lib Cert</th>
                                    <th class="text-center">PSA</th>
                                    <th class="text-center">TOR/F137</th>
                                    <th class="text-center">Honor<br>Applicant?</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center">1</td>
                                    <td class="text-center">{{ $student->SRCODE ?? 'N/A' }}</td>
                                    <td>{{ $student->Last_name }}, {{ $student->First_name }} {{ $student->Middle_name }}</td>

                                    <td class="text-center">
                                        <span class="grad-label text-success">
                                            {{ strtoupper($graduationStatus['text']) }}
                                        </span>
                                    </td>

                                    {{-- Requirements Icons --}}
                                    <td class="text-center">
                                        <span class="req-icon {{ $hasAppSheet ? 'text-success' : 'text-danger' }}">
                                            {{ $hasAppSheet ? '✔' : 'X' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <span class="req-icon {{ $hasLibCert ? 'text-success' : 'text-danger' }}">
                                            {{ $hasLibCert ? '✔' : 'X' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <span class="req-icon {{ $hasPsa ? 'text-success' : 'text-danger' }}">
                                            {{ $hasPsa ? '✔' : 'X' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <span class="req-icon {{ $hasTorF137 ? 'text-success' : 'text-danger' }}">
                                            {{ $hasTorF137 ? '✔' : 'X' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <span class="req-icon {{ $isHonorApplicant ? 'text-success' : 'text-danger' }}">
                                            {{ $isHonorApplicant ? '✔' : 'X' }}
                                        </span>
                                    </td>

                                    {{-- ACTION COLUMN --}}
                                    <td class="text-center">
                                        @if($graduationRequirements)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary mb-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#requirementsModal">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger mb-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#confirmDeleteModal">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        @else
                                            <button type="button"
                                                    class="btn btn-sm btn-success"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#requirementsModal">
                                                <i class="fas fa-upload"></i> Upload Requirements
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-2 mb-0 small text-muted">
                        <span class="text-success fw-bold">✔</span> – submitted / cleared &nbsp;&nbsp;
                        <span class="text-danger fw-bold">X</span> – not yet submitted / not cleared
                    </p>

                    {{-- ================== APPLICATION DETAILS REMOVED ================== --}}
                    {{-- ================== END ACTION BUTTONS ================== --}}

                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================== MODAL: UPLOAD / EDIT REQUIREMENTS ================== --}}
<div class="modal fade" id="requirementsModal" tabindex="-1" aria-labelledby="requirementsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('student.graduation.requirements.save') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="requirementsModalLabel">
              <i class="fas fa-file-upload me-2"></i>
              {{ $graduationRequirements ? 'Edit Graduation Requirements' : 'Upload Graduation Requirements' }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
            <input type="hidden" name="GraduationForm_id" value="{{ $graduationForm->GraduationForm_id ?? '' }}">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="fw-bold">Approval Sheet</label>
                    <input type="file" name="Approval_Sheet" class="form-control">
                    @if(!empty($req->Approval_Sheet))
                        <small class="text-success">Already uploaded</small>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="fw-bold">Library Clearance</label>
                    <input type="file" name="Certificate_Library" class="form-control">
                    @if(!empty($req->Certificate_Library))
                        <small class="text-success">Already uploaded</small>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="fw-bold mt-2">Barangay Clearance</label>
                    <input type="file" name="Barangay_Clearance" class="form-control">
                    @if(!empty($req->Barangay_Clearance))
                        <small class="text-success">Already uploaded</small>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="fw-bold mt-2">Birth Certificate (PSA)</label>
                    <input type="file" name="Birth_Certificate" class="form-control">
                    @if(!empty($req->Birth_Certificate))
                        <small class="text-success">Already uploaded</small>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="fw-bold mt-2">Graduation Application Form</label>
                    <input type="file" name="applicationform_grad" class="form-control">
                    @if(!empty($req->applicationform_grad))
                        <small class="text-success">Already uploaded</small>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="fw-bold mt-2">Report of Grades (COG)</label>
                    <input type="file" name="reportofgrade_path" class="form-control">
                    @if(!empty($req->reportofgrade_path))
                        <small class="text-success">Already uploaded</small>
                    @endif
                </div>

                {{-- NEW: Remarks Field --}}
                <div class="col-12">
                    <label class="fw-bold mt-2">Remarks (Optional)</label>
                    <textarea class="form-control" name="remarks" rows="3" 
                        placeholder="Any additional notes or remarks about your graduation requirements...">{{ old('remarks', $remarks ?? '') }}</textarea>
                    <small class="text-muted">Add any notes or comments about your graduation requirements.</small>
                </div>
            </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i> Save Requirements
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ================== DELETE CONFIRMATION MODAL ================== --}}
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body py-5 px-4 text-center" style="height: 300px;">
        <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/exclamation-mark-4667807-3870807.png?f=webp&w=256" style="width: 60px;" class="mb-3" />
        <h5 class="fw-bold text-dark mb-4">Are you sure you want to delete your graduation application?</h5>
        <p class="text-muted mb-4">This will permanently delete both your graduation requirements and graduation form.</p>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">No, Cancel</button>
          <form action="{{ route('student.graduation.requirements.destroy') }}" method="POST" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger px-4">Yes, Delete</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
    .grad-header {
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
    }
    .grad-header h4 {
        color: #111827;
        font-weight: 600;
    }
    .grad-header i {
        color: #0d6efd;
    }

    .grad-summary-wrap {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
    }
    .grad-summary-table thead {
        background: #f7f9fc;
    }
    .grad-summary-table th {
        font-weight: 700;
        font-size: .85rem;
        white-space: nowrap;
    }
    .grad-summary-table td {
        font-size: .9rem;
    }
    .grad-summary-table .small-col {
        width: 60px;
    }
    .grad-label {
        font-weight: 700;
        letter-spacing: .04em;
        font-size: .85rem;
    }
    .req-icon {
        font-size: 1rem;
        font-weight: 800;
    }
    .card.bg-light {
        border: 1px solid #dee2e6;
    }
</style>

<script>
// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide success/error alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
@endsection