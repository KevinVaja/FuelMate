@extends('layouts.app')
@section('title', 'Verify Petrol Pump')
@section('content')
@php($displayTimezone = config('app.display_timezone', 'Asia/Kolkata'))
<div class="d-flex justify-content-between align-items-start mb-4">
  <div>
    <h4 class="fw-bold mb-0">Petrol Pump Verification Review</h4>
    <p class="text-muted mb-0">Review documents and update the approval status for this petrol pump account.</p>
  </div>
  <a href="{{ route('admin.agents.pending') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>Back to Queue
  </a>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header fw-semibold">Pump Details</div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Pump Name</div>
          <div class="fw-semibold">{{ $agent->user->name ?? 'N/A' }}</div>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Email</div>
          <div>{{ $agent->user->email ?? 'N/A' }}</div>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Phone</div>
          <div>{{ $agent->user->phone ?? 'N/A' }}</div>
        </div>
        <div class="mb-3">
          <div class="text-muted small">GST Number</div>
          <div>{{ $agent->getAttribute('gst_number') ?: 'N/A' }}</div>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Verification Status</div>
          <div><x-verification-status-badge :status="$agent->verification_status" /></div>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Approved At</div>
          <div>{{ $agent->approved_at?->copy()->timezone($displayTimezone)->format('d M Y, h:i A') ?? 'Not approved yet' }}</div>
        </div>
        @if($agent->rejection_reason)
          <div class="alert alert-danger small mb-0">
            <strong>Rejection Reason:</strong> {{ $agent->rejection_reason }}
          </div>
        @endif
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header fw-semibold">Verification Actions</div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.agents.verification.approve', $agent->id) }}" class="mb-3">
          @csrf
          <button type="submit" class="btn btn-success w-100">
            <i class="fas fa-check me-2"></i>Approve Petrol Pump
          </button>
        </form>
        <form method="POST" action="{{ route('admin.agents.verification.reject', $agent->id) }}">
          @csrf
          <div class="mb-3">
            <label for="rejection_reason" class="form-label">Rejection Reason</label>
            <textarea name="rejection_reason" id="rejection_reason" rows="4" class="form-control @error('rejection_reason') is-invalid @enderror" required>{{ old('rejection_reason', $agent->rejection_reason) }}</textarea>
          </div>
          <button type="submit" class="btn btn-outline-danger w-100">
            <i class="fas fa-ban me-2"></i>Reject Petrol Pump
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="row g-4">
      <div class="col-12">
        <x-document-preview-card
          title="Petrol Pump License / Dealership Certificate"
          :url="route('admin.agents.documents.show', ['id' => $agent->id, 'document' => 'petrol_license_photo'])"
          :is-pdf="$agent->documentIsPdf('petrol_license_photo')"
        />
      </div>
      <div class="col-12">
        <x-document-preview-card
          title="GST Certificate"
          :url="route('admin.agents.documents.show', ['id' => $agent->id, 'document' => 'gst_certificate_photo'])"
          :is-pdf="$agent->documentIsPdf('gst_certificate_photo')"
        />
      </div>
      <div class="col-12">
        <x-document-preview-card
          title="Owner Aadhaar / PAN"
          :url="route('admin.agents.documents.show', ['id' => $agent->id, 'document' => 'owner_id_proof_photo'])"
          :is-pdf="$agent->documentIsPdf('owner_id_proof_photo')"
        />
      </div>
    </div>
  </div>
</div>
@endsection
