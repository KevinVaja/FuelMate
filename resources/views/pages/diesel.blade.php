@extends('layouts.marketing')

@section('title', 'Diesel Delivery')
@section('body_class', 'marketing-page-shell')

@section('content')
<section class="page-hero">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7 page-hero__content" data-reveal="right">
        <div class="eyebrow"><i class="fas fa-industry"></i>Diesel delivery service</div>
        <h1 class="page-hero__title">Reliable diesel support for vehicles, equipment, and work sites.</h1>
        <p class="page-hero__copy">
          FuelMate brings diesel to trucks, generators, fleets, and industrial operations with clear pricing,
          location-aware dispatching, and delivery visibility.
        </p>
      </div>
      <div class="col-lg-5" data-reveal="left">
        <div class="page-hero__card">
          <h3>Best for</h3>
          <div class="metric-pills">
            <span class="metric-pill"><i class="fas fa-truck"></i>Commercial vehicles</span>
            <span class="metric-pill"><i class="fas fa-bolt"></i>Generators</span>
            <span class="metric-pill"><i class="fas fa-warehouse"></i>Industrial use</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-block pt-0">
  <div class="container">
    <div class="row g-4 page-highlights">
      <div class="col-md-4"><div class="info-card"><h3>Bulk-friendly flow</h3><p>Designed for larger requests without making the order experience heavy or confusing.</p></div></div>
      <div class="col-md-4"><div class="info-card"><h3>Operations visibility</h3><p>Track progress, dispatch state, and delivery confirmation from the same interface agents use.</p></div></div>
      <div class="col-md-4"><div class="info-card"><h3>Flexible locations</h3><p>Useful for highways, warehouses, construction zones, generators, and planned field stops.</p></div></div>
    </div>
  </div>
</section>

<section class="section-block pt-0">
  <div class="container">
    <div class="section-surface">
      <h2 class="section-title">A cleaner diesel workflow for time-sensitive operations.</h2>
      <p class="section-copy">
        The new palette and layout give diesel delivery a more confident, structured presentation while keeping the
        functional path untouched underneath.
      </p>
      <div class="metric-pills">
        <span class="metric-pill"><i class="fas fa-map-location-dot"></i>Flexible drop points</span>
        <span class="metric-pill"><i class="fas fa-sack-dollar"></i>Transparent charges</span>
        <span class="metric-pill"><i class="fas fa-clock"></i>Real-time dispatch updates</span>
      </div>
      <div class="hero-actions mt-4">
        <a href="/register" class="btn btn-primary">Request Diesel Delivery</a>
      </div>
    </div>
  </div>
</section>
@endsection
