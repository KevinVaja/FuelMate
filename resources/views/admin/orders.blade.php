@extends('layouts.app')
@section('title', 'All Orders')
@section('content')
<div class="mb-4"><h4 class="fw-bold mb-0">All Orders</h4><p class="text-muted mb-0">Manage all fuel delivery requests</p></div>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card text-center py-3"><div class="text-danger fs-3 fw-bold">{{ $summary['total_cancelled_orders'] }}</div><div class="text-muted small">Total Cancelled Orders</div></div></div>
  <div class="col-md-4"><div class="card text-center py-3"><div class="text-warning fs-3 fw-bold">₹{{ number_format((float) $summary['total_cancellation_charges_collected'], 2) }}</div><div class="text-muted small">Cancellation Charges Collected</div></div></div>
  <div class="col-md-4"><div class="card text-center py-3"><div class="text-primary fs-3 fw-bold">₹{{ number_format((float) $summary['total_refund_pending'], 2) }}</div><div class="text-muted small">Refund Pending</div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" action="{{ route('admin.orders') }}" class="row g-2 align-items-end">
      <div class="col-md-3"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search customer/address..." value="{{ request('search') }}"></div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Status</option>
          @foreach(['pending','accepted','fuel_preparing','on_the_way','arrived','otp_verification','delivered','refund_processing','cancelled','completed'] as $s)
          <option value="{{ $s }}" {{ request('status')===$s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Filter</button></div>
      <div class="col-md-2"><a href="{{ route('admin.orders') }}" class="btn btn-outline-secondary btn-sm w-100">Clear</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>#</th><th>Customer</th><th>Agent</th><th>Fuel</th><th>Amount</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($orders as $order)
        <tr>
          <td class="text-muted small">{{ $orders->firstItem() + $loop->index }}</td>
          <td><div>{{ $order->user->name ?? '—' }}</div><div class="text-muted small">{{ $order->user->phone ?? '' }}</div></td>
          <td>
            @if($order->agent?->user?->name)
              {{ $order->agent->user->name }}
            @elseif($order->agent?->name)
              {{ $order->agent->name }}
            @else
              <span class="text-warning small">Unassigned</span>
            @endif
          </td>
          <td><div>{{ $order->fuelProduct->name ?? '—' }}</div><div class="text-muted small">{{ $order->quantity_liters }}L</div></td>
          <td>
            <strong>₹{{ number_format((float) $order->total_amount, 2) }}</strong>
            @if($order->is_cancelled)
            <div class="text-danger small">Charge: ₹{{ number_format((float) $order->cancellation_charge, 2) }}</div>
            @endif
          </td>
          <td>
            <span class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : 'warning text-dark' }}">{{ ucfirst($order->payment_status) }}</span>
            <div class="small text-muted text-uppercase mt-1">{{ $order->paymentMethodLabel() }}</div>
          </td>
          <td>
            <span class="badge badge-status-{{ $order->status }}">{{ ucwords(str_replace('_',' ',$order->status)) }}</span>
            @if($order->billing?->refund_status && $order->billing->refund_status !== 'none')
            <div class="small text-muted text-uppercase mt-1">Refund: {{ $order->billing->refund_status }}</div>
            @endif
          </td>
          <td class="text-muted small">{{ $order->created_at->format('M d, Y') }}</td>
          <td>
            @if($order->canForceCancel())
            <form method="POST" action="{{ route('admin.orders.cancel', $order->id) }}" class="mb-2" onsubmit="return confirm('Force cancel this order?')">
              @csrf @method('PATCH')
              <input type="hidden" name="reason" value="Cancelled by admin.">
              <button type="submit" class="btn btn-sm btn-outline-danger w-100">Force Cancel</button>
            </form>
            @endif
            @if($order->canApproveRefund())
            <form method="POST" action="{{ route('admin.orders.refunds.approve', $order->id) }}" onsubmit="return confirm('Approve this refund and roll it back to the customer wallet?')">
              @csrf
              <button type="submit" class="btn btn-sm btn-outline-primary w-100">Approve Refund</button>
            </form>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="9" class="text-center py-4 text-muted">No orders found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div class="mt-3">{{ $orders->links() }}</div>
@endsection
