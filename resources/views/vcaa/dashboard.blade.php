@extends('vcaa.vcaasidebar')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">VCAA Dashboard</h3>
  </div>

  @php
    // Expecting $collegeProgramCounts = collection of:
    // [ 'College_id', 'College_name', 'Campus_name' (optional), 'program_count' ]
    $totalColleges = $collegeProgramCounts->count();
    $totalPrograms = $collegeProgramCounts->sum('program_count');
  @endphp

  {{-- Summary cards --}}
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body d-flex align-items-center">
          <div class="flex-grow-1">
            <div class="text-muted small">Total Colleges</div>
            <div class="h3 mb-0 fw-bold">{{ $totalColleges }}</div>
          </div>
          <div class="ms-3">
            <i class="bi bi-building fs-1 text-primary"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body d-flex align-items-center">
          <div class="flex-grow-1">
            <div class="text-muted small">Total Programs</div>
            <div class="h3 mb-0 fw-bold">{{ $totalPrograms }}</div>
          </div>
          <div class="ms-3">
            <i class="bi bi-mortarboard fs-1 text-success"></i>
          </div>
        </div>
      </div>
    </div>

    {{-- Average programs per college --}}
    <div class="col-md-4">
      <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body d-flex align-items-center">
          <div class="flex-grow-1">
            <div class="text-muted small">Average Programs / College</div>
            <div class="h3 mb-0 fw-bold">
              {{ $totalColleges > 0 ? number_format($totalPrograms / $totalColleges, 1) : '0.0' }}
            </div>
          </div>
          <div class="ms-3">
            <i class="bi bi-graph-up fs-1 text-info"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Table per college --}}
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
      <h5 class="mb-0 fw-bold">
        <i class="bi bi-list-ul me-2 text-primary"></i>
        Programs per College
      </h5>

      <form method="GET" class="d-flex gap-2">
        <input
          type="text"
          name="search"
          class="form-control form-control-sm"
          placeholder="Search college…"
          value="{{ request('search') }}"
          style="max-width: 220px;"
        >
        <button class="btn btn-sm btn-outline-primary" type="submit">
          <i class="bi bi-search"></i>
        </button>
      </form>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width: 60px;">#</th>
              <th>College</th>
              <th>Campus</th>
              <th class="text-center" style="width: 160px;">No. of Programs</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($collegeProgramCounts as $index => $row)
              <tr>
                <td class="text-muted">{{ $index + 1 }}</td>
                <td class="fw-semibold">{{ $row->College_name }}</td>
                <td>{{ $row->Campus_name ?? '—' }}</td>
                <td class="text-center">
                  <span class="badge rounded-pill
                    {{ $row->program_count == 0 ? 'bg-secondary' :
                       ($row->program_count <= 3 ? 'bg-info' : 'bg-success') }}">
                    {{ $row->program_count }}
                  </span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center text-muted py-4">
                  No colleges found.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
