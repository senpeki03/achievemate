@php
  $currentRoute = Route::currentRouteName();
  $campusRoutes = ['admin.campus', 'admin.college', 'admin.program', 'admin.major']; // include all children
  $isCampusActive = in_array($currentRoute, $campusRoutes);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Sidebar Layout</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="sidebar-collapsed">

  <!-- SIDEBAR -->
  <div class="sidebar collapsed" id="sidebar">
    <div class="sidebar-header sidebar-logo">
      <img src="{{ asset('img/achievemate.png') }}" alt="Logo">
      <i class="bi bi-list burger" id="burgerToggle"></i>
    </div>

    <div style="height: 40px;"></div>

    <div class="card shadow-sm border-0 m-2" id="campusDropdownCard">
      <a href="#campusSubmenu"
        class="card-body py-3 px-4 d-flex align-items-center justify-content-between nav-link text-decoration-none rounded-4 {{ $isCampusActive ? 'custom-bg-primary' : '' }}"
        data-bs-toggle="collapse"
        aria-expanded="{{ $isCampusActive ? 'true' : 'false' }}">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-building fs-6 {{ $isCampusActive ? 'text-white' : '' }}"></i>
          <span class="fw-semibold small ms-1 {{ $isCampusActive ? 'text-white' : 'text-dark' }}">Campus</span>
        </div>
        <span class="chevron-toggle">
          <i class="bi bi-chevron-down {{ $isCampusActive ? 'text-white' : 'text-muted' }}"></i>
        </span>
      </a>

      <!-- ✅ Submenu -->
      <div class="collapse submenu {{ $isCampusActive ? 'show' : '' }}" id="campusSubmenu" data-bs-parent="#sidebar">
        <ul class="list-unstyled ms-5 mb-0">
          <li>
            <a href="{{ route('admin.campus') }}"
              class="nav-link py-2 px-2 small {{ $currentRoute == 'admin.campus' ? 'text-primary fw-bold' : 'text-dark' }}">
              Campus
            </a>
          </li>
          <li>
            <a href="{{ route('admin.college') }}"
              class="nav-link py-2 px-2 small {{ $currentRoute == 'admin.college' ? 'text-primary fw-bold' : 'text-dark' }}">
              College
            </a>
          </li>
          <li>
            <a href="{{ route('admin.program') }}"
              class="nav-link py-2 px-2 small {{ $currentRoute == 'admin.program' ? 'text-primary fw-bold' : 'text-dark' }}">
              Program
            </a>
          </li>
          <li>
            <a href="{{ route('admin.major') }}"
              class="nav-link py-2 px-2 small {{ $currentRoute == 'admin.major' ? 'text-primary fw-bold' : 'text-dark' }}">
              Major
            </a>
          </li>
        </ul>
      </div>
    </div>

    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('admin.usermanage') }}"
        class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 
          {{ in_array($currentRoute, ['admin.usermanage', 'admin.userdesignation']) ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-person fs-6 
          {{ in_array($currentRoute, ['admin.usermanage', 'admin.userdesignation']) ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 
          {{ in_array($currentRoute, ['admin.usermanage', 'admin.userdesignation']) ? 'text-white' : 'text-dark' }}">
          User Management
        </span>
      </a>
    </div>

    <!-- Designation Card -->
    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('admin.designation') }}"
        class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ $currentRoute == 'admin.designation' ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-person fs-6 {{ $currentRoute == 'admin.designation' ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ $currentRoute == 'admin.designation' ? 'text-white' : 'text-dark' }}">Designation</span>
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
      const sidebar       = document.getElementById('sidebar');
      const burgerToggle  = document.getElementById('burgerToggle');
      const topbarWrapper = document.getElementById('topbarWrapper');
      const body          = document.body;
      const campusSubmenu = document.getElementById('campusSubmenu');

      const collapseCampus = campusSubmenu
        ? new bootstrap.Collapse(campusSubmenu, { toggle: false })
        : null;

      let stickOpen = false;

      function collapseSidebar() {
        sidebar.classList.add('collapsed');
        topbarWrapper.classList.add('collapsed');
        body.classList.add('sidebar-collapsed');
        if (collapseCampus) collapseCampus.hide(); // close submenu when sidebar is closed
      }

      function expandSidebar() {
        sidebar.classList.remove('collapsed');
        topbarWrapper.classList.remove('collapsed');
        body.classList.remove('sidebar-collapsed');
      }

      // 🔐 Remember state for Admin sidebar
      const savedState = localStorage.getItem('adminSidebarOpen'); // '1' = open, '0' = closed

      if (savedState === null || savedState === '1') {
        // Default: open on first load
        expandSidebar();
        stickOpen = true;
      } else {
        collapseSidebar();
        stickOpen = false;
      }

      // ❌ REMOVE hover behavior – no mouseenter/mouseleave
      // sidebar.addEventListener('mouseenter', ...)  // removed
      // sidebar.addEventListener('mouseleave', ...)  // removed

      // ✅ Burger toggle: Stick/unstick sidebar open, and save state
      burgerToggle.addEventListener('click', () => {
        stickOpen = !stickOpen;

        if (stickOpen) {
          expandSidebar();
          localStorage.setItem('adminSidebarOpen', '1');
        } else {
          collapseSidebar();
          localStorage.setItem('adminSidebarOpen', '0');
        }
      });
    });
  </script>

</body>
</html>
