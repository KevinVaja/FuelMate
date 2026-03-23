@extends('layouts.app')
@section('title', 'Support Tickets')
@section('content')
<div class="mb-4"><h4 class="fw-bold mb-0">Support Tickets</h4><p class="text-muted mb-0">Manage customer support requests</p></div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" action="{{ route('admin.support') }}" class="row g-2 align-items-end">
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Status</option>
          @foreach(['open','in_progress','resolved','closed'] as $s)
          <option value="{{ $s }}" {{ request('status')===$s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div>
      <div class="col-md-2"><a href="{{ route('admin.support') }}" class="btn btn-outline-secondary btn-sm w-100">Clear</a></div>
    </form>
  </div>
</div>

@forelse($tickets as $ticket)
<div class="card mb-3">
  <div class="card-body">
    <div class="row g-3 align-items-start">
      <div class="col-md-8">
        <div class="d-flex align-items-center gap-2 mb-2">
          @php $sc = ['open'=>'warning','in_progress'=>'info','resolved'=>'success','closed'=>'secondary']; @endphp
          <span class="badge bg-{{ $sc[$ticket->status] ?? 'secondary' }} bg-opacity-75">{{ ucwords(str_replace('_',' ',$ticket->status)) }}</span>
          <h6 class="mb-0 fw-bold">{{ $ticket->subject }}</h6>
        </div>
        <p class="text-muted small mb-2">{{ $ticket->message }}</p>
        <div class="text-muted small"><i class="fas fa-user me-1"></i>{{ $ticket->user->name ?? '—' }} · {{ $ticket->created_at->format('M d, Y') }}</div>
        @if($ticket->admin_response)
        <div class="mt-2 p-2 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25">
          <small class="text-success fw-bold d-block">Response:</small>
          <p class="small mb-0">{{ $ticket->admin_response }}</p>
        </div>
        @endif
      </div>
      <div class="col-md-4">
        <form method="POST" action="{{ route('admin.support.respond', $ticket->id) }}">
          @csrf @method('PATCH')
          <div class="mb-2"><textarea name="admin_response" class="form-control form-control-sm" rows="3" placeholder="Type your response...">{{ $ticket->admin_response }}</textarea></div>
          <div class="mb-2">
            <select name="status" class="form-select form-select-sm">
              @foreach(['open','in_progress','resolved','closed'] as $s)
              <option value="{{ $s }}" {{ $ticket->status === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
              @endforeach
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100">Update Ticket</button>
        </form>
      </div>
    </div>
  </div>
</div>
@empty
<div class="card text-center py-5"><div class="card-body"><i class="fas fa-headset fa-4x text-muted opacity-25 mb-3"></i><h5 class="fw-bold">No tickets found</h5></div></div>
@endforelse
<div class="mt-3">{{ $tickets->links() }}</div>
@endsection
