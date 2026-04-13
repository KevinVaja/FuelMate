@extends('layouts.app')

@section('title', 'Order Fuel')

@section('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endsection

@section('content')
<div class="mb-4">
  <h4 class="fw-bold mb-0">Order Fuel</h4>
  <p class="text-muted">Request emergency fuel delivery to your location or another pinned destination.</p>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Fuel Order Details</div>
      <div class="card-body">
        @if ($errors->any())
          <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('user.order.place') }}" id="fuelOrderForm">
          @csrf

          <div class="mb-3">
            <label class="form-label fw-600">Select Fuel Type</label>
            <div class="row g-3">
              @foreach ($products as $i => $product)
                <div class="col-md-6">
                  <input type="radio" class="btn-check" name="fuel_product_id" id="fuel{{ $product->id }}" value="{{ $product->id }}" data-price="{{ number_format($product->price_per_liter, 2, '.', '') }}" {{ $i === 0 ? 'checked' : '' }}>
                  <label class="card w-100 border-2 fuel-option option-card" for="fuel{{ $product->id }}">
                    <div class="card-body p-3">
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <div class="fw-bold">{{ $product->name }}</div>
                          <div class="text-muted small">{{ ucwords(str_replace('_', ' ', $product->fuel_type)) }}</div>
                        </div>
                        <div class="text-primary fw-bold">₹{{ number_format($product->price_per_liter, 2) }}/L</div>
                      </div>
                    </div>
                  </label>
                </div>
              @endforeach
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-600">Quantity (Litres)</label>
              <input type="number" name="quantity_liters" class="form-control" min="1" max="50" value="{{ old('quantity_liters', 5) }}" required>
              <div class="form-text">Min 1L - Max 50L per order</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-600">Payment Method</label>
              <select name="payment_method" class="form-select">
                <option value="cod" {{ old('payment_method', 'cod') === 'cod' ? 'selected' : '' }}>Cash on Delivery</option>
                <option value="online" {{ old('payment_method') === 'online' ? 'selected' : '' }}>Online</option>
                <option value="wallet" {{ old('payment_method') === 'wallet' ? 'selected' : '' }}>Wallet</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-600">Delivery Address</label>
            <input type="text" name="delivery_address" id="deliveryAddress" class="form-control @error('delivery_address') is-invalid @enderror" placeholder="e.g. Near Petrol Pump, Andheri West, Mumbai" value="{{ old('delivery_address') }}" required>
            <div class="form-text">Enter a nearby landmark or address. If you choose a map pin below, this should still describe the destination clearly for the agent.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-600">Delivery Location Method</label>
            <div class="row g-3">
              <div class="col-md-6">
                <input type="radio" class="btn-check" name="location_mode" id="locationModeLive" value="live_gps" {{ old('location_mode', 'live_gps') === 'live_gps' ? 'checked' : '' }}>
                <label class="card w-100 border-2 location-mode-option option-card" for="locationModeLive">
                  <div class="card-body p-3">
                    <div class="fw-bold text-primary mb-1"><i class="fas fa-location-crosshairs me-2"></i>Use My Current Location</div>
                    <div class="small text-muted">Best when you are ordering fuel for yourself and want live GPS capture from this device.</div>
                  </div>
                </label>
              </div>
              <div class="col-md-6">
                <input type="radio" class="btn-check" name="location_mode" id="locationModeMap" value="map_pin" {{ old('location_mode') === 'map_pin' ? 'checked' : '' }}>
                <label class="card w-100 border-2 location-mode-option option-card" for="locationModeMap">
                  <div class="card-body p-3">
                    <div class="fw-bold text-primary mb-1"><i class="fas fa-map-location-dot me-2"></i>Pick Another Location on Map</div>
                    <div class="small text-muted">Use this when you are ordering fuel for someone else or for a different destination.</div>
                  </div>
                </label>
              </div>
            </div>
          </div>

          <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10 mb-3" id="liveLocationCard">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                  <div class="fw-bold text-primary mb-1"><i class="fas fa-location-crosshairs me-2"></i>Live GPS Location</div>
                  <div class="small text-muted" id="liveLocationStatus">Waiting to capture your current location.</div>
                  <div class="small fw-semibold mt-2 d-none" id="liveLocationCoordinates"></div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="captureLocationBtn"><i class="fas fa-crosshairs me-2"></i>Use My Live Location</button>
              </div>
              <div class="small text-muted mt-3">Location access works on localhost and HTTPS. FuelMate stores this GPS point so the agent can navigate directly to you.</div>
            </div>
          </div>

          <div class="card border-primary border-opacity-25 bg-light mb-3 d-none" id="mapLocationCard">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                  <div class="fw-bold text-primary mb-1"><i class="fas fa-map me-2"></i>Pick Delivery Point on Map</div>
                  <div class="small text-muted" id="mapLocationStatus">Search for an area or click anywhere on the map to drop the delivery pin.</div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="centerMapOnCurrentBtn"><i class="fas fa-location-arrow me-2"></i>Center Map on Me</button>
              </div>

              <div class="input-group input-group-sm mt-3">
                <input type="text" class="form-control" id="mapSearchInput" placeholder="Search area, road, landmark, or city">
                <button type="button" class="btn btn-outline-secondary" id="mapSearchBtn">Search</button>
              </div>

              <div class="list-group mt-2 d-none" id="mapSearchResults"></div>
              <div id="deliveryLocationMap" class="rounded border mt-3"></div>
              <div class="small fw-semibold mt-3 d-none" id="mapLocationCoordinates"></div>
              <a href="#" id="mapPreviewLink" target="_blank" class="btn btn-outline-secondary btn-sm mt-3 d-none"><i class="fas fa-map-location-dot me-2"></i>Open Selected Pin in Google Maps</a>
              <div class="small text-muted mt-3">Clicking the map stores a fixed destination for this order. The app will not replace it later with your current device GPS.</div>
            </div>
          </div>

          <input type="hidden" name="delivery_lat" id="deliveryLat" value="{{ old('delivery_lat') }}">
          <input type="hidden" name="delivery_lng" id="deliveryLng" value="{{ old('delivery_lng') }}">

          <div class="mb-3">
            <label class="form-label fw-600">Notes (optional)</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Any specific instructions...">{{ old('notes') }}</textarea>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2" id="placeOrderBtn"><i class="fas fa-bolt me-2"></i>Place Order</button>
        </form>

        <div class="modal fade" id="otpModal" tabindex="-1" aria-labelledby="otpModalTitle" aria-hidden="true" data-bs-backdrop="static">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="otpModalTitle">Email Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body text-center">
                <p class="text-muted mb-3">We will send a 6-digit code to your registered email. Enter that code below to confirm and place this order.</p>
                <div id="otpStatus" class="text-muted small mb-3">We are preparing your email verification code.</div>
                <input type="text" id="otpInput" class="form-control text-center fw-bold" placeholder="Enter 6-digit code" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
                <div id="otpError" class="text-danger small mt-2 d-none">Invalid verification code</div>
              </div>
              <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-primary" id="verifyOtpBtn">Verify Email &amp; Place Order</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4 mt-3 mt-lg-0">
    <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10 mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h6 class="fw-bold text-primary mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Estimated Bill</h6>
            <div class="small text-muted">Fuel, delivery, platform fee, and GST are previewed here before you confirm.</div>
          </div>
          <span class="badge bg-primary-subtle text-primary-emphasis" id="billingPreviewBadge">Waiting</span>
        </div>
        <div class="small text-muted mb-3" id="billingPreviewMessage">Capture your delivery location to preview the estimated payable amount.</div>
        <div class="d-flex justify-content-between small mb-2"><span>Fuel total</span><strong id="estimateFuelTotal">₹0.00</strong></div>
        <div class="d-flex justify-content-between small mb-2"><span>Delivery charge</span><strong id="estimateDeliveryCharge">₹0.00</strong></div>
        <div class="d-flex justify-content-between small mb-2"><span>Night delivery extra</span><strong id="estimateNightFee">₹0.00</strong></div>
        <div class="d-flex justify-content-between small mb-2"><span>Platform fee</span><strong id="estimatePlatformFee">₹0.00</strong></div>
        <div class="d-flex justify-content-between small mb-2"><span>GST</span><strong id="estimateGstAmount">₹0.00</strong></div>
        <div class="d-flex justify-content-between small mb-3"><span>Estimated distance</span><strong id="estimateDistance">-- km</strong></div>
        <div class="rounded-3 border border-primary border-opacity-25 bg-white px-3 py-3">
          <div class="small text-muted mb-1">Estimated total</div>
          <div class="fs-4 fw-bold text-primary" id="estimateTotalAmount">₹0.00</div>
          <div class="small text-muted mt-1">The final bill locks after agent acceptance and becomes invoice-ready.</div>
        </div>
      </div>
    </div>

    <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10">
      <div class="card-body">
        <h6 class="fw-bold text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Delivery Info</h6>
        <div class="d-flex justify-content-between mb-2 small"><span>Delivery time</span><strong>20-45 mins</strong></div>
        <div class="d-flex justify-content-between mb-2 small"><span>Service availability</span><strong>24/7</strong></div>
        <div class="d-flex justify-content-between mb-2 small"><span>Night delivery extra</span><strong>Applied during late-night hours</strong></div>
        <hr class="my-3">
        <p class="small text-muted mb-0"><i class="fas fa-shield-alt text-primary me-2"></i>100% genuine fuel from certified sources with GST-ready billing and invoice generation.</p>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('fuelOrderForm');
  const placeOrderButton = document.getElementById('placeOrderBtn');
  const deliveryAddressInput = document.getElementById('deliveryAddress');
  const latInput = document.getElementById('deliveryLat');
  const lngInput = document.getElementById('deliveryLng');
  const captureButton = document.getElementById('captureLocationBtn');
  const centerMapOnCurrentButton = document.getElementById('centerMapOnCurrentBtn');
  const liveStatus = document.getElementById('liveLocationStatus');
  const liveCoordinates = document.getElementById('liveLocationCoordinates');
  const mapStatus = document.getElementById('mapLocationStatus');
  const mapCoordinates = document.getElementById('mapLocationCoordinates');
  const liveCard = document.getElementById('liveLocationCard');
  const mapCard = document.getElementById('mapLocationCard');
  const mapSearchInput = document.getElementById('mapSearchInput');
  const mapSearchButton = document.getElementById('mapSearchBtn');
  const mapSearchResults = document.getElementById('mapSearchResults');
  const mapPreviewLink = document.getElementById('mapPreviewLink');
  const locationModeInputs = Array.from(document.querySelectorAll('input[name="location_mode"]'));
  const fuelInputs = Array.from(document.querySelectorAll('input[name="fuel_product_id"]'));
  const quantityInput = form.querySelector('input[name="quantity_liters"]');
  const paymentMethodSelect = form.querySelector('select[name="payment_method"]');
  const verifyOtpButton = document.getElementById('verifyOtpBtn');
  const otpInput = document.getElementById('otpInput');
  const otpError = document.getElementById('otpError');
  const otpStatus = document.getElementById('otpStatus');
  const otpModalElement = document.getElementById('otpModal');
  const otpModal = bootstrap.Modal.getOrCreateInstance(otpModalElement);
  const billingPreviewBadge = document.getElementById('billingPreviewBadge');
  const billingPreviewMessage = document.getElementById('billingPreviewMessage');
  const estimateFuelTotal = document.getElementById('estimateFuelTotal');
  const estimateDeliveryCharge = document.getElementById('estimateDeliveryCharge');
  const estimateNightFee = document.getElementById('estimateNightFee');
  const estimatePlatformFee = document.getElementById('estimatePlatformFee');
  const estimateGstAmount = document.getElementById('estimateGstAmount');
  const estimateDistance = document.getElementById('estimateDistance');
  const estimateTotalAmount = document.getElementById('estimateTotalAmount');
  const csrfToken = '{{ csrf_token() }}';
  const defaultMapCenter = [20.5937, 78.9629];

  let map = null;
  let mapMarker = null;
  let otpVerified = false;
  let requestingOtp = false;
  let searchController = null;
  let estimateTimer = null;

  const storedLocations = {
    live_gps: null,
    map_pin: null,
  };

  const initialMode = document.querySelector('input[name="location_mode"]:checked')?.value ?? 'live_gps';
  if (latInput.value && lngInput.value) {
    storedLocations[initialMode] = {
      lat: Number(latInput.value),
      lng: Number(lngInput.value),
    };
  }

  const getSelectedMode = () => {
    return document.querySelector('input[name="location_mode"]:checked')?.value ?? 'live_gps';
  };

  const setOrderSubmitState = (isBusy) => {
    placeOrderButton.disabled = isBusy;
    placeOrderButton.innerHTML = isBusy
      ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Sending Email Code...'
      : '<i class="fas fa-bolt me-2"></i>Place Order';
  };

  const setOtpVerifyState = (isBusy) => {
    verifyOtpButton.disabled = isBusy;
    verifyOtpButton.innerHTML = isBusy
      ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Verifying...'
      : 'Verify Email &amp; Place Order';
  };

  const showOtpError = (message) => {
    otpError.textContent = message;
    otpError.classList.remove('d-none');
  };

  const hideOtpError = () => {
    otpError.classList.add('d-none');
  };

  const resetOtpModalState = () => {
    otpInput.value = '';
    hideOtpError();
    otpStatus.textContent = 'We are preparing your email verification code.';
    setOtpVerifyState(false);
  };

  const readJson = async (response) => {
    try {
      return await response.json();
    } catch (error) {
      return null;
    }
  };

  const updateHiddenInputs = () => {
    const selectedLocation = storedLocations[getSelectedMode()];

    if (!selectedLocation) {
      latInput.value = '';
      lngInput.value = '';
      return;
    }

    latInput.value = selectedLocation.lat.toFixed(7);
    lngInput.value = selectedLocation.lng.toFixed(7);
  };

  const formatCurrency = (amount) => {
    return `₹${Number(amount || 0).toFixed(2)}`;
  };

  const formatVisibleLocationAddress = (mode) => {
    const enteredAddress = deliveryAddressInput.value.trim();

    if (enteredAddress !== '') {
      return enteredAddress;
    }

    return mode === 'map_pin'
      ? 'Pinned delivery point selected. Enter the delivery address above to show a readable label.'
      : 'Live GPS captured in the background. Enter the delivery address above to show a readable label.';
  };

  const renderEstimatePlaceholder = (message, badge = 'Waiting') => {
    billingPreviewBadge.textContent = badge;
    billingPreviewMessage.textContent = message;
    estimateFuelTotal.textContent = '₹0.00';
    estimateDeliveryCharge.textContent = '₹0.00';
    estimateNightFee.textContent = '₹0.00';
    estimatePlatformFee.textContent = '₹0.00';
    estimateGstAmount.textContent = '₹0.00';
    estimateDistance.textContent = '-- km';
    estimateTotalAmount.textContent = '₹0.00';
  };

  const renderEstimate = (payload) => {
    billingPreviewBadge.textContent = 'Estimated';
    billingPreviewMessage.textContent = 'Preview generated from your selected fuel, quantity, and delivery coordinates.';
    estimateFuelTotal.textContent = formatCurrency(payload.fuel_total);
    estimateDeliveryCharge.textContent = formatCurrency(payload.slab_charge ?? payload.delivery_charge);
    estimateNightFee.textContent = formatCurrency(payload.night_fee);
    estimatePlatformFee.textContent = formatCurrency(payload.platform_fee);
    estimateGstAmount.textContent = formatCurrency(payload.gst_amount);
    estimateDistance.textContent = `${Number(payload.distance_km || 0).toFixed(1)} km`;
    estimateTotalAmount.textContent = formatCurrency(payload.total_amount);
  };

  const selectedFuelInput = () => {
    return document.querySelector('input[name="fuel_product_id"]:checked');
  };

  const fetchEstimatedBilling = async () => {
    const selectedFuel = selectedFuelInput();
    const quantity = Number(quantityInput.value);
    const selectedLocation = storedLocations[getSelectedMode()];

    if (!selectedFuel || !quantity || quantity <= 0) {
      renderEstimatePlaceholder('Choose a valid fuel product and quantity to generate an estimate.');
      return;
    }

    if (!selectedLocation) {
      renderEstimatePlaceholder('Capture your delivery location to preview the estimated payable amount.');
      return;
    }

    billingPreviewBadge.textContent = 'Loading';
    billingPreviewMessage.textContent = 'Refreshing your estimated bill...';

    try {
      const response = await fetch('{{ route('user.order.estimate') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          fuel_product_id: selectedFuel.value,
          quantity_liters: quantity,
          payment_method: paymentMethodSelect.value,
          delivery_lat: selectedLocation.lat,
          delivery_lng: selectedLocation.lng,
        }),
      });

      const payload = await readJson(response);

      if (!response.ok || !payload?.status) {
        throw new Error(payload?.message ?? 'Unable to generate estimate right now.');
      }

      renderEstimate(payload.data ?? {});
    } catch (error) {
      renderEstimatePlaceholder(error.message || 'Unable to generate estimate right now.', 'Retry');
    }
  };

  const scheduleEstimate = () => {
    window.clearTimeout(estimateTimer);
    estimateTimer = window.setTimeout(fetchEstimatedBilling, 250);
  };

  const renderLiveState = () => {
    const liveLocation = storedLocations.live_gps;

    if (!liveLocation) {
      liveStatus.textContent = 'Waiting to capture your current location.';
      liveCoordinates.classList.add('d-none');
      return;
    }

    liveStatus.textContent = 'Current device location captured successfully.';
    liveCoordinates.textContent = `Address shown to agents: ${formatVisibleLocationAddress('live_gps')}`;
    liveCoordinates.classList.remove('d-none');
  };

  const renderMapState = (message = null) => {
    const pinnedLocation = storedLocations.map_pin;

    if (!pinnedLocation) {
      mapStatus.textContent = message ?? 'Search for an area or click anywhere on the map to drop the delivery pin.';
      mapCoordinates.classList.add('d-none');
      mapPreviewLink.classList.add('d-none');
      mapPreviewLink.href = '#';
      return;
    }

    mapStatus.textContent = message ?? 'Pinned destination selected successfully.';
    mapCoordinates.textContent = `Address shown to agents: ${formatVisibleLocationAddress('map_pin')}`;
    mapCoordinates.classList.remove('d-none');
    mapPreviewLink.href = `https://www.google.com/maps/search/?api=1&query=${pinnedLocation.lat},${pinnedLocation.lng}`;
    mapPreviewLink.classList.remove('d-none');
  };

  const updateMapMarker = (lat, lng, recenter = true) => {
    if (!map) {
      return;
    }

    if (!mapMarker) {
      mapMarker = L.marker([lat, lng], { draggable: true }).addTo(map);
      mapMarker.on('dragend', () => {
        const position = mapMarker.getLatLng();
        setPinnedLocation(position.lat, position.lng, 'Pinned destination moved successfully.');
      });
    } else {
      mapMarker.setLatLng([lat, lng]);
    }

    if (recenter) {
      map.setView([lat, lng], Math.max(map.getZoom(), 15));
    }
  };

  const setPinnedLocation = (lat, lng, message = null) => {
    storedLocations.map_pin = {
      lat: Number(Number(lat).toFixed(7)),
      lng: Number(Number(lng).toFixed(7)),
    };

    updateHiddenInputs();
    renderMapState(message);
    updateMapMarker(storedLocations.map_pin.lat, storedLocations.map_pin.lng, true);
    scheduleEstimate();
  };

  const initializeMap = () => {
    if (map || typeof L === 'undefined') {
      if (typeof L === 'undefined') {
        mapStatus.textContent = 'The map picker could not load. Please refresh the page or use live GPS.';
      }

      return;
    }

    map = L.map('deliveryLocationMap').setView(defaultMapCenter, 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    map.on('click', (event) => {
      setPinnedLocation(event.latlng.lat, event.latlng.lng);
    });

    if (storedLocations.map_pin) {
      updateMapMarker(storedLocations.map_pin.lat, storedLocations.map_pin.lng, true);
    }
  };

  const renderModeUi = () => {
    const selectedMode = getSelectedMode();

    liveCard.classList.toggle('d-none', selectedMode !== 'live_gps');
    mapCard.classList.toggle('d-none', selectedMode !== 'map_pin');
    updateHiddenInputs();

    if (selectedMode === 'live_gps') {
      renderLiveState();

      if (!storedLocations.live_gps) {
        requestDeviceLocation().catch(() => {
          // The live status message already explains the error state.
        });
      }

      return;
    }

    initializeMap();
    renderMapState();
    window.setTimeout(() => map?.invalidateSize(), 150);
  };

  const requestDeviceLocation = () => new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      liveStatus.textContent = 'This browser does not support GPS access. Please use a browser with geolocation enabled.';
      reject(new Error('Geolocation is not supported.'));
      return;
    }

    liveStatus.textContent = 'Capturing your current location...';

    navigator.geolocation.getCurrentPosition(
      (position) => {
        storedLocations.live_gps = {
          lat: Number(position.coords.latitude.toFixed(7)),
          lng: Number(position.coords.longitude.toFixed(7)),
        };

        updateHiddenInputs();
        renderLiveState();
        scheduleEstimate();
        resolve(storedLocations.live_gps);
      },
      () => {
        liveStatus.textContent = 'Location access was denied or timed out. Please allow GPS access and try again.';
        reject(new Error('Unable to capture live GPS.'));
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0,
      }
    );
  });

  const ensureLocationForSelectedMode = async () => {
    if (getSelectedMode() === 'live_gps') {
      if (storedLocations.live_gps) {
        updateHiddenInputs();
        return true;
      }

      try {
        await requestDeviceLocation();
        return true;
      } catch (error) {
        return false;
      }
    }

    initializeMap();
    window.setTimeout(() => map?.invalidateSize(), 150);

    if (!storedLocations.map_pin) {
      renderMapState('Please search and click on the map to select the delivery location.');
      mapCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return false;
    }

    updateHiddenInputs();
    return true;
  };

  const clearSearchResults = () => {
    mapSearchResults.innerHTML = '';
    mapSearchResults.classList.add('d-none');
  };

  const searchMapLocations = async () => {
    const query = mapSearchInput.value.trim();

    if (!query) {
      renderMapState('Enter a place, road, landmark, or city to search the map.');
      clearSearchResults();
      return;
    }

    if (searchController) {
      searchController.abort();
    }

    searchController = new AbortController();
    clearSearchResults();
    mapStatus.textContent = 'Searching the map...';

    try {
      const response = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&q=${encodeURIComponent(query)}`, {
        signal: searchController.signal,
        headers: { 'Accept': 'application/json' },
      });

      if (!response.ok) {
        throw new Error('search-failed');
      }

      const results = await response.json();

      if (!Array.isArray(results) || results.length === 0) {
        renderMapState('No matching locations found. Try another search or click directly on the map.');
        return;
      }

      results.forEach((result) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'list-group-item list-group-item-action';
        button.textContent = result.display_name;
        button.addEventListener('click', () => {
          initializeMap();
          if (!deliveryAddressInput.value.trim()) {
            deliveryAddressInput.value = result.display_name;
          }
          setPinnedLocation(result.lat, result.lon, `Pinned destination selected: ${result.display_name}`);

          clearSearchResults();
        });
        mapSearchResults.appendChild(button);
      });

      mapSearchResults.classList.remove('d-none');
      mapStatus.textContent = 'Select one of the search results or click directly on the map.';
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }

      renderMapState('Search is unavailable right now. You can still click directly on the map to set the delivery pin.');
    }
  };

  const sendOtp = async () => {
    const response = await fetch('{{ route('user.send.otp', [], false) }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      },
      body: JSON.stringify({}),
    });

    const data = await readJson(response);

    if (!response.ok || !data?.status) {
      throw new Error(data?.message ?? 'Failed to send the email verification code.');
    }

    return data;
  };

  captureButton.addEventListener('click', async () => {
    try {
      await requestDeviceLocation();
    } catch (error) {
      // The live status text already reflects the failure state.
    }
  });

  centerMapOnCurrentButton.addEventListener('click', async () => {
    initializeMap();

    try {
      const liveLocation = await requestDeviceLocation();
      map.setView([liveLocation.lat, liveLocation.lng], 14);
      renderMapState(storedLocations.map_pin ? null : 'Map centered on your current area. Now click the exact delivery destination.');
    } catch (error) {
      renderMapState('Unable to access your current position. You can still search or click directly on the map.');
    }
  });

  mapSearchButton.addEventListener('click', () => {
    initializeMap();
    searchMapLocations();
  });

  mapSearchInput.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') {
      return;
    }

    event.preventDefault();
    initializeMap();
    searchMapLocations();
  });

  locationModeInputs.forEach((input) => {
    input.addEventListener('change', () => {
      clearSearchResults();
      renderModeUi();
      scheduleEstimate();
    });
  });

  fuelInputs.forEach((input) => {
    input.addEventListener('change', scheduleEstimate);
  });

  deliveryAddressInput.addEventListener('input', () => {
    renderLiveState();
    renderMapState();
  });

  quantityInput.addEventListener('input', scheduleEstimate);
  paymentMethodSelect.addEventListener('change', scheduleEstimate);

  otpModalElement.addEventListener('shown.bs.modal', () => {
    otpInput.focus();
  });

  otpModalElement.addEventListener('hidden.bs.modal', () => {
    if (!otpVerified) {
      resetOtpModalState();
    }
  });

  form.addEventListener('submit', async (event) => {
    if (otpVerified) {
      return;
    }

    event.preventDefault();
    hideOtpError();

    const hasLocation = await ensureLocationForSelectedMode();
    if (!hasLocation) {
      return;
    }

    if (requestingOtp) {
      return;
    }

    requestingOtp = true;
    setOrderSubmitState(true);

    try {
      resetOtpModalState();
      const data = await sendOtp();
      otpStatus.textContent = data.message ?? 'Verification code sent successfully.';
      otpModal.show();
    } catch (error) {
      if (getSelectedMode() === 'live_gps') {
        liveStatus.textContent = error.message || 'Location is ready, but the email code could not be sent. Please try again.';
      } else {
        renderMapState(error.message || 'Location is ready, but the email code could not be sent. Please try again.');
      }
    } finally {
      requestingOtp = false;
      setOrderSubmitState(false);
    }
  });

  const verifyOtp = async () => {
    hideOtpError();

    const otp = otpInput.value.trim();
    if (!/^\d{6}$/.test(otp)) {
      showOtpError('Please enter a valid 6-digit verification code.');
      return;
    }

    try {
      setOtpVerifyState(true);
      const response = await fetch('{{ route('user.verify.otp', [], false) }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ otp }),
      });

      const data = await readJson(response);

      if (!response.ok || !data?.status) {
        showOtpError(data?.message ?? 'Unable to verify the email code right now. Please try again.');
        setOtpVerifyState(false);
        return;
      }

      otpVerified = true;
      otpModal.hide();
      form.submit();
    } catch (error) {
      showOtpError(error.message || 'Unable to verify the email code right now. Please try again.');
      setOtpVerifyState(false);
    }
  };

  verifyOtpButton.addEventListener('click', verifyOtp);

  otpInput.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') {
      return;
    }

    event.preventDefault();
    verifyOtp();
  });

  renderLiveState();
  renderMapState();
  renderModeUi();
  scheduleEstimate();
});
</script>
@endsection
