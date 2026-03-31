@extends('layouts.app')
@section('title', 'Withdrawals')
@section('content')
@php($displayTimezone = config('app.display_timezone', 'Asia/Kolkata'))
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold mb-0">Wallet Withdrawals</h4>
    <p class="text-muted mb-0">Request a payout from your completed delivery earnings.</p>
  </div>
  <a href="{{ route('agent.withdrawals.create') }}" class="btn btn-primary">
    <i class="fas fa-money-bill-transfer me-2"></i>Request Withdrawal
  </a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card stat-card stat-accent-success h-100">
      <div class="card-body py-3">
        <div class="text-muted small mb-1"><i class="fas fa-wallet me-1"></i>Wallet Balance</div>
        <div class="fs-4 fw-bold">₹{{ number_format($walletBalance, 2) }}</div>
        <div class="text-muted small">Available for manual payout completion.</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card h-100">
      <div class="card-body py-3">
        <div class="text-muted small mb-1"><i class="fas fa-arrow-down-wide-short me-1"></i>Minimum Withdrawal</div>
        <div class="fs-4 fw-bold">₹{{ number_format($minimumWithdrawalAmount, 0) }}</div>
        <div class="text-muted small">Amounts below this cannot be requested.</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card stat-accent-info h-100">
      <div class="card-body py-3">
        <div class="text-muted small mb-1"><i class="fas fa-building-columns me-1"></i>Payout Processing</div>
        <div class="fs-6 fw-bold text-uppercase">{{ config('wallet.payout_provider', 'manual') }}</div>
        <div class="text-muted small">Waiting for payment approval from Admin</div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">Withdrawal History</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Amount</th>
          <th>Method</th>
          <th>Status</th>
          <th>Requested Date</th>
          <th>Processed Date</th>
          <th>Admin Note</th>
        </tr>
      </thead>
      <tbody>
        @forelse($withdrawals as $withdrawal)
          <tr>
            <td class="fw-semibold text-success">₹{{ number_format($withdrawal->amount, 2) }}</td>
            <td>{{ $withdrawal->payout_method === 'upi' ? 'UPI' : 'Bank Transfer' }}</td>
            <td><x-withdrawal-status-badge :status="$withdrawal->status" /></td>
            <td class="text-muted small">{{ $withdrawal->requested_at?->copy()->timezone($displayTimezone)->format('d M Y, h:i A') ?? '—' }}</td>
            <td class="text-muted small">{{ $withdrawal->processed_at?->copy()->timezone($displayTimezone)->format('d M Y, h:i A') ?? '—' }}</td>
            <td class="text-muted small">{{ $withdrawal->admin_note ?: '—' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">No withdrawal requests yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">{{ $withdrawals->links() }}</div>
@endsection
