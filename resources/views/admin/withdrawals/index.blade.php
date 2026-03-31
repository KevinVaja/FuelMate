@extends('layouts.app')
@section('title', 'Agent Withdrawals')
@section('content')
@php($displayTimezone = config('app.display_timezone', 'Asia/Kolkata'))
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold mb-0">Agent Withdrawal Requests</h4>
    <p class="text-muted mb-0">Review and complete manual payouts for petrol pump partners.</p>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" action="{{ route('admin.withdrawals.index') }}" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label for="status" class="form-label small text-muted mb-1">Status</label>
        <select name="status" id="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          @foreach(['pending', 'approved', 'completed', 'rejected'] as $filterStatus)
            <option value="{{ $filterStatus }}" {{ $status === $filterStatus ? 'selected' : '' }}>
              {{ ucfirst($filterStatus) }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">
          <i class="fas fa-filter me-1"></i>Filter
        </button>
      </div>
      <div class="col-md-2">
        <a href="{{ route('admin.withdrawals.index') }}" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Pump Name</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Status</th>
          <th>Requested</th>
          <th>Processed</th>
          <th>Admin Note</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($withdrawals as $withdrawal)
          <tr>
            <td>
              <div class="fw-semibold">{{ $withdrawal->agent->user->name ?? 'N/A' }}</div>
              <div class="text-muted small">{{ $withdrawal->agent->user->email ?? 'No email' }}</div>
            </td>
            <td>
              <div class="fw-semibold text-success">₹{{ number_format($withdrawal->amount, 2) }}</div>
              <div class="text-muted small">Wallet: ₹{{ number_format($withdrawal->agent->wallet_balance, 2) }}</div>
            </td>
            <td>
              <div>{{ $withdrawal->payout_method === 'upi' ? 'UPI' : 'Bank Transfer' }}</div>
              <div class="text-muted small">
                {{ $withdrawal->payout_method === 'upi' ? ($withdrawal->upi_id ?: '—') : ($withdrawal->account_number ?: '—') }}
              </div>
            </td>
            <td><x-withdrawal-status-badge :status="$withdrawal->status" /></td>
            <td class="text-muted small">{{ $withdrawal->requested_at?->copy()->timezone($displayTimezone)->format('d M Y, h:i A') ?? '—' }}</td>
            <td class="text-muted small">{{ $withdrawal->processed_at?->copy()->timezone($displayTimezone)->format('d M Y, h:i A') ?? '—' }}</td>
            <td class="text-muted small">{{ $withdrawal->admin_note ?: '—' }}</td>
            <td class="text-end">
              <div class="d-flex justify-content-end flex-wrap gap-2">
                @if($withdrawal->status === 'pending')
                  <form method="POST" action="{{ route('admin.withdrawals.approve', $withdrawal->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                  </form>
                @endif

                @if($withdrawal->status === 'approved')
                  <form method="POST" action="{{ route('admin.withdrawals.complete', $withdrawal->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Mark Completed</button>
                  </form>
                @endif

                @if(in_array($withdrawal->status, ['pending', 'approved'], true))
                  <button
                    type="button"
                    class="btn btn-outline-danger btn-sm js-open-withdrawal-reject-modal"
                    data-bs-toggle="modal"
                    data-bs-target="#rejectWithdrawalModal"
                    data-agent-name="{{ $withdrawal->agent->user->name ?? 'Petrol Pump' }}"
                    data-reject-url="{{ route('admin.withdrawals.reject', $withdrawal->id) }}"
                  >
                    Reject
                  </button>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="text-center py-5 text-muted">No withdrawal requests found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">{{ $withdrawals->links() }}</div>

<div class="modal fade" id="rejectWithdrawalModal" tabindex="-1" aria-labelledby="rejectWithdrawalModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="rejectWithdrawalForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="rejectWithdrawalModalLabel">Reject Withdrawal</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">Add a rejection note for <strong id="rejectWithdrawalAgentName">this petrol pump</strong>.</p>
          <div class="mb-0">
            <label for="admin_note" class="form-label">Admin Note</label>
            <textarea name="admin_note" id="admin_note" class="form-control" rows="4" required>{{ old('admin_note') }}</textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Reject Withdrawal</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const rejectButtons = document.querySelectorAll('.js-open-withdrawal-reject-modal');
  const rejectForm = document.getElementById('rejectWithdrawalForm');
  const rejectAgentName = document.getElementById('rejectWithdrawalAgentName');

  rejectButtons.forEach((button) => {
    button.addEventListener('click', () => {
      rejectForm.action = button.dataset.rejectUrl;
      rejectAgentName.textContent = button.dataset.agentName;
    });
  });
});
</script>
@endsection
