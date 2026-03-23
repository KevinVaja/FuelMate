@extends('layouts.app')
@section('title', 'Track Order')
@section('content')
<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('user.history') }}">Orders</a></li><li class="breadcrumb-item active">Order #{{ $order->displayOrderNumber() }}</li></ol></nav>
    <h4 class="fw-bold mb-0">Track Order #{{ $order->displayOrderNumber() }}</h4>
  </div>
  @php
    $showCancellationChargePanel = $order->canCustomerPayCancellationCharge()
      && (old('cancellation_charge_payment_method') || session('error'));
  @endphp
  <div class="d-flex gap-2 flex-wrap">
    @if($order->billing)
    <a href="{{ route('orders.invoice', $order->id) }}" class="btn btn-outline-primary"><i class="fas fa-file-invoice me-2"></i>View Invoice</a>
    @endif
    @if($order->canCustomerCancel())
    <form method="POST" action="{{ route('user.orders.cancel', $order->id) }}">
      @csrf
      <input type="hidden" name="reason" value="Cancelled by customer.">
      <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Cancel this order? Cancellation after dispatch will incur high charges.')"><i class="fas fa-ban me-2"></i>Cancel Order</button>
    </form>
    @elseif($order->canCustomerPayCancellationCharge())
    <form method="POST" action="{{ route('user.orders.cancel', $order->id) }}" class="d-flex gap-2 flex-wrap align-items-center">
      @csrf
      <input type="hidden" name="reason" value="Cancelled by customer.">
      <button
        type="button"
        class="btn btn-outline-danger"
        data-bs-toggle="collapse"
        data-bs-target="#codCancellationChargePanel"
        aria-expanded="{{ $showCancellationChargePanel ? 'true' : 'false' }}"
        aria-controls="codCancellationChargePanel"
      ><i class="fas fa-ban me-2"></i>Cancel Order</button>
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
          'pending'=>0,
          'accepted'=>1,
          'fuel_preparing'=>2,
          'on_the_way'=>3,
          'arrived'=>4,
          'otp_verification'=>5,
          'delivered'=>6,
          'refund_processing'=>6,
          'cancelled'=>-1,
          'completed'=>6,
        ];
        $currentStep = $steps[$order->status] ?? 0;
        $displayTimezone = config('app.display_timezone', 'Asia/Kolkata');
        $statusItems = [
          ['label'=>'Order Placed','icon'=>'fa-check','step'=>0],
          ['label'=>'Agent Accepted','icon'=>'fa-user-check','step'=>1],
          ['label'=>'Fuel Preparing','icon'=>'fa-gas-pump','step'=>2],
          ['label'=>'On the Way','icon'=>'fa-truck','step'=>3],
          ['label'=>'Arrived','icon'=>'fa-location-dot','step'=>4],
          ['label'=>'OTP Verification','icon'=>'fa-key','step'=>5],
          ['label'=>'Delivered','icon'=>'fa-circle-check','step'=>6],
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
        @if(!$loop->last)<div class="tracking-step-line ms-1 mb-2"></div>@endif
        @endforeach
      </div>
    </div>

    <div class="card">
      <div class="card-header">Order Details</div>
      <div class="card-body">
        @php $billing = $order->billing; @endphp
        <div class="row g-2">
          <div class="col-6"><small class="text-muted">Fuel</small><div class="fw-bold">{{ $order->fuelProduct->name ?? '—' }}</div></div>
          <div class="col-6"><small class="text-muted">Quantity</small><div class="fw-bold">{{ $order->quantity_liters }}L</div></div>
          <div class="col-6"><small class="text-muted">Fuel Total</small><div class="fw-bold">₹{{ number_format((float) ($billing?->fuel_total ?? 0), 2) }}</div></div>
          <div class="col-6"><small class="text-muted">Delivery Charge</small><div class="fw-bold">₹{{ number_format((float) ($order->slab_charge ?: ($billing?->delivery_charge ?? $order->delivery_charge)), 2) }}</div></div>
          @if((float) $order->night_fee > 0)
          <div class="col-6"><small class="text-muted">Night Delivery Extra</small><div class="fw-bold text-warning">₹{{ number_format((float) $order->night_fee, 2) }}</div></div>
          @endif
          <div class="col-6"><small class="text-muted">Platform Fee</small><div class="fw-bold">₹{{ number_format((float) ($billing?->platform_fee ?? 0), 2) }}</div></div>
          <div class="col-6"><small class="text-muted">GST</small><div class="fw-bold">₹{{ number_format((float) ($billing?->gst_amount ?? 0), 2) }}</div></div>
          <div class="col-12 pt-2 border-top"><small class="text-muted">Total Amount</small><div class="fs-5 fw-bold text-primary">₹{{ number_format((float) ($billing?->total_amount ?? $order->total_amount), 2) }}</div></div>
          <div class="col-6"><small class="text-muted">Payment</small><div class="fw-bold text-uppercase">{{ $order->paymentMethodLabel() }}</div></div>
          <div class="col-6"><small class="text-muted">Payment Status</small>
            <span class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : 'warning' }} text-{{ $order->payment_status === 'paid' ? 'white' : 'dark' }}">{{ ucfirst($order->payment_status) }}</span>
          </div>
          <div class="col-6"><small class="text-muted">Billing Status</small>
            <span class="badge bg-light text-dark text-uppercase">{{ $billing?->billing_status ?? 'estimated' }}</span>
          </div>
          <div class="col-6"><small class="text-muted">Invoice</small><div class="fw-bold">{{ $billing ? 'Available' : 'Preparing' }}</div></div>
          @if($order->is_cancelled)
          <div class="col-6"><small class="text-muted">Cancellation Charge</small><div class="fw-bold text-danger">₹{{ number_format((float) $order->cancellation_charge, 2) }}</div></div>
          <div class="col-6"><small class="text-muted">Refund Status</small><div class="fw-bold text-uppercase">{{ $billing?->refund_status ?? 'none' }}</div></div>
          @if($order->cancellationChargePaymentIsSettled())
          <div class="col-6"><small class="text-muted">Cancellation Fee Payment</small><div class="fw-bold">{{ $order->cancellationChargePaymentMethodLabel() ?? 'Paid' }}</div></div>
          <div class="col-6"><small class="text-muted">Charge Payment Status</small><div class="fw-bold text-success text-uppercase">{{ $order->cancellation_charge_payment_status }}</div></div>
          @endif
          @endif
          <div class="col-12"><small class="text-muted">Delivery Address</small><div>{{ $order->delivery_address }}</div></div>
          <div class="col-6"><small class="text-muted">Location Mode</small><div class="fw-bold">{{ $order->usesPinnedMapLocation() ? 'Pinned Map Location' : 'Live Device GPS' }}</div></div>
          @if($order->hasDeliveryCoordinates())
          <div class="col-6"><small class="text-muted">{{ $order->displayLocationLabel() }}</small><div class="fw-bold">{{ $order->displayLocationAddress() }}</div></div>
          @endif
          <div class="col-12"><small class="text-muted">Distance</small><div>{{ $order->distance_km }} km · Est. {{ $order->estimated_delivery_minutes }} min</div></div>
          @if(in_array($order->status, ['accepted', 'fuel_preparing', 'on_the_way', 'arrived', 'otp_verification'], true) && $order->estimated_delivery_minutes)
          <div class="col-12">
            <small class="text-muted">Live ETA</small>
            <div class="fw-bold text-primary" id="liveEtaSummary">{{ $order->estimated_delivery_minutes }} min · {{ number_format((float) $order->distance_km, 1) }} km away</div>
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
          <div class="small text-muted mb-3">The cancellation fee will only be charged if you continue with cancellation.</div>
          <div class="small text-muted mb-2">Cancellation fee to pay now</div>
          <div class="fs-4 fw-bold text-danger mb-3">₹{{ number_format($order->customerCancellationChargeAmount(), 2) }}</div>
          <form method="POST" action="{{ route('user.orders.cancel', $order->id) }}" class="d-flex gap-2 flex-wrap align-items-end">
            @csrf
            <input type="hidden" name="reason" value="Cancelled by customer.">
            <div>
              <label class="form-label small text-muted mb-1">Payment method</label>
              <select name="cancellation_charge_payment_method" class="form-select" style="min-width: 170px;">
                <option value="online" {{ old('cancellation_charge_payment_method', 'online') === 'online' ? 'selected' : '' }}>Pay Online</option>
                <option value="wallet" {{ old('cancellation_charge_payment_method') === 'wallet' ? 'selected' : '' }}>Use Wallet</option>
              </select>
            </div>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Pay ₹{{ number_format($order->customerCancellationChargeAmount(), 2) }} as the cancellation fee and cancel this order?')"><i class="fas fa-credit-card me-2"></i>Pay Fee & Cancel</button>
          </form>
        </div>
      </div>
    </div>
    @endif

    @if(in_array($order->status, ['pending','accepted','fuel_preparing','on_the_way','arrived','otp_verification'], true) && $order->usesLiveCustomerLocation())
    <div class="alert alert-info d-flex justify-content-between align-items-center gap-3">
      <div>
        <div class="fw-bold">Live location sharing is active</div>
        <div class="small mb-0" id="locationSyncStatus">We will keep your order location updated while this page stays open.</div>
      </div>
      <i class="fas fa-location-dot fs-4 text-primary"></i>
    </div>
    @elseif(in_array($order->status, ['pending','accepted','fuel_preparing','on_the_way','arrived','otp_verification'], true))
    <div class="alert alert-secondary d-flex justify-content-between align-items-center gap-3">
      <div>
        <div class="fw-bold">Pinned delivery location selected</div>
        <div class="small mb-0">This order uses a fixed map pin, so we will keep that selected destination unchanged for your agent.</div>
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
            <div class="text-muted small">{{ $order->agent->vehicle_type }} · {{ $order->agent->vehicle_license_plate }}</div>
            @if($order->agent->rating)
            <div class="text-warning small">{{ str_repeat('★', round($order->agent->rating)) }} {{ $order->agent->rating }}</div>
            @endif
          </div>
          @if($order->agent->user->phone)
          <a href="tel:{{ $order->agent->user->phone }}" class="btn btn-outline-primary ms-auto"><i class="fas fa-phone me-2"></i>Call</a>
          @endif
        </div>
      </div>
    </div>
    @endif

    @if(in_array($order->status, ['arrived', 'otp_verification', 'delivered'], true))
    <div class="card mb-4">
      <div class="card-header">Delivery OTP</div>
      <div class="card-body text-center">
        @if($order->hasPendingDeliveryOtp())
        <p class="text-muted mb-3">Share this demo OTP with your agent only after the fuel has reached you.</p>
        <div class="display-5 fw-bold text-primary mb-2">{{ $order->delivery_otp }}</div>
        <div class="small text-muted">Generated at {{ $order->delivery_otp_generated_at?->copy()->timezone($displayTimezone)->format('d M Y, h:i A') ?? 'just now' }}</div>
        @elseif($order->deliveryOtpWasVerified())
        <div class="text-success fw-bold mb-2"><i class="fas fa-circle-check me-2"></i>Delivery OTP verified</div>
        <p class="text-muted mb-0">Your agent has successfully verified the delivery handoff.</p>
        @else
        <p class="text-muted mb-0">When your agent reaches you, they will generate a delivery OTP here. Tell that OTP to the agent so they can mark the order delivered.</p>
        @endif
      </div>
    </div>
    @endif

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Live Tracking</span>
        @if(in_array($order->status, ['pending','accepted','fuel_preparing','on_the_way','arrived','otp_verification'], true))
        <button class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="fas fa-sync me-1"></i>Refresh</button>
        @endif
      </div>
      <div class="card-body text-center py-5 tracking-stage-panel">
        @php
          $mapsUrl = $order->googleMapsDirectionsUrl($order->agent?->current_lat, $order->agent?->current_lng);
          $destinationMapsUrl = $order->googleMapsDestinationUrl();
        @endphp
        @if($order->status === 'pending')
          <i class="fas fa-hourglass-half fa-3x text-warning mb-3"></i>
          <h6 class="fw-bold">Finding an Agent</h6>
          <p class="text-muted small">We're looking for the nearest available agent for your request.</p>
          @if($destinationMapsUrl)
          <a href="{{ $destinationMapsUrl }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="fas fa-map me-1"></i>View Delivery Pin</a>
          @endif
        @elseif($order->status === 'accepted')
          <i class="fas fa-user-check fa-3x text-primary mb-3"></i>
          <h6 class="fw-bold">Agent Found!</h6>
          <p class="text-muted small" id="trackingSummary">Your agent has accepted the request and is getting started. Estimated: {{ $order->estimated_delivery_minutes }} mins</p>
          @if($destinationMapsUrl)
          <a href="{{ $destinationMapsUrl }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="fas fa-map me-1"></i>View Delivery Pin</a>
          @endif
        @elseif($order->status === 'fuel_preparing')
          <i class="fas fa-gas-pump fa-3x text-primary mb-3"></i>
          <h6 class="fw-bold">Fuel Is Being Prepared</h6>
          <p class="text-muted small" id="trackingSummary">Your assigned agent is preparing the fuel for dispatch.</p>
          @if($destinationMapsUrl)
          <a href="{{ $destinationMapsUrl }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="fas fa-map me-1"></i>View Delivery Pin</a>
          @endif
        @elseif($order->status === 'on_the_way')
          <i class="fas fa-truck fa-3x text-primary mb-3 animate-bounce"></i>
          <h6 class="fw-bold">On the Way!</h6>
          <p class="text-muted small" id="trackingSummary">Your agent is heading towards you. Distance: {{ number_format((float) $order->distance_km, 1) }} km · ETA {{ $order->estimated_delivery_minutes }} mins</p>
          @if($mapsUrl)
          <a href="{{ $mapsUrl }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="fas fa-map me-1"></i>Open in Maps</a>
          @endif
        @elseif($order->status === 'arrived')
          <i class="fas fa-location-dot fa-3x text-primary mb-3"></i>
          <h6 class="fw-bold">Agent Arrived</h6>
          <p class="text-muted small">Your agent is at the location and will start OTP handoff shortly.</p>
        @elseif($order->status === 'otp_verification')
          <i class="fas fa-key fa-3x text-primary mb-3"></i>
          <h6 class="fw-bold">OTP Verification In Progress</h6>
          <p class="text-muted small">Share the OTP only after the fuel handoff is complete.</p>
        @elseif($order->status === 'delivered')
          <i class="fas fa-gas-pump fa-3x text-success mb-3"></i>
          <h6 class="fw-bold">Fuel Delivered!</h6>
          <p class="text-muted small">Your order has been completed and settlement has been recorded.</p>
        @elseif($order->status === 'refund_processing')
          <i class="fas fa-rotate fa-3x text-warning mb-3"></i>
          <h6 class="fw-bold text-warning">Refund Processing</h6>
          <p class="text-muted small">{{ $order->cancellationStatusMessage() }} The refund is waiting for admin approval.</p>
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
@if(in_array($order->status, ['pending','accepted','fuel_preparing','on_the_way','arrived','otp_verification'], true) && $order->usesLiveCustomerLocation())
<script>
document.addEventListener('DOMContentLoaded', () => {
  const syncStatus = document.getElementById('locationSyncStatus');
  const trackingSummary = document.getElementById('trackingSummary');
  const liveEtaSummary = document.getElementById('liveEtaSummary');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  const syncUrl = '{{ route('user.track.location', $order->id) }}';

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

  const syncLocation = () => {
    if (!navigator.geolocation || !csrfToken) {
      if (syncStatus) {
        syncStatus.textContent = 'Browser GPS is unavailable. Refresh after enabling location services.';
      }
      return;
    }

    navigator.geolocation.getCurrentPosition(async (position) => {
      try {
        const response = await fetch(syncUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({
            delivery_lat: Number(position.coords.latitude.toFixed(7)),
            delivery_lng: Number(position.coords.longitude.toFixed(7)),
          }),
        });

        if (!response.ok) {
          throw new Error('sync-failed');
        }

        const payload = await response.json();
        updateSummaries(payload);

        if (syncStatus) {
          syncStatus.textContent = `Live location synced. Last update at ${new Date().toLocaleTimeString()}.`;
        }
      } catch (error) {
        if (syncStatus) {
          syncStatus.textContent = 'Unable to sync your live location right now. We will try again shortly.';
        }
      }
    }, () => {
      if (syncStatus) {
        syncStatus.textContent = 'Location permission is blocked. Your order will use the last shared GPS point.';
      }
    }, {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    });
  };

  syncLocation();
  const syncTimer = window.setInterval(syncLocation, 10000);

  window.setTimeout(() => {
    clearInterval(syncTimer);
    window.location.reload();
  }, 15000);
});
</script>
@endif
@if(in_array($order->status, ['accepted','fuel_preparing','on_the_way','arrived','otp_verification'], true) && $order->usesPinnedMapLocation())
<script>
document.addEventListener('DOMContentLoaded', () => {
  window.setTimeout(() => {
    window.location.reload();
  }, 15000);
});
</script>
@endif
@endsection
