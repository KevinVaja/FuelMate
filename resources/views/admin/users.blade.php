@extends('layouts.app')
@section('title', 'Users')
@section('content')
  <div class="mb-4">
    <h4 class="fw-bold mb-0">User Management</h4>
    <p class="text-muted mb-0">All registered customers</p>
  </div>

  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="GET" action="{{ route('admin.users') }}" class="row g-2 align-items-end">
        <div class="col-md-4"><input type="text" name="search" class="form-control form-control-sm"
            placeholder="Search name, email..." value="{{ request('search') }}"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm">Search</button></div>
        <div class="col-md-2"><a href="{{ route('admin.users') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Orders</th>
            <th>Status</th>
            <th>Joined</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($users as $user)
            <tr>
              <td class="text-muted small">{{ $user->id }}</td>
              <td>
                <div class="fw-bold">{{ $user->name }}</div>
              </td>
              <td class="text-muted small">{{ $user->email }}</td>
              <td class="text-muted small">{{ $user->phone ?? '—' }}</td>
              <td><span class="badge bg-light text-dark">{{ $user->fuel_requests_count }}</span></td>
              <td><span
                  class="badge bg-{{ $user->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($user->status) }}</span>
              </td>
              <td class="text-muted small">{{ $user->created_at->format('M d, Y') }}</td>
              <td>
                <form method="POST" action="{{ route('admin.users.toggle', $user->id) }}">
                  @csrf @method('PATCH')
                  <button type="submit" class="btn btn-sm btn-outline-{{ $user->status === 'active' ? 'danger' : 'success' }}">{{ $user->status === 'active' ? 'Suspend' : 'Activate' }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">No users found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $users->links() }}</div>
@endsection