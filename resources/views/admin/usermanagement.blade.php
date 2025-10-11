@extends('admin.adminlayout')

@section('content')

<style>
  html, body {
    overflow: hidden !important;
  }
</style>

<div class="container py-4">
  <div class="card shadow-sm rounded-4 w-100" style="height: 88vh;">
    <div class="card-body d-flex flex-column">

      <!-- Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold">Users</h4>
      </div>

        <!-- Centered Empty State -->
    <div class="d-flex justify-content-center align-items-center text-center">
      <div class="empty-state" style="line-height: 1;">
        <img src="{{ asset('img/box.jpg') }}" alt="No students" style="width: 500px; display: block; margin-bottom: -50px;">

        <h5 class="fw-bold" style="margin: 0; padding: 0;">Start adding User</h5>

        <div class="d-flex justify-content-center gap-2" style="margin-bottom: -20px;">
          <a href="{{ route('admin.adminimport') }}" class="btn btn-primary">
            <i class="fas fa-file-csv me-1"></i> Import CSV
          </a>
          <a href="#" class="btn btn-primary">
            + Add Student
          </a>
        </div>
      </div>
    </div>




    </div>
  </div>
</div>
@endsection
