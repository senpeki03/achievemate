@extends('admin.adminlayout')

@section('content')
<div class="container py-5">
  <div class="card shadow-sm rounded-4">
    <div class="card-body p-4">
      <h4 class="fw-bold mb-4">My Profile</h4>
      <form method="POST" action="{{ route('student.changePassword') }}">
        @csrf
        <div class="row g-3">
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

          <div class="col-md-4 mt-4">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="col-md-4 mt-4 position-relative">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" id="new_password" class="form-control" required>
            <i class="fas fa-eye toggle-password position-absolute" style="top: 38px; right: 15px; cursor: pointer;" onclick="togglePassword('new_password', this)"></i>
          </div>
          <div class="col-md-4 mt-4 position-relative">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="new_password_confirmation" id="confirm_password" class="form-control" required>
            <i class="fas fa-eye toggle-password position-absolute" style="top: 38px; right: 15px; cursor: pointer;" onclick="togglePassword('confirm_password', this)"></i>
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
function togglePassword(fieldId, icon) {
  const field = document.getElementById(fieldId);
  if (field.type === "password") {
    field.type = "text";
    icon.classList.remove("fa-eye");
    icon.classList.add("fa-eye-slash");
  } else {
    field.type = "password";
    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
  }
}
</script>
@endsection