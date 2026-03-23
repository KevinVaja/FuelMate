@extends('layouts.marketing')

@section('title', 'Emergency Support')
@section('body_class', 'marketing-page-shell')

@section('content')
<section class="page-hero">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7 page-hero__content" data-reveal="right">
        <div class="eyebrow"><i class="fas fa-triangle-exclamation"></i>Emergency support</div>
        <h1 class="page-hero__title">When the stop is urgent, the experience should still feel controlled.</h1>
        <p class="page-hero__copy">
          FuelMate is built for roadside emergencies, late-night outages, and time-sensitive refuels where every minute
          matters and clarity matters just as much.
        </p>
      </div>
      <div class="col-lg-5" data-reveal="left">
        <div class="page-hero__card">
          <h3>Emergency hotline</h3>
          <div class="metric-pills">
            <span class="metric-pill"><i class="fas fa-phone-volume"></i>1800-123-FUEL</span>
            <span class="metric-pill"><i class="fas fa-shield-heart"></i>Priority-ready support</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-block pt-0">
  <div class="container">
    <div class="row g-4 page-highlights">
      <div class="col-md-4"><div class="info-card"><h3>Roadside refill</h3><p>For drivers stranded away from the nearest station and needing a fast route back to motion.</p></div></div>
      <div class="col-md-4"><div class="info-card"><h3>Late-night support</h3><p>Round-the-clock access helps when unexpected fuel issues happen outside usual operating hours.</p></div></div>
      <div class="col-md-4"><div class="info-card"><h3>Critical equipment</h3><p>Useful when generators or on-site systems cannot afford a long delay before refueling.</p></div></div>
    </div>
  </div>
</section>

<section class="section-block pt-0">
  <div class="container">
    <div class="quote-card">
      <p class="quote-card__quote">Emergency delivery works best when the interface feels steady, informative, and unmistakably action-oriented.</p>
      <div class="quote-card__meta">That is exactly what this redesign is aiming for across both public pages and authenticated screens.</div>
    </div>
  </div>
</section>
@endsection
