@extends('layouts.app')
@section('title', 'Invoice')
@section('content')
@php
  $billing = $order->billing;
@endphp

<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
  <div>
    <h4 class="fw-bold mb-0">Invoice for Order #{{ $order->displayOrderNumber() }}</h4>
    <p class="text-muted mb-0">FuelMate billing summary and settlement snapshot</p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('user.track', $order->id) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Tracking</a>
    <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card border-primary border-opacity-25">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Billing Breakdown</span>
        <span class="badge bg-light text-dark text-uppercase">{{ $billing?->billing_status ?? 'estimated' }}</span>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-4">
          <div class="col-md-4"><small class="text-muted d-block">Order Number</small><strong>#{{ $order->displayOrderNumber() }}</strong></div>
          <div class="col-md-4"><small class="text-muted d-block">Date</small><strong>{{ $order->created_at->format('d M Y, h:i A') }}</strong></div>
          <div class="col-md-4"><small class="text-muted d-block">Payment Method</small><strong>{{ $order->paymentMethodLabel() }}</strong></div>
          <div class="col-md-4"><small class="text-muted d-block">Fuel Type</small><strong>{{ $order->fuelProduct->name ?? '—' }}</strong></div>
          <div class="col-md-4"><small class="text-muted d-block">Quantity</small><strong>{{ number_format((float) $order->quantity_liters, 2) }} L</strong></div>
          <div class="col-md-4"><small class="text-muted d-block">Rate</small><strong>₹{{ number_format((float) ($billing?->fuel_price_per_liter ?? $order->fuel_price_per_liter), 2) }}/L</strong></div>
        </div>

        <div class="table-responsive">
          <table class="table table-borderless align-middle mb-0">
            <tbody>
              <tr>
                <td class="text-muted">Fuel total</td>
                <td class="text-end fw-semibold">₹{{ number_format((float) ($billing?->fuel_total ?? 0), 2) }}</td>
              </tr>
              <tr>
                <td class="text-muted">Delivery charge</td>
                <td class="text-end fw-semibold">₹{{ number_format((float) ($order->slab_charge ?: ($billing?->delivery_charge ?? $order->delivery_charge)), 2) }}</td>
              </tr>
              @if((float) $order->night_fee > 0)
              <tr>
                <td class="text-muted">Night delivery extra</td>
                <td class="text-end fw-semibold text-warning">₹{{ number_format((float) $order->night_fee, 2) }}</td>
              </tr>
              @endif
              <tr>
                <td class="text-muted">Platform fee</td>
                <td class="text-end fw-semibold">₹{{ number_format((float) ($billing?->platform_fee ?? 0), 2) }}</td>
              </tr>
              <tr>
                <td class="text-muted">GST ({{ number_format((float) ($billing?->gst_percent ?? 18), 0) }}%)</td>
                <td class="text-end fw-semibold">₹{{ number_format((float) ($billing?->gst_amount ?? 0), 2) }}</td>
              </tr>
              <tr class="border-top">
                <td class="fw-bold pt-3">Total amount</td>
                <td class="text-end fw-bold text-primary pt-3">₹{{ number_format((float) ($billing?->total_amount ?? $order->total_amount), 2) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card border-primary border-opacity-25 mb-3">
      <div class="card-header">Delivery Parties</div>
      <div class="card-body">
        <div class="mb-3">
          <small class="text-muted d-block">Customer</small>
          <div class="fw-semibold">{{ $order->user->name ?? '—' }}</div>
          <div class="small text-muted">{{ $order->user->email ?? '' }}</div>
        </div>
        <div class="mb-3">
          <small class="text-muted d-block">Agent</small>
          <div class="fw-semibold">{{ $order->agent->user->name ?? 'Awaiting assignment' }}</div>
          <div class="small text-muted">{{ $order->agent->vehicle_type ?? '—' }}</div>
        </div>
        <div>
          <small class="text-muted d-block">Billing Status</small>
          <div class="fw-semibold text-uppercase">{{ $billing?->billing_status ?? 'estimated' }}</div>
        </div>
      </div>
    </div>

    <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10">
      <div class="card-body">
        <h6 class="fw-bold text-primary mb-3"><i class="fas fa-circle-info me-2"></i>Invoice Notes</h6>
        <div class="small text-muted mb-2">GST and platform fee are stored centrally in FuelMate billing records.</div>
        <div class="small text-muted mb-2">Agent and admin settlement values are prepared for payout and refund workflows.</div>
        <div class="small text-muted mb-0">For COD orders, payment status turns paid after OTP-verified delivery.</div>
      </div>
    </div>
  </div>
</div>
@endsection
