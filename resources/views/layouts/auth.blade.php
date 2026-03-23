<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>
document.documentElement.classList.add('js');
document.addEventListener('DOMContentLoaded', function () {
  window.setTimeout(function () {
    document.body.classList.add('is-loaded');
  }, 240);
});
</script>
<title>@yield('title') - FuelMate</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="{{ asset('brand/fuelmate-mark.svg') }}">
<link rel="shortcut icon" href="{{ asset('brand/fuelmate-mark.svg') }}">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="{{ asset('css/fuelmate-base.css') }}" rel="stylesheet">
<link href="{{ asset('css/fuelmate-auth.css') }}" rel="stylesheet">
<script src="{{ asset('js/fuelmate-ui.js') }}" defer></script>
</head>
<body class="auth-shell">
@include('partials.page-loader')
<div class="auth-stage page-stage" id="sitePage">
  <section class="auth-spotlight" data-reveal="right">
    <div class="auth-spotlight__eyebrow">FuelMate Network</div>
    <h1 class="auth-spotlight__title">Dispatch, track, and recover <span>with calm control</span>.</h1>
    <p class="auth-spotlight__copy">
      FuelMate keeps customers, agents, and admins aligned during emergency fuel deliveries with live updates,
      verified workflows, and a cleaner workspace.
    </p>
    <div class="auth-spotlight__grid">
      <div class="auth-spotlight__metric"><strong>24/7</strong><span>delivery coverage</span></div>
      <div class="auth-spotlight__metric"><strong>Live</strong><span>tracking visibility</span></div>
      <div class="auth-spotlight__metric"><strong>Fast</strong><span>role-based access</span></div>
    </div>
  </section>
  <div class="auth-container" data-reveal="left">
    @yield('content')
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
