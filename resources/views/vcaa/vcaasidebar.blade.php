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
  
  <style>
    /* Submenu look/feel */
    #appMenu a.bg-light { color:#0d6efd; }
    .nav-link.rounded-4.custom-bg-primary { background:#7b0f12; }
    .sidebar .collapse ul li a { transition: background .2s; }
    /* Make sure nested content is hidden when sidebar is collapsed */
    .sidebar.collapsed #appMenu { display: none !important; }
  </style>
</head>
<body class="sidebar-collapsed">

  @php
    // avoid "undefined variable" notices
    $unreadCountSafe = $unreadCount ?? 0;
  @endphp

  <!-- SIDEBAR -->
  <div class="sidebar collapsed" id="sidebar">
    <div class="sidebar-header sidebar-logo">
      <img src="{{ asset('img/achievemate.png') }}" alt="Logo">
      <i class="bi bi-list burger" id="burgerToggle"></i>
    </div>

    <div style="height: 40px;"></div>

    <!-- Home -->
    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('vcaa.dashboard') }}"
         class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ request()->routeIs('vcaa.dashboard') ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-house-door fs-6 {{ request()->routeIs('vcaa.dashboard') ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ request()->routeIs('vcaa.dashboard') ? 'text-white' : 'text-dark' }}">Home</span>
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
        <a href="{{ route('student.notifications') }}">
          <i class="bi bi-bell text-white position-relative">
            @if($unreadCountSafe > 0)
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                {{ $unreadCountSafe }}
              </span>
            @endif
          </i>
        </a>
        <i class="bi bi-files text-white"></i>
      </div>

      <div class="dropdown profile">
        <a href="#" class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <img
            src="#"
            onerror="this.onerror=null;this.src='{{ asset('img/profile-placeholder.png') }}';"
            alt="Profile"
            class="rounded-circle"
            width="36" height="36"
            style="object-fit:cover;cursor:pointer;">
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

  <script>
    // For compatibility; may be unused here but kept to avoid errors
    const IS_APP_ACTIVE = @json($isApplicationActive ?? false);

    document.addEventListener('DOMContentLoaded', function () {
      const sidebar       = document.getElementById('sidebar');
      const burgerToggle  = document.getElementById('burgerToggle');
      const topbarWrapper = document.getElementById('topbarWrapper');
      const body          = document.body;

      const appMenu      = document.getElementById('appMenu');
      const appParentBtn = document.getElementById('appParentBtn');

      const appCollapse = appMenu
          ? bootstrap.Collapse.getOrCreateInstance(appMenu, { toggle:false })
          : null;

      let stickOpen = false;

      function collapseSidebar() {
        sidebar.classList.add('collapsed');
        topbarWrapper.classList.add('collapsed');
        body.classList.add('sidebar-collapsed');
        if (appCollapse) appCollapse.hide();
      }

      function expandSidebar() {
        sidebar.classList.remove('collapsed');
        topbarWrapper.classList.remove('collapsed');
        body.classList.remove('sidebar-collapsed');
        if (appCollapse && IS_APP_ACTIVE) appCollapse.show();
      }

      // 🔐 Remember state per user (VCAA)
      const savedState = localStorage.getItem('vcaaSidebarOpen'); // '1' = open, '0' = closed

      if (savedState === null || savedState === '1') {
        // Default: OPEN if first time or saved as open
        expandSidebar();
        stickOpen = true;
      } else {
        collapseSidebar();
        stickOpen = false;
      }

      // ❌ No more hover open/close
      // (removed mouseenter / mouseleave listeners)

      // ✅ Burger button controls open/close & saves state
      burgerToggle.addEventListener('click', () => {
        stickOpen = !stickOpen;
        if (stickOpen) {
          expandSidebar();
          localStorage.setItem('vcaaSidebarOpen', '1');
        } else {
          collapseSidebar();
          localStorage.setItem('vcaaSidebarOpen', '0');
        }
      });

      // Submenu logic kept generic; safe even if there's no submenu
      if (appMenu && appParentBtn) {
        appMenu.addEventListener('shown.bs.collapse', () => {
          const icon = appParentBtn.querySelector('i.bi:last-child');
          if (icon) icon.classList.replace('bi-chevron-down', 'bi-chevron-up');
        });

        appMenu.addEventListener('hidden.bs.collapse', () => {
          const icon = appParentBtn.querySelector('i.bi:last-child');
          if (icon) icon.classList.replace('bi-chevron-up', 'bi-chevron-down');
        });

        document.querySelectorAll('.app-sub-link').forEach(a => {
          a.addEventListener('click', () => {
            if (!stickOpen && appCollapse) {
              appCollapse.hide();
            }
          });
        });
      }
    });
  </script>
</body>
</html>
