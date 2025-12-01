<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>AchieveMate | Build a Career</title>

  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="icon" type="image/png" href="{{ asset('assets/icon.png') }}" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  {{-- Main landing CSS --}}
  <link rel="stylesheet" href="{{ asset('assets/style.css') }}" />

  {{-- Override footer background using Laravel asset() to avoid 404 --}}
  <style>
    .site-footer {
      background:
        linear-gradient(rgba(139, 0, 11, 0.80), rgba(139, 0, 11, 0.80)),
        url("{{ asset('assets/footer.png') }}") center/cover no-repeat;
      background-attachment: fixed;
    }

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
  </style>
</head>
<body class="landing-bg">

<!-- LOADING SCREEN -->
<div id="loader">
  <img src="https://d37oebn0w9ir6a.cloudfront.net/account_6827/customerio-loading-animation_244ab356f603e104472b77ceb1e5add4.gif" alt="Loading..." width="200">
</div>

<header class="site-header">
  <div class="container nav-container">
    <a href="#home" class="logo">
      <img src="{{ asset('assets/logo.png') }}" alt="Site Logo" />
    </a>
    <nav class="main-nav" aria-label="Main Navigation">
      <ul>
        <li><a href="#home" class="active">Home</a></li>
        <li><a href="#services">Our Services</a></li>
        <li><a href="#contact">Get In Touch</a></li>
        <li><a href="#leaderboards">Leader Boards</a></li>
      </ul>
    </nav>
    <div class="nav-cta">
      <a href="#" class="btn btn-solid" data-bs-toggle="modal" data-bs-target="#loginModal">Log In</a>
      <button class="nav-toggle" aria-label="Toggle navigation">☰</button>
    </div>
  </div>
</header>

<main id="home" class="hero">
  <div class="container hero-inner">
    <div class="hero-text">
      <h1>Go beyond <span class="accent">achievement</span> shelf.</h1>
      <p class="subtitle">Document milestones and inspire with a record that lasts.</p>

      <!-- CTA BUTTONS -->
        <div class="d-flex align-items-center gap-2 mt-2">
          <a href="#" class="btn btn-solid" data-bs-toggle="modal" data-bs-target="#loginModal">
            Get Started
          </a>

          <!-- DOWNLOAD BUTTON - SAME STYLE AS GET STARTED -->
          <a href="#" class="btn btn-solid">
            <i class="fa-solid fa-download me-1"></i> Download
          </a>
        </div>

    </div>
    <div class="hero-media">
      <!-- image removed as requested -->
    </div>
  </div>
</main>

<!-- LOGIN MODAL -->
<div class="modal fade" id="loginModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content p-0 border-0 rounded-4 overflow-hidden">
      <div class="row g-0">
        <div class="col-md-6 bg-white d-flex justify-content-center align-items-center py-4 px-3">
          <img src="{{ asset('assets/logo.png') }}" alt="AchieveMate Logo" style="width: 280px; height: auto;">
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

<section id="services" class="services">
  <div class="container">
    <h2 class="section-title">OUR <span class="accent">SERVICES</span></h2>
    <div class="services-grid">
      <article class="service-card">
        <div class="card-image">
          <img src="{{ asset('assets/1.png') }}" alt="Academic achievement" />
        </div>
        <h3>Academic Achievement Recognition</h3>
        <p>Certificates, awards, and milestones for honor roll and top performers.</p>
        <a href="#" class="card-link">Learn more</a>
      </article>
      <article class="service-card">
        <div class="card-image">
          <img src="{{ asset('assets/2.png') }}" alt="OCR grade extraction" />
        </div>
        <h3>OCR-Based Grade Extraction</h3>
        <p>Automated extraction of grades from scanned documents for fast records.</p>
        <a href="#" class="card-link">Learn more</a>
      </article>
      <article class="service-card">
        <div class="card-image">
          <img src="{{ asset('assets/3.png') }}" alt="Analytics dashboards" />
        </div>
        <h3>Analytics & Dashboards</h3>
        <p>Track trends, progress, and performance metrics with visual dashboards.</p>
        <a href="#" class="card-link">Learn more</a>
      </article>
      <article class="service-card">
        <div class="card-image">
          <img src="{{ asset('assets/4.png') }}" alt="Student portfolio" />
        </div>
        <h3>Student Achievement Portfolio</h3>
        <p>Personalized portfolio showcasing verified accomplishments.</p>
        <a href="#" class="card-link">Learn more</a>
      </article>
    </div>
  </div>
</section>

<!-- Section 3: Contact -->
<section id="contact" class="contact">
  <div class="container">
    <h2 class="section-title contact-title">GET IN <span class="accent">TOUCH</span></h2>
    <div class="contact-panel">
      <div class="panel-inner">
        <div class="panel-left">
          <h3 class="panel-title">Send Us a Message</h3>
          <p class="panel-subtitle">
            You can reach us by email. Fill out the form, and we'll get back to you as soon as possible.
          </p>
          <form class="contact-form" action="#" method="post" novalidate>
            <label for="fullName">Full name</label>
            <input type="text" id="fullName" name="fullName" placeholder="Your Full name" required />

            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="YourEmail@gmail.com" required />

            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" placeholder="Subject" />

            <label for="message">Message</label>
            <textarea id="message" name="message" rows="6" placeholder="Your Message"></textarea>

            <div class="send-row">
              <button type="submit" class="btn btn-solid">Send</button>
            </div>
          </form>
        </div>
        <div class="panel-right">
          <img src="{{ asset('assets/touch.png') }}" alt="Contact illustration" class="contact-illustration" />
        </div>
      </div>
    </div>
  </div>
</section>

<footer class="site-footer">
  <div class="container footer-top">
    <div class="footer-brand">
      <a href="#home" class="footer-logo" aria-label="Achievement Shelf Home">
        <img src="{{ asset('assets/logo_nontrans.png') }}" alt="Achievement Shelf Logo" />
      </a>
    </div>
    <nav class="footer-nav" aria-label="Footer Navigation">
      <ul>
        <li><a href="#home">Home</a></li>
        <li><a href="#services">Our Services</a></li>
        <li><a href="#contact">Get in Touch</a></li>
        <li><a href="#leaderboards">Leaderboard</a></li>
      </ul>
    </nav>
    <div class="footer-social" aria-label="Social Media">
      <ul>
        <li><a href="#" aria-label="Instagram" class="social-icon instagram" title="Instagram">&#x1F465;</a></li>
        <li><a href="#" aria-label="Facebook" class="social-icon facebook" title="Facebook">f</a></li>
        <li><a href="#" aria-label="X" class="social-icon x" title="X">X</a></li>
        <li><a href="#" aria-label="Website" class="social-icon link" title="Link">🔗</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-separator" aria-hidden="true"></div>
  <div class="container footer-bottom">
    <div class="footer-copy">&copy; {{ date('Y') }} Copy Right AchieveMate - Batangas State University TNEU</div>
    <div class="footer-privacy"><a href="#privacy">Privacy Policy</a></div>
    <div class="footer-terms"><a href="#terms">Terms of Service</a></div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Password toggle functionality
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
