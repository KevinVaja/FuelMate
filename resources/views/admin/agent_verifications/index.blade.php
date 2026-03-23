@extends('layouts.app')
@section('title', 'Pump Verification')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Petrol Pump Verification Queue</h4>
    <p class="text-muted mb-0">Review pending document submissions from registered petrol pump businesses.</p>
  </div>
  <a href="{{ route('admin.agents') }}" class="btn btn-outline-secondary">
    <i class="fas fa-list me-2"></i>All Agents
  </a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Pump Name</th>
          <th>Phone</th>
          <th>GST Number</th>
          <th>Status</th>
          <th>View Documents</th>
          <th>Approve</th>
          <th>Reject</th>
        </tr>
      </thead>
      <tbody>
        @forelse($agents as $agent)
          <tr>
            <td>
              <div class="fw-semibold">{{ $agent->user->name ?? 'N/A' }}</div>
              <div class="text-muted small">{{ $agent->user->email ?? 'No email' }}</div>
            </td>
            <td>{{ $agent->user->phone ?? 'N/A' }}</td>
            <td>{{ $agent->getAttribute('gst_number') ?: 'N/A' }}</td>
            <td><x-verification-status-badge :status="$agent->verification_status" /></td>
            <td>
              <a href="{{ route('admin.agents.verify', $agent->id) }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-eye me-2"></i>View Documents
              </a>
            </td>
            <td>
              <form method="POST" action="{{ route('admin.agents.verification.approve', $agent->id) }}">
                @csrf
                <button type="submit" class="btn btn-success btn-sm">Approve</button>
              </form>
            </td>
            <td>
              <button
                type="button"
                class="btn btn-outline-danger btn-sm js-open-reject-modal"
                data-bs-toggle="modal"
                data-bs-target="#rejectAgentModal"
                data-agent-name="{{ $agent->user->name ?? 'Petrol Pump' }}"
                data-reject-url="{{ route('admin.agents.verification.reject', $agent->id) }}"
              >
                Reject
              </button>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">No petrol pump accounts are waiting for verification.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">{{ $agents->links() }}</div>

<div class="modal fade" id="rejectAgentModal" tabindex="-1" aria-labelledby="rejectAgentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="rejectAgentForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="rejectAgentModalLabel">Reject Petrol Pump</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">Provide a rejection reason for <strong id="rejectAgentName">this petrol pump</strong>.</p>
          <div class="mb-0">
            <label for="rejectionReason" class="form-label">Rejection Reason</label>
            <textarea name="rejection_reason" id="rejectionReason" class="form-control" rows="4" required>{{ old('rejection_reason') }}</textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Reject Petrol Pump</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const rejectButtons = document.querySelectorAll('.js-open-reject-modal');
  const rejectForm = document.getElementById('rejectAgentForm');
  const rejectAgentName = document.getElementById('rejectAgentName');

  rejectButtons.forEach((button) => {
    button.addEventListener('click', () => {
      rejectForm.action = button.dataset.rejectUrl;
      rejectAgentName.textContent = button.dataset.agentName;
    });
  });
});
</script>
@endsection
