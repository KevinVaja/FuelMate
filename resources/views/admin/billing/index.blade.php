@extends('layouts.app')
@section('title', 'Billing Dashboard')
@section('content')
<div class="mb-4">
  <h4 class="fw-bold mb-0">Billing Dashboard</h4>
  <p class="text-muted mb-0">Revenue, GST, fees, and payout visibility for FuelMate orders</p>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-success"><div class="card-body py-3"><div class="text-muted small mb-1">Revenue Today</div><div class="fs-4 fw-bold">₹{{ number_format((float) $summary['total_revenue_today'], 2) }}</div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-info"><div class="card-body py-3"><div class="text-muted small mb-1">GST Collected</div><div class="fs-4 fw-bold">₹{{ number_format((float) $summary['total_gst_collected'], 2) }}</div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card"><div class="card-body py-3"><div class="text-muted small mb-1">Platform Fee</div><div class="fs-4 fw-bold">₹{{ number_format((float) $summary['total_platform_fee'], 2) }}</div></div></div></div>
  <div class="col-sm-6 col-lg-3"><div class="card stat-card stat-accent-warning"><div class="card-body py-3"><div class="text-muted small mb-1">Agent Payout Pending</div><div class="fs-4 fw-bold">₹{{ number_format((float) $summary['total_agent_payout_pending'], 2) }}</div></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-4"><div class="card"><div class="card-body py-3"><div class="text-muted small mb-1">Refund Pending</div><div class="fs-4 fw-bold text-primary">₹{{ number_format((float) $summary['total_refund_pending'], 2) }}</div></div></div></div>
  <div class="col-sm-6 col-lg-4"><div class="card"><div class="card-body py-3"><div class="text-muted small mb-1">Refunded</div><div class="fs-4 fw-bold text-success">₹{{ number_format((float) $summary['total_refunded'], 2) }}</div></div></div></div>
  <div class="col-sm-6 col-lg-4"><div class="card"><div class="card-body py-3"><div class="text-muted small mb-1">Cancellation Charges</div><div class="fs-4 fw-bold text-warning">₹{{ number_format((float) $summary['total_cancellation_charges'], 2) }}</div></div></div></div>
</div>

<div class="card mb-4 border-primary border-opacity-25">
  <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div>
      <div class="small text-muted text-uppercase">Orders Billed</div>
      <div class="fs-3 fw-bold">{{ $summary['total_orders_billed'] }}</div>
    </div>
    <div class="small text-muted">Settlement-ready billings remain visible here until downstream payout and refund workflows consume them.</div>
  </div>
</div>

<div class="card">
  <div class="card-header">Recent Billing Records</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>Order</th><th>Customer</th><th>Agent</th><th>Total</th><th>GST</th><th>Platform</th><th>Agent Earning</th><th>Refundable</th><th>Refund</th><th>Billing</th><th>Settlement</th><th>Actions</th><th>Date</th></tr></thead>
      <tbody>
        @forelse($billings as $billing)
        @php
          $refundBadgeClass = match ($billing->refund_status) {
            'pending' => 'badge-status-refund_processing',
            'approved' => 'badge-status-approved',
            'refunded' => 'badge-status-delivered',
            default => 'bg-light text-dark',
          };
        @endphp
        <tr>
          <td class="text-muted small">{{ $billings->firstItem() + $loop->index }}</td>
          <td>{{ $billing->order->user->name ?? '—' }}</td>
          <td>{{ $billing->order->agent?->user?->name ?? 'Awaiting assignment' }}</td>
          <td class="fw-semibold">₹{{ number_format((float) $billing->total_amount, 2) }}</td>
          <td>₹{{ number_format((float) $billing->gst_amount, 2) }}</td>
          <td>₹{{ number_format((float) $billing->platform_fee, 2) }}</td>
          <td class="text-success fw-semibold">₹{{ number_format((float) $billing->agent_earning, 2) }}</td>
          <td>₹{{ number_format((float) ($billing->refundable_amount ?? 0), 2) }}</td>
          <td>
            <span class="badge {{ $refundBadgeClass }}">{{ strtoupper($billing->refund_status) }}</span>
            @if($billing->refund_processed_at)
            <div class="small text-muted mt-1">{{ $billing->refund_processed_at->format('d M Y') }}</div>
            @endif
          </td>
          <td><span class="badge bg-light text-dark text-uppercase">{{ $billing->billing_status }}</span></td>
          <td><span class="badge bg-secondary-subtle text-secondary-emphasis text-uppercase">{{ $billing->settlement_status }}</span></td>
          <td>
            @if($billing->order?->canApproveRefund())
            <form method="POST" action="{{ route('admin.orders.refunds.approve', $billing->order_id) }}" onsubmit="return confirm('Approve this refund and credit the customer wallet?')">
              @csrf
              <button type="submit" class="btn btn-sm btn-outline-primary w-100">Approve Refund</button>
            </form>
            @else
            <span class="text-muted small">No action</span>
            @endif
          </td>
          <td class="text-muted small">{{ $billing->updated_at->format('d M Y') }}</td>
        </tr>
        @empty
        <tr><td colspan="13" class="text-center py-4 text-muted">No billing records found yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div class="mt-3">{{ $billings->links() }}</div>
@endsection
