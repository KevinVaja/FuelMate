@extends('layouts.app')
@section('title', 'Agent Dashboard')
@section('content')
@php
  $canOperate = $agent->isApprovedForOperations();
  $displayTimezone = config('app.display_timezone', 'Asia/Kolkata');
  $maxAgentEarnings = max((float) collect($agentPerformance)->max('earnings'), 1);
  $agentWeeklyEarnings = collect($agentPerformance)->sum('earnings');
  $agentWeeklyDeliveries = collect($agentPerformance)->sum('deliveries');
  $agentStatusTotal = max((int) collect($agentStatusBreakdown)->sum('count'), 1);
  $statusColors = [
    'accepted' => 'sky',
    'on_the_way' => 'cyan',
    'delivered' => 'teal',
    'completed' => 'emerald',
    'cancelled' => 'rose',
  ];
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Agent Dashboard</h4><p class="text-muted mb-0">Welcome, {{ auth()->user()->name }}</p></div>
  <form method="POST" action="{{ route('agent.toggle') }}">
    @csrf
    <button type="submit" class="btn btn-{{ $agent->is_available ? 'success' : 'outline-secondary' }}" @disabled(!$canOperate)>
      <i class="fas fa-circle me-2"></i>{{ $agent->is_available ? 'Online' : 'Offline' }}
    </button>
  </form>
</div>

@if($agent->verification_status === 'pending')
<div class="alert alert-warning"><i class="fas fa-clock me-2"></i>Your petrol pump account is under verification. You will be able to go online and accept orders after admin approval.</div>
@elseif($agent->verification_status === 'rejected')
<div class="alert alert-danger">
  <i class="fas fa-times-circle me-2"></i>Your petrol pump account was rejected.
  @if($agent->rejection_reason)
    <div class="small mt-2"><strong>Reason:</strong> {{ $agent->rejection_reason }}</div>
  @endif
</div>
@elseif($agent->approved_at)
<div class="alert alert-success"><i class="fas fa-circle-check me-2"></i>Your petrol pump account was approved on {{ $agent->approved_at->copy()->timezone($displayTimezone)->format('d M Y, h:i A') }}.</div>
@endif

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3"><div class="card stat-card"><div class="card-body py-3"><div class="d-flex align-items-center gap-3"><div class="bg-success bg-opacity-10 rounded-3 p-2"><i class="fas fa-rupee-sign text-success fs-5"></i></div><div><div class="text-muted small">Today's Earnings</div><div class="fs-4 fw-bold">&#8377;{{ number_format($todayEarnings, 0) }}</div></div></div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-info"><div class="card-body py-3"><div class="d-flex align-items-center gap-3"><div class="bg-primary bg-opacity-10 rounded-3 p-2"><i class="fas fa-calendar-week text-primary fs-5"></i></div><div><div class="text-muted small">This Week</div><div class="fs-4 fw-bold">&#8377;{{ number_format($weekEarnings, 0) }}</div></div></div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-warning"><div class="card-body py-3"><div class="d-flex align-items-center gap-3"><div class="bg-warning bg-opacity-10 rounded-3 p-2"><i class="fas fa-box text-warning fs-5"></i></div><div><div class="text-muted small">Total Deliveries</div><div class="fs-4 fw-bold">{{ $agent->total_deliveries }}</div></div></div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-teal"><div class="card-body py-3"><div class="d-flex align-items-center gap-3"><div class="bg-teal bg-opacity-10 rounded-3 p-2"><i class="fas fa-star text-warning fs-5"></i></div><div><div class="text-muted small">Rating</div><div class="fs-4 fw-bold">{{ $agent->rating ? $agent->rating.'*' : 'N/A' }}</div></div></div></div></div></div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <div class="fw-semibold">7-Day Earnings Trend</div>
          <div class="text-muted small">Completed and delivered orders from the last 7 days</div>
        </div>
        <div class="text-end">
          <div class="small text-muted">Last 7 days</div>
          <div class="fw-bold">&#8377;{{ number_format($agentWeeklyEarnings, 0) }}</div>
        </div>
      </div>
      <div class="card-body">
        <div class="dashboard-chart-summary">
          <div>
            <div class="dashboard-chart-kicker">Deliveries handled</div>
            <div class="dashboard-chart-number">{{ $agentWeeklyDeliveries }}</div>
          </div>
          <div class="dashboard-chart-pill">
            <span class="fw-semibold">{{ number_format($agentWeeklyDeliveries / max(count($agentPerformance), 1), 1) }}</span>
            <span class="text-muted small">avg/day</span>
          </div>
        </div>

        <div class="dashboard-bar-chart">
          @foreach($agentPerformance as $point)
            @php
              $barHeight = $point['earnings'] > 0
                ? max(($point['earnings'] / $maxAgentEarnings) * 100, 12)
                : 6;
            @endphp
            <div class="dashboard-bar-chart__item">
              <div class="dashboard-bar-chart__value">&#8377;{{ number_format($point['earnings'], 0) }}</div>
              <div class="dashboard-bar-chart__track">
                <div class="dashboard-bar-chart__bar dashboard-bar-chart__bar--agent" style="height: {{ number_format($barHeight, 2, '.', '') }}%"></div>
              </div>
              <div class="dashboard-bar-chart__label">{{ $point['short_label'] }}</div>
              <div class="dashboard-bar-chart__meta">{{ $point['deliveries'] }} deliveries</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header">
        <div class="fw-semibold">Delivery Pipeline</div>
        <div class="text-muted small">Status breakdown for your assigned orders</div>
      </div>
      <div class="card-body">
        @forelse($agentStatusBreakdown as $item)
          @php
            $percentage = (int) round(($item['count'] / $agentStatusTotal) * 100);
            $fillWidth = $item['count'] > 0 ? max($percentage, 8) : 0;
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
            <div class="small text-muted mt-1">{{ $percentage }}% of tracked orders</div>
          </div>
        @empty
          <div class="text-muted small">Your delivery pipeline will appear after you start accepting orders.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

@if($activeOrder)
<div class="card border-primary border-2 mb-4">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
    <span><i class="fas fa-truck me-2"></i>Active Delivery - Order #{{ $activeOrder->displayOrderNumber() }}</span>
    <a href="{{ route('agent.active') }}" class="btn btn-light btn-sm">Manage</a>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4"><small class="text-muted d-block">Customer</small><strong>{{ $activeOrder->user->name ?? '—' }}</strong></div>
      <div class="col-md-4"><small class="text-muted d-block">Fuel</small><strong>{{ $activeOrder->fuelProduct->name ?? '—' }} · {{ $activeOrder->quantity_liters }}L</strong></div>
      <div class="col-md-4"><small class="text-muted d-block">Status</small><span class="badge badge-status-{{ $activeOrder->status }}">{{ ucwords(str_replace('_',' ',$activeOrder->status)) }}</span></div>
    </div>
  </div>
</div>
@endif

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Quick Actions</span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4"><a href="{{ route('agent.requests') }}" class="card quick-link-card h-100 text-decoration-none text-center p-3 border-2 hover-primary"><div class="card-body"><i class="fas fa-map-marker-alt fa-2x text-primary mb-2"></i><div class="fw-bold">Available Requests</div><div class="text-muted small">Accept new orders</div></div></a></div>
      <div class="col-md-4"><a href="{{ route('agent.active') }}" class="card quick-link-card h-100 text-decoration-none text-center p-3 border-2 hover-primary"><div class="card-body"><i class="fas fa-truck fa-2x text-warning mb-2"></i><div class="fw-bold">Active Delivery</div><div class="text-muted small">Manage current order</div></div></a></div>
      <div class="col-md-4"><a href="{{ route('agent.earnings') }}" class="card quick-link-card h-100 text-decoration-none text-center p-3 border-2 hover-primary"><div class="card-body"><i class="fas fa-dollar-sign fa-2x text-success mb-2"></i><div class="fw-bold">Earnings</div><div class="text-muted small">View your income</div></div></a></div>
    </div>
  </div>
</div>
@endsection
