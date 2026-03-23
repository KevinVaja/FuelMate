@extends('layouts.marketing')

@section('title', 'Emergency Fuel Delivery')
@section('body_class', 'marketing-home-shell')

@section('content')
  <section class="hero-section">
    <div class="container">
      <div class="row align-items-center g-5 hero-grid">
        <div class="col-lg-6 page-hero__content" data-reveal="right">
          <div class="eyebrow"><i class="fas fa-bolt"></i>24/7 emergency fuel response</div>
          <h1 class="hero-title">Fuel arrives where the breakdown happens.</h1>
          <p class="hero-copy">
            FuelMate coordinates urgent petrol and diesel deliveries for stranded drivers, fleets, and field equipment
            with live tracking, transparent pricing, and verified delivery partners.
          </p>
          <div class="hero-actions d-flex gap-3 flex-wrap">
            <a href="/register" class="btn btn-primary btn-lg"><i class="fas fa-gas-pump me-2"></i>Order Fuel Now</a>
            <a href="/#services" class="btn btn-outline-light btn-lg"><i class="fas fa-arrow-right me-2"></i>Explore
              Services</a>
          </div>
          <div class="hero-service-list">
            <span class="metric-pill"><i class="fas fa-location-dot"></i>Precise location pickup</span>
            <span class="metric-pill"><i class="fas fa-truck-fast"></i>Rapid dispatch routing</span>
            <span class="metric-pill"><i class="fas fa-shield-halved"></i>Verified delivery partners</span>
          </div>
        </div>
        <div class="col-lg-6" data-reveal="left">
          <div class="hero-panel">
            <div class="hero-panel__top">
              <div>
                <div class="hero-panel__eyebrow">Live delivery board</div>
                <div class="hero-panel__title">From request to refuel in one flow.</div>
              </div>
              <div class="hero-panel__badge"><i class="fas fa-signal"></i>Online now</div>
            </div>
            <div class="hero-route">
              <div class="hero-route__stop is-live">
                <div>
                  <strong>Emergency request captured</strong>
                  <span>Highway stop, generator site, or workplace dispatch</span>
                </div>
                <i class="fas fa-headset"></i>
              </div>
              <div class="hero-route__stop">
                <div>
                  <strong>Nearest agent assigned</strong>
                  <span>Verified pump partner with delivery confirmation</span>
                </div>
                <i class="fas fa-user-check"></i>
              </div>
              <div class="hero-route__stop">
                <div>
                  <strong>Fuel delivered with live status</strong>
                  <span>Track ETA, OTP, and final handoff in real time</span>
                </div>
                <i class="fas fa-map-location-dot"></i>
              </div>
            </div>
            <div class="hero-metrics">
              <div class="hero-metric"><strong>10K+</strong><span>orders fulfilled</span></div>
              <div class="hero-metric"><strong>500+</strong><span>active agents</span></div>
              <div class="hero-metric"><strong>25+</strong><span>cities covered</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-block" id="how-it-works">
    <div class="container">
      <div class="section-intro text-center" data-reveal>
        <div class="eyebrow"><i class="fas fa-compass"></i>How it works</div>
        <h2 class="section-title">Built to feel calm in a stressful moment.</h2>
        <p class="section-copy">
          Every step is designed to reduce friction when fuel runs out unexpectedly, whether the request comes from a
          customer car, a commercial vehicle, or on-site equipment.
        </p>
      </div>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="process-card">
            <div class="feature-icon"><i class="fas fa-gas-pump"></i></div>
            <h3 class="h4 fw-bold">Choose fuel type</h3>
            <p class="process-card__meta">Select petrol or diesel, set the quantity, and confirm how you want to share
              your location.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="process-card">
            <div class="feature-icon"><i class="fas fa-map-location-dot"></i></div>
            <h3 class="h4 fw-bold">Pin the exact spot</h3>
            <p class="process-card__meta">Use live coordinates or map search so the assigned partner heads directly to the
              right place.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="process-card">
            <div class="feature-icon"><i class="fas fa-truck-fast"></i></div>
            <h3 class="h4 fw-bold">Track arrival live</h3>
            <p class="process-card__meta">Follow status updates, validate delivery with OTP, and get moving again faster.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-block pt-0" id="services">
    <div class="container">
      <div class="section-intro text-center" data-reveal>
        <div class="eyebrow"><i class="fas fa-layer-group"></i>Services</div>
        <h2 class="section-title">A delivery network that adapts to the situation.</h2>
      </div>
      <div class="row g-4">
        <div class="col-md-6 col-xl-3">
          <div class="service-card">
            <div class="feature-icon"><i class="fas fa-gauge-high"></i></div>
            <h3 class="h4 fw-bold">Petrol Delivery</h3>
            <p class="service-card__meta">On-demand refueling for cars, bikes, and roadside emergencies without waiting
              for a station.</p>
            <a href="{{ route('petrol.page') }}" class="service-card__link">View service <i
                class="fas fa-arrow-right"></i></a>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="service-card">
            <div class="feature-icon"><i class="fas fa-industry"></i></div>
            <h3 class="h4 fw-bold">Diesel Delivery</h3>
            <p class="service-card__meta">Reliable supply support for generators, trucks, industrial units, and larger
              commercial requests.</p>
            <a href="{{ route('diesel.page') }}" class="service-card__link">View service <i
                class="fas fa-arrow-right"></i></a>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="service-card">
            <div class="feature-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <h3 class="h4 fw-bold">Emergency Support</h3>
            <p class="service-card__meta">Priority-focused response when the problem is urgent and the route simply cannot
              wait.</p>
            <a href="{{ route('emergency.page') }}" class="service-card__link">Get help <i
                class="fas fa-arrow-right"></i></a>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="service-card">
            <div class="feature-icon"><i class="fas fa-clock"></i></div>
            <h3 class="h4 fw-bold">24x7 Availability</h3>
            <p class="service-card__meta">Coverage designed for round-the-clock operations in major city zones and active
              delivery corridors.</p>
            <a href="{{ route('availability.page') }}" class="service-card__link">See coverage <i
                class="fas fa-arrow-right"></i></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-block" id="coverage">
    <div class="container">
      <div class="section-surface">
        <div class="row g-4 align-items-center">
          <div class="col-lg-6">
            <div class="section-intro text-start mb-0" data-reveal="right">
              <div class="eyebrow"><i class="fas fa-chart-line"></i>Operational coverage</div>
              <h2 class="section-title">Designed for customers, agents, and operations teams together.</h2>
              <p class="section-copy">
                The public site, sign-in experience, and dashboards all now speak the same design language so the entire
                product feels connected from first visit to final delivery confirmation.
              </p>
            </div>
            <div class="hero-service-list" data-reveal>
              <span class="metric-pill"><i class="fas fa-route"></i>Live route tracking</span>
              <span class="metric-pill"><i class="fas fa-file-shield"></i>Verification workflow</span>
              <span class="metric-pill"><i class="fas fa-wallet"></i>Agent payout management</span>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="quote-card" data-reveal="scale">
              <p class="quote-card__quote">
                "FuelMate turns a high-stress delay into a guided response with visible progress, verified delivery, and
                a cleaner handoff between customer and agent."
              </p>
              <div class="quote-card__meta">Emergency fuel delivery experience, redesigned around clarity and speed.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-block pt-0">
    <div class="container">
      <div class="section-surface text-center" data-reveal="scale">
        <div class="eyebrow"><i class="fas fa-key"></i>Ready to start</div>
        <h2 class="section-title">Create an account and keep fuel support one tap away.</h2>
        <p class="section-copy mx-auto">
          Join as a customer to request emergency deliveries, or sign in as an agent to handle active routes and payouts.
        </p>
        <div class="hero-actions d-flex justify-content-center gap-3 flex-wrap">
          <a href="/register" class="btn btn-primary btn-lg">Create Account</a>
          <a href="/agent-login" class="btn btn-outline-light btn-lg">Agent Sign In</a>
        </div>
      </div>
    </div>
  </section>
@endsection