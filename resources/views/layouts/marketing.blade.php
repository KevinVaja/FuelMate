<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
document.documentElement.classList.add('js');
document.addEventListener('DOMContentLoaded', function () {
  window.setTimeout(function () {
    document.body.classList.add('is-loaded');
  }, 240);
});
</script>
<title>@yield('title', 'FuelMate') - FuelMate</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="{{ asset('brand/fuelmate-mark.svg') }}">
<link rel="shortcut icon" href="{{ asset('brand/fuelmate-mark.svg') }}">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="{{ asset('css/fuelmate-base.css') }}" rel="stylesheet">
<link href="{{ asset('css/fuelmate-home.css') }}" rel="stylesheet">
<script src="{{ asset('js/fuelmate-ui.js') }}" defer></script>
@yield('head')
</head>
<body class="marketing-shell @yield('body_class')">
@include('partials.page-loader')
<nav class="navbar navbar-expand-lg marketing-nav sticky-top">
  <div class="container">
    <a class="navbar-brand" href="/"><img src="{{ asset('brand/fuelmate-logo.svg') }}" alt="FuelMate" class="navbar-brand-logo"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#marketingNav" aria-controls="marketingNav" aria-expanded="false" aria-label="Toggle navigation">
      <i class="fas fa-bars"></i>
    </button>
    <div class="collapse navbar-collapse" id="marketingNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 me-lg-4">
        <li class="nav-item"><a class="nav-link marketing-link" href="/#how-it-works">How It Works</a></li>
        <li class="nav-item"><a class="nav-link marketing-link" href="/#services">Services</a></li>
        <li class="nav-item"><a class="nav-link marketing-link" href="/#coverage">Coverage</a></li>
        <li class="nav-item"><a class="nav-link marketing-link" href="{{ route('contact.page') }}">Contact</a></li>
      </ul>
      <div class="marketing-actions">
        <a href="/login" class="btn btn-outline-light btn-sm">User Sign In</a>
        <a href="/agent-login" class="btn btn-outline-light btn-sm">Agent Sign In</a>
        <a href="/register" class="btn btn-primary btn-sm">Get Started</a>
      </div>
    </div>
  </div>
</nav>

<main class="marketing-main page-stage" id="sitePage">
  @yield('content')
</main>

<footer class="marketing-footer">
  <div class="container py-5">
    <div class="row g-4">
      <div class="col-md-4">
        <img src="{{ asset('brand/fuelmate-logo.svg') }}" alt="FuelMate" class="footer-brand-logo mb-3">
        <p class="text-muted">
          FuelMate helps stranded drivers, fleet operators, and field teams get petrol and diesel delivered directly
          to the exact place they need it.
        </p>
        <div class="marketing-footer__socials mt-3">
          <i class="fab fa-facebook-f"></i>
          <i class="fab fa-instagram"></i>
          <i class="fab fa-twitter"></i>
          <i class="fab fa-linkedin-in"></i>
        </div>
      </div>
      <div class="col-md-2">
        <h5 class="fw-bold mb-3">Explore</h5>
        <ul class="list-unstyled text-muted">
          <li class="mb-2"><a href="/" class="text-decoration-none text-muted">Home</a></li>
          <li class="mb-2"><a href="{{ route('petrol.page') }}" class="text-decoration-none text-muted">Petrol Delivery</a></li>
          <li class="mb-2"><a href="{{ route('diesel.page') }}" class="text-decoration-none text-muted">Diesel Delivery</a></li>
          <li class="mb-2"><a href="{{ route('availability.page') }}" class="text-decoration-none text-muted">24x7 Availability</a></li>
        </ul>
      </div>
      <div class="col-md-3">
        <h5 class="fw-bold mb-3">Use FuelMate</h5>
        <ul class="list-unstyled text-muted">
          <li class="mb-2"><a href="/register" class="text-decoration-none text-muted">Create Account</a></li>
          <li class="mb-2"><a href="/login" class="text-decoration-none text-muted">Customer Sign In</a></li>
          <li class="mb-2"><a href="/agent-login" class="text-decoration-none text-muted">Agent Sign In</a></li>
          <li class="mb-2"><a href="{{ route('emergency.page') }}" class="text-decoration-none text-muted">Emergency Support</a></li>
        </ul>
      </div>
      <div class="col-md-3">
        <h5 class="fw-bold mb-3">Contact</h5>
        <p class="text-muted mb-2"><i class="fas fa-location-dot me-2"></i>Ahmedabad, Gujarat</p>
        <p class="text-muted mb-2"><i class="fas fa-phone me-2"></i>+91 9999999999</p>
        <p class="text-muted mb-2"><i class="fas fa-envelope me-2"></i>support@fuelmate.com</p>
      </div>
    </div>
  </div>
  <div class="text-center py-3 marketing-footer-bottom">
    &copy; {{ date('Y') }} FuelMate. All rights reserved.
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
