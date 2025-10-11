@extends('registrar.layout')

@section('content')
<div class="container py-4">
  <div class="card shadow-sm rounded-4">
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('registrar.studentlist') }}" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>
        </a>
        <h4 class="fw-bold mb-0">Year</h4>
      </div>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-light">
            <tr>
              <th><input type="checkbox"></th>
              <th>Year</th>
              <th>No. of Students</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($students as $student)
              @php
                $yearLower = strtolower($student->year);
                $hasTrack = in_array($yearLower, ['third year', 'fourth year']);
                $targetRoute = $hasTrack
                  ? route('registrar.track', ['department' => $department, 'year' => urlencode($student->year)])
                  : route('registrar.students.by.year', ['department' => $department, 'year' => urlencode($student->year)]);
              @endphp
              <tr class="clickable-row"
                  data-href="{{ $targetRoute }}"
                  style="cursor: pointer;">
                <td><input type="checkbox"></td>
                <td>{{ $student->year }}</td>
                <td>{{ $student->student_count }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.clickable-row').forEach(row => {
      row.addEventListener('click', function (e) {
        if (e.target.tagName.toLowerCase() !== 'input') {
          window.location.href = this.dataset.href;
        }
      });
    });
  });
</script>
