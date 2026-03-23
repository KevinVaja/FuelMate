@extends('layouts.auth')
@section('title', 'Register')
@section('content')
<div class="text-center mb-4 pt-4">
  <img src="{{ asset('brand/fuelmate-logo.svg') }}" alt="FuelMate" class="auth-brand-logo">
  <h2 class="fw-bold">Create Account</h2>
  <p>Join FuelMate today</p>
</div>
<div class="auth-card">
  @if($errors->any())
    <div class="alert alert-danger rounded-3 small">{{ $errors->first() }}</div>
  @endif
  @php $showAgentDocuments = old('role', 'user') === 'agent'; @endphp
  <form method="POST" action="/register" enctype="multipart/form-data">
    @csrf
    <div class="mb-3">
      <label class="form-label">Full Name</label>
      <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="Your full name" value="{{ old('name') }}" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Email Address</label>
      <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="name@example.com" value="{{ old('email') }}" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Phone Number</label>
      <input type="text" name="phone" class="form-control" placeholder="+91 9876543210" value="{{ old('phone') }}">
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Confirm Password</label>
      <input type="password" name="password_confirmation" class="form-control" placeholder="Repeat password" required>
    </div>
    <div class="mb-4">
      <label class="form-label">Register As</label>
      <select name="role" id="registrationRole" class="form-control">
        <option value="user" class="text-black" @selected(!$showAgentDocuments)>Customer</option>
        <option value="agent" class="text-black" @selected($showAgentDocuments)>Petrol Pump Business</option>
      </select>
    </div>
    <div id="agentDocumentFields" class="{{ $showAgentDocuments ? '' : 'd-none' }}">
      <div class="alert alert-info small">
        Upload petrol pump verification documents. Accepted formats: JPG, JPEG, PNG, and PDF up to 2 MB each.
      </div>
      <div class="mb-3">
        <label class="form-label">Petrol Pump License / Dealership Certificate</label>
        <input type="file" name="petrol_license_photo" class="form-control @error('petrol_license_photo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf">
      </div>
      <div class="mb-3">
        <label class="form-label">GST Certificate</label>
        <input type="file" name="gst_certificate_photo" class="form-control @error('gst_certificate_photo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf">
      </div>
      <div class="mb-4">
        <label class="form-label">Owner Aadhaar or PAN</label>
        <input type="file" name="owner_id_proof_photo" class="form-control @error('owner_id_proof_photo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf">
      </div>
    </div>
    <button type="submit" class="btn btn-primary w-100">Create Account</button>
  </form>
</div>
<p class="text-center mt-3">Already have an account? <a href="/login">Sign In</a></p>
@endsection
@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const roleSelect = document.getElementById('registrationRole');
  const agentDocumentFields = document.getElementById('agentDocumentFields');
  const documentInputs = agentDocumentFields.querySelectorAll('input[type="file"]');

  const syncAgentDocumentFields = () => {
    const requiresDocuments = roleSelect.value === 'agent';

    agentDocumentFields.classList.toggle('d-none', !requiresDocuments);

    documentInputs.forEach((input) => {
      input.disabled = !requiresDocuments;
      input.required = requiresDocuments;
    });
  };

  syncAgentDocumentFields();
  roleSelect.addEventListener('change', syncAgentDocumentFields);
});
</script>
@endsection
