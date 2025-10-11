<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ACHIEVEMATE</title>
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}"> 
   <!-- <link rel="stylesheet" href="{{ asset('bootstrap5/css/bootstrap.min.css') }}"> -->
   <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"> 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"> 
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
                <i class='bx bx-home'></i> <!-- Home -->
                <span class="link_name">Home</span>
              </a>
              <ul class="sub-menu blank">
                <li><a class="link_name" href="#">Home</a></li>
              </ul>
            </li>

            <li>
              <a href="{{ route('curriculum.upload.form') }}">
                <i class="bi bi-journal-text"></i> <!-- Curriculum -->
                <span class="link_name">Curriculum</span>
              </a>
              <ul class="sub-menu blank">
                <li><a class="link_name" href="{{ route('curriculum.upload.form') }}">Curriculum</a></li>
              </ul>
            </li>

            <li>
              <a href="#">
                <i class='bi bi-patch-check-fill'></i> <!-- Evaluation -->
                <span class="link_name">Dean's Honor List Evaluation</span>
              </a>
              <ul class="sub-menu blank">
                <li><a class="link_name" href="#">Dean's Honor List Evaluation</a></li>
              </ul>
            </li>

            <li>
              <a href="#">
                <i class='bi bi-star-fill'></i> <!-- Honor List -->
                <span class="link_name">Dean's Honor List</span>
              </a>
              <ul class="sub-menu blank">
                <li><a class="link_name" href="#">Dean's Honor List</a></li>
              </ul>
            </li>

            <li>
              <a href="#">
                <i class='bi bi-person-check-fill'></i> <!-- Initial Evaluation -->
                <span class="link_name">Latin Honor Initial Evaluation</span>
              </a>
              <ul class="sub-menu blank">
                <li><a class="link_name" href="#">Latin Honor Initial Evaluation</a></li>
              </ul>
            </li>

            <li>
              <a href="#">
                <i class='bi bi-award-fill'></i> <!-- Honor List -->
                <span class="link_name">Latin Honor List</span>
              </a>
              <ul class="sub-menu blank">
                <li><a class="link_name" href="#">Latin Honor List</a></li>
              </ul>
            </li>

            <li>
                <div class="iocn-link">
                  <a href="#">
                    <i class='bi bi-megaphone-fill'></i>
                    <span class="link_name">Announcement</span>
                  </a>
                  <i class='bx bxs-chevron-down arrow'></i>
                </div>
                <ul class="sub-menu">
                  <li><a class="link_name" href="#">Announcement</a></li>
                  <li><a href="{{ route('programchair.post.form') }}">Post</a></li>
                  <li><a href="#">Examinations</a></li>
                  <li><a href="#">Dean's Honor List</a></li>
                </ul>
              </li>
       
</ul>
    </div>

<!-- Main Header (beside sidebar) -->
    <div class="main-header d-flex align-items-center justify-content-between px-4 py-2 bg-white border-bottom sticky-top">
  <div class="d-flex align-items-center gap-3">
    <i class='bx bx-menu fs-4' id="sidebarToggle" style="cursor: pointer;"></i>
    <div class="search-box">
      <input type="text" class="form-control" placeholder="Search for..." style="background:#f1f3f5; border:none; width:250px; border-radius:6px;">
    </div>
  </div>
  <div class="d-flex align-items-center gap-3">
    <i class='bx bx-bell position-relative'>
      <span class="badge bg-danger position-absolute rounded-circle" style="top: -5px; right: -8px; font-size: 10px;">1</span>
    </i>
    <i class='bx bx-cog'></i>
    <div class="text-end">
      <span class="fw-semibold">{{ session('firstname') }} {{ session('lastname') }} </span>
      <small class="d-block text-muted" style="font-size: 0.8rem;">({{ session('usertype') }})</small>
    </div>
    <div class="dropdown">
      <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class='bx bx-user-circle fs-4'></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ route('admin.adminprofile') }}">Profile</a></li>
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

</body>
</html>
<!-- Bootstrap Bundle with Popper -->


<script>
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

