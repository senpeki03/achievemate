@extends('student.studentsidebar')
@section('title', 'Student Profile | AchieveMate')

<link rel="stylesheet" href="{{ asset('css/student-profile.css') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('content')
<div class="student-profile container-xxl py-5">
  <div class="profile-container shadow p-5 bg-white rounded-4 mx-auto" style="max-width: 1100px;">

    <div class="profile-header d-flex align-items-center gap-4 flex-wrap">
      <!-- PROFILE IMAGE -->
      <div class="position-relative" style="width:140px;height:140px;">
        <img id="profileImage" src="{{ $photoUrl }}"
             class="rounded-circle border border-3 border-primary mb-3"
             style="width:140px;height:140px;object-fit:cover;" alt="Profile">

        <!-- Camera button opens file picker -->
        <label for="fileUpload"
               class="position-absolute d-flex justify-content-center align-items-center shadow"
               title="Choose photo"
               style="bottom:0;left:50%;transform:translate(-50%,50%);
                      width:38px;height:38px;border-radius:50%;
                      background:#fff;border:2px solid #004aad;cursor:pointer;">
          <svg width="20" height="20" viewBox="0 0 24 24" style="stroke:#004aad;fill:none;stroke-width:2;">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h3l2-3h8l2 3h3a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
        </label>
        <input type="file" id="fileUpload" accept="image/*" class="d-none">
      </div>

      <div class="flex-grow-1">
        <h3 class="fw-bold mb-2">{{ $fullName }}</h3>
        <p class="mb-1"><strong>College:</strong> <span class="text-primary">{{ $college }}</span></p>
        <p class="mb-1"><strong>Program:</strong> <span class="text-primary">{{ $program }}</span></p>
        <p class="mb-1"><strong>SR Code:</strong> <span class="text-primary">{{ $student->SRCODE ?? '' }}</span></p>
      </div>
    </div>

    <!-- READ-ONLY FIELDS -->
    <form id="profileForm" class="mt-5">
      <table class="w-100">
        <tr>
          <th style="width:25%;padding:14px;">Contact Number</th>
          <td style="padding:10px;"><input type="text" class="form-control form-control-sm" value="{{ $contactNumber }}" disabled></td>
        </tr>
        <tr>
          <th style="padding:14px;">Date of Birth</th>
          <td style="padding:10px;"><input type="date" class="form-control form-control-sm" value="{{ $dobYmd }}" disabled></td>
        </tr>
        <tr>
          <th style="padding:14px;">Place of Birth</th>
          <td style="padding:10px;"><input type="text" class="form-control form-control-sm" value="{{ $placeOfBirth }}" disabled></td>
        </tr>
        <tr>
          <th style="padding:14px;">Home Address</th>
          <td style="padding:10px;"><input type="text" class="form-control form-control-sm" value="{{ $homeAddress }}" disabled></td>
        </tr>
        <tr>
          <th style="padding:14px;">Academic Year</th>
          <td style="padding:10px;"><input type="text" class="form-control form-control-sm" value="{{ $ayLabel }}" disabled></td>
        </tr>
      </table>
    </form>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button class="btn btn-outline-secondary px-4" type="button" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
        Change Password
      </button>
      <button class="btn btn-primary px-4" id="editBtn" type="button">Edit Profile</button>
    </div>
  </div>
</div>

<!-- CHANGE PASSWORD MODAL -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-3">
      <div class="modal-header">
        <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="changePasswordForm">
        @csrf
        <div class="modal-body">
          <div id="changePasswordAlert" class="alert d-none" role="alert"></div>

          <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="current_password" name="current_password" required>
          </div>

          <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required>
          </div>

          <div class="mb-3">
            <label for="new_password_confirmation" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="new_password_confirmation" name="new_password_confirmation" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  let selectedFile = null;

  // Camera button → open file picker
  document.querySelector('label[for="fileUpload"]')?.addEventListener('click', () => {
    document.getElementById('fileUpload')?.click();
  });

  // Preview only; actual save happens when clicking "Edit Profile"
  document.getElementById('fileUpload')?.addEventListener('change', (e) => {
    selectedFile = e.target.files?.[0] || null;
    if (!selectedFile) return;
    const reader = new FileReader();
    reader.onload = ev => { document.getElementById('profileImage').src = ev.target.result; };
    reader.readAsDataURL(selectedFile);
  });

  // On Edit Profile → send selected image (if any) to DB
  document.getElementById('editBtn')?.addEventListener('click', async () => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const fd = new FormData();
    if (selectedFile) fd.append('photo', selectedFile); // only send if chosen

    try {
      const res = await fetch("{{ route('student.profile.save') }}", {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: fd,
        credentials: 'same-origin'
      });

      let data = {};
      try { data = await res.json(); } catch (_) {}

      if (!res.ok || data.ok === false) {
        const msg = data.message || (data.errors && Object.values(data.errors).flat().join('\n')) || `HTTP ${res.status}`;
        alert('Failed to save profile.\n' + msg);
        return;
      }

      // success: reload image from DB and clear local selection
      const img = document.getElementById('profileImage');
      img.src = "{{ route('student.profile.photo') }}" + "?v=" + Date.now();
      selectedFile = null;
      alert('✅ Profile saved.');
    } catch (err) {
      alert('Failed to save profile.\n' + (err?.message || err));
      console.error(err);
    }
  });

  // CHANGE PASSWORD HANDLER
  const changePasswordForm = document.getElementById('changePasswordForm');
  const changePasswordAlert = document.getElementById('changePasswordAlert');

  changePasswordForm?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const payload = {
      current_password: document.getElementById('current_password').value,
      new_password: document.getElementById('new_password').value,
      new_password_confirmation: document.getElementById('new_password_confirmation').value,
    };

    changePasswordAlert.classList.add('d-none');
    changePasswordAlert.textContent = '';

    try {
      const res = await fetch("{{ route('student.password.change') }}", {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      });

      let data = {};
      try { data = await res.json(); } catch (_) {}

      if (!res.ok || data.ok === false) {
        let msg = data.message || 'Password change failed.';

        if (data.errors) {
          msg = Object.values(data.errors).flat().join('\n');
        }

        changePasswordAlert.classList.remove('d-none', 'alert-success');
        changePasswordAlert.classList.add('alert-danger');
        changePasswordAlert.textContent = msg;
        return;
      }

      // success
      changePasswordAlert.classList.remove('d-none', 'alert-danger');
      changePasswordAlert.classList.add('alert-success');
      changePasswordAlert.textContent = data.message || 'Password changed successfully.';

      // clear inputs
      changePasswordForm.reset();

      // optionally close modal after a short delay
      setTimeout(() => {
        const modalEl = document.getElementById('changePasswordModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        modalInstance?.hide();
      }, 1000);
    } catch (err) {
      changePasswordAlert.classList.remove('d-none', 'alert-success');
      changePasswordAlert.classList.add('alert-danger');
      changePasswordAlert.textContent = err?.message || 'Something went wrong.';
      console.error(err);
    }
  });
</script>
@endsection
