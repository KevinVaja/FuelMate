@extends('layouts.app')
@section('title', 'Delivery History')
@section('content')
<div class="mb-4"><h4 class="fw-bold mb-0">Delivery History</h4><p class="text-muted mb-0">Your completed deliveries</p></div>

@if($orders->isEmpty())
  <div class="card text-center py-5"><div class="card-body"><i class="fas fa-history fa-4x text-muted opacity-25 mb-3"></i><h5 class="fw-bold">No completed deliveries</h5><p class="text-muted">Your delivery history will appear here.</p></div></div>
@else
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>#</th><th>Customer</th><th>Fuel</th><th>Distance</th><th>Total Amount</th><th>Earning</th><th>Date</th></tr></thead>
      <tbody>
        @foreach($orders as $order)
        <tr>
          <td class="text-muted small">{{ $order->displayOrderNumber() }}</td>
          <td>{{ $order->user->name ?? '—' }}</td>
          <td>{{ $order->fuelProduct->name ?? '—' }}<span class="text-muted ms-1 small">{{ $order->quantity_liters }}L</span></td>
          <td>{{ ($distanceKm = $order->historicalDistanceKm()) !== null ? number_format($distanceKm, 1) . ' km' : '—' }}</td>
          <td>₹{{ number_format((float) ($order->billing?->total_amount ?? $order->total_amount), 0) }}</td>
          <td class="text-success fw-bold">+₹{{ number_format((float) ($order->billing?->agent_earning ?? 0), 0) }}</td>
          <td class="text-muted small">{{ $order->updated_at->format('M d, Y') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
<div class="mt-3">{{ $orders->links() }}</div>
@endif
@endsection
