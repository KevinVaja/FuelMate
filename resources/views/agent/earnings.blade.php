@extends('layouts.app')
@section('title', 'Earnings')
@section('content')
<div class="mb-4"><h4 class="fw-bold mb-0">Earnings</h4><p class="text-muted mb-0">Your delivery earnings summary</p></div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-success"><div class="card-body py-3"><div class="text-muted small mb-1"><i class="fas fa-sun me-1"></i>Today</div><div class="fs-4 fw-bold">₹{{ number_format($data['today_earnings'], 0) }}</div><div class="text-muted small">{{ $data['today_deliveries'] }} deliveries</div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-info"><div class="card-body py-3"><div class="text-muted small mb-1"><i class="fas fa-calendar-week me-1"></i>This Week</div><div class="fs-4 fw-bold">₹{{ number_format($data['week_earnings'], 0) }}</div><div class="text-muted small">{{ $data['week_deliveries'] }} deliveries</div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card"><div class="card-body py-3"><div class="text-muted small mb-1"><i class="fas fa-chart-line me-1"></i>All Time Earnings</div><div class="fs-4 fw-bold">₹{{ number_format($data['total_earnings'], 0) }}</div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-warning"><div class="card-body py-3"><div class="text-muted small mb-1"><i class="fas fa-box me-1"></i>Total Deliveries</div><div class="fs-4 fw-bold">{{ $data['total_deliveries'] }}</div></div></div></div>
</div>

@if($recentOrders->isNotEmpty())
<div class="card">
  <div class="card-header">Recent Completed Deliveries</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>#</th><th>Fuel</th><th>Qty</th><th>Distance</th><th>Earning</th><th>Date</th></tr></thead>
      <tbody>
        @foreach($recentOrders as $order)
        <tr>
          <td class="text-muted small">{{ $order->displayOrderNumber() }}</td>
          <td>{{ $order->fuelProduct->name ?? '—' }}</td>
          <td>{{ $order->quantity_liters }}L</td>
          <td>{{ ($distanceKm = $order->historicalDistanceKm()) !== null ? number_format($distanceKm, 1) . ' km' : '—' }}</td>
          <td class="text-success fw-bold">+₹{{ number_format((float) ($order->billing?->agent_earning ?? 0), 0) }}</td>
          <td class="text-muted small">{{ $order->updated_at->format('M d, Y') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif
@endsection
