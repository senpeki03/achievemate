@php
  $currentRoute = Route::currentRouteName();
@endphp

@php
  $activeRoutes = ['student.application', 'student.application.form', 'student.application.status'];
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"> 
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
      <a href="{{ route('student.dashboard') }}"
        class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ $currentRoute == 'student.dashboard' ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-house-door fs-6 {{ $currentRoute == 'student.dashboard' ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ $currentRoute == 'student.dashboard' ? 'text-white' : 'text-dark' }}">Home</span>
      </a>
    </div>

    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('student.portfolio') }}"
        class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ $currentRoute == 'student.portfolio' ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-person fs-6 {{ $currentRoute == 'student.portfolio' ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ $currentRoute == 'student.portfolio' ? 'text-white' : 'text-dark' }}">Portfolio</span>
      </a>
    </div>

    <!-- Application -->
    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('student.application') }}"
        class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4
          {{ in_array($currentRoute, $activeRoutes) ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-person-check fs-6 {{ in_array($currentRoute, $activeRoutes) ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ in_array($currentRoute, $activeRoutes) ? 'text-white' : 'text-dark' }}">
          Application
        </span>
      </a>
    </div>

    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('student.graduation.show') }}"
        class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4
                {{ request()->routeIs('student.graduation.*') ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-person fs-6 {{ request()->routeIs('student.graduation.*') ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ request()->routeIs('student.graduation.*') ? 'text-white' : 'text-dark' }}">
          Application for Grad
        </span>
      </a>
    </div>

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
        <div class="icons d-flex align-items-center gap-3">
          <!-- Link to Notifications Page -->
          <a href="{{ route('student.notifications') }}">
            <i class="bi bi-bell text-white position-relative">
              <!-- Display Unread Count if Greater Than 0 -->
              @if($unreadCount > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                  {{ $unreadCount }}
                </span>
              @endif
            </i>
          </a>

          <!-- File Icon -->
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
