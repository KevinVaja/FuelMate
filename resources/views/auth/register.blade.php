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
  @php
    $showAgentDocuments = old('role', 'user') === 'agent';
    $verifiedRegistrationEmail = session('email_otp.registration.verified.email');
    $emailAlreadyVerified = is_string($verifiedRegistrationEmail)
      && strtolower($verifiedRegistrationEmail) === strtolower((string) old('email'));
  @endphp
  <form method="POST" action="/register" enctype="multipart/form-data" id="registrationForm">
    @csrf
    <div class="mb-3">
      <label class="form-label">Full Name</label>
      <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="Your full name" value="{{ old('name') }}" required>
    </div>
    <div class="mb-2">
      <label class="form-label">Email Address</label>
      <div class="d-flex gap-2 align-items-start">
        <input type="email" id="registrationEmail" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="name@example.com" value="{{ old('email') }}" required>
        <button type="button" class="btn btn-outline-primary text-nowrap" id="sendRegistrationOtpBtn">
          {{ $emailAlreadyVerified ? 'Resend Code' : 'Send Code' }}
        </button>
      </div>
      <div class="small text-muted mt-2">Enter your Valid Email Address.</div>
    </div>
    <div id="registrationEmailStatus" class="alert {{ $emailAlreadyVerified ? 'alert-success' : 'alert-secondary' }} small py-2 mb-3">
      {{ $emailAlreadyVerified ? 'This email is already verified for the current registration attempt.' : 'Send a 6-digit verification code to unlock registration.' }}
    </div>
    <div id="registrationOtpSection" class="mb-3 {{ $emailAlreadyVerified ? '' : 'd-none' }}">
      <label class="form-label">Email Verification Code</label>
      <div class="d-flex gap-2 align-items-start">
        <input type="text" id="registrationOtp" class="form-control text-center fw-bold" placeholder="Enter 6-digit code" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
        <button type="button" class="btn btn-primary text-nowrap" id="verifyRegistrationOtpBtn">Verify Email</button>
      </div>
      <div class="small text-muted mt-2">Enter the code sent to your inbox before you submit the full registration form.</div>
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
    <button type="submit" class="btn btn-primary w-100" id="registerSubmitBtn" {{ $emailAlreadyVerified ? '' : 'disabled' }}>Create Account</button>
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
  const emailInput = document.getElementById('registrationEmail');
  const otpInput = document.getElementById('registrationOtp');
  const sendOtpButton = document.getElementById('sendRegistrationOtpBtn');
  const verifyOtpButton = document.getElementById('verifyRegistrationOtpBtn');
  const otpSection = document.getElementById('registrationOtpSection');
  const emailStatus = document.getElementById('registrationEmailStatus');
  const submitButton = document.getElementById('registerSubmitBtn');
  const fallbackCsrfToken = '{{ csrf_token() }}';

  let emailVerified = @json($emailAlreadyVerified);
  let otpRequestInFlight = false;
  let otpVerifyInFlight = false;

  const syncAgentDocumentFields = () => {
    const requiresDocuments = roleSelect.value === 'agent';

    agentDocumentFields.classList.toggle('d-none', !requiresDocuments);

    documentInputs.forEach((input) => {
      input.disabled = !requiresDocuments;
      input.required = requiresDocuments;
    });
  };

  const setEmailStatus = (message, tone = 'secondary') => {
    emailStatus.className = `alert alert-${tone} small py-2 mb-3`;
    emailStatus.textContent = message;
  };

  const syncRegistrationState = () => {
    submitButton.disabled = !emailVerified;
    otpInput.disabled = emailVerified;
    verifyOtpButton.disabled = emailVerified;
    sendOtpButton.textContent = emailVerified ? 'Resend Code' : 'Send Code';
  };

  const parseJson = async (response) => {
    try {
      return await response.json();
    } catch (error) {
      return null;
    }
  };

  const getXsrfToken = () => {
    const cookie = document.cookie
      .split('; ')
      .find((entry) => entry.startsWith('XSRF-TOKEN='));

    if (!cookie) {
      return fallbackCsrfToken;
    }

    return decodeURIComponent(cookie.substring('XSRF-TOKEN='.length));
  };

  const normalizedEmail = () => emailInput.value.trim().toLowerCase();

  const invalidateEmailVerification = (showReminder = true) => {
    emailVerified = false;
    otpInput.disabled = false;
    verifyOtpButton.disabled = false;

    if (normalizedEmail() !== '') {
      otpSection.classList.remove('d-none');
    } else {
      otpSection.classList.add('d-none');
    }

    if (showReminder) {
      setEmailStatus('Email changed. Send a fresh verification code for this address before creating the account.');
    }

    syncRegistrationState();
  };

  sendOtpButton.addEventListener('click', async () => {
    if (otpRequestInFlight) {
      return;
    }

    if (!emailInput.reportValidity()) {
      return;
    }

    otpRequestInFlight = true;
    sendOtpButton.disabled = true;
    sendOtpButton.textContent = 'Sending...';

    try {
      const response = await fetch('{{ route('register.email_otp.send', [], false) }}', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': fallbackCsrfToken,
          'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify({
          email: emailInput.value.trim(),
        }),
      });

      const payload = await parseJson(response);

      if (!response.ok || !payload?.status) {
        throw new Error(payload?.message ?? 'Unable to send the verification code right now.');
      }

      emailVerified = false;
      otpInput.value = '';
      otpSection.classList.remove('d-none');
      setEmailStatus(payload.message ?? 'Verification code sent successfully.', 'info');
    } catch (error) {
      setEmailStatus(error.message || 'Unable to send the verification code right now.', 'danger');
    } finally {
      otpRequestInFlight = false;
      sendOtpButton.disabled = false;
      syncRegistrationState();
    }
  });

  verifyOtpButton.addEventListener('click', async () => {
    if (otpVerifyInFlight) {
      return;
    }

    if (!emailInput.reportValidity()) {
      return;
    }

    const otp = otpInput.value.trim();
    if (!/^\d{6}$/.test(otp)) {
      setEmailStatus('Enter the 6-digit code that was sent to your email inbox.', 'warning');
      return;
    }

    otpVerifyInFlight = true;
    verifyOtpButton.disabled = true;
    verifyOtpButton.textContent = 'Verifying...';

    try {
      const response = await fetch('{{ route('register.email_otp.verify', [], false) }}', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': fallbackCsrfToken,
          'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify({
          email: emailInput.value.trim(),
          otp,
        }),
      });

      const payload = await parseJson(response);

      if (!response.ok || !payload?.status) {
        throw new Error(payload?.message ?? 'Unable to verify the code right now.');
      }

      emailVerified = true;
      setEmailStatus(payload.message ?? 'Email verified successfully. You can finish registration now.', 'success');
    } catch (error) {
      emailVerified = false;
      setEmailStatus(error.message || 'Unable to verify the code right now.', 'danger');
    } finally {
      otpVerifyInFlight = false;
      verifyOtpButton.textContent = 'Verify Email';
      syncRegistrationState();
    }
  });

  otpInput.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') {
      return;
    }

    event.preventDefault();
    verifyOtpButton.click();
  });

  emailInput.addEventListener('input', () => {
    invalidateEmailVerification(normalizedEmail() !== '');
  });

  syncAgentDocumentFields();
  syncRegistrationState();
  roleSelect.addEventListener('change', syncAgentDocumentFields);
});
</script>
@endsection
