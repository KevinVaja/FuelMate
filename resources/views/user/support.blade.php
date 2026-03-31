@extends('layouts.app')
@section('title', 'Support')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Support</h4><p class="text-muted mb-0">Contact our team for help</p></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ticketModal"><i class="fas fa-plus me-2"></i>New Ticket</button>
</div>

@if($tickets->isEmpty())
  <div class="card text-center py-5"><div class="card-body"><i class="fas fa-headset fa-4x text-muted opacity-25 mb-3"></i><h5 class="fw-bold">No support tickets</h5><p class="text-muted">Create a ticket if you need help.</p></div></div>
@else
  <div class="d-flex flex-column gap-3">
    @foreach($tickets as $ticket)
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1">
              <h6 class="fw-bold mb-0">{{ $ticket->subject }}</h6>
              @php $sc = ['open'=>'warning','in_progress'=>'info','resolved'=>'success','closed'=>'secondary']; @endphp
              <span class="badge bg-{{ $sc[$ticket->status] ?? 'secondary' }} bg-opacity-75">{{ ucwords(str_replace('_',' ',$ticket->status)) }}</span>
            </div>
            <p class="text-muted small mb-0">{{ $ticket->message }}</p>
            @if($ticket->admin_response)
            <div class="mt-3 p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25">
              <small class="text-primary fw-bold d-block mb-1">Support Response:</small>
              <p class="small mb-0">{{ $ticket->admin_response }}</p>
            </div>
            @endif
          </div>
          <small class="text-muted ms-3 flex-shrink-0">{{ $ticket->created_at->format('M d, Y') }}</small>
        </div>
      </div>
    </div>
    @endforeach
  </div>
@endif

<div class="modal fade" id="ticketModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Support Ticket</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="{{ route('user.support.store') }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3"><label class="form-label fw-600">Subject</label><input type="text" name="subject" class="form-control" placeholder="Brief description of your issue..." required></div>
          <div class="mb-3"><label class="form-label fw-600">Message</label><textarea name="message" class="form-control" rows="4" placeholder="Describe your issue in detail..." required></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Submit Ticket</button></div>
      </form>
    </div>
  </div>
</div>
@endsection
