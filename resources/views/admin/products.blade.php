@extends('layouts.app')
@section('title', 'Fuel Products')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Fuel Products</h4><p class="text-muted mb-0">Manage available fuel types and pricing</p></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal"><i class="fas fa-plus me-2"></i>Add Product</button>
</div>

<div class="row g-3">
  @foreach($products as $product)
  <div class="col-md-6 col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h5 class="fw-bold mb-1">{{ $product->name }}</h5>
            <span class="badge bg-light text-dark">{{ ucwords(str_replace('_',' ',$product->fuel_type)) }}</span>
          </div>
          <span class="badge bg-{{ $product->is_available ? 'success' : 'secondary' }}">{{ $product->is_available ? 'Active' : 'Inactive' }}</span>
        </div>
        <div class="display-6 fw-bold text-primary mb-1">₹{{ number_format($product->price_per_liter, 2) }}</div>
        <div class="text-muted small mb-3">per litre</div>
        @if($product->description)
        <p class="text-muted small mb-3">{{ $product->description }}</p>
        @endif
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#editProductModal{{ $product->id }}">Edit Price</button>
          <form method="POST" action="{{ route('admin.products.toggle', $product->id) }}">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-sm btn-outline-{{ $product->is_available ? 'warning' : 'success' }}">{{ $product->is_available ? 'Disable' : 'Enable' }}</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="editProductModal{{ $product->id }}" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit {{ $product->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="{{ route('admin.products.update', $product->id) }}">
        @csrf @method('PATCH')
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Product Name</label><input type="text" name="name" class="form-control" value="{{ $product->name }}" required></div>
          <div class="mb-3"><label class="form-label">Price per Litre (₹)</label><input type="number" name="price_per_liter" class="form-control" step="0.01" value="{{ $product->price_per_liter }}" required></div>
          <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2">{{ $product->description }}</textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
      </form>
    </div></div>
  </div>
  @endforeach
</div>

<div class="modal fade" id="addProductModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add Fuel Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="{{ route('admin.products.store') }}">
      @csrf
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Product Name</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Fuel Type</label>
          <select name="fuel_type" class="form-select">
            <option value="petrol">Petrol</option><option value="diesel">Diesel</option>
            <option value="premium_petrol">Premium Petrol</option><option value="premium_diesel">Premium Diesel</option>
          </select>
        </div>
        <div class="mb-3"><label class="form-label">Price per Litre (₹)</label><input type="number" name="price_per_liter" class="form-control" step="0.01" required></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Product</button></div>
    </form>
  </div></div>
</div>
@endsection
