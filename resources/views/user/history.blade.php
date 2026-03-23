@extends('layouts.app')
@section('title', 'Order History')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Order History</h4><p class="text-muted mb-0">All your past fuel deliveries</p></div>
  <a href="{{ route('user.order') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>New Order</a>
</div>

@if($orders->isEmpty())
  <div class="card text-center py-5"><div class="card-body"><i class="fas fa-box fa-4x text-muted opacity-25 mb-3"></i><h5 class="fw-bold">No orders yet</h5><p class="text-muted">Your fuel delivery history will appear here.</p><a href="{{ route('user.order') }}" class="btn btn-primary">Place Your First Order</a></div></div>
@else
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>#</th><th>Fuel</th><th>Qty</th><th>Address</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th><th></th></tr></thead>
      <tbody>
        @foreach($orders as $order)
        <tr>
          <td class="text-muted small">{{ $order->displayOrderNumber() }}</td>
          <td>{{ $order->fuelProduct->name ?? '—' }}</td>
          <td>{{ $order->quantity_liters }}L</td>
          <td class="text-muted small truncate-cell">{{ $order->delivery_address }}</td>
          <td><strong>₹{{ number_format($order->total_amount, 0) }}</strong></td>
          <td><span class="badge bg-light text-dark text-uppercase">{{ $order->paymentMethodLabel() }}</span></td>
          <td><span class="badge badge-status-{{ $order->status }}">{{ ucwords(str_replace('_',' ',$order->status)) }}</span></td>
          <td class="text-muted small">{{ $order->created_at->format('M d, Y') }}</td>
          <td><a href="{{ route('user.track', $order->id) }}" class="btn btn-sm btn-outline-primary">Track</a></td>
          <td>
            @if($order->billing)
            <a href="{{ route('orders.invoice', $order->id) }}" class="btn btn-sm btn-outline-secondary">Invoice</a>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
<div class="mt-3">{{ $orders->links() }}</div>
@endif
@endsection
