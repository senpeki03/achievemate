
{{-- resources/views/dean/profile.blade.php --}}
@extends('dean.deansidebar')
@section('title', 'Dean Profile | AchieveMate')

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

        <!-- Camera button -->
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
        <p class="mb-1"><strong>SR Code:</strong> <span class="text-primary">{{ $srCode }}</span></p>
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

    <div class="text-end mt-4">
      <button class="btn btn-primary px-4" id="editBtn" type="button">Edit Profile</button>
    </div>
  </div>
</div>

<script>
  let selectedFile = null;

  document.querySelector('label[for="fileUpload"]')?.addEventListener('click', () => {
    document.getElementById('fileUpload')?.click();
  });

  document.getElementById('fileUpload')?.addEventListener('change', (e) => {
    selectedFile = e.target.files?.[0] || null;
    if (!selectedFile) return;
    const reader = new FileReader();
    reader.onload = ev => { document.getElementById('profileImage').src = ev.target.result; };
    reader.readAsDataURL(selectedFile);
  });

  document.getElementById('editBtn')?.addEventListener('click', async () => {
    if (!selectedFile) {
      alert('Please choose a photo first.');
      return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const fd = new FormData();
    fd.append('photo', selectedFile);

    try {
      const res = await fetch("{{ route('dean.profile.save') }}", {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: fd,
        credentials: 'same-origin'
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) {
        alert('Failed to save profile.\n' + (data.message || `HTTP ${res.status}`));
        return;
      }

      document.getElementById('profileImage').src = "{{ route('dean.profile.photo') }}" + "?v=" + Date.now();
      selectedFile = null;
      alert('✅ Profile saved.');
    } catch (err) {
      alert('Failed to save profile.\n' + (err?.message || err));
      console.error(err);
    }
  });
</script>
@endsection
