@php
  // Current route
  $currentRoute = Route::currentRouteName();

  // Route groups per dropdown
  $announcementRoutes = ['programchair.post.index', 'admin.college', 'admin.program'];
  $settingsRoutes     = ['programchair.rank'];

  // Active flags (per dropdown)
  $isAnnouncementActive = in_array($currentRoute, $announcementRoutes, true);
  $isSettingsActive     = in_array($currentRoute, $settingsRoutes, true);

  // Other flags
  $hasAy = \App\Models\CurriculumAy::query()->exists();
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
  <style>
    /* === Defensive rules to keep collapsed sidebar tidy === */
    .sidebar.collapsed .submenu { display: none !important; }
    .submenu .nav-link { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* Optional: ensure icons center in collapsed state */
    .sidebar.collapsed .card .nav-link span { display: none; } /* hide text labels when collapsed */
  </style>
</head>
<body class="sidebar-collapsed">

  <!-- SIDEBAR -->
  <div class="sidebar collapsed" id="sidebar">
    <div class="sidebar-header sidebar-logo">
      <img src="{{ asset('img/AchieveMate02.png') }}" alt="Logo">
      <i class="bi bi-list burger" id="burgerToggle" role="button" aria-label="Toggle sidebar"></i>
    </div>

    <div style="height: 40px;"></div>

    <!-- Home -->
    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('programchair.dashboard') }}"
         class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ $currentRoute === 'programchair.dashboard' ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-house-door fs-6 {{ $currentRoute === 'programchair.dashboard' ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ $currentRoute === 'programchair.dashboard' ? 'text-white' : 'text-dark' }}">Home</span>
      </a>
    </div>

    <!-- Dean's Honor List (single) -->
    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('programchair.deanshonorlist') }}"
         class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ $currentRoute === 'programchair.deanshonorlist' ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-person-check fs-6 {{ $currentRoute === 'programchair.deanshonorlist' ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ $currentRoute === 'programchair.deanshonorlist' ? 'text-white' : 'text-dark' }}">Dean's Honor List</span>
      </a>
    </div>

    <!-- Announcement dropdown -->
    <div class="card shadow-sm border-0 m-2" id="announcementDropdownCard">
      <a
        class="card-body py-3 px-4 d-flex align-items-center justify-content-between nav-link text-decoration-none rounded-4 {{ $isAnnouncementActive ? 'custom-bg-primary' : '' }}"
        data-bs-toggle="collapse"
        data-bs-target="#announcementSubmenu"
        role="button"
        aria-expanded="{{ $isAnnouncementActive ? 'true' : 'false' }}"
        aria-controls="announcementSubmenu"
      >
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-megaphone-fill fs-6 {{ $isAnnouncementActive ? 'text-white' : '' }}"></i>
          <span class="fw-semibold small ms-1 {{ $isAnnouncementActive ? 'text-white' : 'text-dark' }}">Announcement</span>
        </div>
        <span class="chevron-toggle">
          <i class="bi bi-chevron-down {{ $isAnnouncementActive ? 'text-white' : 'text-muted' }}"></i>
        </span>
      </a>

      <div class="collapse submenu {{ $isAnnouncementActive ? 'show' : '' }}" id="announcementSubmenu" data-bs-parent="#sidebar">
        <ul class="list-unstyled ms-5 mb-0">
          <li>
            <a href="{{ route('programchair.post.index') }}"
               class="nav-link py-2 px-2 small {{ $currentRoute === 'programchair.post.index' ? 'text-primary fw-bold' : 'text-dark' }}">
              Dean's Honor List
            </a>
          </li>
          <li>
            <a href="{{ route('admin.college') }}"
               class="nav-link py-2 px-2 small {{ $currentRoute === 'admin.college' ? 'text-primary fw-bold' : 'text-dark' }}">
              Competition
            </a>
          </li>
          <li>
            <a href="{{ route('admin.program') }}"
               class="nav-link py-2 px-2 small {{ $currentRoute === 'admin.program' ? 'text-primary fw-bold' : 'text-dark' }}">
              Certification
            </a>
          </li>
        </ul>
      </div>
    </div>

    <!-- Curriculum (single) -->
    <div class="card shadow-sm border-0 m-2">
      <a href="{{ $hasAy ? route('programchair.curriculumupload') : route('programchair.curriculum') }}"
         class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4
                {{ in_array($currentRoute, ['programchair.curriculum','programchair.curriculumupload'], true) ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-journal-text fs-6 {{ in_array($currentRoute, ['programchair.curriculum','programchair.curriculumupload'], true) ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ in_array($currentRoute, ['programchair.curriculum','programchair.curriculumupload'], true) ? 'text-white' : 'text-dark' }}">
          Curriculum
        </span>
      </a>
    </div>

    <!-- Settings dropdown -->
    <div class="card shadow-sm border-0 m-2" id="settingsDropdownCard">
      <a
        class="card-body py-3 px-4 d-flex align-items-center justify-content-between nav-link text-decoration-none rounded-4 {{ $isSettingsActive ? 'custom-bg-primary' : '' }}"
        data-bs-toggle="collapse"
        data-bs-target="#settingsSubmenu"
        role="button"
        aria-expanded="{{ $isSettingsActive ? 'true' : 'false' }}"
        aria-controls="settingsSubmenu"
      >
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-gear-fill fs-6 {{ $isSettingsActive ? 'text-white' : '' }}"></i>
          <span class="fw-semibold small ms-1 {{ $isSettingsActive ? 'text-white' : 'text-dark' }}">Settings</span>
        </div>
        <span class="chevron-toggle">
          <i class="bi bi-chevron-down {{ $isSettingsActive ? 'text-white' : 'text-muted' }}"></i>
        </span>
      </a>

      <div class="collapse submenu {{ $isSettingsActive ? 'show' : '' }}" id="settingsSubmenu" data-bs-parent="#sidebar">
        <ul class="list-unstyled ms-5 mb-0">
          <li>
            <a href="{{ route('programchair.rank') }}"
               class="nav-link py-2 px-2 small {{ $currentRoute === 'programchair.rank' ? 'text-primary fw-bold' : 'text-dark' }}">
              Creation Rank
            </a>
          </li>
        </ul>
      </div>
    </div>

  </div>

  <!-- TOPBAR -->
  <div class="topbar-wrapper collapsed" id="topbarWrapper">
    <div class="topbar">
      <div class="search-wrapper text-black">
        <div class="search-box text-white">
          <i class="bi bi-search text-black"></i>
          <input type="text" class="text-white" placeholder="Search">
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

      // Collect all submenus inside the sidebar and init Bootstrap Collapse without auto-toggle
      const submenuEls = Array.from(sidebar.querySelectorAll('.submenu'));
      const collapses = submenuEls.map(el => new bootstrap.Collapse(el, { toggle: false }));

      let stickOpen = false;

      function collapseAllSubmenus() {
        collapses.forEach(c => c.hide());
      }

      function collapseSidebar() {
        sidebar.classList.add('collapsed');
        topbarWrapper.classList.add('collapsed');
        body.classList.add('sidebar-collapsed');
        // ✅ Auto-close submenus whenever the sidebar collapses
        collapseAllSubmenus();
      }

      function expandSidebar() {
        sidebar.classList.remove('collapsed');
        topbarWrapper.classList.remove('collapsed');
        body.classList.remove('sidebar-collapsed');
      }

      // Start collapsed and with submenus closed
      collapseSidebar();

      // Hover behavior when not sticky
      sidebar.addEventListener('mouseenter', () => {
        if (!stickOpen) expandSidebar();
      });
      sidebar.addEventListener('mouseleave', () => {
        if (!stickOpen) collapseSidebar(); // closes submenus too
      });

      // Burger stick/unstick toggle
      burgerToggle.addEventListener('click', () => {
        stickOpen = !stickOpen;
        if (stickOpen) {
          expandSidebar();
        } else {
          collapseSidebar(); // will also close submenus
        }
      });

      // Accordion behavior: only one submenu open at a time
      sidebar.addEventListener('click', (e) => {
        const toggler = e.target.closest('[data-bs-toggle="collapse"][data-bs-target]');
        if (!toggler) return;

        const targetSel = toggler.getAttribute('data-bs-target');
        const target = document.querySelector(targetSel);
        if (!target) return;

        submenuEls.forEach(el => {
          if (el !== target) bootstrap.Collapse.getInstance(el)?.hide();
        });
      });
    });
  </script>

</body>
</html>
