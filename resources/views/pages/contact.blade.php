@extends('layouts.marketing')

@section('title', 'Contact FuelMate')
@section('body_class', 'marketing-page-shell')

@section('content')
<section class="page-hero">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7 page-hero__content" data-reveal="right">
        <div class="eyebrow"><i class="fas fa-headset"></i>Contact FuelMate</div>
        <h1 class="page-hero__title">Talk to the team behind every urgent delivery.</h1>
        <p class="page-hero__copy">
          Reach out for emergency assistance, service questions, coverage requests, or delivery support. We designed
          this page to feel just as clear and reassuring as the rest of the product.
        </p>
      </div>
      <div class="col-lg-5" data-reveal="left">
        <div class="page-hero__card">
          <h3>Support Snapshot</h3>
          <div class="metric-pills">
            <span class="metric-pill"><i class="fas fa-phone"></i>Phone support</span>
            <span class="metric-pill"><i class="fas fa-envelope"></i>Email assistance</span>
            <span class="metric-pill"><i class="fas fa-location-dot"></i>Ahmedabad coverage</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-block pt-0">
  <div class="container">
    <div class="row g-4 contact-grid">
      <div class="col-lg-4">
        <div class="info-card">
          <h3>Get in touch</h3>
          <ul class="detail-list">
            <li><i class="fas fa-phone"></i><span><strong>Phone</strong><br>+91 98765 43210</span></li>
            <li><i class="fas fa-envelope"></i><span><strong>Email</strong><br>support@fuelmate.com</span></li>
            <li><i class="fas fa-location-dot"></i><span><strong>Location</strong><br>Ahmedabad, Gujarat</span></li>
          </ul>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="contact-form-card">
          <h3>Send a message</h3>
          <p>Need emergency fuel, delivery help, or account support? Share the details and our team will follow up.</p>
          <form>
            <div class="row g-3">
              <div class="col-md-6"><input class="form-control" placeholder="Your Name"></div>
              <div class="col-md-6"><input class="form-control" placeholder="Your Email"></div>
              <div class="col-12"><textarea class="form-control" rows="5" placeholder="Message"></textarea></div>
              <div class="col-12"><button class="btn btn-primary w-100" type="submit">Send Message</button></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
