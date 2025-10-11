<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ACHIEVEMATE</title>
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}"> 
   <!-- <link rel="stylesheet" href="{{ asset('bootstrap5/css/bootstrap.min.css') }}"> -->
   <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"> 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"> 
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<div class="sidebar close">
<!-- Logo wrapper inside sidebar -->
<div class="logo-details d-flex align-items-center">
  <div class="logo-container d-flex align-items-center">
    
    <!-- Logo as 'A' -->
    <div class="logo-icon-container" style="margin-right: -10px;">
      <img src="{{ asset('img/logo.png') }}" alt="Logo" class="main-logo-img" style="height: 65px;">
    </div>

    <!-- 'CHIEVEMATE' -->
    <div class="logo-text">
      <div class="d-flex align-items-end" style="line-height: 1; margin-top: 6px;">
        <span class="logo-chie" style="margin-left: -6px; margin-right: -10px;">CHIE</span>
        <img src="{{ asset('img/v.png') }}" alt="V" class="logo-v" style="height: 40px; margin-bottom: -5px; margin-left: 0; margin-right: -5px;">
        <span class="logo-emate" style="margin-left: -7px;">EMATE</span>
      </div>
    </div>

  </div>
</div>

  <ul class="nav-links">
    <li>
    <a href="#">
        <i class='bx bx-home'></i>
        <span class="link_name">Home</span>
    </a>
    <ul class="sub-menu blank">
        <li><a href="#">Home</a></li>
    </ul>
</li>



    <li>
      <a href="#">
        <i class='bx bx-user'></i>
        <span class="link_name">View Portfolio</span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="link_name" href="#">View Portfolio</a></li>
      </ul>
    </li>

    <li>
      <a href="{{ route('student.applydeanslist') }}">
        <i class='bx bx-user-check'></i>
        <span class="link_name">Apply Dean’s List</span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="link_name" href="{{ route('student.applydeanslist')  }}">Apply Dean’s List</a></li>
      </ul>
    </li>

    <li>
    <a href="#">
        <i class='bx bxs-graduation'></i> <!-- ✅ Fixed icon -->
        <span class="link_name">Apply Latin Honor</span>
    </a>
    <ul class="sub-menu blank">
        <li><a class="link_name" href="#">Apply Latin Honor</a></li>
    </ul>
    </li>


    <li>
      <a href="#">
        <i class='bx bx-task'></i>
        <span class="link_name">Application Status</span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="link_name" href="#">Application Status</a></li>
      </ul>
    </li>

    <li>
      <div class="iocn-link">
        <a href="#">
          <i class='bx bx-medal'></i>
          <span class="link_name">Achievements</span>
        </a>
        <i class='bx bxs-chevron-down arrow'></i>
      </div>
      <ul class="sub-menu">
        <li><a class="link_name" href="#">Achievements</a></li>
        <li><a href="#">Certifications</a></li>
        <li><a href="#">Seminars</a></li>
        <li><a href="#">Honors</a></li>
      </ul>
    </li>
</div>

<!-- 🧭 Main Header -->
<div class="main-header d-flex align-items-center justify-content-between px-4 py-2 bg-white border-bottom sticky-top">

  <!-- 🔍 Left Section -->
  <div class="d-flex align-items-center gap-3">
    <i class='bx bx-menu fs-4' id="sidebarToggle" style="cursor: pointer;"></i>
    <div class="search-box">
      <input type="text" class="form-control" placeholder="Search for..." style="background:#f1f3f5; border:none; width:250px; border-radius:6px;">
    </div>
  </div>

  <!-- ⚙️ Right Section -->
  <div class="d-flex align-items-center gap-3">
    <!-- 🔔 Notification Bell (with redirect to /notifications) -->
    @php
      use App\Models\PostRecipient;
      $notifCount = 0;
      $loginId = session('Login_id');

      if ($loginId) {
          $notifCount = PostRecipient::where('student_userId', $loginId)
              ->where('is_read', false)
              ->count();
      }
    @endphp

    <a href="{{ route('student.notifications') }}" class="position-relative" style="cursor: pointer;">
      <i class='bx bx-bell fs-4'></i>
      @if ($notifCount > 0)
        <span id="notifBadge"
              class="badge bg-danger position-absolute"
              style="top: -5px; right: -8px; font-size: 0.7rem; padding: 4px 7px; border-radius: 50%;">
          {{ $notifCount }}
        </span>
      @endif
    </a>



    <!-- ⚙️ Settings Icon -->
    <i class='bx bx-cog'></i>

    <!-- 👤 User Info -->
    <div class="text-end">
      <span class="fw-semibold">{{ session('firstname') ?? '' }} {{ session('lastname') ?? '' }}</span>
      <small class="d-block text-muted" style="font-size: 0.8rem;">
        ({{ session('usertype') ?? 'Role Unknown' }})
      </small>
    </div>

    <!-- 🔽 Profile Dropdown -->
    <div class="dropdown">
      <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class='bx bx-user-circle fs-4'></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ route('student.studentprofile') }}">Profile</a></li>
        <li><a class="dropdown-item" href="#">Settings</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" onclick="confirmLogout()">Logout</a></li>
      </ul>
    </div>
  </div>
</div>



<section class="home">
  <div class="home-1 d-none">
    <i class='bx bx-menu'></i>
  </div>
  @yield('content')
</section>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

  document.querySelectorAll('.mark-as-read').forEach(button => {
  button.addEventListener('click', function () {
    const notifId = this.getAttribute('data-id');
    fetch("{{ route('student.notifications.markAsRead') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
      },
      body: JSON.stringify({ id: notifId })
    })
    .then(response => response.json())
    .then(data => {
      this.closest('li').querySelector('.btn').remove();
      if (data.unreadCount <= 0) {
        document.getElementById('notifBadge').style.display = 'none';
      } else {
        document.getElementById('notifBadge').innerText = data.unreadCount;
      }
    });
  });
});

  // Toggle sidebar open/close
  document.querySelector("#sidebarToggle").addEventListener("click", () => {
    document.querySelector(".sidebar").classList.toggle("close");
  });

  // Logout confirmation
  function confirmLogout() {
    if (confirm("Are you sure you want to log out?")) {
      window.location.href = "{{ route('logout') }}";
    }
  }

  // Handle dropdowns if any (future proof)
  document.querySelectorAll(".arrow").forEach(arrow => {
    arrow.addEventListener("click", (e) => {
      const arrowParent = e.target.closest(".iocn-link").parentElement;
      arrowParent.classList.toggle("showMenu");
    });
  });
</script>
</body>
</html>
