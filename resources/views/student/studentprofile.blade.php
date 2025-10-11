@extends('student.studentlayout')

@section('content')
<style>
  .toggle-eye {
    position: absolute;
    top: 52%;
    right: 15px;
    transform: translateY(-50%);
    cursor: pointer;
    color: #555;
    z-index: 10;
  }
  .toggle-eye::before {
    display: inline-block; /* Ensures icon displays normally */
  }
</style>

<!-- Font Awesome (load only once globally if possible) -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<div class="container py-5">
  <div class="card shadow-sm rounded-4">
    <div class="card-body p-4">
      <h4 class="fw-bold mb-4">My Profile</h4>
      <form method="POST" action="{{ route('student.changePassword') }}">
        @csrf
        <div class="row g-3">
          <!-- Basic Info -->
          <div class="col-md-6">
            <label class="form-label">Srcode</label>
            <input type="text" name="srcode" class="form-control" value="{{ old('srcode', session('srcode')) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">First Name</label>
            <input type="text" name="firstname" class="form-control" value="{{ old('firstname', session('firstname')) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Middle Name</label>
            <input type="text" name="middlename" class="form-control" value="{{ old('middlename', session('middlename')) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Last Name</label>
            <input type="text" name="lastname" class="form-control" value="{{ old('lastname', session('lastname')) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Department</label>
            <input type="text" name="department" class="form-control" value="{{ old('department', session('department')) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Program</label>
            <input type="text" name="program" class="form-control" value="{{ old('program', session('program')) }}">
          </div>
          <div class="col-md-12">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', session('email')) }}" readonly style="background-color: #e9ecef;">
          </div>

          <!-- Password Fields -->
          <div class="col-md-4 mt-3 position-relative">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" id="current_password" class="form-control pe-3" required oninput="handleEyeIcon('current_password')">
            <span onclick="togglePassword('current_password', this)"></span>
          </div>

          <div class="col-md-4 mt-3 position-relative">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" id="new_password" class="form-control pe-3" required oninput="handleEyeIcon('new_password')">
            <span onclick="togglePassword('new_password', this)"></span>
          </div>

          <div class="col-md-4 mt-3 position-relative">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="new_password_confirmation" id="confirm_password" class="form-control pe-3" required oninput="handleEyeIcon('confirm_password')">
            <span  onclick="togglePassword('confirm_password', this)"></span>
          </div>
        </div>

        <div class="text-end mt-4">
          <button type="submit" class="btn btn-primary px-4">Change Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function togglePassword(inputId, iconElement) {
  const input = document.getElementById(inputId);
  if (input.type === "password") {
    input.type = "text";
    iconElement.classList.remove("fa-eye");
    iconElement.classList.add("fa-eye-slash");
  } else {
    input.type = "password";
    iconElement.classList.remove("fa-eye-slash");
    iconElement.classList.add("fa-eye");
  }
}

function handleEyeIcon(inputId) {
  const input = document.getElementById(inputId);
  const icon = document.getElementById('icon_' + inputId);
  if (input.value.length > 0) {
    icon.classList.remove('d-none');
  } else {
    icon.classList.add('d-none');
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}
</script>
@endsection
