@extends('layouts.app')
@section('title', 'Admin Analytics')
@section('content')
@php
  $maxAdminRevenue = max((float) collect($adminTrend)->max('revenue'), 1);
  $adminTrendRevenue = collect($adminTrend)->sum('revenue');
  $adminTrendOrders = collect($adminTrend)->sum('orders');
  $statusColors = [
    'pending' => 'amber',
    'accepted' => 'sky',
    'fuel_preparing' => 'sky',
    'on_the_way' => 'cyan',
    'arrived' => 'teal',
    'otp_verification' => 'teal',
    'delivered' => 'teal',
    'refund_processing' => 'amber',
    'completed' => 'emerald',
    'cancelled' => 'rose',
  ];
@endphp

<div class="mb-4">
  <h4 class="fw-bold mb-0">Analytics Dashboard</h4>
  <p class="text-muted mb-0">FuelMate platform overview</p>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3"><div class="card stat-card"><div class="card-body py-3"><div class="d-flex align-items-center gap-3"><div class="bg-primary bg-opacity-10 rounded-3 p-2"><i class="fas fa-box text-primary fs-5"></i></div><div><div class="text-muted small">Total Orders</div><div class="fs-4 fw-bold">{{ $data['total_orders'] }}</div></div></div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-success"><div class="card-body py-3"><div class="d-flex align-items-center gap-3"><div class="bg-success bg-opacity-10 rounded-3 p-2"><i class="fas fa-rupee-sign text-success fs-5"></i></div><div><div class="text-muted small">Total Revenue</div><div class="fs-4 fw-bold">&#8377;{{ number_format($data['total_revenue'], 0) }}</div></div></div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-info"><div class="card-body py-3"><div class="d-flex align-items-center gap-3"><div class="bg-info bg-opacity-10 rounded-3 p-2"><i class="fas fa-users text-info fs-5"></i></div><div><div class="text-muted small">Total Users</div><div class="fs-4 fw-bold">{{ $data['total_users'] }}</div></div></div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-warning"><div class="card-body py-3"><div class="d-flex align-items-center gap-3"><div class="bg-warning bg-opacity-10 rounded-3 p-2"><i class="fas fa-truck text-warning fs-5"></i></div><div><div class="text-muted small">Active Agents</div><div class="fs-4 fw-bold">{{ $data['active_agents'] }}</div></div></div></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card text-center py-3"><div class="text-warning fs-3 fw-bold">{{ $data['pending_orders'] }}</div><div class="text-muted small">Pending Orders</div></div></div>
  <div class="col-md-3"><div class="card text-center py-3"><div class="text-success fs-3 fw-bold">{{ $data['completed_orders'] }}</div><div class="text-muted small">Completed</div></div></div>
  <div class="col-md-3"><div class="card text-center py-3"><div class="text-info fs-3 fw-bold">{{ $data['pending_agents'] }}</div><div class="text-muted small">Pending Agent Approvals</div></div></div>
  <div class="col-md-3"><div class="card text-center py-3"><div class="text-danger fs-3 fw-bold">{{ $data['open_tickets'] }}</div><div class="text-muted small">Open Support Tickets</div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card text-center py-3"><div class="text-danger fs-3 fw-bold">{{ $data['cancelled_orders'] }}</div><div class="text-muted small">Total Cancelled Orders</div></div></div>
  <div class="col-md-4"><div class="card text-center py-3"><div class="text-warning fs-3 fw-bold">₹{{ number_format((float) $data['cancellation_charges_collected'], 2) }}</div><div class="text-muted small">Cancellation Charges Collected</div></div></div>
  <div class="col-md-4"><div class="card text-center py-3"><div class="text-primary fs-3 fw-bold">₹{{ number_format((float) $data['refund_pending'], 2) }}</div><div class="text-muted small">Refund Pending</div></div></div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <div class="fw-semibold">7-Day Revenue Trend</div>
          <div class="text-muted small">Delivered and completed orders from the last 7 days</div>
        </div>
        <div class="text-end">
          <div class="small text-muted">Last 7 days</div>
          <div class="fw-bold">&#8377;{{ number_format($adminTrendRevenue, 0) }}</div>
        </div>
      </div>
      <div class="card-body">
        <div class="dashboard-chart-summary">
          <div>
            <div class="dashboard-chart-kicker">Order volume</div>
            <div class="dashboard-chart-number">{{ $adminTrendOrders }}</div>
          </div>
          <div class="dashboard-chart-pill">
            <span class="fw-semibold">{{ number_format($adminTrendRevenue / max(count($adminTrend), 1), 0) }}</span>
            <span class="text-muted small">avg/day</span>
          </div>
        </div>

        <div class="dashboard-bar-chart">
          @foreach($adminTrend as $point)
            @php
              $barHeight = $point['revenue'] > 0
                ? max(($point['revenue'] / $maxAdminRevenue) * 100, 12)
                : 6;
            @endphp
            <div class="dashboard-bar-chart__item">
              <div class="dashboard-bar-chart__value">&#8377;{{ number_format($point['revenue'], 0) }}</div>
              <div class="dashboard-bar-chart__track">
                <div class="dashboard-bar-chart__bar dashboard-bar-chart__bar--admin" style="height: {{ number_format($barHeight, 2, '.', '') }}%"></div>
              </div>
              <div class="dashboard-bar-chart__label">{{ $point['short_label'] }}</div>
              <div class="dashboard-bar-chart__meta">{{ $point['orders'] }} orders</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header">
        <div class="fw-semibold">Order Status Mix</div>
        <div class="text-muted small">Live distribution of all orders</div>
      </div>
      <div class="card-body">
        @forelse($orderStatusChart as $item)
          @php
            $fillWidth = $item['count'] > 0 ? max($item['percentage'], 8) : 0;
            $fillClass = $statusColors[$item['status']] ?? 'slate';
          @endphp
          <div class="dashboard-progress-row">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
              <span class="badge badge-status-{{ $item['status'] }}">{{ ucwords(str_replace('_', ' ', $item['status'])) }}</span>
              <span class="small text-muted">{{ $item['count'] }} orders</span>
            </div>
            <div class="dashboard-progress">
              <div class="dashboard-progress__fill dashboard-progress__fill--{{ $fillClass }}" style="width: {{ $fillWidth }}%"></div>
            </div>
            <div class="small text-muted mt-1">{{ $item['percentage'] }}% of all orders</div>
          </div>
        @empty
          <div class="text-muted small">No orders available yet.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Recent Orders</span>
    <a href="{{ route('admin.orders') }}" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>#</th><th>Customer</th><th>Fuel</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        @foreach($recentOrders as $order)
        <tr>
          <td class="text-muted small">{{ $loop->iteration }}</td>
          <td>{{ $order->user->name ?? '—' }}</td>
          <td>{{ $order->fuelProduct->name ?? '—' }} {{ $order->quantity_liters }}L</td>
          <td><strong>&#8377;{{ number_format($order->total_amount, 0) }}</strong></td>
          <td><span class="badge badge-status-{{ $order->status }}">{{ ucwords(str_replace('_',' ',$order->status)) }}</span></td>
          <td class="text-muted small">{{ $order->created_at->format('M d') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
