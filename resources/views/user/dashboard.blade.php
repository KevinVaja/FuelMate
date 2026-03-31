@extends('layouts.app')
@section('title', 'My Dashboard')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Dashboard</h4><p class="text-muted mb-0">Welcome back, {{ auth()->user()->name }}!</p></div>
  <a href="{{ route('user.order') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Order Fuel</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card stat-card">
      <div class="card-body py-3">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-primary bg-opacity-10 rounded-3 p-2"><i class="fas fa-box text-primary fs-5"></i></div>
          <div><div class="text-muted small">Total Orders</div><div class="fs-4 fw-bold">{{ $totalOrders }}</div></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card stat-card stat-accent-success">
      <div class="card-body py-3">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-success bg-opacity-10 rounded-3 p-2"><i class="fas fa-rupee-sign text-success fs-5"></i></div>
          <div><div class="text-muted small">Total Spent</div><div class="fs-4 fw-bold">₹{{ number_format($totalSpent, 0) }}</div></div>
        </div>
      </div>
    </div>
  </div>
</div>

@if($activeOrder)
<div class="card border-primary border-2 mb-4">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
    <span><i class="fas fa-truck me-2"></i>Active Order – Order #{{ $activeOrder->displayOrderNumber() }}</span>
    <a href="{{ route('user.track', $activeOrder->id) }}" class="btn btn-light btn-sm">Track Live</a>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4"><small class="text-muted d-block">Fuel Type</small><strong>{{ $activeOrder->fuelProduct->name ?? '—' }}</strong></div>
      <div class="col-md-4"><small class="text-muted d-block">Quantity</small><strong>{{ $activeOrder->quantity_liters }}L</strong></div>
      <div class="col-md-4"><small class="text-muted d-block">Status</small>
        <span class="badge badge-status-{{ $activeOrder->status }}">{{ ucwords(str_replace('_',' ',$activeOrder->status)) }}</span>
      </div>
    </div>
  </div>
</div>
@endif

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Recent Orders</span>
    <a href="{{ route('user.history') }}" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="card-body p-0">
    @if($recentOrders->isEmpty())
      <div class="text-center py-5 text-muted"><i class="fas fa-box fa-3x mb-3 opacity-25"></i><p>No orders yet. <a href="{{ route('user.order') }}">Place your first order!</a></p></div>
    @else
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>#</th><th>Fuel</th><th>Qty</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
          @foreach($recentOrders as $order)
          <tr>
            <td class="text-muted small">{{ $order->displayOrderNumber() }}</td>
            <td>{{ $order->fuelProduct->name ?? '—' }}</td>
            <td>{{ $order->quantity_liters }}L</td>
            <td>₹{{ number_format($order->total_amount, 0) }}</td>
            <td><span class="badge badge-status-{{ $order->status }}">{{ ucwords(str_replace('_',' ',$order->status)) }}</span></td>
            <td class="text-muted small">{{ $order->created_at->format('M d, Y') }}</td>
            <td><a href="{{ route('user.track', $order->id) }}" class="btn btn-sm btn-outline-primary">Track</a></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>
</div>
@endsection
