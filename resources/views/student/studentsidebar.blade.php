@php
  use Illuminate\Support\Facades\Route;
  use App\Models\Application;
  use App\Models\StudentGrade;
  use App\Models\GraduationForm;
  use App\Models\GraduationRequirement;
  use App\Models\Post; // ⬅️ idagdag ito

  $currentRoute = Route::currentRouteName();
  $studentId = session('Student_id');

  $hasApplications = $studentId ? Application::where('Student_id', $studentId)->exists() : false;
  $hasUploadedGrades = $studentId ? StudentGrade::where('Student_id', $studentId)->exists() : false;

  $hasGraduationApplication = $studentId ? GraduationForm::where('Student_id', $studentId)->exists() : false;

  $hasGraduationRequirements = false;
  if ($studentId && $hasGraduationApplication) {
      $graduationForm = GraduationForm::where('Student_id', $studentId)->first();
      if ($graduationForm) {
          $hasGraduationRequirements = GraduationRequirement::where('GraduationForm_id', $graduationForm->GraduationForm_id)->exists();
      }
  }

  // 🔽 DEPENDE SA LOGIC MO KUNG PANO MO I-FI-FILTER YUNG POST
  // example lang: kunin yung latest Dean’s Honor post for the student
  $deansPost = Post::where('Title', 'LIKE', '%Dean%Honor%')
      ->orderByDesc('Post_id')
      ->first();

  // true kung may post at active pa sya (Start_date/End_date ok pa)
  $canApplyDeansHonor = $deansPost && $deansPost->is_active;

  $applicationPatterns = [
    'student.application', 'student.application.*',
    'student.application.status', 'student.application.status.*',
    'student.graduation',  'student.graduation.*',
    'student.graduation.status', 'student.graduation.status.*',
    'student.latin',       'student.latin.*',
  ];
  $isApplicationActive = request()->routeIs($applicationPatterns);

  $gradePatterns = [
    'student.grades.*',
    'student.studentgrade',
    'student.viewgrade'
  ];
  $isGradeActive = request()->routeIs($gradePatterns);

  $unreadCount     = $unreadCount ?? 0;
  $profilePhotoUrl = route('student.profile.photo', ['_v' => time()]);
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

  <style>
    /* Submenu look/feel */
    #appMenu a.bg-light { color:#0d6efd; }
    .nav-link.rounded-4.custom-bg-primary { background:#7b0f12; }
    .sidebar .collapse ul li a { transition: background .2s; }
    /* Make sure nested content is hidden when sidebar is collapsed */
    .sidebar.collapsed #appMenu { display: none !important; }
  </style>
</head>
{{-- ✅ remove sidebar-collapsed so layout starts open --}}
<body>

  <!-- SIDEBAR -->
  {{-- ✅ remove "collapsed" so sidebar is steady open by default --}}
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header sidebar-logo">
      <img src="{{ asset('img/achievemate.png') }}" alt="Logo">
      <i class="bi bi-list burger" id="burgerToggle"></i>
    </div>

    <div style="height: 40px;"></div>

    <!-- Home -->
    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('student.dashboard') }}"
         class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ request()->routeIs('student.dashboard') ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-house-door fs-6 {{ request()->routeIs('student.dashboard') ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ request()->routeIs('student.dashboard') ? 'text-white' : 'text-dark' }}">Home</span>
      </a>
    </div>

    <!-- Portfolio -->
    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('student.portfolio') }}"
         class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ request()->routeIs('student.portfolio') ? 'custom-bg-primary' : '' }}">
        <i class="bi bi-person fs-6 {{ request()->routeIs('student.portfolio') ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ request()->routeIs('student.portfolio') ? 'text-white' : 'text-dark' }}">Portfolio</span>
      </a>
    </div>

    <!-- Application (Dropdown) -->
    <div class="card shadow-sm border-0 m-2">
      <button
        id="appParentBtn"
        class="card-body w-100 text-start py-3 px-4 d-flex align-items-center gap-3 nav-link text-decoration-none rounded-4 border-0 bg-transparent {{ $isApplicationActive ? 'custom-bg-primary' : '' }}"
        data-bs-toggle="collapse"
        data-bs-target="#appMenu"
        aria-expanded="{{ $isApplicationActive ? 'true' : 'false' }}"
        aria-controls="appMenu"
        type="button">
        <i class="bi bi-person-check fs-6 {{ $isApplicationActive ? 'text-white' : '' }}"></i>
        <span class="fw-semibold small ms-1 {{ $isApplicationActive ? 'text-white' : 'text-dark' }}">Application</span>
      </button>

      <div id="appMenu" class="collapse {{ $isApplicationActive ? 'show' : '' }}">
        <ul class="list-unstyled my-2 ms-4">
          <li class="mb-1">
            @if($hasApplications)
              {{-- ✅ May existing application → View Status --}}
              <a href="{{ route('student.application.status') }}"
                class="app-sub-link d-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none {{ request()->routeIs('student.application.status*') ? 'bg-light fw-semibold' : '' }}">
                <i class="bi bi-award"></i>
                <span class="small">Dean's Honor</span>
                <span class="badge bg-success ms-1">View Status</span>
              </a>

            @elseif($canApplyDeansHonor)
              {{-- ✅ Walang application, pero active pa ang Post → Apply Now --}}
              <a href="{{ route('student.application') }}"
                class="app-sub-link d-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none {{ request()->routeIs('student.application*') ? 'bg-light fw-semibold' : '' }}">
                <i class="bi bi-award"></i>
                <span class="small">Dean's Honor</span>
                <span class="badge bg-primary ms-1">Apply Now</span>
              </a>

            @else
              {{-- ❌ Walang application + closed na (End_date done) --}}
              <button type="button"
                      class="app-sub-link d-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none bg-light text-muted w-100"
                      data-bs-toggle="modal"
                      data-bs-target="#deansClosedModal">
                <i class="bi bi-award"></i>
                <span class="small">Dean's Honor</span>
                <span class="badge bg-secondary ms-1">Closed</span>
              </button>
            @endif
          </li>

          <li class="mb-1">
            @if($hasGraduationApplication && $hasGraduationRequirements)
              <a href="{{ route('student.graduation.status') }}"
                class="app-sub-link d-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none {{ request()->routeIs('student.graduation.status*') ? 'bg-light fw-semibold' : '' }}">
                <i class="bi bi-mortarboard"></i>
                <span class="small">Graduation</span>
                <span class="badge bg-success ms-1">View Status</span>
              </a>
            @elseif($hasGraduationApplication)
              <a href="{{ route('student.graduation.show') }}"
                class="app-sub-link d-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none {{ request()->routeIs(['student.graduation','student.graduation.*']) ? 'bg-light fw-semibold' : '' }}">
                <i class="bi bi-mortarboard"></i>
                <span class="small">Graduation</span>
                <span class="badge bg-warning ms-1">Complete Requirements</span>
              </a>
            @else
              <a href="{{ route('student.graduation.show') }}"
                class="app-sub-link d-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none {{ request()->routeIs(['student.graduation','student.graduation.*']) ? 'bg-light fw-semibold' : '' }}">
                <i class="bi bi-mortarboard"></i>
                <span class="small">Graduation</span>
                <span class="badge bg-primary ms-1">Apply Now</span>
              </a>
            @endif
          </li>
          <li class="mb-1">
            <a href="{{ route('student.latin') }}"
              class="app-sub-link d-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none {{ request()->routeIs(['student.latin','student.latin.*']) ? 'bg-light fw-semibold' : '' }}">
              <i class="bi bi-stars"></i>
              <span class="small">Latin Honors</span>
            </a>
          </li>
        </ul>
      </div>
    </div>

    <!-- Upload Grade / View Grades (Dynamic) -->
    <div class="card shadow-sm border-0 m-2">
      @if($hasUploadedGrades)
        <a href="{{ route('student.grades.view') }}"
           class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ $isGradeActive ? 'custom-bg-primary' : '' }}">
          <i class="fas fa-chart-bar fs-6 {{ $isGradeActive ? 'text-white' : '' }}"></i>
          <span class="fw-semibold small ms-1 {{ $isGradeActive ? 'text-white' : 'text-dark' }}">View Grades</span>
          <span class="badge bg-success ms-auto">Uploaded</span>
        </a>
      @else
        <a href="{{ route('student.studentgrade') }}"
           class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 {{ $isGradeActive ? 'custom-bg-primary' : '' }}">
          <i class="fas fa-upload fs-6 {{ $isGradeActive ? 'text-white' : '' }}"></i>
          <span class="fw-semibold small ms-1 {{ $isGradeActive ? 'text-white' : 'text-dark' }}">Upload Grades</span>
          <span class="badge bg-primary ms-auto">New</span>
        </a>
      @endif
    </div>

    <!-- Event Invite -->
    <div class="card shadow-sm border-0 m-2">
      <a href="{{ route('student.event.invite') }}"
        class="card-body py-3 px-4 d-flex align-items-center gap-4 nav-link text-decoration-none rounded-4 
        {{ request()->routeIs('student.event.invite') ? 'custom-bg-primary' : '' }}">
        
        <i class="bi bi-calendar-event fs-6 
          {{ request()->routeIs('student.event.invite') ? 'text-white' : '' }}"></i>

        <span class="fw-semibold small ms-1 
          {{ request()->routeIs('student.event.invite') ? 'text-white' : 'text-dark' }}">
          Event Invite
        </span>

      </a>
    </div>

  </div>

  <!-- TOPBAR -->
  {{-- ✅ remove "collapsed" so topbar aligns with open sidebar --}}
  <div class="topbar-wrapper" id="topbarWrapper">
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
            @if($unreadCount > 0)
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                {{ $unreadCount }}
              </span>
            @endif
          </i>
        </a>
        <i class="bi bi-files text-white"></i>
      </div>

      <div class="dropdown profile">
        <a href="#" class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <img
            src="{{ $profilePhotoUrl }}"
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
          <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('student.profile') }}"><i class="bi bi-person"></i> My profile</a></li>
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
    // Pass active state from PHP to JS
    const IS_APP_ACTIVE = @json($isApplicationActive);

    document.addEventListener('DOMContentLoaded', function () {
      const sidebar       = document.getElementById('sidebar');
      const burgerToggle  = document.getElementById('burgerToggle');
      const topbarWrapper = document.getElementById('topbarWrapper');
      const body          = document.body;

      const appMenu      = document.getElementById('appMenu');
      const appParentBtn = document.getElementById('appParentBtn');

      // Bootstrap collapse instance for submenu
      const appCollapse = appMenu ? bootstrap.Collapse.getOrCreateInstance(appMenu, { toggle:false }) : null;

      let stickOpen = false;

      function collapseSidebar() {
        // Close sidebar UI
        sidebar.classList.add('collapsed');
        topbarWrapper.classList.add('collapsed');
        body.classList.add('sidebar-collapsed');

        // Force-close the submenu when sidebar collapses
        if (appCollapse) appCollapse.hide();
      }

      function expandSidebar() {
        // Open sidebar UI
        sidebar.classList.remove('collapsed');
        topbarWrapper.classList.remove('collapsed');
        body.classList.remove('sidebar-collapsed');

        // Auto-open submenu only if on any Application route
        if (appCollapse && IS_APP_ACTIVE) appCollapse.show();
      }

      // ✅ SIDEBAR STATE PERSISTENCE (localStorage)
      const savedState = localStorage.getItem('studentSidebarOpen'); // '1' or '0'
      if (savedState === null || savedState === '1') {
          // default: OPEN
          expandSidebar();
          stickOpen = true;
      } else {
          collapseSidebar();
          stickOpen = false;
      }

      // ❌ REMOVE HOVER OPEN/CLOSE — wala nang mouseenter/mouseleave

      // ✅ BURGER BUTTON TOGGLES + SAVE STATE
      burgerToggle.addEventListener('click', () => {
        stickOpen = !stickOpen;
        if (stickOpen) {
          expandSidebar();
          localStorage.setItem('studentSidebarOpen', '1'); // remember OPEN
        } else {
          collapseSidebar();
          localStorage.setItem('studentSidebarOpen', '0'); // remember CLOSED
        }
      });

      // Chevron icon feedback for submenu
      if (appMenu && appParentBtn) {
        appMenu.addEventListener('shown.bs.collapse', () => {
          const icon = appParentBtn.querySelector('i.bi:last-child');
          if (icon) icon.classList.replace('bi-chevron-down', 'bi-chevron-up');
        });
        appMenu.addEventListener('hidden.bs.collapse', () => {
          const icon = appParentBtn.querySelector('i.bi:last-child');
          if (icon) icon.classList.replace('bi-chevron-up', 'bi-chevron-down');
        });

        // OPTIONAL: auto-close submenu when clicking a child,
        // pero HINDI nito gagalawin ang sidebar (submenu lang)
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


<div class="modal fade" id="deansClosedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title">Dean's Honor Application</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        The application period for <strong>Dean's Honor</strong> is already closed.
        Please wait for the next announcement.
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>



</body>
</html>
