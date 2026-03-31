@extends('layouts.app')
@section('title', 'Request Withdrawal')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold mb-0">Request Withdrawal</h4>
    <p class="text-muted mb-0">Submit your payout details for manual processing by the admin team.</p>
  </div>
  <a href="{{ route('agent.withdrawals.index') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>Back to History
  </a>
</div>

@if($errors->any())
  <div class="alert alert-danger">
    <div class="fw-semibold mb-2">Please fix the highlighted fields.</div>
    <ul class="mb-0 ps-3">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body">
        <div class="text-muted small mb-1">Available Wallet Balance</div>
        <div class="fs-3 fw-bold text-success mb-2">₹{{ number_format($walletBalance, 2) }}</div>
        <div class="small text-muted mb-3">Only completed payouts deduct this balance.</div>
        <div class="border rounded-3 p-3 bg-light">
          <div class="small text-muted mb-1">Minimum withdrawal</div>
          <div class="fw-semibold">₹{{ number_format($minimumWithdrawalAmount, 0) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Withdrawal Details</div>
      <div class="card-body">
        <form method="POST" action="{{ route('agent.withdrawals.store') }}">
          @csrf
          <div class="row g-3">
            <div class="col-md-6">
              <label for="amount" class="form-label fw-semibold">Amount</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.01" min="{{ $minimumWithdrawalAmount }}" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                @error('amount')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <div class="col-md-6">
              <label for="payout_method" class="form-label fw-semibold">Payout Method</label>
              <select name="payout_method" id="payout_method" class="form-select @error('payout_method') is-invalid @enderror" required>
                <option value="bank" {{ old('payout_method', 'bank') === 'bank' ? 'selected' : '' }}>Bank</option>
                <option value="upi" {{ old('payout_method') === 'upi' ? 'selected' : '' }}>UPI</option>
              </select>
              @error('payout_method')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div id="bankFields" class="mt-4">
            <h6 class="fw-bold mb-3">Bank Details</h6>
            <div class="row g-3">
              <div class="col-md-4">
                <label for="account_holder_name" class="form-label">Account Holder Name</label>
                <input type="text" name="account_holder_name" id="account_holder_name" class="form-control @error('account_holder_name') is-invalid @enderror" value="{{ old('account_holder_name') }}">
                @error('account_holder_name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="col-md-4">
                <label for="account_number" class="form-label">Account Number</label>
                <input type="text" name="account_number" id="account_number" class="form-control @error('account_number') is-invalid @enderror" value="{{ old('account_number') }}">
                @error('account_number')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="col-md-4">
                <label for="ifsc_code" class="form-label">IFSC Code</label>
                <input type="text" name="ifsc_code" id="ifsc_code" class="form-control @error('ifsc_code') is-invalid @enderror text-uppercase" value="{{ old('ifsc_code') }}">
                @error('ifsc_code')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>

          <div id="upiFields" class="mt-4">
            <h6 class="fw-bold mb-3">UPI Details</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="upi_id" class="form-label">UPI ID</label>
                <input type="text" name="upi_id" id="upi_id" class="form-control @error('upi_id') is-invalid @enderror" value="{{ old('upi_id') }}" placeholder="name@bank">
                @error('upi_id')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-paper-plane me-2"></i>Submit Withdrawal Request
            </button>
            <a href="{{ route('agent.withdrawals.index') }}" class="btn btn-light">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const payoutMethod = document.getElementById('payout_method');
  const bankFields = document.getElementById('bankFields');
  const upiFields = document.getElementById('upiFields');

  const toggleFields = () => {
    const isBank = payoutMethod.value === 'bank';
    bankFields.style.display = isBank ? 'block' : 'none';
    upiFields.style.display = isBank ? 'none' : 'block';
  };

  payoutMethod.addEventListener('change', toggleFields);
  toggleFields();
});
</script>
@endsection
