@extends('layouts.app')
@section('title', 'Active Delivery')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Active Delivery</h4>
  <div class="small text-muted" id="agentActiveGpsStatus">Share your live location to keep ETA accurate.</div>
</div>

@if(!$order)
<div class="card text-center py-5"><div class="card-body"><i class="fas fa-box fa-4x text-muted opacity-25 mb-3"></i><h5 class="fw-bold">No Active Delivery</h5><p class="text-muted">Accept a request to start a delivery.</p><a href="{{ route('agent.requests') }}" class="btn btn-primary">View Requests</a></div></div>
@else
@php $mapsUrl = $order->googleMapsDirectionsUrl(auth()->user()->agent?->current_lat, auth()->user()->agent?->current_lng); @endphp
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card mb-4">
      <div class="card-header">Order #{{ $order->displayOrderNumber() }} Details</div>
      <div class="card-body">
        @php $billing = $order->billing; @endphp
        <div class="row g-2">
          <div class="col-6"><small class="text-muted">Fuel</small><div class="fw-bold">{{ $order->fuelProduct->name ?? '—' }}</div></div>
          <div class="col-6"><small class="text-muted">Quantity</small><div class="fw-bold">{{ $order->quantity_liters }}L</div></div>
          <div class="col-6"><small class="text-muted">Total Amount</small><div class="fw-bold text-primary">₹{{ number_format((float) ($billing?->total_amount ?? $order->total_amount), 2) }}</div></div>
          <div class="col-6"><small class="text-muted">Your Earning</small><div class="fw-bold text-success">₹{{ number_format((float) ($billing?->agent_earning ?? 0), 2) }}</div></div>
          <div class="col-6"><small class="text-muted">Payment</small><div class="fw-bold text-uppercase">{{ $order->paymentMethodLabel() }}</div></div>
          <div class="col-6"><small class="text-muted">Status</small><span class="badge badge-status-{{ $order->status }}">{{ ucwords(str_replace('_',' ',$order->status)) }}</span></div>
          @if($order->estimated_delivery_minutes)
          <div class="col-6"><small class="text-muted">Live ETA</small><div class="fw-bold text-primary" id="agentLiveEta">{{ $order->estimated_delivery_minutes }} min</div></div>
          @endif
          @if($order->distance_km !== null)
          <div class="col-6"><small class="text-muted">Distance to Customer</small><div class="fw-bold" id="agentDistanceToCustomer">{{ number_format((float) $order->distance_km, 1) }} km</div></div>
          @endif
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Customer Details</div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="circle-avatar-sm bg-primary bg-opacity-10 d-flex align-items-center justify-content-center">
            <span class="fw-bold text-primary">{{ substr($order->user->name ?? 'C', 0, 1) }}</span>
          </div>
          <div>
            <div class="fw-bold">{{ $order->user->name ?? '—' }}</div>
            <div class="text-muted small">{{ $order->user->email ?? '' }}</div>
          </div>
        </div>
        @if($order->user->phone)
        <a href="tel:{{ $order->user->phone }}" class="btn btn-outline-primary w-100 mb-3"><i class="fas fa-phone me-2"></i>{{ $order->user->phone }}</a>
        @endif
        <div class="small text-muted"><i class="fas fa-map-marker-alt me-2"></i>{{ $order->delivery_address }}</div>
        <div class="small text-muted mt-2"><i class="fas fa-map-pin me-2"></i>{{ $order->usesPinnedMapLocation() ? 'Pinned map destination' : 'Live customer GPS' }}</div>
        @if($order->hasDeliveryCoordinates())
        <div class="small text-muted mt-2"><i class="fas fa-location-crosshairs me-2"></i>{{ $order->displayLocationLabel() }}: {{ $order->displayLocationAddress() }}</div>
        @endif
        @if($mapsUrl)
        <a href="{{ $mapsUrl }}" target="_blank" class="btn btn-outline-secondary w-100 mt-3 btn-sm" id="agentNavigationLink"><i class="fas fa-map me-2"></i>{{ $order->usesPinnedMapLocation() ? 'Navigate to Pinned Location' : 'Navigate in Google Maps' }}</a>
        @endif
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">Update Delivery Status</div>
      <div class="card-body">
        @if($order->status === 'accepted')
        <div class="p-4 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-25 mb-3">
          <p class="mb-1 text-muted small">Current Status:</p>
          <div class="fw-bold fs-5 mb-3">{{ ucwords(str_replace('_',' ',$order->status)) }}</div>
          <form method="POST" action="{{ route('agent.status', $order->id) }}">
            @csrf
            <input type="hidden" name="current_lat" class="agent-status-lat">
            <input type="hidden" name="current_lng" class="agent-status-lng">
            <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-gas-pump me-2"></i>Start Fuel Preparation</button>
          </form>
        </div>
        <div class="small text-muted mb-4">Move the order into fuel preparation before dispatch begins.</div>
        <div class="border-top pt-4">
          <div class="fw-semibold mb-3">Cancel This Order</div>
          <form method="POST" action="{{ route('agent.cancel', $order->id) }}">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-600">Reason</label>
              <select name="reason" class="form-select" required>
                <option value="">Select a reason</option>
                @foreach(['Vehicle issue', 'Fuel unavailable', 'Location unreachable', 'Emergency'] as $reason)
                <option value="{{ $reason }}">{{ $reason }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Cancel this order?')"><i class="fas fa-ban me-2"></i>Cancel Order</button>
          </form>
        </div>
        @elseif($order->status === 'fuel_preparing')
        <div class="p-4 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-25 mb-3">
          <p class="mb-1 text-muted small">Current Status:</p>
          <div class="fw-bold fs-5 mb-3">{{ ucwords(str_replace('_',' ',$order->status)) }}</div>
          <form method="POST" action="{{ route('agent.status', $order->id) }}">
            @csrf
            <input type="hidden" name="current_lat" class="agent-status-lat">
            <input type="hidden" name="current_lng" class="agent-status-lng">
            <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-truck me-2"></i>Dispatch Order</button>
          </form>
        </div>
        <div class="small text-muted mb-4">Dispatch once fuel is ready and the delivery vehicle is moving.</div>
        <div class="border-top pt-4">
          <div class="fw-semibold mb-3">Cancel This Order</div>
          <form method="POST" action="{{ route('agent.cancel', $order->id) }}">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-600">Reason</label>
              <select name="reason" class="form-select" required>
                <option value="">Select a reason</option>
                @foreach(['Vehicle issue', 'Fuel unavailable', 'Location unreachable', 'Emergency'] as $reason)
                <option value="{{ $reason }}">{{ $reason }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Cancel this order?')"><i class="fas fa-ban me-2"></i>Cancel Order</button>
          </form>
        </div>
        @elseif($order->status === 'on_the_way')
        <div class="p-4 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-25 mb-3">
          <p class="mb-1 text-muted small">Current Status:</p>
          <div class="fw-bold fs-5 mb-3">{{ ucwords(str_replace('_',' ',$order->status)) }}</div>
          <form method="POST" action="{{ route('agent.status', $order->id) }}">
            @csrf
            <input type="hidden" name="current_lat" class="agent-status-lat">
            <input type="hidden" name="current_lng" class="agent-status-lng">
            <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-location-dot me-2"></i>Mark Arrived</button>
          </form>
        </div>
        <div class="small text-muted">Use this once you reach the customer location and are ready for handoff.</div>
        @elseif($order->status === 'arrived')
        <div class="p-4 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-25 mb-3">
          <p class="mb-1 text-muted small">Current Status:</p>
          <div class="fw-bold fs-5 mb-3">{{ ucwords(str_replace('_',' ',$order->status)) }}</div>
          <form method="POST" action="{{ route('agent.status', $order->id) }}">
            @csrf
            <input type="hidden" name="current_lat" class="agent-status-lat">
            <input type="hidden" name="current_lng" class="agent-status-lng">
            <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-key me-2"></i>Generate Delivery OTP</button>
          </form>
        </div>
        <div class="small text-muted">Generate the OTP only after the customer is ready to verify the delivery.</div>
        @elseif($order->status === 'otp_verification')
        <div class="p-4 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-25 mb-3">
          <p class="mb-1 text-muted small">Current Status:</p>
          <div class="fw-bold fs-5 mb-3">{{ ucwords(str_replace('_',' ',$order->status)) }}</div>

          <div class="alert alert-warning mb-3">
            <div class="fw-bold small mb-1">Waiting for customer OTP</div>
            <div class="small mb-0">Ask the customer for the 6-digit demo OTP shown on their tracking page, then enter it here to mark the order delivered.</div>
          </div>
          <form method="POST" action="{{ route('agent.status', $order->id) }}">
            @csrf
            <input type="hidden" name="current_lat" class="agent-status-lat">
            <input type="hidden" name="current_lng" class="agent-status-lng">
            <div class="mb-3">
              <label class="form-label fw-600">Customer Delivery OTP</label>
              <input type="text" name="delivery_otp" class="form-control form-control-lg text-center fw-bold" maxlength="6" placeholder="Enter 6-digit OTP" inputmode="numeric" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-check-circle me-2"></i>Verify OTP & Mark as Delivered</button>
          </form>
        </div>
        <div class="small text-muted">This extra OTP step helps confirm that the fuel has actually reached the customer before the order moves to delivered.</div>
        @elseif($order->status === 'delivered')
        <div class="text-center py-4">
          <i class="fas fa-circle-check fa-3x text-success mb-3"></i>
          <h5 class="fw-bold text-success">Delivery Completed!</h5>
          <p class="text-muted">Great work! Your earning of ₹{{ number_format((float) ($order->billing?->agent_earning ?? 0), 2) }} has been recorded.</p>
        </div>
        @elseif(in_array($order->status, ['cancelled', 'refund_processing'], true))
        <div class="text-center py-4">
          <i class="fas fa-ban fa-3x text-danger mb-3"></i>
          <h5 class="fw-bold text-danger">{{ $order->status === 'refund_processing' ? 'Refund Processing' : 'Order Cancelled' }}</h5>
          <p class="text-muted mb-2">{{ $order->cancellationStatusMessage() }}</p>
          @if($order->hasAdditionalCancellationReason())
          <p class="text-muted small mb-0">{{ $order->cancellationReasonMessage() }}</p>
          @endif
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endif
@endsection
@section('scripts')
@if($order)
<script>
document.addEventListener('DOMContentLoaded', () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  const gpsStatus = document.getElementById('agentActiveGpsStatus');
  const etaLabel = document.getElementById('agentLiveEta');
  const distanceLabel = document.getElementById('agentDistanceToCustomer');
  const navigationLink = document.getElementById('agentNavigationLink');
  const latInputs = document.querySelectorAll('.agent-status-lat');
  const lngInputs = document.querySelectorAll('.agent-status-lng');
  const syncUrl = '{{ route('agent.location') }}';

  const syncLocation = () => {
    if (!navigator.geolocation || !csrfToken) {
      if (gpsStatus) {
        gpsStatus.textContent = 'GPS is unavailable in this browser.';
      }
      return;
    }

    navigator.geolocation.getCurrentPosition(async (position) => {
      const lat = Number(position.coords.latitude.toFixed(7));
      const lng = Number(position.coords.longitude.toFixed(7));

      latInputs.forEach((input) => input.value = lat);
      lngInputs.forEach((input) => input.value = lng);

      try {
        const response = await fetch(syncUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({ current_lat: lat, current_lng: lng }),
        });

        if (!response.ok) {
          throw new Error('sync-failed');
        }

        const payload = await response.json();

        if (etaLabel && payload.estimated_delivery_minutes !== null && payload.estimated_delivery_minutes !== undefined) {
          etaLabel.textContent = `${payload.estimated_delivery_minutes} min`;
        }

        if (distanceLabel && payload.distance_km !== null && payload.distance_km !== undefined) {
          distanceLabel.textContent = `${Number(payload.distance_km).toFixed(1)} km`;
        }

        if (navigationLink && payload.navigation_url) {
          navigationLink.href = payload.navigation_url;
        }

        if (gpsStatus) {
          gpsStatus.textContent = `Live location synced at ${new Date().toLocaleTimeString()}.`;
        }
      } catch (error) {
        if (gpsStatus) {
          gpsStatus.textContent = 'Unable to sync your live location right now.';
        }
      }
    }, () => {
      if (gpsStatus) {
        gpsStatus.textContent = 'Allow location access to keep navigation and ETA accurate.';
      }
    }, {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    });
  };

  syncLocation();
  window.setInterval(syncLocation, 10000);
});
</script>
@endif
@endsection
