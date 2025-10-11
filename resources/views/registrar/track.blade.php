@extends('registrar.layout')

@section('content')
<div class="container py-4">
  <div class="card shadow-sm rounded-4">
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('registrar.year.list', ['department' => $department]) }}" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>
        </a>
        <h4 class="fw-bold mb-0">Tracks for {{ $year }} - {{ $department }}</h4>
      </div>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-light">
            <tr>
              <th><input type="checkbox"></th>
              <th>Track</th>
              <th>No. of Students</th>
            </tr>
          </thead>
          <tbody>
            @if(count($tracks) > 0)
              @foreach ($tracks as $track)
                <tr class="clickable-row"
                    data-href="{{ route('registrar.students.by.track', [
                        'department' => $department,
                        'year' => urlencode($year),
                        'track' => urlencode($track->track)
                    ]) }}"
                    style="cursor: pointer;">
                  <td><input type="checkbox"></td>
                  <td>{{ $track->track }}</td>
                  <td>{{ $track->student_count }}</td>
                </tr>
              @endforeach
            @else
              <tr>
                <td colspan="3" class="text-center text-muted">No tracks found for this year and department.</td>
              </tr>
            @endif
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

