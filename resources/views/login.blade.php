<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>AchieveMate | Build a Career</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap & FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; font-family: 'Segoe UI', sans-serif; }

    /* LOADER STYLES */
    #loader {
      position: fixed;
      z-index: 9999;
      top: 0; left: 0;
      width: 100vw; height: 100vh;
      background-color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      opacity: 1;
      transition: opacity 1s ease;  /* 1-second smooth fade-out */
    }
    #loader.fade-out {
      opacity: 0;
      pointer-events: none; /* Allow clicks immediately after fade */
    }



    .hero {
      min-height: 100vh;
      background: url("{{ asset('img/image.png') }}") no-repeat center center;
      background-size: cover;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 7rem 5% 2rem 5%;
      text-align: left;
    }

    .hero-text { max-width: 700px; color: #000; }
    .hero-text h1 { font-size: 3rem; font-weight: bold; }
    .hero-text p { font-size: 1.1rem; margin: 20px 0; }

    .hero-buttons .btn {
      border-radius: 30px;
      padding: 10px 25px;
      margin-right: 10px;
      margin-bottom: 10px;
    }

    .navbar { transition: background-color 0.3s ease; background-color: transparent; }
    .navbar-brand img { width: 150px; }
    .navbar .nav-link, .navbar .btn { color: #000; }
    .navbar .nav-link:hover, .navbar .nav-link.active { color: #007bff; }
    .navbar .btn.btn-primary { border-color: #000; color: #000; background-color: transparent; }
    .navbar .btn.btn-primary:hover { background-color: rgba(0, 0, 0, 0.1); }

    @media (max-width: 991.98px) {
      .navbar-collapse { background-color: transparent; padding: 1rem; }
      .navbar-collapse .nav-link, .navbar-collapse .btn { color: #000 !important; }
    }

    @media (max-width: 992px) { .hero-text h1 { font-size: 2.2rem; } }
    @media (max-width: 768px) {
      .hero-text { max-width: 100%; text-align: center; }
      .navbar-brand img { width: 120px; }
      .hero-buttons .btn { width: 100%; }
    }
  </style>
</head>
<body>

<!-- LOADING SCREEN -->
<div id="loader">
  <img src="https://d37oebn0w9ir6a.cloudfront.net/account_6827/customerio-loading-animation_244ab356f603e104472b77ceb1e5add4.gif" alt="Loading..." width="200">
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand" href="#">
      <img src="{{ asset('img/Achievemate02.png') }}" alt="logo">
    </a>
    <button class="navbar-toggler border border-black" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse text-center" id="navbarNavDropdown">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Work It</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Portfolio</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Tutorial</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Contact Us</a></li>
      </ul>
      <button class="btn btn-primary rounded-pill ms-lg-3 px-4" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero">
  <div class="hero-text">
    <h1>Go beyond <br><strong>achievement shelf.</strong></h1>
    <p>Document milestones and inspire with a record that lasts.</p>
    <div class="hero-buttons">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Get Started</button>
      <button class="btn btn-outline-secondary">Learn More</button>
    </div>
  </div>
</section>

<!-- LOGIN MODAL -->
<div class="modal fade" id="loginModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content p-0 border-0 rounded-4 overflow-hidden">
      <div class="row g-0">
        <div class="col-md-6 bg-white d-flex justify-content-center align-items-center py-4 px-3">
          <img src="{{ asset('img/Achievemate02.png') }}" alt="logo" style="width: 280px; height: auto;">
        </div>
        <div class="col-md-6 bg-white p-5 login-modal">
          <h4 class="fw-bold text-center mb-4">LOG IN</h4>
          <form action="{{ route('login.submit') }}" method="POST">
            @csrf
            <div class="mb-3">
              <input type="text" name="username" class="form-control border border-primary" placeholder="Email" required>
            </div>
            <div class="mb-3">
              <div class="input-group">
                <input type="password" name="password" id="password" class="form-control border border-primary" placeholder="Password" required>
                <span class="input-group-text bg-white border border-primary" id="togglePassword" style="cursor:pointer;">
                  <i class="fa-solid fa-eye"></i>
                </span>
              </div>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
              <label class="form-check-label" for="rememberMe">Remember me?</label>
            </div>
            <div class="d-grid mb-2">
              <button type="submit" class="btn btn-primary rounded-pill py-2">Log In</button>
            </div>
            <div class="text-end mb-2">
              <a href="#">Forgot Password?</a>
            </div>
            <div class="text-center">
              Need an account? <a href="#">SIGN UP</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap + JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const togglePassword = document.getElementById('togglePassword');
  const passwordInput = document.getElementById('password');
  if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', () => {
      const type = passwordInput.type === 'password' ? 'text' : 'password';
      passwordInput.type = type;
      const icon = togglePassword.querySelector('i');
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    });
  }

  window.addEventListener('load', () => {
    const loader = document.getElementById('loader');
    if (loader) {
      // Enforce a minimum 2 seconds before fade starts
      const minTime = 2000; // 2 seconds
      const start = performance.timing.navigationStart;
      const now = Date.now();
      const elapsed = now - start;
      const remaining = Math.max(minTime - elapsed, 0);

      setTimeout(() => {
        loader.classList.add('fade-out');
      }, remaining);
    }
  });
</script>

</body>
</html>
