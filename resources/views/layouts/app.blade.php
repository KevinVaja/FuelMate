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
<link href="{{ asset('css/fuelmate-app.css') }}" rel="stylesheet">
<script src="{{ asset('js/fuelmate-ui.js') }}" defer></script>
<script src="{{ asset('js/fuelmate-gps.js') }}" defer></script>
@yield('head')
</head>
<body class="app-shell">
@include('partials.page-loader')
<div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="{{ asset('brand/fuelmate-logo.svg') }}" alt="FuelMate" class="sidebar-logo">
  </div>
  <nav class="sidebar-nav">
    <ul class="nav flex-column">
      @auth
        @if(auth()->user()->role === 'user')
          <li class="nav-item"><a href="{{ route('user.dashboard') }}" class="nav-link {{ request()->is('user/dashboard') ? 'active' : '' }}"><i class="fas fa-gauge-high"></i> Dashboard</a></li>
          <li class="nav-item"><a href="{{ route('user.order') }}" class="nav-link {{ request()->is('user/order') ? 'active' : '' }}"><i class="fas fa-plus-circle"></i> Order Fuel</a></li>
          <li class="nav-item"><a href="{{ route('user.history') }}" class="nav-link {{ request()->is('user/history') ? 'active' : '' }}"><i class="fas fa-clock-rotate-left"></i> Order History</a></li>
          <li class="nav-item"><a href="{{ route('user.support') }}" class="nav-link {{ request()->is('user/support') ? 'active' : '' }}"><i class="fas fa-headset"></i> Support</a></li>
        @elseif(auth()->user()->role === 'agent')
          <li class="nav-item"><a href="{{ route('agent.dashboard') }}" class="nav-link {{ request()->is('agent/dashboard') ? 'active' : '' }}"><i class="fas fa-gauge-high"></i> Dashboard</a></li>
          <li class="nav-item"><a href="{{ route('agent.requests') }}" class="nav-link {{ request()->is('agent/requests') ? 'active' : '' }}"><i class="fas fa-map-marker-alt"></i> Available Requests</a></li>
          <li class="nav-item"><a href="{{ route('agent.active') }}" class="nav-link {{ request()->is('agent/active') ? 'active' : '' }}"><i class="fas fa-truck"></i> Active Delivery</a></li>
          <li class="nav-item"><a href="{{ route('agent.earnings') }}" class="nav-link {{ request()->is('agent/earnings') ? 'active' : '' }}"><i class="fas fa-dollar-sign"></i> Earnings</a></li>
          <li class="nav-item"><a href="{{ route('agent.withdrawals.index') }}" class="nav-link {{ request()->is('agent/withdrawals*') ? 'active' : '' }}"><i class="fas fa-money-bill-transfer"></i> Withdrawals</a></li>
          <li class="nav-item"><a href="{{ route('agent.history') }}" class="nav-link {{ request()->is('agent/history') ? 'active' : '' }}"><i class="fas fa-history"></i> History</a></li>
        @else
          <li class="nav-item"><a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->is('admin/dashboard') ? 'active' : '' }}"><i class="fas fa-chart-line"></i> Analytics</a></li>
          <li class="nav-item"><a href="{{ route('admin.orders') }}" class="nav-link {{ request()->is('admin/orders') ? 'active' : '' }}"><i class="fas fa-box"></i> All Orders</a></li>
          <li class="nav-item"><a href="{{ route('admin.users') }}" class="nav-link {{ request()->is('admin/users') ? 'active' : '' }}"><i class="fas fa-users"></i> Users</a></li>
          <li class="nav-item"><a href="{{ route('admin.agents.pending') }}" class="nav-link {{ request()->is('admin/agents/pending') || request()->is('admin/agents/*/verify') ? 'active' : '' }}"><i class="fas fa-file-shield"></i> Pump Verification</a></li>
          <li class="nav-item"><a href="{{ route('admin.agent_coverage') }}" class="nav-link {{ request()->is('admin/agent-coverage*') ? 'active' : '' }}"><i class="fas fa-map-location-dot"></i> Coverage Monitor</a></li>
          <li class="nav-item"><a href="{{ route('admin.products') }}" class="nav-link {{ request()->is('admin/products') ? 'active' : '' }}"><i class="fas fa-gas-pump"></i> Fuel Products</a></li>
          <li class="nav-item"><a href="{{ route('admin.delivery_charges') }}" class="nav-link {{ request()->is('admin/delivery-charges') ? 'active' : '' }}"><i class="fas fa-money-bill-wave"></i> Delivery Charges</a></li>
          <li class="nav-item"><a href="{{ route('admin.billing.index') }}" class="nav-link {{ request()->is('admin/billing') ? 'active' : '' }}"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
          <li class="nav-item"><a href="{{ route('admin.withdrawals.index') }}" class="nav-link {{ request()->is('admin/withdrawals*') ? 'active' : '' }}"><i class="fas fa-money-check-dollar"></i> Agent Payouts</a></li>
          <li class="nav-item"><a href="{{ route('admin.service_areas') }}" class="nav-link {{ request()->is('admin/service-areas') ? 'active' : '' }}"><i class="fas fa-map"></i> Service Areas</a></li>
          <li class="nav-item"><a href="{{ route('admin.support') }}" class="nav-link {{ request()->is('admin/support') ? 'active' : '' }}"><i class="fas fa-headset"></i> Support Tickets</a></li>
        @endif
      @endauth
    </ul>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar">{{ substr(auth()->user()->name ?? 'U', 0, 1) }}</div>
      <div class="info">
        <div class="name">{{ auth()->user()->name ?? '' }}</div>
        <div class="role">{{ auth()->user()->role ?? '' }}</div>
      </div>
    </div>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="btn btn-primary btn-sm w-100 sidebar-logout-btn"><i class="fas fa-sign-out-alt me-2"></i>Logout</button>
    </form>
  </div>
</aside>

<div class="main-content">
  <div class="top-bar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
      <div class="top-bar__heading">
        <div class="top-bar__eyebrow">FuelMate Control Center</div>
        <h6 class="mb-0 fw-600">@yield('title', 'Dashboard')</h6>
      </div>
    </div>
    <div class="top-bar__chip"><i class="fas fa-bolt"></i><span>Live dispatch-ready interface</span></div>
  </div>
  <div class="page-content page-stage" id="sitePage">
    @if(session('success'))
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
    @yield('content')
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
