@extends('layouts.marketing')

@section('title', 'Petrol Delivery')
@section('body_class', 'marketing-page-shell')

@section('content')
<section class="page-hero">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7 page-hero__content" data-reveal="right">
        <div class="eyebrow"><i class="fas fa-gas-pump"></i>Petrol delivery service</div>
        <h1 class="page-hero__title">Instant petrol support without leaving the breakdown point.</h1>
        <p class="page-hero__copy">
          FuelMate delivers petrol directly to cars, bikes, and stranded commuters with live tracking and a calmer
          request flow built around emergencies.
        </p>
      </div>
      <div class="col-lg-5" data-reveal="left">
        <div class="page-hero__card">
          <h3>Best for</h3>
          <div class="metric-pills">
            <span class="metric-pill"><i class="fas fa-car-side"></i>Cars</span>
            <span class="metric-pill"><i class="fas fa-motorcycle"></i>Bikes</span>
            <span class="metric-pill"><i class="fas fa-road"></i>Roadside emergencies</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-block pt-0">
  <div class="container">
    <div class="row g-4 page-highlights">
      <div class="col-md-4"><div class="info-card"><h3>Fast dispatch</h3><p>Nearby delivery partners receive the request quickly so urgent refuels start moving sooner.</p></div></div>
      <div class="col-md-4"><div class="info-card"><h3>Precise delivery</h3><p>Share your location live or pin it manually so the fuel reaches the exact car or bike.</p></div></div>
      <div class="col-md-4"><div class="info-card"><h3>Verified handoff</h3><p>Track the trip, confirm the agent, and close the delivery with the same workflow used across the platform.</p></div></div>
    </div>
  </div>
</section>

<section class="section-block pt-0">
  <div class="container">
    <div class="section-surface">
      <div class="row g-4 align-items-center">
        <div class="col-lg-7">
          <h2 class="section-title">Petrol delivery that feels premium, not improvised.</h2>
          <p class="section-copy">
            The redesign carries the same experience from the public site into the logged-in dashboard, so placing an
            order feels consistent before, during, and after dispatch.
          </p>
        </div>
        <div class="col-lg-5">
          <div class="metric-pills">
            <span class="metric-pill"><i class="fas fa-stopwatch"></i>Quick request flow</span>
            <span class="metric-pill"><i class="fas fa-route"></i>Live ETA visibility</span>
            <span class="metric-pill"><i class="fas fa-user-shield"></i>Trusted partner network</span>
          </div>
          <div class="hero-actions mt-4">
            <a href="/register" class="btn btn-primary">Request Petrol Delivery</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
