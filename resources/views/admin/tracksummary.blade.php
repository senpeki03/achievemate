@extends('admin.adminlayout')

@section('content')
<div class="container py-4">
  <div class="card shadow-sm rounded-4">
    <div class="card-body">
      <!-- Header -->
      <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('admin.user.list') }}" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>
        </a>
        <h4 class="fw-bold mb-0">{{ $department }} - {{ $year }} Track Summary</h4>
      </div>

      <!-- Clean and Aligned Track Summary Table -->
        <div class="table-responsive">
           <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                    <th><input type="checkbox" disabled></th>
                    <th>Track</th>
                    <th>No. of Students</th>
                    </tr>
                </thead>
             <tbody>
                @forelse($tracks as $track)
                <tr class="clickable-row" data-href="{{ route('admin.students.by.track', ['department' => $department, 'year' => $year, 'track' => $track->track]) }}">
                    
                    <td><input type="checkbox" disabled></td>
                    <td>{{ $track->track }}</td>
                    <td>{{ $track->student_count }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="text-muted text-center">No tracks found.</td>
                </tr>
                @endforelse
             </tbody>
            </table>
        </div>
    </div>
  </div>
</div>

<!-- Row click navigation -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.clickable-row').forEach(row => {
      row.addEventListener('click', function () {
        window.location.href = this.dataset.href;
      });
    });
  });
</script>
@endsection
