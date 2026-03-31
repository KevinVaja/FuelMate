@extends('layouts.auth')
@section('title', 'Sign In')
@section('content')
<div class="text-center mb-4 pt-4">
  <img src="{{ asset('brand/fuelmate-logo.svg') }}" alt="FuelMate" class="auth-brand-logo">
  <h2 class="fw-bold">Welcome Back</h2>
  <p>Sign in to your FuelMate account</p>
</div>
<div class="auth-card">
  @if($errors->any())
    <div class="alert alert-danger rounded-3 small">{{ $errors->first() }}</div>
  @endif
  <form method="POST" action="/admin-login">
    @csrf
    <div class="mb-3">
      <label class="form-label">Email Address</label>
      <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="name@example.com" value="{{ old('email') }}" required>
    </div>
    <div class="mb-4">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary w-100 mb-3">Sign In</button>
  </form>
</div>
<p class="text-center mt-3">Don't have an account? <a href="/register">Register</a></p>
@endsection
@section('scripts')
<script>
function fillDemo(email, pass) {
  document.querySelector('[name=email]').value = email;
  document.querySelector('[name=password]').value = pass;
}
</script>
@endsection
