@extends('layouts.app')
@section('title', 'Delivery Charges')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Delivery Charges</h4><p class="text-muted mb-0">Distance-based slab pricing</p></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addChargeModal"><i class="fas fa-plus me-2"></i>Add Slab</button>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
      <div>
        <div class="fw-bold">Night Delivery Pricing</div>
        <div class="text-muted small">Apply an extra delivery charge during late-night operating hours.</div>
      </div>
      <span class="badge {{ $nightDelivery['enabled'] ? 'bg-success-subtle text-success-emphasis' : 'bg-light text-dark' }}">
        {{ $nightDelivery['enabled'] ? 'Enabled' : 'Disabled' }}
      </span>
    </div>
    <form method="POST" action="{{ route('admin.delivery_charges.night_delivery') }}" class="row g-3 align-items-end">
      @csrf
      @method('PATCH')
      <input type="hidden" name="night_delivery_enabled" value="0">
      <div class="col-lg-3">
        <div class="form-check form-switch pt-2">
          <input class="form-check-input" type="checkbox" role="switch" id="nightDeliveryEnabled" name="night_delivery_enabled" value="1" {{ $nightDelivery['enabled'] ? 'checked' : '' }}>
          <label class="form-check-label" for="nightDeliveryEnabled">Enable night extra charge</label>
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Night extra charge (₹)</label>
        <input type="number" name="night_delivery_fee" class="form-control" step="0.01" min="0" value="{{ number_format((float) $nightDelivery['fee'], 2, '.', '') }}" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Starts at</label>
        <input type="time" name="night_starts_at" class="form-control" value="{{ $nightDelivery['starts_at'] }}" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Ends at</label>
        <input type="time" name="night_ends_at" class="form-control" value="{{ $nightDelivery['ends_at'] }}" required>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Save Night Pricing</button>
      </div>
    </form>
    <div class="small text-muted mt-3">FuelMate adds this amount on top of the slab-based delivery charge whenever the order is placed during the configured night window.</div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>Min Distance</th><th>Max Distance</th><th>Charge</th><th></th></tr></thead>
      <tbody>
        @forelse($charges as $charge)
        <tr>
          <td>{{ $charge->min_km }} km</td>
          <td>{{ $charge->max_km }} km</td>
          <td class="fw-bold">₹{{ number_format($charge->charge, 2) }}</td>
          <td>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editChargeModal{{ $charge->id }}">Edit</button>
              <form method="POST" action="{{ route('admin.delivery_charges.delete', $charge->id) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this slab?')">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <div class="modal fade" id="editChargeModal{{ $charge->id }}" tabindex="-1">
          <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit Delivery Charge</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="{{ route('admin.delivery_charges.update', $charge->id) }}">
              @csrf @method('PATCH')
              <div class="modal-body row g-3">
                <div class="col-6"><label class="form-label">Min Distance (km)</label><input type="number" name="min_km" class="form-control" step="0.01" value="{{ $charge->min_km }}" required></div>
                <div class="col-6"><label class="form-label">Max Distance (km)</label><input type="number" name="max_km" class="form-control" step="0.01" value="{{ $charge->max_km }}" required></div>
                <div class="col-12"><label class="form-label">Charge Amount (₹)</label><input type="number" name="charge" class="form-control" step="0.01" value="{{ $charge->charge }}" required></div>
              </div>
              <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
          </div></div>
        </div>
        @empty
        <tr><td colspan="4" class="text-center py-4 text-muted">No delivery charge slabs defined.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="addChargeModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add Delivery Charge Slab</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="{{ route('admin.delivery_charges.store') }}">
      @csrf
      <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">Min Distance (km)</label><input type="number" name="min_km" class="form-control" step="0.01" required></div>
        <div class="col-6"><label class="form-label">Max Distance (km)</label><input type="number" name="max_km" class="form-control" step="0.01" required></div>
        <div class="col-12"><label class="form-label">Charge Amount (₹)</label><input type="number" name="charge" class="form-control" step="0.01" required></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Slab</button></div>
    </form>
  </div></div>
</div>
@endsection
