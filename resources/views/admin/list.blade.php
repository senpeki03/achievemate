@extends('admin.adminlayout')

@section('content')
<div class="container py-4">
  <div class="card shadow-sm rounded-4">
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('admin.user.list') }}" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>
        </a>
        <h4 class="fw-bold mb-0">{{ $department }} - Year Level Summary</h4>
      </div>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-light">
            <tr>
              <th><input type="checkbox" disabled></th>
              <th>Year</th>
              <th>No. of Students</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($students as $student)
              <tr class="clickable-row"
                data-href="{{ ($department === 'CICS' && in_array($student->year, ['Third Year', 'Fourth Year']))
                    ? route('admin.users.by.year', ['department' => $department, 'year' => $student->year])
                    : route('admin.students.by.year.direct', ['department' => $department, 'year' => $student->year])
                }}">

                <td><input type="checkbox" disabled></td>
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
@endsection
