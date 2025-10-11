@extends('admin.adminlayout')

@section('content')
<div class="container py-4">
  <div class="card shadow-sm rounded-4">
    <div class="card-body">

      <!-- Section Header -->
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold mb-0">Summary Overview</h4>
      </div>

      <!-- Tab Navigation -->
      <ul class="nav nav-tabs mb-4" id="summaryTab" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="college-tab" data-bs-toggle="tab" data-bs-target="#college" type="button" role="tab">
            College Summary
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#user" type="button" role="tab">
            User Summary
          </button>
        </li>
      </ul>

      <!-- Tab Content -->
      <div class="tab-content" id="summaryTabContent">

        <!-- 🏫 College Summary Tab -->
        <div class="tab-pane fade show active" id="college" role="tabpanel">
          <div class="table-responsive">
            <table class="table align-middle table-sm">
              <thead class="table-light">
                <tr>
                  <th></th>
                  <th>College</th>
                  <th>No. of Students</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($students as $student)
                <tr class="clickable-row" onclick="window.location='{{ route('admin.year.list', ['department' => $student->department]) }}'">
                  <td><input type="checkbox" disabled></td>
                  <td>{{ $student->department }}</td>
                  <td>{{ $student->student_count }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        <!-- 👤 User Summary Tab -->
        <div class="tab-pane fade" id="user" role="tabpanel">
          <div class="table-responsive">
            <table class="table align-middle table-sm">
              <thead class="table-light">
                <tr>
                  <th></th>
                  <th>User Type</th>
                  <th>No. of Users</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($users as $user)
                <tr class="clickable-row" onclick="window.location='{{ route('admin.proflist', ['usertype' => $user->usertype]) }}'">
                  <td><input type="checkbox" disabled></td>
                  <td>{{ ucfirst($user->usertype) }}</td>
                  <td>{{ $user->count }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Hover Style -->
<style>
  .clickable-row {
    cursor: pointer;
    transition: background-color 0.2s ease-in-out;
  }

  .clickable-row:hover {
    background-color: #e9f1fc !important;
  }
</style>
@endsection
