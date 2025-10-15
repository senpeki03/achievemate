@extends('student.studentsidebar')

@section('title', 'Student Profile | AchieveMate')

<link rel="stylesheet" href="{{ asset('css/student-profile.css') }}">


@section('content')
<div class="student-profile container-xxl py-4">
  <div class="profile-container">
    <div class="profile-header">
      <div class="profile-img-container">
        <img id="profileImage" src="https://via.placeholder.com/130" class="profile-img" alt="Profile">
        <label class="camera-icon" for="fileUpload" title="Upload photo">📷</label>
        <input type="file" id="fileUpload" accept="image/*" class="d-none">
      </div>

      <div class="profile-details">
        <h4 id="studentName">{{ $fullName }}</h4>
        <p><strong>College:</strong> <span class="highlight">{{ $college }}</span></p>
        <p><strong>Program:</strong> <span class="highlight">{{ $program }}</span></p>
        <p><strong>Student No:</strong> <span class="highlight" id="studentNo">{{ $student->Student_no ?? '' }}</span></p>
        <p><strong>SR Code:</strong> <span class="highlight" id="rollNo">{{ $student->SRCODE ?? '' }}</span></p>
      </div>
    </div>

    <form id="profileForm" class="mt-4">
      <table class="info-table">
        <tr>
          <th>Admission Date</th>
          <td>
            <input type="date" class="form-control" name="admissionDate"
              value="{{ optional(\Carbon\Carbon::parse($student->Admission_date ?? null))->format('Y-m-d') }}" disabled>
          </td>
        </tr>
        <tr>
          <th>Date of Birth</th>
          <td>
            <input type="date" class="form-control" name="dob"
              value="{{ optional(\Carbon\Carbon::parse($student->Birthdate ?? null))->format('Y-m-d') }}" disabled>
          </td>
        </tr>
        <tr>
          <th>Gender</th>
          <td>
            @php $gender = $student->Sex ?? ''; @endphp
            <select class="form-select" name="gender" disabled>
              <option {{ $gender === 'Male' ? 'selected' : '' }}>Male</option>
              <option {{ $gender === 'Female' ? 'selected' : '' }}>Female</option>
            </select>
          </td>
        </tr>
        <tr>
          <th>Nationality</th>
          <td><input type="text" class="form-control" name="nationality" value="{{ $student->Nationality ?? '' }}" disabled></td>
        </tr>
        <tr>
          <th>Academic Year</th>
          <td><input type="text" class="form-control" name="academicYear" value="{{ $ayLabel }}" disabled></td>
        </tr>
      </table>
    </form>

    <div class="text-end">
      <button class="btn btn-edit" id="editBtn">Edit Profile</button>
      <button class="btn btn-save d-none" id="saveBtn">Save Changes</button>
    </div>
  </div>
</div>


<script>
  document.querySelector('.camera-icon')?.addEventListener('click', () => {
    document.getElementById('fileUpload')?.click();
  });
  document.getElementById('fileUpload')?.addEventListener('change', (e) => {
    const file = e.target.files?.[0]; if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => document.getElementById('profileImage').src = ev.target.result;
    reader.readAsDataURL(file);
  });
  document.getElementById('editBtn')?.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('#profileForm input, #profileForm select').forEach(el => el.disabled = false);
    document.getElementById('editBtn').classList.add('d-none');
    document.getElementById('saveBtn').classList.remove('d-none');
  });
  document.getElementById('saveBtn')?.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('#profileForm input, #profileForm select').forEach(el => el.disabled = true);
    document.getElementById('editBtn').classList.remove('d-none');
    document.getElementById('saveBtn').classList.add('d-none');
    alert('✅ Profile changes saved successfully!');
  });
</script>
@endsection
