@extends('layouts.app')
@section('title', 'Agents')
@section('content')
  <div class="mb-4">
    <h4 class="fw-bold mb-0">Agent Management</h4>
    <p class="text-muted mb-0">Manage delivery agents</p>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Agent</th>
            <th>Vehicle</th>
            <th>Deliveries</th>
            <th>Rating</th>
            <th>Availability</th>
            <th>Approval</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($agents as $agent)
            <tr>
              <td class="text-muted small">{{ $agent->id }}</td>
              <td>
                <div class="fw-bold">{{ $agent->user->name ?? '—' }}</div>
                <div class="text-muted small">{{ $agent->user->email ?? '' }}</div>
                <div class="text-muted small">{{ $agent->user->phone ?? '' }}</div>
              </td>
              <td>
                <div>{{ $agent->vehicle_type }}</div>
                <div class="text-muted small">{{ $agent->vehicle_license_plate }}</div>
              </td>
              <td>{{ $agent->total_deliveries }}</td>
              <td>{{ $agent->rating ? $agent->rating . '★' : 'N/A' }}</td>
              <td><span
                  class="badge bg-{{ $agent->is_available ? 'success' : 'secondary' }}">{{ $agent->is_available ? 'Online' : 'Offline' }}</span>
              </td>
              <td><span
                  class="badge badge-status-{{ $agent->approval_status === 'pending' ? 'pending' : ($agent->approval_status === 'approved' ? 'completed' : 'cancelled') }}">{{ ucfirst($agent->approval_status) }}</span>
              </td>
              <td>
                @if($agent->approval_status === 'pending')
                  <div class="d-flex gap-1">
                    <form method="POST" action="{{ route('admin.agents.approve', $agent->id) }}">@csrf @method('PATCH')<button
                        type="submit" class="btn btn-sm btn-success">Approve</button></form>
                    <form method="POST" action="{{ route('admin.agents.reject', $agent->id) }}">@csrf @method('PATCH')<button
                        type="submit" class="btn btn-sm btn-danger">Reject</button></form>
                  </div>
                @elseif($agent->approval_status === 'approved')
                  <form method="POST" action="{{ route('admin.agents.reject', $agent->id) }}">@csrf @method('PATCH')<button
                      type="submit" class="btn btn-sm btn-outline-danger">Suspend</button></form>
                @else
                  <form method="POST" action="{{ route('admin.agents.approve', $agent->id) }}">@csrf @method('PATCH')<button
                      type="submit" class="btn btn-sm btn-outline-success">Re-approve</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">No agents found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $agents->links() }}</div>
@endsection