@extends('layouts.app')
@section('title', 'Available Requests')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Available Requests</h4><p class="text-muted mb-0">Fuel delivery requests near you</p></div>
  <div class="text-end">
    <div class="small text-muted mb-2" id="agentGpsStatus">Sharing your live location helps calculate customer ETA.</div>
    <a href="{{ route('agent.requests') }}" class="btn btn-outline-primary"><i class="fas fa-sync me-2"></i>Refresh</a>
  </div>
</div>

@php $agent = auth()->user()->agent; @endphp
@if(!$agent->isApprovedForOperations())
  <div class="alert alert-warning">
    <i class="fas fa-clock me-2"></i>Your petrol pump account is under verification. You cannot accept orders yet.
    @if($agent->verification_status === 'rejected' && $agent->rejection_reason)
      <div class="small mt-2"><strong>Reason:</strong> {{ $agent->rejection_reason }}</div>
    @endif
  </div>
@elseif(!$agent->is_available)
  <div class="alert alert-secondary"><i class="fas fa-moon me-2"></i>You are currently offline. Toggle your availability on the dashboard to start receiving requests.</div>
@elseif($requests->isEmpty())
  <div class="card text-center py-5"><div class="card-body"><i class="fas fa-map-marker-alt fa-4x text-muted opacity-25 mb-3"></i><h5 class="fw-bold">No pending requests</h5><p class="text-muted">New delivery requests will appear here automatically.</p></div></div>
@else
  <div class="d-flex flex-column gap-3">
    @foreach($requests as $request)
    @php $mapsUrl = $request->googleMapsDirectionsUrl($agent->trackingLatitude(), $agent->trackingLongitude()); @endphp
    <div class="card">
      <div class="card-body">
        <div class="row align-items-center g-3">
          <div class="col-auto"><div class="bg-primary bg-opacity-10 rounded-3 p-3"><i class="fas fa-gas-pump text-primary fs-4"></i></div></div>
          <div class="col">
            <div class="row g-2">
              <div class="col-md-6">
                <div class="fw-bold fs-5">{{ $request->fuelProduct->name ?? '—' }}</div>
                <div class="text-muted small">
                  {{ $request->quantity_liters }}L ·
                  @if($request->distance_km !== null)
                    {{ number_format((float) $request->distance_km, 1) }} km away
                  @else
                    Waiting for GPS
                  @endif
                </div>
              </div>
              <div class="col-md-6">
                <div class="small"><i class="fas fa-user text-muted me-1"></i>{{ $request->user->name ?? '—' }}</div>
                <div class="small"><i class="fas fa-phone text-muted me-1"></i>{{ $request->user->phone ?? '—' }}</div>
                <div class="small"><i class="fas fa-map-marker-alt text-muted me-1"></i>{{ $request->delivery_address }}</div>
              </div>
              <div class="col-md-6">
                <div class="small"><i class="fas fa-clock text-muted me-1"></i>Est. {{ $request->estimated_delivery_minutes ?? '—' }} min</div>
                <div class="small">Payment: <strong class="text-uppercase">{{ $request->paymentMethodLabel() }}</strong></div>
                <div class="small"><i class="fas fa-map-pin text-muted me-1"></i>{{ $request->usesPinnedMapLocation() ? 'Pinned map destination' : 'Live customer GPS' }}</div>
                @if($request->hasDeliveryCoordinates())
                <div class="small"><i class="fas fa-location-crosshairs text-muted me-1"></i>{{ $request->displayLocationLabel() }}: {{ $request->displayLocationAddress() }}</div>
                @endif
              </div>
            </div>
          </div>
          <div class="col-auto text-end">
            <div class="fs-4 fw-bold text-primary">₹{{ number_format((float) ($request->billing?->total_amount ?? $request->total_amount), 0) }}</div>
            <div class="text-muted small">Earn: ₹{{ number_format((float) ($request->billing?->agent_earning ?? 0), 0) }}</div>
            @if($mapsUrl)
            <a href="{{ $mapsUrl }}" target="_blank" class="btn btn-outline-secondary btn-sm mt-2 d-block"><i class="fas fa-map-location-dot me-2"></i>{{ $request->usesPinnedMapLocation() ? 'Navigate to Pin' : 'Navigate' }}</a>
            @endif
            <form method="POST" action="{{ route('agent.accept', $request->id) }}" class="mt-2">
              @csrf
              <input type="hidden" name="current_lat" class="agent-current-lat">
              <input type="hidden" name="current_lng" class="agent-current-lng">
              <button type="submit" class="btn btn-primary">Accept Order</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    @endforeach
  </div>
@endif
@endsection
@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  const gpsStatus = document.getElementById('agentGpsStatus');
  const latInputs = document.querySelectorAll('.agent-current-lat');
  const lngInputs = document.querySelectorAll('.agent-current-lng');
  const syncUrl = '{{ route('api.agent.location.update') }}';
  const syncIntervalMs = 15000;
  let latestCoordinates = null;
  let lastSentAt = 0;
  let syncInFlight = false;
  let tracker = null;

  const fillFormCoordinates = (lat, lng) => {
    latInputs.forEach((input) => input.value = lat);
    lngInputs.forEach((input) => input.value = lng);
  };

  const sendLatestLocation = async (force = false) => {
    if (!latestCoordinates || !csrfToken || syncInFlight) {
      return;
    }

    const now = Date.now();
    if (!force && now - lastSentAt < syncIntervalMs - 500) {
      return;
    }

    syncInFlight = true;

    try {
      await fetch(syncUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          latitude: latestCoordinates.lat,
          longitude: latestCoordinates.lng,
        }),
      });

      lastSentAt = now;

      if (gpsStatus) {
        gpsStatus.textContent = `Live location synced at ${new Date().toLocaleTimeString()}. New request distances will stay accurate.`;
      }
    } catch (error) {
      if (gpsStatus) {
        gpsStatus.textContent = 'Unable to sync GPS right now.';
      }
    } finally {
      syncInFlight = false;
    }
  };

  if (!window.FuelMateGps || !csrfToken) {
    if (gpsStatus) {
      gpsStatus.textContent = 'GPS services are unavailable in this browser.';
    }
    return;
  }

  tracker = window.FuelMateGps.createTracker({
    onAccepted(location) {
      latestCoordinates = {
        lat: location.latitude,
        lng: location.longitude,
        accuracyMeters: location.accuracyMeters,
      };

      fillFormCoordinates(location.latitude, location.longitude);

      if (lastSentAt === 0) {
        sendLatestLocation(true);
      } else if (gpsStatus) {
        gpsStatus.textContent = `Precise location ready (${Math.round(location.accuracyMeters)}m). Syncing on the next 15-second heartbeat.`;
      }
    },
    onStatus(message) {
      if (gpsStatus) {
        gpsStatus.textContent = message;
      }
    },
  });

  tracker.start();

  window.setInterval(() => {
    sendLatestLocation();
  }, syncIntervalMs);

  window.addEventListener('beforeunload', () => {
    if (tracker) {
      tracker.stop();
    }
  });
});
</script>
@endsection
