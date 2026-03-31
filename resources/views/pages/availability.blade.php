@extends('layouts.marketing')

@section('title', '24x7 Availability')
@section('body_class', 'marketing-page-shell')

@section('content')
<section class="page-hero">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7 page-hero__content" data-reveal="right">
        <div class="eyebrow"><i class="fas fa-clock"></i>24x7 fuel delivery</div>
        <h1 class="page-hero__title">Availability that matches how real emergencies happen.</h1>
        <p class="page-hero__copy">
          Fuel issues do not wait for office hours, so FuelMate keeps the experience ready for day, night, and
          operations-heavy use cases across active service zones.
        </p>
      </div>
      <div class="col-lg-5" data-reveal="left">
        <div class="page-hero__card">
          <h3>Coverage themes</h3>
          <div class="metric-pills">
            <span class="metric-pill"><i class="fas fa-city"></i>Major city areas</span>
            <span class="metric-pill"><i class="fas fa-route"></i>Delivery corridors</span>
            <span class="metric-pill"><i class="fas fa-moon"></i>Night-ready support</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-block pt-0">
  <div class="container">
    <div class="row g-4 page-highlights">
      <div class="col-md-4"><div class="info-card"><h3>Always visible</h3><p>Users and agents get consistent status information regardless of when the request comes in.</p></div></div>
      <div class="col-md-4"><div class="info-card"><h3>Designed for continuity</h3><p>The new visual system keeps the experience cohesive from landing page to dashboard workflows.</p></div></div>
      <div class="col-md-4"><div class="info-card"><h3>Reliable delivery windows</h3><p>Built for urgent requests that need tracking, confirmation, and less visual noise.</p></div></div>
    </div>
  </div>
</section>

<section class="section-block pt-0">
  <div class="container">
    <div class="section-surface text-center">
      <div class="eyebrow"><i class="fas fa-circle-check"></i>Always available</div>
      <h2 class="section-title">A round-the-clock product now matched by a round-the-clock visual system.</h2>
      <p class="section-copy mx-auto">
        The refreshed layout uses your requested palette across public and logged-in surfaces so the site feels
        intentional and unified at any hour.
      </p>
    </div>
  </div>
</section>
@endsection
