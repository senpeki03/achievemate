@php
  $currentRoute = Route::currentRouteName();
  $campusRoutes = ['programchair.post.index', 'admin.college', 'admin.program']; // include all children
  $isCampusActive = in_array($currentRoute, $campusRoutes);
@endphp

@php
  $studentRoutes = ['registrar.student', 'registrar.studentlist', 'registrar.studentupload'];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AchieveMate</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="sidebar-collapsed">

  <!-- SIDEBAR -->
  <div class="sidebar collapsed" id="sidebar">
    <div class="sidebar-header sidebar-logo">
      <img src="{{ asset('img/AchieveMate02.png') }}" alt="Logo">
      <i class="bi bi-list burger" id="burgerToggle"></i>
    </div>

    <div style="height: 40px;"></div>

    <!-- Dashboard Card -->
    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('registrar.dashboard') }}"
        class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ $currentRoute == 'registrar.dashboard' ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-house-door fs-6 {{ $currentRoute == 'registrar.dashboard' ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ $currentRoute == 'registrar.dashboard' ? 'text-white' : 'text-dark' }}">Home</span>
      </a>
    </div>

    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('dean.honorlist') }}"
        class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ $currentRoute == 'dean.honorlist' ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-person-check fs-6 {{ $currentRoute == 'dean.honorlist' ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ $currentRoute == 'dean.honorlist' ? 'text-white' : 'text-dark' }}">Dean's Honor List</span>
      </a>
    </div>

    <!-- Updated Section: Removed User Management and Maps Cards -->
    <!-- You can now re-add the logic for the routes you need if necessary, without the extra sections. -->

  </div>

  <!-- TOPBAR -->
  <div class="topbar-wrapper collapsed" id="topbarWrapper">
    <div class="topbar">
      <div class="search-wrapper text-black">
        <div class="search-box text-white">
          <i class="bi bi-search text-black"></i>
          <input type="text-white" placeholder="Search">
        </div>
      </div>
      <div class="icons">
        <i class="bi bi-bell text-white"></i>
        <i class="bi bi-files text-white"></i>
      </div>
      <div class="dropdown profile">
        <a href="#" class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="{{ asset('img/profile-placeholder.png') }}" alt="Profile" class="rounded-circle" width="36" height="36" style="cursor: pointer;">
          <div class="text-white">
            <span class="fw-semibold d-block text-white">
              Hi, {{ session('First_name') }} {{ session('Last_name') }}!
            </span>
            <small class="text-white d-block" style="font-size: 0.75rem; margin-left: 4.5rem;">
              ({{ ucfirst(session('usertype') ?? 'User') }})
            </small>
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end mt-2 shadow-sm" aria-labelledby="profileDropdown">
          <li class="px-3 py-2 text-muted small">WELCOME!</li>
          <li><a class="dropdown-item d-flex align-items-center gap-2" href="#"><i class="bi bi-person"></i> My profile</a></li>
          <li><a class="dropdown-item d-flex align-items-center gap-2" href="#"><i class="bi bi-gear"></i> Settings</a></li>
          <li><a class="dropdown-item d-flex align-items-center gap-2" href="#"><i class="bi bi-calendar-event"></i> Activity</a></li>
          <li><a class="dropdown-item d-flex align-items-center gap-2" href="#"><i class="bi bi-life-preserver"></i> Support</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="{{ route('logout') }}"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    @yield('content')
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- SIDEBAR TOGGLE SCRIPT -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const sidebar = document.getElementById('sidebar');
      const burgerToggle = document.getElementById('burgerToggle');
      const topbarWrapper = document.getElementById('topbarWrapper');
      const body = document.body;

      let stickOpen = false;

      function collapseSidebar() {
        sidebar.classList.add('collapsed');
        topbarWrapper.classList.add('collapsed');
        body.classList.add('sidebar-collapsed');
      }

      function expandSidebar() {
        sidebar.classList.remove('collapsed');
        topbarWrapper.classList.remove('collapsed');
        body.classList.remove('sidebar-collapsed');
      }

      // Start in collapsed mode
      collapseSidebar();

      // Hover: Expand/collapse on mouse enter/leave
      sidebar.addEventListener('mouseenter', () => {
        if (!stickOpen) expandSidebar();
      });

      sidebar.addEventListener('mouseleave', () => {
        if (!stickOpen) collapseSidebar();
      });

      // Burger toggle: Stick/unstick sidebar open
      burgerToggle.addEventListener('click', () => {
        stickOpen = !stickOpen;

        if (stickOpen) {
          expandSidebar();
        } else {
          collapseSidebar();
        }
      });
    });
  </script>

</body>
</html>
