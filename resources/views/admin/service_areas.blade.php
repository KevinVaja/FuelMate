@extends('layouts.app')
@section('title', 'Service Areas')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Service Areas</h4><p class="text-muted mb-0">Manage delivery coverage zones</p></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAreaModal"><i class="fas fa-plus me-2"></i>Add Area</button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>Area Name</th><th>City</th><th>Zone</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @forelse($areas as $area)
        <tr>
          <td class="fw-bold">{{ $area->name }}</td>
          <td>{{ $area->city }}</td>
          <td><span class="badge bg-light text-dark">{{ $area->zone }}</span></td>
          <td><span class="badge bg-{{ $area->is_active ? 'success' : 'secondary' }}">{{ $area->is_active ? 'Active' : 'Inactive' }}</span></td>
          <td>
            <form method="POST" action="{{ route('admin.service_areas.toggle', $area->id) }}">
              @csrf @method('PATCH')
              <button type="submit" class="btn btn-sm btn-outline-{{ $area->is_active ? 'warning' : 'success' }}">{{ $area->is_active ? 'Disable' : 'Enable' }}</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center py-4 text-muted">No service areas defined.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="addAreaModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add Service Area</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="{{ route('admin.service_areas.store') }}">
      @csrf
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Area Name</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">City</label><input type="text" name="city" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Zone</label><input type="text" name="zone" class="form-control" placeholder="North / South / East / West"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Area</button></div>
    </form>
  </div></div>
</div>
@endsection
