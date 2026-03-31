@extends('layouts.app')
@section('title', 'Track Order')
@section('head')
  @php
    $trackingMapEnabled = $order->agent !== null
      && $order->status === 'on_the_way'
      && $order->hasDeliveryCoordinates();
  @endphp
  @if($trackingMapEnabled)
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css">
    <style>
      .tracking-map-shell {
        display: grid;
        gap: 1rem;
        text-align: left;
      }

      .tracking-map-shell__hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
      }

      .tracking-map-shell__hero h6 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
      }

      .tracking-map-shell__hero p {
        margin: 0.35rem 0 0;
        color: #64748b;
        font-size: 0.94rem;
      }

      .tracking-map-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.5rem 0.85rem;
        border-radius: 999px;
        background: rgba(37, 99, 235, 0.1);
        color: #1d4ed8;
        font-size: 0.85rem;
        font-weight: 700;
      }

      .tracking-map-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
      }

      .tracking-map-metric {
        padding: 0.95rem 1rem;
        border-radius: 1rem;
        background: rgba(248, 250, 252, 0.96);
        border: 1px solid rgba(226, 232, 240, 0.95);
      }

      .tracking-map-metric__label {
        display: block;
        margin-bottom: 0.32rem;
        color: #64748b;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
      }

      .tracking-map-metric__value {
        color: #111827;
        font-weight: 800;
        font-size: 1.05rem;
      }

      .tracking-map-canvas {
        width: 100%;
        min-height: 420px;
        border-radius: 1.15rem;
        overflow: hidden;
        background:
          linear-gradient(135deg, rgba(239, 246, 255, 0.98), rgba(248, 250, 252, 0.98));
        border: 1px solid rgba(191, 219, 254, 0.65);
      }

      .tracking-map-status {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        color: #475569;
        font-size: 0.92rem;
      }

      .tracking-map-status__hint {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        color: #0f766e;
        font-weight: 600;
      }

      .tracking-user-marker,
      .tracking-agent-marker {
        display: grid;
        place-items: center;
        border-radius: 999px;
        border: 3px solid rgba(255, 255, 255, 0.95);
        box-shadow: 0 10px 18px rgba(15, 23, 42, 0.24);
      }

      .tracking-user-marker {
        width: 20px;
        height: 20px;
        background: #2563eb;
      }

      .tracking-agent-marker {
        width: 22px;
        height: 22px;
        background: #0f766e;
      }

      .tracking-route-fallback {
        stroke: #2563eb;
        stroke-width: 5;
        stroke-linecap: round;
        stroke-linejoin: round;
        opacity: 0.92;
      }

      .leaflet-routing-container {
        display: none;
      }

      @media (max-width: 767px) {
        .tracking-map-metrics {
          grid-template-columns: minmax(0, 1fr);
        }

        .tracking-map-canvas {
          min-height: 340px;
        }
      }
    </style>
  @endif
@endsection
@section('content')
  <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('user.history') }}">Orders</a></li>
          <li class="breadcrumb-item active">Order #{{ $order->displayOrderNumber() }}</li>
        </ol>
      </nav>
      <h4 class="fw-bold mb-0">Track Order #{{ $order->displayOrderNumber() }}</h4>
    </div>
    @php
      $showCancellationChargePanel = $order->canCustomerPayCancellationCharge()
        && (old('cancellation_charge_payment_method') || session('error'));
      $trackingMapEnabled = $order->agent !== null
        && $order->status === 'on_the_way'
        && $order->hasDeliveryCoordinates();
      $trackingPayload = [
        'status' => $order->status,
        'tracking_enabled' => $trackingMapEnabled,
        'agent_latitude' => $order->agent?->trackingLatitude(),
        'agent_longitude' => $order->agent?->trackingLongitude(),
        'user_latitude' => $order->delivery_lat,
        'user_longitude' => $order->delivery_lng,
        'distance_km' => $order->distance_km,
        'estimated_delivery_minutes' => $order->estimated_delivery_minutes,
        'last_location_update' => $order->agent?->last_location_update?->toIso8601String(),
      ];
    @endphp
    <div class="d-flex gap-2 flex-wrap">
      @if($order->billing)
        <a href="{{ route('orders.invoice', $order->id) }}" class="btn btn-outline-primary"><i
            class="fas fa-file-invoice me-2"></i>View Invoice</a>
        <a href="{{ route('orders.invoice.download', $order->id) }}" class="btn btn-primary"><i
            class="fas fa-download me-2"></i>Download Invoice</a>
      @endif
      @if($order->canCustomerCancel())
        <form method="POST" action="{{ route('user.orders.cancel', $order->id) }}">
          @csrf
          <input type="hidden" name="reason" value="Cancelled by customer.">
          <button type="submit" class="btn btn-outline-danger"
            onclick="return confirm('Cancel this order? Cancellation after dispatch will incur high charges.')"><i
              class="fas fa-ban me-2"></i>Cancel Order</button>
        </form>
      @elseif($order->canCustomerPayCancellationCharge())
        <form method="POST" action="{{ route('user.orders.cancel', $order->id) }}"
          class="d-flex gap-2 flex-wrap align-items-center">
          @csrf
          <input type="hidden" name="reason" value="Cancelled by customer.">
          <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse"
            data-bs-target="#codCancellationChargePanel"
            aria-expanded="{{ $showCancellationChargePanel ? 'true' : 'false' }}"
            aria-controls="codCancellationChargePanel"><i class="fas fa-ban me-2"></i>Cancel Order</button>
        </form>
      @endif
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card mb-4">
        <div class="card-header">Order Status</div>
        <div class="card-body">
          @php
            $steps = [
              'pending' => 0,
              'accepted' => 1,
              'fuel_preparing' => 2,
              'on_the_way' => 3,
              'arrived' => 4,
              'otp_verification' => 5,
              'delivered' => 6,
              'refund_processing' => 6,
              'cancelled' => -1,
              'completed' => 6,
            ];
            $currentStep = $steps[$order->status] ?? 0;
            $displayTimezone = config('app.display_timezone', 'Asia/Kolkata');
            $statusItems = [
              ['label' => 'Order Placed', 'icon' => 'fa-check', 'step' => 0],
              ['label' => 'Agent Accepted', 'icon' => 'fa-user-check', 'step' => 1],
              ['label' => 'Fuel Preparing', 'icon' => 'fa-gas-pump', 'step' => 2],
              ['label' => 'On the Way', 'icon' => 'fa-truck', 'step' => 3],
              ['label' => 'Arrived', 'icon' => 'fa-location-dot', 'step' => 4],
              ['label' => 'Handoff Verification', 'icon' => 'fa-key', 'step' => 5],
              ['label' => 'Delivered', 'icon' => 'fa-circle-check', 'step' => 6],
            ];
          @endphp
          @foreach($statusItems as $item)
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="tracking-step-circle {{ $currentStep >= $item['step'] ? 'is-active' : 'is-inactive' }}">
                <i class="fas {{ $item['icon'] }} small"></i>
              </div>
              <span class="{{ $currentStep >= $item['step'] ? 'fw-bold' : 'text-muted' }}">{{ $item['label'] }}</span>
              @if($currentStep === $item['step'] && !in_array($order->status, ['delivered', 'cancelled'], true))
                <span class="badge bg-primary ms-auto small">Current</span>
              @endif
            </div>
            @if(!$loop->last)
            <div class="tracking-step-line ms-1 mb-2"></div>@endif
          @endforeach
        </div>
      </div>

      <div class="card">
        <div class="card-header">Order Details</div>
        <div class="card-body">
          @php $billing = $order->billing; @endphp
          <div class="row g-2">
            <div class="col-6"><small class="text-muted">Fuel</small>
              <div class="fw-bold">{{ $order->fuelProduct->name ?? '—' }}</div>
            </div>
            <div class="col-6"><small class="text-muted">Quantity</small>
              <div class="fw-bold">{{ $order->quantity_liters }}L</div>
            </div>
            <div class="col-6"><small class="text-muted">Fuel Total</small>
              <div class="fw-bold">₹{{ number_format((float) ($billing?->fuel_total ?? 0), 2) }}</div>
            </div>
            <div class="col-6"><small class="text-muted">Delivery Charge</small>
              <div class="fw-bold">
                ₹{{ number_format((float) ($order->slab_charge ?: ($billing?->delivery_charge ?? $order->delivery_charge)), 2) }}
              </div>
            </div>
            @if((float) $order->night_fee > 0)
              <div class="col-6"><small class="text-muted">Night Delivery Extra</small>
                <div class="fw-bold text-warning">₹{{ number_format((float) $order->night_fee, 2) }}</div>
              </div>
            @endif
            <div class="col-6"><small class="text-muted">Platform Fee</small>
              <div class="fw-bold">₹{{ number_format((float) ($billing?->platform_fee ?? 0), 2) }}</div>
            </div>
            <div class="col-6"><small class="text-muted">GST</small>
              <div class="fw-bold">₹{{ number_format((float) ($billing?->gst_amount ?? 0), 2) }}</div>
            </div>
            <div class="col-12 pt-2 border-top"><small class="text-muted">Total Amount</small>
              <div class="fs-5 fw-bold text-primary">
                ₹{{ number_format((float) ($billing?->total_amount ?? $order->total_amount), 2) }}</div>
            </div>
            <div class="col-6"><small class="text-muted">Payment</small>
              <div class="fw-bold text-uppercase">{{ $order->paymentMethodLabel() }}</div>
            </div>
            <div class="col-6"><small class="text-muted">Payment Status</small>
              <span
                class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : 'warning' }} text-{{ $order->payment_status === 'paid' ? 'white' : 'dark' }}">{{ ucfirst($order->payment_status) }}</span>
            </div>
            <div class="col-6"><small class="text-muted">Billing Status</small>
              <span class="badge bg-light text-dark text-uppercase">{{ $billing?->billing_status ?? 'estimated' }}</span>
            </div>
            <div class="col-6"><small class="text-muted">Invoice</small>
              <div class="fw-bold">{{ $billing ? 'Available' : 'Preparing' }}</div>
            </div>
            @if($order->is_cancelled)
              <div class="col-6"><small class="text-muted">Cancellation Charge</small>
                <div class="fw-bold text-danger">₹{{ number_format((float) $order->cancellation_charge, 2) }}</div>
              </div>
              <div class="col-6"><small class="text-muted">Refund Status</small>
                <div class="fw-bold text-uppercase">{{ $billing?->refund_status ?? 'none' }}</div>
              </div>
              @if($order->cancellationChargePaymentIsSettled())
                <div class="col-6"><small class="text-muted">Cancellation Fee Payment</small>
                  <div class="fw-bold">{{ $order->cancellationChargePaymentMethodLabel() ?? 'Paid' }}</div>
                </div>
                <div class="col-6"><small class="text-muted">Charge Payment Status</small>
                  <div class="fw-bold text-success text-uppercase">{{ $order->cancellation_charge_payment_status }}</div>
                </div>
              @endif
            @endif
            <div class="col-12"><small class="text-muted">Delivery Address</small>
              <div>{{ $order->delivery_address }}</div>
            </div>
            <div class="col-6"><small class="text-muted">Location Mode</small>
              <div class="fw-bold">{{ $order->usesPinnedMapLocation() ? 'Pinned Map Location' : 'Live Device GPS' }}</div>
            </div>
            @if($order->hasDeliveryCoordinates())
              <div class="col-6"><small class="text-muted">{{ $order->displayLocationLabel() }}</small>
                <div class="fw-bold">{{ $order->displayLocationAddress() }}</div>
              </div>
            @endif
            <div class="col-12"><small class="text-muted">Distance</small>
              <div>{{ $order->distance_km }} km · Est. {{ $order->estimated_delivery_minutes }} min</div>
            </div>
            @if(in_array($order->status, ['accepted', 'fuel_preparing', 'on_the_way', 'arrived', 'otp_verification'], true) && $order->estimated_delivery_minutes)
              <div class="col-12">
                <small class="text-muted">Live ETA</small>
                <div class="fw-bold text-primary" id="liveEtaSummary">{{ $order->estimated_delivery_minutes }} min ·
                  {{ number_format((float) $order->distance_km, 1) }} km away</div>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      @if($order->canCustomerCancel())
        <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3">
          <div>
            <div class="fw-bold">Cancellation warning</div>
            <div class="small mb-0">Cancellation after dispatch will incur high charges.</div>
          </div>
          <i class="fas fa-triangle-exclamation fs-4 text-warning"></i>
        </div>
      @elseif($order->canCustomerPayCancellationCharge())
        <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3">
          <div>
            <div class="fw-bold">Cancellation charge required</div>
            <div class="small mb-0">{{ $order->customerCancellationRestrictionMessage() }}</div>
          </div>
          <i class="fas fa-credit-card fs-4 text-warning"></i>
        </div>
        <div class="collapse {{ $showCancellationChargePanel ? 'show' : '' }}" id="codCancellationChargePanel">
          <div class="card border-danger border-opacity-25 mb-4">
            <div class="card-body">
              <div class="fw-bold mb-1">Confirm cancellation payment</div>
              <div class="small text-muted mb-3">The cancellation fee will only be charged if you continue with
                cancellation.</div>
              <div class="small text-muted mb-2">Cancellation fee to pay now</div>
              <div class="fs-4 fw-bold text-danger mb-3">₹{{ number_format($order->customerCancellationChargeAmount(), 2) }}
              </div>
              <form method="POST" action="{{ route('user.orders.cancel', $order->id) }}"
                class="d-flex gap-2 flex-wrap align-items-end">
                @csrf
                <input type="hidden" name="reason" value="Cancelled by customer.">
                <div>
                  <label class="form-label small text-muted mb-1">Payment method</label>
                  <select name="cancellation_charge_payment_method" class="form-select" style="min-width: 170px;">
                    <option value="online" {{ old('cancellation_charge_payment_method', 'online') === 'online' ? 'selected' : '' }}>Pay Online</option>
                    <option value="wallet" {{ old('cancellation_charge_payment_method') === 'wallet' ? 'selected' : '' }}>Use
                      Wallet</option>
                  </select>
                </div>
                <button type="submit" class="btn btn-danger"
                  onclick="return confirm('Pay ₹{{ number_format($order->customerCancellationChargeAmount(), 2) }} as the cancellation fee and cancel this order?')"><i
                    class="fas fa-credit-card me-2"></i>Pay Fee & Cancel</button>
              </form>
            </div>
          </div>
        </div>
      @endif

      @if(in_array($order->status, ['pending', 'accepted', 'fuel_preparing', 'on_the_way', 'arrived', 'otp_verification'], true) && $order->usesLiveCustomerLocation())
        <div class="alert alert-info d-flex justify-content-between align-items-center gap-3">
          <div>
            <div class="fw-bold">Live location sharing is active</div>
            <div class="small mb-0" id="locationSyncStatus">We will keep your order location updated while this page stays
              open.</div>
          </div>
          <i class="fas fa-location-dot fs-4 text-primary"></i>
        </div>
      @elseif(in_array($order->status, ['pending', 'accepted', 'fuel_preparing', 'on_the_way', 'arrived', 'otp_verification'], true))
        <div class="alert alert-secondary d-flex justify-content-between align-items-center gap-3">
          <div>
            <div class="fw-bold">Pinned delivery location selected</div>
            <div class="small mb-0">This order uses a fixed map pin, so we will keep that selected destination unchanged for
              your agent.</div>
          </div>
          <i class="fas fa-map-pin fs-4 text-secondary"></i>
        </div>
      @endif

      @if($order->agent)
        <div class="card mb-4">
          <div class="card-header">Your Delivery Agent</div>
          <div class="card-body">
            <div class="d-flex align-items-center gap-3">
              <div class="circle-avatar-md bg-primary bg-opacity-10 d-flex align-items-center justify-content-center">
                <span class="fw-bold text-primary fs-4">{{ substr($order->agent->user->name ?? 'A', 0, 1) }}</span>
              </div>
              <div>
                <div class="fw-bold fs-5">{{ $order->agent->user->name ?? 'Agent' }}</div>
                <div class="text-muted small">{{ $order->agent->vehicle_type }} · {{ $order->agent->vehicle_license_plate }}
                </div>
                @if($order->agent->rating)
                  <div class="text-warning small">{{ str_repeat('★', round($order->agent->rating)) }}
                    {{ $order->agent->rating }}</div>
                @endif
              </div>
              @if($order->agent->user->phone)
                <a href="tel:{{ $order->agent->user->phone }}" class="btn btn-outline-primary ms-auto"><i
                    class="fas fa-phone me-2"></i>Call</a>
              @endif
            </div>
          </div>
        </div>
      @endif

      @if(in_array($order->status, ['arrived', 'otp_verification', 'delivered'], true))
        <div class="card mb-4">
          <div class="card-header">Delivery PIN / QR</div>
          <div class="card-body text-center">
            @if($order->hasPendingDeliveryOtp())
              <p class="text-muted mb-3">Show this delivery PIN or QR to your agent only after the fuel has reached you.</p>
              <div class="small text-uppercase fw-semibold text-muted mb-1">Delivery PIN</div>
              <div class="display-5 fw-bold text-primary mb-2">{{ $order->delivery_otp }}</div>
              @if($order->deliveryHandoffQrPayload())
                <div class="small text-uppercase fw-semibold text-muted mt-4 mb-2">Customer QR</div>
                <div id="deliveryHandoffQr" class="d-inline-block bg-white p-3 rounded border"
                  data-qr-payload="{{ $order->deliveryHandoffQrPayload() }}"></div>
                <div class="small text-muted mt-2">Your agent can scan this QR or type the PIN manually.</div>
              @endif
              <div class="small text-muted">Generated at
                {{ $order->delivery_otp_generated_at?->copy()->timezone($displayTimezone)->format('d M Y, h:i A') ?? 'just now' }}
              </div>
            @elseif($order->deliveryOtpWasVerified())
              <div class="text-success fw-bold mb-2"><i class="fas fa-circle-check me-2"></i>Delivery handoff verified</div>
              <p class="text-muted mb-0">Your agent has successfully verified the final delivery handoff.</p>
            @else
              <p class="text-muted mb-0">When your agent reaches you, FuelMate will generate a delivery PIN and QR here so the
                order can be confirmed securely.</p>
            @endif
          </div>
        </div>
      @endif

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Live Tracking</span>
          @if($trackingMapEnabled)
            <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">Auto-updates every
              15s</span>
          @elseif(in_array($order->status, ['pending', 'accepted', 'fuel_preparing', 'arrived', 'otp_verification'], true))
            <button class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i
                class="fas fa-sync me-1"></i>Refresh Status</button>
          @endif
        </div>
        <div class="card-body {{ $trackingMapEnabled ? '' : 'text-center py-5' }} tracking-stage-panel">
          @php
            $mapsUrl = $order->googleMapsDirectionsUrl($order->agent?->trackingLatitude(), $order->agent?->trackingLongitude());
            $destinationMapsUrl = $order->googleMapsDestinationUrl();
          @endphp
          @if($order->status === 'pending')
            <i class="fas fa-hourglass-half fa-3x text-warning mb-3"></i>
            <h6 class="fw-bold">Finding an Agent</h6>
            <p class="text-muted small">We're looking for the nearest available agent for your request.</p>
            @if($destinationMapsUrl)
              <a href="{{ $destinationMapsUrl }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i
                  class="fas fa-map me-1"></i>View Delivery Pin</a>
            @endif
          @elseif($order->status === 'accepted')
            <i class="fas fa-user-check fa-3x text-primary mb-3"></i>
            <h6 class="fw-bold">Agent Found!</h6>
            <p class="text-muted small" id="trackingSummary">Your agent has accepted the request and is getting started.
              Estimated: {{ $order->estimated_delivery_minutes }} mins</p>
            @if($destinationMapsUrl)
              <a href="{{ $destinationMapsUrl }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i
                  class="fas fa-map me-1"></i>View Delivery Pin</a>
            @endif
          @elseif($order->status === 'fuel_preparing')
            <i class="fas fa-gas-pump fa-3x text-primary mb-3"></i>
            <h6 class="fw-bold">Fuel Is Being Prepared</h6>
            <p class="text-muted small" id="trackingSummary">Your assigned agent is preparing the fuel for dispatch.</p>
            @if($destinationMapsUrl)
              <a href="{{ $destinationMapsUrl }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i
                  class="fas fa-map me-1"></i>View Delivery Pin</a>
            @endif
          @elseif($order->status === 'on_the_way')
            <div class="tracking-map-shell">
              <div class="tracking-map-shell__hero">
                <div>
                  <h6>On the Way</h6>
                  <p id="trackingSummary">Your agent is heading towards you. Distance:
                    {{ number_format((float) $order->distance_km, 1) }} km · ETA {{ $order->estimated_delivery_minutes }}
                    mins.</p>
                </div>
                <div class="tracking-map-pill">
                  <i class="fas fa-location-arrow"></i>
                  <span id="trackingMapState">Live route active</span>
                </div>
              </div>
              <div class="tracking-map-metrics">
                <div class="tracking-map-metric">
                  <span class="tracking-map-metric__label">Live ETA</span>
                  <div class="tracking-map-metric__value" id="trackingMapEta">
                    {{ $order->estimated_delivery_minutes ?? '—' }} min</div>
                </div>
                <div class="tracking-map-metric">
                  <span class="tracking-map-metric__label">Distance Remaining</span>
                  <div class="tracking-map-metric__value" id="trackingMapDistance">
                    {{ $order->distance_km !== null ? number_format((float) $order->distance_km, 1) . ' km' : 'Waiting for GPS' }}
                  </div>
                </div>
                <div class="tracking-map-metric">
                  <span class="tracking-map-metric__label">Agent GPS</span>
                  <div class="tracking-map-metric__value" id="trackingMapLastPing">
                    {{ $order->agent?->last_location_update?->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->format('h:i:s A') ?? 'Waiting...' }}
                  </div>
                </div>
              </div>
              <div id="orderTrackingMap" class="tracking-map-canvas"></div>
              <div class="tracking-map-status">
                <span id="trackingMapStatusText">Waiting for the next live GPS refresh.</span>
                <span class="tracking-map-status__hint"><i class="fas fa-route"></i>Blue route updates with each tracking
                  sync</span>
              </div>
              @if($mapsUrl)
                <div class="d-flex justify-content-end">
                  <a href="{{ $mapsUrl }}" target="_blank" class="btn btn-sm btn-outline-primary" id="trackingMapsLink"><i
                      class="fas fa-map me-1"></i>Open in Maps</a>
                </div>
              @endif
            </div>
          @elseif($order->status === 'arrived')
            <i class="fas fa-location-dot fa-3x text-primary mb-3"></i>
            <h6 class="fw-bold">Agent Arrived</h6>
            <p class="text-muted small">Your agent is at the location and will start handoff verification shortly.</p>
          @elseif($order->status === 'otp_verification')
            <i class="fas fa-key fa-3x text-primary mb-3"></i>
            <h6 class="fw-bold">Handoff Verification In Progress</h6>
            <p class="text-muted small">Share the PIN or show the QR only after the fuel handoff is complete.</p>
          @elseif($order->status === 'delivered')
            <i class="fas fa-gas-pump fa-3x text-success mb-3"></i>
            <h6 class="fw-bold">Fuel Delivered!</h6>
            <p class="text-muted small">Your order has been completed and settlement has been recorded.</p>
          @elseif($order->status === 'refund_processing')
            <i class="fas fa-rotate fa-3x text-warning mb-3"></i>
            <h6 class="fw-bold text-warning">Refund Processing</h6>
            <p class="text-muted small">{{ $order->cancellationStatusMessage() }} The refund is waiting for admin approval.
            </p>
          @elseif($order->status === 'cancelled')
            <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
            <h6 class="fw-bold text-danger">Order Cancelled</h6>
            <p class="text-muted small mb-1">{{ $order->cancellationStatusMessage() }}</p>
            @if($order->hasAdditionalCancellationReason())
              <p class="text-muted small mb-0">{{ $order->cancellationReasonMessage() }}</p>
            @endif
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
@section('scripts')
  @if($order->hasPendingDeliveryOtp() && $order->deliveryHandoffQrPayload())
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const qrContainer = document.getElementById('deliveryHandoffQr');
        const payload = qrContainer?.dataset?.qrPayload;

        if (!qrContainer || !payload || typeof QRCode === 'undefined') {
          return;
        }

        qrContainer.innerHTML = '';
        new QRCode(qrContainer, {
          text: payload,
          width: 160,
          height: 160,
          correctLevel: QRCode.CorrectLevel.M,
        });
      });
    </script>
  @endif
  @if(in_array($order->status, ['pending', 'accepted', 'fuel_preparing', 'on_the_way', 'arrived', 'otp_verification'], true) && $order->usesLiveCustomerLocation())
    <script>
document.addEventListener('DOMContentLoaded', () => {
  const syncStatus = document.getElementById('locationSyncStatus');
  const trackingSummary = document.getElementById('trackingSummary');
  const liveEtaSummary = document.getElementById('liveEtaSummary');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  const syncUrl = '{{ route('user.track.location', $order->id) }}';
  const syncIntervalMs = 15000;
  let latestCoordinates = null;
  let syncInFlight = false;
  let lastSentAt = 0;
  let tracker = null;

  const updateSummaries = (payload) => {
    if (payload.estimated_delivery_minutes === null || payload.estimated_delivery_minutes === undefined || payload.distance_km === null || payload.distance_km === undefined) {
      return;
    }

          const etaText = `${payload.estimated_delivery_minutes} min · ${Number(payload.distance_km).toFixed(1)} km away`;

          if (liveEtaSummary) {
            liveEtaSummary.textContent = etaText;
          }

          if (trackingSummary && @json($order->status) === 'on_the_way') {
            trackingSummary.textContent = `Your agent is heading towards you. Distance: ${Number(payload.distance_km).toFixed(1)} km · ETA ${payload.estimated_delivery_minutes} mins`;
    }
  };

  const syncLocation = async (force = false) => {
    if (!latestCoordinates || !csrfToken || syncInFlight) {
      return;
    }

    const now = Date.now();
    if (!force && now - lastSentAt < syncIntervalMs - 500) {
      return;
    }

    syncInFlight = true;

    try {
      const response = await fetch(syncUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          delivery_lat: latestCoordinates.latitude,
          delivery_lng: latestCoordinates.longitude,
        }),
      });

      if (!response.ok) {
        throw new Error('sync-failed');
      }

      const payload = await response.json();
      lastSentAt = now;
      updateSummaries(payload);

      if (syncStatus) {
        syncStatus.textContent = `Live location synced (${Math.round(latestCoordinates.accuracyMeters)}m). Last update at ${new Date().toLocaleTimeString()}.`;
      }
    } catch (error) {
      if (syncStatus) {
        syncStatus.textContent = 'Unable to sync your live location right now. We will try again shortly.';
      }
    } finally {
      syncInFlight = false;
    }
  };

  if (!window.FuelMateGps || !csrfToken) {
    if (syncStatus) {
      syncStatus.textContent = 'Browser GPS is unavailable. Refresh after enabling location services.';
    }
    return;
  }

  tracker = window.FuelMateGps.createTracker({
    onAccepted(location) {
      latestCoordinates = location;

      if (lastSentAt === 0) {
        syncLocation(true);
      } else if (syncStatus) {
        syncStatus.textContent = `Precise customer location ready (${Math.round(location.accuracyMeters)}m). Syncing on the next 15-second heartbeat.`;
      }
    },
    onStatus(message) {
      if (syncStatus) {
        syncStatus.textContent = message;
      }
    },
  });

  tracker.start();

  window.setInterval(() => {
    syncLocation();
  }, syncIntervalMs);

  window.addEventListener('beforeunload', () => {
    if (tracker) {
      tracker.stop();
    }
  });
});
</script>
@endif
  @if($trackingMapEnabled)
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
      integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const initialPayload = @json($trackingPayload);
        const refreshUrl = '{{ route('api.order.track', $order->id) }}';
        const refreshMs = 15000;
        const animationMs = 14000;
        const mapElement = document.getElementById('orderTrackingMap');
        const trackingSummary = document.getElementById('trackingSummary');
        const trackingMapEta = document.getElementById('trackingMapEta');
        const trackingMapDistance = document.getElementById('trackingMapDistance');
        const trackingMapLastPing = document.getElementById('trackingMapLastPing');
        const trackingMapStatusText = document.getElementById('trackingMapStatusText');
        const trackingMapState = document.getElementById('trackingMapState');
        const trackingMapsLink = document.getElementById('trackingMapsLink');

        if (!mapElement || typeof L === 'undefined') {
          return;
        }

        const map = L.map(mapElement, {
          zoomControl: true,
          scrollWheelZoom: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        const userIcon = L.divIcon({
          className: '',
          html: '<span class="tracking-user-marker"></span>',
          iconSize: [20, 20],
          iconAnchor: [10, 10],
        });

        const agentIcon = L.divIcon({
          className: '',
          html: '<span class="tracking-agent-marker"></span>',
          iconSize: [22, 22],
          iconAnchor: [11, 11],
        });

        let userMarker = null;
        let agentMarker = null;
        let routeControl = null;
        let fallbackRoute = null;
        let refreshTimer = null;
        let animationFrameId = null;
        let mapHasFitted = false;
        let refreshInFlight = false;

        const formatDistance = (distanceKm) => {
          if (distanceKm === null || distanceKm === undefined) {
            return 'Waiting for GPS';
          }

          return `${Number(distanceKm).toFixed(1)} km`;
        };

        const formatEta = (etaMinutes) => {
          if (etaMinutes === null || etaMinutes === undefined) {
            return 'Waiting...';
          }

          return `${etaMinutes} min`;
        };

        const formatLastPing = (timestamp) => {
          if (!timestamp) {
            return 'Waiting...';
          }

          const date = new Date(timestamp);
          return Number.isNaN(date.getTime()) ? 'Waiting...' : date.toLocaleTimeString();
        };

        const stopAnimation = () => {
          if (animationFrameId !== null) {
            window.cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
          }
        };

        const ensureFallbackRoute = () => {
          if (!fallbackRoute) {
            fallbackRoute = L.polyline([], {
              color: '#2563eb',
              weight: 5,
              opacity: 0.92,
              className: 'tracking-route-fallback',
            }).addTo(map);
          }

          return fallbackRoute;
        };

        const removeRoute = () => {
          if (routeControl) {
            map.removeControl(routeControl);
            routeControl = null;
          }

          if (fallbackRoute) {
            map.removeLayer(fallbackRoute);
            fallbackRoute = null;
          }
        };

        const removeAgentMarker = () => {
          stopAnimation();

          if (agentMarker) {
            map.removeLayer(agentMarker);
            agentMarker = null;
          }
        };

        const updateRoute = (agentLatLng, userLatLng) => {
          if (!agentLatLng || !userLatLng) {
            removeRoute();
            return;
          }

          if (typeof L.Routing !== 'undefined' && typeof L.Routing.control === 'function') {
            const waypoints = [
              L.latLng(agentLatLng[0], agentLatLng[1]),
              L.latLng(userLatLng[0], userLatLng[1]),
            ];

            if (!routeControl) {
              routeControl = L.Routing.control({
                waypoints,
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: false,
                routeWhileDragging: false,
                show: false,
                collapsible: true,
                lineOptions: {
                  styles: [{ color: '#2563eb', opacity: 0.92, weight: 5 }],
                },
                createMarker: () => null,
                router: L.Routing.osrmv1({
                  serviceUrl: 'https://router.project-osrm.org/route/v1',
                }),
              }).addTo(map);

              routeControl.on('routingerror', () => {
                const fallback = ensureFallbackRoute();
                fallback.setLatLngs([agentLatLng, userLatLng]);
              });
            } else {
              routeControl.setWaypoints(waypoints);
            }

            if (fallbackRoute) {
              fallbackRoute.setLatLngs([]);
            }

            return;
          }

          const fallback = ensureFallbackRoute();
          fallback.setLatLngs([agentLatLng, userLatLng]);
        };

        const updateMetrics = (payload) => {
          if (trackingMapEta) {
            trackingMapEta.textContent = formatEta(payload.estimated_delivery_minutes);
          }

          if (trackingMapDistance) {
            trackingMapDistance.textContent = formatDistance(payload.distance_km);
          }

          if (trackingMapLastPing) {
            trackingMapLastPing.textContent = formatLastPing(payload.last_location_update);
          }

          if (trackingSummary && payload.distance_km !== null && payload.distance_km !== undefined && payload.estimated_delivery_minutes !== null && payload.estimated_delivery_minutes !== undefined) {
            trackingSummary.textContent = `Your agent is heading towards you. Distance: ${Number(payload.distance_km).toFixed(1)} km · ETA ${payload.estimated_delivery_minutes} mins.`;
          }

          if (trackingMapsLink && payload.agent_latitude !== null && payload.agent_longitude !== null && payload.user_latitude !== null && payload.user_longitude !== null) {
            const url = new URL('https://www.google.com/maps/dir/');
            url.searchParams.set('api', '1');
            url.searchParams.set('origin', `${payload.agent_latitude},${payload.agent_longitude}`);
            url.searchParams.set('destination', `${payload.user_latitude},${payload.user_longitude}`);
            url.searchParams.set('travelmode', 'driving');
            trackingMapsLink.href = url.toString();
          }
        };

        const updateStateText = (message, tone = 'active') => {
          if (trackingMapStatusText) {
            trackingMapStatusText.textContent = message;
          }

          if (!trackingMapState) {
            return;
          }

          trackingMapState.textContent = tone === 'ended' ? 'Tracking stopped' : message.includes('Waiting') ? 'Waiting for GPS' : 'Live route active';
        };

        const fitMapToPoints = (agentLatLng, userLatLng) => {
          const points = [];

          if (agentLatLng) {
            points.push(agentLatLng);
          }

          if (userLatLng) {
            points.push(userLatLng);
          }

          if (points.length === 0) {
            map.setView([22.9734, 78.6569], 5);
            return;
          }

          map.fitBounds(points, { padding: [36, 36] });
        };

        const animateAgentMarker = (targetLatLng) => {
          if (!targetLatLng) {
            return;
          }

          if (!agentMarker) {
            agentMarker = L.marker(targetLatLng, { icon: agentIcon, zIndexOffset: 800 }).addTo(map);
            return;
          }

          stopAnimation();

          const start = agentMarker.getLatLng();
          const startTimestamp = performance.now();

          const step = (timestamp) => {
            const progress = Math.min((timestamp - startTimestamp) / animationMs, 1);
            const eased = progress < 0.5
              ? 4 * progress * progress * progress
              : 1 - Math.pow(-2 * progress + 2, 3) / 2;

            const lat = start.lat + ((targetLatLng[0] - start.lat) * eased);
            const lng = start.lng + ((targetLatLng[1] - start.lng) * eased);
            const currentLatLng = [lat, lng];

            agentMarker.setLatLng(currentLatLng);

            if (fallbackRoute && userMarker) {
              fallbackRoute.setLatLngs([currentLatLng, userMarker.getLatLng()]);
            }

            if (progress < 1) {
              animationFrameId = window.requestAnimationFrame(step);
            } else {
              animationFrameId = null;
            }
          };

          animationFrameId = window.requestAnimationFrame(step);
        };

        const stopTracking = (status) => {
          if (refreshTimer) {
            window.clearInterval(refreshTimer);
            refreshTimer = null;
          }

          removeAgentMarker();
          removeRoute();

          if (status === 'delivered') {
            updateStateText('Live tracking ended because the order was delivered.', 'ended');
          } else {
            updateStateText('Live tracking paused because the order is no longer on the way.', 'ended');
          }
        };

        const applyPayload = (payload, { initial = false } = {}) => {
          updateMetrics(payload);

          if (payload.user_latitude !== null && payload.user_longitude !== null) {
            const userLatLng = [payload.user_latitude, payload.user_longitude];

            if (!userMarker) {
              userMarker = L.marker(userLatLng, { icon: userIcon, zIndexOffset: 600 }).addTo(map);
            } else {
              userMarker.setLatLng(userLatLng);
            }
          }

          if (!payload.tracking_enabled) {
            stopTracking(payload.status);
            return;
          }

          if (payload.agent_latitude === null || payload.agent_longitude === null) {
            updateStateText('Waiting for the first agent GPS ping.');

            if (!mapHasFitted && userMarker) {
              fitMapToPoints(null, [payload.user_latitude, payload.user_longitude]);
              mapHasFitted = true;
            }

            return;
          }

          const agentLatLng = [payload.agent_latitude, payload.agent_longitude];
          const userLatLng = payload.user_latitude !== null && payload.user_longitude !== null
            ? [payload.user_latitude, payload.user_longitude]
            : null;

          if (!agentMarker) {
            agentMarker = L.marker(agentLatLng, { icon: agentIcon, zIndexOffset: 800 }).addTo(map);
          } else if (initial) {
            agentMarker.setLatLng(agentLatLng);
          } else {
            animateAgentMarker(agentLatLng);
          }

          if (userLatLng) {
            updateRoute(agentLatLng, userLatLng);
          }

          if (!mapHasFitted) {
            fitMapToPoints(agentLatLng, userLatLng);
            mapHasFitted = true;
          }

          updateStateText(`Last refreshed at ${new Date().toLocaleTimeString()}.`);
        };

        const refreshTracking = async () => {
          if (refreshInFlight) {
            return;
          }

          refreshInFlight = true;

          try {
            const response = await fetch(refreshUrl, {
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
              },
            });

            if (!response.ok) {
              throw new Error('tracking-refresh-failed');
            }

            const payload = await response.json();
            applyPayload(payload);
          } catch (error) {
            updateStateText('Unable to refresh the route right now. We will retry automatically.');
          } finally {
            refreshInFlight = false;
          }
        };

        applyPayload(initialPayload, { initial: true });
        window.setTimeout(() => map.invalidateSize(), 120);
        window.addEventListener('resize', () => map.invalidateSize());
        refreshTimer = window.setInterval(refreshTracking, refreshMs);

        window.addEventListener('beforeunload', () => {
          stopAnimation();
          if (refreshTimer) {
            window.clearInterval(refreshTimer);
          }
        });
      });
    </script>
  @endif
@endsection
